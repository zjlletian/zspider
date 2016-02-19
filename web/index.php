<?php
	include_once(dirname(dirname(__FILE__)).'/Config.php');
	TaskManager::connect();
	$queueinfo=TaskManager::getQueueInfo();
?>

<!DOCTYPE html>
<html>
<head>
	<title>ZSpider QueueInfo</title>
	<meta charset="utf-8">
</head>
<body>
<h2>ZSpider QueueInfo</h2>
	等待爬取的新网页数量：<?php echo $queueinfo['new_task'];?>
	<br><br>
	需要更新的网页数量：<?php echo $queueinfo['update_task'];?>
	<br><br>
	爬取过的网页总量：<?php echo $queueinfo['new_log'];?>
	<br><br>
	正在处理中的任务：<?php echo count($queueinfo['onprocess']);?>
	<br><br>
	<?php
		foreach ($queueinfo['onprocess'] as $task) {
			$delay=time()-$task['time'];
			$h=intval($delay/3600);
			$m=intval(($delay-$h*3600)/60);
			$s=intval(($delay-$h*3600)%60);
			echo $task['url']."<br>Type:".$task['type']." Level:".$task['level']." Delay:".$h.' hours '.$m.' minutes '.$s." seconds<br><br>";
		}
	?>
	<br>
</body>
</html>