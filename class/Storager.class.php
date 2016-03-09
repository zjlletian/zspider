<?php
require_once(dirname(dirname(__FILE__)).'/Config.php');

class Storager{

	//保存日志的index名称
	private static $logindex;

	//初始化index
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
	                        'type' => 'long'
	                    ],
	                    'charset' => [
	                        'type' => 'string',
	                        'index' => 'not_analyzed'
	                    ],
	                    'text' => [
	                        'type' => 'string'
	                    ],
	                    'md5' => [
	                        'type' => 'string',
	                        'index' => 'not_analyzed'
	                    ]
	                ]
	            ]
	        ]
		];
		return EsConnector::createIndex('zspider', $paramsbody);
	}

	//创建日志索引
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
	                        'type' => 'long',
	                        'index' => 'not_analyzed'
	                    ],
	                    'type' => [
	                        'type' => 'string',
	                        'index' => 'not_analyzed'
	                    ],
	                    'spider' => [
	                        'type' => 'string',
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
		return EsConnector::createIndex(self::$logindex, $paramsbody);
	}

	//保存urlinfo到ES
	static function upsertUrlInfo($urlinfo){
		$urlinfo['time'] = date("Y-m-d H:i:s",TaskManager::getServerTime());
		unset($urlinfo['timeinfo']);
		unset($urlinfo['code']);
		unset($urlinfo['links']);
		unset($urlinfo['level']);
		$urlinfo['md5']=md5($urlinfo['text']);
		$upsert = $urlinfo;
		$upsert['view'] = 0;
		return EsConnector::updateDocByDoc_curl('zspider','html',md5($urlinfo['url']),$urlinfo,$upsert,30);
	}

	//记录日志
	static function putLog($log,$logtype){
		$servertime=TaskManager::getServerTime();
		$log['time']=date("Y-m-d H:i:s",$servertime);
		if(self::$logindex==null || self::$logindex!="zspiderlog-".date("Y.m.d",$servertime)){
			self::$logindex="zspiderlog-".date("Y.m.d",$servertime);
			self::creatLogIndex();
		}
		return EsConnector::insertDoc_curl(self::$logindex,$logtype,$servertime.uniqid(),$log,10);
	}
}