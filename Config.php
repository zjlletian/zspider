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

//----------------------------------爬虫相关规则配置---------------------------------------

//不进行追踪的href (完全匹配以下字段)
$GLOBALS['NOTTRACE_MATCH'] = array(
	'/'
);

//不进行追踪的href (以以下字段开头)
$GLOBALS['NOTTRACE_BEGIN'] = array(
	'#',
	'ftp:',
	'file:',
	'javascript:'
);

//不进行追踪的href (包涵以下字段)
$GLOBALS['NOTTRACE_WITH'] = array(
	'login',
	'logout',
	'loginpage',
	'userinfo',
	'passport'
);

//默认更新周期，七天
$GLOBALS['UPDATE_TIME'] = 60; //3600*24*7

//自定义站点更新周期与等级
$GLOBALS['SITE_UPDATE'] = array(
	'http://www.ifeng.com/'=>array('time'=>3600*24,'level'=>1),
	'http://www.csdn.net/'=>array('time'=>3600*24,'level'=>1)
);

//不进行更新的url (包涵以下字段)
$GLOBALS['NOTUPDATE_WITH'] = array(
	'http://news.ifeng.com/a/'
);