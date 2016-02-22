<?php
require_once(dirname(dirname(__FILE__)).'/Config.php');

//检查是否以screen运行
if(count($argv)!=2 || $argv[1]!='byscreen'){
	Util::echoRed("Please run ZSpider '".APPROOT."/bin/qwatcher start'\n");
	exit();
}

//检查网络是否连接，如果网络中断则停止运行
if(Util::isNetError()){
	Util::echoRed("Network is error.\n");
	exit();
}

echo "Create progress for TaskHandler...\n";

//批量创建爬虫进程
$pids=array();
for($count=1; $count<=$GLOBALS['MAX_PARALLEL']; $count++){
	$pid=TaskHandler::createProgress();
	$pids[]=$pid;
}
Util::echoGreen("Create TaskHandler done, running TaskHandler progress:".count($pids)."\n");

//检测子进程退出状态
for($count=1; $count<count($pids); $count++){
	pcntl_wait($status);
	Util::echoRed("One of TaskHandler exit, remaining TaskHandler progress:".(count($pids)-$count)."\n");
}

Util::echoRed("All TaskHandler progress exit.\n");
Util::putErrorLog("---------------------- All TaskHandler progress exit -----------------------\r\n\r\n");
