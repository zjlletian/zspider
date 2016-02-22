<?php
include_once(dirname(dirname(__FILE__)).'/Config.php');

class TaskManager {

	//mysql连接
	static private $mysqli;

	//连接到Mysql,如果是cli模式则建立持久连接，如果是web模式则建立短连接
	static function connect(){
		if(php_sapi_name()=='cli'){
			$GLOBALS['MYSQL']['host']="p:".$GLOBALS['MYSQL']['host'];
		}
		self::$mysqli=new mysqli();
		self::$mysqli->connect($GLOBALS['MYSQL']['host'],$GLOBALS['MYSQL']['user'],$GLOBALS['MYSQL']['passwd'],$GLOBALS['MYSQL']['db'],$GLOBALS['MYSQL']['port']);
		if(self::$mysqli->connect_error!=null){
			exit();
		}
		return true;
	}

	//启动任务队列 (for queuewacther)
	static function srartQueueWatcher(){
		//连接到mysql
		Util::echoYellow("Init Zspider QueueWatcher...\n");
		self::connect();

		//添加默认起点任务
		foreach ($GLOBALS['DEFAULT_SITE'] as $level => $urls) {
			self::addNewTasks($urls,intval($level));
		}

		//将超时的task重新加入队列中
		Util::echoGreen("Queue Watcher is now on running.\n");
		while(true) {
			$result=self::$mysqli->query("select * from onprocess where acktime<=".time()." limit 1");
			if($result->num_rows>0){
				$task =mysqli_fetch_assoc($result);
				$url =self::$mysqli->escape_string($task['url']);
				self::$mysqli->begin_transaction();
				self::$mysqli->query("insert ignore into taskqueue values(null,'".$url."',".$task['level'].",".$task['time'].",".$task['type'].")");
				self::$mysqli->query("delete from onprocess where id=".$task['id']." limit 1");
				self::$mysqli->commit();
				echo "\n[".date("Y-m-d H:i:s")."] Find a taskack out of time.\n";
				echo "------------------------------------------------------------------------------------------------------------\n";
				Util::echoYellow("Type: ".($task['type']==0?"New":"Update")."   Level: ".$task['level']."\n");
				Util::echoYellow($task['url']."\n");
				echo "------------------------------------------------------------------------------------------------------------\n";
			}
			else{
				sleep(2);
			}
			$result->free();
		}
	}

	//获取一个达到时间的爬虫任务
	static function getTask(){
		//时间升序排列形成广度优先遍历，深度越深队列数据量越大,查询速度变慢，时间降序排列会形成深度优先遍历，刚加进来的新任务就会被马上处理，但老任务会积压
		self::$mysqli->begin_transaction();
		$result = self::$mysqli->query("select * from taskqueue where time<=".time()." order by time limit 1");
		$task=mysqli_fetch_assoc($result);
		if($result->num_rows!=0){
			//删除任务
			self::$mysqli->query("delete from taskqueue where id=".$task['id']." limit 1");
			//任务超时时间100秒，100秒后若没有响应则重新添加任务到队列中
			$url =self::$mysqli->escape_string($task['url']);
			$acktime=time()+100;
			self::$mysqli->query("insert into onprocess values(null,'".$url."',".$task['level'].",".$task['time'].",".$task['type'].",".$acktime.")");
			$task['id']=self::$mysqli->insert_id;
		}
		//如果获取到任务并且事务提交成功，则返回任务
		if(self::$mysqli->commit() && $result->num_rows!=0){
			$result->free();
			return $task;
		}
		else{
			$result->free();
			return null;
		}
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
		}
		//从正在执行的任务中移除
		self::$mysqli->query("delete from onprocess where id=".$task['id']);
		return $response;
	}

	//判断地址是否需要处理,用于redirect后的地址判断，返回处理等级，-1不需要
	static function isHandled($url,$level) {
		$url=self::$mysqli->escape_string($url);

		//如果存在于不更新列表或者正在处理的列表中，标记为已处理
		$sql1="select * from notupdate where url='".$url."' limit 1";
		$sql2="select * from onprocess where url='".$url."' limit 1";
		if(self::$mysqli->query($sql1)->num_rows>0||self::$mysqli->query($sql2)->num_rows>0){
			return -1;
		}

		//判断是否存在于队列中
		$result = self::$mysqli->query("select * from taskqueue where url='".$url."' limit 1");
		$task=mysqli_fetch_assoc($result);
		$result->free();
		//如果不存在任务，标记为未处理。如果存在level较小的new任务，则删除任务，返回较大的level。如果存在level较小的update任务，则提升level
		if(!$task){
			return $level;
		}
		else if($task['type']=='0' && $task['level']<$level){
			self::$mysqli->query("delete from taskqueue where id=".$task['id']." limit 1");
			return $task['level']>$level?$task['level']:$level;
		}
		else if($task['type']=='0' && $task['level']<$level){
			self::$mysqli->query("update taskqueue set level=".$level." where id=".$task['id']." limit 1");
			return -1;
		}
		return -1;
	}

	//批量增加实时任务
	private static function addNewTasks($urls,$level){
		$count=0;
		foreach ($urls as $url) {
			//如果存在于不更新列表中则直接忽略
			$url=self::$mysqli->escape_string($url);
			if(self::$mysqli->query("select * from notupdate where url='".$url."' limit 1")->num_rows>0){
				continue;
			}
			//若不存在队列中或者手动强制添加，则加入新任务。若存在level大于队列中的level，则更新队列中的level
			self::$mysqli->begin_transaction();
			$result = self::$mysqli->query("select * from taskqueue where url='".$url."' limit 1");
			if($result->num_rows==0){
				self::$mysqli->query("insert into taskqueue values(null,'".$url."',".$level.",".time().",0)");
			}
			else if(mysqli_fetch_assoc($result)['level']<$level){
				self::$mysqli->query("update taskqueue set level=".$level." where url=".$url." limit 1");
			}
			$result->free();
			self::$mysqli->commit();
		}
		return $count;
	}

	//对执行结果分配更新任务
	private static function addUpdateTask($url,$level){
		$url=self::$mysqli->escape_string($url);
		//排除不更新的连接
		foreach ($GLOBALS['NOTUPDATE_HAS'] as $notupdate) {
			if(strpos($url,$notupdate) !== false ){
				//添加到不更新列表中
				self::$mysqli->query("insert ignore into notupdate values(null,'".$url."'");
				return 'will not update';
			}
		}

		self::$mysqli->begin_transaction();
		//删除原有更新预约
		self::$mysqli->query("delete from taskqueue where url=".$url." limit 1");

		//分配更新时间
		$updatetime=time()+$GLOBALS['UPDATE_TIME'][$level.''];
		if(isset($GLOBALS['SITE_UPDATE'][$url])){
			if(isset($GLOBALS['SITE_UPDATE'][$url]['level'])){
				$level=$GLOBALS['SITE_UPDATE'][$url]['level'];
				$updatetime=time()+$GLOBALS['UPDATE_TIME'][$level.''];
			}
			if(isset($GLOBALS['SITE_UPDATE'][$url]['time'])){
				$updatetime=time()+$GLOBALS['SITE_UPDATE'][$url]['time'];
			}
		}
		self::$mysqli->query("insert into taskqueue values(null,'".$url."',".$level.",".$updatetime.",1)");
		self::$mysqli->commit();
		return date("Y-m-d H:i:s",$updatetime);
	}

	//获取队列信息
	static function getQueueInfo(){
		$queueinfo=array('onprocess'=>array());

		//等待爬取的新网页数量
		$queueinfo['new_task']=mysqli_fetch_assoc(self::$mysqli->query("select count(*) as count from taskqueue where type=0"))['count'];

		//需要更新的网页数量
		$queueinfo['update_task']=mysqli_fetch_assoc(self::$mysqli->query("select count(*) as count from taskqueue where type=1 and time<=".time()))['count'];

		//正在执行的任务
		$result=self::$mysqli->query("select * from onprocess where acktime>".time());
		while($task=mysqli_fetch_assoc($result)){
			$queueinfo['onprocess'][]=$task;
		}
		$result->free();
		return $queueinfo;
	}
}