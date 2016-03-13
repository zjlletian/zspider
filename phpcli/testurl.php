<?php
require_once(dirname(dirname(__FILE__)).'/Config.php');
var_dump(Util::getSysLoad());
$now=microtime(true);
if(count($argv)<2){
	die("No url was given.\n");
}
$url=$argv[1];
$urlinfo=UrlAnalyzer::getInfoOnce($url,0,null,true);

if(!isset($urlinfo['error'])){

	if(in_array("-l",$argv)){
		if(count($urlinfo['links'])>0){
			echo "+--------------------------------------------------------------------------------------------------------------------------------------+\n";
			foreach ($urlinfo['links'] as $href=>$link) {
				echo "| Href: {$href}\n| Link: {$link}\n";
				echo "+--------------------------------------------------------------------------------------------------------------------------------------+\n";
			}
		}
		echo "\n";
	}

	if(in_array("-t",$argv)){
		echo "+------------------------------------------------------------------ Text -------------------------------------------------------------+\n";
		echo $urlinfo['text']."\n";
	}

	echo "Url: ".$urlinfo['url']."\n";
	echo "Title: ".$urlinfo['title']."\n";
	echo "Charset: ".$urlinfo['charset']."\n";
	echo "Links count: ".count($urlinfo['links'])."\n";
	echo "Time: ".round(microtime(true)-$now,3)."s ( download:".$urlinfo['timeinfo']['download']."s extarct:".$urlinfo['timeinfo']['extarct']."s findhref:".$urlinfo['timeinfo']['findlinks']."s )\n";

	if(in_array("-w",$argv)){
		EsConnector::connect();
		TaskManager::connect();
		$times=intval($argv[count($argv)-1])==0?1:intval($argv[count($argv)-1]);
		$total=0;
		$suc=0;
		$max=0;
		$min=0;
		for($i=0;$i<$times;$i++){
			$now=microtime(true);
			$res=Storager::upsertUrlInfo($urlinfo);
			$time=microtime(true)-$now;
			if($res==false){
				$res='failed';
				var_dump(ESConnector::testConnect());
			}
			else{
				$res='success';
				$suc++;
			}
			$total+=$time;
			echo "wtrite to es:".$res.", time:".round($time,3)."s\n";
		}
		echo "Times:".$times." suc:".$suc." avgtime:".round($total/$times,3)."\n";
	}
}
else{
	Util::echoRed("Get Info failed: ".$url."\nError Message: ".$urlinfo['error']."\n\n");
}
