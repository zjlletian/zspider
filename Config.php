<?php
define('APPROOT',dirname(__FILE__));

//--------------------------------- ZJLUP PHP框架配置 --------------------------------------

//设置include包含文件所在的所有目录
$include_path=get_include_path();  
$include_path.=PATH_SEPARATOR.APPROOT."/lib";
$include_path.=PATH_SEPARATOR.APPROOT."/class";
set_include_path($include_path);

//包含class文件夹中的所有.class.php文件
foreach(new FilesystemIterator(APPROOT."/class", FilesystemIterator::SKIP_DOTS ) as $classfile) {
	require_once($classfile);
}

//加载数据库配置
require_once(APPROOT."/db.inc.php");

//加载邮件发送端设置
if(file_exists(APPROOT."/email.inc.php")){
	require_once(APPROOT."/email.inc.php");
}
else{
	$GLOBALS['SEND_EMAIL'] = false;
}

//默认时区
date_default_timezone_set('Asia/Shanghai');


//--------------------------------- 爬虫处理规则配置 --------------------------------------

//最大并行任务数量
$GLOBALS['MAX_PARALLEL']=30;

//最大网页大小(B)
$GLOBALS['MAX_HTMLSISE']=1024*2048;

//不进行追踪的href (完全匹配以下字段)
$GLOBALS['NOTTRACE_MATCH'] = array(
	'./'
);

//不进行追踪的href (以以下字段开头)
$GLOBALS['NOTTRACE_BEGIN'] = array(
	'#',
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
	'/search',
	'www.baidu.com',
	'www.sogou.com',
	'www.so.com'
);

//--------------------------------- 任务队列规则配置 --------------------------------------

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
	/*array('url'=>'https://www.baidu.com/','level'=>1),
	array('url'=>'http://baike.baidu.com/','level'=>2),
	array('url'=>'http://www.ifeng.com/','level'=>2),
	array('url'=>'http://www.163.com/','level'=>2),
	array('url'=>'http://www.sina.com.cn/','level'=>2)*/
);