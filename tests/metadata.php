<?php
$root=dirname(__DIR__);
$library=json_decode(file_get_contents($root.'/library.json'),true,512,JSON_THROW_ON_ERROR);
$module=json_decode(file_get_contents($root.'/PVWallboxManager/module.json'),true,512,JSON_THROW_ON_ERROR);
$form=json_decode(file_get_contents($root.'/PVWallboxManager/form.json'),true,512,JSON_THROW_ON_ERROR);
if ($library['version']!==$module['version']) throw new RuntimeException('Version mismatch');
if (strpos(file_get_contents($root.'/CHANGELOG.md'),'vorgesehen für '.$library['version'])===false) throw new RuntimeException('Unreleased version missing');
$source=file_get_contents($root.'/PVWallboxManager/module.php');
preg_match_all("/'(\\w+)'\\s*=>\\s*\\['type'\\s*=>/",$source,$matches);
$properties=$matches[1];
$visit=function ($items) use (&$visit,$properties) {
    foreach ($items as $item) {
        if (isset($item['name']) && !in_array($item['name'],$properties,true)) throw new RuntimeException('Unregistered property: '.$item['name']);
        if (isset($item['items'])) $visit($item['items']);
    }
};
$visit($form['elements']);
foreach ($properties as $property) {
    if (strpos(file_get_contents($root.'/docs/PROPERTY-REFERENCE.md'),'| '.$property.' |')===false) throw new RuntimeException('Missing property documentation: '.$property);
}
echo "PASS metadata, form properties and property reference\n";
