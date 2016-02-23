<?php
require_once(dirname(dirname(__FILE__)).'/Config.php');

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

	//获取一个到达处理时间的爬虫任务，$time:提前时间
	static function getTask($time=0){
		//时间升序排列形成广度优先遍历，深度越深队列数据量越大,查询速度变慢，时间降序排列会形成深度优先遍历，刚加进来的新任务就会被马上处理，但老任务会积压
		self::$mysqli->begin_transaction();
		$result = self::$mysqli->query("select * from taskqueue where time<=".(time()-$time)." order by time limit 1");
		$task=mysqli_fetch_assoc($result);
		if($result->num_rows!=0){
			//删除任务
			self::$mysqli->query("delete from taskqueue where id=".$task['id']." limit 1");
			//任务超时时间，若没有响应则重新添加任务到队列中
			$url =self::$mysqli->escape_string($task['url']);
			$acktime=time()+120;
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
		$url=empty($urlinfo['url'])?$task['url']:$urlinfo['url'];
		
		self::$mysqli->begin_transaction();
		//对执行结果分配更新任务
		if(!isset($urlinfo['error'])){
			self::addUpdateTask($urlinfo['url'],$urlinfo['level']);
		}
		//从正在执行的任务中移除
		self::$mysqli->query("delete from onprocess where id=".$task['id']);
		self::$mysqli->commit();

		//当level>0时，尝试将连接加入爬虫任务队列
		if(!isset($urlinfo['error'])){
			if($urlinfo['level']>0 && count($urlinfo['links'])>0){
				self::addNewTasks($urlinfo['links'],$urlinfo['level']-1);
			}
		}
	}

	//批量增加实时任务
	private static function addNewTasks($urls,$level){
		foreach ($urls as $url) {
			$url=self::$mysqli->escape_string($url);
			self::$mysqli->query("insert into newlinks values(null,'".$url."',".$level.")");
		}
	}

	//对执行结果分配更新任务
	private static function addUpdateTask($url,$level){
		$urlbak=$url;
		$url=self::$mysqli->escape_string($url);

		//排除不更新的连接
		foreach ($GLOBALS['NOTUPDATE_HAS'] as $notupdate) {
			if(strpos($urlbak,$notupdate) !== false ){
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
		if(isset($GLOBALS['SITE_UPDATE'][$urlbak])){
			if(isset($GLOBALS['SITE_UPDATE'][$urlbak]['level'])){
				$level=$GLOBALS['SITE_UPDATE'][$urlbak]['level'];
				$updatetime=time()+$GLOBALS['UPDATE_TIME'][$level.''];
			}
			if(isset($GLOBALS['SITE_UPDATE'][$urlbak]['time'])){
				$updatetime=time()+$GLOBALS['SITE_UPDATE'][$urlbak]['time'];
			}
		}
		self::$mysqli->query("insert into taskqueue values(null,'".$url."',".$level.",".$updatetime.",1)");
		self::$mysqli->commit();
		return date("Y-m-d H:i:s",$updatetime);
	}

	//判断地址是否需要处理,用于redirect后的地址判断，返回处理等级，-1不需要
	static function isHandled($url,$level) {
		$url=self::$mysqli->escape_string($url);

		//忽略存在于不更新列表中的链接
		if(self::$mysqli->query("select * from notupdate where url='".$url."' limit 1")->num_rows>0){
			return -1;
		}
		//直接忽略正在处理的链接
		if(self::$mysqli->query("select * from onprocess where url='".$url."' limit 1")->num_rows>0){
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
}