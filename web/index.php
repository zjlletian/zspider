<?php
include_once(dirname(dirname(__FILE__)).'/Config.php');
include_once('TaskManager.class.php');

echo 'zspider';

TaskManager::connect();
var_dump(TaskManager::getQueueInfo());