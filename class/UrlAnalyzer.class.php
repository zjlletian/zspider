<?php
require_once(dirname(dirname(__FILE__)).'/Config.php');
require_once('phpQuery/phpQuery.php');

class UrlAnalyzer{

	//对url尝试3次获取信息
	static function getInfo($url,$level){
		for($count=1; $count<=3; $count++){
			$response=self::getInfoOnce($url,$level);
			if(!isset($response['error']) || $response['code']>=600){
				break;
			}
		}
		return $response;
	}

	//获取url的信息：url:实际访问的地址，code:状态码，charset:原始字符编码，text:纯文本内容，html:网页快照内容，level:判定后的level，error:错误信息
	static function getInfoOnce($url,$level,$referer=null,$istest=false){
		$now=microtime(true); 
		//设置curl
		$ch = curl_init();
		//curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	    //curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
		curl_setopt($ch, CURLOPT_URL, $url);
	 	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 5000);//设置连接超时时间
	 	curl_setopt($ch, CURLOPT_TIMEOUT_MS, 5000);//设置超时时间
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//1将结果返回，0直接stdout
	    curl_setopt($ch, CURLOPT_ENCODING, "gzip");//支持gzip

	    //处理request header,模拟google浏览器
		$header = array();
		$header[] = "Accept: text/html;q=0.8"; 
		$header[] = "Accept-Encoding: gzip";
		$header[] = "Accept-Language: zh,zh-CN;q=0.8"; 
		$header[] = "Cache-Control: max-age=0"; 
		$header[] = "Connection: keep-alive"; 
		$header[] = "Keep-Alive: 300";
		$header[] = "Accept-Charset: utf-8,ISO-8859-1;q=0.7,*;q=0.7"; 
		$header[] = "User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.109 Safari/537.36";
	    if($referer){
	    	$header[] = 'Referer: '.$referer;
	    }
		curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
		unset($header);

		$response = array('code'=>0);
		$htmltext='';
		try{
			//执行请求，对重定向地址循环执行，最大重定向次数5
			for($loops=0; $loops<5; $loops++) {
				$htmltext = curl_exec($ch);
				$responseheader = curl_getinfo($ch);
				$response['url'] = $responseheader['url'];
				$response['code'] = $responseheader['http_code'];

				//判断地址是否被重定向，没有重定向则退出循环.301重定向在curl中的code是200，要用$response['url']!=$url判断
				if(intval($response['code']/100)==3 || $response['url']!=$url){
					if(empty($responseheader['redirect_url'])){
						$url=$response['url'];
						$redirectcode=301;
					}
					else{
						$url=$responseheader['redirect_url'];
						$redirectcode=302;
					}
					if($istest){
						Util::echoYellow("Redirect[".$redirectcode."]: ".$url."\n");
					}

					//检查重定向地址是否有效
					if(!self::checkHref($url)){
						$response['code'] = 700;
						throw new Exception("redirect url is marked to not trace.");
					}

					//判断地址是否需要处理
					if(!$istest){
						$level=TaskManager::isHandled($url,$level);
					}
					if($level==-1){
						$response['code'] = 701;
						throw new Exception("redirect url has been or is being handled.");
					}
					if($level==-2){
						$response['code'] = 702;
						throw new Exception("redirect url has been marked to be not update.");
					}
					if($level==-3){
						$response['code'] = 703;
						throw new Exception("redirect url has been marked to be error url.");
					}

					//如果是302需要以重定向地址重新获取，301不需要
					if($redirectcode==301) {
						break;
					}
					curl_setopt($ch, CURLOPT_URL, $url);
				}
				else {
					break;
				}
			}

			//判断是否重定向超过次数限制
			if(intval($response['code']/100)==3 || $response['url']!=$url){
				$response['code']=302;
				throw new Exception("too mach redirect.\n");
			}

			//下载时间
			$response['timeinfo']['download']=round(microtime(true)-$now,3); 
			$now=microtime(true);

			//判断是否访问成功
			if(intval($response['code'])/100==2) {
				//判断文档类型是否为text/html
				$contentType = strtr(strtoupper($responseheader['content_type']), array(' '=>'','\t'=>'','@'=>''));
				if(strpos($contentType,'TEXT/HTML')===false){
					$response['code'] = 800;
					throw new Exception("doctype is not html.");
				}

				//判断是否空内容
				if(strlen($htmltext)==0){
					$response['code'] = 600;
					throw new Exception("empty html");
				}

				$now=microtime(true);
				//使用ContentType获取字符编码，若未检出，则使用编码检测函数检测方式获取
				$charset ='';
				$validcharsets=array("UTF-8","GB2312","GBK","ISO-8859-1");
				foreach (explode(";",$contentType) as $ct) {
					$ctkv=explode("=",$ct);
					if(count($ctkv)==2 && $ctkv[0]=='CHARSET'){
						$charset=$ctkv[1];
						break;
					}
				}
				if(!in_array($charset,$validcharsets)){
					$charset = mb_detect_encoding($htmltext,$validcharsets);
				}
				//如果未检测出字符编码则返回错误，否则字符集转换为UTF-8
				if($charset ==''){
					$response['code'] = 600;
		    		throw new Exception("unknown charset.");
				}
				elseif ($charset != "UTF-8"){
					$htmltext = mb_convert_encoding($htmltext,'UTF-8',$charset);
					$response['html']=mb_convert_encoding($htmltext,'UTF-8',$charset);
				}
				else{
					$response['html']=$htmltext;
				}
				$response['charset']= $charset;

				//网页文件大小检测，避免内存溢出以及存入es过慢
				if(strlen($htmltext)>$GLOBALS['MAX_HTMLSISE']){
					$response['code'] = 600;
		    		throw new Exception("html is too long. (doc size=".strlen($htmltext).", max size=".$GLOBALS['MAX_HTMLSISE'].")");
				}

				//使用phpQuery解析HTML
				$htmltext = preg_replace(["'<script[^>]*?>.*?</script>'si","'<style[^>]*?>.*?</style>'si"]," ", $htmltext); //phpQuery不会过滤js与css，需要手动去除
				$htmltext = str_ireplace("&amp;","&",$htmltext);

				$htmldom= phpQuery::newDocument($htmltext);

				//获取标题
				$title=$htmldom['title'];
				if($title==null){
					$response['code'] = 600;
					throw new Exception("site has no title.");
				}
				$response['title']=trim($title->text());

				//获取html中纯文本内容
				$body=$htmldom['body'];
				if($body==null){
					$response['code'] = 600;
					throw new Exception("site has no body.");
				}
				$text=self::textFilter($body->text());
				$response['text']=empty($text)?$response['title']:$text;

				unset($body);
				unset($text);

				//提取文本耗时
				$response['timeinfo']['extarct']=round(microtime(true)-$now,3);
				$now=microtime(true);

				//解析网页中的超链接
				$response['links']=array();
				if($level>0 || $istest){
					$baseurl=self::urlSplit($response['url']);
					$response['links']=array();
					foreach ($htmldom['a'] as $a) {
						$href=$a-> getAttribute('href');
				    	$link=self::transformHref($href, $baseurl);
				    	if($href!=false){
				    		if($istest){
				    			if(!isset($response['links'][$href])){
				    				$response['links'][$href]=$link;
				    			}
							}
							else{
								if(!in_array($link,$response['links'])){
				    				$response['links'][]=$link;
				    			}
							}
				    	}
				    }
			    }
			    //解析超连接耗时
				$response['timeinfo']['findlinks']=round(microtime(true)-$now,3);
				unset($htmldom);
			}
			else{
				throw new Exception();
			}
		}
		catch(Exception $e) {
			if($response['code']<600){
				$response['error']="error code=".$response['code'];
			}
			else{
				$response['error']=$e->getMessage();
			}
			echo $e->getMessage();
		}
		curl_close($ch);
		unset($ch);
		unset($htmltext);
		$response['level']=$level;
	    return $response;
	}

	//过滤text内容
	static function textFilter($htmltext){

		//去除多余的html标签
		$htmltext=strip_tags($htmltext);

		//转换html标记
		$filtrule=array(
			"&nbsp;"=>" ",
			"&nbsp"=>" ",
			"&lsquo;"=>"",
			"&rsquo;"=>"",
			"\t"=>" ",
			"\n"=>" "
		);
		$htmltext=strtr($htmltext,$filtrule);

		//合并多个空格
		$htmltext=preg_replace("/[\s]+/is"," ",$htmltext);

		return $htmltext;
	}

	//将url拆分,返回：协议，主机地址，路径，文档名，参数
	static function urlSplit($baseurl){

		//去除url后面的#
		$sharppos=strpos($baseurl,"#");
		if($sharppos!==false){
			$baseurl=substr($baseurl,0,$sharppos);
		}

		//获取协议 $protocol
		if(Util::strStartWith($baseurl,'http://')){
			$info['protocol']='http://';
		}
		else if(Util::strStartWith($baseurl,'https://')){
			$info['protocol']='https://';
		}
		else{
			return false;
		}
		$baseurl=substr($baseurl,strlen($info['protocol']));

		//获取url中的参数
		$argpos=strpos($baseurl,"?");
		if($argpos!==false){
			$info['args']=substr($baseurl,$argpos);
			$baseurl=substr($baseurl,0,$argpos);
		}
		else{
			$info['args']='';
		}
		$info['args']=trim($info['args']);

		//获取文档名
		$filepos=strrpos($baseurl,"/");
		if($filepos!==false){
			$info['file']= substr($baseurl,$filepos+1)==false? "":substr($baseurl,$filepos+1);
			$baseurl=substr($baseurl,0,$filepos+1);
		}
		else{
			$info['file']='';
		}
		$info['file']=trim($info['file']);

		//获取主机名与路径
		$pathpos=strpos($baseurl,"/");
		if($pathpos!==false){
			$info['path']=substr($baseurl,$pathpos);
			$info['host']=substr($baseurl,0,$pathpos);
		}
		else{
			$info['path']='/';
			$info['host']=$baseurl;
		}
		$info['path']=trim($info['path']);
		$info['host']=trim($info['host']);

		return $info;
	}

	//检查href是否可用
	static function checkHref($href){
		//不处理的超链接，全匹配
		if(in_array($href,$GLOBALS['NOTTRACE_MATCH'])||empty($href)) { 
			return false;
		}
		//不处理的超链接，开头
		foreach ($GLOBALS['NOTTRACE_BEGIN'] as $nottrace) {
			if(Util::strStartWith($href,$nottrace)){
				return false;
			}
		}
		//不处理的超链接，包涵
		foreach ($GLOBALS['NOTTRACE_HAS'] as $nottrace) {
			if(strpos($href,$nottrace) !== false){
				return false;
			}
		}
		return true;
	}

	//修正url路径
	private static function transformHref($href, $baseurl){
		//去除url中的空格以及控制字符
		$href=trim($href);
		$href=strtr($href, array('&nbsp;'=>'','&nbsp'=>''));

		//去除href后面的#
		$sharppos=strpos($href,"#");
		if($sharppos!==false){
			$href=substr($href,0,$sharppos);
		}

		//检查href
		if(empty($href) || !self::checkHref($href)){
			return false;
		}

		//以协议开头的直接使用，以'//'开头的继承父链接协议，以'/'开头的使用绝对路径，其他情况使用相对路径
		if(Util::strStartWith($href,'http://') || Util::strStartWith($href,'https://')) {
			$url=self::urlSplit($href);
			if($url==false){
				return false;
			}
			return $url['protocol'].$url['host'].$url['path'].$url['file'].$url['args'];
		}
		elseif(Util::strStartWith($href,'//')) {
			$href=ltrim($href,'//');
			return $baseurl['protocol'].$href;
		}
		elseif(Util::strStartWith($href,'/')) {
			return $baseurl['protocol'].$baseurl['host'].$href;
		}
		else{
			if(Util::strStartWith($href,'./')) {
				$href=ltrim($href,'./');
			}
			return $baseurl['protocol'].$baseurl['host'].$baseurl['path'].$href;
		}
	}
}
