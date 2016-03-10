<?php
require_once(dirname(dirname(__FILE__)).'/Config.php');

//检查是否以screen或者debug运行
if(count($argv)<2 || !($argv[1]=='byscreen' || $argv[1]=='debug')){
	Util::echoRed("Please run ZSpider '".APPROOT."/bin/qwatcher start|debug '\n");
	exit();
}
$GLOBALS['DEBUG']=false;
if($argv[1]=='debug'){
	$GLOBALS['DEBUG']=true;
}

//重命名旧的日志文件
Util::renameOldLog();

//检查网络是否连接，如果网络中断则停止运行
if(Util::isNetError()){
	Util::putErrorLog("Start failed, network error.\r\n");
	exit();
}

//检查ES是否可以连接
ESConnector::connect();
if(!ESConnector::testConnect()){
	Util::putErrorLog("Start failed, can not connect to elasticsearch.\r\n");
	exit();
}

//清空原有pid
exec('rm -rf '.APPROOT.'/pids');

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
	//在网络正常以及ES连接正常
	if(!Util::isNetError() && ESConnector::testConnect()){
		TaskHandler::createProgress($hash);
		$hash=($hash+mt_rand(10,20))%300;
		$count--;
		Util::echoYellow("[".date("Y-m-d H:i:s")."] Restart a TaskHandler. running TaskHandler progress:".($GLOBALS['MAX_PARALLEL']-$count)."\n\n");
	}
}

Util::echoRed("[".date("Y-m-d H:i:s")."] All TaskHandler progress exit.\n");
Util::putErrorLog("---------------------- All TaskHandler progress exit -----------------------\r\n\r\n");

//向服务器报告状态以及停止超时子进程
function reportSpider(){
	while(true){
		Util::killPid($GLOBALS['TASKTIME']+60);
		$data = array ('name' =>$GLOBALS['SPIDERNAME']);
		$ch = curl_init ();
		curl_setopt ( $ch, CURLOPT_URL, $GLOBALS['REPORTADDR'] );
		curl_setopt ( $ch, CURLOPT_POST, 1 );
		curl_setopt ( $ch, CURLOPT_HEADER, 0 );
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt ( $ch, CURLOPT_POSTFIELDS, $data );
		curl_exec ( $ch );
		sleep(10);
	}
}
