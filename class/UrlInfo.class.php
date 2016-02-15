<?php 
include_once(dirname(dirname(__FILE__)).'/Config.php');
include_once('ElasticSearch.class.php');

define('ES_INDEX','zspider');
define('ES_TYPE','websites');

class UrlInfo{

	//保存URL信息到ES
	static function saveUrlInfo($urlinfo){

		$urlinfo['time'] = date("Y-m-d H:i:s");
		unset($urlinfo['code']);
		unset($urlinfo['links']);
		
		$upsert = $urlinfo;
		$upsert['view'] = 0;
		ElasticSearch::updateDocByDoc(ES_INDEX,ES_TYPE,md5($urlinfo['url']),$urlinfo,$upsert);
		unset($upsert);
	}
}