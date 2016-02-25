<?php
require_once(dirname(dirname(dirname(__FILE__))).'/Config.php');
header('Content-type:text/json;charset:utf-8');

//队列信息
QueueWatcher::connect(false);
$queueinfo=QueueWatcher::getQueueInfo();
$onprocess = array();
foreach ($queueinfo['onprocess'] as $task) {
	$delay=$task['acktime']-$task['time'];
	$h=intval($delay/3600);
	$m=intval(($delay-$h*3600)/60);
	$s=intval(($delay-$h*3600)%60);
	$delaystr=($h>0?$h.'小时':'').($m>0?$m.'分钟':'').($s>0?$s.'秒':'');
	
	$task['cost']=time()-$task['acktime'];
	$task['acktime']=date("Y-m-d H:i:s",$task['acktime']);
	$task['time']=date("Y-m-d H:i:s",$task['time']);
	$task['delay']=$delaystr;
	
	$onprocess[]=$task;
}
$queueinfo['onprocess']=$onprocess;

//爬虫列表
$spiders = QueueWatcher::getSpiders();
$spidersinfo= array();
foreach ($spiders as $spider) {
	foreach ($queueinfo['spiders'] as $st) {
		if($spider['name']=$st['spider']){
			$spider['tasks']=$st['tasks'];
			$spidersinfo[]=$spider;
		}
	}
}
$queueinfo['spiders']=$spidersinfo;
echo json_encode($queueinfo);