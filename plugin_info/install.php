<?php

require_once dirname(__FILE__).'/../../../core/php/core.inc.php';

function phone_detection_install() {
    $sql = file_get_contents(dirname(__FILE__) . '/install.sql');
    DB::Prepare($sql, array(), DB::FETCH_TYPE_ROW);
    foreach (phone_detection::byType('phone_detection') as $phone_detection) {
        $phone_detection->save();
    }
    config::save('version',phone_detection::$_version,'phone_detection');
}

function phone_detection_update() {
    log::add('phone_detection', 'debug', 'Phone_detection_update');
    $daemonInfo = phone_detection::deamon_info();

    if ($daemonInfo['state'] == 'ok') {
        phone_detection::deamon_stop();
    }

    $cache = cache::byKey('dependancy' . 'phone_detection');
    $cache->remove();


    $sql = file_get_contents(dirname(__FILE__) . '/install.sql');
    DB::Prepare($sql, array(), DB::FETCH_TYPE_ROW);
    foreach (phone_detection::byType('phone_detection') as $phone_detection) {
        $phone_detection->save();
    }
    message::add('phone_detection','Pensez �|  mettre �|  jour vos antennes et relancer leurs dépendances si besoin ...'))
;
    config::save('version',phone_detection::$_version,'phone_detection');
    if (config::byKey('allowUpdateAntennas','phone_detection',0) == 1) {
        log::add('blea','info','Mise a jour des fichiers de toutes les antennes');
        blea::send_allremotes();
    }

    phone_detection::dependancy_install();

    message::removeAll('Phone_detection');
    message::add('Phone_detection', '{{Mise à jour du plugin Phone_detection terminée, vous êtes en version }}' . phone_detection::getVersion() . '.', null, null);

    phone_detection::deamon_start();

}

function phone_detection_remove() {
   DB::Prepare('DROP TABLE IF EXISTS `phone_detection_remote`', array(), DB::FETCH_TYPE_ROW);
}

