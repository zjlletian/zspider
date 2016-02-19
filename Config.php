<?php
define('APPROOT',dirname(__FILE__));

//设置include包含文件所在的所有目录
$include_path=get_include_path();  
$include_path.=PATH_SEPARATOR.APPROOT."/lib";
set_include_path($include_path);

//包含class文件夹中的所有文件
foreach(new FilesystemIterator(APPROOT."/class", FilesystemIterator::SKIP_DOTS ) as $classfile){
	include_once($classfile);
}

//默认时区
date_default_timezone_set('Asia/Shanghai');

//----------------------------------存储服务器配置--------------------------------------

//Elasticsearch集群地址（多个master的地址）
$GLOBALS['ELASTICSEARCH'] = array('http://localhost:9200');

//Mongodb地址（爬虫任务队列，转储队列）
$GLOBALS['MONGODB'] = 'localhost:27017';

//----------------------------------爬虫相关规则配置---------------------------------------

//最大网页大小 2M
$GLOBALS['MAX_HTMLSISE']=1024*2048;

//最大并行任务数量
$GLOBALS['MAX_PARALLEL']=30;

//不进行追踪的href (完全匹配以下字段)
$GLOBALS['NOTTRACE_MATCH'] = array(
	'/'
);

//不进行追踪的href (以以下字段开头)
$GLOBALS['NOTTRACE_BEGIN'] = array(
	'#',
	'./#',
	'ftp:',
	'file:',
	'javascript:'
);

//不进行追踪的href (包涵以下字段)
$GLOBALS['NOTTRACE_HAS'] = array(
	'error',
	'login',
	'logout',
	'passport',
	'/search'
);

//默认更新周期
$GLOBALS['UPDATE_TIME'] = array(
	'4'=> 3600*24*2,
	'3'=> 3600*24*3,
	'2'=> 3600*24*7,
	'1'=> 3600*24*15,
	'0'=> 3600*24*30
);

//自定义站点更新周期与等级
$GLOBALS['SITE_UPDATE'] = array(
	'http://news.ifeng.com/'=>array('level'=>1,'time'=>3600)
);

//不进行更新的url (包涵以下字段)
$GLOBALS['NOTUPDATE_HAS'] = array(
	'ifeng.com/a/',
	'ifeng.com/news/',
	'ifeng.com/mil/'
);

//默认起点站点
$GLOBALS['DEFAULT_SITE'] = array(
	'2'=>array('https://www.baidu.com/','http://www.ifeng.com/'),
	'3'=>array('http://www.163.com/','http://www.sina.com.cn/','http://baike.baidu.com/')
);
