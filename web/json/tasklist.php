<?php
require_once(dirname(dirname(dirname(__FILE__))).'/Config.php');
header('Content-type:text/json;charset:utf-8');
Dashboard::useMysql();

$tasklist = array();
foreach (Dashboard::getTasklist() as $task) {
    $delay=$task['proctime']-$task['time'];
    $h=intval($delay/3600);
    $m=intval(($delay-$h*3600)/60);
    $s=intval(($delay-$h*3600)%60);
    $delaystr=($h>0?$h.'小时':'').($m>0?$m.'分钟':'').($s>0?$s.'秒':'0秒');

    $task['cost']=time()-$task['proctime'];
    $task['max']=$task['acktime']-$task['proctime'];
    $task['proctime']=date("Y-m-d H:i:s",$task['proctime']);
    $task['time']=date("Y-m-d H:i:s",$task['time']);
    $task['delay']=$delaystr;

    $tasklist[]=$task;
}

echo json_encode($tasklist);