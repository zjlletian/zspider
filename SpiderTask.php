<?php
include_once(dirname(__FILE__).'/Config.php');
include_once('UrlAnalyzer.class.php');
include_once('ESClient.class.php');
include_once('TaskManager.class.php');

//处理爬虫任务队列
while(true){
	$task=TaskManager::getSpiderTask();
	if($task!=null){
		handleSpiderTask($task);
	}
	else{
		sleep(2);
	}
}

//处理一个爬虫任务
function handleSpiderTask($task){
	echo "\n-------------------------".date("Y-m-d H:i:s")." Spider Task ( level ".$task['level']." )-------------------\n";
	$urlinfo=UrlAnalyzer::getInfo($task['url']);
	//当返回数据不为空时
	if($urlinfo['html']!=false){
		echo 'linkUrl: '.$task['url']."\n";
		echo 'trueUrl: '.$urlinfo['url']."\n";
		echo 'siteTitle:'.$urlinfo['title']."\n";

		//保存urlinfo到es
		ESClient::storeUrlInfo($urlinfo);

		//添加到更新队列,定时更新
		TaskManager::addUpdateTask($urlinfo['url'],$task['level'],$task['url']);
		
		//当level>0时，尝试将连接加入爬虫任务队列
		if($task['level']>0 && count($urlinfo['links'])>0){
			foreach ($urlinfo['links'] as $link) {
				TaskManager::addSpiderTask($link,$task['level']-1);
			}
		}
	}
	else{
		echo "process failed , code=".$urlinfo['code']."\n\n";	
	}
}