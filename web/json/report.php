<?php
require_once(dirname(dirname(dirname(__FILE__))).'/Config.php');

if(empty($_SERVER["REMOTE_ADDR"])){
    die("can't get ip");
}

if(!isset($_POST['name'])){
    die("can't get name");
}

if(!isset($_POST['handler'])){
    die("can't get handler count");
}

if(!isset($_POST['sysload'])){
    die("can't get sysload info");
}

$name=$_POST['name'];
$handler=$_POST['handler'];
$sysload=$_POST['sysload'];
$ip=$_SERVER["REMOTE_ADDR"];

Dashboard::useMysql();
echo DashBoard::spiderReport($name,$ip,$handler,$sysload);
