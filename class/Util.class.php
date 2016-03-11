<?php
require_once(dirname(dirname(__FILE__)).'/Config.php');

class Util{

	//检查网络状态，如果网络不通畅则
	static function isNetError($url='http://www.baidu.com/'){
		return !@fopen($url,"r");
	}

	//重命名以前的日志
	static function renameOldLog(){
		if(file_exists(APPROOT."/log/error.log")){
			rename(APPROOT."/log/error.log", APPROOT."/log/error.".date("Y.m.d.H.i.s").".log");
		}
	}

	//记录错误日志
	static function putErrorLog($log){
		$errorpath=APPROOT."/log";
		$errorfile=APPROOT."/log/error.log";
    	if(!file_exists($errorpath)){
    		mkdir($errorpath);
    	}
    	if(!file_exists($errorfile)){
    		touch($errorfile);
    	}
    	$str ='['.date("Y-m-d H:i:s").'] '.$log."\r\n";
		file_put_contents($errorfile, $str, FILE_APPEND);
	}

	//字符串startwith
	static function strStartWith($str, $needle){
		return strpos($str, $needle) === 0;
	}

	//字符串endwith
	static function strEndWith($str, $needle){
		$length = strlen($needle);  
		if($length == 0) {    
			return true;  
		}  
		return (substr($str, -$length) === $needle);
	}

	//输出红色字
	static function echoRed($str){
		echo "\033[31m".$str."\033[0m";
	}

	//输出绿色字
	static function echoGreen($str){
		echo "\033[32m".$str."\033[0m";
	}
	
	//输出绿色字
	static function echoYellow($str){
		echo "\033[33m".$str."\033[0m";
	}

	//向Url Post内容
	static function urlPost($url,$data,$timeout,$jsonout=false){
		$ch = curl_init ();
		$result=false;
		try{
			curl_setopt ( $ch, CURLOPT_URL,$url);
			curl_setopt ( $ch, CURLOPT_POST, 1 );
			curl_setopt ( $ch, CURLOPT_HEADER, 0 );
			curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt ( $ch, CURLOPT_POSTFIELDS,$data);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 5000);//设置连接超时时间
			if($timeout!=null){
				curl_setopt($ch, CURLOPT_TIMEOUT_MS,$timeout*1000);//设置超时时间
			}
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//1将结果返回，0直接stdout
			$result=curl_exec ( $ch );
			if(curl_getinfo($ch)['http_code']/100!=2){
				$result= false;
			}
			else if($jsonout){
				$result=json_decode($result,true);
			}
		}
		catch(Exception $e){
			$result= false;
		}
		finally{
			curl_close($ch);
			unset($ch);
			return $result;
		}
	}

	//写入pid
	static function writePid($pid){
		$pidpath=APPROOT."/pids";
		$pidfile=APPROOT."/pids/{$pid}.pid";
		if(!file_exists($pidpath)){
    		mkdir($pidpath);
    	}
    	if(!file_exists($pidfile)){
    		touch($pidfile);
    	}
		file_put_contents($pidfile,time());
	}

	//kill超时pid
	static function killPid($maxtime){
		$pidpath=APPROOT."/pids";
		$count=0;
		if(file_exists($pidpath)){
			foreach(new FilesystemIterator($pidpath, FilesystemIterator::SKIP_DOTS ) as $pidfile) {
				$pid=trim($pidfile->getFilename(),'.pid');
				$time=intval(file_get_contents($pidfile));
				if($time<time()-$maxtime){
					Util::echoRed("[".date("Y-m-d H:i:s")."] kill Handler,task has used ".(time()-$time)."s, max time is ".$maxtime."s, PID:".$pid."\n\n");
					Util::putErrorLog("kill Handler,task has used ".(time()-$time)."s, max time is ".$maxtime."s, PID:".$pid."\r\n\r\n");
					unlink($pidfile);
					exec("kill -9 ".$pid);
				}
				else{
					$count++;
				}
			}
		}
		return $count;
	}

	static function getHandlerCount(){
		$pidpath=APPROOT."/pids";
		$count=0;
		if(file_exists($pidpath)){
			foreach(new FilesystemIterator($pidpath, FilesystemIterator::SKIP_DOTS ) as $pidfile) {
				$count++;
			}
		}
		return $count;
	}
}
