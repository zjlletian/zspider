#!/bin/sh
echo -e "zspider were killed :"
ps aux > $(cd `dirname $0`; pwd)shellpid.tmp
awk '/zspider/ { system("kill -9 "$2)}' $(cd `dirname $0`; pwd)shellpid.tmp
rm -f $(cd `dirname $0`; pwd)shellpid.tmp
screen -wipe zspider
ps aux | grep 'zspider'
