<?php
require_once(dirname(dirname(__FILE__)).'/Config.php');

class TaskHandler {

	//正在处理的任务
	static $dealingTask;
	static $pid;

	//创建爬虫子进程
	static function createProgress($hash) {
		$pid = pcntl_fork();
		if(!$pid) {
			self::$pid=posix_getpid();
			register_shutdown_function('fatalErrorHandler');
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
		Storager::initIndex();

		//循环获取任务
		while(true){
			Util::writePid(self::$pid);
			$now=microtime(true);
		    $task=TaskManager::getTask($hash);
			if($task!=null){
				self::$dealingTask=$task;
				self::handleTask($task,$now);
			}
			else{
				if($GLOBALS['DEBUG']){
					Util::echoYellow("Get null task in offset ".$hash."\n");
				}
				$hash=$hash+mt_rand(10,20);
				if($hash>=300){
					$hash=0;
					sleep(5);
				}
			}
		}
	}

	//执行爬虫任务
	private static function handleTask($task,$now){
		$gettasktime=round(microtime(true)-$now,3);
		//解析URL信息
		$urlinfo=UrlAnalyzer::getInfo($task['url'],$task['level']);

		//如果返回状态为0，则检查网络
		if($urlinfo['code']==0){
			if(Util::isNetError()){
				Util::putErrorLog("TaskHandler stop, url: ".self::$dealingTask['url']);
				Util::putErrorLog("Message: Network is error.\r\n");
				Util::echoRed("[".date("Y-m-d H:i:s")."] Network Error on dealing with ".TaskHandler::$dealingTask['url']."\n");
				exit();
			}
		}

		//保存url信息到ES,最多尝试五次
		$savenow=microtime(true);
		if(!isset($urlinfo['error'])){
            $savesuc=false;
            for($count=0;$count<5;$count++){
				$savesuc=Storager::upsertUrlInfo($urlinfo)!=false;
                if($savesuc){
                    break;
                }
            }
			if($savesuc==false){
                if(!ESConnector::testConnect()){
                    Util::putErrorLog("TaskHandler stop, url: ".self::$dealingTask['url']);
                    Util::putErrorLog("Message: Elasticsearch disconnect.\r\n");
                    Util::echoRed("[".date("Y-m-d H:i:s")."] Elasticsearch disconnect on dealing with ".TaskHandler::$dealingTask['url']."\n\n");
                    exit();
                }
                else{
                    $str="Save url info to elasticsearch failed, url: ".$task['url'];
                    Util::putErrorLog($str);
                    Util::echoRed("[".date("Y-m-d H:i:s")."] ".$str."\n");

                    $str="Message: Save url info to elasticsearch failed.";
                    Util::putErrorLog($str."\r\n");
                    Util::echoRed("[".date("Y-m-d H:i:s")."] ".$str."\n\n");

                    $urlinfo['error']='save url info to elasticsearch failed.';
                    $urlinfo['code']=900;
                }
            }
		}
		$savetime=round(microtime(true)-$savenow,3);
		$proctime=round(microtime(true)-$now,3);

		//提交任务执行结果,记录日志到ES
		$submitnow=microtime(true);
		if(TaskManager::submitTask($task,$urlinfo)){
			$submittime=round(microtime(true)-$submitnow,3);
			$totaltime=round(microtime(true)-$now,3);
			$lognow=microtime(true);
			$log['url']=$task['url'];
			$log['type']=$task['type']==0? "New":"Update";
			$log['level']=$task['level'];
			$log['spider']=$task['spider'];
			if(!isset($urlinfo['error'])){
				$log['url']=$urlinfo['url'];
				$log['level']=$urlinfo['level'];
				$log['newlinks']=count($urlinfo['links']);
				$log['timeinfo']=$urlinfo['timeinfo'];
				$log['timeinfo']['gettask']=$gettasktime;
				$log['timeinfo']['saveinfo']=$savetime;
				$log['timeinfo']['submit']=$submittime;
				$log['timeinfo']['total']=$totaltime;
				$logtype="success";
			}
			else{
				$log['error']=$urlinfo['error'];
				$log['timeinfo']['total']=$totaltime;
				$log['timeinfo']['submit']=$submittime;
				$logtype="error";
			}
			Storager::putLog($log,$logtype);
			$logtime=round(microtime(true)-$lognow,3);

			if(!isset($urlinfo['error']) && $GLOBALS['DEBUG']){
				echo "Size:".round(strlen($urlinfo['text'])/1024,2)."KB Links:".count($urlinfo['links'])." Url: ".$task['url']."\n";
				echo "Gettask:".$gettasktime."s download:".$urlinfo['timeinfo']['download']."s extarct:".$urlinfo['timeinfo']['extarct']."s findhref:".$urlinfo['timeinfo']['findlinks']."s saveinfo:".$savetime."s\n";
				
				$sum=$proctime+$submittime+$logtime;
				echo "Proc:".$proctime."s(".round($proctime/$sum*100,1)."%) Submit:".$submittime."s(".round($submittime/$sum*100,1)."%) Log:".$logtime."s(".round($logtime/$sum*100,1)."%)\n\n";
			}
		}
		unset($log);
		unset($urlinfo);
	}
}

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
