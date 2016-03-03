<?php
require_once(dirname(dirname(__FILE__)).'/Config.php');

//检查是否以screen运行
if(count($argv)<2 || $argv[1]!='byscreen'){
	Util::echoRed("Please run ZSpider '".APPROOT."/bin/qwatcher start'\n");
	exit();
}

//重命名旧的日志文件
Util::renameOldLog();

//检查网络是否连接，如果网络中断则停止运行
if(Util::isNetError()){
	Util::putErrorLog("Start failed, network error.\r\n");
	exit();
}

//报告爬虫状态
$pid = pcntl_fork();
if(!$pid) {
	reportSpider();
}

Util::echoYellow("[".date("Y-m-d H:i:s")."] Create progress for TaskHandler...\n");
Util::putErrorLog("---------------------- Create progress for TaskHandler -----------------------");

$hash=0;

//批量创建爬虫进程
for($count=1; $count<=$GLOBALS['MAX_PARALLEL']; $count++){
	TaskHandler::createProgress($hash);
	$hash=($hash+mt_rand(10,20))%300;
}
Util::echoGreen("[".date("Y-m-d H:i:s")."] Create TaskHandler done, running TaskHandler progress:".$GLOBALS['MAX_PARALLEL']."\n\n");
Util::putErrorLog("Create TaskHandler done, running TaskHandler progress:".$GLOBALS['MAX_PARALLEL']."\r\n");

//检测子进程退出状态
for($count=1; $count<=$GLOBALS['MAX_PARALLEL']; $count++){
	pcntl_wait($status);
	Util::echoRed("[".date("Y-m-d H:i:s")."] One TaskHandler stoped. running TaskHandler progress:".($GLOBALS['MAX_PARALLEL']-$count)."\n");
	if(!Util::isNetError()){	
		TaskHandler::createProgress($hash);
		$hash=($hash+mt_rand(10,20))%300;
		$count--;
		Util::echoYellow("[".date("Y-m-d H:i:s")."] Restart a TaskHandler. running TaskHandler progress:".($GLOBALS['MAX_PARALLEL']-$count)."\n\n");
	}
}

Util::echoRed("[".date("Y-m-d H:i:s")."] All TaskHandler progress exit.\n");
Util::putErrorLog("---------------------- All TaskHandler progress exit -----------------------\r\n\r\n");

//向服务器报告在线状态
function reportSpider(){
	while(true){
		$data = array ('name' =>$GLOBALS['SPIDERNAME']);
		$ch = curl_init ();
		curl_setopt ( $ch, CURLOPT_URL, $GLOBALS['REPORTADDR'] );
		curl_setopt ( $ch, CURLOPT_POST, 1 );
		curl_setopt ( $ch, CURLOPT_HEADER, 0 );
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt ( $ch, CURLOPT_POSTFIELDS, $data );
		$return = curl_exec ( $ch );
		sleep(20);
	}
}
