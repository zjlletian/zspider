
$(function(){
    loadQueueInfo();
    loadTotalDoc();
    loadTodayDoc();
    showdoc();
});

var showdocb=false;
var showqueueb=false;
var showtaskb=false;

function showdoc(){
    showdocb=true;
    showqueueb=false;
    showtaskb=false;
    $('#docboard').css("display","");
    $('#queueboard').css("display","none");
    $('#taskboard').css("display","none");
    loadDocCount();
}

function showqueue(){
    showdocb=false;
    showqueueb=true;
    showtaskb=false;
    $('#docboard').css("display","none");
    $('#queueboard').css("display","");
    $('#taskboard').css("display","none");
}

function showtask(){
    showdocb=false;
    showqueueb=false;
    showtaskb=true;
    $('#docboard').css("display","none");
    $('#queueboard').css("display","none");
    $('#taskboard').css("display","");
    loadTaskList();
}

//加载队列与在线爬虫列表
var spiderinfoh='<tr><th>机器标识</th> <th>IP</th> <th>执行中任务数量</th> </tr>';
var spiderinfo='<tr><td>{$name}</td> <td>{$ip}</td> <td>{$tasks}</td> </tr>';
function loadQueueInfo(){
    $.get('/json/queueinfo.php',function(data){
        //队列任务信息
        $('#newtask').html(data.new);
        $('#updatetask').html(data.update_now);

        //在线爬虫机器
        $('#spidercount').html(data.spiders.length);
        slist=spiderinfoh;
        for(var i=0;i<data.spiders.length;i++){
            slist+=spiderinfo.replace('{$name}',data.spiders[i].name).replace('{$ip}',data.spiders[i].ip).replace('{$tasks}',data.spiders[i].tasks);
        }
        $('#spiderlist').html(slist);
        setTimeout("loadQueueInfo()",1000);
    });
}

//文档总数
function loadTotalDoc(){
    $.get('/json/countdoc.php?intv=24000h&from=1994-10-26 00:00:00',function(data){
        $("#totaldoc").html(data.total);
        setTimeout("loadTotalDoc()",1000);
    });
}

//今日文档数量
function loadTodayDoc(){
    $.get('/json/countlog.php',function(data){
        $("#todaynew").html(data.new);
        $("#todayupdate").html(data.update);
        setTimeout(" loadTodayDoc()",1000);
    });
}

//正在执行的任务信息
var tasktemplet='<a href="{$href}" target="_blank">{$hreftext}</a><br>执行爬虫:{$spider}&nbsp;&nbsp;&nbsp;次数:{$times}&nbsp;&nbsp;&nbsp;等级:{$level}&nbsp;&nbsp;&nbsp;类型:{$type}&nbsp;&nbsp;&nbsp;预定时间:{$time}&nbsp;&nbsp;&nbsp;延迟:{$delay}<div id="{$id}p" class="progress"><div id="{$id}pb" class="progress-bar progress-bar-{$color} progress-bar-striped active" role="progressbar"style="text-align:left;padding-left:1%;min-width:6%;width:{$width}%">{$msg}</div></div>';
function loadTaskList(){
    if(showtaskb){
        $.get('/json/tasklist.php',function(tasks){
            tlist='';
            var wait=0;
            var error=0;
            for(var i=0;i<tasks.length;i++){
                taskbar=tasktemplet.replace('{$href}',tasks[i].url).replace('{$hreftext}',(tasks[i].url.length>80?tasks[i].url.substr(0,80)+'....':tasks[i].url));
                taskbar=taskbar.replace('{$level}',tasks[i].level).replace('{$type}',(tasks[i].type==0?'新页面':'更新'));
                taskbar=taskbar.replace('{$time}',tasks[i].time).replace('{$delay}',tasks[i].delay).replace('{$spider}',tasks[i].spider);
                taskbar=taskbar.replace('{$id}',tasks[i].id).replace('{$id}',tasks[i].id);
                taskbar=taskbar.replace('{$width}',tasks[i].cost/tasks[i].max*100).replace('{$times}',tasks[i].times);
                //设置进度条颜色
                if(tasks[i].cost<(0.2*tasks[i].max)){
                    taskbar=taskbar.replace('{$color}','success');
                }
                else if(tasks[i].cost<(0.6*tasks[i].max)){
                    taskbar=taskbar.replace('{$color}','info');
                }
                else if(tasks[i].cost<tasks[i].max){
                    taskbar=taskbar.replace('{$color}','warning');
                }
                else{
                    taskbar=taskbar.replace('{$color}','danger');
                }
                //设置进度条文字
                if(tasks[i].cost<=tasks[i].max){
                    taskbar=taskbar.replace('{$msg}',tasks[i].cost+"/"+tasks[i].max);
                }
                else if(tasks[i].times<4 && (10-tasks[i].cost+tasks[i].max)>=0 ){
                    wait+=1;
                    taskbar=taskbar.replace('{$msg}','等待爬虫响应: '+(10-tasks[i].cost+tasks[i].max)+'秒后将转交给其他爬虫处理');
                }
                else if(tasks[i].times<4 && (10-tasks[i].cost+tasks[i].max)<0 ){
                    error+=1;
                    taskbar=taskbar.replace('{$msg}','任务超时：等待其他爬虫处理');
                }
                else if(tasks[i].times>=4 && (10-tasks[i].cost+tasks[i].max)>=0){
                    wait+=1;
                    taskbar=taskbar.replace('{$msg}','等待爬虫响应: '+(10-tasks[i].cost+tasks[i].max)+'秒后任务将删除');
                }
                else{
                    error+=1;
                    taskbar=taskbar.replace('{$msg}','任务超时次数过多: 任务将删除');
                }
                tlist+=taskbar;
            }
            $('#tasks').html(tlist);
            $('#onprocess').html('实时任务进度&nbsp;&nbsp;( 执行:'+(tasks.length-error)+'&nbsp;&nbsp;等待:'+wait+'&nbsp;&nbsp;超时:'+error+' )');

            setTimeout("loadTaskList()",1000);
        });
    }
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
    return year+"-"+(date>9?month:'0'+month)+"-"+(date>9?date:'0'+date)+" "+(hour>9?hour:'0'+hour)+":"+(minute>9?minute:'0'+minute)+":"+(second>9?second:'0'+second);
}

//显示文档更新数量
var doccount = echarts.init(document.getElementById('doccount'));
function loadDocCount(){
    if(showdocb){
        timeto=getTimeStr(0);
        timefrom=getTimeStr(-7200);
        $.get("/json/countlog.php?intv=1m&from="+timefrom+"&to="+timeto,function(data){
            time=[];
            totalcount=[];
            newcount=[];
            updatecount=[];
            if(data.interval.length>0){
                for(var i=0;i<data.interval.length-1;i++){
                    timestr=data.interval[i].time;
                    time.push(timestr.substr(5,11));
                    totalcount.push(data.interval[i].count);
                    newcount.push(data.interval[i].new);
                    updatecount.push(data.interval[i].update);
                }
            }
            // 指定图表的配置项和数据
            var option = {
                title: {
                    text:'      每分钟爬取文档数量 ('+timefrom.substr(0,16)+' - '+timeto.substr(0,16)+')'
                },
                tooltip: {
                    trigger: 'axis',
                    axisPointer: {
                        animation: false
                    }
                },
                xAxis: {
                    data:time
                },
                yAxis: {
                    type: 'value',
                    boundaryGap: [0,'100%'],
                    splitLine: {
                        show:false
                    }
                },
                series: [{
                    name: '总量',
                    type: 'line',
                    hoverAnimation:true,
                    lineStyle: {
                        normal: {
                            color:'rgb(255,30,30)',
                            width: 1
                        }
                    },
                    itemStyle : { 
                        normal: {
                            color:'rgb(255,30,30)',
                            opacity:0
                        }
                    },
                    areaStyle: {
                        normal:{
                            color:'rgb(255,30,30)',
                            opacity:0.2
                        }
                    },
                    data:totalcount
                },{
                    name: '新增',
                    type: 'line',
                    lineStyle: {
                        normal: {
                            opacity:0
                        }
                    },
                    itemStyle : { 
                        normal: {
                            opacity:0
                        }
                    },
                    data:newcount
                },{
                    name: '更新',
                    type: 'line',
                    lineStyle: {
                        normal: {
                            opacity:0
                        }
                    },
                    itemStyle : { 
                        normal: {
                            opacity:0
                        }
                    },
                    data:updatecount
                }]
            };
            doccount.setOption(option);
            setTimeout("loadDocCount()",60000);
        });
    }
}
