<?php
include_once(dirname(__FILE__).'/Config.php');
include_once('UrlAnalyzer.class.php');
include_once('TaskManager.class.php');
include_once('Util.class.php');

//任务终止时调用
function shutdown_function(){
	
}
register_shutdown_function('shutdown_function');

//连接到mongodb中的队列，启动ack监控子进程与es转储子进程
TaskManager::init();

//添加默认起点任务
TaskManager::addNewTask('https://www.baidu.com/',2);
TaskManager::addNewTask('http://www.ifeng.com/',2);

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
	unset($task);
}

//处理一个爬虫任务
function handleSpiderTask($task) {
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

	//获取URL信息
	$urlinfo=UrlAnalyzer::getInfo($task['url'],$task['level']);
	//当返回数据不为空时
	if($urlinfo['html']!=null){
		
		//保存urlinfo
		TaskManager::saveUrlInfo($urlinfo);
		echo 'Url: '.$urlinfo['url']."\n";
		echo 'Title: '.$urlinfo['title']."\n";
		echo 'Charset: '.$urlinfo['charset']."\n";

		//添加更新任务
		echo 'UpdateTime: '.TaskManager::addUpdateTask($urlinfo['url'],$urlinfo['level'])."\n";

		//当level>0时，尝试将连接加入爬虫任务队列
		if($urlinfo['level']>0 && count($urlinfo['links'])>0){
			foreach ($urlinfo['links'] as $link) {
				TaskManager::addNewTask($link,$urlinfo['level']-1);
			}
		}
	}
	unset($UrlInfo);
	echo "------------------------------------------------------------------------------\n";
}