<?php

class PVWallboxManager extends IPSModule
{

    // =========================================================================
    // 1. KONSTRUKTOR & INITIALISIERUNG
    // =========================================================================

    public function Create()
    {
        // Immer zuerst
        parent::Create();

        $this->RegisterCustomProfiles();

        // Properties aus form.json
        $this->RegisterPropertyString('WallboxIP', '0.0.0.0');
        $this->RegisterPropertyString('WallboxAPIKey', '');
        $this->RegisterPropertyInteger('RefreshInterval', 30);
        $this->RegisterPropertyBoolean('ModulAktiv', true);
        $this->RegisterPropertyBoolean('DebugLogging', false);
        $this->RegisterVariableString('Log', 'Modul-Log', '', 99);
        $this->RegisterPropertyInteger('MinAmpere', 6);   // Minimal möglicher Ladestrom
        $this->RegisterPropertyInteger('MaxAmpere', 16);  // Maximal möglicher Ladestrom
        $this->RegisterPropertyInteger('Phasen1Schwelle', 1400); // Beispiel: 1-phasig ab < 1.400 W
        $this->RegisterPropertyInteger('Phasen3Schwelle', 3700); // Beispiel: 3-phasig ab > 3.700 W
        $this->RegisterPropertyInteger('MinLadeWatt', 1400);

        // Variablen nach API v2
        $this->RegisterVariableInteger('Status',        'Status',                                   'PVWM.CarStatus',       1);
        $this->RegisterVariableInteger('AccessStateV2', 'Wallbox Modus',                            'PVWM.AccessStateV2',   2);
        $this->RegisterVariableFloat('Leistung',        'Aktuelle Ladeleistung zum Fahrzeug (W)',   'PVWM.Watt',            3);
        IPS_SetIcon($this->GetIDForIdent('Leistung'),   'Flash');
        $this->RegisterVariableInteger('Ampere',        'Max. Ladestrom (A)',                       'PVWM.Ampere',          4);
        IPS_SetIcon($this->GetIDForIdent('Ampere'),     'Energy');

        $this->RegisterVariableInteger('Phasenmodus',   'Phasenmodus',                              'PVWM.PSM',             5);
        $this->RegisterVariableBoolean('Freigabe',      'Ladefreigabe',                             'PVWM.ALW',             6);
        $this->RegisterVariableInteger('Kabelstrom',    'Kabeltyp (A)',                             'PVWM.AmpereCable',     7);
        IPS_SetIcon($this->GetIDForIdent('Kabelstrom'), 'Energy');
        $this->RegisterVariableFloat('Energie',         'Geladene Energie (Wh)',                    'PVWM.Wh',              8);
        $this->RegisterVariableInteger('Fehlercode',    'Fehlercode',                               'PVWM.ErrorCode',       9);

        // === 3. Energiequellen ===
        $this->RegisterPropertyInteger('PVErzeugungID', 0);
        $this->RegisterPropertyString('PVErzeugungEinheit', 'W');
        $this->RegisterPropertyInteger('NetzeinspeisungID', 0);
        $this->RegisterPropertyString('NetzeinspeisungEinheit', 'W');
        $this->RegisterPropertyBoolean('InvertNetzeinspeisung', false);
        $this->RegisterPropertyInteger('HausverbrauchID', 0);
        $this->RegisterPropertyString('HausverbrauchEinheit', 'W');
        $this->RegisterPropertyBoolean('InvertHausverbrauch', false);
        $this->RegisterPropertyInteger('BatterieladungID', 0);
        $this->RegisterPropertyString('BatterieladungEinheit', 'W');
        $this->RegisterPropertyBoolean('InvertBatterieladung', false);

        $this->RegisterPropertyBoolean('UseMarketPrices', false);
        $this->RegisterPropertyString('MarketPriceProvider', 'awattar_at');
        $this->RegisterPropertyString('MarketPriceAPI', '');
        $this->RegisterPropertyInteger('MarketPriceInterval', 30); // Minuten

        $this->RegisterVariableFloat('CurrentSpotPrice','Aktueller Börsenpreis (ct/kWh)',                   'PVWM.CentPerKWh', 30);
        $this->RegisterVariableString('MarketPrices', 'Börsenpreis-Vorschau', '', 31);

        // Zielzeit für Zielzeitladung
        $this->RegisterVariableInteger('TargetTime', 'Zielzeit', '~UnixTimestampTime', 20);
        IPS_SetIcon($this->GetIDForIdent('TargetTime'), 'clock');

        // === Modul-Variablen für Visualisierung, Status, Lademodus etc. ===
        $this->RegisterVariableFloat('PV_Ueberschuss','☀️ PV-Überschuss (W)',                               'PVWM.Watt', 10);
        IPS_SetIcon($this->GetIDForIdent('PV_Ueberschuss'), 'solar-panel');

        $this->RegisterVariableInteger('PV_Ueberschuss_A', 'PV-Überschuss (A)',                             'PVWM.Ampere', 11);
        IPS_SetIcon($this->GetIDForIdent('PV_Ueberschuss_A'), 'Energy');

        // Hausverbrauch (W)
        $this->RegisterVariableFloat('Hausverbrauch_W','🏠 Hausverbrauch (W)',                              'PVWM.Watt', 12);
        IPS_SetIcon($this->GetIDForIdent('Hausverbrauch_W'), 'home');

        // Hausverbrauch abzügl. Wallbox (W) – wie vorher empfohlen
        $this->RegisterVariableFloat('Hausverbrauch_abz_Wallbox','🏠 Hausverbrauch abzügl. Wallbox (W)',    'PVWM.Watt',15);
        IPS_SetIcon($this->GetIDForIdent('Hausverbrauch_abz_Wallbox'), 'home');

        // Lademodi
        $this->RegisterVariableBoolean('ManuellLaden', '🔌 Manuell: Vollladen aktiv', '~Switch', 40);
        $this->RegisterVariableBoolean('PV2CarModus', '🌞 PV2Car-Modus', '~Switch', 41);
        $this->RegisterVariableBoolean('ZielzeitLaden', '⏰ Zielzeit-Ladung', '~Switch', 42);
        $this->RegisterVariableInteger('PVAnteil',    'PV-Anteil (%)',                                      'PVWM.Percent',43);
        IPS_SetIcon($this->GetIDForIdent('PVAnteil'), 'Percent');

        // Timer für zyklische Abfrage (z.B. alle 30 Sek.)
        $this->RegisterTimer('PVWM_UpdateStatus', 0, 'IPS_RequestAction(' . $this->InstanceID . ', "UpdateStatus", "pvonly");');
        $this->RegisterTimer('PVWM_UpdateMarketPrices', 0, 'IPS_RequestAction(' . $this->InstanceID . ', "UpdateMarketPrices", "");');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $interval = $this->ReadPropertyInteger('RefreshInterval'); 
        $this->LogTemplate('debug', "Timer-Intervall: $interval Sekunden");

        $aktiv = $this->ReadPropertyBoolean('ModulAktiv');
        if ($aktiv) {
            $this->SetTimerInterval('PVWM_UpdateStatus', $interval * 1000);
        } else {
            $this->SetTimerInterval('PVWM_UpdateStatus', 0); // Timer AUS
        }
        // Strompreis-Update-Timer steuern
        if ($this->ReadPropertyBoolean('UseMarketPrices')) {
            $marketInterval = max(5, $this->ReadPropertyInteger('MarketPriceInterval')); // Minimum 5 Minuten
            $this->SetTimerInterval('PVWM_UpdateMarketPrices', $marketInterval * 60 * 1000);
        } else {
            $this->SetTimerInterval('PVWM_UpdateMarketPrices', 0); // Timer AUS
        }
    }

    // =========================================================================
    // 2. PROFILE & VARIABLEN-PROFILE
    // =========================================================================

    private function RegisterCustomProfiles()
    {
        // Hilfsfunktion für Anlage/Löschen/Suffix/Icon/Assoziationen
        $create = function($name, $type, $digits, $suffix, $icon = '', $associations = null) {
            if (IPS_VariableProfileExists($name)) {
                IPS_DeleteVariableProfile($name);
            }
            IPS_CreateVariableProfile($name, $type);
            IPS_SetVariableProfileDigits($name, $digits);
            IPS_SetVariableProfileText($name, '', $suffix);
            if (!empty($icon)) {
                IPS_SetVariableProfileIcon($name, $icon);
            }
            if (is_array($associations)) {
                foreach ($associations as $idx => $a) {
                    // [Wert, Name, Icon, Farbe]
                    IPS_SetVariableProfileAssociation($name, $a[0], $a[1], $a[2] ?? '', $a[3] ?? -1);
                }
            }
        };

        // Integer-Profile (mit Assoziationen, wo nötig)
        $create('PVWM.CarStatus', VARIABLETYPE_INTEGER, 0,  '', 'Car', [
            [0, 'Unbekannt/Firmwarefehler',                 'Question',     0x888888],
            [1, 'Bereit, kein Fahrzeug',                    'Parking',      0xAAAAAA],
            [2, 'Fahrzeug lädt',                            'Lightning',    0x00FF00],
            [3, 'Warte auf Fahrzeug',                       'Car',          0x0088FF],
            [4, 'Ladung beendet, Fahrzeug noch verbunden',  'Check',        0xFFFF00],
            [5, 'Fehler',                                   'Alert',        0xFF0000]
        ]);

        $create('PVWM.ErrorCode', VARIABLETYPE_INTEGER, 0, '', 'Alert', [
            [0,  'Kein Fehler',                 '', 0x44FF44],
            [1,  'FI AC',                       '', 0xFFAA00],
            [2,  'FI DC',                       '', 0xFFAA00],
            [3,  'Phasenfehler',                '', 0xFF4444],
            [4,  'Überspannung',                '', 0xFF4444],
            [5,  'Überstrom',                   '', 0xFF4444],
            [6,  'Diodenfehler',                '', 0xFF4444],
            [7,  'PP ungültig',                 '', 0xFF4444],
            [8,  'GND ungültig',                '', 0xFF4444],
            [9,  'Schütz hängt',                '', 0xFF4444],
            [10, 'Schütz fehlt',                '', 0xFF4444],
            [11, 'FI unbekannt',                '', 0xFF4444],
            [12, 'Unbekannter Fehler',          '', 0xFF4444],
            [13, 'Übertemperatur',              '', 0xFF4444],
            [14, 'Keine Kommunikation',         '', 0xFF4444],
            [15, 'Verriegelung klemmt offen',   '', 0xFF4444],
            [16, 'Verriegelung klemmt verriegelt', '', 0xFF4444],
            [20, 'Reserviert 20',               '', 0xAAAAAA],
            [21, 'Reserviert 21',               '', 0xAAAAAA],
            [22, 'Reserviert 22',               '', 0xAAAAAA],
            [23, 'Reserviert 23',               '', 0xAAAAAA],
            [24, 'Reserviert 24',               '', 0xAAAAAA]
        ]);

        $create('PVWM.AccessStateV2', VARIABLETYPE_INTEGER, 0, '', 'Lock', [
            [0, 'Neutral (Wallbox entscheidet)', 'LockOpen', 0xAAAAAA],
            [1, 'Nicht Laden (gesperrt)',        'Lock', 0xFF4444],
            [2, 'Laden (erzwungen)',             'Power', 0x44FF44]
        ]);

        $create('PVWM.PSM', VARIABLETYPE_INTEGER, 0, '', 'Lightning', [
            [0, 'Auto',     'Gears', 0xAAAAAA],
            [1, '1-phasig', 'Plug', 0x00ADEF],
            [2, '3-phasig', 'Plug', 0xFF9900]
        ]);

        $create('PVWM.ALW', VARIABLETYPE_BOOLEAN, 0, '', 'Power', [
            [false, 'Nicht freigegeben', 'Close', 0xFF4444],
            [true,  'Laden freigegeben', 'Power', 0x44FF44]
        ]);

        $create('PVWM.AmpereCable', VARIABLETYPE_INTEGER, 0, ' A', 'Energy');

        // Die bisherigen Profile
        $create('PVWM.Ampere',      VARIABLETYPE_INTEGER, 0, ' A',      'Energy');
        $create('PVWM.Percent',     VARIABLETYPE_INTEGER, 0, ' %',      'Percent');
        $create('PVWM.Watt',        VARIABLETYPE_FLOAT,   0, ' W',      'Flash');
        $create('PVWM.W',           VARIABLETYPE_FLOAT,   0, ' W',      'Flash');
        $create('PVWM.CentPerKWh',  VARIABLETYPE_FLOAT,   3, ' ct/kWh', 'Euro');
        $create('PVWM.Wh',          VARIABLETYPE_FLOAT,   0, ' Wh',     'Lightning');
    }

    // =========================================================================
    // 3. EVENTS & REQUESTACTION
    // =========================================================================

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case "UpdateStatus":
                $this->UpdateStatus($Value);
                break;
            case "UpdateMarketPrices":
                $this->AktualisiereMarktpreise();
                break;
            case "ManuellLaden":
                $this->SetValue('ManuellLaden', $Value);
                break;
            // ... weitere cases ...
            default:
                throw new Exception("Invalid Ident: $Ident");
        }
    }

    // =========================================================================
    // 4. WALLBOX-KOMMUNIKATION (API)
    // =========================================================================

    private function getStatusFromCharger()
    {
        $ip = trim($this->ReadPropertyString('WallboxIP'));

        // 1. Check: IP konfiguriert?
        if ($ip == "" || $ip == "0.0.0.0") {
            $this->LogTemplate('error', "Keine IP-Adresse für Wallbox konfiguriert.");
            //$this->SetStatus(200); // Symcon-Status: Konfiguration fehlt
            return false;
        }
        // 2. Check: IP gültig?
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $this->LogTemplate('error', "Ungültige IP-Adresse konfiguriert: $ip");
            //$this->SetStatus(201); // Symcon-Status: Konfigurationsfehler
            return false;
        }
        // 3. Check: Erreichbar (Ping Port 80)?
        if (!$this->ping($ip, 80, 1)) {
            $this->LogTemplate('error', "Wallbox unter $ip:80 nicht erreichbar.");
            //$this->SetStatus(202); // Symcon-Status: Keine HTTP-Antwort
            return false;
        }

        // 4. HTTP-Request via cURL, V2 API bevorzugen
        $url = "http://$ip/api/status";
        $json = false;
        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            $json = curl_exec($ch);
            curl_close($ch);
        } catch (Exception $e) {
            $this->LogTemplate('error', "HTTP Fehler: " . $e->getMessage());
            //$this->SetStatus(203);
            return false;
        }

        if ($json === false || strlen($json) < 2) {
            $this->LogTemplate('error', "Fehler: Keine Antwort von Wallbox ($url)");
            //$this->SetStatus(203);
            return false;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            $this->LogTemplate('error', "Fehler: Ungültiges JSON von Wallbox ($url)");
            //$this->SetStatus(204);
            return false;
        }

        //$this->SetStatus(102); // Alles OK (optional)
        return $data;
    }

    private function ping($host, $port = 80, $timeout = 1)
    {
        $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if ($fp) {
            fclose($fp);
            return true;
        }
        return false;
    }

    // =========================================================================
    // 5. HAUPT-STEUERLOGIK
    // =========================================================================

    public function UpdateStatus(string $mode = 'pvonly')
    {
        $this->LogTemplate('debug', "UpdateStatus getriggert (Modus: $mode, Zeit: " . date("H:i:s") . ")");

        $data = $this->getStatusFromCharger();
        if ($data === false) {
            // Fehler wurde schon geloggt
            return;
        }

        // Defensive Daten-Extraktion
        $car = isset($data['car']) ? intval($data['car']) : 0;
        $leistung = (isset($data['nrg'][11]) && is_array($data['nrg'])) ? floatval($data['nrg'][11]) : 0.0;
        $ampere = isset($data['amp']) ? intval($data['amp']) : 0;
        $energie = isset($data['wh']) ? intval($data['wh']) : 0;
        $freigabe = isset($data['alw']) ? (bool)$data['alw'] : false;
        $kabelstrom = isset($data['cbl']) ? intval($data['cbl']) : 0;
        $fehlercode = isset($data['err']) ? intval($data['err']) : 0;
        $psm = isset($data['psm']) ? intval($data['psm']) : 0;
        $pha = $data['pha'] ?? [];
//        $accessStateV2 = isset($data['accessStateV2']) ? intval($data['accessStateV2']) : 0;

        // Kompatibel beide Felder für forceState/AccessStateV2 abfragen
        $accessStateV2 = 0;
        if (isset($data['frc'])) {
            $accessStateV2 = intval($data['frc']);
        } elseif (isset($data['accessStateV2'])) {
            $accessStateV2 = intval($data['accessStateV2']);
        }
        $this->LogTemplate(
            'debug',
            "Status: forceState (frc)=" . (isset($data['frc']) ? $data['frc'] : 'n/a') .
            " accessStateV2=" . (isset($data['accessStateV2']) ? $data['accessStateV2'] : 'n/a')
        );

        // Jetzt Werte NUR bei Änderung schreiben und loggen:
        $this->SetValueAndLogChange('Status',      $car,         'Status');
        $this->SetValueAndLogChange('AccessStateV2', $accessStateV2, 'Wallbox Modus');
        $this->SetValueAndLogChange('Leistung',    $leistung,    'Aktuelle Ladeleistung zum Fahrzeug', 'W');
        $this->SetValueAndLogChange('Ampere',      $ampere,      'Maximaler Ladestrom', 'A');
        $this->SetValueAndLogChange('Phasenmodus', $psm,         'Phasenmodus');
        $this->SetValueAndLogChange('Energie',     $energie,     'Geladene Energie', 'Wh');
        $this->SetValueAndLogChange('Freigabe',    $freigabe,    'Ladefreigabe');
        $this->SetValueAndLogChange('Kabelstrom',  $kabelstrom,  'Kabeltyp');
        $this->SetValueAndLogChange('Fehlercode',  $fehlercode,  'Fehlercode', '', 'warn');

        $berechnung = $this->BerechnePVUeberschuss();
        $pvUeberschuss = $berechnung['ueberschuss_w'];
        $ampere        = $berechnung['ueberschuss_a'];
        $anzPhasen     = $berechnung['phasenmodus'];

        // Phasenumschaltung
        $this->PruefeUndSetzePhasenmodus($pvUeberschuss);

        // Ladefreigabe steuern (z.B. im pvonly Modus)
        $this->SteuerungLadefreigabe($pvUeberschuss, $mode, $ampere, $anzPhasen);
    }

    // =========================================================================
    // 6. WALLBOX STEUERN (SET-FUNKTIONEN)
    // =========================================================================

    private function simpleCurlGet($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);

        $result = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        return [
            'result'    => $result,
            'httpcode'  => $httpcode,
            'error'     => $curlError
        ];
    }

    public function SetChargingCurrent(int $ampere)
    {
        $minAmp = $this->ReadPropertyInteger('MinAmpere');
        $maxAmp = $this->ReadPropertyInteger('MaxAmpere');
        // Wertebereich prüfen
        if ($ampere < $minAmp || $ampere > $maxAmp) {
            $this->LogTemplate('warn', "SetChargingCurrent: Ungültiger Wert ($ampere A). Erlaubt: $minAmp–$maxAmp A!");
            return false;
        }
        $ip = $this->ReadPropertyString('WallboxIP');
        $url = "http://$ip/api/set?amp=" . intval($ampere);

        $this->LogTemplate('info', "SetChargingCurrent: Sende Ladestrom $ampere A an $url");

        $response = $this->simpleCurlGet($url);

        if ($response['result'] === false || $response['httpcode'] != 200) {
            $this->LogTemplate(
                'error',
                "SetChargingCurrent: Fehler beim Setzen auf $ampere A! " .
                "HTTP-Code: {$response['httpcode']}, cURL-Fehler: {$response['error']}"
            );
            return false;
        } else {
            $this->LogTemplate('ok', "SetChargingCurrent: Ladestrom auf $ampere A gesetzt. (HTTP {$response['httpcode']})");
            $this->UpdateStatus();
            return true;
        }
    }

    public function SetPhaseMode(int $mode)
    {
        // Wertebereich prüfen: 0 = Auto, 1 = 1-phasig, 2 = 3-phasig
        if ($mode < 0 || $mode > 2) {
            $this->LogTemplate('warn', "SetPhaseMode: Ungültiger Wert ($mode). Erlaubt: 0=Auto, 1=1-phasig, 2=3-phasig!");
            return false;
        }

        $ip = $this->ReadPropertyString('WallboxIP');
        $url = "http://$ip/api/set?psm=" . intval($mode);

        $modes = [0 => "Auto", 1 => "1-phasig", 2 => "3-phasig"];
        $modeText = $modes[$mode] ?? $mode;

        $this->LogTemplate('info', "SetPhaseMode: Sende Phasenmodus '$modeText' ($mode) an $url");

        $response = $this->simpleCurlGet($url);

        if ($response['result'] === false || $response['httpcode'] != 200) {
            $this->LogTemplate(
                'error',
                "SetPhaseMode: Fehler beim Setzen auf '$modeText' ($mode)! HTTP-Code: {$response['httpcode']}, cURL-Fehler: {$response['error']}"
            );
            return false;
        } else {
            $this->LogTemplate('ok', "SetPhaseMode: Phasenmodus auf '$modeText' ($mode) gesetzt. (HTTP {$response['httpcode']})");
            // Direkt Status aktualisieren
            $this->UpdateStatus();
            return true;
        }
    }

    public function SetForceState(int $state)
    {
        // Wertebereich prüfen: 0 = Neutral, 1 = Nicht Laden, 2 = Laden
        if ($state < 0 || $state > 2) {
            $this->LogTemplate('warn', "SetForceState: Ungültiger Wert ($state). Erlaubt: 0=Neutral, 1=OFF, 2=ON!");
            return false;
        }

        $ip = $this->ReadPropertyString('WallboxIP');
        $url = "http://$ip/api/set?frc=" . intval($state);

        $modes = [
            0 => "Neutral (Wallbox entscheidet)",
            1 => "Nicht Laden (gesperrt)",
            2 => "Laden (erzwungen)"
        ];
        $modeText = $modes[$state] ?? $state;

        $this->LogTemplate('info', "SetForceState: Sende Wallbox-Modus '$modeText' ($state) an $url");

        $response = $this->simpleCurlGet($url);

        if ($response['result'] === false || $response['httpcode'] != 200) {
            $this->LogTemplate(
                'error',
                "SetForceState: Fehler beim Setzen auf '$modeText' ($state)! HTTP-Code: {$response['httpcode']}, cURL-Fehler: {$response['error']}"
            );
            return false;
        } else {
            $this->LogTemplate('ok', "SetForceState: Wallbox-Modus auf '$modeText' ($state) gesetzt. (HTTP {$response['httpcode']})");
            // Direkt Status aktualisieren
            $this->UpdateStatus();
            return true;
        }
    }

    public function SetChargingEnabled(bool $enabled)
    {
        $ip = $this->ReadPropertyString('WallboxIP');
        $apiKey = $this->ReadPropertyString('WallboxAPIKey');
        $alwValue = $enabled ? 1 : 0;

        $statusText = $enabled ? "Laden erlaubt" : "Laden gesperrt";

        if ($apiKey != '') {
            // Offizieller Weg: mit API-Key
            $url = "http://$ip/api/set?dwo=0&alw=$alwValue&key=" . urlencode($apiKey);
            $this->LogTemplate('info', "SetChargingEnabled: Sende (API-Key) Ladefreigabe '$statusText' ($alwValue) an $url");
        } else {
            // Inoffizieller Weg: MQTT-Shortcut
            $url = "http://$ip/mqtt?payload=alw=$alwValue";
            $this->LogTemplate('info', "SetChargingEnabled: Sende (MQTT) Ladefreigabe '$statusText' ($alwValue) an $url");
        }

        $response = $this->simpleCurlGet($url);

        if ($response['result'] === false || $response['httpcode'] != 200) {
            $this->LogTemplate(
                'error',
                "SetChargingEnabled: Fehler beim Setzen der Ladefreigabe ($alwValue)! HTTP-Code: {$response['httpcode']}, cURL-Fehler: {$response['error']}"
            );
            return false;
        } else {
            $this->LogTemplate('ok', "SetChargingEnabled: Ladefreigabe wurde auf '$statusText' ($alwValue) gesetzt. (HTTP {$response['httpcode']})");
            $this->UpdateStatus();
            return true;
        }
    }

    public function StopCharging()
    {
        $ip = $this->ReadPropertyString('WallboxIP');
        $url = "http://$ip/api/set?stp=1";

        $this->LogTemplate('info', "StopCharging: Sende Stopp-Befehl an $url");

        $response = $this->simpleCurlGet($url);

        if ($response['result'] === false || $response['httpcode'] != 200) {
            $this->LogTemplate(
                'error',
                "StopCharging: Fehler beim Stoppen des Ladevorgangs! HTTP-Code: {$response['httpcode']}, cURL-Fehler: {$response['error']}"
            );
            return false;
        } else {
            $this->LogTemplate('ok', "StopCharging: Ladevorgang wurde gestoppt. (HTTP {$response['httpcode']})");
            // Direkt Status aktualisieren
            $this->UpdateStatus();
            return true;
        }
    }

    private function PruefeUndSetzePhasenmodus($pvUeberschuss)
    {
        $schwelle1 = $this->ReadPropertyInteger('Phasen1Schwelle');
        $schwelle3 = $this->ReadPropertyInteger('Phasen3Schwelle');
        $aktuellerPhasenmodus = $this->GetValue('Phasenmodus');

        if ($pvUeberschuss >= $schwelle3 && $aktuellerPhasenmodus != 2) {
            $this->SetValueAndLogChange('Phasenmodus', 2, 'Phasenumschaltung', '', 'ok');
            $ok = $this->SetPhaseMode(2);
            if (!$ok) {
                $this->LogTemplate('error', 'PruefeUndSetzePhasenmodus: Umschalten auf 3-phasig fehlgeschlagen!');
            }
        } elseif ($pvUeberschuss <= $schwelle1 && $aktuellerPhasenmodus != 1) {
            $this->SetValueAndLogChange('Phasenmodus', 1, 'Phasenumschaltung', '', 'warn');
            $ok = $this->SetPhaseMode(1);
            if (!$ok) {
                $this->LogTemplate('error', 'PruefeUndSetzePhasenmodus: Umschalten auf 1-phasig fehlgeschlagen!');
            }
        }
    }

    private function SteuerungLadefreigabe($pvUeberschuss, $modus = 'pvonly', $ampere = 0, $anzPhasen = 1)
    {
        $minUeberschuss = $this->ReadPropertyInteger('MinLadeWatt'); // z.B. 1400 W

        // Default: Immer FRC=1 → Kein Laden, Wallbox gesperrt (wartet auf Überschuss)
        $sollFRC = 1;

        // PV-Modus: nur Laden bei Überschuss
        if ($modus === 'pvonly' && $pvUeberschuss >= $minUeberschuss) {
            $sollFRC = 2; // Laden erzwingen
        }

        // Manueller Modus: Immer laden, unabhängig vom Überschuss
        if ($modus === 'manuell') {
            $sollFRC = 2;
        }

        // Nur wenn nötig an Wallbox senden!
        $aktFRC = $this->GetValue('AccessStateV2');
        if ($aktFRC !== $sollFRC) {
            $ok = $this->SetForceState($sollFRC);
            if ($ok) {
                $this->LogTemplate('ok', "Ladefreigabe auf FRC=$sollFRC gestellt (Modus: $modus, Überschuss: {$pvUeberschuss}W)");
                IPS_Sleep(1000); // Kleines Delay, damit die Wallbox reagieren kann
            } else {
                $this->LogTemplate('warn', "Ladefreigabe setzen auf FRC=$sollFRC **fehlgeschlagen**!");
            }
        }

        // Wenn Laden aktiviert, Ampere setzen (nur wenn gültig)
        if ($sollFRC == 2 && $ampere > 0) {
            $ok = $this->SetChargingCurrent($ampere);
            if ($ok) {
                $this->LogTemplate('ok', "Ladestrom auf $ampere A gesetzt (Phasen: $anzPhasen).");
            } else {
                $this->LogTemplate('warn', "Setzen des Ladestroms auf $ampere A **fehlgeschlagen**!");
            }
        }
    }

    // =========================================================================
    // 7. HILFSFUNKTIONEN & WERTLOGGING
    // =========================================================================

    private function SetValueAndLogChange($ident, $newValue, $caption = '', $unit = '', $level = 'info')
    {
        $varID = @$this->GetIDForIdent($ident);
        if ($varID === false || $varID === 0) {
            $this->LogTemplate('warn', "Variable mit Ident '$ident' nicht gefunden!");
            return;
        }

        // Versuche, aktuellen Wert robust zu lesen
        try {
            $oldValue = GetValue($varID);
        } catch (Exception $e) {
            $oldValue = null;
        }

        // Wenn identisch, nichts tun
        if ($oldValue === $newValue) {
            return;
        }

        // Werte ggf. als Klartext formatieren
        $formatValue = function($value) use ($varID) {
            $varInfo = @IPS_GetVariable($varID);
            if (!$varInfo) return strval($value);
            $profile = $varInfo['VariableCustomProfile'] ?: $varInfo['VariableProfile'];

            switch ($profile) {
                case 'PVWM.CarStatus':
                    $map = [
                        0 => 'Unbekannt/Firmwarefehler',
                        1 => 'Bereit, kein Fahrzeug',
                        2 => 'Fahrzeug lädt',
                        3 => 'Warte auf Fahrzeug',
                        4 => 'Ladung beendet, Fahrzeug noch verbunden',
                        5 => 'Fehler'
                    ];
                    return $map[intval($value)] ?? $value;

                case 'PVWM.PSM':
                    $map = [0 => 'Auto', 1 => '1-phasig', 2 => '3-phasig'];
                    return $map[intval($value)] ?? $value;

                case 'PVWM.ALW':
                    return ($value ? 'Laden freigegeben' : 'Nicht freigegeben');

                case 'PVWM.AccessStateV2':
                    $map = [
                        0 => 'Neutral (Wallbox entscheidet)',
                        1 => 'Nicht Laden (gesperrt)',
                        2 => 'Laden (erzwungen)'
                    ];
                    return $map[intval($value)] ?? $value;

                case 'PVWM.ErrorCode':
                    $map = [
                        0 => 'Kein Fehler', 1 => 'FI AC', 2 => 'FI DC', 3 => 'Phasenfehler', 4 => 'Überspannung',
                        5 => 'Überstrom', 6 => 'Diodenfehler', 7 => 'PP ungültig', 8 => 'GND ungültig', 9 => 'Schütz hängt',
                        10 => 'Schütz fehlt', 11 => 'FI unbekannt', 12 => 'Unbekannter Fehler', 13 => 'Übertemperatur',
                        14 => 'Keine Kommunikation', 15 => 'Verriegelung klemmt offen', 16 => 'Verriegelung klemmt verriegelt',
                        20 => 'Reserviert 20', 21 => 'Reserviert 21', 22 => 'Reserviert 22', 23 => 'Reserviert 23', 24 => 'Reserviert 24'
                    ];
                    return $map[intval($value)] ?? $value;

                case 'PVWM.Ampere':
                case 'PVWM.AmpereCable':
                    return number_format($value, 0, ',', '.') . ' A';

                case 'PVWM.Watt':
                case 'PVWM.W':
                    return number_format($value, 0, ',', '.') . ' W';

                case 'PVWM.Wh':
                    return number_format($value, 0, ',', '.') . ' Wh';

                case 'PVWM.Percent':
                    return number_format($value, 0, ',', '.') . ' %';

                case 'PVWM.CentPerKWh':
                    return number_format($value, 3, ',', '.') . ' ct/kWh';

                default:
                    if (is_bool($value)) return $value ? 'ja' : 'nein';
                    if (is_numeric($value)) return number_format($value, 0, ',', '.');
                    return strval($value);
            }
        };

        // Meldung zusammensetzen
        $oldText = $formatValue($oldValue);
        $newText = $formatValue($newValue);
        if ($caption) {
            $msg = "$caption geändert: $oldText → $newText";
        } else {
            $msg = "Wert geändert: $oldText → $newText";
        }

        $this->LogTemplate($level, $msg);
        SetValue($varID, $newValue);
    }

    
    // =========================================================================
    // 8. LOGGING / DEBUG / STATUSMELDUNGEN
    // =========================================================================

    private function LogTemplate($type, $short, $detail = '')
        {
            $emojis = [
                'info'  => 'ℹ️',
                'warn'  => '⚠️',
                'error' => '❌',
                'ok'    => '✅',
                'debug' => '🐞'
            ];
            $icon = isset($emojis[$type]) ? $emojis[$type] : 'ℹ️';
            $msg = $icon . ' ' . $short;
            if ($detail !== '') {
                $msg .= ' | ' . $detail;
            }
            if ($type === 'debug' && !$this->ReadPropertyBoolean('DebugLogging')) {
                return;
            }
            IPS_LogMessage('[PVWM]', $msg);
        }

    // =========================================================================
    // 9. BERECHNUNGEN
    // =========================================================================
    private function BerechnePVUeberschuss()
    {
        // PV-Erzeugung holen
        $pvID = $this->ReadPropertyInteger('PVErzeugungID');
        $pvEinheit = $this->ReadPropertyString('PVErzeugungEinheit');
        $pv = ($pvID > 0) ? GetValueFloat($pvID) : 0;
        if ($pvEinheit == "kW") $pv *= 1000;

        // Hausverbrauch holen (inkl. Wallbox-Leistung)
        $hvID = $this->ReadPropertyInteger('HausverbrauchID');
        $hvEinheit = $this->ReadPropertyString('HausverbrauchEinheit');
        $invertHV = $this->ReadPropertyBoolean('InvertHausverbrauch');
        $hausverbrauch = ($hvID > 0) ? GetValueFloat($hvID) : 0;
        if ($hvEinheit == "kW") $hausverbrauch *= 1000;
        if ($invertHV) $hausverbrauch *= -1;

        // Wallbox-Leistung (direkt an Auto, nur für Visualisierung)
        $ladeleistung = $this->GetValue('Leistung');
        $hausverbrauchAbzWallbox = $hausverbrauch - $ladeleistung;

        // Batterie-Ladung: Nur positiv (lädt)
        $batID = $this->ReadPropertyInteger('BatterieladungID');
        $batEinheit = $this->ReadPropertyString('BatterieladungEinheit');
        $invertBat = $this->ReadPropertyBoolean('InvertBatterieladung');
        $batterieladung = ($batID > 0) ? GetValueFloat($batID) : 0;
        if ($batEinheit == "kW") $batterieladung *= 1000;
        if ($invertBat) $batterieladung *= -1;

        // Verbrauch gesamt = Hausverbrauch (inkl. Wallbox) + nur wenn Batterie lädt (batterieladung > 0)
        $verbrauchGesamt = $hausverbrauch;
        if ($batterieladung > 0) $verbrauchGesamt += $batterieladung;

        // --- PV-Überschuss berechnen ---
        $pvUeberschuss = max(0, $pv - $verbrauchGesamt);

        // Wie viele Phasen aktuell? (Kannst du weglassen oder aus Variable lesen)
        $aktuellerPhasenmodus = $this->GetValue('Phasenmodus');
        $anzPhasen = ($aktuellerPhasenmodus == 2) ? 3 : 1;

        // LADENSTROM (AMPERE) BERECHNEN
        $minAmp = $this->ReadPropertyInteger('MinAmpere');
        $maxAmp = $this->ReadPropertyInteger('MaxAmpere');
        $ampere = floor($pvUeberschuss / (230 * $anzPhasen));
        $ampere = max($minAmp, min($maxAmp, $ampere));

        // === ALLE Variablen setzen ===
        $this->SetValueAndLogChange('PV_Ueberschuss', $pvUeberschuss, 'PV-Überschuss', 'W', 'debug');
        $this->SetValueAndLogChange('Hausverbrauch_W', $hausverbrauch, 'Hausverbrauch', 'W', 'debug');
        $this->SetValueAndLogChange('Hausverbrauch_abz_Wallbox', $hausverbrauchAbzWallbox, 'Hausverbrauch abz. Wallbox', 'W', 'debug');
        $this->SetValueAndLogChange('PV_Ueberschuss_A', $ampere, 'PV-Überschuss (A)', 'A', 'debug');

        // Logging
        $this->LogTemplate(
            'debug',
            "PV-Überschuss: PV=$pv W, Haus=$hausverbrauch W, Wallbox=$ladeleistung W, Batterie=$batterieladung W, Phasenmodus=$anzPhasen → Überschuss=$pvUeberschuss W / $ampere A"
        );

        // Rückgabe für die Steuerlogik
        return [
            'ueberschuss_w' => $pvUeberschuss,
            'ueberschuss_a' => $ampere,
            'phasenmodus'   => $anzPhasen
        ];
    }
    
    //=========================================================================
    // 10. EXTERNE SCHNITTSTELLEN & FORECAST
    // =========================================================================
    private function AktualisiereMarktpreise()
    {
        if (!$this->ReadPropertyBoolean('UseMarketPrices')) {
            $this->LogTemplate('info', "Börsenpreis-Update übersprungen (deaktiviert).");
            return;
        }

        // Provider/URL wählen
        $provider = $this->ReadPropertyString('MarketPriceProvider');
        $apiUrl = '';
        if ($provider == 'awattar_at') {
            $apiUrl = 'https://api.awattar.at/v1/marketdata';
        } elseif ($provider == 'awattar_de') {
            $apiUrl = 'https://api.awattar.de/v1/marketdata';
        } elseif ($provider == 'custom') {
            $apiUrl = $this->ReadPropertyString('MarketPriceAPI');
        }
        if ($apiUrl == '') {
            $this->LogTemplate('error', "Keine gültige API-URL für Strompreis-Provider!");
            return;
        }

        // Daten abrufen (mit cURL und Timeout)
        $response = $this->simpleCurlGet($apiUrl);
        if ($response['result'] === false || $response['httpcode'] != 200) {
            $this->LogTemplate(
                'error',
                "Abruf der Börsenpreise fehlgeschlagen! HTTP-Code: {$response['httpcode']}, cURL-Fehler: {$response['error']} (URL: $apiUrl)"
            );
            return;
        }
        $json = $response['result'];
        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['data'])) {
            $this->LogTemplate('error', "Fehlerhafte Antwort der API (keine 'data').");
            return;
        }

        // --- Preise aufbereiten (nächste 36h) ---
        $preise = [];
        $now = time();
        $maxTimestamp = $now + 36 * 3600; // bis max 36h in die Zukunft

        foreach ($data['data'] as $item) {
            if (isset($item['start_timestamp'], $item['marketprice'])) {
                $start = intval($item['start_timestamp'] / 1000);
                if ($start > $maxTimestamp) break;
                $preise[] = [
                    'timestamp' => $start,
                    'price' => floatval($item['marketprice'] / 10.0) // €/MWh → ct/kWh
                ];
            }
        }

        if (count($preise) === 0) {
            $this->LogTemplate('warn', "Keine gültigen Preisdaten gefunden!");
            return;
        }

        // Aktuellen Preis setzen (erster Datensatz)
        $aktuellerPreis = $preise[0]['price'];
        $this->SetValueAndLogChange('CurrentSpotPrice', $aktuellerPreis);

        // Forecast als JSON speichern
        $this->SetValueAndLogChange('MarketPrices', json_encode($preise));

        $this->LogTemplate('ok', "Börsenpreise aktualisiert: Aktuell {$aktuellerPreis} ct/kWh – " . count($preise) . " Preispunkte gespeichert.");
    }

}
