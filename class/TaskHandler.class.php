<?php
require_once(dirname(dirname(__FILE__)).'/Config.php');

//捕获fatalError
function fatalErrorHandler(){
	$types=array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);
	$e = error_get_last();
	if(in_array($e['type'],$types)){
		$str="TaskHandler stop. message:".$e['message']." file:".$e['file']."(".$e['line'].")";
	}
	Util::putErrorLog($str);
}

class TaskHandler {

	//创建爬虫子进程
	static function createProgress() {
		$pid = pcntl_fork();
		if(!$pid) {
			register_shutdown_function('fatalErrorHandler');
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
				Util::putErrorLog("TaskHandler stop. Network is error.");
				exit();
			}
		}

		//保存url信息到ES
		if(!isset($urlinfo['error'])){
			EsOpreator::upsertUrlInfo($urlinfo);
		}

		//提交任务执行结果
		TaskManager::submitTask($task,$urlinfo);

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