<?php

require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class phone_detection extends eqLogic
{

    /*************** Attributs ***************/
    

    /************* Static methods ************/

    // /**
    //  * Tâche exécutée toutes les minutes.
    //  *
    //  * @param null $_eqLogic_id Identifiant des objets
    //  */


    // public static function cron($_eqLogic_id = null)
    // {
    //   // Récupère la liste des téléphones
    //   if ($_eqLogic_id == null) {
    //     $eqLogics = self::byType('phone_detection', true);
    //   } else {
    //     $eqLogics = array(self::byId($_eqLogic_id));
    //   }

    //   // Met à jour l'ensemble des équipements
    //   foreach($eqLogics as $phone_detectionObj) {
 	//       $getDataCmd = $phone_detectionObj->getCmd(/*'action'*/ null, 'refresh');
	//       if(is_object($getDataCmd)) {
	// 	      log::add('phone_detection', 'debug', $getDataCmd->getId() );
	// 	      $getDataCmd->execute();
	//       }
    //   }
    // }

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
        // log::add('phone_detection','info','version:' . $pluginVersion);
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

        // $zigate_version = trim(file_get_contents(dirname(__FILE__) . '/../../resources/zigate_version.txt'));
        // $cmd = "/usr/bin/python3 -c 'from distutils.version import LooseVersion;import zigate,sys;" .
        //       "sys.exit(LooseVersion(zigate.__version__)<LooseVersion(\"".$zigate_version."\"))' 2>&1";
        // exec($cmd, $output, $return_var);

        // if ($return_var == 0) {
        //     $return['state'] = 'ok';
        // }

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
        $return = ['state' => 'nok'];
        $pid_file = jeedom::getTmpFolder('phone_detection') . '/daemon.pid';
        if (file_exists($pid_file)) {
            if (posix_getsid(trim(file_get_contents($pid_file)))) {
                $return['state'] = 'ok';
            } else {
                shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file . ' 2>&1 > /dev/null');
            }
        }

        $return['launchable'] = 'ok';
        // $port = config::byKey('port', 'phone_detection');
        // $host = config::byKey('host', 'phone_detection');
        $btport = config::byKey('btport', 'phone_detection');
        if (phone_detection::dependancy_info()['state'] == 'nok') {
            $cache = cache::byKey('dependancy' . 'phone_detection');
            $cache->remove();
            $return['launchable'] = 'nok';
            $return['launchable_message'] = __('Veuillez (ré-)installer les dépendances', __FILE__);
            return $return;
        } 

        if ($btport == "none" || $btport == "") {
            $return['launchable'] = 'nok';
            $return['launchable_message'] = __('Veuillez sélecter un contrôleur bluetooth', __FILE__);
            return $return;
        }
        
        
        // if (!$host) {
        //     if (@!file_exists($port) && $port != 'auto') {
        //         $return['launchable'] = 'nok';
        //         $return['launchable_message'] = __('Le port n\'est pas configuré ou la zigate n\'est pas connecté.', __FILE__);
        //     } elseif ($port != 'auto') {
        //         exec(system::getCmdSudo() . 'chmod 777 ' . $port . ' > /dev/null 2>&1');
        //     }
        // }
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
        // $port = config::byKey('port', 'phone_detection');
        // $host = config::byKey('host', 'phone_detection');
        $btport = config::byKey('btport', 'phone_detection');
        $deamon_path = dirname(__FILE__) . '/../../resources';
        $interval = config::byKey('interval', 'phone_detection');
        $absentThreshold = config::byKey('absentThreshold', 'phone_detection');
        $callback = network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/phone_detection/core/php/phone_detection.php';

        $cmd = '/usr/bin/python3 ' . $deamon_path . '/phone_detectiond/phone_detectiond.py ';
        // if ($host) {
        //     $cmd .= ' --device ' . $host;
        // } else {
        //     $cmd .= ' --device ' . $port;
        // }
        $cmd .= ' --device ' . $btport;
        $cmd .= ' --loglevel ' . log::convertLogLevel(log::getLogLevel('phone_detection'));
        $cmd .= ' --apikey ' . jeedom::getApiKey('phone_detection');
        $cmd .= ' --pidfile ' . jeedom::getTmpFolder('phone_detection') . '/daemon.pid';
        $cmd .= ' --socket ' . jeedom::getTmpFolder('phone_detection') . '/daemon.sock';
        $cmd .= ' --callback ' . $callback;
        $cmd .= ' --interval ' . $interval;
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

        message::removeAll('phone_detection', 'unableStartDeamon');
        log::add('phone_detection', 'info', 'Démon phone_detection lancé');
    }


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
        phone_detection::callDeamon('insert_device',
            [
                $this->getId(),
                $this->getName(),
                $this->getConfiguration('macAddress')
            ]
        );
    }

    public function preRemove() {
        phone_detection::callDeamon('remove_device', 
            [
                $this->getId(),
                $this->getName(),
                $this->getConfiguration('macAddress')
            ]
        );
    }

    public function postUpdate()
    {
        log::add('phone_detection', 'debug', 'postUpdate()');

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

    public static function postSave() {
      log::add('phone_detection', 'debug', 'postSave()');
      // log::add('phone_detection', 'debug', 'applyMacAddress:'. $this->getConfiguration('applyMacAddress'));
      // log::add('phone_detection', 'debug', 'macAddress:' . $this->getConfiguration('macAddress'));
      // if ($this->getConfiguration('applyMacAddress') !=  $this->getConfiguration('macAddress')) {
      //   $this->applyModuleConfiguration();
      // }
    }

    private function applyModuleConfiguration() {
      $this->setConfiguration('applyMacAddress', $this->getConfiguration('macAddress'));
      $this->save();

      // if($this->getConfiguration('device') == '') {
      //   return true;
      // }

      // $device = self::devicesParameters($this->getConfiguration('device'));

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
        if ($this->getLogicalId() == 'refresh') {
            // On récupère l'équipement à partir de l'identifiant fournit par la commande
		$phone_detectionObj = phone_detection::byId($this->getEqlogic_id());
		log::add('phone_detection', 'debug', 'eqLogic Id:' .$this->getEqLogic_id());

//		$phone_detectionObj = $phone_detectionArray[0];
            // On récupère la commande 'data' appartenant à l'équipement
	    $dataCmd = $phone_detectionObj->getCmd('info', 'state');

	    // On récupère la mac address de l'équipement
		$macAddress = $phone_detectionObj->getConfiguration('macAddress');
    		log::add('phone_detection','debug', 'mac address: '.$macAddress);
	
	    // On ping le device pour savoir s'il est là
	    $name = shell_exec("sudo hcitool -i hci0 name $macAddress");
	    log::add('phone_detection', 'debug', 'device name:'.$x);

	    $state = (empty($name) ? 0 : 1);

            // On lui ajoute un évènement avec pour information 'Données de test'
            $dataCmd->event($state);
            // On sauvegarde cet évènement
	    $dataCmd->save();


        }
    }

    /********** Getters and setters **********/

}
