<?php

require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";
require_once dirname(__FILE__) . "/../class/phone_detection_remote.class.php";

if (!jeedom::apiAccess(init('apikey'), 'phone_detection')) {
    echo __('Vous n\'êtes pas autorise a effectuer cette action', __FILE__);
    die();
}

$results  = json_decode(file_get_contents("php://input"), true);
$action   = $results['action'];
$value    = 0;
$antennas = phone_detection_remote::getCacheRemotes('allremotes', array());
if (config::byKey('noLocal', 'phone_detection', 0) == 0){
    $local = array('id'=>0,'remoteName'=>'local','configuration'=>array());
    array_push($antennas, $local);
}

switch ($action) {
    case "update_device_status":
        $source  = $results['source'];
        $id      = $results['id'];
        $value   = $results['value'];
        $eqLogic = eqLogic::byId($id);

        log::add('phone_detection','info','Update device status (' . $value . ') from antenna ' . $source . ' for ' . $eqLogic->getHumanName());
        if ($eqLogic->getConfiguration('deviceType') == 'phone' && $eqLogic->getIsEnable()) {
            foreach ($antennas as $antenna){
                if (method_exists($antenna, 'getRemoteName')) {
                    $from = $antenna->getRemoteName();
                } else {
                    $from = 'local';
                }
                if ($from == $source){
                    if (method_exists($antenna, 'setCache')) {
                        $antenna->setCache('lastupdate', date("Y-m-d H:i:s"));
                    }
                    $statePropertyCmd = $eqLogic->getCmd(null, 'state_' . $source);
                    if (!is_object($statePropertyCmd)) {
                        $statePropertyCmd = new phone_detectionCmd();
                        $statePropertyCmd->setLogicalId('state_' . $source);
                        $statePropertyCmd->setIsVisible(0);
                        $statePropertyCmd->setIsHistorized(0);
                        $statePropertyCmd->setName(__('Etat_'. $source, __FILE__));
                        $statePropertyCmd->setType('info');
                        $statePropertyCmd->setSubType('binary');
                        $statePropertyCmd->setTemplate('dashboard', 'line');
                        $statePropertyCmd->setTemplate('mobile', 'line');
                        $statePropertyCmd->setEqLogic_id($eqLogic->getId());
                        $statePropertyCmd->save();
                        $eqLogic->checkAndUpdateCmd($statePropertyCmd, 0);
                    }
                    $currentState = (int)($statePropertyCmd->execCmd() == 1);
                    log::add('phone_detection','info', 'Update value from ' . $currentState . ' to ' . $value . ' for ' . $statePropertyCmd->getHumanName());
                    $eqLogic->checkAndUpdateCmd($statePropertyCmd, $value);
                    $eqLogic->computePresence();
                    phone_detection::updateGlobalDevice();
                    break;
                }
            }
        }
        $success = true;
        break;

    case "test":
        $source  = $results['source'];

        log::add('phone_detection','info','Receive a test from antenna ' . $source);
        if ($source != 'local'){
            foreach ($antennas as $antenna){
                if ($antenna->getRemoteName() == $source){
                    $antenna->setCache('lastupdate', date("Y-m-d H:i:s"));
                    break;
                }
            }
        }
        $value   = 0;
        $success = true;
        break;

    case "heartbeat":
        $source  = $results['source'];
        $version = $results['version'];
        $alive   = $results['alive'];        
        log::add('phone_detection','debug','This is a heartbeat from antenna ' . $source . ' version=' . $version . ' alive=' . $alive);
        if ($source != 'local'){
            foreach ($antennas as $antenna){
                if ($antenna->getRemoteName() == $source){
                    $antenna->setCache('version', $version);
                    if ($alive == 0) {
                        log::add('phone_detection', 'error', 'Arret de l\'antenne ' . $antenna->getRemoteName() . ' because alive=' . $alive);
                        phone_detection::stopremote($antenna->getId());
                        message::add('phone_detection', 'Arret de l\'antenne ' . $antenna->getRemoteName() . ' suite a un probleme reporte par l\'antenne.');
                    } else {
                        $antenna->setCache('lastupdate', date("Y-m-d H:i:s"));
                    }
                    break;
                }
            }
        }
        $success = true;
        $value = 0;
        break;

    case "get_status":
        $source  = $results['source'];
        $id      = $results['id'];
        $eqLogic = eqLogic::byId($id);
        
        log::add('phone_detection','info','Receive get_status for ' . $eqLogic->getHumanName() . ' from antenna ' . $source);

        $values = Null;
        foreach ($antennas as $antenna){
            if (method_exists($antenna, 'getRemoteName')) {
                $from = $antenna->getRemoteName();
            } else {
                $from = 'local';
            }
            if ($from == $source){
                if (method_exists($antenna, 'setCache')) {
                   $antenna->setCache('lastupdate', date("Y-m-d H:i:s"));
                }
                $statePropertyCmd = $eqLogic->getCmd(null, 'state_' . $source);
                if (!is_object($statePropertyCmd)) {
                    $statePropertyCmd = new phone_detectionCmd();
                    $statePropertyCmd->setLogicalId('state_' . $source);
                    $statePropertyCmd->setIsVisible(0);
                    $statePropertyCmd->setIsHistorized(0);
                    $statePropertyCmd->setName(__('Etat_'. $source, __FILE__));
                    $statePropertyCmd->setType('info');
                    $statePropertyCmd->setSubType('binary');
                    $statePropertyCmd->setTemplate('dashboard', 'line');
                    $statePropertyCmd->setTemplate('mobile', 'line');
                    $statePropertyCmd->setEqLogic_id($eqLogic->getId());
                    $statePropertyCmd->save();
                    $eqLogic->checkAndUpdateCmd($statePropertyCmd, 0);
                }
                $value = (int) ($statePropertyCmd->execCmd() == 1);
                break;
            }
        }
        $success = true;
        break;

    case "refresh_group":
        $source  = $results['source'];

        log::add('phone_detection','info','Receive refresh_group from antenna ' . $source);
        phone_detection::updateGlobalDevice();
        $success = true;
        break;

    case "get_devices":
        $source  = $results['source'];

        log::add('phone_detection','info','Receive get_devices from antenna ' . $source);
        phone_detection::updateGlobalDevice();
        $devices = eqLogic::byType("phone_detection", true);
        $values = Null;

        foreach($devices as $d) {
            if ($d->getConfiguration('deviceType') != 'phone' || $d->getIsEnable() == false) {
                continue;
            }

            foreach ($antennas as $antenna){
                if (method_exists($antenna, 'getRemoteName')) {
                $from = $antenna->getRemoteName();
        } else {
                $from = 'local';
            }
                if ($from == $source){
                if (method_exists($antenna, 'setCache')) {
                       $antenna->setCache('lastupdate', date("Y-m-d H:i:s"));
                }
                    $statePropertyCmd = $d->getCmd(null, 'state_' . $source);
                    if (!is_object($statePropertyCmd)) {
                        $statePropertyCmd = new phone_detectionCmd();
                        $statePropertyCmd->setLogicalId('state_' . $source);
                        $statePropertyCmd->setIsVisible(0);
                        $statePropertyCmd->setIsHistorized(0);
                        $statePropertyCmd->setName(__('Etat_'. $source, __FILE__));
                        $statePropertyCmd->setType('info');
                        $statePropertyCmd->setSubType('binary');
                        $statePropertyCmd->setTemplate('dashboard', 'line');
                        $statePropertyCmd->setTemplate('mobile', 'line');
                        $statePropertyCmd->setEqLogic_id($d->getId());
                        $statePropertyCmd->save();
                        $d->checkAndUpdateCmd($statePropertyCmd, 0);
                    }
                    $stateValue   = (int)($statePropertyCmd->execCmd() == 1);
                    $getValueDate = $statePropertyCmd->getValueDate();
                    $name         = $d->getName();
                    $humanName    = $d->getHumanName();
                    $id           = $d->getId();
                    $macAddress   = $d->getConfiguration('macAddress');

                    $values[$id] = [
                        "state"         => $stateValue,
                        "lastValueDate" => $getValueDate,
                        "name"          => $name,
                        "humanName"     => $humanName,
                        "id"            => $id,
                        "macAddress"    => $macAddress
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
