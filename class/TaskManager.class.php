<?php
include_once(dirname(dirname(__FILE__)).'/Config.php');
include_once('Util.class.php');

class TaskManager{

	//mongodb客户端
	private static $mongo;

	//爬虫任务队列
	private static $taskQueue;

	//不更新的url列表
	private static $notUpdate;

	//正在处理的队列
	private static $onProcess;

	//初始化连接
	private static function connect(){
		if(self::$mongo==null) {
			//连接mongodb
			self::$mongo = new Mongo($GLOBALS['MONGODB']);
			self::$taskQueue = self::$mongo->zspider->taskqueue;
			self::$notUpdate = self::$mongo->zspider->notupdate;
			self::$onProcess = self::$mongo->zspider->onprocess;

			//创建子进程，用于检测ack的超时，将超时的task重新加入队列中
			$pid = pcntl_fork();
			if ($pid == -1) {
				Util::echoRed('Could not fork ackMonitor');
				exit();
			}
			elseif(!$pid) {
			    while(true) {
					$task=self::$onProcess->findOne(array('acktime'=>array('$lte'=>time())));
					if($task!=null){
						self::$onProcess->remove($task);
						unset($task['acktime']);
						self::$taskQueue->insert($task);
					}
					else{
						sleep(2);
					}
					unset($task);
				}
			}
		}
		return true;
	}

	//检查是否可以进行处理
	static function isCanBeHandled($url) {
		self::connect();
		$ary=array('url'=>$url);
		return self::$taskQueue->findOne($ary)!=null||self::$notUpdate->findOne($ary)!=null||self::$onProcess->findOne($ary)!=null;
	}

	//增加实时任务
	static function addNewTask($url,$level,$force=false){
		self::connect();
		
		//如果存在于不更新列表中则直接返回
		if(self::$notUpdate->findOne(array('url'=>$url))!=null){
			return false;
		}

		//若不存在队列中或者手动强制添加，则加入新任务。
		$task=self::$taskQueue->findOne(array('url'=>$url));
		if($task==null || $force) {
			self::$taskQueue->insert(array('url'=>$url,'level'=>$level,'time'=>time(),'type'=>'new'));
			return true;
		}
		//若存在新任务且level大于队列中的level，则更新队列中的level
		if($task['type']='new' && $task['level']<$level) { 
			self::$taskQueue->update($task,array('$set'=>array("level"=>$level)));
			return true;
		}
		return false;
	}

	//增加更新任务
	static function addUpdateTask($url,$level){
		self::connect();

		//排除不更新的连接
		foreach ($GLOBALS['NOTUPDATE_WITH'] as $notupdate) {
			if(strpos($url,$notupdate) !== false ){
				self::$notUpdate->remove(array('url'=>$url));
				self::$notUpdate->insert(array('url'=>$url));
				return 'will not update';
			}
		}

		//删除原有更新预约
		self::$taskQueue->remove(array("url" => $url));

		//分配更新时间
		$updatetime=time()+$GLOBALS['UPDATE_TIME'];
		
		if(isset($GLOBALS['SITE_UPDATE'][$url])){
			if(isset($GLOBALS['SITE_UPDATE'][$url]['time']))
				$updatetime=time()+$GLOBALS['SITE_UPDATE'][$url]['time'];
			if(isset($GLOBALS['SITE_UPDATE'][$url]['level']))
				$level=$GLOBALS['SITE_UPDATE'][$url]['level'];
		}
		self::$taskQueue->insert(array('url'=>$url,'level'=>$level,'time'=>$updatetime,'type'=>'update'));
		return date("Y-m-d H:i:s",$updatetime);
	}
	
	//获取任务
	static function getTask(){
		self::connect();

		//由于时间升序排列造成先进先出，形成广度优先遍历，深度越深，队列数据量大量上升，这里要注意磁盘的空间是否足够。
		$task=self::$taskQueue->findOne(array('time'=>array('$lte'=>time())));
		if($task!=null){
			self::$taskQueue->remove($task);
			//任务超时时间60秒，60秒后若没有响应则重新添加任务到队列中
			$task['acktime']=time()+60;
			self::$onProcess->insert($task);
		}
		return $task;
	}

	//任务ack
	static function ackTask($task){
		self::connect();

		self::$onProcess->remove($task);
		return true;
	}
}