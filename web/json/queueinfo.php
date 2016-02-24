<?php
require_once(dirname(dirname(dirname(__FILE__))).'/Config.php');

//显示队列信息
QueueWatcher::connect(false);
$queueinfo=QueueWatcher::getQueueInfo();

echo "处理中的任务: \n\n";
foreach ($queueinfo['onprocess'] as $task) {
	$delay=$task['acktime']-$task['time'];
	$h=intval($delay/3600);
	$m=intval(($delay-$h*3600)/60);
	$s=intval(($delay-$h*3600)%60);
	$delaystr=($h>0?$h.'小时':'').($m>0?$m.'分钟':'').($s>0?$s.'秒':'');
	echo $task['url']." Type:".$task['type']." Level:".$task['level']."\n";
	echo "第".$task['times']."次处理，开始时间：".date("Y-m-d H:i:s",$task['acktime'])." 耗时:".(time()-$task['acktime'])."秒\n";
	echo "加入队列时间：".date("Y-m-d H:i:s",$task['time'])." 延迟:".$delaystr."\n\n";
}
echo "等待爬取的新网页数量：".$queueinfo['new_task']."\n";
echo "需要更新的网页数量：".$queueinfo['update_task']."\n";
echo "正在处理的任务数量: ".count($queueinfo['onprocess'])."\n\n";
