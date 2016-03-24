
$(function(){
    loadQueueInfo();
    loadTotalDoc();
    loadTodayDoc();
    showdoc();
	loadDocCount();
	loadAvgTime();
});

var showtaskb=false;

function showdoc(){
    showtaskb=false;
    newtasklist=[];
    tasklist=[];
    $('#docboard').css("display","");
    $('#taskboard').css("display","none");
    $('#spiderboard').css("display","none");
}

function showtask(){
    showtaskb=true;
    newtasklist=[];
    tasklist=[];
    $('#docboard').css("display","none");
    $('#taskboard').css("display","");
    $('#spiderboard').css("display","none");
    loadTaskList();
}

function showspiders(){
    showtaskb=false;
    $('#docboard').css("display","none");
    $('#taskboard').css("display","none");
    $('#spiderboard').css("display","");
}

//加载队列与在线爬虫列表
var spiderinfoh='<tr><th width="80px">机器标识</th> <th width="90px">IP</th> <th width="80px">进程</th> <th width="40px">负载</th></tr>';
var spiderinfo='<tr><td>{$name}</td> <td>{$ip}</td> <td>{$tasks}</td><td><div style="width: 12px;height: 12px;border-radius:6px;background:{$color}"></div></td> </tr>';
function loadQueueInfo(){
    $.get('/json/queueinfo.php?r='+Math.random(),function(data){
        //队列任务信息
        $('#newtask').html(data.new);
        $('#updatetask').html(data.update_now);

        //在线爬虫机器
        $('#spidercount').html(data.spiders.length);
        slist=spiderinfoh;
        tasks=0;
        for(var i=0;i<data.spiders.length;i++){
            if(data.spiders[i].sysload.cpuload>5 || data.spiders[i].sysload.cpuused>90  || (data.spiders[i].sysload.memused/data.spiders[i].sysload.memtotal)>0.9){
                color='red';
            }
            else if(data.spiders[i].sysload.cpuload>3 || data.spiders[i].sysload.cpuused>75  || (data.spiders[i].sysload.memused/data.spiders[i].sysload.memtotal)>0.75){
                color='orangered';
            }
            else if(data.spiders[i].sysload.cpuload>1.5 || data.spiders[i].sysload.cpuused>60  || (data.spiders[i].sysload.memused/data.spiders[i].sysload.memtotal)>0.6){
                color='orange';
            }
            else{
                 color='green';
            }
            slist+=spiderinfo.replace('{$name}',data.spiders[i].name).replace('{$ip}',data.spiders[i].ip).replace('{$tasks}',data.spiders[i].tasks+'/'+data.spiders[i].sysload.running+'/'+data.spiders[i].handler).replace('{$color}',color);
            tasks+=parseInt(data.spiders[i].tasks);
        }
        $('#spiderlist').html(slist);
        $('#onprosess').html(tasks);
        setTimeout("loadQueueInfo()",1000);
    });
}

//文档总数
function loadTotalDoc(){
    $.get('/json/countdoc.php?r='+Math.random(),function(data){
        $("#totaldoc").html(data.total);
        setTimeout("loadTotalDoc()",1000);
    });
}

//今日文档数量
function loadTodayDoc(){
    $.get('/json/countlog.php?type=success&intv=24h&r='+Math.random(),function(data){
        $("#todaynew").html(data.new);
        $("#todayupdate").html(data.update);
        setTimeout(" loadTodayDoc()",1000);
    });
}

//进度条信息展示
var tasktemplet='<div id="taskdiv-{$id}"><a href="{$href}" target="_blank">{$hreftext}</a><br>' +
    '执行爬虫:{$spider}&nbsp;&nbsp;&nbsp;次数:{$times}&nbsp;&nbsp;&nbsp;等级:{$level}&nbsp;&nbsp;&nbsp;类型:{$type}&nbsp;&nbsp;&nbsp;预定时间:{$time}&nbsp;&nbsp;&nbsp;延迟:{$delay}' +
    '<div class="progress"><div id="pb-{$id}" class="progress-bar progress-bar-striped active progress-bar-success"  role="progressbar" style="text-align:left;padding-left:0.5%;min-width:4%;"' +
    ' data-color="progress-bar-success" data-times="{$times}" data-cost="{$cost}" data-max="{$max}"></div></div></div>';
var tasklist=[];
var newtasklist=[];
function timebarinc(barid) {
    if (showtaskb && (tasklist.indexOf(barid) != -1 || newtasklist.indexOf(barid) != -1)) {
        bar= $("#pb-"+barid);
        times=parseInt(bar.attr('data-times'));
        cost=parseFloat(bar.attr('data-cost'));
        max=parseFloat(bar.attr('data-max'));

        //设置进度条颜色
        if(cost<10){
            newcolor="progress-bar-success";
         }
         else if(cost<30){
           newcolor="progress-bar-info";
         }
         else if(cost<max){
            newcolor="progress-bar-warning";
         }
         else{
           newcolor="progress-bar-danger";
         }
        if(bar.attr('data-color')!=newcolor){
            bar.removeClass(bar.attr('data-color'));
            bar.addClass(newcolor);
            bar.attr('data-color',newcolor);
        }

         //设置进度条文字
         if(cost<=max){
             bar.html(parseInt(cost)+"/"+parseInt(max));
         }
         else if(times<4 && (10-cost+max)>=0 ){
             bar.html('等待爬虫响应: '+parseInt(10-cost+max)+'秒后将转交给其他爬虫处理');
         }
         else if(times<4 && (10-cost+max)<0 ){
             bar.html('任务超时：等待其他爬虫处理');
         }
         else if(times>=4 && (10-cost+max)>=0){
             bar.html('等待爬虫响应: '+parseInt(10-cost+max)+'秒后任务将删除');
         }
         else{
             bar.html('任务超时次数过多: 任务将删除');
         }
        bar.css('width',cost/max*100+'%');
        bar.attr('data-cost',cost+0.1);
        setTimeout("timebarinc('"+barid+"')", 100);
    }
    else{
        fadeoutBar(barid);
    }
}

//进度条淡出
function fadeoutBar(barid){
    $("#taskdiv-"+barid).fadeOut(300);
    setTimeout("removeBar('"+barid+"')", 500);
}

//删除进度条节点
function removeBar(barid){
    $("#taskdiv-"+barid).remove();
}

//正在执行的任务信息
function loadTaskList(){
    $.get('/json/tasklist.php?r='+Math.random(),function(tasks){
        newtasklist=[];
        wait=0;
        error=0;
        for(i=0;i<tasks.length;i++){
            newtasklist.push(tasks[i].uniqid);
            if(tasklist.indexOf(tasks[i].uniqid)==-1){
                taskbar=tasktemplet.replace('{$id}',tasks[i].uniqid).replace('{$id}',tasks[i].uniqid);
                taskbar=taskbar.replace('{$href}',tasks[i].url).replace('{$hreftext}',(tasks[i].url.length>100?tasks[i].url.substr(0,100)+'....':tasks[i].url));
                taskbar=taskbar.replace('{$times}',tasks[i].times).replace('{$times}',tasks[i].times).replace('{$level}',tasks[i].level).replace('{$type}',(tasks[i].type==0?'新页面':'更新'));
                taskbar=taskbar.replace('{$time}',tasks[i].time).replace('{$delay}',tasks[i].delay).replace('{$spider}',tasks[i].spider);
                taskbar=taskbar.replace('{$cost}',tasks[i].cost).replace('{$max}',tasks[i].max);
                $('#tasks').append(taskbar);
                timebarinc(tasks[i].uniqid);
            }
             if((tasks[i].cost>tasks[i].max)&&10-tasks[i].cost+tasks[i].max>=0 ){
                wait+=1;
            }
            else if(tasks[i].cost>tasks[i].max){
                error+=1;
            }
        }
        tasklist=newtasklist;
        $('#onprocess').html('实时任务进度&nbsp;&nbsp;( 执行:'+(tasks.length-wait-error)+'&nbsp;&nbsp;等待:'+wait+'&nbsp;&nbsp;超时:'+error+' )');
        if(showtaskb){
        	setTimeout("loadTaskList()",1000);
    	}
    });
}

//时间戳格式化
function getTimeStr(offset) {
    now=new Date();
    now.setTime(Date.parse(new Date())+offset*1000);
    year=now.getFullYear();
    month=now.getMonth()+1;
    date=now.getDate();
    hour=now.getHours();
    minute=now.getMinutes();
    second=now.getSeconds();
    return year+"-"+(month>9?month:'0'+month)+"-"+(date>9?date:'0'+date)+" "+(hour>9?hour:'0'+hour)+":"+(minute>9?minute:'0'+minute)+":"+(second>9?second:'0'+second);
}

//显示文档更新数量
var doccount = echarts.init(document.getElementById('doccount'));
function loadDocCount(){
    timeto=getTimeStr(-60);
    timefrom=getTimeStr(-3600*24);
    $.get("/json/countlog.php?type=success&intv=1m&from="+timefrom+"&to="+timeto+'&r='+Math.random(),function(data){
        time=[];
        newcount=[];
        updatecount=[];

        for(i=-3600*24;i<-60;i+=60){
        	timestr=getTimeStr(i).substr(0,16)+':00';
        	time.push(timestr.substr(5,11));
        	if(data.interval.length>0 && data.interval[0].time<=timestr){
        		intv=data.interval.shift();
        		newcount.push(intv.new);
                updatecount.push(intv.update);
        	}
        	else{
        		newcount.push(0);
                updatecount.push(0);
        	}
        }

        // 指定图表的配置项和数据
        var option = {
            title: {
                text:'24小时内获取文档数量 ( 新增 '+data.new+'  更新 '+data.update+' )',
                subtext:'基于 '+timefrom.substr(0,16)+' 至 '+timeto.substr(0,16)+' 任务日志分析',
                x: 'center',
                top:-5
            },
            legend: {
                data:['新增','更新'],
                x: 'left'
            },
            tooltip: {
                trigger: 'axis'
            },
            xAxis: {
                data:time
            },
            yAxis: {
                type: 'value',
                name: '处理速度(文档/分钟)'
            },
            series: [{
                name: '新增',
                hoverAnimation:true,
                type: 'line',
                itemStyle : {
                    normal: {
                        opacity:0.8,
                        color:'#1b809e'
                    }
                },
                lineStyle: {
                    normal: {
                        opacity:0.8
                    }
                },
                areaStyle: {
                    normal: {
                        opacity:0.2
                    }
                },
                data:newcount
            },{
                name: '更新',
                hoverAnimation:true,
                type: 'line',
                itemStyle : {
                    normal: {
                        opacity:0.8,
                        color:'#eb9316'
                    }
                },
                lineStyle: {
                    normal: {
                        opacity:0.8
                    }
                },
                areaStyle: {
                    normal: {
                        opacity:0.2
                    }
                },
                data:updatecount
            }],
            dataZoom: [{
                type: 'inside',
                start: 90,
                end: 100
            }, {
                start: 90,
                end: 100
            }]
        };
        doccount.setOption(option);
        setTimeout("loadDocCount()",60000);
    });
}

var avgTime = echarts.init(document.getElementById('avgtime'));
function loadAvgTime(){
	$.get("/json/avgtime?r="+Math.random(),function(data){
		var option = {
	        title: {
	            text:'单文档处理周期 '+data.total+'s (平均链接数 '+parseInt(data.newlinks)+')',
	            subtext:'基于 '+getTimeStr(-120).substr(11,5)+' 至 '+getTimeStr(-60).substr(11,5)+' 任务日志分析',
	            x:'center',
	            top:15
	        },
	        tooltip : {
		        trigger: 'item',
		        formatter: "{b} : {c}s ({d}%)"
		    },
		    series : [{
	            name:'处理周期',
	            type:'pie',
	            radius : [25,95],
	            selectedMode: 'single',
	            center : ['50%',200],
	            roseType : 'area',
	            data:[{
	            	value:data.gettask,
	            	name:'获取任务'
	            },{
	            	value:data.download,
	            	name:'下载文档'
	            },{
	            	value:data.extarct,
	            	name:'提取正文'
	            },{
	            	value:data.findlinks,
	            	name:'提取链接'
	            },{
	            	value:data.saveinfo,
	            	name:'建立索引'
	            },{
                    value:data.submit,
                    name:'提交任务'
                }].sort(function(a,b){return a.value-b.value})
	        }]
	    };
		avgTime.setOption(option);
        setTimeout("loadAvgTime()",60000);
	});
}
