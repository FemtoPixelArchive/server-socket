<?php
if (!function_exists('json_encode')) {
	throw new Exception ('Must install json_encode');
}

class Server_Socket {
	protected $_host = '127.0.0.1';
	protected $_port = 8080;
	protected $_socket = NULL;
	protected $_clients = array();
	protected $_messages = array();
	protected $_verbose = true;
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
	
	const SONACOM_URL = 'http://localhost/sonacom?code=999999&tel=';
	
	const LOG_INFO = "\033[0;32m[INFO]";
	const LOG_WARN = "\033[0;33m[WARNING]";
	const LOG_ERROR = "\033[0;31m[ERROR]";
	const LOG_NOTICE = "\033[0;34m[NOTICE]";
	
	public function __construct($host = '127.0.0.1', $port = 8080) {
		$this->setHost($host)
				->setPort($port)
				->start();
	}
	
	public function setVerbose($verbose = true) {
		$this->_verbose = (bool) $verbose;
		return $this;
	}
	
	public function isVerbose() {
		return (bool)$this->_verbose;
	}
	
	public function getPort() {
		return $this->_port;
	}
	
	public function getHost() {
		return $this->_host;
	}
	
	public function getSocket() {
		return $this->_socket;
	}
	
	public function setPort($port) {
		$this->_port = (int) $port;
		if ($this->getSocket()) {
			$this->stop();
		}
		return $this;
	}
	
	public function setHost($host) {
		$this->_host = $host;
		if ($this->getSocket()) {
			$this->stop();
		}
		return $this;
	}
	
	public function stop() {
		$this->log('Server :: stop', self::LOG_NOTICE);
		foreach ($this->_clients as $tel => $socket) {
			$this->setClient($tel);
		}
		if ($this->getSocket()) {
			socket_close($this->getSocket());
		}
		$this->_socket = NULL;
		return $this;
	}
	
	public function start() {
		ini_set('max_execution_time', 0);
		$this->log('Server :: start', self::LOG_NOTICE);
		$this->_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		if (!$this->getSocket()) {
			$message = 'Unable to start server :: socket_create';
			$this->log($message, self::LOG_ERROR);
			throw new exception($message);
		}
		socket_bind($this->getSocket(), $this->getHost(), $this->getPort());
		$binded = socket_bind($this->getSocket(), $this->getHost(), $this->getPort());
		if (!$binded) {
			$message = 'Unable to start server :: socket_bind';
			$this->log($message, self::LOG_ERROR);
			throw new exception($message);
		}
		$listened = socket_listen($this->getSocket());
		if (!$listened) {
			$message = 'Unable to start server :: socket_listen';
			$this->log($message, self::LOG_ERROR);
			throw new exception($message);
		}
		$nonblock = socket_set_nonblock($this->getSocket());
		if (!$nonblock) {
			$message = 'Unable to start server :: socket_set_unblock';
			$this->log($message, self::LOG_ERROR);
			throw new exception($message);
		}
		while($this->getSocket()) {
			$socket = @socket_accept($this->getSocket());
			if ($socket) {
				$this->onConnect($socket);
			}
			$this->killExpiredClients();
		}
		return $this;
	}

	public function killExpiredClients() {
		foreach ($this->_clients as $tel => $item) {
			if (($item['time'] + 10) <= time()) {
				$this->sendMessage($this->getClient($tel), $this->getMessageFromCode(9999));
				$this->setClient($tel);
			}
		}
		return $this;
	}
	
	public function onConnect($socket) {
		$this->identify($socket);
		return $this;
	}
	
	public function identify($socket) {
		$i = 0;
		do {
			$request = socket_read($socket, 4096);
			usleep(500000);
		} while($socket && !$request && (++$i < 5));
		preg_match('~GET /([^?]+)\?([^ ]+)~', $request, $globb);
		if (count($globb) != 3) {
			$globb = array(
				'none',
				'none',
				'none',
			);
		}
		parse_str($globb[2], $output);
		$identity = isset($globb[1]) ? $globb[1] : 'anonymous';
		$this->log("Incomming connection : $identity");
		$function = 'treat' . ucfirst($identity);
		$this->$function($output, $socket, $request);
		return $this;
	}
	
	public function __call($function, $params) {
		if (strpos($function, 'treat') === 0) {
			//$this->log("Incomming connection unallowed : {$params[2]}", self::LOG_WARN);
		}
		return $this;
	}
	
	public function sendMessage($socket, $resp, $type = 'text/javascript') {
		if (!is_resource($socket)) {
			$this->log('Server try to send message to non existing socket', self::LOG_WARN);
			return $this;
		}
		$message = json_encode($resp);
		if ($type == 'text/javascript') {
			$message = "callback($message);";
		}
		$date = gmdate('D, d M Y H:i:s \G\M\T');
		$length = strlen($message);
		$query = "HTTP/1.0 200 OK\r\n";
		$query .= "Date: $date\r\n";
		$query .= "Server: Marcel Server\r\n";
		$query .= "Content-Type: $type\r\n";
		$query .= "Content-Length: $length\r\n";
		$query .= "Last-Modified: $date\r\n\r\n";
		$query .= $message;
		
		$res = socket_write($socket, $query);
		if (!$res) {
			$this->log('Error while writing message', self::LOG_ERROR);
		}
		socket_close($socket);
		return $this;
	}
	
	public function treatDebug($request, $socket) {
		if (!isset($request['password'])) {
			$this->log('Try to connect as debug without password', self::LOG_WARN);
			socket_close($socket);
			return $this;
		}
		if (isset($request['stop'])) {
			$this->stop();
			return $this;
		}
		ob_start();
		echo 'Clients:';
		var_dump($this->_clients);
		echo 'Messages:';
		var_dump($this->_messages);
		$content = ob_get_clean();
		socket_write($socket, $content);
		socket_close($socket);
		return $this;
	}
	
	public function treatSonacom($request, $socket) {
		if (!isset($request['tel']) || !preg_match('~^0[0-9]{9}$~', $request['tel'])) {
			$this->log("Phone invalid : {$request['tel']}", self::LOG_WARN);
			return $this;
		}
		if (!isset($request['code'])) {
			$this->log("Code invalid : {$request['code']}", self::LOG_WARN);
			return $this;
		}
		$code = (int) $request['code'];
		$message = $this->getMessageFromCode($code);
		$this->setMessage($request['tel'], $message)
				->sendMessage($socket, 'ok', 'text/html');
		socket_close($socket);
		return $this;
	}
	
	public function setMessage($tel, $message) {
		$dump = json_encode($message);
		$this->log("Incomming message for $tel : $dump");
		$this->_messages[$tel][] = $message;
		if ($this->getClient($tel)) {
			$this->sendMessage($this->getClient($tel), $this->getMessages($tel));
		}
		return $this;
	}
	
	public function getMessages($tel) {
		$messages = (isset($this->_messages[$tel]) ? $this->_messages[$tel] : array());
		unset($this->_messages[$tel]);
		return $messages;
	}
	
	public function treatClient($request, $socket) {
		if (!isset($request['tel']) || !preg_match('~^0[0-9]{9}$~', $request['tel'])) {
			$this->log("Phone invalid : {$request['tel']}", self::LOG_WARN);
			return $this;
		}
		$this->log("Client identified as {$request['tel']}");
		if (isset($request['init'])) {
			$this->log("Init to sonacom : {$request['tel']}");
			//$result = file_get_contents(self::SONACOM_URL . $request['tel']);
		}
		$this->setClient($request['tel'], $socket);
		$message = $this->getMessages($request['tel']);
		if (count($message)) {
			$this->sendMessage($socket, $message);
			$this->log("Sending messages for {$request['tel']}");
		}
		if (isset($request['logout'])) {
			$this->log("Client logout : {$request['tel']}");
			$this->setClient($request['tel']);
		}
		return $this;
	}
		
	public function setClient($tel, $socket = NULL) {
		if ($socket) {
			if ($this->getClient($tel)) {
				socket_close($socket);
			} else {
				$this->_clients[$tel] = array(
					'socket' => $socket,
					'time' => time(),
				);
			}
		} else {
			$this->log("Client $tel disconnected");
			if ($this->getClient($tel)) {
				socket_close($this->_clients[$tel]['socket']);
			}
			unset($this->_clients[$tel]);
		}
		return $this;
	}
	
	public function getClient($tel) {
		return ((isset($this->_clients[$tel]['socket']) && is_resource($this->_clients[$tel]['socket'])) ? $this->_clients[$tel]['socket'] : NULL);
	}
	
	public function getMessageFromCode($code) {
		$message = isset($this->_codeDefinition[$code]) ? $this->_codeDefinition[$code] : 'Unknown message';
		$return = array(
			'code' => $code,
			'message' => $message,
		);
		return $return;
	}
	
	public function log($message, $status = self::LOG_INFO) {
		if ($this->isVerbose()) {
			echo "$status $message\n";
		}
		return $this;
	}
}
