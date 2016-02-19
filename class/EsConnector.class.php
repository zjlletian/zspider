<?php 
include_once(dirname(dirname(__FILE__)).'/Config.php');
include_once('elasticsearch/vendor/autoload.php');

class ESConnector {
	
	static private $esclient=null;

	//建立连接
	static function connect(){
		if(self::$esclient==null){
			self::$esclient = Elasticsearch\ClientBuilder::create()->setHosts($GLOBALS['ELASTICSEARCH'])->build();
		}
		return true;
	}

	//创建Index
	static function createIndex($index){
		try{
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

	//复杂查询
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
}