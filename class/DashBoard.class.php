<?php
require_once(dirname(dirname(__FILE__)).'/Config.php');

class Dashboard{

    private static $mycon;

    //在需要mysql查询前调用
    static function useMysql(){
        self::$mycon=mysqli_connect($GLOBALS['MYSQL']['host'],$GLOBALS['MYSQL']['user'],$GLOBALS['MYSQL']['passwd'],$GLOBALS['MYSQL']['db'],$GLOBALS['MYSQL']['port']);
        if(mysqli_connect_error()!=null){
           die(mysqli_connect_error());
        }
    }

    //在需要ES查询时调用
    static function useES(){
        ESConnector::connect();
    }

    //爬虫状态报告（用于爬虫post）
    static function spiderReport($name,$ip){
        mysqli_query(self::$mycon,"replace into spiders values(null,'{$name}','{$ip}',(SELECT unix_timestamp(now())))");
        return  array("status"=>1);
    }

    //获取队列信息
    static function getQueueInfo(){
        $queueinfo['new']=mysqli_fetch_assoc(mysqli_query(self::$mycon,"select value from queueinfo where item='new'"))['value'];
        $queueinfo['update_now']=mysqli_fetch_assoc( mysqli_query(self::$mycon,"select value from queueinfo where item='update_now'"))['value'];

        //爬虫正在执行的任务数量
        $spidertask=array();
        $result= mysqli_query(self::$mycon,"select spider,count(*) as tasks from onprocess where status=1 group by spider");
        while($task=mysqli_fetch_assoc($result)){
            $spidertask[]=$task;
        }
        $result->free();

        //在线爬虫信息 (60秒内有报告信息的爬虫)
        $queueinfo['spiders']=array();
        $result=mysqli_query(self::$mycon,"select * from spiders where acktime>(SELECT unix_timestamp(now())-60)");
        while($spider=mysqli_fetch_assoc($result)){
            $spider['tasks']=0;
            foreach ($spidertask as $st) {
                if($spider['name']==$st['spider']){
                    $spider['tasks']=$st['tasks'];
                }
            }
            $queueinfo['spiders'][]=$spider;
        }
        $result->free();

        return $queueinfo;
    }

    //正在执行的任务列表
    static function getTasklist(){
        $tasklist=array();

        $result= mysqli_query(self::$mycon,"select * from onprocess order by proctime");
        while($task=mysqli_fetch_assoc($result)){
            $tasklist[]=$task;
        }
        $result->free();
        return  $tasklist;
    }

    //获取日志统计
    static function getLogCount($from, $to, $interval, $type){
        $query=[
            "query"=> [
                "bool"=> [ //filtered
                    "must"=> [ //filter
                        "range"=> [
                            "time"=> [
                                "gte"=>$from,
                                "lte"=>$to
                            ]
                        ]
                    ]
                ]
            ],
            "size" => 0,
            "aggs" => [
                "countbytime" => [
                    "date_histogram" => [
                        "field" => "time",
                        "interval" => $interval
                    ],
                    "aggs" => [
                        "countbyspider" => [
                            "terms" => [
                                "field" => "spider"
                            ]
                        ]
                    ]
                ],
                "countbytype"=>[
                    "terms" => [
                        "field" => "type"
                    ]
                ]
            ]
        ];
        return EsConnector::search('zspiderlog-*',$type,$query);
    }

    //获取文档数量
    static function getDocCount($from, $to, $interval, $doctype){
        $query=[
            "query"=> [
                "bool"=> [
                    "must"=> [
                        "range"=> [
                            "time"=> [
                                "gte"=>$from,
                                "lte"=>$to
                            ]
                        ]
                    ]
                ]
            ],
            "size" => 0,
            "aggs" => [
                "countbytime" => [
                    "date_histogram" => [
                        "field" => "time",
                        "interval" => $interval
                    ]
                ]
            ]
        ];
        return EsConnector::search('zspider',$doctype,$query);
    }
}