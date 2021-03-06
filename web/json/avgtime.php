<?php
require_once(dirname(dirname(dirname(__FILE__))).'/Config.php');
header('Content-type:text/json;charset:utf-8');

$from=isset($_GET['from'])? $_GET['from'] : date("Y-m-d H:i:",time()-120)."00";
$to=isset($_GET['to'])?$_GET['to'] : date("Y-m-d H:i:",time()-60)."00";

Dashboard::useES();
$logcount=Dashboard::getAvgTime($from,$to)["aggregations"];

$result["total"]=$logcount["avgtotal"]["value"]==null? 0 : round($logcount["avgtotal"]["value"],3);
$result["gettask"]=$logcount["avgtotal"]["value"]==null? 0 : round($logcount["avggettask"]["value"],3);
$result["download"]=$logcount["avgtotal"]["value"]==null? 0 : round($logcount["avgdownload"]["value"],3);
$result["extract"]=$logcount["avgtotal"]["value"]==null? 0 : round($logcount["avgextract"]["value"],3);
$result["findlinks"]=$logcount["avgtotal"]["value"]==null? 0 : round($logcount["avgfindlinks"]["value"],3);
$result["saveinfo"]=$logcount["avgtotal"]["value"]==null? 0 : round($logcount["avgsaveinfo"]["value"],3);
$result["submit"]=$logcount["avgtotal"]["value"]==null? 0 : round($logcount["avgsubmit"]["value"],3);
$result["newlinks"]=$logcount["avgnewlinks"]["value"]==null? 0 : round($logcount["avgnewlinks"]["value"],3);
echo json_encode($result);
