<?php
	include_once(dirname(dirname(__FILE__)).'/Config.php');
	include_once('TaskManager.class.php');
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
	正在处理中的任务：<br>
	<?php
		foreach ($queueinfo['onprocess'] as $task) {
			echo "level:".$task['level']." ".$task['url']."<br>";
		}
	?>
	<br>
	等待爬取的新网页数量：<?php echo $queueinfo['new_task'];?>
	<br><br>
	需要更新的网页数量：<?php echo $queueinfo['update_task'];?>
	<br><br>
	爬取过的网页总量：<?php echo $queueinfo['new_log'];?>
	<br><br>
	待转储列队文档数量：<?php echo $queueinfo['ontransport'];?>
	<br><br>
</body>
</html>