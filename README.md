ZSpider分布式网站爬虫  开发日志 verison 7.4.1

--------------------- 运行前提 ---------------------

1.运行脚本需要screen与awk工具支持。

2.php版本>=5.4。

3.使用Elasticsearch-php作为ES连接工具。https://github.com/elastic/elasticsearch-php


--------------------- 开发任务 ----------------------

完成关键词搜索web api。

任务日志图形化统计。


--------------------- 优化任务 ---------------------

优化es的文本分析器。

完善特殊网站的规则，最好使用正则表达式操作。


--------------------- 2016-3-3 version 7.4.1 ---------------------

1.优化代码中mysql操作的写法，优化sql语句的写法。

2.增加DashBoard专门处理Web请求。

3.爬虫执行过程中增加对ES连接以及ES执行结果的检查。

4.不同等级的任务具有不同的初始时间。


--------------------- 2016-3-3 version 7.3.0 ---------------------

1.去除保存到html字段，增加md5字段。

2.将原有的es中websites类型更名html，兼容后期更多类型的文档，如doc，pdf等。

3.由于任务处理速度过快，将新链接转储的并发量提升到100。


--------------------- 2016-3-3 version 7.2.0 ---------------------

1.使用两种方式检测字符集，避免当meta中不包含字符集信息时phpquery出错的问题。


--------------------- 2016-3-3 version 7.1.0 ---------------------

1.修复phpquery造成大量内存占用的问题。

2.使用hash避免同一时间多个进程同时读取一个任务，造成大量null返回。


--------------------- 2016-3-3 version 7.0.0 ---------------------

1.将基于正则的simple_html_dom替换为基于dom的phpquery来解析HTML。

2.只对level>0的任务进行findlinks操作。


--------------------- 2016-3-3 version 6.8.4 ---------------------

1.实现任务日志查询与文档数量查询API


--------------------- 2016-3-2 version 6.8.3 ---------------------

1.优化日志记录，细化耗时记录到毫秒。



--------------------- 2016-3-1 version 6.8.2 ---------------------

1.优化shell的help界面样式，更友好和美观。

2.记录任务的各阶段耗时。


--------------------- 2016-2-29 version 6.8.0 ---------------------

1.优化从HTML提取纯文本的步骤，降低系统资源消耗，在数据量大的页面中效果提升非常明显。

2.缩短进度条默认长度60s为30s。


--------------------- 2016-2-28 version 6.7.0 ---------------------

1.解决dashboard中爬虫机器名称错误的问题。

2.将 time()函数替换成mysql自带求时间戳函数，避免不同机器执行时的时间不一致问题。


--------------------- 2016-2-27 version 6.6.2 ---------------------

1.解决DashBoard请求ajax太频繁的问题。

2.使用定时任务收集队列信息，避免DashBoard单次请求耗时巨大的问题。


--------------------- 2016-2-26 version 6.6.0 ---------------------

1.DashBoard中任务以进度条展示。

2.优化任务超时处理。

3.给队列加入uniqid, 修正队列启动时大量并发进程同时读取一个任务的错误。


--------------------- 2016-2-25 version 6.5.0 ---------------------

1.增加爬虫机器心跳检测，定时报告爬虫机器信息。

2.newlinks表回归，使用多线程与hash方式查找，提升转储性能。


--------------------- 2016-2-24 version 6.2.1 ---------------------

1.优化ACK处理，加入单个任务超时限制。

2.优化错误类型处理。

3.快慢表前期对性能提升明显，但中后期由于数据量增大，速度降低较多，所以移除newlinks表。

4.修复字符集编码识别的错误。

5.增加爬虫机器身份标识。

6.增加queue Dashboard

--------------------- 2016-2-24 version 6.0.2 ---------------------

1.将是否把新连接加入队列的处理逻辑交给队列服务器处理。

2.优化screen输出，优化日志显示。

3.增加错误记录,ack超时超过4次的任务也将判定为错误任务,每10天自动清理错误记录。

4.发生程序致命错误时重启线程。


--------------------- 2016-2-22 version 5.1.2 ---------------------

1.优化href拼接的逻辑。

2.使用ES记录任务处理日志。

3.增加系统错误日志记录功能。

4.预留邮件发送接口配置文件。


--------------------- 2016-2-22 version 5.0.1 ---------------------

1.优化shell提示，将shell放入bin中。


--------------------- 2016-2-22 version 5.0.0 ---------------------

1.将任务队列存储替换成为mysql（数据库结构:/doc/zspider.sql），将队列任务监视独立成为服务。

2.shell优化：将原有shell合并，并增加查看进程状态等功能。

3.增加单个网址解析测试功能。

4.将任务日志记录从TaskManager中移除；使用TaskHandler创建进程与处理任务。


--------------------- 2016-2-18 version 4.0.0 ---------------------

1.自动加载class文件夹下的所有类。

2.将ES的存储与搜索等相关操作独立成为EsOpreator,使TaskManager专注于调度任务。


--------------------- 2016-2-18 version 3.0.1 ---------------------

1.不同的等级使用不同的更新时间。


--------------------- 2016-2-18 version 3.0.0 ---------------------

1.使用shell脚本来管理所有进程。

2.使用多线程执行爬虫任务。


--------------------- 2016-2-18 version 2.0.0 ---------------------

1.增加web管理界面。

2.增加任务日志记录功能。


--------------------- 2016-2-17 version 1.3.0 ---------------------

1.将原来TaskManager中的addNewTask,saveUrlInfo,addUpdateTask,ackTask合并成为submitTask，减少Spider调用TaskManager的次数。

2.使用config来配置默认起始站点。

3.获取html时，过滤html中无用的标签信息。


--------------------- 2016-2-16 version 1.2.0 ---------------------

1.限制html的大小，避免内存泄漏。

2.使用异步方式将mongo中的数据转储到es中，加快爬虫速度。

3.优化重定向后地址的检查与处理。

4.使用Wathcer来运行队列子进程。


--------------------- 2016-2-15 version 1.0.0 ---------------------

1.优化内存回收。

2.优化UrlAnalyzer中自定义错误的处理。

3.修复curl扩展不能识别301重定向的问题。

4.优化启动方式。


--------------------- 2016-2-14 version 0.9.2 ---------------------

1.增加队列的ack机制，使用子线程管理ack。

2.屏蔽错误的charset。


--------------------- 2016-2-13 version 0.9.1 ---------------------

1.解决由于重定向导致的重复爬取问题。

2.优化shell输出。


--------------------- 2016-2-12 version 0.8.1 ---------------------

1.合并原有updateTask队列与spiderTask队列为taskQueue


--------------------- 2016-2-11 version 0.8.0 ---------------------

1.优化UrlAnalyzer获取charset的方法。

2.优化SpiderTask.php的处理逻辑。
