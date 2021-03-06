<?php

require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";

if (!jeedom::apiAccess(init('apikey'), 'phone_detection')) {
    echo __('Vous n\'êtes pas autorisé à effectuer cette action', __FILE__);
    die();
}

$results = json_decode(file_get_contents("php://input"), true);
$action = $results['action'];
$value = 0;

switch ($action) {
    case "update_device_status":
        $id = $results['id'];
        log::add('phone_detection', 'debug', 'id: '.$id);
        $value = $results['value'];
        log::add('phone_detection', 'debug', 'value: '.$value);

        $eqLogic = eqLogic::byId($id);
        if ($eqLogic->getConfiguration('deviceType') == 'phone') {
            log::add('phone_detection','debug', 'Device Name: '. $eqLogic->getHumanName());
            $stateProperty = $eqLogic->getCmd('info', 'state');
            log::add('phone_detection','debug', 'State property name: '. $stateProperty->getHumanName());

            $stateProperty->event($value);
            phone_detection::updateGlobalDevice();
        }
        $success = true;
        break;

    case "test":
        $success = true;
        $value = 0;
        break;

    case "get_status":
        $id = $results['id'];
        log::add('phone_detection', 'debug', 'id: '.$id);
        $eqLogic = eqLogic::byId($id);
        $statePropertyCmd = $eqLogic->getCmd('info', 'state');
        $value = $statePropertyCmd->execCmd();
        $success = true;
        break;

    case "refresh_group":
        phone_detection::updateGlobalDevice();
        $success = true;
        break;

    case "get_devices":
        $devices = eqLogic::byType("phone_detection", true);
        $values = Null;
        // $values["count"] = count($devices);
        // $values["devices"] = $devices;
        
        foreach($devices as $d) {
            if ($d->getConfiguration('deviceType') != 'phone') {
                continue;
            }

            $statePropertyCmd = $d->getCmd('info', 'state');
            $stateValue = $statePropertyCmd->execCmd() == 1;
            $getValueDate = $statePropertyCmd->getValueDate();
            $name = $d->getName();
            $humanName = $d->getHumanName();
            $id = $d->getId();
            $macAddress = $d->getConfiguration('macAddress');
            
            $values[$id] = [
                "state" => $stateValue, 
                "lastValueDate" => $getValueDate,
                "name" => $name,
                "humanName" => $humanName,
                "id" => $id,
                "macAddress" => $macAddress
            ];
        }
        $success = true;
        $value = $values;
        break;
}

$response = array('success' => $success, 'value' => $value);

echo json_encode($response);
?>