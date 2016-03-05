<?php
require_once(dirname(dirname(__FILE__)).'/Config.php');

class StoragerMongo{

	//mongo数据库客户端
	private static $mongo;

	//保存日志的index名称
	private static $logindex;

	//创建文档存储索引与river
	static function initIndex(){
		EsConnector::connect();
		$paramsbody = [
	    	'mappings' => [
	            'html' => [
	                'properties' => [
	                	'time' => [
	                    	'format' => 'YYYY-MM-dd HH:mm:ss',
	                        'type' => 'date'
	                    ],
	                    'url' => [
	                        'type' => 'string'
	                    ],
	                    'title' => [
	                        'type' => 'string'
	                    ],
	                    'view' => [
	                        'type' => 'integer',
							'index' => 'not_analyzed'
	                    ],
	                    'charset' => [
	                        'type' => 'string',
	                        'index' => 'no'
	                    ],
	                    'text' => [
	                        'type' => 'string'
	                    ],
	                    'md5' => [
	                        'type' => 'string', //md5 of html
	                        'index' => 'not_analyzed'
	                    ]
	                ]
	            ]
	        ]
		];
		EsConnector::createIndex('zspider', $paramsbody);

		$river=[
			"type"=>"mongodb",
			"mongodb"=> [
				"host"=> "localhost",
				"port"=>"27017",
				"db"=> "zspider",
				"collection"=> "html"
		  	],
		  	"index"=>[
				"name"=>"zspider",
				"type"=>"html"
		  	]
		];
		ESConnector::updateDocByDoc("_river","zspider_html","_meta",$river,$river);

		//建立mongodb索引
		self::$mongo = new MongoClient();
		$html=self::$mongo->selectDB("zspider")->selectCollection("html");
		//(此方法可能在未来弃用)$html->ensureIndex(array("url"=>1),array('unique' => true));
		foreach($html->getIndexInfo() as $index){
			if (isset($index['key']['url'])){
				return true;
			}
		}
		return $html->createIndex(array("url"=>1),array('unique' => true));
	}

	//创建日志索引与river
	private static function creatLogIndex(){
		$paramsbody = [
			'settings' => [
				'number_of_shards' => 1,
				'number_of_replicas' =>0
	        ],
	    	'mappings' => [
	            '_default_' => [
	                'properties' => [
	                    'time' => [
	                    	'format' => 'YYYY-MM-dd HH:mm:ss',
	                        'type' => 'date'
	                    ],
	                    'url' => [
	                        'type' => 'string',
							'index' => 'not_analyzed'
	                    ],
	                    'level' => [
	                        'type' => 'integer',
							'index' => 'not_analyzed'
	                    ],
	                    'type' => [
	                        'type' => 'string', //'new' or 'update'
	                        'index' => 'not_analyzed'
	                    ],
	                    'spider' => [
	                        'type' => 'string', //spider name
	                        'index' => 'not_analyzed'
	                    ],
						'timeinfo' => [
							'properties' => [
								'total'=>[
									'type' => 'float'
								]
							]
						]
	                ]
	            ],
	            'success' => [
					'properties' => [
					'timeinfo' => [
						'properties' => [
								'gettask'=>[
									'type' => 'float'
								],
								'download'=>[
									'type' => 'float'
								],
								'extarct'=>[
									'type' => 'float'
								],
								'findlinks'=>[
									'type' => 'float'
								],
								'saveinfo'=>[
									'type' => 'float'
								]
							]
						]
					]
				],
	            'error' => [
					'properties' => [
						'error' => [
							'type' => 'string'
						]
					]
				]
	        ]
		];
		EsConnector::createIndex(self::$logindex, $paramsbody);

		//success类型river
		$river=[
			"type"=>"mongodb",
			"mongodb"=> [
				"host"=> "localhost",
				"port"=>"27017",
				"db"=> self::$logindex,
				"collection"=> "success"
			],
			"index"=>[
				"name"=>self::$logindex,
				"type"=>"success"
			]
		];
		ESConnector::updateDocByDoc("_river","zspiderlog_success","_meta",$river,$river);

		//error类型rever
		$river=[
			"type"=>"mongodb",
			"mongodb"=> [
				"host"=> "localhost",
				"port"=>"27017",
				"db"=>self::$logindex,
				"collection"=> "error"
			],
			"index"=>[
				"name"=>self::$logindex,
				"type"=>"error"
			]
		];
		ESConnector::updateDocByDoc("_river","zspiderlog_error","_meta",$river,$river);
	}

	//保存urlinfo到Mongodb
	static function upsertUrlInfo($urlinfo){
		$urlinfo['time'] = date("Y-m-d H:i:s",TaskManager::getServerTime());
		unset($urlinfo['timeinfo']);
		unset($urlinfo['code']);
		unset($urlinfo['links']);
		unset($urlinfo['level']);
		$urlinfo['md5']=md5($urlinfo['text']);
		$urlinfo['view'] = 0;
		$html=self::$mongo->selectDB("zspider")->selectCollection("html");
		return $html->update(array("url"=>$urlinfo['url']),$urlinfo,array("upsert"=>true,"multiple"=>false));
	}

	//记录日志到Mongodb
	static function putLog($log,$logtype){
		$servertime=TaskManager::getServerTime();
		$log['time']=date("Y-m-d H:i:s",$servertime);
		if(self::$logindex==null || self::$logindex!="zspiderlog_".date("Ymd",$servertime)){
			self::$logindex="zspiderlog_".date("Ymd",$servertime);
			self::creatLogIndex();
		}
		$logcol=self::$mongo->selectDB(self::$logindex)->selectCollection($logtype);
		return $logcol->insert($log);
	}
}
