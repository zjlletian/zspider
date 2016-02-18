#!/bin/sh

screen -dmS zspider php $(cd `dirname $0`; pwd)/shell/SpiderTask.php
echo "ZSpider is Runing, use 'screen -r zspider' to see monitor."