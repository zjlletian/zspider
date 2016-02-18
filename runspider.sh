#!/bin/sh

ps aux > screens.tmp
awk '/zspider/ { print $0 >> "zspider.run"; }' screens.tmp
rm screens.tmp -f
if [ -f zspider.run ];then
	echo 'ZSpider is on running, you cann not start a new task.'
	cat zspider.run
	rm zspider.run -f
	echo
	exit 1
fi
screen -dmS zspider php $(cd `dirname $0`; pwd)/shell/SpiderTask.php
echo "ZSpider is now on runing, use 'screen -r zspider' to see monitor."
ps aux | grep zspider