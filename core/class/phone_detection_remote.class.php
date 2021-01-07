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

/* * ***************************Includes********************************* */

class phone_detection_remote {
	/*     * *************************Attributs****************************** */
	private $id;
	private $remoteName;
	private $configuration;

	/*     * ***********************Methode static*************************** */

	public static function byId($_id) {

		$values = array(
			'id' => $_id,
		);
		$sql = 'SELECT ' . DB::buildField(__CLASS__) . '
		FROM phone_detection_remote
		WHERE id=:id';
		return DB::Prepare($sql, $values, DB::FETCH_TYPE_ROW, PDO::FETCH_CLASS, __CLASS__);
	}

	public static function all() {
		$sql = 'SELECT ' . DB::buildField(__CLASS__) . '
		FROM phone_detection_remote';
		return DB::Prepare($sql, array(), DB::FETCH_TYPE_ALL, PDO::FETCH_CLASS, __CLASS__);
	}

	/*     * *********************Methode d'instance************************* */

	public function save() {
		DB::save($this);
		self::setCacheRemotes('allremotes',self::all());
		return;
	}

	public function remove() {
		return DB::remove($this);
	}

	public function getCache($_key = '', $_default = '') {
		$cache = cache::byKey('eqLogicCacheAttr' . $this->getId())->getValue();
		return utils::getJsonAttr($cache, $_key, $_default);
	}

	public function setCache($_key, $_value = null) {
		cache::set('eqLogicCacheAttr' . $this->getId(), utils::setJsonAttr(cache::byKey('eqLogicCacheAttr' . $this->getId())->getValue(), $_key, $_value));
	}

	public static function getCacheRemotes($_key = '', $_default = '') {
		$cache = cache::byKey('PhoneDetectionPluginRemotes')->getValue();
		return utils::getJsonAttr($cache, $_key, $_default);
	}

	public static function setCacheRemotes($_key, $_value = null) {
		cache::set('PhoneDetectionPluginRemotes', utils::setJsonAttr(cache::byKey('PhoneDetectionPluginRemotes')->getValue(), $_key, $_value));
	}

	public function execCmd($_cmd) {
		$ip = $this->getConfiguration('remoteIp');
		$port = $this->getConfiguration('remotePort');
		$user = $this->getConfiguration('remoteUser');
		$pass = $this->getConfiguration('remotePassword');
		if (!$connection = ssh2_connect($ip, $port)) {
			log::add('phone_detection', 'error', 'connexion SSH KO for ' . $this->remoteName);
				return false;
		} else {
			if (!ssh2_auth_password($connection, $user, $pass)) {
				log::add('phone_detection', 'error', 'Authentification SSH KO for ' . $this->remoteName);
				return false;
			} else {
				foreach ($_cmd as $cmd){
					log::add('phone_detection', 'info', __('Commande par SSH ',__FILE__) . $cmd .  __(' sur ',__FILE__) . $ip);
					$execmd = "echo '" . $pass . "' | sudo -S " . $cmd;
					$stream = ssh2_exec($connection, $execmd);
					$errorStream = ssh2_fetch_stream($stream, SSH2_STREAM_STDERR);
					stream_set_blocking($errorStream, true);
					stream_set_blocking($stream, true);
					$output = stream_get_contents($stream) . ' ' . stream_get_contents($errorStream);
					fclose($stream);
					fclose($errorStream);
					if (trim($output) != '') {
						log::add('phone_detection','debug',$output);
					}
				}
				$stream = ssh2_exec($connection, 'exit');
				$errorStream = ssh2_fetch_stream($stream, SSH2_STREAM_STDERR);
				stream_set_blocking($errorStream, true);
				stream_set_blocking($stream, true);
				$output = stream_get_contents($stream) . ' ' . stream_get_contents($errorStream);
				fclose($stream);
				fclose($errorStream);
				if (trim($output) != '') {
					log::add('phone_detection','debug',$output);
				}
				return $output !== false;
			}
		}
	}

	public function sendFiles($_local, $_target) {
		$ip = $this->getConfiguration('remoteIp');
		$port = $this->getConfiguration('remotePort');
		$user = $this->getConfiguration('remoteUser');
		$pass = $this->getConfiguration('remotePassword');
		if (!$connection = ssh2_connect($ip, $port)) {
			log::add('phone_detection', 'error', 'connexion SSH KO for ' . $this->remoteName);
			return false;
		} else {
			if (!ssh2_auth_password($connection, $user, $pass)) {
				log::add('phone_detection', 'error', 'Authentification SSH KO for ' . $this->remoteName);
				return false;
			} else {
				log::add('phone_detection', 'info', 'Envoie de fichier sur ' . $ip);
				$result = ssh2_scp_send($connection, $_local, $_target, 0777);
				if (!$result){
					log::add('phone_detection','error','Files could not be sent to ' . $ip);
					return false;
				} else {
					log::add('phone_detection','info','Files successfully sent to ' . $ip);
				}
				$execmd = "echo '" . $pass . "' | sudo -S " . 'exit';
				$stream = ssh2_exec($connection, $execmd);
				$errorStream = ssh2_fetch_stream($stream, SSH2_STREAM_STDERR);
				stream_set_blocking($errorStream, true);
				stream_set_blocking($stream, true);
				$output = stream_get_contents($stream);
				fclose($stream);
				fclose($errorStream);
				if (trim($output) != '') {
					log::add('phone_detection','debug',$output);
				}
			}
		}
		return true;
	}

	public function getFiles($_local, $_target) {
		$ip = $this->getConfiguration('remoteIp');
		$port = $this->getConfiguration('remotePort');
		$user = $this->getConfiguration('remoteUser');
		$pass = $this->getConfiguration('remotePassword');
		if (!$connection = ssh2_connect($ip, $port)) {
			log::add('phone_detection', 'error', 'connexion SSH KO for ' . $this->remoteName);
				return false;
		} else {
			if (!ssh2_auth_password($connection, $user, $pass)) {
				log::add('phone_detection', 'error', 'Authentification SSH KO for ' . $this->remoteName);
				return false;
			} else {
				log::add('phone_detection', 'info', __('Récupération de fichier depuis ',__FILE__) . $ip);
				$result = ssh2_scp_recv($connection, $_target, $_local);
				$execmd = "echo '" . $pass . "' | sudo -S " . 'exit';
				$stream = ssh2_exec($connection, $execmd);
				$errorStream = ssh2_fetch_stream($stream, SSH2_STREAM_STDERR);
				stream_set_blocking($errorStream, true);
				stream_set_blocking($stream, true);
				$output = stream_get_contents($stream);
				fclose($stream);
				fclose($errorStream);
				if (trim($output) != '') {
					log::add('phone_detection','debug',$output);
				}
			}
		}
		return true;
	}

	/*     * **********************Getteur Setteur*************************** */

	public function getId() {
		return $this->id;
	}

	public function setId($id) {
		$this->id = $id;
		return $this;
	}

	public function getRemoteName() {
		return $this->remoteName;
	}

	public function setRemoteName($name) {
		$this->remoteName = $name;
		return $this;
	}

	public function getConfiguration($_key = '', $_default = '') {
		return utils::getJsonAttr($this->configuration, $_key, $_default);
	}

	public function setConfiguration($_key, $_value) {
		$this->configuration = utils::setJsonAttr($this->configuration, $_key, $_value);
		return $this;
	}
}
