<?php
include_once(dirname(dirname(__FILE__)).'/Config.php');

class TaskManager{

	//mongodb客户端
	private static $mongo;

	//爬虫任务队列
	private static $taskQueue;

	//不更新的url列表
	private static $notUpdate;

	//初始化连接
	private static function connect(){
		if(self::$mongo==null){
			self::$mongo = new Mongo($GLOBALS['MONGODB']);
			self::$taskQueue = self::$mongo->zspider->taskqueue;
			self::$notUpdate = self::$mongo->zspider->notupdate;
		}
		return true;
	}

	//检查是否可以进行处理
	static function isCanBeHandled($url) {
		self::connect();
		return self::$notUpdate->findOne(array('url'=>$url))!=null||self::$taskQueue->findOne(array('url'=>$url))!=null;
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
		if(in_array($url,$GLOBALS['SITE_UPDATE'])){
			$updatetime=time()+$GLOBALS['SITE_UPDATE'][$url]['time'];
			$level=$GLOBALS['SITE_UPDATE'][$url]['level'];
		}
		else{
			$updatetime=time()+$GLOBALS['UPDATE_TIME'];
		}
		self::$taskQueue->insert(array('url'=>$url,'level'=>$level,'time'=>$updatetime,'type'=>'update'));
		return date("Y-m-d H:i:s",$updatetime);
	}
	
	//获取最新的任务
	static function getSpiderTask(){
		self::connect();

		//由于时间升序排列造成先进先出，形成广度优先遍历，深度越深，队列数据量大量上升，这里要注意磁盘的空间是否足够。
		$task=self::$taskQueue->findOne(array('time'=>array('$lte'=>time())));
		if($task!=null){
			self::$taskQueue->remove($task);	
		}
		return $task;
	}
}