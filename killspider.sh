#!/bin/sh
echo -e "zspider were killed :"
ps aux > shellpid.tmp
awk '/zspider/ { system("kill -9 "$2)}' shellpid.tmp
rm -f shellpid.tmp
screen -wipe zspider
ps aux | grep 'zspider'
