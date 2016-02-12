<?php
include_once(dirname(dirname(__FILE__)).'/Config.php');
include_once('Util.class.php');
include_once('TaskManager.class.php');
include_once('SimpleHtmlDom.php');

class UrlAnalyzer{

	//获取url的信息。
	static function getInfo($url){
		
		//如果失败，尝试三次
		for($count=0; $count<3; $count++){
			$response=self::getResponse($url);

			if(intval($response['code']/100)==3){
				Util::echoRed("Get urlinfo failed, too mach redirect.\n");
				break;
			}
			if(intval($response['code']/100)==6){
				Util::echoRed("Get urlinfo failed, redirect url had been handled.\n");
				break;
			}
			if($response['html']!=false){
				break;
			}
			Util::echoRed("Get urlinfo failed, Code=".$response['code']."\n");
		}
		return $response;
	}

	//获取url的信息：trueurl重定向后的地址，code状态码，html网页内容，charset原始字符编码
	private static function getResponse($url,$referer=null){
		$response = array();
		try{
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
		 	//设置连接超时时间
		 	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 5000);
		 	//设置超时时间
		 	curl_setopt($ch, CURLOPT_TIMEOUT_MS, 5000);
		 	//1将结果返回，0直接stdout
		    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		    //支持gzip
		    curl_setopt($ch, CURLOPT_ENCODING, "gzip");
		    //支持30x重定向
		    //curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		    //最大重定向次数
		    //curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

		    //处理header
			$header = array();
			$header[] = "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8"; 
			$header[] = "Accept-Encoding: gzip";
			$header[] = "Accept-Language: zh,zh-CN;q=0.8"; 
			$header[] = "Cache-Control: max-age=0"; 
			$header[] = "Connection: keep-alive"; 
			$header[] = "Keep-Alive: 300";
			$header[] = "Accept-Charset: utf-8,ISO-8859-1;q=0.7,*;q=0.7"; 
			$header[] = "User-Agent:Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/49.0.2623.13 Safari/537.36";
		    if($referer){
		    	$header[] = 'Referer: '.$referer;
		    }
			curl_setopt($ch, CURLOPT_HTTPHEADER,$header);

			//判断重定向
			for($loops = 0;$loops<5;$loops++) {
				$htmltext = curl_exec($ch);
				$responseheader = curl_getinfo($ch);
				$response['url'] = $responseheader['url'];
				$response['code'] = $responseheader['http_code'];

				//没有重定向则退出循环
				if(intval($response['code']/100)!=3){
					break;
				}
				else{
					$redirect_url=$responseheader['redirect_url'];
					echo "Redirect: ".$redirect_url."\n";
					//如果重定向的地址是否可以处理
					if(TaskManager::isCanBeHandled($redirect_url)){
						$response['html'] = false;
						$response['code'] = 600;
						return $response;
					}
					curl_setopt($ch, CURLOPT_URL, $redirect_url);
				}
			}

			//判断是否访问成功,并且是文档类型是text/html
			$contentType = strtr(strtoupper($responseheader['content_type']), array(' '=>'','\t'=>'','@'=>''));
			if(intval($response['code'])/100==2 && strpos($contentType,'TEXT/HTML')!==false) {
				//获取字符编码并转换 
				$charset ='';
				foreach (explode(";",$contentType) as $ct) {
					$ctkv=explode("=",$ct);
					if(count($ctkv)==2 && $ctkv[0]=='CHARSET'){
						$charset=$ctkv[1];
						break;
					}
				}
				if($charset ==''){
					$charset = mb_detect_encoding($htmltext, array('ASCII','UTF-8','GB2312','GBK'));
				}
				if($charset ==''){
					$charset = "UTF-8";
				}
				if ($charset != "UTF-8"){
					$htmltext = mb_convert_encoding($htmltext, 'UTF-8', $charset);
				}
				//网页标题
				$htmldom = new simple_html_dom();
				$htmldom->load($htmltext);

				$response['title']=trim($htmldom->find('title',0)->innertext);
				$response['charset']= $charset;
				$response['html'] = $htmltext;
				
				//解析网页中的超链接
				$links=array();
				foreach ($htmldom->find('a') as $a) {
			    	$href=self::transformHref(trim($a->href),$response['url']);
			    	if($href!=false){
			    		if(!in_array($href,$links))
			    			$links[]=$href;
			    	}
			    }
			    $response['links']=$links;
				$htmldom->clear();
				unset($htmldom);
			}
			else{
				$response['html'] = false;
			}
		}
		catch(Exception $e) {
		    $response['code'] = 0;
		    $response['html'] = false;
		}
		curl_close($ch);
		unset($ch);
	    return $response;
	}

	//修正url路径
	private static function transformHref($href,$baseurl){

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
		foreach ($GLOBALS['NOTTRACE_WITH'] as $nottrace) {
			if(strpos($href,$nottrace) !== false)
				return false;
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