<?php
require_once(dirname(dirname(__FILE__)).'/Config.php');

class TaskManager {

	//mysql连接
	private static $mysqli;

	//连接到Mysql,如果是$ispcon为true模式则建立持久连接，否则建立短连接
	static function connect($ispcon=true){
		$host=$ispcon? "p:".$GLOBALS['MYSQL']['host'] : $GLOBALS['MYSQL']['host'];
		self::$mysqli=new mysqli();
		self::$mysqli->connect($GLOBALS['MYSQL']['host'],$GLOBALS['MYSQL']['user'],$GLOBALS['MYSQL']['passwd'],$GLOBALS['MYSQL']['db'],$GLOBALS['MYSQL']['port']);
		if(self::$mysqli->connect_error!=null){
			exit();
		}
		return true;
	}

	//获取一个到达处理时间的爬虫任务，$time:提前时间
	static function getTask($time=0){

		//获取一个处理超时需要重新处理的任务
		self::$mysqli->begin_transaction();
		$task =mysqli_fetch_assoc(self::$mysqli->query("select * from onprocess where status=0 limit 1"));
		if($task!=null && $task['times']<4){
			//标记为正在处理
			self::$mysqli->query("update onprocess set status=1, times=times+1, acktime=".time().", spider='".$GLOBALS['SPIDERNAME']."' where id=".$task['id']." limit 1");
		}
		//如果获取到任务并且事务提交成功，则返回任务
		if(self::$mysqli->commit() && $task!=null && $task['times']<4){
			return $task;
		}

		//从任务队列中获取任务（时间升序：广度优先遍历，深度越深队列数据量越大,查询速度变慢。时间降序：深度优先遍历，新任务马上处理，老任务积压）
		self::$mysqli->begin_transaction();
		$task =mysqli_fetch_assoc(self::$mysqli->query("select * from taskqueue where time<=".(time()-$time)." order by time limit 1"));
		if($task!=null){
			$url=self::$mysqli->escape_string($task['url']);
			//从队列中删除任务
			self::$mysqli->query("delete from taskqueue where id=".$task['id']." limit 1");
			//标记为正在处理
			self::$mysqli->query("insert into onprocess values(null,'".$url."',".$task['level'].",".$task['time'].",".$task['type'].",".time().",1,1,'".$GLOBALS['SPIDERNAME']."')");
			$task['id']=self::$mysqli->insert_id;
			$task['spider']=$GLOBALS['SPIDERNAME'];
		}
		//如果获取到任务并且事务提交成功，则返回任务
		if(self::$mysqli->commit() && $task!=null){
			return $task;
		}
		return null;
	}

	//提交任务执行结果
	static function submitTask($task,$urlinfo){

		//对执行结果分配更新任务
		if(!isset($urlinfo['error'])){
			self::addUpdateTask($urlinfo['url'],$urlinfo['level']);
		}
		//从正在执行的任务中移除
		self::$mysqli->query("delete from onprocess where id=".$task['id']);

		//解除任务定时
		set_time_limit(0);
		
		//当level>0时，将连接加入队列，否则记录错误
		if(!isset($urlinfo['error'])){
			if($urlinfo['level']>0 && count($urlinfo['links'])>0){
				foreach ($urlinfo['links'] as $url) {
					$url=self::$mysqli->escape_string($url);
					self::$mysqli->query("insert into newlinks values(null,'".$url."',".($urlinfo['level']-1).")");
				}
			}
		}
		else{
			$taskurl =self::$mysqli->escape_string($task['url']);
			if($urlinfo['code']==0){
				self::$mysqli->query("replace into errortask values(null,'".$taskurl."',".(time()+3600*24*100).")"); //连接错误，100天后清理
			}
			else if($urlinfo['code']<500){
				self::$mysqli->query("replace into errortask values(null,'".$taskurl."',".(time()+3600*24*80).")"); //http错误，80天后清理
			}
			else if(500<=$urlinfo['code'] && $urlinfo['code']<600){
				self::$mysqli->query("replace into errortask values(null,'".$taskurl."',".(time()+3600*24*50).")"); //服务器错误，50天后清理
			}
			else if(600<=$urlinfo['code'] && $urlinfo['code']<700){
				self::$mysqli->query("replace into errortask values(null,'".$taskurl."',".(time()+3600*24*50).")"); //内容错误，50天后清理
			}
			else if($urlinfo['code']==800){
				self::$mysqli->query("insert ignore into notupdate values(null,'".$taskurl."')"); //非html加入到notupdate
			}
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
		self::$mysqli->query("insert ignore into taskqueue values(null,'".$url."',".$level.",".$updatetime.",1)");
		return date("Y-m-d H:i:s",$updatetime);
	}

	//判断地址是否需要处理,用于redirect后的地址判断，返回处理等级，-1不需要
	static function isHandled($url,$level) {
		$url=self::$mysqli->escape_string($url);

		//忽略正在处理的链接
		if(self::$mysqli->query("select * from onprocess where url='".$url."' limit 1")->num_rows>0){
			return -1;
		}
		//忽略存在于不更新列表中的链接
		if(self::$mysqli->query("select * from notupdate where url='".$url."' limit 1")->num_rows>0){
			return -2;
		}
		//忽略标记为错误的链接
		if(self::$mysqli->query("select * from errortask where url='".$url."' limit 1")->num_rows>0){
			return -3;
		}

		$result = self::$mysqli->query("select * from taskqueue where url='".$url."' limit 1");
		$task=mysqli_fetch_assoc($result);
		$result->free();

		//判断是否存在于队列中,如果不存在任务，标记为未处理。如果存在level较小的new任务，则删除任务，返回较大的level。如果存在level较小的update任务，则提升level
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
