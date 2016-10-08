<?php
require_once('Zend/Cache/Backend/Apc.php');

class Server {
	static protected $_instance = NULL;
	protected $_cache = NULL;
	protected $_codeDefinition = array(
		0 => 'OK (initialisation)',
		1 => 'Appel non abouti (plus de canaux disponible ou numéro inexistant)',
		2 => 'Appel en absence (plusieurs  sonneries et coupures après 40s)',
		3 => 'Décroché',
		4 => 'Raccroché',
		5 => 'fin de l\'appel',
		6 => 'fin intro',
		7 => 'question 1',
		71 => 'réponse 1-1',
		72 => 'réponse 1-2',
		73 => 'réponse 1-3',
		74 => 'réponse 1-4',
		8 => 'question 2',
		81 => 'réponse 2-1',
		82 => 'réponse 2-2',
		83 => 'réponse 2-3',
		84 => 'réponse 2-4',
		9 => 'question 3',
		91 => 'réponse 3-1',
		92 => 'réponse 3-2',
		93 => 'réponse 3-3',
		94 => 'réponse 3-4',
		10 => 'question 4',
		101 => 'réponse 4-1',
		102 => 'réponse 4-2',
		103 => 'réponse 4-3',
		104 => 'réponse 4-4',
		11 => 'question 5',
		111 => 'réponse 5-1',
		112 => 'réponse 5-2',
		113 => 'réponse 5-3',
		114 => 'réponse 5-4',
		9999 => 'No message',
	);
	const SONACOM_URL = 'http://localhost/test/sonacom.php?tel=';
	
	static public function getInstance() {
		if (!self::$_instance instanceof self) {
			self::$_instance = new self;
		}
		return self::$_instance;
	}
	
	public function getCache() {
		if (!$this->_cache instanceof Zend_Cache_Backend_Apc) {
			$this->_cache = new Zend_Cache_Backend_Apc;
		}
		return $this->_cache;
	}
	
	public function checkPhone($tel) {
		if (!preg_match('~^0[0-9]{9}$~', $tel)) {
			throw new Exception('Phone invalid');
		}
		return $this;
	}
	
	public function setMessage($tel, $code) {
		$this->checkPhone($tel);
		$key = 'messages_' . $tel;
		$messages = $this->getMessages($tel, false);
		$messages[] = $this->getFromCode($code);
		$this->getCache()->save($messages, $key);
		return $this;
	}
	
	public function getMessages($tel, $empty = true) {
		$this->checkPhone($tel);
		$key = 'messages_' . $tel;
		$messages = $this->getCache()->load($key);
		if ((bool)$empty && $messages) {
			$this->getCache()->remove($key);
		}
		return $messages;
	}
	
	public function getFromCode($code) {
		if (!isset($this->_codeDefinition[$code])) {
			throw new Exception('Invalid code received');
		}
		$message = array(
			'code' => $code,
			'message' => $this->_codeDefinition[$code],
		);
		return $message;
	}
	
	public function initCommunication($tel) {
		$this->checkPhone($tel);
		$result = file_get_contents(self::SONACOM_URL . $tel);
		return $this;
	}
}