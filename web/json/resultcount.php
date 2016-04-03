<?php
require_once(dirname(dirname(dirname(__FILE__))).'/Config.php');
header('Content-type:text/json;charset:utf-8');

$from=isset($_GET['from'])? $_GET['from'] : date("Y-m-d H:i:",time()-3600*24)."00";
$to=isset($_GET['to'])?$_GET['to'] : date("Y-m-d H:i:",time())."00";

$result['suc']['new']= 0;
$result['suc']['update']= 0;
$result['err']['new']= 0;
$result['err']['update']= 0;

Dashboard::useES();
$logcount=Dashboard::getLogCount($from, $to, '24h', 'success');
foreach ($logcount['aggregations']["countbytime"]["buckets"] as $timeinterval) {
    foreach($timeinterval["countbytype"]["buckets"] as $typecount){
        if($typecount['key']=="New") {
            $result['suc']['new']+=$typecount['doc_count'];
        }
        else if($typecount['key']=="Update") {
            $result['suc']['update']+=$typecount['doc_count'];
        }
    }
}

$logcount=Dashboard::getLogCount($from, $to, '24h', 'error');
foreach ($logcount['aggregations']["countbytime"]["buckets"] as $timeinterval) {
    foreach($timeinterval["countbytype"]["buckets"] as $typecount){
        if($typecount['key']=="New") {
            $result['err']['new']+=$typecount['doc_count'];
        }
        else if($typecount['key']=="Update") {
            $result['err']['update']+=$typecount['doc_count'];
        }
    }
}

$result['total']=$result['suc']['new']+$result['suc']['update']+$result['err']['new']+$result['err']['update'];
echo json_encode($result);
