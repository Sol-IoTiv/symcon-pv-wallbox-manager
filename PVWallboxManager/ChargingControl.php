<?php

/** Serialized command execution and a persistent, feedback-driven phase transition. */
trait ChargingControl
{
    private $controlDepth = 0;
    private $chargerSnapshot = null;
    private $desiredPhaseMode = null;
    private $phaseDecisionPending = false;
    private $energySnapshot = null;
    private $gridSampleTime = null;
    private $commandTransportFailed = false;

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
        $this->gridSampleTime = null;
        try { return $operation(); }
        finally {
            $this->chargerSnapshot = null;
            $this->desiredPhaseMode = null;
            $this->phaseDecisionPending = false;
            $this->energySnapshot = null;
            $this->gridSampleTime = null;
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
        $this->commandTransportFailed = false;
        if ($key !== 'frc' || $value !== 1) {
            if ($this->ReadAttributeString('ReleaseRecovery') !== '{}') return false;
        }
        if (($key !== 'frc' || $value !== 1) &&
            (!$this->ReadPropertyBoolean('ModulAktiv') || $this->ReadAttributeBoolean('ControlStopRequested'))) return false;
        $ip = trim($this->ReadPropertyString('WallboxIP'));
        if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;
        $response = $this->simpleCurlGet('http://' . $ip . '/api/set?' . $key . '=' . $value);
        $this->commandTransportFailed = (int)$response['httpcode'] === 0;
        $body = is_string($response['result']) ? json_decode($response['result'], true) : null;
        $ok = (int)$response['httpcode'] === 200 && is_array($body) && ($body[$key] ?? null) === true;
        if (!$ok) {
            $detail = $key . '=' . $value . ', HTTP=' . $response['httpcode'];
            $detail .= ', Transport=' . substr(str_replace(["\r", "\n"], ' ', (string)($response['error'] ?? '')), 0, 200);
            // Only log the requested result field; never dump credentials or a complete status response.
            $detail .= ', JSON=' . json_last_error_msg() . ', Bestätigung=' . json_encode($body[$key] ?? null);
            $this->LogTemplate('error', 'Wallbox-Befehl nicht bestätigt', $detail);
        }
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
        $this->WriteAttributeBoolean('ChargingPowerObserved', false);
        $this->resetNoPowerCounter();
        if ($cancel) $this->resetPhaseDecisionObservation();
        $this->WriteAttributeString('VehiclePhaseObservation', '{}');
        if ($cancel && $this->phaseState() === 'confirming') return $this->phaseFault('Umschaltung vor Bestätigung abgebrochen');
        if ($cancel && $this->phaseState() !== 'fault') $this->clearTransition();
        $this->WriteAttributeInteger('LastSentChargingCurrent', 0);
        if ($this->chargerSnapshot !== null && $this->chargerStopped($this->chargerSnapshot)) return true;
        return $this->sendChargerCommand('frc', 1);
    }

    private function phaseFault(string $reason): bool
    {
        $this->resetPhaseDecisionObservation();
        $this->WriteAttributeString('PhaseTransitionState', 'fault');
        $this->WriteAttributeString('PhaseTransitionError', $reason);
        $this->SetNoChargeReason('Phasensteuerung gesperrt: ' . $reason);
        $this->SetTimerInterval('PVWM_PhaseTransition', 0);
        $this->stopCharging(false);
        $this->LogTemplate('error', 'Phasensteuerung gesperrt', $reason);
        return false;
    }

    private function handlePhaseStopFailure(): bool
    {
        if (!$this->commandTransportFailed) return $this->phaseFault('Ladestop abgelehnt');
        // Persist a separate context in the existing stop-only recovery gate. The
        // original phase deadline must not expire while communication is recovered.
        $this->WriteAttributeString('ReleaseRecovery', json_encode([
            'context'=>'phase', 'deadline'=>$this->now()+120, 'next'=>$this->now()+30,
            'attempts'=>0, 'samples'=>0
        ]));
        $this->SetTimerInterval('PVWM_PhaseTransition', 0);
        $this->SetNoChargeReason('Stop-Timeout vor Phasenwechsel – warte auf bestätigten Stillstand');
        $this->LogTemplate('warn', 'Stop-Wiederherstellung vor Phasenwechsel gestartet',
            'Maximal 3 Prüfungen im Abstand von 30 Sekunden innerhalb von 120 Sekunden; keine Phasenbefehle oder Ladefreigabe');
        return false;
    }
    public function ResetPhaseFault(): bool
    {
        $this->LogTemplate('info', 'Phasenfehler-Reset angefordert', 'Instanz=' . $this->InstanceID . ', Zustand=' . $this->phaseState() . ', Fehler=' . $this->ReadAttributeString('PhaseTransitionError'));
        try {
            return $this->controlled(function () {
            $status = $this->getStatusFromCharger();
            if ($status === false || !$this->chargerStopped($status)) {
                $this->stopCharging(false);
                $this->LogTemplate('warn', 'Fehlerreset abgelehnt', $status === false ? 'Kein gültiger Wallboxstatus' : sprintf('Stillstand nicht bestätigt: car=%d, frc=%d, alw=%s, psm=%d, Leistung=%.0f W, Ströme=%.2f/%.2f/%.2f A', $status['car'], $status['frc'], $status['alw'] ? 'true' : 'false', $status['psm'], $status['nrg'][11], $status['nrg'][4], $status['nrg'][5], $status['nrg'][6]));
                return false;
            }
            $this->clearTransition();
            $this->WriteAttributeString('PhaseTransitionError', '');
            $this->WriteAttributeInteger('ReleaseFailures', 0);
            $this->WriteAttributeInteger('ReleaseRetryAfter', 0);
            $this->WriteAttributeString('ReleaseRecovery', '{}');
            $this->ClearNoChargeReason();
            $this->LogTemplate('ok', 'Fehlersperre zurückgesetzt', 'Stillstand bestätigt; Ladeplan wird im nächsten Regelzyklus geprüft');
            return true;
            });
        } catch (\Throwable $e) {
            $this->LogTemplate('error', 'Fehlerreset nicht ausgeführt', $e->getMessage());
            throw $e;
        }
    }

    private function configuredPhaseCooldown(): int
    {
        return max(30, min(1800, $this->ReadPropertyInteger('PhaseSwitchCooldown')));
    }

    private function phaseCooldownActive(): bool
    {
        $last = $this->ReadAttributeInteger('LetztePhasenUmschaltung');
        return $last > 0 && $this->now() - $last < $this->configuredPhaseCooldown();
    }

    private function awaitingConfirmedPhaseResume(array $data): bool
    {
        return $this->phaseState() === 'idle'
            && $this->ReadAttributeBoolean('PhaseResumePending')
            && in_array($data['psm'], [1,2], true)
            && $data['psm'] === $this->ReadAttributeInteger('PhaseTransitionTarget')
            && $this->phaseCooldownActive();
    }

    private function resetPhaseDecisionObservation(): void
    {
        if ($this->ReadAttributeString('PhaseDecisionObservation') !== '{}') {
            $this->WriteAttributeString('PhaseDecisionObservation', '{}');
        }
    }

    private function phaseChangeIsStable(int $phaseMode, int $targetWatts, int $currentWatts): bool
    {
        if ($phaseMode === self::PHASE_MODE_3P && $targetWatts - $currentWatts < self::PHASE_UP_MIN_GAIN_W) {
            $this->resetPhaseDecisionObservation();
            return false;
        }

        $sample = $this->gridSampleTime ?? $this->now();
        $previous = json_decode($this->ReadAttributeString('PhaseDecisionObservation'), true) ?: [];
        $last = $previous['last'] ?? 0;
        $maxGap = max(30, 2 * $this->ReadPropertyInteger('RefreshInterval') + 5);
        if (($previous['target'] ?? 0) !== $phaseMode || $sample < $last || $sample - $last > $maxGap) {
            $previous = ['target'=>$phaseMode, 'since'=>$sample, 'last'=>$sample, 'samples'=>1];
        } elseif ($sample <= $last) {
            return false;
        } else {
            $previous['last'] = $sample;
            $previous['samples'] = min(2, $previous['samples'] + 1);
        }
        $this->WriteAttributeString('PhaseDecisionObservation', json_encode($previous));
        return $previous['samples'] >= 2
            && ($phaseMode === self::PHASE_MODE_1P || $sample - $previous['since'] >= self::PHASE_UP_STABILITY_S);
    }

    private function currentPhaseAmpere(int $currentPhases, int $plannedPhases, int $ampere): int
    {
        $plannedWatts = $ampere * $this->vehiclePhaseCount($plannedPhases) * 230;
        $currentAmpere = $this->clampAmpere((int)floor($plannedWatts / (230 * $this->vehiclePhaseCount($currentPhases))));
        $boundedPhases = $currentPhases;
        $currentAmpere = $this->applyMaxGridLoadLimit($currentAmpere, $boundedPhases);
        return $boundedPhases === $currentPhases ? $currentAmpere : 0;
    }

    private function executeChargingPlan(int $phaseMode, int $ampere, bool $enable): bool
    {
        if ($this->recoverReleaseStop()) return false;
        $data = $this->chargerSnapshot ?? $this->getStatusFromCharger();
        if ($data === false) return $this->phaseFault('Kein gültiger Wallboxstatus');
        if (!$this->ReadPropertyBoolean('ModulAktiv') || $this->ReadAttributeBoolean('ControlStopRequested') || !$enable || !$this->isCarConnected($data)) return $this->stopCharging();
        if ($this->phaseState() === 'fault') {
            $this->SetNoChargeReason('Phasensteuerung gesperrt: ' . $this->ReadAttributeString('PhaseTransitionError'));
            $this->stopCharging(false);
            return false;
        }
        if ($data['err'] !== 0) return $this->phaseFault('Wallbox meldet Fehler ' . $data['err']);
        if ($this->ReadAttributeInteger('ReleaseFailures') >= 3) {
            $this->SetNoChargeReason('Ladefreigabe nach 3 fehlgeschlagenen Versuchen gesperrt – Fehlerreset erforderlich');
            return $this->stopCharging(false);
        }
        if ($this->vehicleSocInvalid()) { $this->SetNoChargeReason('Fahrzeug-SoC oder Ziel-SoC ungültig'); return $this->stopCharging(); }
        if ($this->targetSocReached()) { $this->SetNoChargeReason('Ziel-SoC erreicht'); return $this->stopCharging(); }
        if ($this->ReadAttributeBoolean('ChargeEndConfirmed')) {
            $this->SetNoChargeReason('Fahrzeug fordert keine Ladung an');
            return $this->stopCharging();
        }
        $state = $this->phaseState();
        $resumePending = $this->awaitingConfirmedPhaseResume($data);
        $minimum = max(6, $this->ReadPropertyInteger('MinAmpere'));
        // An issued phase command must be confirmed while stopped, even if the current grid budget vanishes.
        // After confirmation a new cycle checks the budget before sending any current or release command.
        if ($state !== 'confirming') {
            $phases = $this->phaseModeToPhaseCount($phaseMode);
            $ampere = $this->clampAmpere($ampere);
            $ampere = $this->applyMaxGridLoadLimit($ampere, $phases);
            if ($ampere < $minimum) {
                $this->resetPhaseDecisionObservation();
                if ($this->GetNoChargeReason() === '') $this->SetNoChargeReason('Kein zulässiger Ladestrom');
                return $this->stopCharging(!$resumePending);
            }
            $phaseMode = $phases === 3 ? self::PHASE_MODE_3P : self::PHASE_MODE_1P;
        }
        if (in_array($state, ['stopping','confirming'], true)) {
            if ($this->now() >= $this->ReadAttributeInteger('PhaseTransitionDeadline')) return $this->phaseFault('Zeitüberschreitung bei ' . $state);
            if ($this->now() - $this->ReadAttributeInteger('PhaseTransitionSampleTime') < 2) return false;
            $this->WriteAttributeInteger('PhaseTransitionSampleTime', $this->now());
            // Once switching has started, finish confirmation while stopped; never retarget mid-command.
            $samples = $this->chargerStopped($data) ? $this->ReadAttributeInteger('PhaseTransitionSamples') + 1 : 0;
            if ($state === 'confirming' && $data['psm'] !== $this->ReadAttributeInteger('PhaseTransitionTarget')) $samples = 0;
            $this->WriteAttributeInteger('PhaseTransitionSamples', $samples);
            if (!$this->chargerStopped($data) && !$this->stopCharging(false)) return $this->handlePhaseStopFailure();
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
            $this->SetTimerInterval('PVWM_PhaseTransition', 2000);
            $this->resetNoPowerCounter();
            $this->LogTemplate('ok', 'Phasenwechsel bestätigt', (string)$data['psm']);
            // Re-evaluate the complete plan on the next normal cycle before resuming.
            return false;
        }
        $charging = $data['car'] === 2 && $data['alw'] && (float)$data['nrg'][11] > 30;
        $keepResumePending = $resumePending && !$charging;
        if ($this->isChargeEndCandidate($data)) {
            $this->resetPhaseDecisionObservation();
            $actualPhases = $this->phaseModeToPhaseCount($data['psm']);
            $actualAmpere = $this->currentPhaseAmpere($actualPhases, $phases, $ampere);
            if ($actualAmpere < $minimum) return $this->stopCharging();
            if ($this->observeChargeEnd()) return false;
            $this->SetNoChargeReason('Prüfe Ladeende bei unveränderter Phase');
            return $this->startAtCurrentPhase($data, min($data['amp'], $actualAmpere), false);
        }
        if ($data['psm'] !== $phaseMode) {
            $cooldown = $this->phaseCooldownActive();
            $knownPhase = in_array($data['psm'], [1,2], true);
            $stabilize = $knownPhase && $this->ReadAttributeBoolean('SessionPowerObserved');
            $actualPhases = $this->phaseModeToPhaseCount($data['psm']);
            $actualAmpere = $knownPhase && ($cooldown || $stabilize) ? $this->currentPhaseAmpere($actualPhases, $phases, $ampere) : 0;
            $targetWatts = $ampere * $this->vehiclePhaseCount($phases) * 230;
            $currentWatts = $actualAmpere >= $minimum ? $actualAmpere * $this->vehiclePhaseCount($actualPhases) * 230 : 0;
            $waitForStability = $stabilize && !$this->phaseChangeIsStable($phaseMode, $targetWatts, $currentWatts);
            if ($cooldown || $waitForStability) {
                $this->SetNoChargeReason($cooldown ? 'Cooldown nach Phasenumschaltung aktiv' : 'Warte auf stabiles Ladebudget für Phasenwechsel');
                $canRestart = ($resumePending || $stabilize) && ($data['frc'] === 2 || $this->chargerStopped($data));
                if ((!$charging && !$canRestart) || $actualAmpere < $minimum) {
                    return $this->stopCharging(!$resumePending && !$stabilize);
                }
                return $this->startAtCurrentPhase($data, $actualAmpere, $keepResumePending);
            }
            $this->resetPhaseDecisionObservation();
            $this->WriteAttributeInteger('PhaseTransitionTarget', $phaseMode);
            $this->WriteAttributeInteger('PhaseTransitionSamples', 0);
            $this->WriteAttributeInteger('PhaseTransitionSampleTime', $this->now());
            $this->WriteAttributeInteger('PhaseTransitionDeadline', $this->now() + max(15,$this->ReadPropertyInteger('PhaseSwitchTimeout')));
            $this->WriteAttributeString('PhaseTransitionState', 'stopping');
            $this->WriteAttributeBoolean('PhaseResumePending', true);
            $this->SetNoChargeReason('Phasenumschaltung: Ladestop abwarten');
            $this->SetTimerInterval('PVWM_PhaseTransition', 2000);
            if (!$this->stopCharging(false)) return $this->handlePhaseStopFailure();
            return false;
        }
        $this->resetPhaseDecisionObservation();
        return $this->startAtCurrentPhase($data, $ampere, $keepResumePending);
    }

    private function startAtCurrentPhase(array $data, int $ampere, bool $keepResumePending): bool
    {
        if ($this->ReadAttributeInteger('ReleaseFailures') > 0) {
            // Fresh readback can confirm a command whose HTTP acknowledgement was lost.
            if ($data['frc'] === 2 && $data['alw']) {
                $this->WriteAttributeInteger('ReleaseFailures', 0);
                $this->WriteAttributeInteger('ReleaseRetryAfter', 0);
                $this->LogTemplate('ok', 'Ladefreigabe durch Rückmeldung bestätigt');
            } elseif ($this->now() < $this->ReadAttributeInteger('ReleaseRetryAfter')) {
                $this->SetNoChargeReason('Ladefreigabe nicht bestätigt – Wiederholung frühestens nach 30 Sekunden');
                return false;
            }
        }
        $ampere = $this->clampAmpere($ampere);
        if ($ampere < 6 || !$this->ReadPropertyBoolean('ModulAktiv') || $this->ReadAttributeBoolean('ControlStopRequested')) return $this->stopCharging();
        if ($data['amp'] !== $ampere) {
            if (!$this->sendChargerCommand('amp', $ampere)) return $this->phaseFault('Ladestrom abgelehnt');
            $this->WriteAttributeInteger('LastChargingCurrentChange', $this->now());
        }
        $this->WriteAttributeInteger('LastChargingCurrent', $ampere);
        $this->WriteAttributeInteger('LastSentChargingCurrent', $ampere);
        if (!$this->ReadPropertyBoolean('ModulAktiv') || $this->ReadAttributeBoolean('ControlStopRequested') || $this->vehicleSocInvalid() || $this->targetSocReached()) return $this->stopCharging();
        if ($data['frc'] !== 2) {
            // Check again immediately before release (deactivation may have changed the property).
            if (!$this->ReadPropertyBoolean('ModulAktiv') || $this->ReadAttributeBoolean('ControlStopRequested') || $this->vehicleSocInvalid() || $this->targetSocReached()) return $this->stopCharging();
            $this->WriteAttributeBoolean('ChargingPowerObserved', false);
            if (!$this->sendChargerCommand('frc', 2)) {
                $failures = $this->ReadAttributeInteger('ReleaseFailures') + 1;
                $this->WriteAttributeInteger('ReleaseFailures', $failures);
                $this->WriteAttributeInteger('ReleaseRetryAfter', $this->now() + 30);
                $this->SetNoChargeReason($failures >= 3
                    ? 'Ladefreigabe nach 3 fehlgeschlagenen Versuchen gesperrt – Fehlerreset erforderlich'
                    : 'Ladefreigabe nicht bestätigt – Wiederholung frühestens nach 30 Sekunden');
                $this->LogTemplate('warn', 'Ladefreigabe nicht bestätigt', 'Versuch ' . $failures . '/3');
                // A lost acknowledgement does not establish whether either command took effect.
                // Never reuse the snapshot from before frc=2: that may still describe an idle charger.
                if (!$this->sendChargerCommand('frc', 1)) {
                    $this->chargerSnapshot = null;
                    $stopped = $this->getStatusFromCharger();
                    if ($stopped === false || !$this->chargerStopped($stopped)) {
                        if ($this->commandTransportFailed) {
                            $this->WriteAttributeString('ReleaseRecovery', json_encode([
                                'deadline'=>$this->now()+120, 'next'=>$this->now()+30,
                                'attempts'=>0, 'samples'=>0
                            ]));
                            $this->SetNoChargeReason('Kommunikationsausfall: Stop-Wiederherstellung wartet auf bestätigten Stillstand');
                            $this->LogTemplate('warn', 'Stop-Wiederherstellung gestartet', 'Maximal 3 Prüfungen im Abstand von 30 Sekunden; keine Freigabe oder Phasenwechsel');
                            return false;
                        }
                        return $this->phaseFault('Ladestop nach Freigabefehler nicht bestätigt');
                    }
                    $this->LogTemplate('warn', 'Ladestop durch Rückmeldung bestätigt',
                        'Befehlsbestätigung fehlt; frischer Status bestätigt Stillstand. Freigabe bleibt verzögert und begrenzt.');
                }
                return false;
            }
            $this->WriteAttributeInteger('ReleaseFailures', 0);
            $this->WriteAttributeInteger('ReleaseRetryAfter', 0);
        }
        $this->WriteAttributeBoolean('PhaseResumePending', $keepResumePending);
        if ((float)$data['nrg'][11] < self::NO_POWER_THRESHOLD_W && !$this->ReadAttributeBoolean('ChargingPowerObserved')) {
            $this->SetNoChargeReason('Ladefreigabe angefordert – warte auf Leistungsaufnahme des Fahrzeugs');
        }
        return true;
    }

    /** Returns true for the entire recovery cycle, including its completion. */
    private function recoverReleaseStop(): bool
    {
        $state = json_decode($this->ReadAttributeString('ReleaseRecovery'), true);
        if ($state === []) return false;
        // A genuine phase fault always wins. Never turn an existing latch into recovery.
        if ($this->phaseState() === 'fault') {
            $this->WriteAttributeString('ReleaseRecovery', '{}');
            return false;
        }
        if (!is_array($state) || !isset($state['deadline'],$state['next'],$state['attempts'],$state['samples'])) {
            $this->WriteAttributeString('ReleaseRecovery', '{}');
            $this->phaseFault('Ungültiger Zustand der Stop-Wiederherstellung');
            return true;
        }
        $this->SetNoChargeReason('Kommunikationsausfall: Stop-Wiederherstellung wartet auf bestätigten Stillstand');
        if ($this->now() >= $state['deadline'] || $state['attempts'] >= 3) {
            $this->WriteAttributeString('ReleaseRecovery', '{}');
            $this->phaseFault('Stop-Wiederherstellung erschöpft – Fehlerreset erforderlich');
            return true;
        }
        if ($this->now() < $state['next']) return true;
        // Persist the attempt before I/O, so restart/re-entry cannot reset its budget.
        $state['attempts']++;
        $state['next'] = $this->now()+30;
        $this->WriteAttributeString('ReleaseRecovery', json_encode($state));
        $ack = $this->sendChargerCommand('frc', 1);
        $transport = $this->commandTransportFailed;
        $this->chargerSnapshot = null;
        $data = $this->getStatusFromCharger();
        if ((!$ack && !$transport) || ($data !== false && $data['err'] !== 0)) {
            $this->WriteAttributeString('ReleaseRecovery', '{}');
            $this->phaseFault('Stop-Wiederherstellung: Stop abgelehnt oder Wallboxfehler');
            return true;
        }
        $state['samples'] = $data !== false && $this->chargerStopped($data) ? $state['samples']+1 : 0;
        $this->LogTemplate('info', 'Stop-Wiederherstellung', 'Prüfung '.$state['attempts'].'/3, Stillstandsbestätigungen '.$state['samples'].'/2');
        if ($state['samples'] >= 2) {
            $this->WriteAttributeString('ReleaseRecovery', '{}');
            if (($state['context'] ?? '') === 'phase') {
                // Discard the old phase plan. A later normal cycle must recheck all limits.
                $this->clearTransition(true);
            }
            $this->WriteAttributeInteger('ReleaseRetryAfter', $this->now()+30);
            $this->LogTemplate('ok', 'Stop-Wiederherstellung abgeschlossen', 'Zweimal Stillstand bestätigt; Ladeplan wird erneut geprüft. Freigabeversuche bleiben begrenzt.');
            return true;
        }
        $this->WriteAttributeString('ReleaseRecovery', json_encode($state));
        return true;
    }

    private function validSocValue(int $id): bool
    {
        return $id > 0 && IPS_VariableExists($id) && in_array(IPS_GetVariable($id)['VariableType'], [1,2], true)
            && is_numeric(GetValue($id)) && is_finite((float)GetValue($id)) && GetValue($id) >= 0 && GetValue($id) <= 100;
    }

    private function vehicleSocInvalid(): bool
    {
        $soc = $this->ReadPropertyInteger('CarSOCID');
        $target = $this->ReadPropertyInteger('CarTargetSOCID');
        return ($soc > 0 && !$this->validSocValue($soc)) || ($target > 0 && !$this->validSocValue($target));
    }

    private function targetSocReached(): bool
    {
        $soc = $this->ReadPropertyInteger('CarSOCID');
        $target = $this->ReadPropertyInteger('CarTargetSOCID');
        return $soc > 0 && $target > 0 && IPS_VariableExists($soc) && IPS_VariableExists($target)
            && is_numeric(GetValue($soc)) && is_numeric(GetValue($target)) && GetValue($soc) >= GetValue($target);
    }

    private function checkEnergyMeasurement(int $source, int $updated, float $house, float $wallbox): bool
    {
        if ($source <= 0) return true;
        $now = $this->now();
        $state = json_decode($this->ReadAttributeString('EnergyMeasurementState'), true) ?: [];
        if (($state['source'] ?? 0) !== $source) $state = ['source'=>$source, 'wallbox'=>$wallbox];
        $maxAge = max(120, 3 * $this->ReadPropertyInteger('RefreshInterval'));
        $plausible = $updated > 0 && $updated <= $now && $now - $updated <= $maxAge && $house + 100 >= $wallbox;
        $changed = abs($wallbox - $state['wallbox']) > 150;
        if ($changed || !$plausible) {
            if (!isset($state['waitingSince'])) $state['waitingSince'] = $now;
            // Require two later source updates, not two reads of the same cached value.
            $state['after'] = $now;
            $state['lastUpdate'] = $updated;
            $state['samples'] = 0;
            $state['wallbox'] = $wallbox;
        }
        if (isset($state['waitingSince']) && $plausible && !$changed
            && $updated > $state['after'] && $updated > $state['lastUpdate']) {
            $state['lastUpdate'] = $updated;
            $state['samples']++;
            if ($state['samples'] >= 2) {
                unset($state['waitingSince'], $state['after'], $state['samples'], $state['lastUpdate']);
                $state['wallbox'] = $wallbox;
            }
        }
        $this->WriteAttributeString('EnergyMeasurementState', json_encode($state));
        return !isset($state['waitingSince']);
    }

    private function holdForHouseMeasurement(array $data): void
    {
        $this->resetNoPowerCounter();
        $this->resetPhaseDecisionObservation();
        $mode = $this->getCurrentModeKey();
        $houseSoc = $this->ReadPropertyInteger('HausakkuSOCID');
        if (($mode === 'pv2car' && (int)$this->GetValue('PVAnteil') === 0)
            || (in_array($mode, ['pvonly','hybrid'], true) && $houseSoc > 0 && !$this->validSocValue($houseSoc))) {
            $this->SetNoChargeReason($mode === 'pv2car' ? 'PV-Anteil ist 0 %' : 'Hausakku-SoC ungültig');
            $this->stopCharging();
            return;
        }
        $state = json_decode($this->ReadAttributeString('EnergyMeasurementState'), true) ?: [];
        $maxWait = max(120, 3 * $this->ReadPropertyInteger('RefreshInterval'));
        $expired = $this->now() - ($state['waitingSince'] ?? $this->now()) >= $maxWait;
        $reason = $expired ? 'Hausverbrauch nicht zeitlich plausibel – Ladestop angefordert'
            : 'Warte auf aktualisierten Hausverbrauch nach Änderung der Wallboxleistung';
        $this->SetNoChargeReason($reason);
        if ($expired || $this->vehicleSocInvalid() || $this->targetSocReached() || $data['err'] !== 0) {
            $this->stopCharging();
            return;
        }
        if (in_array($this->phaseState(), ['stopping','confirming'], true)) {
            // Finish an already authorized transition while stopped; never release here.
            $this->executeChargingPlan($this->ReadAttributeInteger('PhaseTransitionTarget'), $data['amp'], true);
            return;
        }
        if ($data['car'] !== 2 || !$data['alw'] || $data['frc'] !== 2) {
            $this->stopCharging(false);
            return;
        }
        // Keep at most the current setting. Grid reductions/stops still have priority.
        if ($data['amp'] < max(6, $this->ReadPropertyInteger('MinAmpere')) || !in_array($data['psm'], [1,2], true)) {
            $this->stopCharging();
            return;
        }
        $this->executeChargingPlan($data['psm'], $data['amp'], true);
        if ($this->phaseState() === 'idle' && $this->GetNoChargeReason() === '') $this->SetNoChargeReason($reason);
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
        $this->gridSampleTime = (int)$meta['VariableUpdated'];
        $watts = (float)$value * ($this->ReadPropertyString('NetzleistungEinheit') === 'kW' ? 1000 : 1);
        return $this->ReadPropertyBoolean('InvertNetzleistung') ? -$watts : $watts;
    }
}
