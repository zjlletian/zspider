<?php
include_once(dirname(dirname(__FILE__)).'/Config.php');

class Util{

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

	/*
	echo "\033[31m 红色字 \033[0m"; 
	echo "\033[32m 绿色字 \033[0m"; 
	echo "\033[33m 黄色字 \033[0m";
	echo "\033[34m 蓝色字 \033[0m"; 
	echo "\033[35m 紫色字 \033[0m"; 
	echo "\033[36m 天蓝字 \033[0m"; 
	echo "\033[37m 白色字 \033[0m";
	*/

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