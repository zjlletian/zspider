<?php
require_once(dirname(dirname(__FILE__)).'/Config.php');

class QueueWatcher {

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

	//启动QueueWatcher
	static function srartQueueWatcher(){

		//连接到mysql
		Util::echoYellow("[".date("Y-m-d H:i:s")."] Init Zspider QueueWatcher...\n");
		self::connect();

		//添加默认起点任务
		foreach ($GLOBALS['DEFAULT_SITE'] as $link) {
			self::addLinkToQueue($link);
		}

		//启动监视ack的进程
		Util::echoGreen("[".date("Y-m-d H:i:s")."] Create Ack Watcher...\n");
		$pid = pcntl_fork();
		if(!$pid) {
			self::connect();
			self::handleAck();
		}

		//启动转储新链接的进程
		Util::echoGreen("[".date("Y-m-d H:i:s")."] Create NewLinks Watcher...\n");
		$pid = pcntl_fork();
		if(!$pid) {
			self::connect();
			self::handleNewLinks();
		}

		//启动删除过期错误的进程
		Util::echoGreen("[".date("Y-m-d H:i:s")."] Create ErrorLinks Watcher...\n\n");
		$pid = pcntl_fork();
		if(!$pid) {
			self::connect();
			self::cleanErrorLinks();
		}

		pcntl_wait($status);
		Util::echoRed("[".date("Y-m-d H:i:s")."] QueueWatcher stoped..");
	}

	//处理ack，将超时的task重新加入队列中
	private static function handleAck(){
		while(true) {
			//处理200秒之前开始的任务(单任务最大执行时间为120秒)
			$task =mysqli_fetch_assoc(self::$mysqli->query("select * from onprocess where status=1 and acktime<=".(time()-200)." limit 1")); 
			if($task!=null){
				$url = self::$mysqli->escape_string($task['url']);
				//将执行次数小于4次的标记为需要重新处理，否则丢弃任务。
				self::$mysqli->begin_transaction();
				if($task['times']>=4){
					Util::echoRed("[".date("Y-m-d H:i:s")."] Ack out of time ".$task['times']." times, task abandon.\n");
					Util::echoRed("Type:".$task['type']." Level:".$task['level']." Url:".$url."\n\n");
					self::$mysqli->query("delete from onprocess where id=".$task['id']." limit 1");
					self::$mysqli->query("replace into errortask values(null,'".$url."',".(time()+3600*24*100).")"); //错误连接，100天后清理
				}
				else{
					Util::echoYellow("[".date("Y-m-d H:i:s")."] Ack out of time ".$task['times']." times, task retry.\n");
					Util::echoYellow("Type:".$task['type']." Level:".$task['level']." Url:".$url."\n\n");
					self::$mysqli->query("update onprocess set status=0 where id=".$task['id']." limit 1");
				}
				self::$mysqli->commit();
			}
			else{
				sleep(2);
			}
		}
	}

	//处理新链接，将符合的新链接加入队列中
	private static function handleNewLinks(){
		while (true) {
			$link=mysqli_fetch_assoc(self::$mysqli->query("select * from newlinks limit 1"));
			if($link!=null){
				self::addLinkToQueue($link);
				//从新链接列表中删除
				self::$mysqli->query("delete from newlinks where id=".$link['id']." limit 1");
			}
			else{
				sleep(2); //延迟2秒
			}
		}
	}

	//添加连接到队列中
	private static function addLinkToQueue($link){
		$level=$link['level'];
		$url=self::$mysqli->escape_string($link['url']);

		//忽略存在于不更新列表中的链接
		if(self::$mysqli->query("select * from notupdate where url='".$url."' limit 1")->num_rows>0){
			return ;
		}
		//忽略正在处理的链接
		if(self::$mysqli->query("select * from onprocess where url='".$url."' limit 1")->num_rows>0){
			return ;
		}
		//忽略标记为错误的链接
		if(self::$mysqli->query("select * from errortask where url='".$url."' limit 1")->num_rows>0){
			return ;
		}

		//若不存在队列中，则加入新任务。若存在level大于队列中的level，则更新队列中的level
		self::$mysqli->begin_transaction();
		if(!self::$mysqli->query("insert into taskqueue values(null,'".$url."',".$level.",".time().",0)")){
			$task = mysqli_fetch_assoc(self::$mysqli->query("select * from taskqueue where url='".$url."' limit 1"));
			if($task!=null && $task['level']<$level){
				self::$mysqli->query("update taskqueue set level=".$level." where id=".$task['id']." limit 1");
			}
		}
		self::$mysqli->commit();
	}

	//清除到达清理时间的错误连接，每10天执行一次
	private static function cleanErrorLinks(){
		while (true) {
			self::$mysqli->query("delete from errortask where time<=".time());
			time_sleep_until(time()+3600*24*10);
		}
	}

	//获取队列信息(for web)
	static function getQueueInfo(){
		$queueinfo=array('onprocess'=>array());

		//等待爬取的新网页数量
		$queueinfo['new_task']=mysqli_fetch_assoc(self::$mysqli->query("select count(*) as count from taskqueue where type=0"))['count'];

		//需要更新的网页数量
		$queueinfo['update_task']=mysqli_fetch_assoc(self::$mysqli->query("select count(*) as count from taskqueue where type=1 and time<=".time()))['count'];

		//正在执行的任务
		$result=self::$mysqli->query("select * from onprocess where status=1 order by acktime");
		while($task=mysqli_fetch_assoc($result)){
			$queueinfo['onprocess'][]=$task;
		}
		$result->free();
		return $queueinfo;
	}
}
