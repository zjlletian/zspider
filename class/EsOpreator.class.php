<?php
include_once(dirname(dirname(__FILE__)).'/Config.php');

class EsOpreator{

	//保存urlinfo到ES
	static function upsertUrlInfo($urlinfo){
		$urlinfo['updatetime'] = date("Y-m-d H:i:s");
		unset($urlinfo['code']);
		unset($urlinfo['links']);
		unset($urlinfo['level']);
		unset($urlinfo['html']);//不存储快照，减少es存储空间
		$upsert = $urlinfo;
		$upsert['view'] = 0;
		EsConnector::updateDocByDoc('zspider','websites',md5($urlinfo['url']),$urlinfo,$upsert);
	}

	//记录日志
	static function putLog($log){

	}
}