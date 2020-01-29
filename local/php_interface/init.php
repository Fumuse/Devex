<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
	die();
}

use \Bitrix\Main\Loader;
use \Bitrix\Main\EventManager;

Loader::registerAutoLoadClasses($module = null, [
	'newUserTask' => '/local/php_interface/include/newUserTask.php',
]);

EventManager::getInstance()->addEventHandler(
	"rest",
	"OnUserAdd",
	[
		"newUserTask",
		"createTask"
	]
);