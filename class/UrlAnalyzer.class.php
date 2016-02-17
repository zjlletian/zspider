<?php
include_once(dirname(dirname(__FILE__)).'/Config.php');
include_once('Util.class.php');
include_once('TaskManager.class.php');
include_once('SimpleHtmlDom.php');

class UrlAnalyzer{

	//对url尝试3次获取信息
	static function getInfo($url,$level){
		for($count=1; $count<=3; $count++){
			$response=self::getResponse($url,$level);
			if(!isset($response['error']) || $response['code']==600){
				break;
			}
		}
		return isset($response['error'])? null:$response ;
	}

	//获取url的信息：url重定向后的地址，code状态码，html网页快照内容，text纯文本内容，charset原始字符编码
	private static function getResponse($url,$level,$referer=null){

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

		$response = array();
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
						$redirect_url=$response['url'];
						Util::echoYellow("Redirect[301]: ".$redirect_url."\n");
					}
					else{
						$redirect_url=$responseheader['redirect_url'];
						Util::echoYellow("Redirect[302]: ".$redirect_url."\n");
					}
					$url=$redirect_url;

					//判断地址是否需要处理
					$level2=TaskManager::isHandled($redirect_url,$level);
					if($level2==-1){
						$response['code'] = 600;
						throw new Exception("Get urlinfo cancle, redirect url has been or is being handled.\n");
					}
					if($level2!=$level){
						Util::echoYellow("level ".$level."up to ".$level2."\n");
						$level=$level2;
					}
					//检查重定向地址是否有效
					if(!self::checkHref($redirect_url)){
						$response['code'] = 600;
						throw new Exception("Get urlinfo cancle, redirect url was marked to not trace.\n");
					}
					curl_setopt($ch, CURLOPT_URL, $redirect_url);
				}
				else{
					break;
				}
			}

			//判断是否重定向超过次数限制
			if(intval($response['code']/100)==3 || $response['url']!=$url){
				$response['code']=600;
				throw new Exception("Get urlinfo cancle, too mach redirect.\n");
			}

			//判断是否访问成功
			if(intval($response['code'])/100==2) {

				//判断文档类型是否为text/html
				$contentType = strtr(strtoupper($responseheader['content_type']), array(' '=>'','\t'=>'','@'=>''));
				if(strpos($contentType,'TEXT/HTML')===false){
					$response['code'] = 600;
					throw new Exception("Get urlinfo cancle, doctype is not html.\n");
				}

				//使用content_type获取字符编码，若未检出，则使用编码检测函数检测方式获取
				$charset ='';
				foreach (explode(";",$contentType) as $ct) {
					$ctkv=explode("=",$ct);
					if(count($ctkv)==2 && $ctkv[0]=='CHARSET'){
						$charset=$ctkv[1];
						break;
					}
				}
				if($charset ==''){
					$charset = mb_detect_encoding($htmltext, array('UTF-8','GBK','GB2312'));
				}
				//如果未检测出字符编码则返回错误，否则字符集转换为UTF-8
				if($charset ==''){
					$response['code'] = 600;
		    		throw new Exception("Get urlinfo cancle, unknown charset.\n");
				}
				elseif ($charset != "UTF-8"){
					$htmltext = mb_convert_encoding($htmltext, 'UTF-8', $charset);
				}
				$response['charset']= $charset;

				//网页文件大小检测，避免内存溢出以及存入es过慢
				if(strlen($htmltext)>$GLOBALS['MAX_HTMLSISE']){
					$response['code'] = 600;
		    		throw new Exception("Get urlinfo cancle, html is too long. (doc size=".strlen($htmltext).", max size=".$GLOBALS['MAX_HTMLSISE'].")\n");
				}

				//开始html解析
				$htmldom= new simple_html_dom();
				$htmldom->load($htmltext);

				//获取标题
				$response['title']=trim($htmldom->find('title',0)->innertext);
				if(empty($response['title'])){
					$response['code'] = 600;
					throw new Exception("Get urlinfo cancle, site has no title.\n");
				}

				//获取html纯文本内容
				$body=$htmldom->find('body',0)->innertext;
				if(empty($body)){
					$response['code'] = 600;
					throw new Exception("Get urlinfo cancle, site has no body.\n");
				}
				$text=self::htmlFilter($body);
				$response['text']=empty($text)?$response['title']:$text;
				unset($body);
				unset($text);

				//网页快照
				$response['html']=$htmltext;

				//解析网页中的超链接
				$response['links']=array();
				foreach ($htmldom->find('a') as $a) {
			    	$href=self::transformHref(trim($a->href),$response['url']);
			    	if($href!=false){
			    		if(!in_array($href,$response['links']))
			    			$response['links'][]=$href;
			    	}
			    }
				$htmldom->clear();
				unset($htmldom);
				Util::echoGreen("Get urlinfo succeed. \n");
			}
			else{
				throw new Exception();
			}
		}
		catch(Exception $e) {
			if($response['code']!=600)
				Util::echoRed("Get urlinfo failed, Code=".$response['code']."\n");
			else
				Util::echoYellow($e->getMessage());
			$response['error']=true;
		}
		curl_close($ch);
		unset($ch);
		unset($htmltext);
		$response['level']=$level;
	    return $response;
	}

	//过滤html内容
	static function htmlFilter($htmltext){
		$search = array (
			"'<script[^>]*?>.*?</script>'si",
			"'<style[^>]*?>.*?</style>'si", 
			"'<a[^>]*?>.*?</a>'si",
			"'<img[^>]*?>.*?</img>'si",
			"'<input[^>]*?>.*?</input>'si",
			"'<!--[/!]*?[^<>]*?>'si",
			"'([rn])[s]+'",
			"'&(quot|#34);'i",
			"'&(amp|#38);'i", 
			"'&(lt|#60);'i", 
			"'&(gt|#62);'i", 
			"'&(nbsp|#160);'i", 
			"'&(iexcl|#161);'i", 
			"'&(cent|#162);'i", 
			"'&(pound|#163);'i", 
			"'&(copy|#169);'i", 
			"'&#(d+);'e"
		);
		//去除标签以及innertext
		$htmltext = preg_replace($search,"   ", $htmltext);
		//去除'<></>'标签
		return strip_tags($htmltext);
	}

	//检查href是否可用
	static function checkHref($href){
		//不处理的超链接，全匹配
		if(in_array($href,$GLOBALS['NOTTRACE_MATCH'])||empty($href)) { 
			return false;
		}
		//不处理的超链接，开头
		foreach ($GLOBALS['NOTTRACE_BEGIN'] as $nottrace) {
			if(Util::strStartWith($href,$nottrace))
				return false;
		}
		//不处理的超链接，包涵
		foreach ($GLOBALS['NOTTRACE_HAS'] as $nottrace) {
			if(strpos($href,$nottrace) !== false)
				return false;
		}
		return true;
	}

	//修正url路径
	private static function transformHref($href,$baseurl){

		//检查href
		if(!self::checkHref($href)){
			return false;
		}

		//去除href后面的#
		$hrefsharppos=strpos($href,"#");
		if($hrefsharppos!==false){
			$href=substr($href,0,$hrefsharppos);
		}

		//以协议开头的,直接使用
		if(Util::strStartWith($href,'http://')||Util::strStartWith($href,'https://')) { 
			return $href;
		}
		//继承baseurl的协议
		elseif(Util::strStartWith($href,'//')) { 
			if(Util::strStartWith($baseurl,'http://')){
				return 'http:'.$href;
			}
			elseif(Util::strStartWith($baseurl,'https://')){
				return 'https:'.$href;
			}
		}
		//继承baseurl的路径
		else{ 
			//获取协议
			if(Util::strStartWith($baseurl,'http:')){
				$protocol='http://';
			}
			elseif(Util::strStartWith($baseurl,'https:')){
				$protocol='https://';
			}
			$baseurl=ltrim($baseurl,$protocol);
			//去除url后面的参数
			$argpos=strpos($baseurl,"?");
			if($argpos!==false){
				$baseurl=substr($baseurl,0,$argpos);
			}
			//去除url后面的#
			$sharppos=strpos($baseurl,"#");
			if($sharppos!==false){
				$baseurl=substr($baseurl,0,$sharppos);
			}
			//继承baseurl地址的绝对路径或相对路径
			if(Util::strStartWith($href,'/')) {
				$hostpos=strpos($baseurl,"/");
				$href=ltrim($href,'/');
			}
			else{
				$hostpos=strrpos($baseurl,"/");
			}
			if($hostpos!=false){
				$baseurl=substr($baseurl,0,$hostpos+1);
			}
			if(Util::strStartWith($href,'./')){
				$href=ltrim($href,'./');
			}
			return $protocol.$baseurl.$href;
		}
		return false;
	}
}