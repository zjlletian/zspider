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

		//启动监视ack进程
		Util::echoGreen("[".date("Y-m-d H:i:s")."] Create Ack Watcher...\n");
		$pid = pcntl_fork();
		if(!$pid) {
			self::connect();
			self::handleAck();
		}

		//启动删除过期错误进程
		Util::echoGreen("[".date("Y-m-d H:i:s")."] Create ErrorLinks Watcher...\n");
		$pid = pcntl_fork();
		if(!$pid) {
			self::connect();
			self::cleanErrorLinks();
		}

		//收集队列信息
		Util::echoGreen("[".date("Y-m-d H:i:s")."] Create Queueinfo collector...\n\n");
		$pid = pcntl_fork();
		if(!$pid) {
			self::connect();
			self::queueinfoCollector();
		}

		//启动新链接转储进程
		Util::echoGreen("[".date("Y-m-d H:i:s")."] Create NewLinks transporter...\n");
		for($count=0;$count<100;$count++){
			$pid = pcntl_fork();
			if(!$pid) {
				self::connect();
				self::handleNewLinks($count*500);
			}
		}

		while(true){
			pcntl_wait($status);
		}
		Util::echoRed("[".date("Y-m-d H:i:s")."] QueueWatcher stoped..");
	}

	//处理ack，将超时的task重新加入队列中
	private static function handleAck(){
		while(true) {
			//获取ack超时10秒及以上的任务
			$task =mysqli_fetch_assoc(self::$mysqli->query("select * from onprocess where status=1 and acktime<=(SELECT unix_timestamp(now())-10) limit 1"));
			if($task!=null){
				$url = self::$mysqli->escape_string($task['url']);
				//将执行次数小于4次的标记为需要重新处理，否则丢弃任务。
				self::$mysqli->begin_transaction();
				if($task['times']>=4){
					Util::echoRed("[".date("Y-m-d H:i:s")."] Ack out of time ".$task['times']." times, task abandon.\n");
					Util::echoRed("Type:".$task['type']." Level:".$task['level']." Url:".$url."\n\n");
					self::$mysqli->query("delete from onprocess where id=".$task['id']." limit 1");
					self::$mysqli->query("replace into errortask values(null,'".$url."',(SELECT unix_timestamp(now())+3600*24*100))"); //错误连接，100天后清理
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

	//处理新链接
	private static function handleNewLinks($hash){
		while(true) {
			$result=self::$mysqli->query("select * from newlinks limit ".$hash.",5");
			if($result->num_rows>0){
				while($link=mysqli_fetch_assoc($result)){
					self::addLinkToQueue($link);
					self::$mysqli->query("delete from newlinks where id=".$link['id']." limit 1");
				}
				$result->free();
			}
			else{
				usleep(500000);//500毫秒
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
		if(!self::$mysqli->query("insert into taskqueue values(null,'".$url."',".$level.",(SELECT unix_timestamp(now())),0)")){
			$task = mysqli_fetch_assoc(self::$mysqli->query("select * from taskqueue where url='".$url."' limit 1"));
			if($task!=null && $task['level']<$level){
				self::$mysqli->query("update taskqueue set level=".$level." where id=".$task['id']." limit 1");
			}
		}
	}

	//清除到达清理时间的错误连接，每10天执行一次
	private static function cleanErrorLinks(){
		while (true) {
			self::$mysqli->query("delete from errortask where time<=(SELECT unix_timestamp(now()))");
			time_sleep_until(time()+3600*24*10);
		}
	}

	//队列信息收集，因为某些count(*)在大量数据下执行需要很长时间，所以采用单独进程统计数据
	private static function queueinfoCollector(){
		while(true){

			//等待爬取的新网页数量
			$new=mysqli_fetch_assoc(self::$mysqli->query("select count(*) as count from taskqueue where type=0"))['count'];
			self::$mysqli->query("replace into queueinfo values('new','".$new."')");

			//需要现在更新的网页数量
			$update_now=mysqli_fetch_assoc(self::$mysqli->query("select count(*) as count from taskqueue where type=1 and time<=(SELECT unix_timestamp(now()))"))['count'];
			self::$mysqli->query("replace into queueinfo values('update_now','".$update_now."')");

			sleep(2);
		}
	}

	//获取队列信息(for web)
	static function getQueueInfo(){
		
		$queueinfo=array('onprocess'=>array(),'spiders'=>array());

		//正在执行的任务
		$result=self::$mysqli->query("select * from onprocess order by proctime");
		while($task=mysqli_fetch_assoc($result)){
			$queueinfo['onprocess'][]=$task;
		}
		$result->free();

		//正在执行任务的爬虫
		$result=self::$mysqli->query("select spider,count(*) as tasks from onprocess where acktime>(SELECT unix_timestamp(now())-10) group by spider");
		while($task=mysqli_fetch_assoc($result)){
			$queueinfo['spiders'][]=$task;
		}
		$result->free();

		$queueinfo['new']=mysqli_fetch_assoc(self::$mysqli->query("select value from queueinfo where item='new'"))['value'];
		$queueinfo['update_now']=mysqli_fetch_assoc(self::$mysqli->query("select value from queueinfo where item='update_now'"))['value'];

		return $queueinfo;
	}

	//在线爬虫列表（for web）
	static function getSpiders(){
		$spiders=array();
		$result=self::$mysqli->query("select * from spiders where acktime>(SELECT unix_timestamp(now())-60)"); //获取60秒内有报告信息的爬虫（三次报告时间）
		while($spider=mysqli_fetch_assoc($result)){
			$spider['tasks']=0;
			$spiders[]=$spider;
		}
		$result->free();
		return $spiders;
	}

	//爬虫状态反馈（for web）
	static function spiderReport($name,$ip){
		return self::$mysqli->query("replace into spiders values(null,'".$name."','".$ip."',(SELECT unix_timestamp(now())))");
	}
}
