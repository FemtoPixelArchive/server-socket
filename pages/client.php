<?php
require_once('Zend/Json.php');
require_once('classes/Server.php');

try {
	ini_set('max_execution_time', 0);
	$tel = isset($_GET['tel']) ? $_GET['tel'] : 0;
	$init = isset($_GET['init']);
	$server = new Server;
	if ($init) {
		$server->initCommunication($tel);
	}
	$time = time();
	do {
		$message = $server->getMessages($tel);
		usleep(10000);
	} while (!$message && (($time + 5) >= time()));
} catch (Exception $e) {
	$message = array(
		'message' => $e->getMessage(),
		'error' => $e->getCode(),
	);
}
echo 'callback(' . Zend_Json::encode($message) . ');';