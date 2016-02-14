<?php
include_once(dirname(__FILE__).'/Config.php');
include_once('UrlAnalyzer.class.php');
include_once('UrlInfo.class.php');
include_once('TaskManager.class.php');
include_once('Util.class.php');

TaskManager::addNewTask('http://www.baidu.com',1);

//处理爬虫任务队列
while(true){
	$task=TaskManager::getTask();
	if($task!=null){
		handleSpiderTask($task);
		TaskManager::ackTask($task);
	}
	else{
		sleep(2);
	}
}

//处理一个新爬虫任务
function handleSpiderTask($task){
	if($task['type']=='new')
		echo "\n----------------------------- New Task -----------------------------\n";
	else
		echo "\n---------------------------- Update Task ---------------------------\n";
	
	echo "Time: ".date("Y-m-d H:i:s")."  ";
	$delay=time()-$task['time'];
	$h=intval($delay/3600);
	$m=intval(($delay-$h*3600)/60);
	$s=intval(($delay-$h*3600)%60);
	if($h>24)
		Util::echoRed("Delay: ".$h.' hours '.$m.' minutes '.$s." seconds\n");
	elseif($h>12)
		Util::echoYellow("Delay: ".$h.' hours '.$m.' minutes '.$s." seconds\n");
	else
		Util::echoGreen("Delay: ".$h.' hours '.$m.' minutes '.$s." seconds\n");
	echo "Level: ".$task['level']."\n";
	echo "TaskUrl: ".$task['url']."\n";
	
	//获取URL信息
	$urlinfo=UrlAnalyzer::getInfo($task['url']);
	//当返回数据不为空时
	if($urlinfo['html']!=false){
		//保存urlinfo
		UrlInfo::saveUrlInfo($urlinfo);
		//添加更新任务
		$updatetime=TaskManager::addUpdateTask($urlinfo['url'],$task['level']);

		Util::echoGreen("Process result: \n");
		echo 'Url: '.$urlinfo['url']."\n";
		echo 'Title: '.$urlinfo['title']."\n";
		echo 'Charset: '.$urlinfo['charset']."\n";
		echo 'Updatetime: '.$updatetime."\n";

		//当level>0时，尝试将连接加入爬虫任务队列
		if($task['level']>0 && count($urlinfo['links'])>0){
			foreach ($urlinfo['links'] as $link) {
				TaskManager::addNewTask($link,$task['level']-1);
			}
		}
	}
	echo "------------------------------------------------------------------------------\n\n";
}