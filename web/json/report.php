<?php
require_once(dirname(dirname(dirname(__FILE__))).'/Config.php');

if(!isset($_POST['name'])){
    die("can't get name");
}

if(empty($_SERVER["REMOTE_ADDR"])){
    die("can't get ip");
}

$name=$_POST['name'];
$ip=$_SERVER["REMOTE_ADDR"];

Dashboard::useMysql();
json_encode(DashBoard::spiderReport($name,$ip));
