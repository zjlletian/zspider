<?php
require_once(dirname(dirname(dirname(__FILE__))).'/Config.php');
header('Content-type:text/json;charset:utf-8');

Dashboard::useMysql();
$queueinfo=Dashboard::getQueueInfo();

echo json_encode($queueinfo);
