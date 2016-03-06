<?php
require_once(dirname(dirname(dirname(__FILE__))).'/Config.php');
header('Content-type:text/json;charset:utf-8');

$from=isset($_GET['from'])? $_GET['from'] : date("Y-m-d "."00:00:00");
$to=isset($_GET['to'])?$_GET['to'] : date("Y-m-d H:i:s",time());
$interval=isset($_GET['intv'])?$_GET['intv'] : '30m';
$doctype=isset($_GET['doctype'])?$_GET['doctype'] : '';  //type="html" or other

Dashboard::useES();
$logcount=Dashboard::getDocCount($from, $to, $interval, $doctype);

$result=['total'=>$logcount['hits']['total'], 'interval'=>array()];
foreach ($logcount['aggregations']["countbytime"]["buckets"] as $timeinterval) {
	$interval=['time'=>$timeinterval['key_as_string'], 'count'=>$timeinterval['doc_count']];
	$result['interval'][]=$interval;
}

echo json_encode($result);
