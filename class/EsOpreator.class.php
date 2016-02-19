<?php
include_once(dirname(dirname(__FILE__)).'/Config.php');

define('ES_INDEX','zspider');
define('ES_TYPE','websites');

class EsOpreator{

	//链接到ES
	static function connectES(){
		echo "Connect to ElasticSearch... ";
		if(ESConnector::connect()){
			Util::echoGreen("[ok]\n");
		}
		else{
			Util::echoRed("[failed]\n");
			exit();
		}
	}

	//保存网页信息到ES
	static function upsertUrlInfo($urlinfo){
		$urlinfo['time'] = date("Y-m-d H:i:s");
		unset($urlinfo['code']);
		unset($urlinfo['links']);
		unset($urlinfo['level']);
		$upsert = $urlinfo;
		$upsert['view'] = 0;
		EsConnector::updateDocByDoc(ES_INDEX,ES_TYPE,md5($urlinfo['url']),$urlinfo,$upsert);
	}
}