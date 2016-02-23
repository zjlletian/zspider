<?php
require_once(dirname(dirname(__FILE__)).'/Config.php');

class QueueWatcher {

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

	//启动QueueWatcher
	static function srartQueueWatcher(){
		//连接到mysql
		Util::echoYellow("Init Zspider QueueWatcher...\n");
		self::connect();

		//添加默认起点任务
		foreach ($GLOBALS['DEFAULT_SITE'] as $link) {
			self::addNewLink($link);
		}

		//启动ack监视
		$pid = pcntl_fork();
		if(!$pid) {
			Util::echoGreen("Ack Watcher is now on running.\n");
			self::handleAck();
		}

		//启动新链接转储
		$pid = pcntl_fork();
		if(!$pid) {
			Util::echoGreen("NewLinks Watcher is now on running.\n");
			self::handleNewLinks();
		}

		pcntl_wait($status);
		pcntl_wait($status);
		Util::echoRed("QueueWatcher stoped..");
	}

	//处理ack，将超时的task重新加入队列中
	private static function handleAck(){
		while(true) {
			$result=self::$mysqli->query("select * from onprocess where acktime<=".time()." limit 1");
			if($result->num_rows>0){
				$task =mysqli_fetch_assoc($result);
				$url =self::$mysqli->escape_string($task['url']);
				self::$mysqli->begin_transaction();
				self::$mysqli->query("insert ignore into taskqueue values(null,'".$url."',".$task['level'].",".$task['time'].",".$task['type'].")");
				self::$mysqli->query("delete from onprocess where id=".$task['id']." limit 1");
				self::$mysqli->commit();
			}
			else{
				sleep(2);
			}
			$result->free();
		}
	}

	//处理新链接，将符合的新链接加入队列中
	private static function handleNewLinks(){
		while (true) {
			$link=mysqli_fetch_assoc(self::$mysqli->query("select * from newlinks limit 1"));
			if($link!=null){
				//添加新链接
				self::addNewTask($link);
				//从新链接列表中删除
				self::$mysqli->query("delete from newlinks where id=".$link['id']." limit 1");
			}
			else{
				usleep(100000); //延迟0.1秒
			}
		}
	}

	//添加新链接
	private static function addNewlink($link){
		$url=self::$mysqli->escape_string($link['url']);
		$level=$link['level'];

		//忽略存在于不更新列表中的链接
		if(self::$mysqli->query("select * from notupdate where url='".$url."' limit 1")->num_rows>0){
			return ;
		}
		//直接忽略正在处理的链接
		if(self::$mysqli->query("select * from onprocess where url='".$url."' limit 1")->num_rows>0){
			return ;
		}

		//若不存在队列中，则加入新任务。若存在level大于队列中的level，则更新队列中的level
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

	//获取队列信息(for web)
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
