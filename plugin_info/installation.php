<?php

require_once dirname(__FILE__).'/../../../core/php/core.inc.php';

function phone_detection_install() {
}

function phone_detection_update() {
    log::add('phone_detection', 'debug', 'Phone_detection_update');
    $daemonInfo = phone_detection::deamon_info();

    if ($daemonInfo['state'] == 'ok') {
        phone_detection::deamon_stop();
    }

    $cache = cache::byKey('dependancy' . 'phone_detection');
    $cache->remove();

    phone_detection::dependancy_install();

    message::removeAll('Phone_detection');
    message::add('Phone_detection', '{{Mise à jour du plugin Phone_detection terminée, vous êtes en version }}' . phone_detection::getVersion() . '.', null, null);

    phone_detection::deamon_start();

}

function phone_detection_remove() {
}

