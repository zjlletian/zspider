<?php
include_once(dirname(dirname(__FILE__)).'/Config.php');
include_once('EsConnector.class.php');
include_once('Util.class.php');

define('ES_INDEX','zspider');
define('ES_TYPE','websites');

class UrlInfo {

	//链接到ES
	static function connectES(){
		return ESConnector::connect();
	}

	//保存网页信息到ES
	static function upsertToES($urlinfo){
		unset($urlinfo['_id']);
		$upsert = $urlinfo;
		$upsert['view'] = 0;
		EsConnector::updateDocByDoc(ES_INDEX,ES_TYPE,md5($urlinfo['url']),$urlinfo,$upsert);
	}
}