<?php 
include_once(dirname(dirname(__FILE__)).'/Config.php');
include_once('ElasticSearch.class.php');
include_once('TaskManager.class.php');

define('ES_INDEX','zspider');
define('ES_TYPE','websites');

class Transporter {
	//启动从mongo到es的异步传输任务
	static function start(){
		//链接到es
		ElasticSearch::connect();

		//创建子进程，用于将mongo中的url信息转储到es。
		$pid = pcntl_fork();
		if ($pid == -1) {
			Util::echoRed("Fork urlinfo transport progress... [failed]\n");
			exit();
		}
		elseif(!$pid) {
			Util::echoYellow("Fork urlinfo transport progress... [ok]\n");
		    while(true) {
		    	$urlinfo=TaskManager::callTransport();
				if($urlinfo!=null){
					$upsert = $urlinfo;
					$upsert['view'] = 0;
					ElasticSearch::updateDocByDoc(ES_INDEX,ES_TYPE,md5($urlinfo['url']),$urlinfo,$upsert);
					unset($upsert);
				}
				else{
					usleep(100000); //100毫秒
				}
			}
		}
	}
}