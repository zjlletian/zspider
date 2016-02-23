<?php
require_once(dirname(dirname(__FILE__)).'/Config.php');

//检查是否以screen运行
if(count($argv)!=2 || $argv[1]!='byscreen'){
	Util::echoRed("Please run ZSpider '".APPROOT."/bin/qwatcher start'\n");
	exit();
}

//检查网络是否连接，如果网络中断则停止运行
if(Util::isNetError()){
	Util::putErrorLog("Start failed, network error.\r\n");
	exit();
}

Util::echoYellow("[".date("Y-m-d H:i:s")."] Create progress for TaskHandler...\n");
Util::putErrorLog("---------------------- Create progress for TaskHandler -----------------------");

//批量创建爬虫进程
for($count=1; $count<=$GLOBALS['MAX_PARALLEL']; $count++){
	TaskHandler::createProgress();
}
Util::echoGreen("[".date("Y-m-d H:i:s")."] Create TaskHandler done, running TaskHandler progress:".$GLOBALS['MAX_PARALLEL']."\n\n");
Util::putErrorLog("Create TaskHandler done, running TaskHandler progress:".$GLOBALS['MAX_PARALLEL']."\r\n");

//检测子进程退出状态
for($count=1; $count<$GLOBALS['MAX_PARALLEL']; $count++){
	pcntl_wait($status);
	Util::echoRed("[".date("Y-m-d H:i:s")."] One TaskHandler stoped. running TaskHandler progress:".($GLOBALS['MAX_PARALLEL']-$count)."\n");
	if(!Util::isNetError()){
		TaskHandler::createProgress();
		$count--;
		Util::echoYellow("[".date("Y-m-d H:i:s")."] Restart a TaskHandler. running TaskHandler progress:".($GLOBALS['MAX_PARALLEL']-$count)."\n\n");
	}
}

Util::echoRed("[".date("Y-m-d H:i:s")."] All TaskHandler progress exit.\n");
Util::putErrorLog("---------------------- All TaskHandler progress exit -----------------------\r\n\r\n");
