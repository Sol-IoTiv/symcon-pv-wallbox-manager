<?php
require __DIR__.'/bootstrap.php';
require __DIR__.'/../PVWallboxManager/module.php';
$tests = [];
$tests['idle phases are zero'] = function () {
    expect(invoke(new PVWallboxManager(), 'determinePhases', ['nrg'=>array_fill(0,12,0)]) === 0, 'Idle must not claim 1P');
};
$tests['invalid grid variable blocks charging'] = function () {
    $m=new PVWallboxManager(); $m->SetValue('NetzlimitAktiv',true); $m->SetValue('MaxNetzbezugWatt',2000);
    $phase=3; $r=new ReflectionMethod($m,'applyMaxGridLoadLimit'); $r->setAccessible(true);
    expect($r->invokeArgs($m,[16,&$phase])===0,'Missing grid meter must not bypass the limit');
};
$tests['sustained house load is not discarded forever'] = function () {
    $m=new PVWallboxManager();
    invoke($m,'applyFilters',['haus'=>1000,'wallbox'=>0]);
    for ($i=0;$i<5;$i++) $result=invoke($m,'applyFilters',['haus'=>8000,'wallbox'=>0]);
    expect($result['hausFiltered']>=7900,'Real sustained load must enter the filter');
};
$failures=0;
foreach ($tests as $name=>$test) {
    try { $test(); echo "PASS $name\n"; }
    catch (Throwable $e) { ++$failures; echo "FAIL $name: {$e->getMessage()}\n"; }
}
exit($failures ? 1 : 0);
