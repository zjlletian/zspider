<?php
require_once(dirname(dirname(__FILE__)).'/Config.php');

class EsOpreator{

	//保存日志的index名称
	private static $logindex;

	//初始化index
	static function initIndex(){
		EsConnector::connect();
		$paramsbody = [
	    	'mappings' => [
	            'websites' => [
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
	                        'type' => 'string'
	                    ],
	                    'text' => [
	                        'type' => 'string'
	                    ],
	                    'html' => [
	                        'type' => 'string'
	                    ]
	                ]
	            ]
	        ]
		];
		return EsConnector::createIndex('zspider', $paramsbody);
	}

	//保存urlinfo到ES
	static function upsertUrlInfo($urlinfo){
		$urlinfo['time'] = date("Y-m-d H:i:s");
		unset($urlinfo['code']);
		unset($urlinfo['links']);
		unset($urlinfo['level']);
		unset($urlinfo['html']);//不存储快照，减少es存储空间
		$upsert = $urlinfo;
		$upsert['view'] = 0;
		return EsConnector::updateDocByDoc('zspider','websites',md5($urlinfo['url']),$urlinfo,$upsert);
	}

	//创建日志索引
	private static function creatLogIndex(){
		$paramsbody = [
	    	'mappings' => [
	            '_default_' => [
	                'properties' => [
	                    'time' => [
	                    	'format' => 'YYYY-MM-dd HH:mm:ss',
	                        'type' => 'date'
	                    ],
	                    'url' => [
	                        'type' => 'string'
	                    ],
	                    'level' => [
	                        'type' => 'long'
	                    ],
	                    'type' => [
	                        'type' => 'string'
	                    ]
	                ]
	            ],
	            'cancel' => [
					'properties' => [
						'message' => [
							'type' => 'string'
						]
					]
				],
				'error' => [
					'properties' => [
						'code' => [
							'type' => 'long'
						]
					]
				]
	        ]
		];
		return EsConnector::createIndex(self::$logindex, $paramsbody);
	}

	//记录日志
	static function putLog($log,$logtype){
		$log['time']=date("Y-m-d H:i:s");
		if(self::$logindex==null || self::$logindex!="zspiderlog-".date("Y.m.d")){
			self::$logindex="zspiderlog-".date("Y.m.d");
			self::creatLogIndex();
		}
		return EsConnector::insertDoc(self::$logindex,$logtype,time().uniqid(),$log);
	}
}