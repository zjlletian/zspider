ZSpider分布式网站爬虫  开发日志 verison 0.9.2

--------------------- 遗留问题 ---------------------

加入mongodb操作的try-catch。

增加mongodb索引。

特殊网站的规则。

优化es的Analyzer。

减少存入es的htmltext文本长度。

使用正则表达式操作字符串。

优化内存回收。


at version 0.9.2
--------------------- 2016-2-14 ---------------------

1.增加队列的ack机制，使用子线程管理ack。

2.屏蔽错误的charset。

at version : 0.9.1
--------------------- 2016-2-13 ---------------------

1.解决由于重定向导致的重复爬取问题。

2.优化shell输出。

--------------------- 2016-2-12 ---------------------

1.合并原有updateTask队列与spiderTask队列为taskQueue


at version : 0.8.1
--------------------- 2016-2-11 ---------------------

1.优化UrlAnalyzer获取charset的方法。

2.优化SpiderTask.php的处理逻辑。