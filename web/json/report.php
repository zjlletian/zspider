<?php
require_once(dirname(dirname(dirname(__FILE__))).'/Config.php');

$name=$_POST['name'];
$ip=$_SERVER["REMOTE_ADDR"];

QueueWatcher::connect(false);
echo 'report by '.$name.' on '.$ip.' result:';
var_dump(QueueWatcher::spiderReport($name,$ip));