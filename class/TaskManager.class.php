<?php
include_once(dirname(dirname(__FILE__)).'/Config.php');

class TaskManager {

	//mongodb客户端
	private static $mongo;

	//爬虫任务队列
	private static $taskQueue;

	//不更新的url列表
	private static $notUpdate;

	//正在处理的任务
	private static $onProcess;

	//任务日志
	private static $taskLog;

	//连接到mongodb
	static function connect(){
		if(self::$mongo==null) {
			self::$mongo = new Mongo($GLOBALS['MONGODB']);
			self::$taskQueue = self::$mongo->zspider->taskqueue;
			self::$notUpdate = self::$mongo->zspider->notupdate;
			self::$onProcess = self::$mongo->zspider->onprocess;
			self::$taskLog = self::$mongo->zspider->tasklog;
		}
		return true;
	}

	//启动任务队列
	static function initQueue(){
		Util::echoYellow("Init Zspider TaskQueue...\n");
		//连接到mongodb
		echo "Connect to MongoDB... ";
		self::connect();
		Util::echoGreen("[ok]\n");

		//创建mongodb索引
		self::$notUpdate->ensureIndex(array('url' => 1), array('unique' => true)); //url升序，唯一
		self::$taskQueue->ensureIndex(array('url' => 1), array('unique' => true)); //url升序，唯一
		self::$taskQueue->ensureIndex(array('time' => 1)); //time升序 早->晚
		self::$taskQueue->ensureIndex(array('type' => 1)); //type升序 new->update
		self::$taskLog->ensureIndex(array('time' => -1)); //time降序 晚->早
		self::$taskLog->ensureIndex(array('statu' => -1)); //statu降序 2->0
		self::$taskLog->ensureIndex(array('type' => 1)); //type升序 new->update

		//启动ack监视子进程
		self::srartAckWatcher();

		//添加默认起点任务
		foreach ($GLOBALS['DEFAULT_SITE'] as $level => $urls) {
			self::addNewTasks($urls,intval($level));
		}
	}

	//获取队列信息
	static function getQueueInfo(){
		$queueinfo=array();
		//正在处理的任务信息
		$queueinfo['onprocess'] = iterator_to_array(self::$onProcess->find());
		//等待爬取的网页总量
		$queueinfo['new_task'] = self::$taskQueue->count(array('type'=>'new'));
		//需要更新的网页总量
		$queueinfo['update_task'] = self::$taskQueue->count(array('type'=>'update','time'=>array('$lte'=>time())));
		//爬取过的网页总量
		$queueinfo['new_log'] = self::$taskLog->count(array('type'=>'new'));
		//近一小时内爬取的数量
		$queueinfo['speed1h'] = self::$taskLog->count(array('time'=>array('$gte'=>time()-3600)));
		return $queueinfo;
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
					if(self::$taskQueue->findOne(array('url'=>$task['url']))==null){
						unset($task['acktime']);
						self::$taskQueue->insert($task);
					}
					self::$onProcess->remove(array('_id'=>$task['_id']));
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

	//判断地址是否需要处理,返回处理等级，-1不需要 (用于redirect后的地址判断)
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
			//Util::echoYellow("find a new-type task with same url, process ahead.\n");
			self::$taskQueue->remove(array('_id'=>$task['_id']));
			return $task['level']>$level?$task['level']:$level;
		}
		else{
			return -1;
		}
	}

	//获取任务
	static function getTask(){
		//由于时间升序排列造成先进先出，形成广度优先遍历，深度越深，队列数据量大量上升，这里要注意磁盘的空间是否足够。
		$task=self::$taskQueue->findOne(array('time'=>array('$lte'=>time()))); //小余等于当前时间
		if($task!=null){
			self::$taskQueue->remove(array('_id'=>$task['_id']));
			//任务超时时间60秒，100秒后若没有响应则重新添加任务到队列中
			$task['acktime']=time()+100;
			self::$onProcess->insert($task);
		}
		return $task;
	}

	//提交任务执行结果，返回更新时间字符串
	static function submitTask($task,$urlinfo){
		$response = array();
		$url=empty($urlinfo['url'])?$task['url']:$urlinfo['url'];
		
		if(!isset($urlinfo['error'])){
			//对执行结果分配更新任务
			$response['updatetime']=self::addUpdateTask($urlinfo['url'],$urlinfo['level']);
			//当level>0时，尝试将连接加入爬虫任务队列
			$response['newlinks']=0;
			if($urlinfo['level']>0 && count($urlinfo['links'])>0){
				$response['newlinks']=self::addNewTasks($urlinfo['links'],$urlinfo['level']-1);
			}
			//记录任务日志
			self::$taskLog->insert(array('url'=>$url,'time'=>time(),'type'=>$task['type'],'statu'=>0));
		}
		else{
			if($urlinfo['code']==600){
				self::$taskLog->insert(array('url'=>$url,'time'=>time(),'type'=>$task['type'],'statu'=>1,'error'=>$urlinfo['error']));
			}
			else{
				self::$taskLog->insert(array('url'=>$url,'time'=>time(),'type'=>$task['type'],'statu'=>2,'error'=>$urlinfo['error']));
			}
		}
		//从正在执行的任务中移除
		self::$onProcess->remove(array('_id'=>$task['_id']));
		return $response;
	}

	//批量增加实时任务
	private static function addNewTasks($urls,$level){
		$count=0;
		foreach ($urls as $url) {
			//如果存在于不更新列表中则直接忽略
			if(self::$notUpdate->findOne(array('url'=>$url))!=null){
				continue;
			}
			//若不存在队列中或者手动强制添加，则加入新任务。若存在level大于队列中的level，则更新队列中的level
			$task=self::$taskQueue->findOne(array('url'=>$url));
			if($task==null) {
				self::$taskQueue->insert(array('url'=>$url,'level'=>$level,'time'=>time(),'type'=>'new'));
				$count++;
			}
			else if($task['level']<$level) { 
				self::$taskQueue->update($task,array('$set'=>array("level"=>$level)));
				$count++;
			}
		}
		return $count;
	}

	//对执行结果分配更新任务
	private static function addUpdateTask($url,$level){
		//排除不更新的连接
		foreach ($GLOBALS['NOTUPDATE_HAS'] as $notupdate) {
			if(strpos($url,$notupdate) !== false ){
				//添加到不更新列表中
				if(self::$notUpdate->findOne(array('url'=>$url))==null)
					self::$notUpdate->insert(array('url'=>$url));
				return 'will not update';
			}
		}
		//删除原有更新预约
		self::$taskQueue->remove(array("url" => $url));

		//分配更新时间
		if(isset($GLOBALS['SITE_UPDATE'][$url])){
			if(isset($GLOBALS['SITE_UPDATE'][$url]['time'])){
				$updatetime=time()+$GLOBALS['SITE_UPDATE'][$url]['time'];
			}
			if(isset($GLOBALS['SITE_UPDATE'][$url]['level'])){
				$level=$GLOBALS['SITE_UPDATE'][$url]['level'];
			}
		}
		else{
			$updatetime=time()+$GLOBALS['UPDATE_TIME'][$level.''];
		}
		self::$taskQueue->insert(array('url'=>$url,'level'=>$level,'time'=>$updatetime,'type'=>'update'));
		return date("Y-m-d H:i:s",$updatetime);
	}
}