<?php
require_once(dirname(dirname(__FILE__)).'/Config.php');

class TaskManager {

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

	//获取服务器时间
	static function getServerTime(){
		return intval(mysqli_fetch_assoc(mysqli_query(self::$mycon,"select unix_timestamp(now()) as time"))['time']);
	}

	//获取一个到达处理时间的爬虫任务
	static function getTask($hash){

		$uniqid=md5($GLOBALS['SPIDERNAME'].mt_rand(0,1000).uniqid());

		//获取一个处理超时需要重新处理的任务，每增加一个level或者失败一次增加十秒可用时间
		$task =mysqli_fetch_assoc(mysqli_query(self::$mycon,"select * from onprocess where status=0 and times<4 limit 1"));
		if($task!=null){
            mysqli_query(self::$mycon,"update onprocess set uniqid='{$uniqid}',status=1,times=times+1,proctime=(SELECT unix_timestamp(now())),acktime=(SELECT unix_timestamp(now())+{$GLOBALS['TASKTIME']}+times*10+level*10),spider='{$GLOBALS['SPIDERNAME']}' where id={$task['id']} and status=0 limit 1");
			$task =mysqli_fetch_assoc(mysqli_query(self::$mycon,"select * from onprocess where uniqid='{$uniqid}'"));
			if($task!=null){
				return $task;
			}
		}

		//从任务队列中获取任务（时间升序：广度优先遍历，深度越深队列数据量越大,查询速度变慢。时间降序：深度优先遍历，新任务马上处理，老任务积压）
		$task =mysqli_fetch_assoc(mysqli_query(self::$mycon,"select * from taskqueue where time<=(SELECT unix_timestamp(now())) order by time limit {$hash},1"));
		if($task!=null){
            mysqli_begin_transaction(self::$mycon);
			$url= mysqli_escape_string(self::$mycon,$task['url']);
			//从队列中删除任务
            mysqli_query(self::$mycon,"delete from taskqueue where id={$task['id']} limit 1");
			//标记为正在处理
            mysqli_query(self::$mycon,"insert into onprocess values(null,'{$uniqid}','{$url}',{$task['level']},{$task['time']},{$task['type']},(SELECT unix_timestamp(now())),(SELECT unix_timestamp(now())+{$GLOBALS['TASKTIME']}+{$task['level']}*10),1,1,'{$GLOBALS['SPIDERNAME']}')");

			if(mysqli_commit(self::$mycon)){
				return mysqli_fetch_assoc( mysqli_query(self::$mycon,"select * from onprocess where uniqid='{$uniqid}' limit 1"));
			}
		}
		return null;
	}

	//提交任务执行结果
	static function submitTask($task,$urlinfo){
		$taskurl= mysqli_escape_string(self::$mycon,$task['url']);
		$taskproc=mysqli_fetch_assoc( mysqli_query(self::$mycon,"select * from onprocess where url='{$taskurl}' limit 1"));
		//检测任务是否已经被其他爬虫处理完毕
		if($taskproc==null){
			$str="Submit refused, url: ".$task['url'];
			Util::putErrorLog($str);
			Util::echoRed("[".date("Y-m-d H:i:s")."] ".$str."\n");

			$str="Message: Task has been handled or removed.";
			Util::putErrorLog($str."\r\n");
			Util::echoRed("[".date("Y-m-d H:i:s")."] ".$str."\n\n");
			return false;
		}

        //对执行结果分配更新任务,如果保存到ES失败,则继续处理
        if(!isset($urlinfo['error'])){
            self::addUpdateTask($urlinfo['url'],$urlinfo['level']);
        }

         //从正在执行的任务中移除
        mysqli_query(self::$mycon,"delete from onprocess where id=".$task['id']);

		//当level>0时，将连接加入队列，否则记录错误
		if(!isset($urlinfo['error'])){
			if($urlinfo['level']>0 && count($urlinfo['links'])>0){
				//$sql="";
				$level=$urlinfo['level']-1;
				foreach ($urlinfo['links'] as $url) {
					$url= mysqli_escape_string(self::$mycon,$url);
					$sql="insert into newlinks values(null,'{$url}',{$level});";
					mysqli_query(self::$mycon,$sql);
				}
				//mysqli_multi_query(self::$mycon,$sql);
			}
		}
		else{
			$taskurl = mysqli_escape_string(self::$mycon,$task['url']);
			if($urlinfo['code']==0){
                mysqli_query(self::$mycon,"replace into errortask values(null,'{$taskurl}',(SELECT unix_timestamp(now())+3600*24*100))"); //连接错误，100天后清理
			}
			else if($urlinfo['code']<500){
                mysqli_query(self::$mycon,"replace into errortask values(null,'{$taskurl}',(SELECT unix_timestamp(now())+3600*24*80))"); //http错误，80天后清理
			}
			else if(500<=$urlinfo['code'] && $urlinfo['code']<600){
                mysqli_query(self::$mycon,"replace into errortask values(null,'{$taskurl}',(SELECT unix_timestamp(now())+3600*24*50))"); //服务器错误，50天后清理
			}
			else if(600<=$urlinfo['code'] && $urlinfo['code']<700){
                mysqli_query(self::$mycon,"replace into errortask values(null,'{$taskurl}',(SELECT unix_timestamp(now())+3600*24*50))"); //内容错误，50天后清理
			}
			else if($urlinfo['code']==800){
                mysqli_query(self::$mycon,"insert ignore into notupdate values(null,'{$taskurl}')"); //非html类型
			}
            else if($urlinfo['code']==900){
                mysqli_query(self::$mycon,"insert ignore into notupdate values(null,'{$taskurl}')"); //存入ES错误
            }
		}
		return true;
	}

	//对执行结果分配更新任务
	private static function addUpdateTask($url,$level){
		$urlbak=$url;
		$url= mysqli_escape_string(self::$mycon,$url);

		//排除不更新的连接
		foreach ($GLOBALS['NOTUPDATE_HAS'] as $notupdate) {
			if(strpos($urlbak,$notupdate) !== false ){
				//添加到不更新列表中
                mysqli_query(self::$mycon,"insert ignore into notupdate values(null,'{$url}')");
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
        mysqli_query(self::$mycon,"insert ignore into taskqueue values(null,'{$url}',{$level},{$updatetime},1)");
		return date("Y-m-d H:i:s",$updatetime);
	}

	//判断地址是否需要处理,用于redirect后的地址判断，返回处理等级，-1不需要
	static function isHandled($url,$level) {
		$url= mysqli_escape_string(self::$mycon,$url);

		//忽略正在处理的链接
		if( mysqli_query(self::$mycon,"select * from onprocess where url='{$url}' limit 1")->num_rows>0){
			return -1;
		}
		//忽略存在于不更新列表中的链接
		if( mysqli_query(self::$mycon,"select * from notupdate where url='{$url}' limit 1")->num_rows>0){
			return -2;
		}
		//忽略标记为错误的链接
		if( mysqli_query(self::$mycon,"select * from errortask where url='{$url}' limit 1")->num_rows>0){
			return -3;
		}

		$result = mysqli_query(self::$mycon,"select * from taskqueue where url='{$url}' limit 1");
		$task=mysqli_fetch_assoc($result);
		$result->free();

		//判断是否存在于队列中,如果不存在任务，标记为未处理。如果存在level较小的new任务，则删除任务，返回较大的level。如果存在level较小的update任务，则提升level
		if(!$task){
			return $level;
		}
		else if($task['type']=='0' && $task['level']<$level){
            mysqli_query(self::$mycon,"delete from taskqueue where id={$task['id']} limit 1");
			return $task['level']>$level?$task['level']:$level;
		}
		else if($task['type']=='0' && $task['level']<$level){
            mysqli_query(self::$mycon,"update taskqueue set level={$level} where id={$task['id']} limit 1");
			return -1;
		}
		return -1;
	}
}
