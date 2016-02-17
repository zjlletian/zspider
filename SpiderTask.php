<?php
include_once(dirname(__FILE__).'/Config.php');
include_once('UrlAnalyzer.class.php');
include_once('TaskManager.class.php');
include_once('Util.class.php');

//连接到mongodb中的队列，启动ack监控子进程与es转储子进程
TaskManager::init();

//处理爬虫任务队列
while(true){
	$task=TaskManager::getTask();
	if($task!=null){
		handleSpiderTask($task);
	}
	else{
		sleep(2);
	}
	unset($task);
}

//处理一个爬虫任务
function handleSpiderTask($task){
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
	//执行任务，获取URL信息
	$urlinfo=UrlAnalyzer::getInfo($task['url'],$task['level']);

	//提交任务执行结果
	$response=TaskManager::submitTask($task['_id'],$urlinfo);

	//显示执行结果
	if($urlinfo!=null){
		echo "Url: ".$urlinfo['url']."\n";
		echo "Title: ".$urlinfo['title']."\n";
		echo "Charset: ".$urlinfo['charset']."\n";
		echo "Update Time: ".$response['updatetime']."\n";
		echo "New Links: ".$response['newlinks']."\n";
	}
	echo "------------------------------------------------------------------------------\n";
	unset($urlinfo);
	unset($response);
}