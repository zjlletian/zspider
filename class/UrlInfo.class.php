<?php 
include_once(dirname(dirname(__FILE__)).'/Config.php');
include_once('ESClient.class.php');

class UrlInfo{

	//保存URL信息到ES
	static function saveUrlInfo($urlinfo){

		$urlinfo['time'] = date("Y-m-d H:i:s");
		unset($urlinfo['code']);
		unset($urlinfo['links']);
		
		$upsert = $urlinfo;
		$upsert['view'] = 0;
		ESClient::updateDocByDoc('zspider','websites',md5($urlinfo['url']),$urlinfo,$upsert);
		unset($upsert);
	}
}