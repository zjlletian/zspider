<?php
require_once(dirname(dirname(dirname(__FILE__))).'/Config.php');
header('Content-type:text/json;charset:utf-8');

$type=isset($_GET['type'])?$_GET['type'] : ''; //'html' or others

Dashboard::useES();
$logcount=Dashboard::getDocCount($type);
$result=['total'=>$logcount['hits']['total']];
echo json_encode($result);
