<?php
require __DIR__.'/bootstrap.php';
require __DIR__.'/../PVWallboxManager/module.php';
class SimulatedManager extends PVWallboxManager {
    public $clock = 1000;
    public $commands = [];
    public $requests = 0;
    public $reject = null;
    public $status;
    public $offline = false;
    public $prices = null;
    public $stopOnAmp = false;
    public function __construct() {
        parent::__construct();
        $this->status=['car'=>3,'psm'=>1,'frc'=>1,'alw'=>false,'amp'=>6,'err'=>0,'var'=>11,'ama'=>16,'nrg'=>array_fill(0,12,0)];
    }
    protected function now(): int { return $this->clock; }
    protected function simpleCurlGet($url) {
        if (strpos($url,'/api/status')!==false) {
            $this->requests++;
            return ['result'=>$this->offline ? false : json_encode($this->status),'httpcode'=>$this->offline ? 503 : 200,'error'=>''];
        }
        if (strpos($url,'/api/set?')!==false) {
            parse_str(parse_url($url,PHP_URL_QUERY),$args);
            $key=key($args); $this->commands[]=[$key,(int)$args[$key]];
            if ($key==='amp' && $this->stopOnAmp) $this->attributes['ControlStopRequested']=true;
            return ['result'=>json_encode([$key=>$this->reject===$key ? 'rejected' : true]),'httpcode'=>200,'error'=>''];
        }
        return ['result'=>json_encode($this->prices),'httpcode'=>200,'error'=>''];
    }
    public function plan($phase,$amp,$enable=true) {
        $this->clock+=2;
        return invoke($this,'controlled',function () use ($phase,$amp,$enable) {
            return invoke($this,'executeChargingPlan',$phase,$amp,$enable);
        });
    }
    public function grid($watts,$limit=2000) {
        $this->SetValue('NetzlimitAktiv',true); $this->SetValue('MaxNetzbezugWatt',$limit);
        $this->properties['NetzleistungID']=42; SetValue(42,$watts);
        $GLOBALS['metadata'][42]=['VariableType'=>2,'VariableUpdated'=>$this->clock,'VariableProfile'=>'','VariableCustomProfile'=>''];
    }
}
$tests=[];
$tests['start writes current before enabling'] = function () {
    $m=new SimulatedManager(); $m->plan(1,16);
    expect($m->commands===[['amp',16],['frc',2]],'Current must precede enable');
    expect($m->requests===1,'Exactly one status request per control cycle');
};
$tests['negative grid power still limits requested load'] = function () {
    $m=new SimulatedManager(); $m->grid(-500); $m->plan(2,16);
    expect($m->commands===[['amp',10],['frc',2]],'2500 W budget should stay at 1P/10A');
};
$tests['zero grid power still limits requested load'] = function () {
    $m=new SimulatedManager(); $m->grid(0); $m->plan(2,16);
    expect($m->commands===[['amp',8],['frc',2]],'2000 W budget should stay at 1P/8A');
};
$tests['invalid meter never releases charging'] = function () {
    $m=new SimulatedManager(); $m->SetValue('NetzlimitAktiv',true); $m->SetValue('MaxNetzbezugWatt',2000); $m->plan(2,16);
    expect($m->commands===[],'Stopped wallbox must not be enabled');
};
$tests['stale meter blocks charging'] = function () {
    $m=new SimulatedManager(); $m->grid(0); $m->clock+=121; $m->plan(1,16);
    expect($m->commands===[],'Stale values cannot authorize a start');
};
$tests['inverted kW grid input'] = function () {
    $m=new SimulatedManager(); $m->grid(0.5); $m->properties['NetzleistungEinheit']='kW'; $m->properties['InvertNetzleistung']=true; $m->plan(1,16);
    expect($m->commands===[['amp',10],['frc',2]],'Inverted kW must normalize to -500 W');
};
$tests['phase change waits for stop and readback'] = function () {
    $m=new SimulatedManager(); $m->status['frc']=2; $m->status['car']=2; $m->status['alw']=true; $m->status['nrg'][11]=3000; $m->status['nrg'][4]=13;
    $m->plan(2,6); $m->plan(2,6);
    expect(!in_array(['psm',2],$m->commands,true),'No psm while current flows');
    $m->status['frc']=1; $m->status['car']=3; $m->status['alw']=false; $m->status['nrg']=array_fill(0,12,0);
    $m->plan(2,6); $m->plan(2,6);
    expect(end($m->commands)===['psm',2],'Two stopped samples precede psm');
    $m->plan(2,6);
    expect(!in_array(['frc',2],$m->commands,true),'No enable before phase readback');
    $m->status['psm']=2; $m->plan(2,6); $m->plan(2,6);
    expect($m->attributes['PhaseTransitionState']==='idle','Readback completes transition');
    expect(!in_array(['frc',2],$m->commands,true),'Completion does not immediately resume');
    $m->plan(2,6); expect(end($m->commands)===['frc',2],'New cycle may resume');
};
$tests['HTTP 200 API error prevents release'] = function () {
    $m=new SimulatedManager(); $m->reject='amp'; $m->plan(1,16);
    expect($m->attributes['PhaseTransitionState']==='fault','API rejection must latch fault');
    expect(!in_array(['frc',2],$m->commands,true),'No enable after current rejection');
};
$tests['stop rejection prevents phase command'] = function () {
    $m=new SimulatedManager(); $m->status['frc']=2; $m->reject='frc'; $m->plan(2,6);
    expect($m->attributes['PhaseTransitionState']==='fault','Stop rejection faults');
    expect(!in_array(['psm',2],$m->commands,true),'No psm after stop rejection');
};
$tests['timeout remains latched across repeated updates'] = function () {
    $m=new SimulatedManager(); $m->status['frc']=2; $m->plan(2,6); $m->clock+=61; $m->plan(2,6); $m->plan(2,6);
    expect($m->attributes['PhaseTransitionState']==='fault','Timeout remains fault');
    expect(!in_array(['frc',2],$m->commands,true),'No resume after timeout');
};
$tests['cooldown does not force unsafe phase fallback'] = function () {
    $m=new SimulatedManager(); $m->status['psm']=2; $m->attributes['LetztePhasenUmschaltung']=990; $m->grid(500);
    $m->plan(2,16);
    expect($m->commands===[],'1P budget during 3P cooldown must remain stopped');
};
$tests['disabled module never enables'] = function () {
    $m=new SimulatedManager(); $m->properties['ModulAktiv']=false;
    $m->plan(2,16); $m->RequestAction('ManuellAmpere',16);
    expect(!in_array(['frc',2],$m->commands,true),'Disabled action cannot restart charging');
};
$tests['invalid charger payload never confirms standstill'] = function () {
    $m=new SimulatedManager(); unset($m->status['nrg']); $m->plan(2,16);
    expect($m->attributes['PhaseTransitionState']==='fault','Missing currents cannot mean stopped');
    expect(!in_array(['psm',2],$m->commands,true),'No phase switch on incomplete status');
};
$tests['semaphore prevents overlapping update and releases after exception'] = function () {
    $m=new SimulatedManager(); $GLOBALS['locks']['PVWM.Control.123']=true;
    $m->UpdateStatus(); expect($m->requests===0,'Busy timer performs no I/O');
    $GLOBALS['locks']['PVWM.Control.123']=false;
    try { invoke($m,'controlled',function () { throw new RuntimeException('test'); }); } catch (RuntimeException $e) {}
    expect(!$GLOBALS['locks']['PVWM.Control.123'],'finally releases semaphore');
};
$tests['manual full update routes through safe executor'] = function () {
    $m=new SimulatedManager(); $m->UpdateStatus();
    expect($m->commands===[['amp',16],['frc',2]],'Full UpdateStatus controls manual safely');
};
$tests['empty and malformed prices retain previous values'] = function () {
    $m=new SimulatedManager(); $m->properties['UseMarketPrices']=true; $m->SetValue('CurrentSpotPrice',25.0);
    foreach ([['data'=>[]],['data'=>'bad'],['data'=>[['marketprice'=>12]]]] as $payload) { $m->prices=$payload; invoke($m,'AktualisiereMarktpreise'); }
    expect($m->GetValue('CurrentSpotPrice')===25.0,'Invalid prices must not overwrite last valid price');
};
$tests['min current above hardware limit does not crash or charge'] = function () {
    $m=new SimulatedManager(); $m->properties['MinAmpere']=20; $m->plan(1,32);
    expect(!in_array(['frc',2],$m->commands,true),'Impossible minimum must stop');
};
$tests['phase rejection after a successful stop cannot resume'] = function () {
    $m=new SimulatedManager(); $m->reject='psm';
    $m->plan(2,6); $m->plan(2,6); $m->plan(2,6);
    expect($m->attributes['PhaseTransitionState']==='fault','Rejected psm latches a fault');
    expect(!in_array(['frc',2],$m->commands,true),'Rejected psm never releases');
};
$tests['lost connection during switching remains blocked'] = function () {
    $m=new SimulatedManager(); $m->plan(2,6); $m->offline=true; $m->plan(2,6);
    $m->offline=false; $m->plan(2,6);
    expect($m->attributes['PhaseTransitionState']==='fault','Recovery alone cannot release fault');
};
$tests['fault reset requires confirmed standstill'] = function () {
    $m=new SimulatedManager(); $m->attributes['PhaseTransitionState']='fault'; $m->status['frc']=2;
    expect($m->ResetPhaseFault()===false,'Cannot reset while not stopped');
    $m->status['frc']=1;
    expect($m->ResetPhaseFault()===true,'Stopped wallbox permits explicit reset');
};
$tests['stop during unconfirmed switch latches fault'] = function () {
    $m=new SimulatedManager(); $m->plan(2,6); $m->plan(2,6); $m->plan(2,6);
    $m->SetForceState(1);
    expect($m->attributes['PhaseTransitionState']==='fault','An unconfirmed switch cannot be silently discarded');
};
$tests['persisted phase transition resumes without duplicate psm'] = function () {
    $m=new SimulatedManager(); $m->plan(2,6); $m->plan(2,6); $m->plan(2,6);
    $restored=new SimulatedManager(); $restored->attributes=$m->attributes; $restored->clock=$m->clock; $restored->status['psm']=2;
    $restored->plan(2,6); $restored->plan(2,6);
    expect($restored->commands===[],'Readback after worker recreation must not repeat psm');
    expect($restored->attributes['PhaseResumePending']===true,'Resume intent persists until new plan is applied');
};
$tests['repeated same-second calls do not count as separate confirmations'] = function () {
    $m=new SimulatedManager(); $m->plan(2,6); $m->clock-=2; $m->plan(2,6); $m->clock-=2; $m->plan(2,6);
    expect(!in_array(['psm',2],$m->commands,true),'Distinct samples need time separation');
};
$tests['PV start respects configured hysteresis'] = function () {
    $m=new SimulatedManager(); $m->SetValue('LademodusAuswahl',0); $m->properties['PVErzeugungID']=43; SetValue(43,3000.0);
    $m->UpdateStatus(); $m->UpdateStatus();
    expect(!in_array(['frc',2],$m->commands,true),'No unconditional fast start');
    $m->UpdateStatus(); expect(in_array(['frc',2],$m->commands,true),'Third stable cycle starts');
};
$tests['PV demand for 3P cannot override a 1P grid budget'] = function () {
    $m=new SimulatedManager(); $m->SetValue('LademodusAuswahl',0); $m->properties['PVErzeugungID']=43; SetValue(43,9000.0); $m->grid(-500);
    for($i=0;$i<5;$i++) $m->UpdateStatus();
    expect(!in_array(['psm',2],$m->commands,true),'PV demand cannot command 3P before budget check');
    foreach($m->commands as $c) if($c[0]==='amp') expect($c[1]<=10,'Grid budget bounds current');
};
$tests['PV share mode obeys net budget'] = function () {
    $m=new SimulatedManager(); $m->SetValue('LademodusAuswahl',1); $m->SetValue('PVAnteil',50); $m->properties['PVErzeugungID']=43; SetValue(43,9000.0); $m->grid(-500);
    for($i=0;$i<5;$i++) $m->UpdateStatus();
    expect(!in_array(['psm',2],$m->commands,true),'PV share mode cannot override grid budget');
};
$tests['forced Hybrid 3P end charging obeys grid budget'] = function () {
    $m=new SimulatedManager(); $m->SetValue('LademodusAuswahl',5); $m->status['frc']=2; $m->properties['HybridEndMode']=2;
    $m->attributes['HybridLowPvSince']=1; $m->grid(-500);
    $m->UpdateStatus(); expect(!in_array(['psm',2],$m->commands,true),'Forced Hybrid still honors 1P budget');
};
$tests['forced Hybrid obeys cooldown'] = function () {
    $m=new SimulatedManager(); $m->SetValue('LademodusAuswahl',5); $m->status['frc']=2; $m->properties['HybridEndMode']=2;
    $m->attributes['HybridLowPvSince']=1; $m->attributes['LetztePhasenUmschaltung']=990;
    $m->UpdateStatus(); expect(!in_array(['psm',2],$m->commands,true),'Forced Hybrid cannot bypass cooldown');
};
$tests['target SoC stops even manual requests'] = function () {
    $m=new SimulatedManager(); $m->properties['CarSOCID']=44; $m->properties['CarTargetSOCID']=45; SetValue(44,80); SetValue(45,80);
    $m->plan(1,16); expect(!in_array(['frc',2],$m->commands,true),'Target SoC has priority');
};
$tests['disconnected car cancels pending stop'] = function () {
    $m=new SimulatedManager(); $m->plan(2,6); $m->status['car']=1; $m->plan(2,6);
    expect($m->attributes['PhaseTransitionState']==='idle','Disconnect cancels before psm');
    expect(!in_array(['psm',2],$m->commands,true),'No psm after disconnect');
};
$tests['wallbox limits cache is bound to IP and expires'] = function () {
    $m=new SimulatedManager();
    invoke($m,'controlled',function () use($m) { expect(invoke($m,'clampAmpere',32)===16,'16A hardware limit'); expect(invoke($m,'clampAmpere',32)===16,'Cached hardware limit'); });
    expect($m->requests===1,'Clamp does not repeat HTTP');
    $m->properties['WallboxIP']='192.0.2.2'; $m->status['var']=22; $m->status['ama']=32; $m->properties['MaxAmpere']=32;
    invoke($m,'controlled',function () use($m) { expect(invoke($m,'clampAmpere',32)===32,'New IP invalidates limit cache'); });
    $m->clock+=301; $m->offline=true;
    invoke($m,'controlled',function () use($m) { expect(invoke($m,'clampAmpere',32)===0,'Expired unavailable limit cannot authorize charging'); });
};
$tests['two-phase vehicle remains distinct from configured 3P'] = function () {
    $m=new SimulatedManager(); $m->status['psm']=2; $m->status['nrg'][4]=6; $m->status['nrg'][5]=6;
    expect(invoke($m,'determinePhases',$m->status)===2,'Measured vehicle phases remain two');
    $m->plan(2,6); expect(!in_array(['psm',1],$m->commands,true),'Two used phases do not imply 1P mode');
};
$tests['public phase command cannot bypass automatic mode'] = function () {
    $m=new SimulatedManager(); $m->SetValue('LademodusAuswahl',0);
    expect($m->SetPhaseMode(2)===false,'External direct phase call cannot bypass PV mode');
    expect($m->commands===[],'No raw command');
};
$tests['current price selects matching interval including negative prices'] = function () {
    $m=new SimulatedManager(); $m->properties['UseMarketPrices']=true;
    $m->prices=['data'=>[
        ['start_timestamp'=>1100000,'end_timestamp'=>1200000,'marketprice'=>200],
        ['start_timestamp'=>900000,'end_timestamp'=>1100000,'marketprice'=>-50],
        ['start_timestamp'=>800000,'end_timestamp'=>900000,'marketprice'=>100]
    ]];
    invoke($m,'AktualisiereMarktpreise');
    expect($m->GetValue('CurrentSpotPrice')===-5.0,'Use matching interval, not first row');
    expect($m->GetValue('MarketPricesValid')===true,'Valid response is marked current');
};
$tests['deactivation arriving during current command prevents release'] = function () {
    $m=new SimulatedManager(); $m->stopOnAmp=true; $m->plan(1,16);
    expect(!in_array(['frc',2],$m->commands,true),'Pending deactivation wins before frc=2');
};
$tests['deactivation during active current change requests stop'] = function () {
    $m=new SimulatedManager(); $m->status['frc']=2; $m->stopOnAmp=true; $m->plan(1,16);
    expect(end($m->commands)===['frc',1],'Already active charging must also stop');
};
$tests['sanitized user V4 firmware 60.6 snapshot is accepted without assuming stop confirmation'] = function () {
    $m=new SimulatedManager();
    $m->status=json_decode(file_get_contents(__DIR__.'/fixtures/go-e-v4-60.6-sanitized.json'),true,512,JSON_THROW_ON_ERROR);
    expect(invoke($m,'validChargerStatus',$m->status),'Actual field types must be accepted');
    expect(invoke($m,'getHardwareMaxAmpereFromStatus',$m->status)===16,'11 kW variant maps to 16 A');
    expect(invoke($m,'determinePhases',$m->status)===0,'No measured charging current');
    expect(!invoke($m,'chargerStopped',$m->status),'frc=0 is not an acknowledged frc=1 stop');
    $m->plan(2,16);
    expect($m->commands===[['frc',1]],'Disconnected car must not receive phase or enable commands');
};
$tests['automatic phase learning and vehicle change'] = function () {
    foreach ([1=>16,2=>13,3=>9] as $count=>$expected) {
        $m=new SimulatedManager(); $m->properties['CarMaxPhases']=1; // Legacy value must not decide.
        $m->status['psm']=2; $m->status['car']=2; $m->status['alw']=true;
        $m->status['amp']=6; $m->status['nrg'][11]=3330;
        for($i=0;$i<$count;$i++) $m->status['nrg'][4+$i]=6;
        $m->grid(3040,6000);
        $m->plan(2,16);
        expect(in_array(['amp',9],$m->commands,true),'First sample must assume 3 phases');
        $m->plan(2,16); $m->commands=[]; $m->plan(2,16);
        expect(in_array(['amp',$expected],$m->commands,true),'Stable actual phases determine budget');
        $m->status['car']=1; $m->plan(2,16);
        expect(invoke($m,'vehiclePhaseCount',3)===3,'Unplug resets learned vehicle');
        $m->status['car']=2; $m->status['nrg'][6]=6; $m->commands=[]; $m->plan(2,16);
        expect(in_array(['amp',9],$m->commands,true),'Next vehicle starts conservatively');
    }
};
$tests['phase learning discards pauses and phase increases immediately'] = function () {
    $m=new SimulatedManager();
    $s=$m->status; $s['car']=2; $s['alw']=true; $s['psm']=2; $s['nrg'][4]=6; $s['nrg'][5]=6;
    for($i=0;$i<3;$i++) { invoke($m,'observeVehiclePhases',$s,'192.168.1.2'); $m->clock+=2; }
    expect(invoke($m,'vehiclePhaseCount',3)===2,'Two phases learned');
    expect(invoke($m,'vehiclePhaseCount',1)===1,'1P mode stays single phase');
    $s['nrg'][6]=6; invoke($m,'observeVehiclePhases',$s,'192.168.1.2');
    expect(invoke($m,'vehiclePhaseCount',3)===3,'Third phase immediately restores conservative budget');
    $s['car']=3; invoke($m,'observeVehiclePhases',$s,'192.168.1.2');
    expect(invoke($m,'vehiclePhaseCount',3)===3,'Pause resets detection');
};
$tests['repeated samples and taper cannot establish fewer phases'] = function () {
    $m=new SimulatedManager(); $s=$m->status; $s['car']=2; $s['alw']=true; $s['psm']=2; $s['nrg'][4]=6;
    for($i=0;$i<4;$i++) invoke($m,'observeVehiclePhases',$s,'192.168.1.2');
    expect(invoke($m,'vehiclePhaseCount',3)===3,'Same time samples do not qualify');
    $s['amp']=16;
    for($i=0;$i<4;$i++) { $m->clock+=2; invoke($m,'observeVehiclePhases',$s,'192.168.1.2'); }
    expect(invoke($m,'vehiclePhaseCount',3)===3,'Tapering is not capability evidence');
};
$tests['PV share phase selection uses allocated power'] = function () {
    $m=new SimulatedManager(); $m->SetValue('LademodusAuswahl',1); $m->SetValue('PVAnteil',25);
    $m->properties['PVErzeugungID']=43; SetValue(43,8000.0);
    $m->properties['Phasen3Limit']=1; $m->properties['StartLadeHysterese']=1;
    $m->attributes['LastChargingCurrent']=16;
    $m->UpdateStatus();
    expect($m->attributes['PhaseTransitionState']==='idle','2000 W allocation must not request 3P for 8000 W total');
};
$tests['zero PV share stops even with long hysteresis'] = function () {
    $m=new SimulatedManager(); $m->SetValue('LademodusAuswahl',1); $m->SetValue('PVAnteil',0);
    $m->status['frc']=2; $m->status['car']=2; $m->status['alw']=true; $m->status['nrg'][11]=2000;
    $m->properties['StopLadeHysterese']=10; $m->attributes['LastChargingCurrent']=10;
    $m->UpdateStatus();
    expect(in_array(['frc',1],$m->commands,true),'Explicit zero share must stop immediately');
};
$tests['Hybrid preserves missing grid reason'] = function () {
    $m=new SimulatedManager(); $m->SetValue('LademodusAuswahl',5);
    $m->properties['PVErzeugungID']=43; SetValue(43,3000.0); $m->properties['StartLadeHysterese']=1;
    $m->attributes['LastChargingCurrent']=16;
    $m->SetValue('NetzlimitAktiv',true); $m->SetValue('MaxNetzbezugWatt',4000);
    $m->UpdateStatus();
    expect(strpos($m->attributes['LastNoChargeReason'],'Messvariable fehlt')!==false,'Hybrid must preserve grid failure reason');
};
$tests['mode change resets prior surplus and phase counters'] = function () {
    $m=new SimulatedManager(); $m->attributes['SmoothedSurplus']=10000;
    $m->attributes['Phasen3Zaehler']=20; $m->attributes['HybridLowPvSince']=1;
    $m->RequestAction('LademodusAuswahl',1);
    expect($m->attributes['SmoothedSurplus']==0,'Old surplus must not authorize new mode');
    expect($m->attributes['HybridLowPvSince']===0,'Old Hybrid delay must not survive mode changes');
    expect(!in_array(['psm',2],$m->commands,true),'No stale 3P request');
};
$tests['invalid configured house SoC cannot authorize PV start'] = function () {
    foreach([0,5] as $mode) {
        $m=new SimulatedManager(); $m->SetValue('LademodusAuswahl',$mode); $m->properties['HausakkuSOCID']=999;
        $m->properties['PVErzeugungID']=43; SetValue(43,3000.0); $m->properties['StartLadeHysterese']=1;
        $m->attributes['LastChargingCurrent']=16; $m->UpdateStatus();
        expect(!in_array(['frc',2],$m->commands,true),'Missing configured house SoC must block PV/Hybrid');
    }
};
$tests['numeric string energy source is rejected before float read'] = function () {
    $m=new SimulatedManager(); $m->properties['PVErzeugungID']=43; SetValue(43,'3000');
    $GLOBALS['metadata'][43]=['VariableType'=>3,'VariableUpdated'=>1000];
    $m->UpdateStatus();
    expect(!in_array(['frc',2],$m->commands,true),'String source must not reach numeric Symcon getter');
    unset($GLOBALS['metadata'][43]);
};
foreach ([0=>'PV',1=>'PV share',2=>'manual',5=>'Hybrid'] as $mode=>$label) {
    $tests[$label.' obeys stop conditions'] = function () use ($mode) {
        foreach (['grid','soc','disabled','offline'] as $condition) {
            $m=new SimulatedManager(); $m->SetValue('LademodusAuswahl',$mode);
            $m->status['car']=2; $m->status['frc']=2; $m->status['alw']=true; $m->status['nrg'][11]=1380;
            $m->properties['PVErzeugungID']=43; SetValue(43,9000.0);
            if ($condition==='grid') $m->grid(9000,1000);
            if ($condition==='soc') { $m->properties['CarSOCID']=44; $m->properties['CarTargetSOCID']=45; SetValue(44,80); SetValue(45,80); }
            if ($condition==='disabled') $m->properties['ModulAktiv']=false;
            if ($condition==='offline') $m->offline=true;
            $m->UpdateStatus();
            expect(in_array(['frc',1],$m->commands,true),$condition.' must request stop');
            foreach($m->commands as $c) expect($c[0]==='frc' && $c[1]===1,'Stop condition must not change phase or current');
        }
    };
    $tests[$label.' uses automatically detected two phase budget'] = function () use ($mode) {
        $m=new SimulatedManager(); $m->SetValue('LademodusAuswahl',$mode); $m->SetValue('ManuellPhasen',2);
        $m->status['car']=2; $m->status['frc']=2; $m->status['alw']=true; $m->status['psm']=2;
        $m->status['amp']=6; $m->status['nrg'][4]=6; $m->status['nrg'][5]=6; $m->status['nrg'][11]=2760;
        $m->properties['PVErzeugungID']=43; SetValue(43,9000.0); $m->grid(3000,5000);
        $m->attributes['LastChargingCurrent']=16;
        for($i=0;$i<5;$i++) { $m->clock+=2; $m->commands=[]; $m->UpdateStatus(); }
        expect(in_array(['amp',10],$m->commands,true),'4760 W budget supports two phases at 10 A');
        expect($m->attributes['PhaseTransitionState']==='idle','No unnecessary switching');
    };
}
$tests['PV start is blocked by low house battery but PV share ignores that gate'] = function () {
    foreach([0,1,5] as $mode) {
        $m=new SimulatedManager(); $m->SetValue('LademodusAuswahl',$mode); $m->properties['HausakkuSOCID']=46; SetValue(46,20);
        $m->properties['PVErzeugungID']=43; SetValue(43,3000.0); $m->properties['StartLadeHysterese']=1;
        $m->attributes['LastChargingCurrent']=16; $m->UpdateStatus();
        expect(in_array(['frc',2],$m->commands,true)===($mode===1),'House SoC gate must match mode semantics');
    }
};
$tests['PV stops after configured hysteresis while Hybrid holds minimum'] = function () {
    foreach([0,5] as $mode) {
        $m=new SimulatedManager(); $m->SetValue('LademodusAuswahl',$mode);
        $m->status['car']=2; $m->status['frc']=2; $m->status['alw']=true; $m->status['nrg'][11]=1380;
        $m->properties['StopLadeHysterese']=1; $m->UpdateStatus();
        expect(in_array(['frc',1],$m->commands,true)===($mode===0),'PV stops, Hybrid maintains minimum');
    }
};
$tests['invalid vehicle SoC cannot crash or release charging'] = function () {
    $m=new SimulatedManager(); $m->properties['CarSOCID']=44; $m->properties['CarTargetSOCID']=45;
    SetValue(44,'offline'); SetValue(45,80); $m->UpdateStatus();
    expect(!in_array(['frc',2],$m->commands,true),'Invalid configured vehicle SoC must block');
    expect(invoke($m,'BerechneVerbleibendeLadezeit')==='n/a','Invalid SoC has no time estimate');
};
$tests['PV stop hysteresis survives zero surplus'] = function () {
    $m=new SimulatedManager(); $m->SetValue('LademodusAuswahl',0);
    $m->status['car']=2; $m->status['frc']=2; $m->status['alw']=true; $m->status['nrg'][11]=1380;
    $m->properties['StopLadeHysterese']=3;
    $m->UpdateStatus(); $m->UpdateStatus();
    expect(!in_array(['frc',1],$m->commands,true),'Stop hysteresis must not be bypassed by zero current calculation');
    $m->UpdateStatus(); expect(in_array(['frc',1],$m->commands,true),'Third low cycle stops');
};
$tests['Hybrid end delay uses minimum then configured current'] = function () {
    $m=new SimulatedManager(); $m->SetValue('LademodusAuswahl',5);
    $m->status['car']=2; $m->status['frc']=2; $m->status['alw']=true; $m->status['nrg'][11]=1380;
    $m->properties['HybridEndMode']=1; $m->properties['HybridEndAmpere']=10; $m->properties['HybridEndDelaySeconds']=60;
    $m->UpdateStatus(); expect(!in_array(['amp',10],$m->commands,true),'End current must wait');
    $m->clock+=60; $m->UpdateStatus(); expect(in_array(['amp',10],$m->commands,true),'End current starts after delay');
};
$tests['invalid infinite market values preserve prior price'] = function () {
    $m=new SimulatedManager(); $m->properties['UseMarketPrices']=true; $m->SetValue('CurrentSpotPrice',25);
    $m->prices=['data'=>[['start_timestamp'=>0,'end_timestamp'=>2000000,'marketprice'=>'1e999']]];
    invoke($m,'AktualisiereMarktpreise');
    expect($m->GetValue('MarketPricesValid')===false && $m->GetValue('CurrentSpotPrice')===25,'Infinite numeric strings are not valid prices');
};
$tests['PV share automatically increases at house battery target'] = function () {
    $m=new SimulatedManager(); $m->SetValue('LademodusAuswahl',1); $m->SetValue('PVAnteil',70);
    $m->properties['HausakkuSOCID']=46; $m->properties['HausakkuSOCVollSchwelle']=93;
    $m->properties['PVErzeugungID']=43; SetValue(43,3000.0);
    $m->status['car']=2; $m->status['frc']=2; $m->status['alw']=true;
    $m->status['amp']=6; $m->status['nrg'][11]=1380; $m->attributes['LastChargingCurrent']=16;
    foreach([92,93,100,92] as $soc) {
        SetValue(46,$soc); $m->commands=[]; $m->clock+=30; $m->UpdateStatus();
        expect(!in_array(['frc',1],$m->commands,true),'House SoC crossing must not stop PV share');
        $expected = $soc >= 93 ? 14 : 10;
        // Current increases are ramped; allow another cycle to reach the requested power.
        $m->clock+=30; $m->UpdateStatus();
        expect(in_array(['amp',$expected],$m->commands,true),'Effective share follows house SoC');
        expect($m->GetValue('PVAnteil')===70,'User share must remain stored');
        $text=invoke($m,'collectStatusData')['modusText'];
        expect((strpos($text,'wirksam 100 %')!==false)===($soc>=93),'Status must explain effective share');
    }
};
$tests['70 percent of 1500 W can stop below house target'] = function () {
    $m=new SimulatedManager(); $m->SetValue('LademodusAuswahl',1); $m->SetValue('PVAnteil',70);
    $m->properties['PVErzeugungID']=43; SetValue(43,1500.0);
    $m->properties['HausakkuSOCID']=46; SetValue(46,20);
    $m->status['car']=2; $m->status['frc']=2; $m->status['alw']=true; $m->status['nrg'][11]=1380;
    for($i=0;$i<max(1,$m->properties['StopLadeHysterese']);$i++) { $m->clock+=30; $m->UpdateStatus(); }
    expect(in_array(['frc',1],$m->commands,true),'1050 W share is below default stop threshold');
    SetValue(46,100); $m->SetValue('LademodusAuswahl',0); $m->status['car']=3; $m->status['frc']=1; $m->status['alw']=false; $m->status['nrg'][11]=0; $m->commands=[];
    for($i=0;$i<max(3,$m->properties['StartLadeHysterese']);$i++) { $m->clock+=30; $m->UpdateStatus(); }
    expect(in_array(['frc',2],$m->commands,true),'1500 W full surplus meets default start threshold');
};
$tests['automatic PV share preserves zero and falls back on unavailable house SoC'] = function () {
    $m=new SimulatedManager(); $m->SetValue('PVAnteil',70);
    expect(invoke($m,'effectivePvShare')===70,'No assigned meter keeps requested share');
    $m->properties['HausakkuSOCID']=46;
    foreach (['offline',-1,101] as $value) {
        SetValue(46,$value); expect(invoke($m,'effectivePvShare')===70,'Invalid SoC must not authorize full share');
    }
    SetValue(46,100); $m->SetValue('PVAnteil',0);
    expect(invoke($m,'effectivePvShare')===0,'Full house battery cannot override explicit zero');
};
$tests['automatic full PV share still obeys grid limit'] = function () {
    $m=new SimulatedManager(); $m->SetValue('LademodusAuswahl',1); $m->SetValue('PVAnteil',70);
    $m->properties['HausakkuSOCID']=46; SetValue(46,100);
    $m->properties['PVErzeugungID']=43; SetValue(43,9000.0); $m->grid(0,1000);
    for($i=0;$i<5;$i++) $m->UpdateStatus();
    expect(!in_array(['frc',2],$m->commands,true),'Full share must not bypass insufficient net budget');
};
$tests['PV share waiting vehicle does not fall back to manual'] = function () {
    $m=new SimulatedManager(); $m->SetValue('LademodusAuswahl',1); $m->properties['ModeAfterUnplug']=2;
    $m->properties['PVErzeugungID']=43; SetValue(43,3000.0);
    $m->status['car']=4; $m->status['frc']=2; $m->status['alw']=false;
    for($i=0;$i<12;$i++) { $m->clock+=16; $m->UpdateStatus(); }
    expect($m->GetValue('LademodusAuswahl')===1,'A release without observed charging is not a charge end');
};
$tests['actual completed charging still applies configured end mode'] = function () {
    $m=new SimulatedManager(); $m->SetValue('LademodusAuswahl',1); $m->properties['ModeAfterUnplug']=2;
    $m->properties['PVErzeugungID']=43; SetValue(43,3000.0);
    $m->status['car']=2; $m->status['frc']=2; $m->status['alw']=true; $m->status['nrg'][11]=2000;
    $m->UpdateStatus(); $m->status['car']=4; $m->status['alw']=false; $m->status['nrg'][11]=0;
    for($i=0;$i<4;$i++) { $m->clock+=30; $m->UpdateStatus(); }
    expect($m->GetValue('LademodusAuswahl')===2,'Confirmed charging followed by completion applies configured mode');
};
$tests['temporary low charging power is not completion'] = function () {
    $m=new SimulatedManager(); $m->SetValue('LademodusAuswahl',1); $m->properties['ModeAfterUnplug']=2;
    $m->properties['PVErzeugungID']=43; SetValue(43,3000.0);
    $m->status['car']=2; $m->status['frc']=2; $m->status['alw']=true; $m->status['nrg'][11]=2000;
    $m->UpdateStatus(); $m->status['nrg'][11]=100;
    for($i=0;$i<6;$i++) { $m->clock+=30; $m->UpdateStatus(); }
    expect($m->GetValue('LademodusAuswahl')===1,'Low power with charging status must not change mode');
};
$tests['intentional stop resets completed session evidence'] = function () {
    $m=new SimulatedManager(); $m->attributes['ChargingPowerObserved']=true; $m->attributes['NoPowerCounter']=2;
    $m->plan(1,6,false);
    expect($m->attributes['ChargingPowerObserved']===false && $m->attributes['NoPowerCounter']===0,'Each restart needs fresh charging evidence');
};
$tests['PV start waits for phase decision before release'] = function () {
    foreach([0,1,5] as $mode) {
        $m=new SimulatedManager(); $m->SetValue('LademodusAuswahl',$mode);
        $m->status['psm']=2; $m->properties['PVErzeugungID']=43; SetValue(43,2800.0);
        $m->properties['StartLadeHysterese']=1; $m->properties['Phasen1Limit']=5;
        for($i=0;$i<4;$i++) { $m->clock+=16; $m->UpdateStatus(); }
        expect(!in_array(['frc',2],$m->commands,true),'Do not briefly release in old 3P mode');
        $m->clock+=16; $m->UpdateStatus();
        expect($m->attributes['PhaseTransitionState']==='stopping','Stable 1P choice starts controlled transition');
    }
};
$tests['waiting car with prior release settles phase choice first'] = function () {
    $m=new SimulatedManager(); $m->SetValue('LademodusAuswahl',1);
    $m->status['car']=4; $m->status['frc']=2; $m->status['psm']=2;
    $m->properties['PVErzeugungID']=43; SetValue(43,2800.0); $m->properties['Phasen1Limit']=5;
    $m->UpdateStatus();
    expect(in_array(['frc',1],$m->commands,true),'Waiting car must not remain released with unresolved phase choice');
};
$tests['cooldown does not start stopped car in wrong phase mode'] = function () {
    $m=new SimulatedManager(); $m->status['psm']=1; $m->attributes['LetztePhasenUmschaltung']=990;
    $m->plan(2,6);
    expect(!in_array(['frc',2],$m->commands,true),'Wait for target phase instead of starting briefly during cooldown');
};
$tests['PV startup releases only after target phase readback'] = function () {
    foreach([[2,1,2800],[1,2,9000]] as [$initial,$target,$surplus]) {
        $m=new SimulatedManager(); $m->SetValue('LademodusAuswahl',1); $m->status['psm']=$initial;
        $m->properties['PVErzeugungID']=43; SetValue(43,(float)$surplus);
        $m->properties['StartLadeHysterese']=1; $m->properties['Phasen1Limit']=3; $m->properties['Phasen3Limit']=3;
        for($i=0;$i<5;$i++) { $m->clock+=2; $m->UpdateStatus(); }
        expect(in_array(['psm',$target],$m->commands,true),'Stable choice must command target phase while stopped');
        expect(!in_array(['frc',2],$m->commands,true),'No release before phase readback');
        $m->status['psm']=$target;
        for($i=0;$i<4;$i++) { $m->clock+=2; $m->UpdateStatus(); }
        expect(in_array(['frc',2],$m->commands,true),'Confirmed target allows charging');
    }
};
$tests['running PV charge keeps phase hysteresis without immediate stop'] = function () {
    $m=new SimulatedManager(); $m->SetValue('LademodusAuswahl',1); $m->status['psm']=2;
    $m->status['car']=2; $m->status['frc']=2; $m->status['alw']=true; $m->status['nrg'][11]=2760;
    $m->properties['PVErzeugungID']=43; SetValue(43,2800.0); $m->properties['Phasen1Limit']=5;
    $m->UpdateStatus();
    expect(!in_array(['frc',1],$m->commands,true),'Running charge retains hysteresis while threshold settles');
};
$failures=0;
foreach ($tests as $name=>$test) {
    try { $test(); echo "PASS $name\n"; }
    catch (Throwable $e) { ++$failures; echo "FAIL $name: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}\n"; }
}
exit($failures ? 1 : 0);
