<?php
include_once(dirname(dirname(__FILE__)).'/Config.php');

class TaskHandler {

	//创建爬虫子进程
	static function createProgress() {
		$pid = pcntl_fork();
		if(!$pid) {
			self::runTask();
		}
		else{
			return $pid;
		}
	}

	//爬虫子进程
	private static function runTask(){
		//连接到mysql中的任务队列
		TaskManager::connect();
		//连接到ES
		ESConnector::connect();
		//循环获取任务
		while(true){
		    $task=TaskManager::getTask();
			if($task!=null){
				self::handleTask($task);
			}
			else{
				sleep(2);
			}
		}
	}

	//执行爬虫任务
	private static function handleTask($task){
		//解析URL信息
		$urlinfo=UrlAnalyzer::getInfo($task['url'],$task['level']);

		//如果返回状态为0，则检查网络
		if($urlinfo['code']==0){
			if(Util::isNetError()){
				Util::echoRed("Network is error.\n");
				exit();
			}
		}

		//保存url信息到ES
		if(!isset($urlinfo['error'])){
			EsOpreator::upsertUrlInfo($urlinfo);
		}

		//提交任务执行结果
		$response=TaskManager::submitTask($task,$urlinfo);
		unset($urlinfo);
		unset($response);
	}
}