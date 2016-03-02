<?php
require_once(dirname(dirname(__FILE__)).'/Config.php');

$now=microtime(true);
if(count($argv)<2){
	die("not url argv.\n");
}
$url=$argv[1];
$urlinfo=UrlAnalyzer::getInfoOnce($url,0,null,true);

if(!isset($urlinfo['error'])){
	echo "Url: ".$urlinfo['url']."\n";
	echo "Title: ".$urlinfo['title']."\n";
	echo "Charset: ".$urlinfo['charset']."\n";
	echo "Html size: ".number_format(strlen($urlinfo['html'])/1024,1)."KB\n";
	echo "Links count: ".count($urlinfo['links'])."\n";
	echo "Time: ".round(microtime(true)-$now,3)."s ( download:".$urlinfo['timeinfo']['download']."s loadhtml:".$urlinfo['timeinfo']['loadhtml']."s extarct:".$urlinfo['timeinfo']['extarct']."s findhref:".$urlinfo['timeinfo']['findlinks']."s )\n\n";

	if(isset($argv[2]) && $argv[2]=='show'){
		echo "+--------------------------------------------------------------------------------------------------------------------------------------+\n";
		echo "|                               Href                              |                                  Link                              |\n";
		foreach ($urlinfo['links'] as $href=>$link) {
			echo "+--------------------------------------------------------------------------------------------------------------------------------------+\n";
			echo "| ".strformat($href,60)."   |   ".strformat($link,60)."\n";
		}
		echo "+--------------------------------------------------------------------------------------------------------------------------------------+\n\n";

		echo "--------------------------------------------------------------- Text ----------------------------------------------------------------\n";
		echo $urlinfo['text']."\n\n";
	}
}
else{
	Util::echoRed("Get Info failed: ".$url."\nError Message: ".$urlinfo['error']."\n\n");
}

function strformat($str,$len){
	if(strlen($str)>$len){
		return substr($str,0,$len)."...";
	}
	else{
		for($i=0;$i<$len-strlen($str);$i++){
			$str.=" ";
		}
		return $str;
	}
}