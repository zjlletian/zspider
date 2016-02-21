#!/bin/sh

#帮助信息
function showhelp(){
	echo -e "Command error. Valid commands: 'start','stop','screen'"
}

#启动Queue watcher
function start(){
	ps aux | awk '/qwatcher.php/ && !/awk/ { print $0 >> "qwatcher.run"; }'
	if [ -f qwatcher.run ];then
		echo -e "Queue watcher exists, use command 'screen' to show screen."
		rm qwatcher.run -f
		exit 0
	fi
	screen -dmS qwatcher php $(cd `dirname $0`; pwd)/phpcli/qwatcher.php byscreen
	echo -e "Queue watcher is now on runing, use command 'screen' to show screen."
}

#停止Queue watcher, 通常screen进程结束，子进程也会停止，但为了防止子进程没有退出，仍然做检查并且kill
function stop(){
	ps aux | awk '/SCREEN -dmS qwatcher/ && !/awk/ {system("kill -9 "$2);}'
	ps aux | awk '/qwatcher.php/ && !/awk/ {system("kill -9 "$2);}'
	echo -e "Queue watcher stoped."
	screen -wipe > /dev/null
}

#检查参数个数
if [ $# -ne 1 ];then
	showhelp
	exit 0
fi

if [ $1 = 'start' ];then
	start
elif [ $1 = 'stop' ];then
	stop
elif [ $1 = 'screen' ];then
	screen -r qwatcher
else
   showhelp
fi
