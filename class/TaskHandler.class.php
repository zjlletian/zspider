<?php
require_once(dirname(dirname(__FILE__)).'/Config.php');

//捕获fatalError
function fatalErrorHandler(){
	$types=array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);
	$e = error_get_last();
	if(in_array($e['type'],$types)){
		$str="TaskHandler stop on dealing with url:".TaskHandler::$dealingTask['url'];
		Util::putErrorLog($str);
		$str="Message:".$e['message'];
		Util::putErrorLog($str);
		$str="File:".$e['file']."(".$e['line'].")\r\n";
		Util::putErrorLog($str);
		Util::echoRed("[".date("Y-m-d H:i:s")."] Fatal Error on dealing with ".TaskHandler::$dealingTask['url']."\n");
		Util::echoRed("[".date("Y-m-d H:i:s")."] Error Message:".$e['message']."\n");
	}
}

class TaskHandler {

	//正在处理的任务
	static $dealingTask;

	//创建爬虫子进程
	static function createProgress() {
		$pid = pcntl_fork();
		if(!$pid) {
			register_shutdown_function('fatalErrorHandler');
			error_reporting(0);
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

		//连接到ES,创建索引
		EsOpreator::initIndex();

		//循环获取任务
		while(true){
		    $task=TaskManager::getTask();
			if($task!=null){
				self::$dealingTask=$task;
				self::handleTask($task);
			}
			else{
				sleep(2);
			}
		}
	}

	//执行爬虫任务
	private static function handleTask($task){

		//设置单任务最大执行时间
		set_time_limit(120);

		//解析URL信息
		$urlinfo=UrlAnalyzer::getInfo($task['url'],$task['level']);

		//如果返回状态为0，则检查网络
		if($urlinfo['code']==0){
			if(Util::isNetError()){
				$str="TaskHandler stop on dealing with url: ".self::$dealingTask['url'];
				Util::putErrorLog($str);
				$str="Message:Network is error.\r\n";
				Util::putErrorLog($str);
				Util::echoRed("[".date("Y-m-d H:i:s")."] Network Error on dealing with ".TaskHandler::$dealingTask['url']."\n");
				exit();
			}
		}

		//保存url信息到ES
		if(!isset($urlinfo['error'])){
			EsOpreator::upsertUrlInfo($urlinfo);
		}

		//提交任务执行结果
		TaskManager::submitTask($task,$urlinfo);
		set_time_limit(0);
		
		//记录任务日志
		if(!isset($urlinfo['error'])){
			$log['url']=$urlinfo['url'];
			$log['level']=$urlinfo['level'];
			$log['type']=$task['type']==0?"New":"Update";
			$logtype="suc";
		}
		elseif($urlinfo['code']==600){
			$log['url']=$task['url'];
			$log['level']=$task['level'];
			$log['type']=$task['type']==0?"New":"Update";
			$log['message']=$urlinfo['error'];
			$logtype="cancel";
		}
		else{
			$log['url']=$task['url'];
			$log['level']=$task['level'];
			$log['type']=$task['type']==0?"New":"Update";
			$log['code']=$urlinfo['code'];
			$logtype="error";
		}
		EsOpreator::putLog($log,$logtype);
		unset($log);
		unset($urlinfo);
		unset($response);
	}
}