<?php
require_once('Zend/Json.php');
require_once('classes/Server.php');

if (isset($_GET['clean'])) {
	Server::getInstance()->getCache()->clean();
}
$ids = Server::getInstance()->getCache()->getIds();
var_dump($ids);
/*foreach ($ids as $id) {
	var_dump(Server::getInstance()->getCache()->load($id));
}*/
