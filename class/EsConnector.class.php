<?php 
require_once(dirname(dirname(__FILE__)).'/Config.php');
require_once('elasticsearch/vendor/autoload.php');

class ESConnector {
	
	static private $esclient=null;

	//建立连接
	static function connect(){
		if(self::$esclient==null){
			self::$esclient = Elasticsearch\ClientBuilder::create()->setHosts($GLOBALS['ELASTICSEARCH'])->build();
		}
		return true;
	}

	//测试是否连接到ES
	static function testConnect(){
		$testquery=[
			"query"=>[
				"match_all"=>[]
			]
		];
		return self::search("","",$testquery)!=false;
	}

	//创建Index
	static function createIndex($indexname,$body=null){
		try{
			$params = [
				'index' => $indexname
			];
			if($body!=null){
				$params['body'] = $body;
			}
			return self::$esclient->indices()->create($params);
		}
		catch(Exception $e){
			return false;
		}
	}

	//删除Index
	static function deleteIndex($index){
		try{
			$params = [
				'index' => $index
			];
			return self::$esclient->indices()->delete($params);
		}
		catch(Exception $e){
			return false;
		}
	}

	//根据index,$type,id获取document
	static function getDocById($index,$type,$id){
		try{
			$params = [
			    'index' => $index,
			    'type' => $type,
			    'id' => $id
			];
			return self::$esclient->get($params);
		}
		catch(Exception $e){
			return false;
		}
	}

	//根据index,$type,id删除document
	static function deleteDocById($index,$type,$id){
		try{
			$params = [
			    'index' => $index,
			    'type' => $type,
			    'id' => $id
			];
			return self::$esclient->delete($params);
		}
		catch(Exception $e){
			return false;
		}
	}

	//插入document
	static function insertDoc($index,$type,$id,$docbody){
		try{
			$params = [
			    'index' => $index,
			    'type' => $type,
			    'id' => $id,
			    'body' => $docbody
			];
			return self::$esclient->index($params);
		}
		catch(Exception $e){
			return false;
		}
	}

	//插document curl方式
	static function insertDoc_curl($index,$type,$id,$docbody){
		$data = $docbody;
		$ch = curl_init ();
		curl_setopt ( $ch, CURLOPT_URL,"{$GLOBALS['ELASTICSEARCH'][0]}/{$index}/{$type}/{$id}");
		curl_setopt ( $ch, CURLOPT_POST, 1 );
		curl_setopt ( $ch, CURLOPT_HEADER, 0 );
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt ( $ch, CURLOPT_POSTFIELDS, json_encode($data) );
		$json=curl_exec ( $ch );
		return json_decode($json,true);
	}

	//更新document
	static function updateDocByDoc($index,$type,$id,$docbody,$upsert=null){
		try{
			$params = [
			    'index' => $index,
			    'type' => $type,
			    'id' => $id,
			    'body' => [
			        'doc' => $docbody
			    ]
			];
			if($upsert!=null){
				$params['body']['upsert'] = $upsert;
			}
			return self::$esclient->update($params);
		}
		catch(Exception $e){
			return false;
		}
	}

	//使用script更新document
	static function updateDocByScript($index,$type,$id,$script,$args,$upsert=null){
		try{
			$params = [
			    'index' => $index,
			    'type' => $type,
			    'id' => $id,
			    'body' => [
			        'script' => $script,
			        'params' => $args
			    ]
			];
			if($upsert!=null){
				$params['body']['upsert'] = $upsert;
			}
			return self::$esclient->update($params);
		}
		catch(Exception $e){
			return false;
		}
	}

	//查询
	static function search($index,$type,$body){
		try{
			$params = [
			    'index' => $index,
			    'type' => $type,
			    'body' => $body
			];
			return self::$esclient->search($params);
		}
		catch(Exception $e){
			return false;
		}
	}

	//查询（CURL方式）
	static function search_curl($index,$type,$body){
		$data = $body;
		$ch = curl_init ();
		curl_setopt ( $ch, CURLOPT_URL,"{$GLOBALS['ELASTICSEARCH'][0]}/{$index}/{$type}/_search");
		curl_setopt ( $ch, CURLOPT_POST, 1 );
		curl_setopt ( $ch, CURLOPT_HEADER, 0 );
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt ( $ch, CURLOPT_POSTFIELDS, json_encode($data) );
		$json=curl_exec ( $ch );
		return json_decode($json,true);
	}
}