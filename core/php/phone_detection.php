<?php

require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";

if (!jeedom::apiAccess(init('apikey'), 'phone_detection')) {
    echo __('Vous n\'êtes pas autorisé à effectuer cette action', __FILE__);
    die();
}

$results = json_decode(file_get_contents("php://input"), true);
// $response = array('success' => false);
$action = $results['action'];

switch ($action) {
    case "update_device_status":
        $id = $results['id'];
        $value = $results['value'];
        log::add('phone_detection', 'debug', 'id: '.$id);
        log::add('phone_detection', 'debug', 'value: '.$value);

        $eqLogic = eqLogic::byId($id);
        log::add('phone_detection','debug', 'Device Name: '. $eqLogic->getHumanName());
        $stateProperty = $eqLogic->getCmd('info', 'state');
        log::add('phone_detection','debug', 'State property name: '. $stateProperty->getHumanName());

        $stateProperty->event($value);
        $success = true;
        break;

    case "test":
        $success = true;
        break;
}

$response = array('success' => $success);

echo json_encode($response);
?>