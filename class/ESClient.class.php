<?php 
include_once(dirname(dirname(__FILE__)).'/Config.php');
include_once('elasticsearch/vendor/autoload.php');

class ESClient {

	//保存URL信息到ES
	static function storeUrlInfo($urlinfo){

		$urlinfo['time'] = date("Y-m-d H:i:s");
		unset($urlinfo['code']);
		unset($urlinfo['links']);
		
		$upsert = $urlinfo;
		$upsert['view'] = 0;
		self::updateDocByDoc('zspider','websites',md5($urlinfo['url']),$urlinfo,$upsert);
		unset($upsert);
	}
	
	static private $esclient=null;

	//建立连接
	static private function connect(){
		if(self::$esclient==null){
			self::$esclient = Elasticsearch\ClientBuilder::create()->setHosts($GLOBALS['ESHOST'])->build();
		}
		return true;
	}

	//创建Index
	static function createIndex($index){
		try{
			self::connect();
			$params = [
				'index' => $index
			];
			return self::$esclient->indices()->create($params);
		}
		catch(Exception $e){
			return false;
		}
	}

	//删除Index
	static function deleteIndex($index){
		try{
			self::connect();
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
			self::connect();
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
			self::connect();
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
			self::connect();
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

	//更新document
	static function updateDocByDoc($index,$type,$id,$docbody,$upsert=null){
		try{
			self::connect();
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
		self::connect();
		
	}

	//复杂查询
	static function search($index,$type,$body){
		try{
			self::connect();
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
}