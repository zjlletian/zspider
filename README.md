ZSpider 分布式网站爬虫

-----------------ZSpider开发日志 2016-2-11-----------
遗留问题：

1.UpdateTask未处理，如何更新要进一步考虑。

2.TaskManager中获取任务时，findOne()方式会造成广度优先遍历。

3.TaskManager中插入currentTask时要加入超时策略。

4.在mongodb中建立索引加快查询速度。

5.优化es的Analyzer。

6.队列增加ack机制。