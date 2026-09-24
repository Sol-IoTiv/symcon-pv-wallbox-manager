<?php

/** Serialized command execution and a persistent, feedback-driven phase transition. */
trait ChargingControl
{
    private $controlDepth = 0;
    private $chargerSnapshot = null;
    private $desiredPhaseMode = null;
    private $energySnapshot = null;

    protected function now(): int { return time(); }

    private function controlled(callable $operation, bool $required = true)
    {
        if ($this->controlDepth > 0) return $operation();
        $key = 'PVWM.Control.' . $this->InstanceID;
        if (!IPS_SemaphoreEnter($key, $required ? 5000 : 1)) {
            if ($required) throw new RuntimeException('Laderegelung beschäftigt. Aktion bitte erneut ausführen.');
            return false;
        }
        $this->controlDepth++;
        $this->chargerSnapshot = null;
        $this->energySnapshot = null;
        try { return $operation(); }
        finally {
            $this->chargerSnapshot = null;
            $this->desiredPhaseMode = null;
            $this->energySnapshot = null;
            $this->controlDepth--;
            IPS_SemaphoreLeave($key);
        }
    }

    private function phaseState(): string { return $this->ReadAttributeString('PhaseTransitionState'); }
    private function hasChargingIntent(): bool
    {
        return (int)$this->GetValue('AccessStateV2') === 2 || $this->ReadAttributeBoolean('PhaseResumePending')
            || in_array($this->phaseState(), ['stopping', 'confirming'], true);
    }

    private function sendChargerCommand(string $key, int $value): bool
    {
        if (($key !== 'frc' || $value !== 1) &&
            (!$this->ReadPropertyBoolean('ModulAktiv') || $this->ReadAttributeBoolean('ControlStopRequested'))) return false;
        $ip = trim($this->ReadPropertyString('WallboxIP'));
        if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;
        $response = $this->simpleCurlGet('http://' . $ip . '/api/set?' . $key . '=' . $value);
        $body = is_string($response['result']) ? json_decode($response['result'], true) : null;
        $ok = (int)$response['httpcode'] === 200 && is_array($body) && ($body[$key] ?? null) === true;
        if (!$ok) $this->LogTemplate('error', 'Wallbox-Befehl nicht bestätigt', $key . '=' . $value . ', HTTP=' . $response['httpcode']);
        return $ok;
    }

    private function validChargerStatus(array $data): bool
    {
        foreach (['car','psm','frc','amp','err'] as $key) {
            if (!isset($data[$key]) || !is_int($data[$key])) return false;
        }
        if (!in_array($data['car'], [1,2,3,4], true) || !in_array($data['psm'], [0,1,2], true)
            || !in_array($data['frc'], [0,1,2], true) || $data['amp'] < 6 || $data['amp'] > 32) return false;
        if (!isset($data['alw']) || !is_bool($data['alw']) || !isset($data['nrg']) || !is_array($data['nrg'])) return false;
        foreach ([4,5,6,11] as $index) {
            if (!isset($data['nrg'][$index]) || !is_numeric($data['nrg'][$index]) || !is_finite((float)$data['nrg'][$index])) return false;
        }
        return (float)$data['nrg'][11] >= 0;
    }

    private function chargerStopped(array $data): bool
    {
        if ($data['frc'] !== 1 || $data['alw'] || $data['car'] === 2 || (float)$data['nrg'][11] > 30) return false;
        foreach ([4,5,6] as $index) if (abs((float)$data['nrg'][$index]) > 0.2) return false;
        return true;
    }

    private function clearTransition(bool $resume = false): void
    {
        $this->WriteAttributeString('PhaseTransitionState', 'idle');
        $this->WriteAttributeInteger('PhaseTransitionSamples', 0);
        $this->WriteAttributeInteger('PhaseTransitionSampleTime', 0);
        $this->WriteAttributeBoolean('PhaseResumePending', $resume);
        $this->SetTimerInterval('PVWM_PhaseTransition', 0);
    }

    private function stopCharging(bool $cancel = true): bool
    {
        if ($cancel && $this->phaseState() === 'confirming') return $this->phaseFault('Umschaltung vor Bestätigung abgebrochen');
        if ($cancel && $this->phaseState() !== 'fault') $this->clearTransition();
        $this->WriteAttributeInteger('LastSentChargingCurrent', 0);
        if ($this->chargerSnapshot !== null && $this->chargerStopped($this->chargerSnapshot)) return true;
        return $this->sendChargerCommand('frc', 1);
    }

    private function phaseFault(string $reason): bool
    {
        $this->WriteAttributeString('PhaseTransitionState', 'fault');
        $this->WriteAttributeString('PhaseTransitionError', $reason);
        $this->SetNoChargeReason('Phasensteuerung gesperrt: ' . $reason);
        $this->SetTimerInterval('PVWM_PhaseTransition', 0);
        $this->stopCharging(false);
        $this->LogTemplate('error', 'Phasensteuerung gesperrt', $reason);
        return false;
    }

    public function ResetPhaseFault(): bool
    {
        return $this->controlled(function () {
            $status = $this->getStatusFromCharger();
            if ($status === false || !$this->chargerStopped($status)) {
                $this->stopCharging(false);
                return false;
            }
            $this->clearTransition();
            $this->WriteAttributeString('PhaseTransitionError', '');
            $this->ClearNoChargeReason();
            return true;
        });
    }

    private function configuredPhaseCooldown(): int
    {
        return max(30, min(1800, $this->ReadPropertyInteger('PhaseSwitchCooldown')));
    }

    private function executeChargingPlan(int $phaseMode, int $ampere, bool $enable): bool
    {
        $data = $this->chargerSnapshot ?? $this->getStatusFromCharger();
        if ($data === false) return $this->phaseFault('Kein gültiger Wallboxstatus');
        if (!$this->ReadPropertyBoolean('ModulAktiv') || $this->ReadAttributeBoolean('ControlStopRequested') || !$enable || !$this->isCarConnected($data)) return $this->stopCharging();
        if ($this->phaseState() === 'fault') {
            $this->SetNoChargeReason('Phasensteuerung gesperrt: ' . $this->ReadAttributeString('PhaseTransitionError'));
            $this->stopCharging(false);
            return false;
        }
        if ($data['err'] !== 0) return $this->phaseFault('Wallbox meldet Fehler ' . $data['err']);
        if ($this->targetSocReached()) { $this->SetNoChargeReason('Ziel-SoC erreicht'); return $this->stopCharging(); }
        $phases = $this->phaseModeToPhaseCount($phaseMode);
        $ampere = $this->clampAmpere($ampere);
        $ampere = $this->applyMaxGridLoadLimit($ampere, $phases);
        if ($ampere < max(6, $this->ReadPropertyInteger('MinAmpere'))) {
            if ($this->GetNoChargeReason() === '') $this->SetNoChargeReason('Kein zulässiger Ladestrom');
            return $this->stopCharging();
        }
        $phaseMode = $phases === 3 ? self::PHASE_MODE_3P : self::PHASE_MODE_1P;
        $state = $this->phaseState();
        if (in_array($state, ['stopping','confirming'], true)) {
            if ($this->now() >= $this->ReadAttributeInteger('PhaseTransitionDeadline')) return $this->phaseFault('Zeitüberschreitung bei ' . $state);
            if ($this->now() - $this->ReadAttributeInteger('PhaseTransitionSampleTime') < 2) return false;
            $this->WriteAttributeInteger('PhaseTransitionSampleTime', $this->now());
            // Once switching has started, finish confirmation while stopped; never retarget mid-command.
            $samples = $this->chargerStopped($data) ? $this->ReadAttributeInteger('PhaseTransitionSamples') + 1 : 0;
            if ($state === 'confirming' && $data['psm'] !== $this->ReadAttributeInteger('PhaseTransitionTarget')) $samples = 0;
            $this->WriteAttributeInteger('PhaseTransitionSamples', $samples);
            if (!$this->chargerStopped($data) && !$this->stopCharging(false)) return $this->phaseFault('Ladestop abgelehnt');
            if ($samples < 2) return false;
            if ($state === 'stopping') {
                // The planner may have changed its mind while waiting for the stop.
                $this->WriteAttributeInteger('PhaseTransitionTarget', $phaseMode);
                if ($data['psm'] === $phaseMode) { $this->clearTransition(true); return false; }
                $this->WriteAttributeInteger('LetztePhasenUmschaltung', $this->now());
                if (!$this->sendChargerCommand('psm', $phaseMode)) return $this->phaseFault('Phasenbefehl abgelehnt');
                $this->WriteAttributeString('PhaseTransitionState', 'confirming');
                $this->WriteAttributeInteger('PhaseTransitionSamples', 0);
                $this->WriteAttributeInteger('PhaseTransitionDeadline', $this->now() + max(15, $this->ReadPropertyInteger('PhaseSwitchTimeout')));
                return false;
            }
            $this->WriteAttributeInteger('LetztePhasenUmschaltung', $this->now());
            $this->clearTransition(true);
            $this->resetNoPowerCounter();
            $this->LogTemplate('ok', 'Phasenwechsel bestätigt', (string)$data['psm']);
            // Re-evaluate the complete plan on the next normal cycle before resuming.
            return false;
        }
        if ($data['psm'] !== $phaseMode) {
            $last = $this->ReadAttributeInteger('LetztePhasenUmschaltung');
            if ($last > 0 && $this->now() - $last < $this->configuredPhaseCooldown()) {
                $this->SetNoChargeReason('Cooldown nach Phasenumschaltung aktiv');
                // Keep the observed phase, but never increase the requested power budget.
                if (!in_array($data['psm'], [1,2], true)) return $this->stopCharging();
                $actualPhases = $this->phaseModeToPhaseCount($data['psm']);
                $actualAmpere = (int)floor($ampere * $phases / $actualPhases);
                $boundedPhases = $actualPhases;
                $actualAmpere = $this->applyMaxGridLoadLimit($actualAmpere, $boundedPhases);
                if ($boundedPhases !== $actualPhases || $actualAmpere < max(6,$this->ReadPropertyInteger('MinAmpere'))) return $this->stopCharging();
                return $this->startAtCurrentPhase($data, $actualAmpere);
            }
            $this->WriteAttributeInteger('PhaseTransitionTarget', $phaseMode);
            $this->WriteAttributeInteger('PhaseTransitionSamples', 0);
            $this->WriteAttributeInteger('PhaseTransitionSampleTime', $this->now());
            $this->WriteAttributeInteger('PhaseTransitionDeadline', $this->now() + max(15,$this->ReadPropertyInteger('PhaseSwitchTimeout')));
            $this->WriteAttributeString('PhaseTransitionState', 'stopping');
            $this->WriteAttributeBoolean('PhaseResumePending', true);
            $this->SetNoChargeReason('Phasenumschaltung: Ladestop abwarten');
            $this->SetTimerInterval('PVWM_PhaseTransition', 2000);
            if (!$this->stopCharging(false)) return $this->phaseFault('Ladestop abgelehnt');
            return false;
        }
        return $this->startAtCurrentPhase($data, $ampere);
    }

    private function startAtCurrentPhase(array $data, int $ampere): bool
    {
        $ampere = $this->clampAmpere($ampere);
        if ($ampere < 6 || !$this->ReadPropertyBoolean('ModulAktiv') || $this->ReadAttributeBoolean('ControlStopRequested')) return $this->stopCharging();
        if ($data['amp'] !== $ampere) {
            if (!$this->sendChargerCommand('amp', $ampere)) return $this->phaseFault('Ladestrom abgelehnt');
            $this->WriteAttributeInteger('LastChargingCurrentChange', $this->now());
        }
        $this->WriteAttributeInteger('LastChargingCurrent', $ampere);
        $this->WriteAttributeInteger('LastSentChargingCurrent', $ampere);
        if (!$this->ReadPropertyBoolean('ModulAktiv') || $this->ReadAttributeBoolean('ControlStopRequested') || $this->targetSocReached()) return $this->stopCharging();
        if ($data['frc'] !== 2) {
            // Check again immediately before release (deactivation may have changed the property).
            if (!$this->ReadPropertyBoolean('ModulAktiv') || $this->ReadAttributeBoolean('ControlStopRequested') || $this->targetSocReached()) return $this->stopCharging();
            if (!$this->sendChargerCommand('frc', 2)) return $this->phaseFault('Ladefreigabe abgelehnt');
            $this->WriteAttributeInteger('LastManualStartTimestamp', $this->now());
        }
        $this->WriteAttributeBoolean('PhaseResumePending', false);
        return true;
    }

    private function targetSocReached(): bool
    {
        $soc = $this->ReadPropertyInteger('CarSOCID');
        $target = $this->ReadPropertyInteger('CarTargetSOCID');
        return $soc > 0 && $target > 0 && IPS_VariableExists($soc) && IPS_VariableExists($target)
            && is_numeric(GetValue($soc)) && is_numeric(GetValue($target)) && GetValue($soc) >= GetValue($target);
    }

    private function readGridPower(): ?float
    {
        $id = $this->ReadPropertyInteger('NetzleistungID');
        if ($id <= 0 || !IPS_VariableExists($id)) { $this->SetNoChargeReason('Netzlimit: Messvariable fehlt'); return null; }
        $meta = IPS_GetVariable($id);
        $value = GetValue($id);
        if (!in_array($meta['VariableType'], [1,2], true) || !is_numeric($value) || !is_finite((float)$value)) {
            $this->SetNoChargeReason('Netzlimit: Messwert ungültig'); return null;
        }
        $maxAge = max(10, $this->ReadPropertyInteger('GridMeasurementMaxAge'));
        $age = $this->now() - (int)$meta['VariableUpdated'];
        if ($age < 0 || $age > $maxAge) { $this->SetNoChargeReason('Netzlimit: Messwert veraltet'); return null; }
        $watts = (float)$value * ($this->ReadPropertyString('NetzleistungEinheit') === 'kW' ? 1000 : 1);
        return $this->ReadPropertyBoolean('InvertNetzleistung') ? -$watts : $watts;
    }
}
