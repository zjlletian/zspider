<?php
require_once(dirname(dirname(__FILE__)).'/Config.php');

$now=microtime(true);
if(count($argv)<2){
	die("No url was given.\n");
}
$url=$argv[1];
$urlinfo=UrlAnalyzer::getInfoOnce($url,0,null,true);

if(!isset($urlinfo['error'])){

	if(isset($argv[2]) && $argv[2]=='show'){
		echo "+--------------------------------------------------------------------------------------------------------------------------------------+\n";
		foreach ($urlinfo['links'] as $href=>$link) {
			echo "| Href: {$href}\n| Link: {$link}\n";
			echo "+--------------------------------------------------------------------------------------------------------------------------------------+\n";
		}
		echo "\n--------------------------------------------------------------- Text ----------------------------------------------------------------\n";
		echo $urlinfo['text']."\n\n";
	}

	echo "Url: ".$urlinfo['url']."\n";
	echo "Title: ".$urlinfo['title']."\n";
	echo "Charset: ".$urlinfo['charset']."\n";
	echo "Links count: ".count($urlinfo['links'])."\n";
	echo "Time: ".round(microtime(true)-$now,3)."s ( download:".$urlinfo['timeinfo']['download']."s extarct:".$urlinfo['timeinfo']['extarct']."s findhref:".$urlinfo['timeinfo']['findlinks']."s )\n\n";
}
else{
	Util::echoRed("Get Info failed: ".$url."\nError Message: ".$urlinfo['error']."\n\n");
}
