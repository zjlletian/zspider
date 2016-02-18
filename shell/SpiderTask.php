<?php
include_once(dirname(dirname(__FILE__)).'/Config.php');
include_once('UrlAnalyzer.class.php');
include_once('TaskManager.class.php');
include_once('Util.class.php');

//标记网络状态
define('NETERROR',APPROOT.'neterror.tmp');
function marknetwork(){
	if(!Util::checkNetwork()){
		Util::echoRed("Net work is unavailable\n");
		if(!file_exists(NETERROR)){
			touch(NETERROR);
		}
		exit();
	}
}
if(file_exists(NETERROR)){
	unlink(NETERROR);
}
marknetwork();

//连接到mongodb中的队列，启动ack监控子进程与es转储子进程
TaskManager::init();

//获取到的任务与任务pid
$tasks=array();
$taskpid=array();

//循环获取与执行任务
while(true){
	//若网络中断则停止运行
	if(file_exists(NETERROR)){
		exit();
	}
	//批量获取爬虫任务
	$count=0;
	for(;$count<$GLOBALS['MAX_PARALLEL'];$count++){
		$task=TaskManager::getTask();
		if($task!=null){
			$tasks[]=$task;
		}
		else{
			break;
		}
	}
	//若没有获取到任务，则休眠两秒
	if($count==0){
		sleep(2);
		break;
	}
	//为每一个任务创建进程
	foreach ($tasks as $task) {
		$taskpid[]=createProgress($task);
	}
	//等待子进程退出
	foreach($taskpid as $pid){
		pcntl_waitpid($pid,$status);
	}
	unset($tasks);
	unset($taskspid);
}

//为任务创建进程
function createProgress($task){
	$pid = pcntl_fork();
	if(!$pid) {
	    handleSpiderTask($task);
	    exit();
	}
	else{
		return $pid;
	}
}

//处理一个爬虫任务
function handleSpiderTask($task){
	//执行任务，获取URL信息
	$urlinfo=UrlAnalyzer::getInfo($task['url'],$task['level']);

	//如果返回状态为0，则检查网络
	if($urlinfo['code']==0){
		marknetwork();
	}

	//提交任务执行结果
	$response=TaskManager::submitTask($task,$urlinfo);

	echo "\n\n[".date("Y-m-d H:i:s")."] ";
	echo "Task Url: ".$task['url']."\n";
	echo "Type:".($task['type']=='new'? "New  ":"Update  ");
	echo "Level:".$task['level']."  ";
	$delay=time()-$task['time'];
	$h=intval($delay/3600);
	$m=intval(($delay-$h*3600)/60);
	$s=intval(($delay-$h*3600)%60);
	$delaystr="Delay: ".$h.' hours '.$m.' minutes '.$s." seconds\n";
	if($h>24)
		Util::echoRed($delaystr);
	elseif($h>12)
		Util::echoYellow($delaystr);
	else
		Util::echoGreen($delaystr);
	unset($delay);
	unset($delaystr);
	echo "------------------------------------------------------------------------------\n";
	if(!isset($urlinfo['error'])){
		echo "Url: ".$urlinfo['url']."\n";
		echo "Title: ".$urlinfo['title']."\n";
		echo "Charset: ".$urlinfo['charset']."\n";
		echo "Update Time: ".$response['updatetime']."\n";
		echo "New Links: ".$response['newlinks']."\n";
	}
	else if($urlinfo['code']==600){
		Util::echoYellow("Get urlinfo cancle: ".$urlinfo['error']."\n");
	}
	else{
		Util::echoRed("Get urlinfo error: ".$urlinfo['error']."\n");
	}
	echo "------------------------------------------------------------------------------\n";
	
	unset($urlinfo);
	unset($response);
}