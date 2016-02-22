<?php
require_once(dirname(dirname(__FILE__)).'/Config.php');

class Util{

	//检查网络状态，如果网络不通畅则
	static function isNetError(){
		$check = @fopen('http://www.baidu.com',"r"); 
		return !$check;
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
    	$str ='['.date('Y-m-d h-i-s').'] '.$log."\r\n";
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
}