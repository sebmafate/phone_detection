<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

try {
    require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
    require_once dirname(__FILE__) . '/../class/phone_detection_remote.class.php';

    include_file('core', 'authentification', 'php');

    if (!isConnect('admin')) {
        throw new Exception('401 Unauthorized');
    }

    ajax::init();

    /*
    if (init('action') == 'allantennas') {
        if (init('remote') == 'local') {
            if (init('type') == 'reception'){
                foreach (eqLogic::byType('phone_detection') as $eqLogic){
                    $eqLogic->setConfiguration('antennareceive','local');
                    $eqLogic->save();
                }
            } else {
                foreach (eqLogic::byType('phone_detection') as $eqLogic){
                    $eqLogic->setConfiguration('antenna','local');
                    $eqLogic->save();
                }
            }
        } else {
            if (init('type') == 'reception'){
                foreach (eqLogic::byType('phone_detection') as $eqLogic){
                    $eqLogic->setConfiguration('antennareceive',init('remoteId'));
                    $eqLogic->save();
                }
            } else {
                foreach (eqLogic::byType('phone_detection') as $eqLogic){
                    $eqLogic->setConfiguration('antenna',init('remoteId'));
                    $eqLogic->save();
                }
            }
        }
        ajax::success();
    }
    
    if (init('action') == 'syncconfPhoneDetection') {
        phone_detection::syncconfPhoneDetection(false);
        ajax::success();
    }

    if (init('action') == 'getMobileGraph') {
        ajax::success(phone_detection::getMobileGraph());
    }

    if (init('action') == 'getMobileHealth') {
        ajax::success(phone_detection::getMobileHealth());
    }

    if (init('action') == 'saveAntennaPosition') {
        ajax::success(phone_detection::saveAntennaPosition(init('antennas')));
    }
     */
    
    if (init('action') == 'launchremotes') {
        ajax::success(phone_detection::launch_allremotes());
    }
    
    if (init('action') == 'sendremotes') {
        ajax::success(phone_detection::send_allremotes());
    }
    
    if (init('action') == 'updateremotes') {
        ajax::success(phone_detection::update_allremotes());
    }
    
    if (init('action') == 'stopremotes') {
        ajax::success(phone_detection::stop_allremotes());
    }

    if (init('action') == 'save_PhoneDetectionRemote') {
        $phone_detectionRemoteSave = jeedom::fromHumanReadable(json_decode(init('phone_detection_remote'), true));
        $phone_detection_remote = phone_detection_remote::byId($phone_detectionRemoteSave['id']);
        if (!is_object($phone_detection_remote)) {
            $phone_detection_remote = new phone_detection_remote();
        }
        utils::a2o($phone_detection_remote, $phone_detectionRemoteSave);
        $phone_detection_remote->save();
        ajax::success(utils::o2a($phone_detection_remote));
    }

    if (init('action') == 'get_PhoneDetectionRemote') {
        //log::add('phone_detection', 'debug', 'BR>> get_PhoneDetectionRemote = ' . init('id'));
        $phone_detection_remote = phone_detection_remote::byId(init('id'));
        if (!is_object($phone_detection_remote)) {
            throw new Exception(__('Remote inconnu : ', __FILE__) . init('id'), 9999);
        }
        //log::add('phone_detection', 'debug', 'BR>> get_PhoneDetectionRemote = return ' . jeedom::toHumanReadable(utils::o2a($phone_detection_remote)));
        ajax::success(jeedom::toHumanReadable(utils::o2a($phone_detection_remote)));
    }

    if (init('action') == 'remove_PhoneDetectionRemote') {
        $phone_detection_remote = phone_detection_remote::byId(init('id'));
        if (!is_object($phone_detection_remote)) {
            throw new Exception(__('Remote inconnu : ', __FILE__) . init('id'), 9999);
        }
        $phone_detection_remote->remove();
        ajax::success();
    }

    if (init('action') == 'sendRemoteFiles') {
        if (!phone_detection::sendRemoteFiles(init('remoteId'))) {
            ajax::error(__('Erreur, vérifiez la logphone_detection', __FILE__));
        }
        ajax::success();
    }

    if (init('action') == 'getRemoteLog') {
        if (!phone_detection::getRemoteLog(init('remoteId'))) {
            ajax::error(__('Erreur, vérifiez la log phone_detection', __FILE__));
        }
        ajax::success();
     }

     if (init('action') == 'getRemoteLogDependancy') {
        if (!phone_detection::getRemoteLog(init('remoteId'),'_dependancy')) {
            ajax::error(__('Erreur, vérifiez la log phone_detection', __FILE__));
        }
        ajax::success();
     }

     if (init('action') == 'launchremote') {
        if (!phone_detection::launchremote(init('remoteId'))) {
            ajax::error(__('Erreur, vérifiez la log phone_detection', __FILE__));
        }
        ajax::success();
     }

     if (init('action') == 'stopremote') {
        if (!phone_detection::stopremote(init('remoteId'))) {
            ajax::error(__('Erreur, vérifiez la log phone_detection', __FILE__));
        }
        ajax::success();
     }

     if (init('action') == 'remotelearn') {
        ajax::success(phone_detection::remotelearn(init('remoteId'), init('state')));
     }

     if (init('action') == 'dependancyRemote') {
        ajax::success(phone_detection::dependancyRemote(init('remoteId')));
     }

     if (init('action') == 'aliveremote') {
        ajax::success(phone_detection::aliveremote(init('remoteId')));
     }

    if (init('action') == 'changeLogLive') {
        ajax::success(phone_detection::changeLogLive(init('level')));
    }

    throw new Exception('Aucune methode correspondante');
    /*     * *********Catch exeption*************** */
} catch (Exception $e) {
    //log::add('phone_detection', 'error', 'EXCEPTION ' . $e->getMessage . '(' . $e->getCode() . ')');
    ajax::error(displayException($e), $e->getCode());
}
?>
