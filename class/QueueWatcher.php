<?php
require_once(dirname(dirname(__FILE__)).'/Config.php');

class QueueWatcher {

	//mysql连接
	private static $mycon;

	//连接到Mysql,如果是$ispcon为true模式则建立持久连接，否则建立短连接
	static function connect($ispcon=true){
		$host=$ispcon? "p:".$GLOBALS['MYSQL']['host'] : $GLOBALS['MYSQL']['host'];
		self::$mycon=mysqli_connect($host,$GLOBALS['MYSQL']['user'],$GLOBALS['MYSQL']['passwd'],$GLOBALS['MYSQL']['db'],$GLOBALS['MYSQL']['port']);
		if(mysqli_connect_error()!=null){
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
		Util::echoGreen("[".date("Y-m-d H:i:s")."] Create Queueinfo collector...\n");
		$pid = pcntl_fork();
		if(!$pid) {
			self::queueinfoCollector();
		}

		//启动新链接转储进程
		Util::echoGreen("[".date("Y-m-d H:i:s")."] Create NewLinks transporter...\n\n");
		for($count=0;$count<$GLOBALS['MAX_PARALLEL_QUEUE'];$count++){
			$pid = pcntl_fork();
			if(!$pid) {
				self::connect();
				self::handleNewLinks($count*$GLOBALS['MAX_PARALLEL_QUEUE']*10);
			}
		}

		//监视子进程退出
		while(true){
			pcntl_wait($status);
		}
		Util::echoRed("[".date("Y-m-d H:i:s")."] QueueWatcher stoped..");
	}

	//处理ack，将超时的task重新加入队列中
	private static function handleAck(){
		while(true) {
			//获取ack超时10秒及以上的任务
			$result=mysqli_query(self::$mycon,"select * from onprocess where status=1 and acktime<=(SELECT unix_timestamp(now())-10) limit 1");
			while($task =mysqli_fetch_assoc($result)){
				$url = mysqli_escape_string(self::$mycon,$task['url']);
				//将执行次数小于4次的标记为需要重新处理，否则丢弃任务。
				mysqli_begin_transaction(self::$mycon);
				if($task['times']>=4){
					Util::echoRed("[".date("Y-m-d H:i:s")."] Ack out of time ".$task['times']." times, task abandon.\n");
					Util::echoRed("Type:".$task['type']." Level:".$task['level']." Url:".$url."\n\n");
					mysqli_query(self::$mycon,"delete from onprocess where id={$task['id']} limit 1");
					mysqli_query(self::$mycon,"replace into errortask values(null,'{$url}',(SELECT unix_timestamp(now())+3600*24*100))"); //错误连接，100天后清理
				}
				else{
					Util::echoYellow("[".date("Y-m-d H:i:s")."] Ack out of time ".$task['times']." times, task retry.\n");
					Util::echoYellow("Type:".$task['type']." Level:".$task['level']." Url:".$url."\n\n");
					mysqli_query(self::$mycon,"update onprocess set status=0 where id={$task['id']} limit 1");
				}
				mysqli_commit(self::$mycon);
			}
			sleep(2);
		}
	}

	//处理新链接
	private static function handleNewLinks($hash){
		while(true) {
			$result=mysqli_query(self::$mycon,"select * from newlinks limit {$hash},10");
			if($result->num_rows>0){
				while($link=mysqli_fetch_assoc($result)){
					self::addLinkToQueue($link);
					mysqli_query(self::$mycon,"delete from newlinks where id={$link['id']} limit 1");
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
		$url=mysqli_escape_string(self::$mycon,$link['url']);

		//忽略存在于不更新列表中的链接
		if(mysqli_query(self::$mycon,"select * from notupdate where url='{$url}' limit 1")->num_rows>0){
			return ;
		}
		//忽略正在处理的链接
		if(mysqli_query(self::$mycon,"select * from onprocess where url='{$url}' limit 1")->num_rows>0){
			return ;
		}
		//忽略标记为错误的链接
		if(mysqli_query(self::$mycon,"select * from errortask where url='{$url}' limit 1")->num_rows>0){
			return ;
		}
		//若不存在队列中，则加入新任务。若存在level大于队列中的level，则更新队列中的level
		if(!mysqli_query(self::$mycon,"insert into taskqueue values(null,'{$url}','{$level}',(SELECT unix_timestamp(now())),0)")){
			$task = mysqli_fetch_assoc(mysqli_query(self::$mycon,"select * from taskqueue where url='{$url}' limit 1"));
			if($task!=null && $task['level']<$level){
				mysqli_query(self::$mycon,"update taskqueue set level={$level} where id={$task['id']} limit 1");
			}
		}
	}

	//清除到达清理时间的错误连接，每10天执行一次
	private static function cleanErrorLinks(){
		while (true) {
			self::connect(false);
			mysqli_query(self::$mycon,"delete from errortask where time<=(SELECT unix_timestamp(now()))");
			time_sleep_until(time()+3600*24*10);
		}
	}

	//队列信息收集，因为某些count(*)在大量数据下执行需要很长时间，所以采用单独进程统计数据
	private static function queueinfoCollector(){
		while(true){

			//等待爬取的新网页数量
			$new=mysqli_fetch_assoc(mysqli_query(self::$mycon,"select count(*) as count from taskqueue where type=0"))['count'];
			mysqli_query(self::$mycon,"replace into queueinfo values('new','".$new."')");

			//需要现在更新的网页数量
			$update_now=mysqli_fetch_assoc(mysqli_query(self::$mycon,"select count(*) as count from taskqueue where type=1 and time<=(SELECT unix_timestamp(now()))"))['count'];
			mysqli_query(self::$mycon,"replace into queueinfo values('update_now','{$update_now}')");

			sleep(2);
		}
	}
}
