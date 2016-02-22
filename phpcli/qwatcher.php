<?php
include_once(dirname(dirname(__FILE__)).'/Config.php');

//检查是否以screen运行
if(count($argv)!=2 || $argv[1]!='byscreen'){
	Util::echoRed("Please run QueueWatcher by '".APPROOT."/bin/qwatcher start'\n");
	exit();
}

//启动队列任务监视，将超时的task重新加入队列中
TaskManager::srartQueueWatcher();
