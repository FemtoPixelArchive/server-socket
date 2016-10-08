<?php
require_once('Zend/Json.php');
require_once('classes/Server.php');

try {
	$tel = isset($_GET['tel']) ? $_GET['tel'] : 0;
	$code = (int)(isset($_GET['code']) ? $_GET['code'] : 0);
	$server = Server::getInstance()->setMessage($tel, $code);
	$message = 'ok';
} catch (Exception $e) {
	$message = array(
		'message' => $e->getMessage(),
		'error' => $e->getCode(),
	);
}
echo Zend_Json::encode($message);