<?php
// In-memory Symcon adapter. No network or real charging equipment is used.
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$GLOBALS['values'] = [];
$GLOBALS['metadata'] = [];
$GLOBALS['locks'] = [];
function IPS_SemaphoreEnter($name, $wait) { if (!empty($GLOBALS['locks'][$name])) return false; return $GLOBALS['locks'][$name] = true; }
function IPS_SemaphoreLeave($name) { $GLOBALS['locks'][$name] = false; }
function IPS_VariableExists($id) { return array_key_exists($id, $GLOBALS['values']); }
function GetValue($id) { return $GLOBALS['values'][$id]; }
function GetValueFloat($id) { return (float)GetValue($id); }
function SetValue($id, $value) { $GLOBALS['values'][$id] = $value; }
function IPS_GetVariable($id) { return $GLOBALS['metadata'][$id] ?? ['VariableType'=>2, 'VariableUpdated'=>1000, 'VariableProfile'=>'', 'VariableCustomProfile'=>'']; }
function IPS_EventExists($id) { return isset($GLOBALS['events'][$id]); }
function IPS_SetEventActive($id,$active) { $GLOBALS['events'][$id]=$active; }
function IPS_LogMessage($sender, $message) {}
function IPS_Sleep($ms) {}
function IPS_GetVariableProfile($name) { return ['Associations'=>[]]; }
class IPSModule {
    public $InstanceID = 123;
    public $properties = [];
    public $attributes = [];
    public $timers = [];
    public function __construct() {
        $source = file_get_contents(__DIR__.'/../PVWallboxManager/module.php');
        preg_match_all("/'(\w+)'\s*=>\s*\['type'\s*=>\s*'\w+',\s*'default'\s*=>\s*([^\]]+)\]/", $source, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) $this->properties[$m[1]] = eval('return '.$m[2].';');
        preg_match('/registerAttributes\(\[(.*?)\]\);/s', $source, $match);
        $this->attributes = eval('return ['.$match[1].'];');
        foreach (['Status'=>3,'AccessStateV2'=>1,'Leistung'=>0.0,'PhasenmodusEinstellung'=>1,'Phasenmodus'=>0,'LademodusAuswahl'=>2,'ManuellAmpere'=>16,'ManuellPhasen'=>1,'NetzlimitAktiv'=>false,'MaxNetzbezugWatt'=>0,'Ampere'=>6,'PVAnteil'=>100,'Freigabe'=>false,'PV_Ueberschuss'=>0,'PV_Ueberschuss_A'=>0,'ChargeTime'=>'','StatusInfo'=>'','Energie'=>0,'Fehlercode'=>0,'Kabelstrom'=>16] as $k=>$v) $this->SetValue($k,$v);
        $this->properties['WallboxIP'] = '192.0.2.1';
    }
    public function __call($name, $args) {
        if (strpos($name,'ReadProperty')===0) return $this->properties[$args[0]];
        if (strpos($name,'ReadAttribute')===0) return $this->attributes[$args[0]];
        if (strpos($name,'WriteAttribute')===0) { $this->attributes[$args[0]]=$args[1]; return; }
        if ($name==='SendDebug' || $name==='SetStatus') return;
        throw new BadMethodCallException($name);
    }
    public function GetValue($id) { return GetValue($id); }
    public function SetValue($id,$value) { SetValue($id,$value); }
    public function GetIDForIdent($id) { return $id; }
    public function SetTimerInterval($id,$value) { $this->timers[$id]=$value; }
}
function invoke($object, $method, ...$args) {
    $reflection = new ReflectionMethod($object, $method);
    $reflection->setAccessible(true);
    return $reflection->invokeArgs($object, $args);
}
function expect($condition, $message) { if (!$condition) throw new RuntimeException($message); }
