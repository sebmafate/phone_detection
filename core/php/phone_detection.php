<?php

require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";

if (!jeedom::apiAccess(init('apikey'), 'phone_detection')) {
    echo __('Vous n\'êtes pas autorisé à effectuer cette action', __FILE__);
    die();
}

$results = json_decode(file_get_contents("php://input"), true);
$action  = $results['action'];
$value   = 0;
$remotes = phone_detection_remote::getCacheRemotes('allremotes',array());

switch ($action) {
    case "update_device_status":
        log::add('phone_detection','info','Update device status from antenna ' . $results['source']);
        $source = $results['source'];
        $id     = $results['id'];
        $value  = $results['value'];
        log::add('phone_detection', 'debug', 'id: '.$id . ', value:' . $value);

        $eqLogic = eqLogic::byId($id);
        log::add('phone_detection','debug', 'Device Name: '. $eqLogic->getHumanName());
        if ($eqLogic->getConfiguration('deviceType') == 'phone' && $eqLogic->isEnabled()) {
            foreach ($remotes as $remote){
                if ($remote->getRemoteName() == $results['source']){
                    $remote->setCache('lastupdate', date("Y-m-d H:i:s"));
                    $statePropertyCmd = $eqLogic->getCmd(null, 'state_' . $results['source']);
                    if (!is_object($statePropertyCmd)) {
                        $statePropertyCmd = new phone_detection_Cmd();
                        $statePropertyCmd->setLogicalId('state_' . $results['source']);
                        $statePropertyCmd->setIsVisible(0);
                        $statePropertyCmd->setIsHistorized(0);
                        $statePropertyCmd->setName(__('state_ '. $results['source'], __FILE__));
                        $statePropertyCmd->setType('info');
                        $statePropertyCmd->setSubType('binary');
                        $statePropertyCmd->setTemplate('dashboard','line');
                        $statePropertyCmd->setTemplate('mobile','line');
                        $statePropertyCmd->setEqLogic_id($eqLogic->getId());
                        $statePropertyCmd->save();
                    }
                    log::add('phone_detection','debug', 'State property name: '. $statePropertyCmd->getHumanName());
                    $eqLogic->checkAndUpdateCmd($statePropertyCmd,$value);
                    $eqLogic->computePresence();
                    phone_detection::updateGlobalDevice();
                    break;
                }
            }
        }
        $success = true;
        break;

    case "test":
        log::add('phone_detection','info','Receive a test from antenna ' . $results['source']);
        if ($results['source'] != 'local'){
            foreach ($remotes as $remote){
                if ($remote->getRemoteName() == $results['source']){
                    $remote->setCache('lastupdate', date("Y-m-d H:i:s"));
                    break;
                }
            }
        }
        $value   = 0;
        $success = true;
        break;

    case "heartbeat":
        log::add('phone_detection','info','This is a heartbeat from antenna ' . $results['source']);
        if ($results['source'] != 'local'){
            foreach ($remotes as $remote){
                if ($remote->getRemoteName() == $results['source']){
                    $remote->setCache('lastupdate', date("Y-m-d H:i:s"));
                    break;
                }
            }
        }
        $success = true;
        $value = 0;
        break;

    case "get_status":
        log::add('phone_detection','info','Receive get_status for from antenna ' . $results['source']);
        $id = $results['id'];
        log::add('phone_detection', 'debug', 'id: '.$id);
        $eqLogic = eqLogic::byId($id);

        $values = Null;
        foreach ($remotes as $remote){
            if ($remote->getRemoteName() == $results['source']){
                $remote->setCache('lastupdate', date("Y-m-d H:i:s"));
                $statePropertyCmd = $eqLogic->getCmd(null, 'state_' . $results['source']);
                if (!is_object($statePropertyCmd)) {
                    $statePropertyCmd = new phone_detection_Cmd();
                    $statePropertyCmd->setLogicalId('state_' . $results['source']);
                    $statePropertyCmd->setIsVisible(0);
                    $statePropertyCmd->setIsHistorized(0);
                    $statePropertyCmd->setName(__('state_ '. $results['source'], __FILE__));
                    $statePropertyCmd->setType('info');
                    $statePropertyCmd->setSubType('binary');
                    $statePropertyCmd->setTemplate('dashboard','line');
                    $statePropertyCmd->setTemplate('mobile','line');
                    $statePropertyCmd->setEqLogic_id($eqLogic->getId());
                    $statePropertyCmd->save();
                }

                $statePropertyCmd = $eqLogic->getCmd('info', 'state');
                $value = $statePropertyCmd->execCmd();
                break;
            } 
        }
        $success = true;
        break;

    case "refresh_group":
        log::add('phone_detection','info','Receive refresh_group from antenna ' . $results['source']);
        phone_detection::updateGlobalDevice();
        $success = true;
        break;

    case "get_devices":
        log::add('phone_detection','info','Receive get_devices from antenna ' . $results['source']);
        phone_detection::updateGlobalDevice();
        $devices = eqLogic::byType("phone_detection", true);
        $values = Null;
        // $values["count"] = count($devices);
        // $values["devices"] = $devices;
        
        foreach($devices as $d) {
            if ($d->getConfiguration('deviceType') != 'phone' || $d->isEnable() == false) {
                continue;
            }

            foreach ($remotes as $remote){
                if ($remote->getRemoteName() == $results['source']){
                    $remote->setCache('lastupdate', date("Y-m-d H:i:s"));
                    $statePropertyCmd = $d->getCmd(null, 'state_' . $results['source']);
                    if (!is_object($statePropertyCmd)) {
                        $statePropertyCmd = new phone_detection_Cmd();
                        $statePropertyCmd->setLogicalId('state_' . $results['source']);
                        $statePropertyCmd->setIsVisible(0);
                        $statePropertyCmd->setIsHistorized(0);
                        $statePropertyCmd->setName(__('state_ '. $results['source'], __FILE__));
                        $statePropertyCmd->setType('info');
                        $statePropertyCmd->setSubType('binary');
                        $statePropertyCmd->setTemplate('dashboard','line');
                        $statePropertyCmd->setTemplate('mobile','line');
                        $statePropertyCmd->setEqLogic_id($d->getId());
                        $statePropertyCmd->save();
                    }

                    $statePropertyCmd = $d->getCmd('info', 'state_' . $results['source']));
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
                    break;
                }
            }
        } 
        $success = true;
        $value = $values;
        break;
}

$response = array('success' => $success, 'value' => $value);

echo json_encode($response);
?>
