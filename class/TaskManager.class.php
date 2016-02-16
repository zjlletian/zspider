<?php
include_once(dirname(dirname(__FILE__)).'/Config.php');
include_once('EsConnector.class.php');
include_once('Util.class.php');

define('ES_INDEX','zspider');
define('ES_TYPE','websites');

class TaskManager {

	//mongodb客户端
	private static $mongo;

	//爬虫任务队列
	private static $taskQueue;

	//不更新的url列表
	private static $notUpdate;

	//正在处理的任务
	private static $onProcess;

	//正在转储的队列
	private static $transport;

	//主进程pid, 转储进程pid, ack监视进程pid
	private static $mainPid;
	private static $esPid;
	private static $ackPid;

	//初始化
	static function init(){
		if(self::$mongo==null) {
			Util::echoYellow("Zspider Init...\n");
			//连接mongodb
			echo "Connect to TaskQueue on MongoDB... ";
			self::$mongo = new Mongo($GLOBALS['MONGODB']);
			self::$taskQueue = self::$mongo->zspider->taskqueue;
			self::$notUpdate = self::$mongo->zspider->notupdate;
			self::$onProcess = self::$mongo->zspider->onprocess;
			self::$transport = self::$mongo->zspider->transport;
			Util::echoGreen("[ok]\n");

			//启动转储进程与ack监视进程
			self::$esPid=self::startEsTransport();
			self::$ackPid=self::srartAckWatcher();

			//获取主进程pid
			self::$mainPid=posix_getpid();
			Util::echoYellow("ZSpider start, mainPid:".self::$mainPid.", esPid:".self::$esPid.", ackPid:".self::$ackPid."\n");
		}
		return true;
	}

	//创建用于从mongo到es的异步同步数据的子进程
	private static function startEsTransport(){
		//链接到es
		echo "Connect to ElasticSearch... ";
		if(ESConnector::connect()){
			Util::echoGreen("[ok]\n");
		}
		else{
			Util::echoRed("[failed]\n");
			exit();
		}
		//创建子进程
		echo "Fork data transport progress... ";
		$pid = pcntl_fork();
		if ($pid == -1) {
			Util::echoRed("[failed]\n");
			exit();
		}
		else if(!$pid) {
		    while(true) {
		    	$urlinfo=self::$transport->findOne();
				if($urlinfo!=null){
					self::$transport->remove($urlinfo);
					unset($urlinfo['_id']);
					$upsert = $urlinfo;
					$upsert['view'] = 0;
					EsConnector::updateDocByDoc(ES_INDEX,ES_TYPE,md5($urlinfo['url']),$urlinfo,$upsert);
					unset($upsert);
					unset($urlinfo);
				}
				else{
					usleep(100000); //100毫秒
				}
			}
		}
		else{
			Util::echoGreen("[ok]\n");
		}
		return $pid;
	}

	//创建用于检测ack的超时子进程，将超时的task重新加入队列中
	private static function srartAckWatcher(){
		echo "Fork ack watcher progress... ";
		$pid = pcntl_fork();
		if ($pid == -1) {
			Util::echoRed("[failed]\n");
			exit();
		}
		else if(!$pid) {
		    while(true) {
				$task=self::$onProcess->findOne(array('acktime'=>array('$lte'=>time())));
				if($task!=null){
					self::$onProcess->remove($task);
					if(self::$taskQueue->findOne(array('url'=>$task['url']))==null){
						unset($task['acktime']);
						self::$taskQueue->insert($task);
					}
					unset($task);
				}
				else{
					sleep(2);
				}
				unset($task);
			}
		}
		else{
			Util::echoGreen("[ok]\n");
		}
		return $pid;
	}

	//增加实时任务
	static function addNewTask($url,$level){
		//如果存在于不更新列表中则直接返回
		if(self::$notUpdate->findOne(array('url'=>$url))!=null){
			return false;
		}

		//若不存在队列中或者手动强制添加，则加入新任务。
		$task=self::$taskQueue->findOne(array('url'=>$url));
		if($task==null) {
			self::$taskQueue->insert(array('url'=>$url,'level'=>$level,'time'=>time(),'type'=>'new'));
			return true;
		}
		//若存在level大于队列中的level，则更新队列中的level
		if($task['level']<$level) { 
			self::$taskQueue->update($task,array('$set'=>array("level"=>$level)));
			return true;
		}
		return false;
	}

	//增加更新任务
	static function addUpdateTask($url,$level){
		//排除不更新的连接
		foreach ($GLOBALS['NOTUPDATE_HAS'] as $notupdate) {
			if(strpos($url,$notupdate) !== false ){
				if(self::$notUpdate->findOne(array('url'=>$url))==null)
					self::$notUpdate->insert(array('url'=>$url));
				return 'will not update';
			}
		}
		//删除原有更新预约
		self::$taskQueue->remove(array("url" => $url));

		//分配默认更新时间
		if(isset($GLOBALS['SITE_UPDATE'][$url])){
			if(isset($GLOBALS['SITE_UPDATE'][$url]['time']))
				$updatetime=time()+$GLOBALS['SITE_UPDATE'][$url]['time'];
			if(isset($GLOBALS['SITE_UPDATE'][$url]['level']))
				$level=$GLOBALS['SITE_UPDATE'][$url]['level'];
		}
		else{
			$updatetime=time()+$GLOBALS['UPDATE_TIME'];
		}
		self::$taskQueue->insert(array('url'=>$url,'level'=>$level,'time'=>$updatetime,'type'=>'update'));
		return date("Y-m-d H:i:s",$updatetime);
	}

	//获取任务
	static function getTask(){

		//由于时间升序排列造成先进先出，形成广度优先遍历，深度越深，队列数据量大量上升，这里要注意磁盘的空间是否足够。
		$task=self::$taskQueue->findOne(array('time'=>array('$lte'=>time())));
		if($task!=null){
			self::$taskQueue->remove($task);
			//任务超时时间60秒，100秒后若没有响应则重新添加任务到队列中
			$task['acktime']=time()+100;
			self::$onProcess->insert($task);
		}
		return $task;
	}

	//任务ack
	static function ackTask($task){
		self::$onProcess->remove($task);
		return true;
	}
	
	//判断地址是否需要处理，返回处理等级，-1不需要
	static function isHandled($url,$level) {
		//如果存在于不更新列表或者正在处理的列表中，标记为已处理
		if(self::$notUpdate->findOne(array('url'=>$url))!=null||self::$onProcess->findOne(array('url'=>$url))!=null){
			return -1;
		}
		$task=self::$taskQueue->findOne(array('url'=>$url));
		//如果不存在任务，标记为未处理
		if($task==null){
			return $level;
		}
		//如果存在new任务或者level较小的update任务，则删除任务，返回较大的level（相当于提前处理）
		if($task!=null && ($task['type']=='new' || $task['level']<$level)){
			Util::echoYellow("find a new-type task with same url, process ahead.\n");
			self::$taskQueue->remove($task);
			return $task['level']>$level?$task['level']:$level;
		}
		else{
			return -1;
		}
	}

	//将urlInfo保存到mongo中
	static function saveUrlInfo($urlinfo){
		$urlinfo['time'] = date("Y-m-d H:i:s");
		unset($urlinfo['code']);
		unset($urlinfo['links']);
		unset($urlinfo['level']);

		self::$transport->insert($urlinfo);
	}
}