<?php
require_once(dirname(dirname(__FILE__)).'/Config.php');

//捕获fatalError
function fatalErrorHandler(){
	$types=array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);
	$e = error_get_last();
	if(in_array($e['type'],$types)){
		$str="Fatal Error, url: ".TaskHandler::$dealingTask['url'];
		Util::putErrorLog($str);
		Util::echoRed("[".date("Y-m-d H:i:s")."] ".$str."\n");

		$str="Message: ".$e['message'];
		Util::putErrorLog($str);
		Util::echoRed("[".date("Y-m-d H:i:s")."] ".$str."\n");

		$str="File: ".$e['file']." (line ".$e['line'].")";
		Util::putErrorLog($str."\r\n");
		Util::echoRed("[".date("Y-m-d H:i:s")."] ".$str."\n\n");
	}
}

class TaskHandler {

	//正在处理的任务
	static $dealingTask;

	//创建爬虫子进程
	static function createProgress($hash) {
		$pid = pcntl_fork();
		if(!$pid) {
			register_shutdown_function('fatalErrorHandler');
			//error_reporting(0);
			self::runTask($hash);
		}
		else{
			return $pid;
		}
	}

	//爬虫子进程
	private static function runTask($hash){
		//连接到mysql中的任务队列
		TaskManager::connect();

		//连接到ES,创建索引
		EsOpreator::initIndex();

		//循环获取任务
		while(true){
		    $task=TaskManager::getTask($hash);
			if($task!=null){
				self::$dealingTask=$task;
				//设置单个最长任务时间，防止任务卡死（该函数时间不包括调用系统函数时间，调用数据库或sleep时间）
				set_time_limit(120);
				self::handleTask($task);
				set_time_limit(0);
			}
			else{
				$hash=($hash+mt_rand(10,20))%300;
				$hash=$hash>=300 ? 0:$hash;
				sleep(2);
			}
		}
	}

	//执行爬虫任务
	private static function handleTask($task){
		
		$now=microtime(true);

		//解析URL信息
		$urlinfo=UrlAnalyzer::getInfo($task['url'],$task['level']);

		//如果返回状态为0，则检查网络
		if($urlinfo['code']==0){
			if(Util::isNetError()){
				$str="TaskHandler stop, url: ".self::$dealingTask['url'];
				Util::putErrorLog($str);
				$str="Message: Network is error.\r\n";
				Util::putErrorLog($str);
				Util::echoRed("[".date("Y-m-d H:i:s")."] Network Error on dealing with ".TaskHandler::$dealingTask['url']."\n");
				exit();
			}
		}

		//保存url信息到ES
		if(!isset($urlinfo['error'])){
			EsOpreator::upsertUrlInfo($urlinfo);
		}

		//提交任务执行结果,记录日志到ES
		$log['url']=$task['url'];
		$log['type']=$task['type']==0? "New":"Update";
		$log['level']=$task['level'];
		$log['spider']=$task['spider'];
		$totaltime=round(microtime(true)-$now,3);
		if(TaskManager::submitTask($task,$urlinfo)){
			if(!isset($urlinfo['error'])){
				$log['url']=$urlinfo['url'];
				$log['level']=$urlinfo['level'];
				$urlinfo['timeinfo']['total']=
				$log['timeinfo']=$urlinfo['timeinfo'];
				$log['timeinfo']['total']=$totaltime;
				$logtype="success";
			}
			else{
				$log['error']=$urlinfo['error'];
				$log['timeinfo']['total']=$totaltime;
				$logtype="error";
			}
		}
		else{
			$log['timeinfo']['total']=$totaltime;
			$log['timeinfo']['max']=$task['acktime']-$task['proctime'];
			$logtype="timeout";
		}
		EsOpreator::putLog($log,$logtype);
		unset($log);
		unset($urlinfo);
	}
}
