<?php
require_once(dirname(dirname(dirname(__FILE__))).'/Config.php');
header('Content-type:text/json;charset:utf-8');

$from=isset($_GET['from'])? $_GET['from'] : date("Y-m-d H:i:s",time()-3600*12);
$to=isset($_GET['to'])?$_GET['to'] : date("Y-m-d H:i:s",time());
$interval=isset($_GET['intv'])?$_GET['intv'] : '1h';
$type=isset($_GET['type'])?$_GET['type'] : 'success';

Dashboard::useES();
$logcount=Dashboard::getLogCount($from, $to, $interval, $type);

$result=['total'=>$logcount['hits']['total'], 'interval'=>array()];
foreach ($logcount['aggregations']["countbytime"]["buckets"] as $timeinterval) {
	$interval=['time'=>$timeinterval['key_as_string'], 'count'=>$timeinterval['doc_count'], 'spiders'=>array()];
	foreach ($timeinterval["countbyspider"]["buckets"] as $spider) {
		$interval['spiders'][]=['spider'=>$spider['key'],'count'=>$spider['doc_count']];
	}
	$result['interval'][]=$interval;
}

echo json_encode($result);