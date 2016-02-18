#!/bin/sh
ps aux > shellpid.tmp
awk '/zspider.php/ { system("kill -9 "$2)}' shellpid.tmp
rm -f shellpid.tmp
echo -e "zspider were killed ."
ps aux | grep zspider