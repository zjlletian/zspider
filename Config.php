<?php
define('APPROOT',dirname(__FILE__));

//设置include包含文件所在的所有目录
$include_path=get_include_path();  
$include_path.=PATH_SEPARATOR.APPROOT."/lib"; 
$include_path.=PATH_SEPARATOR.APPROOT."/class";
set_include_path($include_path);

date_default_timezone_set('Asia/Shanghai');

//----------------------------------存储服务器配置--------------------------------------

//Elasticsearch集群地址（多个master的地址）
$GLOBALS['ESHOST'] = array('http://localhost:9200');

//Mongodb地址，爬虫队列，更新队列
$GLOBALS['MONGODB'] = 'localhost:27017';

//----------------------------------爬虫相关配置---------------------------------------

//不进行追踪的href(全匹配)
$GLOBALS['NOTTRACE_MATCH'] = array(
	'/'
);

//不进行追踪的href(开头)
$GLOBALS['NOTTRACE_BEGIN'] = array(
	'#',
	'ftp:',
	'file:',
	'javascript:'
);

//不进行追踪的href(包涵)
$GLOBALS['NOTTRACE_WITH'] = array(
	'login',
	'logout',
	'loginpage',
	'userinfo',
	'passport'
);

//白名单(包涵)
$GLOBALS['WHITE'] = array();

//默认更新周期，七天
$GLOBALS['UPDATE_CYCLE'] = 3600*24*7;

//快速更新站点
$GLOBALS['UPDATE_SITE'] = array(
	'http://www.ifeng.com/'=>array('time'=>3600*24,'level'=>0),
	'http://www.csdn.net/'=>array('time'=>3600*24,'level'=>0)
);