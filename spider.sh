#!/bin/sh

#帮助信息
function showhelp(){
	echo -e "Command error. Valid commands: 'start','stop','screen','status'"
}

#启动Zspider
function start(){
	ps aux | awk '/zspider.php/ && !/awk/ { print $0 >> "zspider.run"; }'
	if [ -f zspider.run ];then
		echo -e "ZSpider exists, use command 'screen' to show screen or 'status' to show process status."
		rm zspider.run -f
		exit 0
	fi
	screen -dmS zspider php $(cd `dirname $0`; pwd)/phpcli/zspider.php byscreen
	echo -e "ZSpider is now on runing, use command 'screen' to show screen or 'status' to show process status."
}

#停止Zspider, 通常screen进程结束，子进程也会停止，但为了防止子进程没有退出，仍然做检查并且kill
function stop(){
	ps aux | awk '/SCREEN -dmS zspider/ && !/awk/ {system("kill -9 "$2);}'
	ps aux | awk '/zspider.php/ && !/awk/ {system("kill -9 "$2);}'
	echo -e "ZSpider stoped."
	screen -wipe > /dev/null
}

#以ps aux显示进程状态，并统计相关信息
function status(){
	ps aux | awk '
	BEGIN{
		count=0;
		"cat /proc/cpuinfo| grep processor| wc -l" | getline cpus;
	}
	/zspider.php/&& !/awk/ && !/SCREEN -dmS/{
		cpu+=$3;
		mem+=$6;
		count+=1;
	}
	END {
		print "Process count:"count"\nCPU:"cpu/cpus"%\nMemory:"mem/1024"MB";
	}'
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
	screen -r zspider
elif [ $1 = 'status' ];then
	status
else
   showhelp
fi
