<?php
include_once(dirname(dirname(__FILE__)).'/Config.php');

class TaskManager{

	//mongodb客户端
	private static $mongocli;

	//爬虫任务队列
	private static $spiderTask;

	//更新任务队列
	private static $updateTask;

	//最近更新过的任务
	private static $currentTask;

	//初始化连接
	private static function connect(){
		if(self::$mongocli==null){
			self::$mongocli = new Mongo($GLOBALS['MONGODB']);
			self::$spiderTask=self::$mongocli->zsearch->spiderTask;
			self::$updateTask=self::$mongocli->zsearch->updateTask;
			self::$currentTask=self::$mongocli->zsearch->currentTask;
		}
		return true;
	}

	//增加爬虫任务
	static function addSpiderTask($url,$level,$forceadd=false){
		self::connect();
		$task=self::$spiderTask->findOne(array('url'=>$url));

		//若存在任务且level大于队列中的level
		if($task!=null){ 
			if($task['level']<$level){
				self::$spiderTask->update($task,array('$set'=> array("level" => $level)));
			}
		}
		//若不存任务切最近未更新过，则添加任务
		elseif(self::$currentTask->findOne(array("url" => $url))==null||$forceadd){
			self::$spiderTask->insert(array('url'=>$url,'level'=>$level));
		}
	}
	
	//获取未处理的爬虫任务
	static function getSpiderTask(){
		self::connect();
		$task=self::$spiderTask->findOne();
		if($task!=null){
			self::$spiderTask->remove($task, array("justOne" => true));
			return $task;
		}
		return null;
	}

	//添加更新任务
	static function addUpdateTask($url,$level,$fromurl){
		self::connect();

		//添加到最近更新过的历史任务中
		if($url!=$fromurl){
			$fromurltask=self::$currentTask->findOne(array("url" => $fromurl));
			if($fromurltask!=null){
				self::$currentTask->remove($fromurltask, array("justOne" => true));
			}
			self::$currentTask->insert(array('url'=>$fromurl));
		}
		$urltask=self::$currentTask->findOne(array("url" => $url));
		if($urltask!=null){
			self::$currentTask->remove($urltask, array("justOne" => true));
		}
		self::$currentTask->insert(array('url'=>$url));

		//删除原有更新任务
		$task=self::$updateTask->findOne(array("url" => $url));
		if($urltask!=null){
			self::$updateTask->remove($urltask, array("justOne" => true));
		}

		//只对level>0的站点进行更新
		if($level>0){
			if(!in_array($url, $GLOBALS['UPDATE_SITE'])){
				$updatetime=time()+$GLOBALS['UPDATE_CYCLE'];	
			}
			else{
				$updatetime=time()+$GLOBALS['UPDATE_SITE'][$url]['time'];
				$level=$GLOBALS['UPDATE_SITE'][$url]['level'];
			}
			self::$updateTask->insert(array('url'=>$url,'level'=>$level,'time'=>$updatetime));
			echo "updatetime: ".date("Y-m-d H:i:s",$updatetime)."\n";
		}
	}

	//获取未处理的更新任务
	static function getUpdateTask(){
		self::connect();
		$task=self::$updateTask->findOne();
		if($task!=null){
			self::$updateTask->remove($task, array("justOne" => true));
			return $task;
		}
		return null;
	}
}