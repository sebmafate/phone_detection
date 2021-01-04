<?php

require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
require_once dirname(__FILE__) . '/phone_detection_remote.class.php';

class phone_detection extends eqLogic
{

    /*************** Attributs ***************/
    

    /************* Static methods ************/

    /**
     * Call the call Python daemon.
     *
     * @param  string $action Action calling.
     * @param  string $args   Other arguments.
     * @return array  Result of the callZiGate.
     */
    public static function callDeamon($action, $args = '')
    {
        log::add('phone_detection', 'debug', 'callDeamon ' . print_r($action, true) . ' ' .print_r($args, true));
        $apikey = jeedom::getApiKey('phone_detection');
        $sock = 'unix://' . jeedom::getTmpFolder('phone_detection') . '/daemon.sock';
        $fp = stream_socket_client($sock, $errno, $errstr);
        $result = '';

        log::add('phone_detection', 'debug', 'error ' . $errno .' : '. $errstr);

        if ($fp) {
            $query = [
                'action' => $action,
                'args' => $args,
                'apikey' => $apikey
            ];
            try {
                fwrite($fp, json_encode($query));
                while (!feof($fp)) {
                    $result .= fgets($fp, 1024);
                }
            } catch( Exception $ex) {
                log::add('phone_detection', 'info', print_r($ex));
            } finally {
                fclose($fp);
            }
        }
        $result = (is_json($result)) ? json_decode($result, true) : $result;
        log::add('phone_detection', 'debug', 'result callDeamon '.print_r($result, true));

        return $result;
    }

    public static function updateGlobalDevice() {
        log::add('phone_detection', 'info', 'updateGlobalDevice()');

        $devices = eqLogic::byType("phone_detection", true);
        $deviceCount = 0;
        // $values["count"] = count($devices);
        // $values["devices"] = $devices;
        
        foreach($devices as $d) {
            if ($d->getConfiguration('deviceType') != 'phone') {
                continue;
            }

            $statePropertyCmd = $d->getCmd('info', 'state');
            $stateValue = $statePropertyCmd->execCmd();
            $deviceCount += $stateValue;
        }

        $globalDevice = self::byLogicalId('GlobalGroup', 'phone_detection');
        $stateCmd = $globalDevice->getCmd('info', 'state');
        $stateCmd->event($deviceCount > 0 ? 1 : 0);
        $stateCmd->save();

        $deviceCountCmd = $globalDevice->getCmd('info', 'count');
        $deviceCountCmd->event($deviceCount);
        $deviceCountCmd->save();
    }

    //
    // Gestion des antennes distantes, base sur le plugin BLEA
    //
    public static function sendRemoteFiles($_remoteId) {
        phone_detection::stopremote($_remoteId);
        $remoteObject = phone_detection_remote::byId($_remoteId);
        $user=$remoteObject->getConfiguration('remoteUser');
        $script_path = dirname(__FILE__) . '/../../resources/';
        log::add('phone_detection','info','Compression du dossier local');
        exec('tar -zcvf /tmp/folder-phone_detection.tar.gz ' . $script_path);
        log::add('phone_detection','info','Envoie du fichier  /tmp/folder-phone_detection.tar.gz');
        $result = false;
        $result = $remoteObject->execCmd(['rm -Rf /home/'.$user.'/phone_detectiond','mkdir -p /home/'.$user.'/phone_detectiond']);
        if ($remoteObject->sendFiles('/tmp/folder-phone_detection.tar.gz','/home/'.$user.'/folder-phone_detection.tar.gz')) {
            log::add('phone_detection','info',__('Décompression du dossier distant',__FILE__));
            $result = $remoteObject->execCmd(['tar -zxf /home/'.$user.'/folder-phone_detection.tar.gz -C /home/'.$user.'/phone_detectiond','rm -f /home/'.$user.'/folder-phone_detection.tar.gz']);
        }
        log::add('phone_detection','info',__('Suppression du zip local',__FILE__));
        exec('rm -f /tmp/folder-phone_detection.tar.gz');
        log::add('phone_detection','info',__('Finie',__FILE__));
        return $result;
    }

    public static function getRemoteLog($_remoteId,$_dependancy='') {
        $remoteObject = phone_detection_remote::byId($_remoteId);
        $name = $remoteObject->getRemoteName();
        $local = dirname(__FILE__) . '/../../../../log/phone_detection_'.str_replace(' ','-',$name).$_dependancy;
        log::add('phone_detection','info','Suppression de la log ' . $local);
        exec('rm -f '. $local);
        log::add('phone_detection','info',__('Recuperation de la log distante sur '.$name,__FILE__));
        if ($remoteObject->getFiles($local,'/tmp/phone_detection'.$_dependancy)) {
            $remoteObject->execCmd(['cat /dev/null > /tmp/phone_detection'.$_dependancy]);
            return true;
        }
        return false;
    }

    public static function dependancyRemote($_remoteId) {
        log::add('phone_detection', 'debug', 'entering dependancyRemote');
        phone_detection::stopremote($_remoteId);
        log::add('phone_detection', 'debug', 'remote stopped');
        $remoteObject = phone_detection_remote::byId($_remoteId);
        $user = $remoteObject->getConfiguration('remoteUser');
        log::add('phone_detection','info',__('Installation des dependances sur ' . $remoteObject->getRemoteName(),__FILE__));
        return $remoteObject->execCmd(['bash /home/'.$user.'/phone_detectiond/resources/install_apt.sh /tmp/phone_detection_dependancy 2>&1 &']);
    }

    public static function launchremote($_remoteId) {
        log::add('phone_detection','info',__('Lancement du démon distant',__FILE__));
        $remoteObject = phone_detection_remote::byId($_remoteId);
        $last = $remoteObject->getCache('lastupdate','0');
        phone_detection::stopremote($_remoteId);
        sleep(5);
        $user=$remoteObject->getConfiguration('remoteUser');
        $device=$remoteObject->getConfiguration('remoteDevice');
        $script_path = '/home/'.$user.'/phone_detectiond/resources/phone_detectiond';
        $cmd = '/usr/bin/python3 ' . $script_path . '/phone_detectiond.py';
        $cmd .= ' --loglevel ' . log::convertLogLevel(log::getLogLevel('phone_detection'));
        $cmd .= ' --device ' . $device;
        $cmd .= ' --socketport ' . config::byKey('socketport', 'phone_detection');
        $cmd .= ' --sockethost ""';
        $cmd .= ' --callback ' . network::getNetworkAccess('internal') . '/plugins/phone_detection/core/php/phone_detection.php';
        $cmd .= ' --apikey ' . jeedom::getApiKey('phone_detection');
        $cmd .= ' --daemonname "' . $remoteObject->getRemoteName() . '"';
        $cmd .= ' --interval ' . $interval;
        $cmd .= ' --present_interval ' . $present_interval;
        $cmd .= ' --absentThreshold ' . $absentThreshold;
        $cmd .= ' >> ' . '/tmp/phone_detection' . ' 2>&1 &';
        log::add('phone_detection','info','Lancement du démon distant ' . $cmd);
        phone_detection_remote::setCacheRemotes('allremotes',phone_detection_remote::all());
        return $remoteObject->execCmd([$cmd]);
    }

    public static function stopremote($_remoteId) {
        log::add('phone_detection','info',__('Arret du demon distant ' . $_remoteId,__FILE__));
        $remoteObject = phone_detection_remote::byId($_remoteId);
        $ip = $remoteObject->getConfiguration('remoteIp');
        $value = array('apikey' => jeedom::getApiKey('phone_detection'), 'cmd' => 'stop');
        $value = json_encode($value);
        $socket = socket_create(AF_INET, SOCK_STREAM, 0);
        $port   = config::byKey('socketport', 'phone_detection', 55009);
        if ($socket) {
            if (socket_connect($socket, $ip, $port)) {
                socket_write($socket, $value, strlen($value));
                socket_close($socket);
            }
        }
        $remoteObject->execCmd(['fuser -k ' . $port . '/tcp >> /dev/null 2>&1 &']);
        return True;
    }


    /**************** Methods ****************/
    /**
     * Return plugin version.
     *
     * @return string Version of the plugin.
     */
    public static function getVersion()
    {
        $pluginVersion = 'Error';
        if (!file_exists(dirname(__FILE__) . '/../../plugin_info/info.json')) {
            log::add('phone_detection', 'warning', 'Pas de fichier info.json');
        }
        $data = json_decode(file_get_contents(dirname(__FILE__) . '/../../plugin_info/info.json'), true);
        if (!is_array($data)) {
            log::add('phone_detection', 'warning', 'Impossible de décoder le fichier info.json');
        }
        try {
            $pluginVersion = $data['version'];
        } catch (\Exception $e) {
            log::add('phone_detection', 'warning', 'Impossible de récupérer la version.');
        }
        return $pluginVersion;
    }

    /**
     * Get lib dependancy information.
     *
     * @return array Python3 command return.
     */
    public static function dependancy_info()
    {
        $return = [
            'state' => 'nok',
            'log' => 'phone_detection_update',
            'progress_file' => jeedom::getTmpFolder('phone_detection') . '/dependance'
        ];

        $return['state'] = 'ok';

        return $return;
    }

    /**
     * Return information (status) about daemon.
     *
     * @return array Shell command return.
     */
    public static function deamon_info()
    {
        $return = array(); 
        $return['log'] = 'phone_detection';
        $return['state'] = 'nok';
        if (config::byKey('noLocal', 'phone_detection', 0) == 1){
            $return['state'] = 'ok'; 
            $return['launchable'] = 'ok';
            return $return;
        }
        $pid_file = jeedom::getTmpFolder('phone_detection') . '/daemon.pid';
        if (file_exists($pid_file)) {
            if (posix_getsid(trim(file_get_contents($pid_file)))) {
                $return['state'] = 'ok';
            } else {
                shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file . ' 2>&1 > /dev/null');
            }
        }
        $return['launchable'] = 'ok';
        
        $btport = config::byKey('btport', 'phone_detection');
        $interval = config::byKey('interval', 'phone_detection', 10);
        $present_interval = config::byKey('present_interval', 'phone_detection', 30);
        $absentThreshold = config::byKey('absentThreshold', 'phone_detection', 180);
        $port = config::byKey('port', 'phone_detection', 55009);

        if (phone_detection::dependancy_info()['state'] == 'nok') {
            $cache = cache::byKey('dependancy' . 'phone_detection');
            $cache->remove();
            $return['launchable'] = 'nok';
            $return['launchable_message'] = __('Veuillez (ré-)installer les dépendances', __FILE__);
            return $return;
        } 

        if ($btport == "none" || $btport == "" || empty($btport)) {
            $return['launchable'] = 'nok';
            $return['launchable_message'] = __('Veuillez sélectionner un contrôleur bluetooth', __FILE__);
            return $return;
        }

        if($interval == 0 || empty($interval)) {
            $return['launchable'] = 'nok';
            $return['launchable_message'] = _('Veuillez reseigner un interval de mise à jour en absence supérieur à 0', __FILE__);
        }

        if($present_interval == 0 || empty($present_interval)) {
            $return['launchable'] = 'nok';
            $return['launchable_message'] = _('Veuillez reseigner un interval de mise à jour en présence supérieur à 0', __FILE__);
        }

        if($absentThreshold == 0 || empty($absentThreshold)) {
            $return['launchable'] = 'nok';
            $return['launchable_message'] = _('Veuillez reseigner un délai d\'absence supérieur à 0', __FILE__);
        }

        if($port == 0 || empty($port)) {
            $return['launchable'] = 'nok';
            $return['launchable_message'] = _('Veuillez renseigner un port (default 55009)', __FILE__);
        }


        return $return;
    }


    /**
     * Start python daemon.
     *
     * @return array Shell command return.
     */
    public static function deamon_start($_debug = false)
    {
        self::deamon_stop();
        $deamon_info = self::deamon_info();
        if ($deamon_info['launchable'] != 'ok') {
            throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
        }
        $btport = config::byKey('btport', 'phone_detection');
        $deamon_path = dirname(__FILE__) . '/../../resources';
        $interval = config::byKey('interval', 'phone_detection', 10);
        $present_interval = config::byKey('present_interval', 'phone_detection', 30);
        $absentThreshold = config::byKey('absentThreshold', 'phone_detection', 180);
        $callback = network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/phone_detection/core/php/phone_detection.php';

        $cmd = '/usr/bin/python3 ' . $deamon_path . '/phone_detectiond/phone_detectiond.py ';
        $cmd .= ' --device ' . $btport;
        $cmd .= ' --loglevel ' . log::convertLogLevel(log::getLogLevel('phone_detection'));
        $cmd .= ' --apikey ' . jeedom::getApiKey('phone_detection');
        $cmd .= ' --pidfile ' . jeedom::getTmpFolder('phone_detection') . '/daemon.pid';
        $cmd .= ' --socket ' . jeedom::getTmpFolder('phone_detection') . '/daemon.sock';
        $cmd .= ' --callback ' . $callback;
        $cmd .= ' --daemonname "local"';
        $cmd .= ' --interval ' . $interval;
        $cmd .= ' --present_interval ' . $present_interval;
        $cmd .= ' --absentThreshold ' . $absentThreshold;
        
        log::add('phone_detection', 'info', 'Lancement démon phone_detection : ' . $cmd);
        exec($cmd . ' >> ' . log::getPathToLog('phone_detection') . ' 2>&1 &');
        $i = 0;
        while ($i < 5) {
            $deamon_info = self::deamon_info();
            if ($deamon_info['state'] == 'ok') {
                break;
            }
            sleep(1);
            $i++;
        }

        if ($i >= 5) {
            log::add('phone_detection', 'error', 'Impossible de lancer le démon phone_detection, relancer le démon en debug et vérifiez la log', 'unableStartDeamon');
            return false;
        }


        // demarrage des demons distants
        //
        phone_detection_remote::setCacheRemotes('allremotes',phone_detection_remote::all());
        phone_detection::launch_allremotes();
        message::removeAll('phone_detection', 'unableStartDeamon');
        log::add('phone_detection', 'info', 'Démon phone_detection lancé');
        return true;
    }

    public function launch_allremotes(){
        log::add('phone_detection','info','Launching remotes ...');
        $remotes = phone_detection_remote::all();
        foreach ($remotes as $remote) {
            phone_detection::launchremote($remote->getId());
            sleep(1);
        }
    }

    public function update_allremotes(){
        log::add('phone_detection','info','Updating remotes ...');
        $remotes = phone_detection_remote::all();
        foreach ($remotes as $remote) {
            phone_detection::dependancyRemote($remote->getId());
            phone_detection::launchremote($remote->getId());
        }
    }

    public function send_allremotes(){
        log::add('phone_detection','info','Updating files on remotes ...');
        $remotes = phone_detection_remote::all();
        foreach ($remotes as $remote) {
            phone_detection::sendRemoteFiles($remote->getId());
            phone_detection::launchremote($remote->getId());
        }
    }


    public function stop_allremotes(){
        log::add('phone_detection','info','Stopping remotes ...');
        $remotes = phone_detection_remote::all();
        foreach ($remotes as $remote) {
            phone_detection::stopremote($remote->getId());
        }
    }

    public static function health() {
        $return = array(); 
        $remotes = phone_detection_remote::getCacheRemotes('allremotes',array());
        if (count($remotes) !=0){
            $return[] = array(
                'test' => __('Nombre d\'antennes', __FILE__),
                'result' => count($remotes),
                'advice' =>  '',
                'state' => True,
            );
            foreach ($remotes as $remote){
                $last = $remote->getCache('lastupdate','0');
                $name = $remote->getRemoteName();
                if ($last == '0' or time() - strtotime($last)>60){
                    $result = 'NOK';
                    $advice = __('Vérifier le démon sur votre antenne',__FILE__);
                    $state = False;
                } else {
                    $result = 'OK';
                    $advice = '';
                    $state = True;
                }
                $return[] = array(
                    'test' => __('Démon ' . $name, __FILE__),
                    'result' => $result,
                    'advice' =>  $advice,
                    'state' =>$state,
                );
            }
        }
        return $return;
    }

    public static function cron() {
        $remotes = phone_detection_remote::getCacheRemotes('allremotes',array());
        $allEqlogic = eqLogic::byType('phone_detection');
        foreach ($remotes as $remote) {
            $last = $remote->getCache('lastupdate','0');
            if (($last == '0' or time() - strtotime($last)>65)) {
                $auto = $remote->getConfiguration('remoteDaemonAuto','0');
                //foreach ($allEqlogic as $eqLogic){
                    //$rssicmd = $eqLogic->getCmd(null, 'rssi' . $remote->getRemoteName());
                    //$presentcmd = $eqLogic->getCmd(null, 'present' . $remote->getRemoteName());
                    //$eqLogic->checkAndUpdateCmd($presentcmd, 0);
                    //$eqLogic->checkAndUpdateCmd($rssicmd, -200);
                    //$eqLogic->setCache('rssi' . $remote->getRemoteName(),-200);
                    //$eqLogic->computePresence();
                //}
                if ($auto == 1){
                    log::add('phone_detection','info','Restarting daemon on remote ' . $remote->getRemoteName());
                    phone_detection::launchremote($remote->getId());
                }
            }
        }
        $deamon_info = self::deamon_info();
        if ($deamon_info['state'] != 'ok'){
            //foreach ($allEqlogic as $eqLogic){
            //    $rssicmd = $eqLogic->getCmd(null, 'rssilocal');
            //    $presentcmd = $eqLogic->getCmd(null, 'presentlocal');
            //    $eqLogic->checkAndUpdateCmd($presentcmd, 0);
            //    $eqLogic->checkAndUpdateCmd($rssicmd, -200);
            //    $eqLogic->setCache('rssilocal',-200);
            //    $eqLogic->computePresence();
            //}
        }
    }

    public static function cron15() {
        $remotes = phone_detection_remote::getCacheRemotes('allremotes',array());
        $availremote= array();
        foreach ($remotes as $remote) {
            log::add('phone_detection', 'debug', 'cacheRemotes: ' . $remote); 
            self::getRemoteLog($remote->getId());
            $availremote[] = $remote->getRemoteName();
        }
        foreach (eqLogic::byType('phone_detection') as $eqLogic){
            foreach ($eqLogic->getCmd('info') as $cmd) {
                $logicalId = $cmd->getLogicalId();
                if (substr($logicalId,0,4) == 'rssi'){
                    $remotename= substr($logicalId,4);
                    if ($remotename != 'local' && $remotename != 'local' && !(in_array($remotename,$availremote))){
                        $cmd->remove();
                    } else if ($remotename == 'local') {
                        if (config::byKey('noLocal', 'phone_detection', 0) == 1){
                            $cmd->remove();
                        }
                    }
                } else if (substr($logicalId,0,7) == 'present' && $logicalId!= 'present') {
                    $remotename= substr($logicalId,7);
                    if ($remotename != 'local' && !(in_array($remotename,$availremote))){
                        $cmd->remove();
                    } else if ($remotename == 'local') {
                        if (config::byKey('noLocal', 'phone_detection', 0) == 1){
                            $cmd->remove();
                        }
                    }
                }
            }
        }
    }

    public static function socket_connection($_value,$_allremotes = False) {
        if (config::byKey('port', 'phone_detection', 'none') != 'none') {
            $socket = socket_create(AF_INET, SOCK_STREAM, 0);
            socket_connect($socket, '127.0.0.1', config::byKey('socketport', 'phone_detection'));
            socket_write($socket, $_value, strlen($_value));
            socket_close($socket);
        }
        if ($_allremotes){
            $remotes = phone_detection_remote::getCacheRemotes('allremotes',array());
            foreach ($remotes as $remote) {
                $ip = $remote->getConfiguration('remoteIp');
                $last = $remote->getCache('lastupdate','0');
                if ($last == '0' or time() - strtotime($last)>65){
                    continue;
                } else {
                    $socket = socket_create(AF_INET, SOCK_STREAM, 0);
                    socket_connect($socket, $ip, config::byKey('socketport', 'phone_detection'));
                    socket_write($socket, $_value, strlen($_value));
                    socket_close($socket);
                }
            }
        }
    }

    public static function changeLogLive($_level) {
        $value = array('apikey' => jeedom::getApiKey('phone_detection'), 'cmd' => $_level);
        $value = json_encode($value);
        self::socket_connection($value,True);
    }



    public static function sendIdToDeamon() {
        foreach (self::byType('phone_detection') as $eqLogic) {
            $eqLogic->allowDevice();
            usleep(500);
        }
        $value = json_encode(array('apikey' => jeedom::getApiKey('phone_detection'), 'cmd' => 'ready'));
        log::add('phone_detection', 'info', 'Sending ready to daemons');
        self::socket_connection($value,True);
    }

    /// END REMOTE ANTENNAS

    /**
     * Stop python daemon.
     *
     * @return array Shell command return.
     */
    public static function deamon_stop()
    {
        $deamon_info = self::deamon_info();
        $pid_file = jeedom::getTmpFolder('phone_detection') . '/daemon.pid';
        if (file_exists($pid_file)) {
            $pid = intval(trim(file_get_contents($pid_file)));
            system::kill($pid);
        }
        $i = 0;
        while ($i < 5) {
            $deamon_info = self::deamon_info();
            if ($deamon_info['state'] == 'nok') {
                break;
            }
            sleep(1);
            $i++;
        }
        if ($i >= 5) {
            log::add('phone_detection', 'error', 'Impossible d\'arrêter le démon phone_detection, tuons-le');
            system::kill('phone_detectiond.py');
        }
    }



    /**
     * Install dependancies.
     *
     * @return array Shell script command return.
     */
    public static function dependancy_install()
    {
        log::remove(__CLASS__ . '_update');
        return [
            'script' => dirname(__FILE__) . '/../../resources/install_#stype#.sh ' . jeedom::getTmpFolder('phone_detection') . '/dependance',
            'log' => log::getPathToLog(__CLASS__ . '_update')
        ];
    }

    public function postInsert() {
        log::add('phone_detection', 'debug', 'postInsert()');
        if( $this->getConfiguration('deviceType') == 'phone') {
            phone_detection::callDeamon('insert_device',
                [
                    $this->getId(),
                    $this->getName(),
                    $this->getConfiguration('macAddress')
                ]
            );
        }
    }

    public function preRemove() {
        log::add('phone_detection', 'debug', 'preRemove()');
        if( $this->getConfiguration('deviceType') == 'phone') {
            phone_detection::callDeamon('remove_device', 
                [
                    $this->getId(),
                    $this->getName(),
                    $this->getConfiguration('macAddress')
                ]
            );
        }
    }

    public function postUpdate()
    {
        log::add('phone_detection', 'debug', 'postUpdate()');

        if (empty($this->getConfiguration('deviceType'))) {
            log::add('phone_detection', 'info', 'deviceType must be set to phone');
            $this->setConfiguration('deviceType', 'phone');
            $this->save();
        }

        $deviceType = $this->getConfiguration('deviceType');

        if ($deviceType == 'phone') {

            $getDataCmd = $this->getCmd(null, 'state');
            if (!is_object($getDataCmd)) {
                // Création de la commande
                $cmd = new phone_detectionCmd();
                // Nom affiché
                $cmd->setName('Etat');
                // Identifiant de la commande
                $cmd->setLogicalId('state');
                // Identifiant de l'équipement
                $cmd->setEqLogic_id($this->getId());
                // Type de la commande
                $cmd->setType('info');
                // Sous-type de la commande
                $cmd->setSubType('binary');
                // Visibilité de la commande
                $cmd->setIsVisible(1);
                // Sauvegarde de la commande
                $cmd->save();
            }
            $getDataCmd = $this->getCmd(null, 'refresh');
            if (!is_object($getDataCmd)) {
                // Création de la commande
                $cmd = new phone_detectionCmd();
                // Nom affiché
                $cmd->setName('Rafraichir');
                // Identifiant de la commande
                $cmd->setLogicalId('refresh');
                // Identifiant de l'équipement
                $cmd->setEqLogic_id($this->getId());
                // Type de la commande
                $cmd->setType('action');
                // Sous-type de la commande
                $cmd->setSubType('other');
                // Visibilité de la commande
                $cmd->setIsVisible(1);
                // Sauvegarde de la commande
                $cmd->save();
            }

            if ($this->getIsEnable()) {
                phone_detection::callDeamon('update_device', 
                    [
                        $this->getId(),
                        $this->getName(),
                        $this->getConfiguration('macAddress')
                    ]
                );
            } else {
                phone_detection::callDeamon('remove_device', 
                [
                    $this->getId(),
                    $this->getName(),
                    $this->getConfiguration('macAddress')
                ]
            );
            }
        }

        if ($deviceType == 'GlobalGroup') {
            $getRefreshCmd = $this->getCmd(null, 'refresh');
            if (is_object($getRefreshCmd)) {
                $getRefreshCmd->remove();
            }
        }

        self::createGlobalGroup();
    }

    private static function createGlobalGroup() {
        // $test = self::all();
        // // log::add('phone_detection', 'debug', get_class($test));
        // foreach($test as $t) {
        //     log::add('phone_detection', 'debug', '-----------------');
        //     // log::add('phone_detection', 'debug', /*$t.getName() . '\t' .*/ $t.getId()); // . '\t' . $t.getEqType_name());
        //     log::add('phone_detection', 'debug', get_class($t) . '\t' . $t->getName() . ' [' . $t->getId() . '] ' . $t->getEqType_name());
        // }

        // $t = self::byLogicalId('GlobalGroup', 'phone_detection');
        // log::add('phone_detection', 'debug', get_class($t) . '\t' . $t->getName() . ' [' . $t->getId() . '] ' . $t->getEqType_name());


        // // log::add('phone_detection','debug', print_r($test));
        // return;

        if (is_object(self::byLogicalId('GlobalGroup', 'phone_detection'))) {
            return;
        }

        try {
            log::add('phone_detection', 'debug', 'create Global Group device');

            $group = new self();
            $group->setLogicalId('GlobalGroup');

            log::add('phone_detection', 'debug', '\t--> set Name');
            $group->setName('Tous les téléphones');

            log::add('phone_detection', 'debug', '\t--> set eqTypeName');
            $group->setEqType_name('phone_detection');

            log::add('phone_detection', 'debug', '\t--> set deviceType');
            $group->setConfiguration('deviceType', 'GlobalGroup');

            log::add('phone_detection', 'debug', '\t--> set visible = 0');
            $group->setIsVisible(0);

            log::add('phone_detection', 'debug', '\t--> set enable = 1');
            $group->setIsEnable(1);

            log::add('phone_detection', 'debug', '\t--> set category ');
            $group->setConfiguration('category', 'group');

            log::add('phone_detection', 'debug', '\t--> set group id');
            $group->setConfiguration('id', 0);

            $group->save();
            $group = self::byLogicalId('GlobalGroup', 'phone_detection');

            $getDataCmd = $group->getCmd(null, 'state');
            if (!is_object($getDataCmd)) {
                // Création de la commande
                $cmd = new phone_detectionCmd();
                // Nom affiché
                $cmd->setName('Etat');
                // Identifiant de la commande
                $cmd->setLogicalId('state');
                // Identifiant de l'équipement
                $cmd->setEqLogic_id($group->getId());
                // Type de la commande
                $cmd->setType('info');
                // Sous-type de la commande
                $cmd->setSubType('binary');
                // Visibilité de la commande
                $cmd->setIsVisible(1);
                // Sauvegarde de la commande
                $cmd->save();
            }

            $getDataCmd = $group->getCmd(null, 'count');
            if (!is_object($getDataCmd)) {
                // Création de la commande
                $cmd = new phone_detectionCmd();
                // Nom affiché
                $cmd->setName('Nombre de téléphones présents');
                // Identifiant de la commande
                $cmd->setLogicalId('count');
                // Identifiant de l'équipement
                $cmd->setEqLogic_id($group->getId());
                // Type de la commande
                $cmd->setType('info');
                // Sous-type de la commande
                $cmd->setSubType('numeric');
                // Visibilité de la commande
                $cmd->setIsVisible(1);
                // Sauvegarde de la commande
                $cmd->save();
            }

            $getRefreshCmd = $group->getCmd(null, 'refresh');
            if (is_object($getRefreshCmd)) {
                $getRefreshCmd->remove();
                $group->save();
            }

        } catch( Exception $ex) {
            log::add('phone_detection', 'debug', print_r($ex));
        }

        // $group->save();
    }

    private function applyModuleConfiguration() {
      $this->setConfiguration('applyMacAddress', $this->getConfiguration('macAddress'));
      $this->save();
    }
    /********** Getters and setters **********/

}

class phone_detectionCmd extends cmd
{

    /*************** Attributs ***************/

    /************* Static methods ************/

    /**************** Methods ****************/

    public function execute($_options = array()) {
        log::add('phone_detection', 'debug', 'cmdId:' . $this->getLogicalId());

        // Test pour ne répondre qu'à la commande rafraichir
        if ($this->getLogicalId() == 'refresh' /*&& $this->getConfiguration('deviceType') == 'phone'*/) {
            // On récupère l'équipement à partir de l'identifiant fournit par la commande
            $phone_detectionObj = phone_detection::byId($this->getEqlogic_id());
            log::add('phone_detection', 'debug', 'eqLogic Id:' . $this->getEqLogic_id());

            if ($phone_detectionObj->getConfiguration('deviceType') == 'phone') {

                // On récupère la commande 'data' appartenant à l'équipement
                $dataCmd = $phone_detectionObj->getCmd('info', 'state');

                // On récupère la mac address de l'équipement
                $macAddress = $phone_detectionObj->getConfiguration('macAddress');
                    log::add('phone_detection','debug', 'mac address: '.$macAddress);
            
                // On ping le device pour savoir s'il est là
                $btController = config::byKey('btport', 'phone_detection');

                // $btController = $phone_detectionObj->getConfiguration('btport');
                log::add('phone_detection','info', 'BT Device: '.$btController);

                $btController = ( $btController == '' ? 'hci0' : $btController );
                
                $name = shell_exec("sudo hcitool -i ". $btController ." name " . $macAddress);
                log::add('phone_detection', 'debug', 'device name: '. $name);

                $state = (empty($name) ? 0 : 1);
                log::add('phone_detection', 'debug', 'device state: '. $state);

                // On lui ajoute un évènement avec pour information 'Données de test'
                //$dataCmd->event($state);
                // On sauvegarde cet évènement
                // $dataCmd->save();
            }
        }
        phone_detection::updateGlobalDevice();
    }

    /********** Getters and setters **********/

}
