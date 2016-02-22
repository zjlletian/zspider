<?php
include_once(dirname(dirname(__FILE__)).'/Config.php');

$url=isset($argv[1])? $argv[1] : 'http://zjlup.com';

$urlinfo=UrlAnalyzer::getResponse($url,0,null,true);

if(!isset($urlinfo['error'])){
	echo "Url: ".$urlinfo['url']."\n";
	echo "Title: ".$urlinfo['title']."\n";
	echo "Charset: ".$urlinfo['charset']."\n";
	echo "Html size: ".number_format(strlen($urlinfo['html'])/1024,1)."KB\n";
	echo "Links count: ".count($urlinfo['links'])."\n";
	foreach ($urlinfo['links'] as $link) {
		echo $link."\n";
	}
}
else{
	Util::echoRed("Get Info failed: ".$url."\nError Message: ".$urlinfo['error']."\n");
}
