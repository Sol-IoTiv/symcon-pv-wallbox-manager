<?php
class PVWallboxManager extends IPSModule
{
    public function Create()
    {
        parent::Create();

        // Visualisierung berechneter Werte
        $this->RegisterVariableFloat('PV_Ueberschuss', 'PV-Überschuss (W)', '~Watt', 10); // Aktuell berechneter PV-Überschuss in Watt

        // Energiequellen (Variablen-IDs für Berechnung)
        $this->RegisterPropertyInteger('PVErzeugungID', 0); // PV-Erzeugung in Watt
        $this->RegisterPropertyString("PVErzeugungEinheit", "W");
        
        $this->RegisterPropertyInteger('HausverbrauchID', 0); // Hausverbrauch in Watt
        $this->RegisterPropertyBoolean("InvertHausverbrauch", false);
        $this->RegisterPropertyString("HausverbrauchEinheit", "W");
        
        $this->RegisterPropertyInteger('BatterieladungID', 0); // Batterie-Lade-/Entladeleistung in Watt
        $this->RegisterPropertyBoolean("InvertBatterieladung", false);
        $this->RegisterPropertyString("BatterieladungEinheit", "W");
        
        $this->RegisterPropertyInteger('NetzeinspeisungID', 0); // Einspeisung/Bezug ins Netz (positiv/negativ)
        $this->RegisterPropertyBoolean("InvertNetzeinspeisung", false);
        $this->RegisterPropertyString("NetzeinspeisungEinheit", "W");

        // Wallbox-Einstellungen
        $this->RegisterPropertyInteger('GOEChargerID', 0); // Instanz-ID des GO-e Chargers
        $this->RegisterPropertyInteger('MinAmpere', 6); // Minimale Ladeleistung (Ampere)
        $this->RegisterPropertyInteger('MaxAmpere', 16); // Maximale Ladeleistung (Ampere)
        $this->RegisterPropertyInteger('Phasen', 3); // Anzahl aktiv verwendeter Ladephasen (1 oder 3)

        // Lade-Logik & Schwellenwerte
        $this->RegisterPropertyInteger('MinLadeWatt', 1400); // Mindest-PV-Überschuss zum Starten (Watt)
        $this->RegisterPropertyInteger('MinStopWatt', -300); // Schwelle zum Stoppen bei Defizit (Watt)
        $this->RegisterPropertyInteger('Phasen1Schwelle', 1000); // Schwelle zum Umschalten auf 1-phasig (Watt)
        $this->RegisterPropertyInteger('Phasen3Schwelle', 4200); // Schwelle zum Umschalten auf 3-phasig (Watt)
        $this->RegisterPropertyInteger('Phasen1Limit', 3); // Messzyklen unterhalb Schwelle vor Umschalten auf 1-phasig
        $this->RegisterPropertyInteger('Phasen3Limit', 3); // Messzyklen oberhalb Schwelle vor Umschalten auf 3-phasig
        $this->RegisterPropertyBoolean('DynamischerPufferAktiv', true); // Dynamischer Sicherheitsabzug aktiv

        // Fahrzeug-Erkennung & Ziel-SOC
        $this->RegisterPropertyBoolean('NurMitFahrzeug', true); // Ladung nur wenn Fahrzeug verbunden
        $this->RegisterPropertyBoolean('AllowBatteryDischarge', true); // Erlaubt die Entladung der Hausbatterie zur Unterstützung des PV-Überschussladens
        $this->RegisterPropertyBoolean('UseCarSOC', false); // Fahrzeug-SOC berücksichtigen
        $this->RegisterPropertyInteger('CarSOCID', 0); // Variable für aktuellen SOC des Fahrzeugs
        $this->RegisterPropertyFloat('CarSOCFallback', 20); // Fallback-SOC wenn keine Variable verfügbar
        $this->RegisterPropertyInteger('CarTargetSOCID', 0); // Ziel-SOC Variable
        $this->RegisterPropertyFloat('CarTargetSOCFallback', 80); // Fallback-Zielwert für SOC
        $this->RegisterPropertyInteger('MaxAutoWatt', 11000); // / Standardwert: 11.000 W (typisch für 3-phasige Wallbox/Fahrzeug, bei Bedarf anpassen)
        $this->RegisterPropertyFloat('CarBatteryCapacity', 52.0); // Batteriekapazität des Fahrzeugs in kWh
        $this->RegisterPropertyBoolean('AlwaysUseTargetSOC', false); // Ziel-SOC immer berücksichtigen (auch bei PV-Überschussladung)

        // Interne Status-Zähler für Phasenumschaltung
        $this->RegisterAttributeInteger('Phasen1Counter', 0);
        $this->RegisterAttributeInteger('Phasen3Counter', 0);

        $this->RegisterAttributeBoolean('RunLogFlag', true);

        // Start/Stop-Hysterese
        $this->RegisterPropertyInteger('StartHysterese', 0); // Anzahl Zyklen über Startschwelle bis gestartet wird
        $this->RegisterPropertyInteger('StopHysterese', 0);  // Anzahl Zyklen unter Stoppschwelle bis gestoppt wird

        $this->RegisterAttributeInteger('StartHystereseCounter', 0);
        $this->RegisterAttributeInteger('StopHystereseCounter', 0);

        // Erweiterte Logik: PV-Verteilung Auto/Haus
        $this->RegisterPropertyBoolean('PVVerteilenAktiv', false); // PV-Leistung anteilig zum Auto leiten
        $this->RegisterPropertyInteger('PVAnteilAuto', 33); // Anteil für das Auto in Prozent
        $this->RegisterPropertyInteger('HausakkuSOCID', 0); // SOC-Variable des Hausakkus
        $this->RegisterPropertyInteger('HausakkuSOCVollSchwelle', 95); // Schwelle ab wann Akku voll gilt

        // Visualisierung & WebFront-Buttons
        $this->RegisterVariableBoolean('ManuellVollladen', '🔌 Manuell: Vollladen aktiv', '', 20);
        $this->EnableAction('ManuellVollladen');

        $this->RegisterVariableBoolean('PV2CarModus', '☀️ PV-Anteil fürs Auto aktiv', '', 30);
        $this->EnableAction('PV2CarModus');

        $this->RegisterVariableBoolean('ZielzeitladungModus', '⏱️ Zielzeitladung', '', 40);
        $this->EnableAction('ZielzeitladungModus');
        
        //$this->RegisterVariableBoolean('ZielzeitladungPVonly', '⏱️ Zielzeitladung PV-optimiert', '', 40);
        //$this->EnableAction('ZielzeitladungPVonly');

        $this->RegisterVariableBoolean('AllowBatteryDischargeStatus', 'PV-Batterieentladung zulassen', '', 98);

        $this->RegisterVariableString('FahrzeugStatusText', 'Fahrzeug Status', '', 70);
        $this->RegisterVariableString('LademodusStatus', 'Aktueller Lademodus', '', 80);
        $this->RegisterVariableString('WallboxStatusText', 'Wallbox Status', '~HTMLBox', 90);

        $this->RegisterVariableInteger('TargetTime', 'Ziel-Zeit (Uhr)', '~UnixTimestampTime', 60);
        $this->EnableAction('TargetTime');

        // Zykluszeiten & Ladeplanung
        $this->RegisterPropertyInteger('RefreshInterval', 60); // Intervall für die Überschuss-Berechnung (Sekunden)
        $this->RegisterPropertyInteger('TargetChargePreTime', 4); // Stunden vor Zielzeit aktiv laden

        //Für die Berechnung der Ladeverluste
        //$this->RegisterAttributeBoolean("ChargingActive", false);
        //$this->RegisterAttributeFloat("ChargeSOCStart", 0);
        //$this->RegisterAttributeFloat("ChargeEnergyStart", 0);
        //$this->RegisterAttributeInteger("ChargeStartTime", 0);

        // Strompreis-Ladung (ab Version 0.9)
      
        //$this->RegisterVariableBoolean('StrompreisModus', '💰 Strompreis-Modus aktiv', '', 50);
        //$this->EnableAction('StrompreisModus');
        $this->RegisterPropertyInteger("CurrentPriceID", 0);      // Aktueller Preis (ct/kWh, Float)
        $this->RegisterPropertyInteger("ForecastPriceID", 0);     // 24h-Prognose (ct/kWh, String)
        //$this->RegisterPropertyFloat("MinPrice", 0.000);       // Mindestpreis (ct/kWh)
        //$this->RegisterPropertyFloat("MaxPrice", 30.000);      // Höchstpreis (ct/kWh)

        // Timer für regelmäßige Berechnung
        $this->RegisterTimer('PVUeberschuss_Berechnen', 0, 'IPS_RequestAction(' . $this->InstanceID . ', "UpdateCharging", 0);');
        
        $this->RegisterPropertyBoolean('ModulAktiv', true);
        $this->RegisterPropertyBoolean('DebugLogging', false);
        $this->RegisterAttributeBoolean('RunLock', false);

    }
    
    public function ApplyChanges()
    {
        parent::ApplyChanges();
    
        $this->Log('Instanz-Config: ' . json_encode(IPS_GetConfiguration($this->InstanceID)), 'debug');
    
        $interval = $this->ReadPropertyInteger('RefreshInterval');
        $goeID    = $this->ReadPropertyInteger('GOEChargerID');
        $pvID     = $this->ReadPropertyInteger('PVErzeugungID');
    
        // Timer nur aktivieren, wenn GO-e und PV-Erzeugung konfiguriert
        if (!$this->ReadPropertyBoolean('ModulAktiv')) {
            // Deaktiviert: Timer aus
            $this->SetTimerInterval('PVUeberschuss_Berechnen', 0);
            $this->SetLademodusStatus("⚠️ Modul ist deaktiviert. Keine Aktionen.");
            $this->Log('Modul ist deaktiviert – Timer gestoppt.', 'info');
            return;
        }
    
        // Timer nur aktivieren, wenn GO-e und PV-Erzeugung konfiguriert
        if ($goeID > 0 && $pvID > 0 && $interval > 0) {
            $this->SetTimerInterval('PVUeberschuss_Berechnen', $interval * 1000);
            $this->Log("Timer aktiviert: Intervall PVUeberschuss_Berechnen={$interval}s", 'info');
    
            // **Initialen Durchlauf direkt nach Aktivierung auslösen**
            $this->Log('Modul wurde aktiviert – initialer Berechnungsdurchlauf gestartet.', 'info');
            $this->PVUeberschuss_Berechnen();
        } else {
            $this->SetTimerInterval('PVUeberschuss_Berechnen', 0);
            $this->Log('Timer deaktiviert – GO-e Instanz oder PV-Erzeugung oder Intervall nicht konfiguriert.', 'warn');
        }
        $this->SetValue('AllowBatteryDischargeStatus', $this->ReadPropertyBoolean('AllowBatteryDischarge'));
    }

    public function RequestAction($ident, $value)
    {
        // NUR Variablen und Modus-Flags setzen! KEINE Statusmeldungen!
        switch ($ident) {
    case 'ManuellVollladen':
        SetValue($this->GetIDForIdent($ident), $value);
        if ($value) {
            SetValue($this->GetIDForIdent('PV2CarModus'), false);
            SetValue($this->GetIDForIdent('ZielzeitladungModus'), false);
        }
        break;
    
    case 'PV2CarModus':
        SetValue($this->GetIDForIdent($ident), $value);
        if ($value) {
            SetValue($this->GetIDForIdent('ManuellVollladen'), false);
            SetValue($this->GetIDForIdent('ZielzeitladungModus'), false);
        }
        break;
    
    case 'ZielzeitladungModus':
        SetValue($this->GetIDForIdent($ident), $value);
        if ($value) {
            SetValue($this->GetIDForIdent('ManuellVollladen'), false);
            SetValue($this->GetIDForIdent('PV2CarModus'), false);
        }
        break;

        case 'TargetTime':
            SetValue($this->GetIDForIdent($ident), $value);
            break;
    
        default:
            parent::RequestAction($ident, $value);
            break;
    }
    
        // Hauptlogik immer am Ende aufrufen!
        $this->UpdateCharging();
    }
    
    public function UpdateCharging()
    {
        // Schutz vor Überschneidung: Nur ein Durchlauf gleichzeitig!
        if ($this->ReadAttributeBoolean('RunLock')) {
            $this->Log("UpdateCharging() läuft bereits – neuer Aufruf wird abgebrochen.", 'warn');
            return;
        }
        $this->WriteAttributeBoolean('RunLock', true);
    
        try {
            $this->WriteAttributeBoolean('RunLogFlag', true); // Start eines neuen Durchlaufs
            $this->Log("Starte Berechnung (UpdateCharging)", 'debug');
    
            $goeID = $this->ReadPropertyInteger('GOEChargerID');
            $status = GOeCharger_GetStatus($goeID); // 1=bereit, 2=lädt, 3=warte, 4=beendet
    
            // Immer: PV-Überschuss (inkl. Batterieabzug) berechnen und anzeigen
            $pvUeberschussStandard = $this->BerechnePVUeberschuss();
            SetValue($this->GetIDForIdent('PV_Ueberschuss'), $pvUeberschussStandard);
            $this->Log("Standard-PV-Überschuss berechnet: {$pvUeberschussStandard} W", 'debug');
    
            // === Fahrzeugstatus-Logik ===
            if ($this->ReadPropertyBoolean('NurMitFahrzeug') && $status == 1) {
                // Wenn kein Fahrzeug verbunden, alle Modi deaktivieren
                foreach (['ManuellVollladen','PV2CarModus','ZielzeitladungModus'] as $mod) {
                    if (GetValue($this->GetIDForIdent($mod))) {
                        SetValue($this->GetIDForIdent($mod), false);
                    }
                }
                // Wallbox auf "Bereit" setzen UND Ladeleistung 0 setzen
                if (GOeCharger_getMode($goeID) != 1) {
                    GOeCharger_setMode($goeID, 1);
                }
                $this->SetLadeleistung(0); // <--- Ladeleistung explizit auf 0
                $this->SetFahrzeugStatus("⚠️ Kein Fahrzeug verbunden – bitte erst Fahrzeug anschließen.");
                SetValue($this->GetIDForIdent('PV_Ueberschuss'), 0.0);
                $this->Log("Kein Fahrzeug verbunden – Abbruch der Berechnung", 'warn');
                $this->UpdateWallboxStatusText();
                return;
            }
            // Status-Logik für weitere Fahrzeugstatus
            if ($this->ReadPropertyBoolean('NurMitFahrzeug')) {
                if ($status == 3) {
                    $this->SetFahrzeugStatus("🚗 Fahrzeug angeschlossen, wartet auf Freigabe (z.B. Tür öffnen oder am Fahrzeug 'Laden' aktivieren)");
                    $this->Log("Fahrzeug angeschlossen, wartet auf Freigabe", 'debug');
                }
                if ($status == 4) {
                    $this->SetFahrzeugStatus("🅿️ Fahrzeug verbunden, Ladung beendet. Moduswechsel möglich.");
                    $this->Log("Fahrzeug verbunden, Ladung beendet", 'debug');
                }
            }
    
            // === Ziel-SOC immer berücksichtigen, wenn Option aktiv ===
            if ($this->ReadPropertyBoolean('AlwaysUseTargetSOC')) {
                $socID = $this->ReadPropertyInteger('CarSOCID');
                $soc = (IPS_VariableExists($socID) && $socID > 0) ? GetValue($socID) : $this->ReadPropertyFloat('CarSOCFallback');
                $targetSOCID = $this->ReadPropertyInteger('CarTargetSOCID');
                $targetSOC = (IPS_VariableExists($targetSOCID) && $targetSOCID > 0) ? GetValue($targetSOCID) : $this->ReadPropertyFloat('CarTargetSOCFallback');
                $capacity = $this->ReadPropertyFloat('CarBatteryCapacity'); // z.B. 52.0
    
                $fehlendeProzent = max(0, $targetSOC - $soc);
                $fehlendeKWh = $capacity * $fehlendeProzent / 100.0;
    
                $this->Log("SOC-Prüfung: Ist={$soc}% | Ziel={$targetSOC}% | Fehlend=" . round($fehlendeProzent, 2) . "% | Fehlende kWh=" . round($fehlendeKWh, 2) . " kWh", 'info');
    
                if ($soc >= $targetSOC) {
                    $this->SetLadeleistung(0);
                    $this->SetLademodusStatus("Ziel-SOC erreicht ({$soc}% ≥ {$targetSOC}%) – keine weitere Ladung.");
                    $this->Log("Ziel-SOC erreicht ({$soc}% ≥ {$targetSOC}%) – keine weitere Ladung.", 'info');
                    $this->UpdateWallboxStatusText();
                    return;
                }
            }
    
            // === Modus-Weiche: NUR eine Logik pro Durchlauf! ===
            // Priorität: Manuell > Zielzeit > PV2Car > Standard
            if (GetValue($this->GetIDForIdent('ManuellVollladen'))) {
                $this->SetLadeleistung($this->GetMaxLadeleistung());
                $this->SetLademodusStatus("Manueller Volllademodus aktiv");
                $this->Log("Modus: Manueller Volllademodus", 'info');
            
            } elseif (GetValue($this->GetIDForIdent('ZielzeitladungModus'))) {
                $this->Log("Modus: Zielzeitladung aktiv", 'info');
                $this->LogikZielzeitladung();
            
            } elseif (GetValue($this->GetIDForIdent('PV2CarModus'))) {
                $this->Log("Modus: PV2Car aktiv", 'info');
                // ... (PV2Car-Modus-Logik, wie gehabt) ...
                $this->UpdateWallboxStatusText();
                $this->UpdateFahrzeugStatusText();
                return;
            
            } else {
                // === Standard: Nur PV-Überschuss/Hysterese ===
                $this->Log("Modus: PV-Überschuss (Standard)", 'info');
                $this->LogikPVPureMitHysterese();
            }
    
            // Optional: WallboxStatusText für WebFront aktualisieren (nur einmal pro Zyklus)
            $this->UpdateWallboxStatusText();
            $this->UpdateFahrzeugStatusText();
            $this->WriteAttributeBoolean('RunLogFlag', false);
    
        } finally {
            // Sperre immer wieder freigeben!
            $this->WriteAttributeBoolean('RunLock', false);
        }
    }
    
    // --- Hilfsfunktion: PV-Überschuss berechnen ---
    // Modus kann 'standard' (bisher wie gehabt) oder 'pv2car' (neuer PV2Car-Modus) sein
    private function BerechnePVUeberschuss(string $modus = 'standard'): float
    {
        $goeID  = $this->ReadPropertyInteger("GOEChargerID");
    
        // Werte auslesen, immer auf Watt normiert
        $pv    = 0;
        $pvID  = $this->ReadPropertyInteger('PVErzeugungID');
        if ($pvID > 0 && @IPS_VariableExists($pvID)) {
            $pv = GetValue($pvID);
            if ($this->ReadPropertyString('PVErzeugungEinheit') == 'kW') {
                $pv *= 1000;
            }
        }
        
        $haus  = $this->GetNormWert('HausverbrauchID', 'HausverbrauchEinheit', 'InvertHausverbrauch', "Hausverbrauch");
        $batt  = $this->GetNormWert('BatterieladungID', 'BatterieladungEinheit', 'InvertBatterieladung', "Batterieladung");
        $netz  = $this->GetNormWert('NetzeinspeisungID', 'NetzeinspeisungEinheit', 'InvertNetzeinspeisung', "Netzeinspeisung");
    
        $ladeleistung = ($goeID > 0) ? GOeCharger_GetPowerToCar($goeID) : 0;
    
        // --- Unterscheidung nach Modus ---
        if ($modus == 'pv2car') {
            $ueberschuss = $pv - $haus;
            $logModus = "PV2Car (Auto bekommt Anteil vom Überschuss, Rest Batterie)";
        } else {
            $ueberschuss = $pv - $haus - max(0, $batt);
            $logModus = "Standard (Batterie hat Vorrang)";
        }
    
        // === Dynamischer Puffer NUR im Standard-Modus (PV-Überschussladen) ===
        $pufferProzent = 1.0;
        $abgezogen = 0;
        $pufferText = "Dynamischer Puffer ist deaktiviert. Kein Abzug.";
    
        if ($modus === 'standard' && $this->ReadPropertyBoolean('DynamischerPufferAktiv')) {
            if ($ueberschuss < 2000)      $pufferProzent = 0.80;
            elseif ($ueberschuss < 4000)  $pufferProzent = 0.85;
            elseif ($ueberschuss < 6000)  $pufferProzent = 0.90;
            else                          $pufferProzent = 0.93;
    
            $alterUeberschuss = $ueberschuss;
            $ueberschuss      = $ueberschuss * $pufferProzent;
    
            $abgezogen = round($alterUeberschuss - $ueberschuss);
            $prozent   = round((1 - $pufferProzent) * 100);
            $pufferText = "Dynamischer Puffer: Es werden $abgezogen W abgezogen ($prozent% vom Überschuss, Faktor: $pufferProzent)";
        }
    
        // Auf Ganzzahl runden und negatives abfangen
        $ueberschuss = max(0, round($ueberschuss));
    
        // --- Puffer-Log ---
        $this->Log($pufferText, 'info');
    
        // --- Zentrales Logging ---
        $this->Log(
            "[{$logModus}] PV: {$pv} W | Haus: {$haus} W | Batterie: {$batt} W | Dyn.Puffer: {$abgezogen} W | → Überschuss: {$ueberschuss} W",
            'info'
        );
    
        // In Variable schreiben (nur im Standardmodus als Visualisierung)
        if ($modus == 'standard') {
            $this->SetLogValue('PV_Ueberschuss', $ueberschuss);
        }
    
        return $ueberschuss;
    }

    // --- Hysterese-Logik für Standardmodus ---
    private function LogikPVPureMitHysterese()
    {
        $minStart = $this->ReadPropertyInteger('MinLadeWatt');
        $minStop  = $this->ReadPropertyInteger('MinStopWatt');
        $ueberschuss = $this->BerechnePVUeberschuss('standard'); // loggt bereits Puffer!
        $goeID = $this->ReadPropertyInteger('GOEChargerID');
        $ladeModusID = @IPS_GetObjectIDByIdent('accessStateV2', $goeID);
        $ladeModus = ($ladeModusID !== false && @IPS_VariableExists($ladeModusID)) ? GetValueInteger($ladeModusID) : 0;
    
        // Initialisiere Counter, falls nicht gesetzt (Robustheit)
        $startCounter = $this->ReadAttributeInteger('StartHystereseCounter');
        $stopCounter  = $this->ReadAttributeInteger('StopHystereseCounter');
    
        $this->Log("Hysterese: Modus={$ladeModus}, Überschuss={$ueberschuss} W, MinStart={$minStart} W, MinStop={$minStop} W", 'info');
    
        if ($ladeModus == 2) { // Wallbox lädt bereits
            // === Stop-Hysterese ===
            if ($ueberschuss <= $minStop) {
                $stopCounter++;
                $this->WriteAttributeInteger('StopHystereseCounter', $stopCounter);
                $this->Log("🛑 Stop-Hysterese: {$stopCounter}/" . ($this->ReadPropertyInteger('StopHysterese')+1), 'debug');
    
                if ($stopCounter > $this->ReadPropertyInteger('StopHysterese')) {
                    $this->SetLadeleistung(0);
                    if (@IPS_InstanceExists($goeID)) {
                        GOeCharger_setMode($goeID, 1); // 1 = Bereit
                        $this->Log("🔌 Wallbox-Modus auf 'Bereit' gestellt (1)", 'info');
                    }
                    $msg = "PV-Überschuss unter Stop-Schwelle ({$ueberschuss} W ≤ {$minStop} W) – Wallbox gestoppt";
                    $this->Log($msg, 'info');
                    $this->SetLademodusStatus($msg);
                    // Counter nach Stopp zurücksetzen
                    $this->WriteAttributeInteger('StopHystereseCounter', 0);
                    $this->WriteAttributeInteger('StartHystereseCounter', 0);
                }
            } else {
                // Reset Stop-Hysterese, falls Überschuss wieder über Schwelle
                if ($stopCounter > 0) $this->WriteAttributeInteger('StopHystereseCounter', 0);
    
                $this->SetLadeleistung($ueberschuss);
                if ($ueberschuss > 0) {
                    if (@IPS_InstanceExists($goeID)) {
                        GOeCharger_setMode($goeID, 2); // 2 = Laden erzwingen
                        $this->Log("⚡ Wallbox-Modus auf 'Laden' gestellt (2)", 'info');
                    }
                }
                $msg = "PV-Überschuss: Bleibt an ({$ueberschuss} W)";
                $this->Log($msg, 'info');
                $this->SetLademodusStatus($msg);
            }
    
        } else { // Wallbox lädt NICHT (jede andere Modusnummer)
            // === Start-Hysterese ===
            if ($ueberschuss >= $minStart) {
                $startCounter++;
                $this->WriteAttributeInteger('StartHystereseCounter', $startCounter);
                $this->Log("🟢 Start-Hysterese: {$startCounter}/" . ($this->ReadPropertyInteger('StartHysterese')+1), 'debug');
    
                if ($startCounter > $this->ReadPropertyInteger('StartHysterese')) {
                    $this->SetLadeleistung($ueberschuss);
    
                    if ($ueberschuss > 0) {
                        if (@IPS_InstanceExists($goeID)) {
                            GOeCharger_setMode($goeID, 2); // 2 = Laden erzwingen
                            $this->Log("⚡ Wallbox-Modus auf 'Laden' gestellt (2)", 'info');
                        }
                    }
                    $msg = "PV-Überschuss über Start-Schwelle ({$ueberschuss} W ≥ {$minStart} W) – Wallbox startet";
                    $this->Log($msg, 'info');
                    $this->SetLademodusStatus($msg);
                    // Counter nach Start zurücksetzen
                    $this->WriteAttributeInteger('StartHystereseCounter', 0);
                    $this->WriteAttributeInteger('StopHystereseCounter', 0);
                }
            } else {
                // Reset Start-Hysterese, falls Überschuss wieder unter Schwelle
                if ($startCounter > 0) $this->WriteAttributeInteger('StartHystereseCounter', 0);
    
                $this->SetLadeleistung(0);
                if (@IPS_InstanceExists($goeID)) {
                    GOeCharger_setMode($goeID, 1); // 1 = Bereit
                    $this->Log("🔌 Wallbox-Modus auf 'Bereit' gestellt (1)", 'info');
                }
                $msg = "PV-Überschuss zu niedrig ({$ueberschuss} W) – bleibt aus";
                $this->Log($msg, 'info');
                $this->SetLademodusStatus($msg);
            }
        }
    
        // Warnung bei unbekanntem Moduswert (nur falls gewünscht)
        if (!in_array($ladeModus, [1,2])) {
            $this->Log("Unbekannter Wallbox-Modus: {$ladeModus}", 'warn');
        }
    }

    // --- Zielzeitladung mit Preisoptimierung & PV-Überschuss ---
    private function LogikZielzeitladung()
    {
        // --- 1. Zielzeit bestimmen (als Timestamp für heute oder ggf. morgen) ---
        $targetTimeVarID = $this->GetIDForIdent('TargetTime');
        $targetTimeRaw = GetValue($targetTimeVarID);
    
        // Zielzeit-Variable enthält oft nur Sekunden seit Tagesbeginn
        $heute = strtotime('today');
        $targetTime = $heute + ($targetTimeRaw % 86400);
        // Falls Zielzeit schon vorbei, dann auf morgen setzen
        if ($targetTime < time()) $targetTime += 86400;
    
        $this->Log("DEBUG: Zielzeit (lokal): $targetTime / " . date('d.m.Y H:i:s', $targetTime), 'debug');
    
        // --- 2. Ladebedarf (kWh) ---
        $socID = $this->ReadPropertyInteger('CarSOCID');
        $soc = (IPS_VariableExists($socID) && $socID > 0) ? GetValue($socID) : $this->ReadPropertyFloat('CarSOCFallback');
        $targetSOCID = $this->ReadPropertyInteger('CarTargetSOCID');
        $targetSOC = (IPS_VariableExists($targetSOCID) && $targetSOCID > 0) ? GetValue($targetSOCID) : $this->ReadPropertyFloat('CarTargetSOCFallback');
        $capacity = $this->ReadPropertyFloat('CarBatteryCapacity');
        $fehlendeProzent = max(0, $targetSOC - $soc);
        $fehlendeKWh = $capacity * $fehlendeProzent / 100.0;
        $maxWatt = $this->GetMaxLadeleistung();
        $ladezeitStunden = ceil($fehlendeKWh / ($maxWatt / 1000));
    
        // --- 3. Forecast (24h Preise) ---
        $forecastVarID = $this->ReadPropertyInteger("ForecastPriceID");
        $forecast = [];
        if ($forecastVarID > 0 && @IPS_VariableExists($forecastVarID)) {
            $forecastString = GetValue($forecastVarID);
            $forecast = json_decode($forecastString, true);
            if (!is_array($forecast)) {
                $forecast = array_map('floatval', explode(';', $forecastString));
            }
        }
        if (!is_array($forecast) || count($forecast) < 1) {
            $this->Log("Forecast: Keine gültigen Strompreis-Prognosedaten gefunden.", 'warn');
            $this->SetLadeleistung(0);
            $this->SetLademodusStatus("Keine Preis-Forecastdaten – kein Laden möglich!");
            return;
        }
    
        // --- 4. Nur Slots bis Zielzeit (und ab jetzt) filtern ---
        $now = time();
        $slots = [];
        foreach ($forecast as $slot) {
            if (isset($slot['start']) && isset($slot['end'])) {
                // Nur relevante Slots im Zeitraum zwischen jetzt und Zielzeit
                if ($slot['end'] > $now && $slot['start'] < $targetTime) {
                    $slots[] = [
                        "price" => floatval($slot['price']),
                        "start" => $slot['start'],
                        "end"   => $slot['end'],
                    ];
                }
            }
        }
        if (count($slots) == 0) {
            $this->Log("Zielzeitladung: Keine passenden Forecast-Slots im Planungszeitraum!", 'warn');
            $this->SetLadeleistung(0);
            $this->SetLademodusStatus("Zielzeitladung: Keine passenden Forecast-Slots gefunden!");
            return;
        }
    
        // --- 5. Nach Preis sortieren & Ladeplan erstellen ---
        usort($slots, fn($a, $b) => $a["price"] <=> $b["price"]);
        $ladeSlots = array_slice($slots, 0, $ladezeitStunden);
    
        // --- Logging: Ladeplan ---
        $ladeplanLog = implode(" | ", array_map(function($slot) {
            $von = date('H:i', $slot["start"]);
            $bis = date('H:i', $slot["end"]);
            return "{$von}-{$bis}: " . number_format($slot["price"], 2, ',', '.') . " ct";
        }, $ladeSlots));
        $this->Log("Zielzeit-Ladeplan (günstigste Stunden): $ladeplanLog", 'info');
    
        // --- 6. Laden nur im günstigsten Slot, sonst PV-only ---
        $ladeJetzt = false;
        $aktuellerSlotPrice = null;
        foreach ($ladeSlots as $slot) {
            if ($now >= $slot["start"] && $now < $slot["end"]) {
                $ladeJetzt = true;
                $aktuellerSlotPrice = $slot["price"];
                break;
            }
        }
    
        if ($ladeJetzt) {
            $msg = "Zielzeitladung: Im günstigen Slot (" . number_format($aktuellerSlotPrice, 2, ',', '.') . " ct/kWh) – maximale Leistung {$maxWatt} W";
            $this->SetLadeleistung($maxWatt);
            $this->SetLademodusStatus($msg);
            $this->Log($msg, 'info');
        } else {
            // PV-Überschuss laden, falls verfügbar
            $pvUeberschuss = $this->BerechnePVUeberschuss();
            if ($pvUeberschuss > 0) {
                $msg = "Zielzeitladung: Nicht im Preisslot – PV-Überschuss laden ({$pvUeberschuss} W)";
                $this->SetLadeleistung($pvUeberschuss);
                $this->SetLademodusStatus($msg);
                $this->Log($msg, 'info');
            } else {
                $msg = "Zielzeitladung: Warten auf günstigen Strompreis oder PV-Überschuss.";
                $this->SetLadeleistung(0);
                $this->SetLademodusStatus($msg);
                $this->Log($msg, 'info');
            }
        }
    }

    private function GetMaxLadeleistung(): int
    {
        $phasen = $this->ReadPropertyInteger('Phasen');
        $maxAmp = $this->ReadPropertyInteger('MaxAmpere');
        $maxWatt = $phasen * 230 * $maxAmp;
        $hardLimit = $this->ReadPropertyInteger('MaxAutoWatt');
        if ($hardLimit > 0 && $maxWatt > $hardLimit) {
            $maxWatt = $hardLimit;
        }
        return $maxWatt;
    }
    
    private function SetLadeleistung(int $watt)
    {
        $typ = 'go-e';
    
        switch ($typ) {
            case 'go-e':
                $goeID = $this->ReadPropertyInteger('GOEChargerID');
                if (!@IPS_InstanceExists($goeID)) {
                    $this->Log("⚠️ go-e Charger Instanz nicht gefunden (ID: $goeID)", 'warn');
                    return;
                }
    
                // Optionale Obergrenze für die Ladeleistung (z. B. Hardware- oder Fahrzeuglimit)
                $maxAutoWatt = $this->ReadPropertyInteger('MaxAutoWatt');
                if ($maxAutoWatt > 0 && $watt > $maxAutoWatt) {
                    $this->Log("⚠️ Ladeleistung auf Fahrzeuglimit reduziert ({$watt} W → {$maxAutoWatt} W)", 'info');
                    $watt = $maxAutoWatt;
                }
                // Mindestladeleistung für go-e Charger (meist ca. 1380 W 1-phasig, 4140 W 3-phasig)
                $minWatt = $this->ReadPropertyInteger('MinLadeWatt');
                if ($watt > 0 && $watt < $minWatt) {
                    $this->Log("⚠️ Angeforderte Ladeleistung zu niedrig ({$watt} W), setze auf Mindestwert {$minWatt} W.", 'info');
                    $watt = $minWatt;
                }
    
                // Counter nur bei > 0 W prüfen, sonst zurücksetzen
                if ($watt > 0) {
                    // Phasenumschaltung prüfen
                    $phaseVarID = @IPS_GetObjectIDByIdent('SinglePhaseCharging', $goeID);
                    $aktuell1phasig = false;
                    if ($phaseVarID !== false && @IPS_VariableExists($phaseVarID)) {
                        $aktuell1phasig = GetValueBoolean($phaseVarID);
                    }
    
                    // Hysterese für Umschaltung 1-phasig
                    if ($watt < $this->ReadPropertyInteger('Phasen1Schwelle') && !$aktuell1phasig) {
                        $alterCounter = $this->ReadAttributeInteger('Phasen1Counter');
                        $counter = $alterCounter + 1;
                        $this->WriteAttributeInteger('Phasen1Counter', $counter);
                        $this->WriteAttributeInteger('Phasen3Counter', 0);
                        // Nur loggen, wenn sich der Counter erhöht
                        if ($counter !== $alterCounter) {
                            $this->Log("⏬ Zähler 1-phasig: {$counter} / {$this->ReadPropertyInteger('Phasen1Limit')}", 'info');
                        }
                        if ($counter >= $this->ReadPropertyInteger('Phasen1Limit')) {
                            if (!$aktuell1phasig) {
                                GOeCharger_SetSinglePhaseCharging($goeID, true);
                                $this->Log("🔁 Umschaltung auf 1-phasig ausgelöst", 'info');
                            }
                            $this->WriteAttributeInteger('Phasen1Counter', 0);
                        }
                    }
                    // Hysterese für Umschaltung 3-phasig
                    elseif ($watt > $this->ReadPropertyInteger('Phasen3Schwelle') && $aktuell1phasig) {
                        $alterCounter = $this->ReadAttributeInteger('Phasen3Counter');
                        $counter = $alterCounter + 1;
                        $this->WriteAttributeInteger('Phasen3Counter', $counter);
                        $this->WriteAttributeInteger('Phasen1Counter', 0);
                        // Nur loggen, wenn sich der Counter erhöht
                        if ($counter !== $alterCounter) {
                            $this->Log("⏫ Zähler 3-phasig: {$counter} / {$this->ReadPropertyInteger('Phasen3Limit')}", 'info');
                        }
                        if ($counter >= $this->ReadPropertyInteger('Phasen3Limit')) {
                            if ($aktuell1phasig) {
                                GOeCharger_SetSinglePhaseCharging($goeID, false);
                                $this->Log("🔁 Umschaltung auf 3-phasig ausgelöst", 'info');
                            }
                            $this->WriteAttributeInteger('Phasen3Counter', 0);
                        }
                    }
                    // Keine Umschaltbedingung – Zähler zurücksetzen
                    else {
                        $this->WriteAttributeInteger('Phasen1Counter', 0);
                        $this->WriteAttributeInteger('Phasen3Counter', 0);
                    }
                } else {
                    // Zähler zurücksetzen, wenn Leistung 0
                    $this->WriteAttributeInteger('Phasen1Counter', 0);
                    $this->WriteAttributeInteger('Phasen3Counter', 0);
                }
    
                // Modus & Ladeleistung nur setzen, wenn nötig
                $modusID = @IPS_GetObjectIDByIdent('accessStateV2', $goeID);
                $wattID  = @IPS_GetObjectIDByIdent('Watt', $goeID);
    
                $aktuellerModus = -1;
                if ($modusID !== false && @IPS_VariableExists($modusID)) {
                    $aktuellerModus = GetValueInteger($modusID);
                }
    
                $aktuelleLeistung = -1;
                if ($wattID !== false && @IPS_VariableExists($wattID)) {
                    $aktuelleLeistung = GetValueFloat($wattID);
                }
    
                // Ladeleistung nur setzen, wenn Änderung > 50 W
                if ($aktuelleLeistung < 0 || abs($aktuelleLeistung - $watt) > 50) {
                    GOeCharger_SetCurrentChargingWatt($goeID, $watt);
                    $this->Log("✅ Ladeleistung gesetzt: {$watt} W", 'info');
    
                    // Nach Setzen der Leistung Modus sicherheitshalber aktivieren:
                    if ($watt > 0 && $aktuellerModus != 2) {
                        GOeCharger_setMode($goeID, 2); // 2 = Laden erzwingen
                        $this->Log("⚡ Modus auf 'Laden' gestellt (2)", 'info');
                    }
                    if ($watt == 0 && $aktuellerModus != 1) {
                        GOeCharger_setMode($goeID, 1); // 1 = Bereit
                        $this->Log("🔌 Modus auf 'Bereit' gestellt (1)", 'info');
                    }
                } else {
                    $this->Log("🟡 Ladeleistung unverändert – keine Änderung notwendig", 'debug');
                }
    
                // Hinweis, falls die Wallbox auf "Bereit" steht, aber geladen werden soll
                $status = GOeCharger_GetStatus($goeID); // 1=bereit, 2=lädt, 3=warte, 4=beendet
                if ($watt > 0 && $aktuellerModus == 1 && in_array($status, [3, 4])) {
                    $msg = "⚠️ Ladeleistung gesetzt, aber die Ladung startet nicht automatisch.<br>
                            Bitte Fahrzeug einmal ab- und wieder anstecken, um die Ladung zu aktivieren!";
                    $this->SetLademodusStatus($msg);
                    $this->Log($msg, 'warn');
                }
                break;
            default:
                $this->Log("❌ Unbekannter Wallbox-Typ '$typ' – keine Steuerung durchgeführt.", 'error');
                break;
        }
    }

    private function SetFahrzeugStatus($text, $log = false)
    {
        $this->SetLogValue('FahrzeugStatusText', $text);
        if ($log) $this->Log("FahrzeugStatus: $text", 'info');
    }

    private function SetLademodusStatus($text)
    {
        $this->SetLogValue('LademodusStatus', $text);
    }
    
    private function GetNormWert(string $idProp, string $einheitProp, string $invertProp, string $name = ""): float
    {
        $wert = 0;
        $vid = $this->ReadPropertyInteger($idProp);
        if ($vid > 0 && @IPS_VariableExists($vid)) {
            $wert = GetValue($vid);
            if ($this->ReadPropertyBoolean($invertProp)) {
                $wert *= -1;
            }
            if ($this->ReadPropertyString($einheitProp) == "kW") {
                $wert *= 1000;
            }
        } else {
            if ($name != "") {
                $this->Log("Hinweis: Keine $name-Variable gewählt, Wert wird als 0 angesetzt.", 'debug');
            }
        }
        return $wert;
    }

    private function UpdateWallboxStatusText()
    {
        $goeID = $this->ReadPropertyInteger('GOEChargerID');
        if ($goeID == 0) {
            $text = '<span style="color:gray;">Keine GO-e Instanz gewählt</span>';
        } else {
            $status = GOeCharger_GetStatus($goeID);
            switch ($status) {
                case 1:
                    $text = '<span style="color: gray;">Ladestation bereit, kein Fahrzeug</span>';
                    break;
                case 2:
                    $text = '<span style="color: green; font-weight:bold;">Fahrzeug lädt</span>';
                    break;
                case 3:
                    $text = '<span style="color: orange;">Fahrzeug angeschlossen, wartet auf Ladefreigabe</span>';
                    break;
                case 4:
                    $text = '<span style="color: blue;">Ladung beendet, Fahrzeug verbunden</span>';
                    break;
                default:
                    $text = '<span style="color: red;">Unbekannter Status</span>';
                    $this->Log("Unbekannter Status vom GO-e Charger: $status", 'warn');
            }
        }
        $this->SetLogValue('WallboxStatusText', $text);
    }

    private function UpdateFahrzeugStatusText()
    {
        $goeID = $this->ReadPropertyInteger('GOEChargerID');
        $status = GOeCharger_GetStatus($goeID);
        $modus = 'Kein Modus aktiv';
    
        if (GetValue($this->GetIDForIdent('ManuellVollladen'))) {
            $modus = 'Manueller Volllademodus';
        } elseif (GetValue($this->GetIDForIdent('PV2CarModus'))) {
            $modus = 'PV2Car';
        } elseif (GetValue($this->GetIDForIdent('ZielzeitladungModus'))) {
            $modus = 'Zielzeitladung';
        }
    
        $statusText = "";
        switch ($status) {
            case 2:
                $statusText = "⚡️ Fahrzeug lädt – Modus: $modus";
                break;
            case 3:
                $statusText = "🚗 Fahrzeug angeschlossen, wartet auf Freigabe (Modus: $modus)";
                break;
            case 4:
                if ($modus !== 'Kein Modus aktiv')
                    $statusText = "🔋 Modus aktiv: $modus – aber Ladung beendet.";
                else
                    $statusText = "🅿️ Fahrzeug verbunden, Ladung beendet. Moduswechsel möglich.";
                break;
            case 1:
            default:
                $statusText = "⚠️ Kein Fahrzeug verbunden.";
                break;
        }
        $this->SetFahrzeugStatus($statusText);
    
        // *** Logging ***
        $this->Log("UpdateFahrzeugStatusText: GO-e Status={$status}, Modus='{$modus}', Statustext='$statusText'", 'debug');
    }

    private function Log(string $message, string $level)
    {
        // Unterstützte Level: debug, info, warn, warning, error
        $prefix = "PVWM";
        $normalized = strtolower(trim($level));
    
        // Nur nicht-leere Nachrichten loggen
        if (trim($message) === '') return;
    
        switch ($normalized) {
            case 'debug':
                if ($this->ReadPropertyBoolean('DebugLogging')) {
                    IPS_LogMessage("{$prefix} [DEBUG]", $message);
                    $this->SendDebug("DEBUG", $message, 0);
                }
                break;
            case 'warn':
            case 'warning':
                IPS_LogMessage("{$prefix} [WARN]", $message);
                break;
            case 'error':
                IPS_LogMessage("{$prefix} [ERROR]", $message);
                break;
            case 'info':
            default:
                IPS_LogMessage("{$prefix}", $message);
                break;
        }
    }

    private function SetLogValue($ident, $value)
    {
        $varID = $this->GetIDForIdent($ident);
        if ($varID !== false && @IPS_VariableExists($varID)) {
            if (GetValue($varID) !== $value) {
                SetValue($varID, $value);
                // Logausgabe max. 100 Zeichen, sonst abgeschnitten
                $short = is_string($value) ? mb_strimwidth($value, 0, 100, "...") : $value;
                IPS_LogMessage("PVWM({$this->InstanceID})", "[$ident] = " . $short);
            }
        }
    }
}
