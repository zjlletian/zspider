<?php
require_once(dirname(dirname(dirname(__FILE__))).'/Config.php');

if(!isset($_POST['name'])){
    die("null name posted ");
}

if(empty($_SERVER["REMOTE_ADDR"])){
    die("cant get ip");
}

$name=$_POST['name'];
$ip=$_SERVER["REMOTE_ADDR"];

Dashboard::useMysql();
echo DashBoard::spiderReport($name,$ip);
