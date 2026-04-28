<?php
class PVWallboxManager extends IPSModule
{
    // =========================================================================
    // 1. KONSTANTEN
    // =========================================================================

    private const MODE_KEEP_CURRENT = -1;
    private const MODE_PVONLY = 0;
    private const MODE_PV2CAR = 1;
    private const MODE_MANUELL = 2;
    private const MODE_TARGET_TIME = 3;
    private const MODE_TARGET_TIME_PV = 4;

    private const PHASE_MODE_1P = 1;
    private const PHASE_MODE_3P = 2;

    private const NO_POWER_THRESHOLD_W = 300;
    private const NO_POWER_COUNTER_LIMIT = 3;
    private const PHASE_SWITCH_COOLDOWN_S = 30;
    private const CURRENT_CHANGE_COOLDOWN_S = 20;

    // =========================================================================
    // 2. CREATE / APPLYCHANGES / FORM
    // =========================================================================

    public function Create()
    {
        parent::Create();

        $this->RegisterCustomProfiles();
        $this->registerAttributes([
            'MarketPricesTimerInterval'      => 0,
            'MarketPricesActive'             => false,
            'Phasen1Zaehler'                 => 0,
            'Phasen3Zaehler'                 => 0,
            'LadeStartZaehler'               => 0,
            'LadeStopZaehler'                => 0,
            'HausverbrauchAbzWallboxBuffer'  => '[]',
            'HausverbrauchAbzWallboxLast'    => 0.0,
            'NoPowerCounter'                 => 0,
            'LastTimerStatus'                => -1,
            'NeutralModeUntil'               => 0,
            'LetztePhasenUmschaltung'        => 0,
            'LastStatusInfoHTML'             => '',
            'LastChargingCurrent'            => 0,
            'LastChargingCurrentChange'      => 0,
            'LastSentChargingCurrent'        => 0,
            'SmoothedSurplus'                => 0.0,
            'LastCarConnected'               => false,
            'StartupTimestamp'               => 0,
        ]);

        $this->registerProperties([
            'WallboxIP'             => ['type'=>'string',  'default'=>'0.0.0.0'],
            'WallboxAPIKey'         => ['type'=>'string',  'default'=>''],
            'RefreshInterval'       => ['type'=>'integer', 'default'=>30],
            'ModulAktiv'            => ['type'=>'boolean', 'default'=>true],
            'ModeAfterUnplug'       => ['type'=>'integer', 'default'=>0],
            'DebugLogging'          => ['type'=>'boolean', 'default'=>false],
            'MinAmpere'             => ['type'=>'integer', 'default'=>6],
            'MaxAmpere'             => ['type'=>'integer', 'default'=>16],
            'Phasen1Schwelle'       => ['type'=>'integer', 'default'=>3680],
            'Phasen3Schwelle'       => ['type'=>'integer', 'default'=>4140],
            'HausakkuSOCID'         => ['type'=>'integer', 'default'=>0],
            'HausakkuSOCVollSchwelle'=>['type'=>'integer',  'default'=>95],
            'CarSOCID'              => ['type'=>'integer', 'default'=>0],
            'CarTargetSOCID'        => ['type'=>'integer', 'default'=>0],
            'CarBatteryCapacity'    => ['type'=>'float',   'default'=>0],
            'Phasen1Limit'          => ['type'=>'integer', 'default'=>3],
            'Phasen3Limit'          => ['type'=>'integer', 'default'=>3],
            'MinLadeWatt'           => ['type'=>'integer', 'default'=>1400],
            'MinStopWatt'           => ['type'=>'integer', 'default'=>1100],
            'StartLadeHysterese'    => ['type'=>'integer', 'default'=>3],
            'StopLadeHysterese'     => ['type'=>'integer', 'default'=>3],
            'InitialCheckInterval'  => ['type'=>'integer', 'default'=>10],
            'PVErzeugungID'         => ['type'=>'integer', 'default'=>0],
            'PVErzeugungEinheit'    => ['type'=>'string',  'default'=>'W'],
            'HausverbrauchID'       => ['type'=>'integer', 'default'=>0],
            'HausverbrauchEinheit'  => ['type'=>'string',  'default'=>'W'],
            'InvertHausverbrauch'   => ['type'=>'boolean', 'default'=>false],
            'BatterieladungID'      => ['type'=>'integer', 'default'=>0],
            'BatterieladungEinheit' => ['type'=>'string',  'default'=>'W'],
            'InvertBatterieladung'  => ['type'=>'boolean', 'default'=>false],
            'UseMarketPrices'       => ['type'=>'boolean', 'default'=>false],
            'MarketPriceProvider'   => ['type'=>'string',  'default'=>'awattar_at'],
            'MarketPriceAPI'        => ['type'=>'string',  'default'=>''],
            'MarketPriceBasePrice'  => ['type'=>'float',   'default'=>0.00],
            'MarketPriceSurcharge'  => ['type'=>'float',   'default'=>0.00],
            'MarketPriceTaxRate'    => ['type'=>'float',   'default'=>0.00],
            'SmoothingAlpha'        => ['type'=>'float',   'default'=>0.5],
            'MaxRampDeltaAmp'       => ['type'=>'integer', 'default'=>2],
        ]);

        $this->RegisterVariableBoolean('ModulAktiv_Switch', '✅ Modul aktiv', '~Switch', 900);

        $this->registerVariables([
            ['integer', 'Status',                       'Status',                                   'PVWM.CarStatus',            1,  'Car'],
            ['integer', 'AccessStateV2',                'Wallbox Modus',                            'PVWM.AccessStateV2',        2,  'LockOpen'],
            ['float',   'Leistung',                     'Aktuelle Ladeleistung zum Fahrzeug (W)',   'PVWM.Watt',                 3,  'Flash'],
            ['integer', 'Ampere',                       'Max. Ladestrom (A)',                       'PVWM.Ampere',               4,  'Energy'],
            ['boolean', 'Freigabe',                     'Ladefreigabe',                             'PVWM.ALW',                  6,  'Power'],
            ['integer', 'Kabelstrom',                   'Kabeltyp (A)',                             'PVWM.AmpereCable',          7,  'Energy'],
            ['float',   'Energie',                      'Geladene Energie (Wh)',                    'PVWM.Wh',                   8,  null],
            ['integer', 'Fehlercode',                   'Fehlercode',                               'PVWM.ErrorCode',            9,  null],
            ['float',   'PV_Ueberschuss',               '☀️ PV-Überschuss (W)',                     'PVWM.Watt',                10, 'solar-panel'],
            ['integer', 'PV_Ueberschuss_A',             '⚡ PV-Überschuss (A)',                     'PVWM.Ampere',              12, 'Energy'],
            ['float',   'Hausverbrauch_W',              '🏠 Hausverbrauch (W)',                     'PVWM.Watt',                13, 'home'],
            ['float',   'Hausverbrauch_abz_Wallbox',    '🏠 Hausverbrauch abzügl. Wallbox (W)',     'PVWM.Watt',                15, 'home'],
            ['float',   'CurrentSpotPrice',             'Aktueller Börsenpreis (ct/kWh)',           'PVWM.CentPerKWh',          30, 'Euro'],
            ['string',  'MarketPrices',                 'Börsenpreis-Vorschau',                     '',                         31, null],
            ['string',  'MarketPricesPreview',          '📊 Börsenpreis-Vorschau (HTML)',           '~HTMLBox',                 32, null],
            ['integer', 'TargetTime',                   'Zielzeit',                                 '~UnixTimestampTime',       20, 'clock'],
            ['integer', 'LademodusAuswahl',             '🔁 Lademodus',                             'PVWM.Lademodus',           39, 'Shuffle'],
            ['integer', 'PVAnteil',                     'PV-Anteil (%)',                            'PVWM.Percent',             43, 'Percent'],
            ['integer', 'ManuellAmpere',                '🔌 Ampere (manuell)',                      'PVWM.Ampere',              44, null],
            ['integer', 'ManuellPhasen',                '🔀 Phasen (manuell)',                      'PVWM.PSM',                 45, null],
            ['integer', 'PhasenmodusEinstellung',       '🟢 Wallbox Phasen (Soll)',                 'PVWM.PSM',                 50, 'Lightning'],
            ['integer', 'Phasenmodus',                  '🔵 Fahrzeug nutzt Phasen (Ist)',           'PVWM.PhasenText',          51, 'Lightning'],
            ['string',  'StatusInfo',                   'ℹ️ Status-Info',                            '~HTMLBox',                70,  null],
            ['string',  'ChargeTime',                   '⏳ Ladezeit',                              '',                         80 , null],
        ]);

        $this->EnableAction('ModulAktiv_Switch');
        $this->EnableAction('LademodusAuswahl');
        $this->EnableAction('ManuellAmpere');
        $this->EnableAction('ManuellPhasen');
        $this->EnableAction('PVAnteil');

        $this->RegisterTimer('PVWM_UpdateStatus',       0, 'IPS_RequestAction('.$this->InstanceID.',"UpdateStatus","pvonly");');
        $this->RegisterTimer('PVWM_UpdateMarketPrices', 0, 'IPS_RequestAction('.$this->InstanceID.',"UpdateMarketPrices","");');
        $this->RegisterTimer('PVWM_InitialCheck',       0, 'IPS_RequestAction('.$this->InstanceID.',"UpdateStatus","pvonly");');

        $this->SetTimerNachModusUndAuto();
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->WriteAttributeFloat('SmoothedSurplus', 0.0);

        $aktiv = $this->ReadPropertyBoolean('ModulAktiv');
        $this->SetValue('ModulAktiv_Switch', $aktiv);

        $this->SetTimerInterval('PVWM_UpdateStatus', 0);
        $this->SetTimerInterval('PVWM_UpdateMarketPrices', 0);
        $this->SetTimerInterval('PVWM_InitialCheck', 0);

        $this->SetTimerNachModusUndAuto();
        $this->SetMarketPriceTimerZurVollenStunde();
        $this->UpdateHausverbrauchEvent();
        $this->validateAmpereConfiguration();

        $this->WriteAttributeInteger('LadeStartZaehler', 0);
        $this->WriteAttributeInteger('LadeStopZaehler', 0);
        $this->WriteAttributeInteger('Phasen1Zaehler', 0);
        $this->WriteAttributeInteger('Phasen3Zaehler', 0);
        $this->WriteAttributeInteger('LastSentChargingCurrent', 0);
        $this->WriteAttributeInteger('LastChargingCurrent', 0);
        $this->WriteAttributeBoolean('LastCarConnected', false);

        if ($aktiv) {
            $this->WriteAttributeInteger('StartupTimestamp', time());
            $this->UpdateStatus('startup');
        }
    }

    public function GetConfigurationForm()
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        $version = 'unbekannt';
        $name = 'PVWallboxManager';

        $moduleFile = __DIR__ . '/module.json';
        if (file_exists($moduleFile)) {
            $moduleJson = json_decode(file_get_contents($moduleFile), true);
            if (is_array($moduleJson)) {
                if (isset($moduleJson['version'])) {
                    $version = (string) $moduleJson['version'];
                }
                if (isset($moduleJson['name'])) {
                    $name = (string) $moduleJson['name'];
                }
            }
        }

        array_unshift($form['elements'], [
            'type'    => 'ExpansionPanel',
            'caption' => 'ℹ️ Modulinfo',
            'items'   => [
                [
                    'type'    => 'Label',
                    'caption' => $name . ' – Version ' . $version
                ]
            ]
        ]);

        return json_encode($form);
    }

    // =========================================================================
    // 3. REGISTRIERUNG: PROFILES / ATTRIBUTES / PROPERTIES / VARIABLES
    // =========================================================================

    private function RegisterCustomProfiles()
    {
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
                    IPS_SetVariableProfileAssociation($name, $a[0], $a[1], $a[2] ?? '', $a[3] ?? -1);
                }
            }
        };

        $create('PVWM.CarStatus', VARIABLETYPE_INTEGER, 0,  '', 'Car', [
            [0, 'Unbekannt/Firmwarefehler',                 'Question',     0x888888],
            [1, 'Bereit, kein Fahrzeug',                    'Parking',      0xAAAAAA],
            [2, 'Fahrzeug lädt',                            'Lightning',    0x00FF00],
            [3, 'Fahrzeug verbunden / Bereit zum Laden',    'Car',          0x0088FF],
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
//            [0, 'Auto',     'Gears', 0xAAAAAA],
            [1, '1-phasig', 'Plug', 0x00ADEF],
            [2, '3-phasig', 'Plug', 0xFF9900]
        ]);

        $create('PVWM.Lademodus', VARIABLETYPE_INTEGER, 0, '', 'Shuffle', [
            [0, 'Nur PV',     'SolarPanel', 0x44AA44],
            [1, 'PV-Anteil',  'Sun',        0xFFCC00],
            [2, 'Manuell',    'Power',      0xFF8800]
        ]);

        $create('PVWM.PhasenText', VARIABLETYPE_INTEGER, 0, '', 'Lightning', [
            [0, 'Keine Ladung', '', 0xAAAAAA],
            [1, '1-phasig',     '', 0x2186eb],
            [2, '2-phasig',     '', 0x2186eb],
            [3, '3-phasig',     '', 0x2186eb]
        ]);

        $create('PVWM.ALW', VARIABLETYPE_BOOLEAN, 0, '', 'Power', [
            [false, 'Nicht freigegeben', 'Close', 0xFF4444],
            [true,  'Laden freigegeben', 'Power', 0x44FF44]
        ]);

        $create('PVWM.AmpereCable', VARIABLETYPE_INTEGER, 0, ' A', 'Energy');
        
        $create('PVWM.Ampere',      VARIABLETYPE_INTEGER, 0, ' A',      'Energy');
        IPS_SetVariableProfileValues("PVWM.Ampere", 6, 32, 1);
        $create('PVWM.Percent',     VARIABLETYPE_INTEGER, 0, ' %',      'Percent');
        IPS_SetVariableProfileValues('PVWM.Percent', 0, 100, 1);
        $create('PVWM.Watt',        VARIABLETYPE_FLOAT,   0, ' W',      'Flash');
        $create('PVWM.W',           VARIABLETYPE_FLOAT,   0, ' W',      'Flash');
        $create('PVWM.CentPerKWh',  VARIABLETYPE_FLOAT,   3, ' ct/kWh', 'Euro');
        $create('PVWM.Wh',          VARIABLETYPE_FLOAT,   0, ' Wh',     'Lightning');

    }

    private function GetProfileText($ident)
    {
        $vid = @$this->GetIDForIdent($ident);
        if (!$vid) return '';
        $val = GetValue($vid);
        $v = IPS_GetVariable($vid);
        $profile = $v['VariableCustomProfile'] ?: $v['VariableProfile'];
        if (!$profile || !IPS_VariableProfileExists($profile)) return (string)$val;

        $assos = IPS_GetVariableProfile($profile)['Associations'];
        foreach ($assos as $a) {
            if ($a['Value'] == $val) return $a['Name'];
        }
        return (string)$val;
    }

    private function registerAttributes(array $list): void
    {
        foreach ($list as $ident => $default) {
            switch (gettype($default)) {
                case 'boolean': $this->RegisterAttributeBoolean($ident, $default); break;
                case 'integer': $this->RegisterAttributeInteger($ident, $default); break;
                case 'double':  $this->RegisterAttributeFloat($ident,   $default); break;
                case 'string':  $this->RegisterAttributeString($ident,  $default); break;
            }
        }
    }

    private function registerProperties(array $list): void
    {
        foreach ($list as $ident => $cfg) {
            switch ($cfg['type']) {
                case 'string':  $this->RegisterPropertyString($ident, $cfg['default']); break;
                case 'integer': $this->RegisterPropertyInteger($ident, $cfg['default']); break;
                case 'boolean': $this->RegisterPropertyBoolean($ident, $cfg['default']); break;
                case 'float':   $this->RegisterPropertyFloat($ident,   $cfg['default']); break;
            }
        }
    }

    private function registerVariables(array $list): void
    {
        foreach ($list as $cfg) {
            list($type, $ident, $name, $profile, $position, $icon) = $cfg + [null,null,null,null,null,null];
            switch ($type) {
                case 'integer': $this->RegisterVariableInteger($ident, $name, $profile, $position); break;
                case 'float':   $this->RegisterVariableFloat($ident,   $name, $profile, $position); break;
                case 'boolean': $this->RegisterVariableBoolean($ident, $name, $profile, $position); break;
                case 'string':  $this->RegisterVariableString($ident,  $name, $profile, $position); break;
            }
            if (!empty($icon)) {
                IPS_SetIcon($this->GetIDForIdent($ident), $icon);
            }
        }
    }

    // =========================================================================
    // 4. REQUESTACTION / UI-EINGABEN / BEDIENLOGIK
    // =========================================================================

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'ModulAktiv_Switch':
                $this->handleModulAktivSwitch((bool) $Value);
                return;

            case 'UpdateStatus':
                $this->UpdateStatus((string) $Value);
                return;

            case 'UpdateMarketPrices':
                $this->AktualisiereMarktpreise();
                $this->SetTimerInterval('PVWM_UpdateMarketPrices', 3600000);
                return;

            case 'LademodusAuswahl':
                $this->handleLademodusAuswahl((int) $Value);
                return;

            case 'PVAnteil':
                $this->handlePVAnteilChange((int) $Value);
                return;

            case 'ManuellAmpere':
                $this->handleManuellAmpereChange((int) $Value);
                return;

            case 'ManuellPhasen':
                $this->handleManuellPhasenChange((int) $Value);
                return;

            default:
                throw new Exception("Invalid Ident: $Ident");
        }
    }

    private function handleModulAktivSwitch(bool $active): void
    {
        $this->SetValue('ModulAktiv_Switch', $active);

        IPS_SetProperty($this->InstanceID, 'ModulAktiv', $active);
        IPS_ApplyChanges($this->InstanceID);

        if (!$active) {
            $this->SetForceState(1);
            $this->ResetModiNachLadeende();

            $this->SetValue('PV_Ueberschuss', 0);
            $this->SetValue('PV_Ueberschuss_A', 0);
            $this->SetValue('Hausverbrauch_W', 0);
            $this->SetValue('Hausverbrauch_abz_Wallbox', 0);

            $this->SetTimerInterval('PVWM_UpdateStatus', 0);
            $this->SetTimerInterval('PVWM_InitialCheck', 0);
            $this->SetTimerInterval('PVWM_UpdateMarketPrices', 0);

            $this->LogTemplate('info', 'Modul deaktiviert – Wallbox gesperrt, Modi zurückgesetzt, Timer gestoppt.');
        }

        $this->UpdateStatusAnzeige();
    }

    private function handleLademodusAuswahl(int $mode): void
    {
        if (!in_array($mode, [self::MODE_PVONLY, self::MODE_PV2CAR, self::MODE_MANUELL], true)) {
            throw new Exception("Ungültiger Wert für LademodusAuswahl: $mode");
        }

        $this->applyChargingMode($this->mapSelectionToMode($mode));
    }

    private function handlePVAnteilChange(int $value): void
    {
        $value = max(0, min(100, $value));
        $this->SetValue('PVAnteil', $value);
        $this->LogTemplate('info', "PV-Anteil geändert", "{$value}%");

        if ($this->getCurrentModeKey() === 'pv2car') {
            $this->UpdateStatus('pv2car');
        }
    }

    private function handleManuellAmpereChange(int $amp): void
    {
        $amp = max(
            $this->ReadPropertyInteger('MinAmpere'),
            min($this->ReadPropertyInteger('MaxAmpere'), $amp)
        );

        $this->SetValue('ManuellAmpere', $amp);

        if ($this->getCurrentModeKey() === 'manuell') {
            $this->UpdateStatus('manuell');
        }
    }

    private function handleManuellPhasenChange(int $phases): void
    {
        $phases = ($phases == 2) ? 2 : 1;
        $this->SetValue('ManuellPhasen', $phases);

        if ($this->getCurrentModeKey() === 'manuell') {
            $this->UpdateStatus('manuell');
        }
    }

    private function applyChargingMode(string $mode): void
    {
        $allowedModes = ['pvonly', 'pv2car', 'manuell'];
        if (!in_array($mode, $allowedModes, true)) {
            throw new Exception("Ungültiger Modus: $mode");
        }

        $oldMode      = $this->getCurrentModeKey();
        $oldSelection = $this->getCurrentModeSelection();
        $newSelection = $this->mapModeToSelection($mode);

        if ($oldSelection === $newSelection) {
            $this->SetTimerNachModusUndAuto();
            $this->UpdateStatus($mode);
            return;
        }

        $this->SetValue('LademodusAuswahl', $newSelection);

        switch ($mode) {
            case 'pvonly':
                $this->LogTemplate('info', 'Lademodus geändert', 'Nur PV');
                break;
            case 'pv2car':
                $this->LogTemplate('info', 'Lademodus geändert', 'PV-Anteil');
                break;
            case 'manuell':
                $this->LogTemplate('info', 'Lademodus geändert', 'Manuell');
                break;
        }

        if ($mode === 'pvonly') {
            $this->resetToPvOnlyBaseState($oldMode === 'manuell');
        }

        $this->SetTimerNachModusUndAuto();
        $this->UpdateStatus($mode);
    }

    private function resetToPvOnlyBaseState(bool $fromManual = false): void
    {
        $this->SetPhaseMode(self::PHASE_MODE_1P);
        $this->SetChargingCurrent(6);
        $this->SetValueAndLogChange('PV_Ueberschuss_A', 0, 'PV-Überschuss (A)', 'A', 'debug');

        $this->LogTemplate('ok', 'PVonly-Basis gesetzt', '1-phasig / 6A');

        if ($fromManual) {
            $this->WriteAttributeInteger('LadeStartZaehler', 0);
            $this->WriteAttributeInteger('LadeStopZaehler', 0);
            $this->LogTemplate('debug', 'Hysterese-Zähler zurückgesetzt', 'nach Verlassen von Manuell');

            $neutralUntil = time() + 30;
            $this->WriteAttributeInteger('NeutralModeUntil', $neutralUntil);
            $this->LogTemplate('debug', 'Neutralmodus aktiv', 'bis ' . date('H:i:s', $neutralUntil));

            IPS_Sleep(1000);
            $this->UpdateStatus();
        }
    }

    // =========================================================================
    // 5. HAUPTZYKLUS / UPDATESTATUS / ROUTING
    // =========================================================================

    public function UpdateStatus(string $triggerMode = '')
    {
        $activeMode = $this->getCurrentModeKey();
        $this->LogTemplate('debug', 'UpdateStatus', "Modus={$activeMode}, Zeit=" . date('H:i:s'));

        if ($this->handleNeutralMode()) {
            return;
        }

        $this->handleInitialCheck();

        $data = $this->getStatusFromCharger();
        if ($data === false) {
            $this->handleChargerUnavailable();
            return;
        }

        $phasen = $this->determinePhases($data);

        $energyRaw = $this->gatherEnergyData();

        if ($triggerMode === 'startup') {
            $startupTs = $this->ReadAttributeInteger('StartupTimestamp');

            if ($startupTs > 0 && (time() - $startupTs) < 5) {

                if ($energyRaw['pv'] === 0 || $energyRaw['haus'] === 0) {
                    $this->LogTemplate(
                        'debug',
                        'Startup-Run übersprungen',
                        'Energiewerte noch nicht valide'
                    );

                    $this->SetTimerNachModusUndAuto();
                    return;
                }
            }
        }

        $this->updateHousePower($energyRaw);

        if (!$this->isCarConnected($data)) {
            $this->updateSurplusDisplayWithoutCar($energyRaw);
        }

        $vars = $this->extractChargerVariables($data);
        $this->syncChargerVariables($vars, $phasen);
        
        if ($this->PruefeLadeendeAutomatisch()) {
            $this->UpdateStatusAnzeige();
            $this->HandleLadezeitLogging();
            $this->SetTimerNachModusUndAuto();
            return;
        }

        $this->routeChargingMode($data, $phasen);
        $this->SetTimerNachModusUndAuto();
        $this->UpdateStatusAnzeige();
        $this->HandleLadezeitLogging();
    }

    private function collectStatusData(): array
    {
        $socVarID    = $this->ReadPropertyInteger('CarSOCID');
        $socAktuell  = ($socVarID > 0 && @IPS_VariableExists($socVarID))
            ? GetValue($socVarID) . '%'
            : 'n/a';

        $targetVarID = $this->ReadPropertyInteger('CarTargetSOCID');
        $socZiel     = ($targetVarID > 0 && @IPS_VariableExists($targetVarID))
            ? GetValue($targetVarID) . '%'
            : 'n/a';

        $status     = $this->GetValue('Status');
        $inInitial  = ($status === false || $status <= 1);
        $initialInt = $this->ReadPropertyInteger('InitialCheckInterval');

        $until        = intval($this->ReadAttributeInteger('NeutralModeUntil'));
        $neutralActive = ($until > time());

        $modeKey = $this->getCurrentModeKey();

        switch ($modeKey) {
            case 'manuell':
                $modusText = sprintf(
                    '🔌 Manuell: Vollladen (%d-phasig, %d A)',
                    $this->GetValue('ManuellPhasen'),
                    $this->GetValue('ManuellAmpere')
                );
                break;

            case 'pv2car':
                $modusText = '🌞 PV-Anteil (' . $this->GetValue('PVAnteil') . '%)';
                break;

            case 'pvonly':
            default:
                $modusText = '☀️ Nur PV (PV-Überschuss)';
                break;
        }

        $moduleActive = $this->ReadPropertyBoolean('ModulAktiv');

        $psmSollTxt = $this->GetProfileText('PhasenmodusEinstellung');
        $psmIstTxt  = $this->GetProfileText('Phasenmodus');
        $statusTxt  = $this->GetProfileText('Status');
        $frcTxt     = $this->GetProfileText('AccessStateV2');

        return [
            'socAktuell'    => $socAktuell,
            'socZiel'       => $socZiel,
            'inInitial'     => $inInitial,
            'initialInt'    => $initialInt,
            'neutralActive' => $neutralActive,
            'neutralUntil'  => $until,
            'modusText'     => $modusText,
            'psmSollTxt'    => $psmSollTxt,
            'psmIstTxt'     => $psmIstTxt,
            'statusTxt'     => $statusTxt,
            'frcTxt'        => $frcTxt,
            'moduleActive'  => $moduleActive,
        ];
    }

    private function routeChargingMode(array $data, int $phasen): void
    {
        $handlers = [
            'manuell' => 'ModusManuellVollladen',
            'pv2car'  => 'ModusPV2CarLaden',
            'pvonly'  => 'ModusPVonlyLaden',
        ];

        if (!$this->handleCarConnectionState($data)) {
            $this->handleNoCarConnected();
            $this->SetTimerNachModusUndAuto();
            return;
        }

        $key = $this->getCurrentModeKey();

        if (!isset($handlers[$key])) {
            $this->LogTemplate('warn', 'Unbekannter Lademodus', $key);
            return;
        }

        $method = $handlers[$key];

        if ($key === 'pvonly') {
            $this->$method($data, $phasen);
            return;
        }

        $this->$method($data);
    }

    private function handleNeutralMode(): bool
    {
        $until = $this->ReadAttributeInteger('NeutralModeUntil');
        if ($until > time()) {
            $this->LogTemplate('debug', 'Neutralmodus aktiv', 'Ladefreigabe gesperrt bis ' . date('H:i:s', $until));
            $this->SetForceState(1);
            return true;
        }
        return false;
    }

    private function handleInitialCheck(): bool
    {
        $status    = $this->GetValue('Status');
        $inInitial = ($status === false || $status <= 1);
        if ($inInitial) {
            $this->LogTemplate('debug', 'Initial-Check aktiv', "Status={$status}");
        }
        return $inInitial;
    }

    // =========================================================================
    // 6. LADEMODI
    // =========================================================================

    private function ModusPVonlyLaden(array $data, int $anzPhasenAlt)
    {
        if (!$this->isCarConnected($data)) {
            $this->handleNoCarConnected();
            return;
        }

        $energy = $this->gatherEnergyData();

        $energy = $this->applyFilters($energy);

        $surplus       = $this->calculateSurplus($energy, $anzPhasenAlt, true);
        $pvUeberschuss = $surplus['ueberschuss_w'];
        $ampere        = $surplus['ueberschuss_a'];

        $this->PruefeUndSetzePhasenmodus($pvUeberschuss);

        $minLadeWatt = $this->ReadPropertyInteger('MinLadeWatt');
        $aktFRC      = $this->GetValue('AccessStateV2') === 2 ? 2 : 1;

        if ($aktFRC === 1 && $pvUeberschuss >= $minLadeWatt) {
            $desiredFRC = 2;

            $this->WriteAttributeInteger('LadeStartZaehler', 0);
            $this->WriteAttributeInteger('LadeStopZaehler', 0);

            $this->LogTemplate(
                'start',
                'PVonly Schnellstart',
                'Überschuss=' . round($pvUeberschuss) . ' W, Mindestleistung=' . $minLadeWatt . ' W'
            );
        } else {
            $desiredFRC = $this->BerechneLadefreigabeMitHysterese($pvUeberschuss);
        }

        $anzPhasenNeu = max(1, $this->GetValue('Phasenmodus'));

        if ($anzPhasenNeu !== $anzPhasenAlt) {
            $surplus = $this->calculateSurplus($energy, $anzPhasenNeu, true);
            $ampere  = $surplus['ueberschuss_a'];

            $this->LogTemplate(
                'debug',
                'Ampere nach Phasenwechsel neu berechnet',
                "Phasen={$anzPhasenNeu}, Strom={$ampere} A"
            );
        }

        $this->SteuerungLadefreigabe(
            $pvUeberschuss,
            'pvonly',
            $ampere,
            $anzPhasenNeu,
            $desiredFRC
        );
    }

    private function ModusPV2CarLaden(array $data)
    {
        if (!$this->isCarConnected($data)) {
            $this->handleNoCarConnected();
            return;
        }

        $anteil = max(0, min(100, intval($this->GetValue('PVAnteil'))));

        $oldPhasen = max(1, $this->GetValue('Phasenmodus'));

        $energy     = $this->gatherEnergyData();
        $filtered   = $this->applyFilters($energy);
        $rawSurplus = max(0, $energy['pv'] - $filtered['hausFiltered']);

        $alpha      = $this->ReadPropertyFloat('SmoothingAlpha');
        $lastSmooth = $this->ReadAttributeFloat('SmoothedSurplus');
        if ($lastSmooth <= 0) {
            $smooth = $rawSurplus;
        } else {
            $smooth = $alpha * $rawSurplus + (1 - $alpha) * $lastSmooth;
        }
        $this->WriteAttributeFloat('SmoothedSurplus', $smooth);

        $anteilWatt = intval(round($smooth * $anteil / 100));

        $this->PruefeUndSetzePhasenmodus($smooth);
        $newPhasen = max(1, $this->GetValue('Phasenmodus'));
        if ($newPhasen !== $oldPhasen) {
            $energy     = $this->gatherEnergyData();
            $filtered   = $this->applyFilters($energy);
            $rawSurplus = max(0, $energy['pv'] - $filtered['hausFiltered']);
            $smooth     = $alpha * $rawSurplus + (1 - $alpha) * $smooth;
            $anteilWatt = intval(round($smooth * $anteil / 100));
        }

        $minAmp   = $this->ReadPropertyInteger('MinAmpere');
        $maxAmp   = $this->ReadPropertyInteger('MaxAmpere');
        $desiredA = (int)ceil($anteilWatt / (230 * $newPhasen));
        $desiredA = max($minAmp, min($maxAmp, $desiredA));

        $minLadeWatt = $this->ReadPropertyInteger('MinLadeWatt');
        $aktFRC      = $this->GetValue('AccessStateV2') === 2 ? 2 : 1;
        $isFastStart = ($aktFRC === 1 && $anteilWatt >= $minLadeWatt);

        $lastA    = $this->ReadAttributeInteger('LastChargingCurrent');
        $maxDelta = $this->ReadPropertyInteger('MaxRampDeltaAmp');

        if ($isFastStart) {
            // Schnellstart: Ramp-Up beim ersten Start überspringen
            $ampere = $desiredA;
        } else {
            $diff   = max(-$maxDelta, min($maxDelta, $desiredA - $lastA));
            $ampere = $lastA + $diff;
        }

        $this->WriteAttributeInteger('LastChargingCurrent', $ampere);

        $this->SetValueAndLogChange('PV_Ueberschuss',   round($smooth), 'PV-Überschuss',     'W', 'debug');
        $this->SetValueAndLogChange('PV_Ueberschuss_A', $ampere,         'PV-Überschuss (A)', 'A', 'debug');

        if ($isFastStart) {
            $desiredFRC = 2;

            $this->WriteAttributeInteger('LadeStartZaehler', 0);
            $this->WriteAttributeInteger('LadeStopZaehler', 0);

            $this->LogTemplate(
                'start',
                'PV2Car Schnellstart',
                'Anteil=' . round($anteilWatt) . ' W, Strom=' . $ampere . ' A, Phasen=' . $newPhasen
            );
        } else {
            $desiredFRC = $this->BerechneLadefreigabeMitHysterese($anteilWatt);
        }

        $this->SteuerungLadefreigabe(
            $smooth,
            'pv2car',
            $ampere,
            $newPhasen,
            $desiredFRC
        );
    }

    private function ModusManuellVollladen(array $data)
    {
        if (!$this->isCarConnected($data)) {
            $this->handleNoCarConnected();
            return;
        }

        $anzPhasenGewuenscht = $this->GetValue('ManuellPhasen') == 2 ? 2 : 1;
        $ampereGewuenscht    = intval($this->GetValue('ManuellAmpere'));
        $minAmp = $this->ReadPropertyInteger('MinAmpere');
        $maxAmp = $this->ReadPropertyInteger('MaxAmpere');
        $ampereGewuenscht = max($minAmp, min($maxAmp, $ampereGewuenscht));

        $aktPhasen = $this->GetValue('Phasenmodus');
        if ($aktPhasen !== $anzPhasenGewuenscht) {
            $this->SetPhaseMode($anzPhasenGewuenscht);
            $this->LogTemplate('debug', 'Manuell Phasenmodus gewechselt', "{$aktPhasen} → {$anzPhasenGewuenscht}");
        }

        $anzPhasenIst = max(1, $this->GetValue('Phasenmodus'));

        $energy   = $this->gatherEnergyData();
        $energy   = $this->applyFilters($energy);
        $surplus  = $this->calculateSurplus($energy, $anzPhasenIst, false);

        $ueberschuss_w = $surplus['ueberschuss_w'];
        $ueberschuss_a = $surplus['ueberschuss_a'];

        $this->SetValue('PV_Ueberschuss',   $ueberschuss_w);
        $this->SetValue('PV_Ueberschuss_A', $ueberschuss_a);

        $this->SetValueAndLogChange('Phasenmodus', $anzPhasenIst, 'Genutzte Phasen (Fahrzeug)', '', 'debug');

        $this->SteuerungLadefreigabe(0, 'manuell', $ampereGewuenscht, $anzPhasenIst);

        $this->WriteAttributeInteger('LadeStartZaehler', 0);
        $this->WriteAttributeInteger('LadeStopZaehler', 0);
        $this->LogTemplate('debug', 'Manuell aktiv', 'Hysterese und automatische Phasenumschaltung deaktiviert');

        $this->LogTemplate(
            'ok',
            'Manuelles Vollladen aktiv',
            sprintf(
                '%d-phasig / %d A | PV=%d W, Haus=%d W, Wallbox=%d W, Batterie=%d W, Überschuss=%d W / %d A',
                $anzPhasenIst,
                $ampereGewuenscht,
                $energy['pv'],
                $energy['hausFiltered'],
                $energy['wallbox'],
                $energy['batt'],
                $ueberschuss_w,
                $ueberschuss_a
            )
        );

        $this->SetTimerNachModusUndAuto();
    }

    // =========================================================================
    // 7. LADELOGIK / HYSTERESEN / LADEENDE / VERBINDUNGSSTATUS
    // =========================================================================

    private function BerechneLadefreigabeMitHysterese(int $pvUeberschuss): int
    {
        $minLadeWatt  = $this->ReadPropertyInteger('MinLadeWatt');
        $minStopWatt  = $this->ReadPropertyInteger('MinStopWatt');
        $startHys     = $this->ReadPropertyInteger('StartLadeHysterese');
        $stopHys      = $this->ReadPropertyInteger('StopLadeHysterese');
        $startZ       = $this->ReadAttributeInteger('LadeStartZaehler');
        $stopZ        = $this->ReadAttributeInteger('LadeStopZaehler');

        $aktFRC     = $this->GetValue('AccessStateV2') === 2 ? 2 : 1;
        $desiredFRC = $aktFRC;

        // 🛑 Sperre nach Moduswechsel – kein Start erlaubt
        if ($aktFRC === 2 && $this->VerhindereStopHystereseKurzNachModuswechsel(15)) {
            return $aktFRC;
        }

        // 🟢 Start-Hysterese NUR wenn aktuell noch NICHT lädt (FRC=1)
        if ($aktFRC === 1) {
            if ($pvUeberschuss >= $minLadeWatt) {
                $startZ++;
                $this->WriteAttributeInteger('LadeStartZaehler', $startZ);
                $this->WriteAttributeInteger('LadeStopZaehler', 0);
                $this->LogTemplate('debug', 'Start-Hysterese', "{$startZ}/{$startHys} ≥ {$minLadeWatt} W");
                if ($startZ >= $startHys) {
                    $this->LogTemplate('start', 'Ladefreigabe aktiviert', "{$pvUeberschuss} W verfügbar");
                    $desiredFRC = 2;
                    $this->WriteAttributeInteger('LadeStartZaehler', 0);
                }
            } else {
                // Nur zurücksetzen, wenn wir noch nicht laden
                $this->WriteAttributeInteger('LadeStartZaehler', 0);
            }
        }

        // 🔴 Stop-Hysterese nur wenn bereits geladen wird (FRC=2)
        if ($aktFRC === 2) {
            if ($pvUeberschuss <= $minStopWatt) {
                $stopZ++;
                $this->WriteAttributeInteger('LadeStopZaehler', $stopZ);
                $this->WriteAttributeInteger('LadeStartZaehler', 0);
                $this->LogTemplate('debug', 'Stop-Hysterese', "{$stopZ}/{$stopHys} ≤ {$minStopWatt} W");
                if ($stopZ >= $stopHys) {
                    $this->LogTemplate('stop', 'Ladefreigabe beendet', "{$pvUeberschuss} W verfügbar");
                    $desiredFRC = 1;
                    $this->WriteAttributeInteger('LadeStopZaehler', 0);
                }
            } else {
                $this->WriteAttributeInteger('LadeStopZaehler', 0);
            }
        }

        return $desiredFRC;
    }

    private function VerhindereStopHystereseKurzNachModuswechsel(int $cooldownSekunden): bool
    {
        $letzte = $this->ReadAttributeInteger('LetztePhasenUmschaltung');
        if ($letzte <= 0) {
            return false;
        }
        $vergangen = time() - $letzte;
        if ($vergangen < $cooldownSekunden) {
            $this->LogTemplate(
                'debug',
                'Stop-Hysterese unterdrückt',
                "{$vergangen}s seit Phasenwechsel < {$cooldownSekunden}s"
            );
            return true;
        }
        return false;
    }

    private function resetNoPowerCounter(): void
    {
        $this->WriteAttributeInteger('NoPowerCounter', 0);
    }

    private function applySafeIdleState(): void
    {
        $this->SetForceState(1);
        $this->SetPhaseMode(self::PHASE_MODE_1P);
        $this->SetChargingCurrent(6);
    }

    private function isChargeEndFallbackBlocked(): bool
    {
        $cooldownSeconds = time() - $this->ReadAttributeInteger('LetztePhasenUmschaltung');

        if ($cooldownSeconds < self::PHASE_SWITCH_COOLDOWN_S) {
            $this->resetNoPowerCounter();
            $this->LogTemplate(
                'debug',
                'Fallback gesperrt',
                "{$cooldownSeconds}s seit Phasenumschaltung < " . self::PHASE_SWITCH_COOLDOWN_S . 's'
            );
            return true;
        }

        $lastCurrentChange  = $this->ReadAttributeInteger('LastChargingCurrentChange');
        $sinceCurrentChange = time() - $lastCurrentChange;

        if ($sinceCurrentChange < self::CURRENT_CHANGE_COOLDOWN_S) {
            $this->resetNoPowerCounter();
            $this->LogTemplate(
                'debug',
                'Fallback gesperrt',
                "{$sinceCurrentChange}s seit Stromänderung < " . self::CURRENT_CHANGE_COOLDOWN_S . 's'
            );
            return true;
        }

        return false;
    }

    private function PruefeLadeendeAutomatisch()
    {
        $currentFRC = $this->GetValue('AccessStateV2');
        $modeKey    = $this->getCurrentModeKey();

        $this->LogTemplate(
            'debug',
            'PruefeLadeendeAutomatisch',
            "FRC={$currentFRC}, Modus={$modeKey}"
        );

        $socID       = $this->ReadPropertyInteger('CarSOCID');
        $socTargetID = $this->ReadPropertyInteger('CarTargetSOCID');
        $socAktuell  = ($socID > 0 && IPS_VariableExists($socID)) ? GetValue($socID) : null;
        $socZiel     = ($socTargetID > 0 && IPS_VariableExists($socTargetID)) ? GetValue($socTargetID) : null;

        $loadActive = in_array($modeKey, ['pvonly', 'pv2car', 'manuell'], true);

        $this->LogTemplate(
            'debug',
            'Ladefreigabe/Modus aktiv',
            $loadActive ? 'ja' : 'nein'
        );

        if ($loadActive && $socAktuell !== null && $socZiel !== null) {
            if ($socAktuell >= $socZiel) {
                $this->LogTemplate(
                    'stop',
                    'Ziel-SOC erreicht',
                    "{$socAktuell}% ≥ {$socZiel}%"
                );
                $this->applySafeIdleState();
                $this->ResetModiNachLadeende();
                $this->resetNoPowerCounter();

                return true;
            }

            $this->LogTemplate(
                'debug',
                'SOC-Ziel noch nicht erreicht',
                "{$socAktuell}% < {$socZiel}%"
            );
        }

        if ($loadActive && $currentFRC === 2) {
            if ($this->isChargeEndFallbackBlocked()) {
                return false;
            }

            $leistung  = intval(round($this->GetValue('Leistung')));
            $cntVorher = $this->ReadAttributeInteger('NoPowerCounter');

            $this->LogTemplate(
                'debug',
                'Fallback-Pfad',
                "Leistung={$leistung} W, NoPowerCounter={$cntVorher}"
            );

            if ($leistung < self::NO_POWER_THRESHOLD_W) {
                $cnt = $cntVorher + 1;
                $this->WriteAttributeInteger('NoPowerCounter', $cnt);
                $this->LogTemplate('debug', 'NoPowerCounter erhöht', (string) $cnt);

                if ($cnt >= self::NO_POWER_COUNTER_LIMIT) {
                    $this->LogTemplate(
                        'stop',
                        'Ladeende erkannt',
                        'Ladeleistung < ' . self::NO_POWER_THRESHOLD_W . " W nach {$cnt} Updates"
                    );

                    $this->applySafeIdleState();
                    $this->ResetModiNachLadeende();
                    $this->resetNoPowerCounter();

                    return true;
                }

            } else {
                $this->resetNoPowerCounter();
                $this->LogTemplate(
                    'debug',
                    'NoPowerCounter zurückgesetzt',
                    'Ladeleistung ≥ ' . self::NO_POWER_THRESHOLD_W . ' W'
                );
            }

        } else {
            $this->resetNoPowerCounter();
            $this->LogTemplate('debug', 'Fallback übersprungen', 'kein aktiver Lademodus oder keine Freigabe');
        }

        return false;
    }

    private function ResetModiNachLadeende()
    {
        $oldMode = $this->getCurrentModeKey();

        $this->WriteAttributeFloat('SmoothedSurplus', 0.0);
        $this->ApplyModeAfterDisconnectOrChargeEnd('🏁 Ladeende');

        if ($oldMode === 'manuell') {
            $this->SetPhaseMode(self::PHASE_MODE_1P);
            $this->SetChargingCurrent(6);

            $this->SetValueAndLogChange('PV_Ueberschuss_A', 0, 'PV-Überschuss (A)', 'A', 'debug');

            $this->LogTemplate('stop', 'Ladevorgang beendet');
            $this->LogTemplate('ok', 'Basiszustand gesetzt', '1-phasig / 6A / 0A');
        }
    }

    private function handleCarConnectionState(array $data): bool
    {
        $carConnected = $this->isCarConnected($data);
        $wasConnected = (bool) $this->ReadAttributeBoolean('LastCarConnected');

        if ($wasConnected && !$carConnected) {
            $this->ApplyModeAfterDisconnectOrChargeEnd('🚗 Fahrzeug abgesteckt');
        }

        $this->WriteAttributeBoolean('LastCarConnected', $carConnected);

        return $carConnected;
    }

    private function ApplyModeAfterDisconnectOrChargeEnd(string $reason): void
    {
        $modeAfterUnplug = (int) $this->ReadPropertyInteger('ModeAfterUnplug');
        $aktuellerModus  = (int) $this->GetValue('LademodusAuswahl');

        if ($modeAfterUnplug === self::MODE_KEEP_CURRENT) {
            $this->LogTemplate('info', 'Lademodus bleibt erhalten', $reason);
            return;
        }

        if (!in_array($modeAfterUnplug, [self::MODE_PVONLY, self::MODE_PV2CAR, self::MODE_MANUELL], true)) {
            $this->LogTemplate('warn', 'Ungültiger ModeAfterUnplug-Wert', "{$modeAfterUnplug} → Fallback auf Nur PV");
            $modeAfterUnplug = 0;
        }

        if ($aktuellerModus === $modeAfterUnplug) {
            $this->LogTemplate('debug', 'Lademodus bleibt unverändert', $reason);
            return;
        }

        $this->SetValue('LademodusAuswahl', $modeAfterUnplug);
        $this->LogTemplate(
            'info',
            'Lademodus gewechselt',
            sprintf(
                '%s | %s → %s',
                $reason,
                $this->getModeSelectionLabel($aktuellerModus),
                $this->getModeSelectionLabel($modeAfterUnplug)
            )
        );
    }

    private function handleNoCarConnected(): void
    {
        if ($this->GetValue('AccessStateV2') != 1) {
            $this->SetForceState(1);
            $this->LogTemplate('info', 'Kein Fahrzeug verbunden', 'Wallbox gesperrt');
        }

        $this->ResetWallboxVisualisierungKeinFahrzeug();
    }

    private function isCarConnected(array $data): bool
    {
        return (isset($data['car']) && intval($data['car']) > 1);
    }

    // =========================================================================
    // 8. PHASENLOGIK
    // =========================================================================

    private function determinePhases(array $data): int
    {
        $cnt = 0;
        foreach ([$data['nrg'][4] ?? 0, $data['nrg'][5] ?? 0, $data['nrg'][6] ?? 0] as $strom) {
            if (abs(floatval($strom)) > 1.5) {
                $cnt++;
            }
        }
        return max(1, $cnt);
    }

    private function PruefeUndSetzePhasenmodus($pvUeberschuss = null, $forceThreePhase = false)
    {
        $umschaltCooldown = 30;
        $letzteUmschaltung = @$this->ReadAttributeInteger('LetztePhasenUmschaltung');
        if (!is_int($letzteUmschaltung) || $letzteUmschaltung <= 0) {
            $letzteUmschaltung = 0;
        }
        $now = time();

        if ($forceThreePhase) {
            $aktModus = $this->GetValue('Phasenmodus');
            if ($aktModus != 2) {
                $this->SetValueAndLogChange('Phasenmodus', 2, 'Phasenumschaltung', '', 'ok');
                $ok = $this->SetPhaseMode(self::PHASE_MODE_3P);
                if ($ok) {
                    $this->LogTemplate(
                        'ok',
                        'Manueller Modus',
                        '3-phasig erzwungen'
                    );
                } else {
                    $this->LogTemplate(
                        'error',
                        'Manueller Modus',
                        'Umschalten auf 3-phasig fehlgeschlagen'
                    );
                }
                $this->WriteAttributeInteger('Phasen3Zaehler', 0);
                $this->WriteAttributeInteger('Phasen1Zaehler', 0);
                $this->WriteAttributeInteger('LetztePhasenUmschaltung', $now);
            }
            return;
        }

        if (($now - $letzteUmschaltung) < $umschaltCooldown) {
            $rest = $umschaltCooldown - ($now - $letzteUmschaltung);
            $this->LogTemplate('debug', 'Phasenumschaltung Cooldown', "noch {$rest} Sekunden");
            return;
        }

        $schwelle1 = $this->ReadPropertyInteger('Phasen1Schwelle');
        $schwelle3 = $this->ReadPropertyInteger('Phasen3Schwelle');
        $limit1    = $this->ReadPropertyInteger('Phasen1Limit');
        $limit3    = $this->ReadPropertyInteger('Phasen3Limit');
        $aktModus  = $this->GetValue('Phasenmodus');

        if ($aktModus == 1 && $pvUeberschuss >= $schwelle3) {
            $zaehler = $this->ReadAttributeInteger('Phasen3Zaehler') + 1;
            $this->WriteAttributeInteger('Phasen3Zaehler', $zaehler);
            $this->WriteAttributeInteger('Phasen1Zaehler', 0);

            $this->LogTemplate('debug', 'Phasen-Hysterese 1→3', "{$zaehler}/{$limit3} > {$schwelle3} W");
            if ($zaehler >= $limit3) {
                $this->SetValueAndLogChange('Phasenmodus', 2, 'Phasenumschaltung', '', 'ok');
                $ok = $this->SetPhaseMode(self::PHASE_MODE_3P);
                if (!$ok) {
                    $this->LogTemplate('error', 'Phasenumschaltung fehlgeschlagen', '1→3');
                }
                $this->WriteAttributeInteger('Phasen3Zaehler', 0);
                $this->WriteAttributeInteger('Phasen1Zaehler', 0);
                $this->WriteAttributeInteger('LetztePhasenUmschaltung', $now);
            }
            return;
        }

        if ($aktModus > 1 && $pvUeberschuss <= $schwelle1) {
            $zaehler = $this->ReadAttributeInteger('Phasen1Zaehler') + 1;
            $this->WriteAttributeInteger('Phasen1Zaehler', $zaehler);
            $this->WriteAttributeInteger('Phasen3Zaehler', 0);

            $this->LogTemplate('debug', 'Phasen-Hysterese 3→1', "{$zaehler}/{$limit1} < {$schwelle1} W");
            if ($zaehler >= $limit1) {
                $this->SetValueAndLogChange('Phasenmodus', 1, 'Phasenumschaltung', '', 'warn');
                $ok = $this->SetPhaseMode(self::PHASE_MODE_1P);
                if (!$ok) {
                    $this->LogTemplate('error', 'Phasenumschaltung fehlgeschlagen', '3→1');
                }
                $this->WriteAttributeInteger('Phasen3Zaehler', 0);
                $this->WriteAttributeInteger('Phasen1Zaehler', 0);
                $this->WriteAttributeInteger('LetztePhasenUmschaltung', $now);
            }
            return;
        }
    }

    private function SteuerungLadefreigabe($pvUeberschuss, $modus = 'pvonly', $ampere = 0, $anzPhasen = 1, $overrideFRC = null)
    {
        $minUeberschuss = $this->ReadPropertyInteger('MinLadeWatt');

        if ($overrideFRC !== null) {
            $sollFRC = $overrideFRC;
        } else {
            $sollFRC = ($modus === 'manuell' || ($modus === 'pvonly' && $pvUeberschuss >= $minUeberschuss))
                ? 2
                : 1;
        }

        $aktFRC = $this->GetValue('AccessStateV2');

        if ($aktFRC !== $sollFRC) {
            $this->LogTemplate('debug', 'SetForceState', "FRC={$sollFRC}, Modus={$modus}");

                if ($this->SetForceState($sollFRC)) {
                    if ($sollFRC === 1 || $sollFRC === 2) {
                        $this->WriteAttributeInteger('LastSentChargingCurrent', 0);
                    }
                }

            IPS_Sleep(1000);
        }

        if ($sollFRC === 2 && $ampere > 0) {
            $lastSentAmpere = $this->ReadAttributeInteger('LastSentChargingCurrent');

            if ($lastSentAmpere !== $ampere) {
                $this->LogTemplate('debug', 'SetChargingCurrent', "{$lastSentAmpere} A → {$ampere} A");

                if ($this->SetChargingCurrent($ampere)) {
                    $this->WriteAttributeInteger('LastSentChargingCurrent', $ampere);
                }
            }
        }
    }

    // =========================================================================
    // 9. ENERGIEDATEN / BERECHNUNG / FILTER
    // =========================================================================

    private function gatherEnergyData(): array
    {
        $pvID  = $this->ReadPropertyInteger('PVErzeugungID');
        $pv    = $pvID > 0 ? GetValueFloat($pvID) : 0;
        if ($this->ReadPropertyString('PVErzeugungEinheit') === 'kW') {
            $pv *= 1000;
        }

        $wb = round($this->GetValue('Leistung'));

        $hvID = $this->ReadPropertyInteger('HausverbrauchID');
        $hv   = $hvID > 0 ? GetValueFloat($hvID) : 0;
        if ($this->ReadPropertyString('HausverbrauchEinheit') === 'kW') {
            $hv *= 1000;
        }
        if ($this->ReadPropertyBoolean('InvertHausverbrauch')) {
            $hv = -$hv;
        }

        $batID = $this->ReadPropertyInteger('BatterieladungID');
        $bat   = $batID > 0 ? GetValueFloat($batID) : 0;
        if ($this->ReadPropertyString('BatterieladungEinheit') === 'kW') {
            $bat *= 1000;
        }
        if ($this->ReadPropertyBoolean('InvertBatterieladung')) {
            $bat = -$bat;
        }

        return [
            'pv'      => round($pv),
            'wallbox' => $wb,
            'haus'    => round($hv),
            'batt'    => round($bat),
        ];
    }

    private function applyFilters(array $data): array 
    {
        $raw = max(0, $data['haus'] - $data['wallbox']);

        $buf = json_decode($this->ReadAttributeString('HausverbrauchAbzWallboxBuffer'), true) ?: [];
        $last = floatval($this->ReadAttributeFloat('HausverbrauchAbzWallboxLast'));

        $threshold = 1.5 * $this->ReadPropertyInteger('MaxAmpere') * 230;

        /*
        * Nur starke Anstiege als Spike blockieren.
        * Starke Abfälle sind erlaubt, damit z.B. Wärmepumpe/Boiler
        * nach dem Abschalten nicht künstlich im Filter hängen bleiben.
        */
        if ($last > 0 && ($raw - $last) > $threshold) {
            $filtered = $last;
            $this->LogTemplate('warn', 'Spike erkannt', "{$raw} W → {$last} W beibehalten");
        } else {
            $buf[] = $raw;

            if (count($buf) > 3) {
                array_shift($buf);
            }

            $mean = array_sum($buf) / count($buf);

            $filtered = $mean;

            $this->WriteAttributeString('HausverbrauchAbzWallboxBuffer', json_encode($buf));
            $this->WriteAttributeFloat('HausverbrauchAbzWallboxLast', $mean);
        }

        $data['hausFiltered'] = round($filtered);
        return $data;
    }

    private function calculateSurplus(array $data, int $anzPhasen, bool $log = true): array
    {
        $batLoad = max(0, $data['batt']);

        $cons = $data['hausFiltered'] + $batLoad;

        $socID  = $this->ReadPropertyInteger('HausakkuSOCID');
        $voll   = $this->ReadPropertyInteger('HausakkuSOCVollSchwelle');
        $soc    = ($socID > 0 && IPS_VariableExists($socID)) ? GetValue($socID) : null;
        if ($soc !== null && $soc >= $voll) {
            $cons = $data['hausFiltered'];
        }

        $rawSurplus = max(0, $data['pv'] - $cons);
        if (abs($rawSurplus) < 1) {
            $rawSurplus = 0;
        }

        $alpha      = $this->ReadPropertyFloat('SmoothingAlpha');
        $lastSmooth = $this->ReadAttributeFloat('SmoothedSurplus');
        if ($lastSmooth <= 0) {
            $smoothed = $rawSurplus;
        } else {
            $smoothed = $alpha * $rawSurplus + (1 - $alpha) * $lastSmooth;
        }
        $this->WriteAttributeFloat('SmoothedSurplus', $smoothed);
        $useSurplus = $smoothed;

        $cutoff     = 250;
        $desiredAmp = 0;
        if ($useSurplus >= $cutoff) {
            $desiredAmp = (int)ceil($useSurplus / (230 * $anzPhasen));
            $desiredAmp = max(
                $this->ReadPropertyInteger('MinAmpere'),
                min($this->ReadPropertyInteger('MaxAmpere'), $desiredAmp)
            );
        } elseif ($log) {
            $this->LogTemplate('debug', 'PV-Überschuss unter Cutoff', "< {$cutoff} W → 0 W");
        }

        $lastAmp  = $this->ReadAttributeInteger('LastChargingCurrent');
        $maxDelta = $this->ReadPropertyInteger('MaxRampDeltaAmp');
        $delta    = max(-$maxDelta, min($maxDelta, $desiredAmp - $lastAmp));
        $amp      = $lastAmp + $delta;
        $this->WriteAttributeInteger('LastChargingCurrent', $amp);

        if ($log) {
        $this->LogTemplate(
            'debug',
            'Berechnung geglättet',
            sprintf(
                'PV=%d W, Haus=%d W, Batt=%d W, Überschuss≈%.0f W, Strom=%d A, Δ%+d A, Phasen=%d',
                $data['pv'],
                $data['hausFiltered'],
                $batLoad,
                $useSurplus,
                $amp,
                $delta,
                $anzPhasen
            )
        );
            $this->SetValueAndLogChange('PV_Ueberschuss',   round($useSurplus),   'PV-Überschuss',     'W', 'debug');
            $this->SetValueAndLogChange('PV_Ueberschuss_A', $amp,                 'PV-Überschuss (A)', 'A', 'debug');
        } else {
            $this->SetValue('PV_Ueberschuss',   round($useSurplus));
            $this->SetValue('PV_Ueberschuss_A', $amp);
        }

        return [
            'ueberschuss_w' => round($useSurplus),
            'ueberschuss_a' => $amp,
        ];
    }

    private function updateHousePower(array $energyRaw): void
    {
        $this->SetValueAndLogChange('Hausverbrauch_W', $energyRaw['haus'], 'Hausverbrauch (W)');
        $this->SetValueAndLogChange(
            'Hausverbrauch_abz_Wallbox',
            max(0, $energyRaw['haus'] - $energyRaw['wallbox']),
            'Hausverbrauch abzgl. Wallbox (W)'
        );
    }

    private function UpdateHausverbrauchEvent()
    {
        $eventIdent = "UpdateHausverbrauchW";
        $eventID = @$this->GetIDForIdent($eventIdent);
        $hvID = $this->ReadPropertyInteger('HausverbrauchID');
        $myVarID = $this->GetIDForIdent('Hausverbrauch_W');
        $einheit = $this->ReadPropertyString('HausverbrauchEinheit');

        if ($eventID && ($hvID <= 0 || @IPS_GetEvent($eventID)['TriggerVariableID'] != $hvID)) {
            IPS_DeleteEvent($eventID);
            $eventID = 0;
        }
        if ($hvID > 0 && IPS_VariableExists($hvID)) {
            if (!$eventID) {
                $eventID = IPS_CreateEvent(0);
                IPS_SetIdent($eventID, $eventIdent);
                IPS_SetParent($eventID, $this->InstanceID);
                IPS_SetEventTrigger($eventID, 0, $hvID);
                IPS_SetEventActive($eventID, true);
                IPS_SetName($eventID, "Aktualisiere Hausverbrauch_W");
            }
            $script = <<<'EOD'
    $wert = GetValue($_IPS['VARIABLE']);
    $einheit = IPS_GetProperty($_IPS['INSTANCE'], 'HausverbrauchEinheit');
    if ($einheit == 'kW') $wert *= 1000;
    SetValue($_IPS['TARGET'], round($wert));
    EOD;
            $script = str_replace(['$_IPS[\'INSTANCE\']', '$_IPS[\'TARGET\']'], [$this->InstanceID, $myVarID], $script);

            IPS_SetEventScript($eventID, $script);
        }

        $eventIdent2 = "UpdateHausverbrauchAbzWallbox";
        $eventID2 = @$this->GetIDForIdent($eventIdent2);
        $myVarID2 = $this->GetIDForIdent('Hausverbrauch_abz_Wallbox');
        $srcVarID = $this->GetIDForIdent('Hausverbrauch_W');

        if ($eventID2 && (@IPS_GetEvent($eventID2)['TriggerVariableID'] != $srcVarID)) {
            IPS_DeleteEvent($eventID2);
            $eventID2 = 0;
        }
        if ($srcVarID > 0 && IPS_VariableExists($srcVarID)) {
            if (!$eventID2) {
                $eventID2 = IPS_CreateEvent(0);
                IPS_SetIdent($eventID2, $eventIdent2);
                IPS_SetParent($eventID2, $this->InstanceID);
                IPS_SetEventTrigger($eventID2, 0, $srcVarID);
                IPS_SetEventActive($eventID2, true);
                IPS_SetName($eventID2, "Aktualisiere Hausverbrauch_abz_Wallbox");
            }
            $script2 = <<<'EOD'
    $hv = GetValue($_IPS['VARIABLE']);
    $wb = GetValue(IPS_GetObjectIDByIdent('Leistung', $_IPS['INSTANCE']));
    SetValue(IPS_GetObjectIDByIdent('Hausverbrauch_abz_Wallbox', $_IPS['INSTANCE']), round($hv - $wb));
    EOD;
            $script2 = str_replace(['$_IPS[\'INSTANCE\']'], [$this->InstanceID], $script2);
            IPS_SetEventScript($eventID2, $script2);
        }
    }

    private function updateSurplusDisplayWithoutCar(array $energyRaw): void
    {
        $filtered = $this->applyFilters($energyRaw);
        $surplus  = $this->calculateSurplus($filtered, 1, false);
        $this->SetValue('PV_Ueberschuss',   $surplus['ueberschuss_w']);
        $this->SetValue('PV_Ueberschuss_A', $surplus['ueberschuss_a']);
    }

    // =========================================================================
    // 10. WALLBOX / API / CHARGER-ZUGRIFF
    // =========================================================================

    private function getStatusFromCharger()
    {
        $ip = trim($this->ReadPropertyString('WallboxIP'));

        if ($ip == "" || $ip == "0.0.0.0") {
            $this->LogTemplate('error', 'Keine IP-Adresse für Wallbox konfiguriert');
            return false;
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $this->LogTemplate('error', 'Ungültige IP-Adresse konfiguriert', $ip);
            $this->SetStatus(201);
            return false;
        }
        if (!$this->ping($ip, 80, 1)) {
            $this->LogTemplate('error', 'Wallbox nicht erreichbar', "{$ip}:80");
            return false;
        }

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
            $this->LogTemplate('error', 'HTTP-Fehler', $e->getMessage());
            return false;
        }

        if ($json === false || strlen($json) < 2) {
            $this->LogTemplate('error', 'Keine Antwort von Wallbox', $url);
            return false;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            $this->LogTemplate('error', 'Ungültiges JSON von Wallbox', $url);
            return false;
        }

        return $data;
    }

    private function simpleCurlGet($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);

        $result = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'result'   => $result,
            'httpcode' => $httpcode,
            'error'    => $error
        ];
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

    public function SetChargingCurrent(int $ampere)
    {
        $requestedAmpere = $ampere;
        $ampere = $this->clampAmpere($ampere);

        if ($requestedAmpere !== $ampere) {
            $this->LogTemplate(
                'warn',
                'Ladestrom begrenzt',
                "{$requestedAmpere} A → {$ampere} A"
            );
        }

        $ip = $this->ReadPropertyString('WallboxIP');
        $url = "http://$ip/api/set?amp=" . intval($ampere);

        $this->LogTemplate('debug', 'SetChargingCurrent', "{$ampere} A → {$url}");

        $response = $this->simpleCurlGet($url);

        if ($response['result'] === false || $response['httpcode'] != 200) {
            $this->LogTemplate(
                'error',
                'SetChargingCurrent fehlgeschlagen',
                "{$ampere} A, HTTP={$response['httpcode']}, cURL={$response['error']}"
            );
            return false;
        }

        $this->WriteAttributeInteger('LastChargingCurrent', $ampere);
        $this->WriteAttributeInteger('LastChargingCurrentChange', time());

        $this->LogTemplate('debug', 'Ladestrom gesetzt', "{$ampere} A, HTTP={$response['httpcode']}");
        return true;
    }

    public function SetPhaseMode(int $mode)
    {
        if ($mode < 0 || $mode > 2) {
            $this->LogTemplate('warn', 'SetPhaseMode ungültig', "{$mode} (0=Auto, 1=1-phasig, 2=3-phasig)");
            return false;
        }

        $ip = $this->ReadPropertyString('WallboxIP');
        $url = "http://$ip/api/set?psm=" . intval($mode);

        $modes = [0 => "Auto", 1 => "1-phasig", 2 => "3-phasig"];
        $modeText = $modes[$mode] ?? $mode;

        $this->LogTemplate('debug', 'SetPhaseMode', "{$modeText} ({$mode}) → {$url}");

        $response = $this->simpleCurlGet($url);

        if ($response['result'] === false || $response['httpcode'] != 200) {
            $this->LogTemplate(
                'error',
                'SetPhaseMode fehlgeschlagen',
                "{$modeText} ({$mode}), HTTP={$response['httpcode']}, cURL={$response['error']}"
            );
            return false;
        } else {
            $this->LogTemplate('debug', 'SetPhaseMode gesetzt', "{$modeText} ({$mode}), HTTP={$response['httpcode']}");
            return true;
        }
    }

    public function SetForceState(int $state)
    {
        if ($state < 0 || $state > 2) {
            $this->LogTemplate('warn', 'SetForceState ungültig', "{$state} (0=Neutral, 1=OFF, 2=ON)");
            return false;
        }

        $ip       = $this->ReadPropertyString('WallboxIP');
        $url      = "http://{$ip}/api/set?frc=" . intval($state);
        $modes    = [
            0 => "Neutral (Wallbox entscheidet)",
            1 => "Nicht Laden (gesperrt)",
            2 => "Laden (erzwungen)"
        ];
        $modeText = $modes[$state] ?? $state;

        $this->LogTemplate('debug', 'SetForceState', "HTTP GET → {$url}");

        $response = $this->simpleCurlGet($url);

        if ($response['result'] === false || $response['httpcode'] != 200) {
            $this->LogTemplate(
                'error',
                'SetForceState fehlgeschlagen',
                "{$modeText} ({$state}), HTTP={$response['httpcode']}, cURL={$response['error']}"
            );
            return false;
        }

        $this->LogTemplate('debug', 'SetForceState gesetzt', "{$modeText} ({$state}), HTTP={$response['httpcode']}");
        $varID = $this->GetIDForIdent('AccessStateV2');
        if ($varID) {
            SetValue($varID, $state);
        }

        return true;
    }

    public function SetChargingEnabled(bool $enabled)
    {
        $ip = $this->ReadPropertyString('WallboxIP');
        $apiKey = $this->ReadPropertyString('WallboxAPIKey');
        $alwValue = $enabled ? 1 : 0;
        $statusText = $enabled ? "Laden erlaubt" : "Laden gesperrt";

        if ($apiKey != '') {
            $url = "http://$ip/api/set?dwo=0&alw=$alwValue&key=" . urlencode($apiKey);
            $this->LogTemplate('debug', 'SetChargingEnabled', "{$statusText} ({$alwValue}) mit API-Key → {$url}");
        } else {
            $url = "http://$ip/api/set?dwo=0&alw=$alwValue";
            $this->LogTemplate('debug', 'SetChargingEnabled', "{$statusText} ({$alwValue}) → {$url}");
        }

        $response = $this->simpleCurlGet($url);

        if ($response['result'] === false || $response['httpcode'] != 200) {
            $this->LogTemplate(
                'error',
                'SetChargingEnabled fehlgeschlagen',
                "ALW={$alwValue}, HTTP={$response['httpcode']}, cURL={$response['error']}"
            );
            return false;
        } else {
            $this->LogTemplate('debug', 'SetChargingEnabled gesetzt', "{$statusText} ({$alwValue}), HTTP={$response['httpcode']}");
            return true;
        }
    }

    private function extractChargerVariables(array $data): array
    {
        return [
            'psm'      => intval($data['psm']   ?? 0),
            'car'      => intval($data['car']   ?? 0),
            'leistung' => $data['nrg'][11]      ?? 0.0,
            'ampereWB' => $data['amp']          ?? 0,
            'energie'  => $data['wh']           ?? 0,
            'freigabe' => (bool)($data['alw']   ?? false),
            'kabel'    => $data['cbl']          ?? 0,
            'err'      => $data['err']          ?? 0,
            'frcRaw'   => $data['frc']          ?? null,
            'stateRaw' => $data['accessStateV2']?? null,
        ];
    }

    private function syncChargerVariables(array $vars, int $phasen): void
    {
        $this->SetValueAndLogChange(
            'PhasenmodusEinstellung',
            $vars['psm'],
            'Wallbox-Phasen Soll',
            '',
            'debug'
        );

        $this->SetValueAndLogChange(
            'Phasenmodus',
            $phasen,
            'Genutzte Phasen',
            '',
            'debug'
        );

        $accessStateV2 = ($vars['frcRaw'] === 2 || $vars['stateRaw'] === 2) ? 2 : 1;
        $this->SetValueAndLogChange('Status',                $vars['car'],               'Status');
        $this->SetValueAndLogChange('AccessStateV2',         $accessStateV2,             'Wallbox Modus');
        $this->SetValueAndLogChange('Leistung',              $vars['leistung'],          'Aktuelle Ladeleistung (W)',  'W');
        $this->SetValueAndLogChange('Ampere',                $vars['ampereWB'],          'Max. Ladestrom',             'A');
        $this->SetValueAndLogChange('Energie',               $vars['energie'],           'Geladene Energie',           'Wh');
        $this->SetValueAndLogChange('Freigabe',              $vars['freigabe'],          'Ladefreigabe');
        $this->SetValueAndLogChange('Kabelstrom',            $vars['kabel'],             'Kabeltyp');
        $this->SetValueAndLogChange('Fehlercode',            $vars['err'],               'Fehlercode', '', 'warn');
    }

    private function handleChargerUnavailable(): void
    {
        $this->ResetWallboxVisualisierungKeinFahrzeug();
        $this->LogTemplate('debug', 'Wallbox nicht erreichbar – Visualisierung zurückgesetzt');
        $this->UpdateStatusAnzeige();
    }

    // =========================================================================
    // 11. WALLBOX-GRENZEN / AMPERE-VALIDIERUNG
    // =========================================================================

    private function validateAmpereConfiguration(): void
    {
        $configuredMaxAmpere = (int) $this->ReadPropertyInteger('MaxAmpere');
        $wallboxMaxAmpere = $this->getEffectiveWallboxMaxAmpere();

        if ($configuredMaxAmpere > $wallboxMaxAmpere) {
            $this->LogTemplate(
                'warn',
                sprintf(
                    'MaxAmpere (%d A) überschreitet das erkannte Wallbox-Limit (%d A). Es wird intern auf %d A begrenzt.',
                    $configuredMaxAmpere,
                    $wallboxMaxAmpere,
                    $wallboxMaxAmpere
                )
            );
        }
    }

    private function getEffectiveWallboxMaxAmpere(): int
    {
        $status = $this->getStatusFromCharger();
        if (!is_array($status)) {
            return 16;
        }

        $hardwareMaxAmpere = $this->getHardwareMaxAmpereFromStatus($status);
        $configuredWallboxMaxAmpere = $this->getConfiguredWallboxMaxAmpereFromStatus($status);

        return min($hardwareMaxAmpere, $configuredWallboxMaxAmpere);
    }

    private function getHardwareMaxAmpereFromStatus(array $status): int
    {
        $variant = isset($status['var']) ? (int) $status['var'] : 0;

        return ($variant === 22) ? 32 : 16;
    }

    private function getConfiguredWallboxMaxAmpereFromStatus(array $status): int
    {
        if (isset($status['ama'])) {
            $ama = (int) $status['ama'];
            if ($ama >= 6 && $ama <= 32) {
                return $ama;
            }
        }

        return 32;
    }

    private function clampAmpere(int $ampere): int
    {
        $minAmpere = max(6, (int) $this->ReadPropertyInteger('MinAmpere'));
        $maxAmpere = max($minAmpere, (int) $this->ReadPropertyInteger('MaxAmpere'));
        $effectiveMaxAmpere = min($maxAmpere, $this->getEffectiveWallboxMaxAmpere());

        if ($minAmpere > $effectiveMaxAmpere) {
            $this->LogDebug(
                'clampAmpere',
                sprintf(
                    'MinAmpere (%dA) liegt über effectiveMaxAmpere (%dA) und wird begrenzt.',
                    $minAmpere,
                    $effectiveMaxAmpere
                )
            );
        }
        
        $minAmpere = min($minAmpere, $effectiveMaxAmpere);

        return max($minAmpere, min($ampere, $effectiveMaxAmpere));
    }

    // =========================================================================
    // 12. TIMER / ZEITLOGIK / ZIELZEIT
    // =========================================================================

    private function GetInitialCheckInterval() {
        $val = intval($this->ReadPropertyInteger('InitialCheckInterval'));
        if ($val < 5 || $val > 60) $val = 5;
        return $val;
    }

    private function SetTimerNachModusUndAuto()
    {
        if (!@is_int($this->ReadAttributeInteger('MarketPricesTimerInterval'))) {
            $this->WriteAttributeInteger('MarketPricesTimerInterval', 0);
        }
        if (!@is_bool($this->ReadAttributeBoolean('MarketPricesActive'))) {
            $this->WriteAttributeBoolean('MarketPricesActive', false);
        }

        $car        = @$this->GetValue('Status');
        $lastStatus = $this->ReadAttributeInteger('LastTimerStatus');
        if ($car !== $lastStatus) {
            $this->LogTemplate('debug', 'SetTimerNachModusUndAuto', "Status={$car}");
            $this->WriteAttributeInteger('LastTimerStatus', $car);
        }

        $this->SetTimerInterval('PVWM_UpdateStatus', 0);
        $this->SetTimerInterval('PVWM_InitialCheck', 0);

        if (!$this->ReadPropertyBoolean('ModulAktiv')) {
            $this->SetTimerInterval('PVWM_UpdateMarketPrices', 0);
            return;
        }

        $mainInterval    = intval($this->ReadPropertyInteger('RefreshInterval'));
        $initialInterval = $this->GetInitialCheckInterval();

        if ($car === false || $car <= 1) {
            if ($initialInterval > 0) {
                $this->SetTimerInterval('PVWM_InitialCheck', $initialInterval * 1000);
            }
        } else {
            $this->SetTimerInterval('PVWM_UpdateStatus', $mainInterval * 1000);
        }
    }

    private function SetMarketPriceTimerZurVollenStunde()
    {
        if (!$this->ReadPropertyBoolean('UseMarketPrices')) {
            $this->SetTimerInterval('PVWM_UpdateMarketPrices', 0);
            $this->WriteAttributeBoolean('MarketPricesActive', false);
            return;
        }

        $this->AktualisiereMarktpreise();

        $now = time();
        $sekBisNaechsteStunde = (60 - date('i', $now)) * 60 - date('s', $now);
        if ($sekBisNaechsteStunde <= 0) $sekBisNaechsteStunde = 3600;

        $this->SetTimerInterval('PVWM_UpdateMarketPrices', $sekBisNaechsteStunde * 1000);
        $this->WriteAttributeBoolean('MarketPricesActive', true);
    }

    private function BerechneVerbleibendeLadezeit(): string
    {
        $socID       = $this->ReadPropertyInteger('CarSOCID');
        $socTargetID = $this->ReadPropertyInteger('CarTargetSOCID');
        if ($socID <= 0 || $socTargetID <= 0 || !IPS_VariableExists($socID) || !IPS_VariableExists($socTargetID)) {
            return 'n/a';
        }

        $socAktuell = GetValue($socID);
        $socZiel    = GetValue($socTargetID);
        $deltaSoc   = $socZiel - $socAktuell;
        if ($deltaSoc <= 0) {
            return '00h 00min';
        }

        $kapazitaet = $this->ReadPropertyFloat('CarBatteryCapacity');
        if ($kapazitaet <= 0) {
            return 'n/a';
        }

        $leistungW = $this->GetValue('Leistung');
        if ($leistungW <= 0) {
            return '00h 00min';
        }

        $bedarfKwh = ($deltaSoc / 100) * $kapazitaet;
        $hours     = $bedarfKwh / ($leistungW / 1000);

        $h = floor($hours);
        $m = floor(($hours - $h) * 60);

        return sprintf('%02dh %02dmin', $h, $m);
    }

    private function BerechneFertigZeit(string $restTime): string
    {
        if ($restTime === 'n/a' || $restTime === '00h 00min') {
            return 'n/a';
        }
        list($h, $m) = sscanf($restTime, '%dh %dmin');
        $finish = new \DateTime();
        $finish->add(new \DateInterval(sprintf('PT%dH%dM', $h, $m)));
        return $finish->format('H:i');
    }

    private function HandleLadezeitLogging(): void
    {
        $restTime = $this->BerechneVerbleibendeLadezeit();

        $finishTime = '00:00';
        if ($restTime !== 'n/a') {
            $finishTime = $this->BerechneFertigZeit($restTime);
            if ($finishTime === 'n/a') {
                $finishTime = '00:00';
            }
        }

        $chargeString = "{$restTime} –> {$finishTime} Uhr";

        $this->SetValueAndLogChange('ChargeTime', $chargeString, '⏳ Ladezeit/Fertigzeit:');

        if ($restTime === 'n/a') {
            $this->LogTemplate('warn', 'Verbleibende Ladezeit nicht berechenbar (fehlende Daten)');
        }
        elseif ($restTime !== '00h 00min') {
            $this->LogTemplate(
                'info',
                "⏳ Geschätzte Ladezeit: {$restTime} / ⏰ Voraussichtliche Fertigzeit: {$finishTime} Uhr"
            );
        }
    }

    // =========================================================================
    // 13. STROMPREISE / FORECAST
    // =========================================================================

    private function AktualisiereMarktpreise()
    {
        $this->LogTemplate('debug', 'AktualisiereMarktpreise', 'aufgerufen');

        if (!$this->ReadPropertyBoolean('UseMarketPrices')) {
            $this->LogTemplate('debug', 'Börsenpreis-Update übersprungen', 'deaktiviert');
            return;
        }

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
            $this->LogTemplate('error', 'Keine gültige API-URL', 'Strompreis-Provider');
            return;
        }

        $response = $this->simpleCurlGet($apiUrl);
        if ($response['result'] === false || $response['httpcode'] != 200) {
            $this->LogTemplate(
                'error',
                'Abruf der Börsenpreise fehlgeschlagen',
                "HTTP={$response['httpcode']}, cURL={$response['error']}, URL={$apiUrl}"
            );
            return;
        }
        $json = $response['result'];
        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['data'])) {
            $this->LogTemplate('error', 'Fehlerhafte API-Antwort', "Feld 'data' fehlt");
            return;
        }

        $preise = array_map(function($item) {
            return [
                'timestamp' => intval($item['start_timestamp'] / 1000),
                'price'     => floatval($item['marketprice'] / 10.0)
            ];
        }, $data['data']);

        $grundpreis   = $this->ReadPropertyFloat('MarketPriceBasePrice');
        $aufschlagPct = $this->ReadPropertyFloat('MarketPriceSurcharge') / 100;
        $steuersatz   = $this->ReadPropertyFloat('MarketPriceTaxRate') / 100;

        $aktuellerNetto = $preise[0]['price'];

        $preisVorAufschlag = $aktuellerNetto + $grundpreis;

        $preisNachAufschlag = $preisVorAufschlag * (1 + $aufschlagPct);

        $preisBrutto = round($preisNachAufschlag * (1 + $steuersatz), 3);

        $this->SetValueAndLogChange('CurrentSpotPrice', $preisBrutto);

        foreach ($preise as &$p) {
            $nettoVor = $p['price'] + $grundpreis;
            $nettoMitAufschlag = $nettoVor * (1 + $aufschlagPct);
            $p['price'] = round($nettoMitAufschlag * (1 + $steuersatz), 3);
        }
        unset($p);

        $this->SetValueAndLogChange('MarketPrices', json_encode($preise));
        $this->LogTemplate('debug', 'MarketPrices gesetzt', substr(json_encode($preise), 0, 100) . '...');

        $this->SetValue('MarketPricesPreview', $this->FormatMarketPricesPreviewHTML(24));

        $this->LogTemplate(
            'ok',
            'Börsenpreise aktualisiert',
            "{$preisBrutto} ct/kWh aktuell, " . count($preise) . ' Preispunkte gespeichert'
        );
    }

    private function FormatMarketPricesPreviewHTML($max = 24)
    {
        $preiseRaw = @$this->GetValue('MarketPrices');
        $preise    = json_decode($preiseRaw, true);
        if (!is_array($preise) || count($preise) === 0) {
            return '<span style="color:#888;">Keine Preisdaten verfügbar.</span>';
        }

        $hourStart = strtotime(date('Y-m-d H:00:00'));
        $future = array_filter($preise, static function($p) use ($hourStart) {
            return ($p['timestamp'] ?? 0) >= $hourStart;
        });
        $slice = array_slice(array_values($future), 0, $max);

        if (count($slice) === 0) {
            return '<span style="color:#888;">Keine zukünftigen Preisdaten verfügbar.</span>';
        }

        $allePreise = array_column($slice, 'price');
        $min        = min($allePreise);
        $maxPrice   = max($allePreise);

        $html = <<<EOT
    <style>
    .pvwm-row {
        display: flex; align-items: center;
        margin: 7px 0 0 0;
    }
    .pvwm-hour {
        width: 28px; min-width: 28px;
        font-weight: 600;
        font-size: 1.07em;
        text-align: right;
        padding-right: 8px;
    }
    .pvwm-bar-wrap {
        flex: 1;
        display: flex; align-items: center;
    }
    .pvwm-bar {
        display: flex; align-items: center; justify-content: left;
        height: 22px;
        border-radius: 7px;
        font-weight: 700;
        font-size: 1.10em;
        box-shadow: 0 1px 2.5px #0002;
        padding-left: 18px;
        letter-spacing: 0.02em;
        min-width: 62px;
        background: #eee;
        transition: width 0.35s;
    }
    </style>
    <div style="font-family:Segoe UI,Arial,sans-serif;font-size:14px;max-width:540px;">
    <b style="font-size:1.07em;">Börsenpreis-Vorschau:</b>
    EOT;

        foreach ($slice as $i => $dat) {
            $time = date('H', $dat['timestamp']);
            $price = number_format($dat['price'], 3, ',', '.');
            $percent = ($dat['price'] - $min) / max(0.001, ($maxPrice - $min));

            if ($percent <= 0.5) {
                // #38b000 (grün) bis #ffcc00 (gelb)
                $t = $percent / 0.5;
                $r = intval(56 + (255-56) * $t);
                $g = intval(176 + (204-176) * $t);
                $b = 0;
            } else {
                // #ffcc00 (gelb) bis #ff6a00 (orange)
                $t = ($percent-0.5)/0.5;
                $r = 255;
                $g = intval(204 - (204-106) * $t);
                $b = 0;
            }
            $color = sprintf("#%02x%02x%02x", $r, $g, $b);

            // Balkenbreite von 38% bis 100%
            $barWidth = 38 + intval($percent * 62);

            $html .= "<div class='pvwm-row'>
                <span class='pvwm-hour'>$time</span>
                <span class='pvwm-bar-wrap'>
                    <span class='pvwm-bar' style='background:$color; width:{$barWidth}%;'>{$price} ct</span>
                </span>
            </div>";
        }
        $html .= '</div>';
        return $html;
    }

    // =========================================================================
    // 14. STATUSANZEIGE / HTML / WEBFRONT-DARSTELLUNG
    // =========================================================================

    private function renderStatusHtml(array $d): string
    {
        $html  = '<div style="font-size:15px; line-height:1.75em;">';

        if (!$d['moduleActive']) {
            $html .= '<div style="color:red; font-weight:bold; margin-bottom:6px;">● Modul deaktiviert</div>';
        }

        if ($d['inInitial']) {
            $html .= "🔄 <b>Initial-Check:</b> Aktiv ({$d['initialInt']} s)<br>";
        }

        if ($d['neutralActive']) {
            $t = date('H:i:s', $d['neutralUntil']);
            $html .= "⏸️ <b>Neutralmodus:</b> aktiv bis {$t}<br>";
        }

        $html .= "☀️ <b>Lademodus:</b> {$d['modusText']}<br>";

        $html .= '<div style="height:6px;"></div>';

        $html .= "🚗 <b>Fahrzeug:</b> {$d['statusTxt']}<br>";
        $html .= "🔌 <b>Wallbox:</b> {$d['frcTxt']}<br>";

        $html .= '<div style="height:6px;"></div>';

        $html .= "⚡ <b>Wallbox-Phasen:</b> {$d['psmSollTxt']}<br>";
        $html .= "🚘 <b>Fahrzeug-Phasen:</b> {$d['psmIstTxt']}<br>";

        $html .= '<div style="height:6px;"></div>';

        $html .= "🔋 <b>SOC:</b> {$d['socAktuell']} / Ziel: {$d['socZiel']}";

        $html .= '</div>';

        return $html;
    }

    private function UpdateStatusAnzeige(): void
    {
        $data = $this->collectStatusData();
        $html = $this->renderStatusHtml($data);

        $last = $this->ReadAttributeString('LastStatusInfoHTML');
        if ($last !== $html) {
            SetValue($this->GetIDForIdent('StatusInfo'), $html);
            $this->WriteAttributeString('LastStatusInfoHTML', $html);
            $this->LogTemplate('debug', 'Status-Info HTMLBox', 'aktualisiert');
        }
        else {
            $this->LogTemplate('debug', 'Status-Info HTMLBox', 'unverändert');
        }
    }

    private function ResetWallboxVisualisierungKeinFahrzeug()
    {
        $this->WriteAttributeFloat('SmoothedSurplus', 0.0);

        $this->SetValue('Leistung', 0);
        $this->SetValue('PV_Ueberschuss', 0);
        $this->SetValue('PV_Ueberschuss_A', 0);
        
        $hvID = $this->ReadPropertyInteger('HausverbrauchID');
        $hvEinheit = $this->ReadPropertyString('HausverbrauchEinheit');
        $invertHV = $this->ReadPropertyBoolean('InvertHausverbrauch');
        $hausverbrauch = ($hvID > 0) ? @GetValueFloat($hvID) : 0;
        if ($hvEinheit == "kW") $hausverbrauch *= 1000;
        if ($invertHV) $hausverbrauch *= -1;
        $hausverbrauch = round($hausverbrauch);

        $this->SetValue('Freigabe', false);
        $this->SetValue('AccessStateV2', 1);
        $this->SetValue('Status', 1);
        $this->SetTimerNachModusUndAuto();
    }

    private function SetValueAndLogChange($ident, $newValue, $caption = '', $unit = '', $level = 'info')
    {
        $varID = @$this->GetIDForIdent($ident);
        if ($varID === false || $varID === 0) {
            $this->LogTemplate('warn', 'Variable nicht gefunden', "Ident={$ident}");
            return;
        }

        try {
            $oldValue = GetValue($varID);
        } catch (Exception $e) {
            $oldValue = null;
        }

        if (is_string($oldValue) || is_string($newValue)) {
            if ((string)$oldValue === (string)$newValue) return;
        } else {
            if (round(floatval($oldValue), 2) == round(floatval($newValue), 2)) return;
        }

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
                        3 => 'Fahrzeug verbunden / Bereit zum Laden',
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
    // 15. MODUS-MAPPING / LABELS / STATUS-HILFEN
    // =========================================================================

    private function getCurrentModeSelection(): int
    {
        return intval($this->GetValue('LademodusAuswahl'));
    }

    private function mapSelectionToMode(int $selection): string
    {
        switch ($selection) {
            case self::MODE_PV2CAR:
                return 'pv2car';
            case self::MODE_MANUELL:
                return 'manuell';
            case self::MODE_PVONLY:
            default:
                return 'pvonly';
        }
    }

    private function mapModeToSelection(string $mode): int
    {
        switch ($mode) {
            case 'pv2car':
                return self::MODE_PV2CAR;
            case 'manuell':
                return self::MODE_MANUELL;
            case 'pvonly':
            default:
                return self::MODE_PVONLY;
        }
    }

    private function getCurrentModeKey(): string
    {
        return $this->mapSelectionToMode($this->getCurrentModeSelection());
    }

    private function getModeSelectionLabel(int $mode): string
    {
        switch ($mode) {
            case self::MODE_KEEP_CURRENT:
                return 'Beibehalten';
            case self::MODE_PVONLY:
                return 'Nur PV';
            case self::MODE_PV2CAR:
                return 'PV-Anteil';
            case self::MODE_MANUELL:
                return 'Manuell';
            default:
                return 'Unbekannt';
        }
    }

    // =========================================================================
    // 16. LOGGING / ALLGEMEINE HILFSFUNKTIONEN
    // =========================================================================

private function LogTemplate(string $type, string $short, string $detail = ''): void
{
    $debugActive = $this->ReadPropertyBoolean('DebugLogging');

    if ($type === 'debug' && !$debugActive) {
        return;
    }

    $emojis = [
        'info'  => 'ℹ️',
        'warn'  => '⚠️',
        'error' => '❌',
        'ok'    => '✅',
        'debug' => '🐞',
        'start' => '🚀',
        'stop'  => '⏹️'
    ];

    $icon = $emojis[$type] ?? 'ℹ️';

    $msg = trim($icon . ' ' . $short);

    if ($detail !== '') {
        $msg .= ' | ' . $detail;
    }

    // normales Log (wie bisher)
    IPS_LogMessage('[PVWM]', $msg);

    // NEU: Debug-Fenster
    if ($debugActive) {
        $this->SendDebug($type . ' | ' . $short, $detail !== '' ? $detail : '-', 0);
    }
}

}

