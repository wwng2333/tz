<?php
use Workerman\Worker;
require_once __DIR__ . '/vendor/autoload.php';

Worker::$stdoutFile = 'tz.log';
$http_worker = new Worker("http://0.0.0.0:80");
$http_worker->count = 5;

function writeover($filename, $data, $method = 'w', $chmod = 0) {
	$handle = fopen($filename, $method);
	!handle && die("文件打开失败");
	flock($handle, LOCK_EX);
	fwrite($handle, $data);
	flock($handle, LOCK_UN);
	fclose($handle);
	$chmod && @chmod($filename, 0777);
}

function count_online_num($time, $ip) {
	if($ip != '') {
		$fileCount = 'online.json';
		$gap = 60; //一分钟
		if (!file_exists($fileCount)) {
			$arr[$ip] = $time;
			writeover($fileCount, json_encode($arr), 'w', 1);
			return 1;
		} else {
			$json = file_get_contents($fileCount);
			$arr = json_decode($json,true);
			$arr[$ip] = $time;
			foreach($arr as $a_ip => $a_time) {
				if($time - $a_time > $gap) unset($arr[$a_ip]);
			}
			writeover($fileCount, json_encode($arr), 'w', 1);
		}
		return count($arr);
	}
}

function Check_Third_Pard($name) {
	if(get_extension_funcs($name) == false) {
		return '<font color="red">×</font>';
	} else {
		return '<font color="green">√</font>';
	}
}

function cpuinfo() {
	if(!is_readable('/proc/cpuinfo')) return false;
	$cpuinfo = file_get_contents('/proc/cpuinfo');
	preg_match_all('/model\s+name\s*\:\s*(.*)/i', $cpuinfo, $model); //型号
	preg_match_all('/cpu\s+MHz\s*\:\s*(.*)/i', $cpuinfo, $mhz);
	preg_match_all('/cache\s+size\s*\:\s*(.*)/i', $cpuinfo, $cache);
	preg_match_all('/bogomips\s*\:\s*(.*)/i', $cpuinfo, $bogomips);
	if(empty($model[1])) return false;
	$res['cpu_num'] = count($model[1]);
	$models = array();
	foreach(array_count_values($model[1]) as $model_k=>$model_v){
		$models[] = array('model'=>$model_k,'total'=>$model_v,'key'=>array_search($model_k,$model[1]));
	}
	$res['cpu_model'] = $models;
	$res['cpu_mhz'] = $mhz[1];
	$res['cpu_cache'] = $cache[1];
	$res['cpu_bogomips'] = $bogomips[1];
	return $res;
}

function uptime() {
	if(!is_readable('/proc/uptime')) return false;
	return trim(current(explode(' ', file_get_contents('/proc/uptime'))));
}

function meminfo() {
	if(!is_readable('/proc/meminfo')) return false;
	$meminfo = file_get_contents('/proc/meminfo');
	$res['MemTotal'] = preg_match('/MemTotal\s*\:\s*(\d+)/i', $meminfo, $MemTotal) ? (int)$MemTotal[1] : 0;
	$res['MemFree'] = preg_match('/MemFree\s*\:\s*(\d+)/i', $meminfo, $MemFree) ? (int)$MemFree[1] : 0;
	$res['Cached'] = preg_match('/Cached\s*\:\s*(\d+)/i', $meminfo, $Cached) ? (int)$Cached[1] : 0;
	$res['Buffers'] = preg_match('/Buffers\s*\:\s*(\d+)/i', $meminfo, $Buffers) ? (int)$Buffers[1] : 0;
	$res['SwapTotal'] = preg_match('/SwapTotal\s*\:\s*(\d+)/i', $meminfo, $SwapTotal) ? (int)$SwapTotal[1] : 0;
	$res['SwapFree'] = preg_match('/SwapFree\s*\:\s*(\d+)/i', $meminfo, $SwapFree) ? (int)$SwapFree[1] : 0;
	return $res;
}

function loadavg() {
	if(!is_readable('/proc/loadavg')) return false;
	$loadavg = explode(' ', file_get_contents('/proc/loadavg'));
	return implode(' ', current(array_chunk($loadavg, 4)));
}

function _get_loaded_extensions() {
	$array = get_loaded_extensions();
	$count = count($array);
	$return = '';
	for($i=0;$i<$count;$i++) {
		if ($i != 0 && $i % 13 == 0) {
			$return .= $array[$i].'<br/>';
		} else {
			$return .= $array[$i].'&nbsp;&nbsp;';
		}
	}
	return $return;
}

function integer_test() {
	$timeStart = microtime(true);
	for($i = 0; $i < 3000000; $i++) {
		$t = 1+1;
	}
	return microtime(true) - $timeStart;
}

function float_test() {
	$t = pi();
	$timeStart = microtime(true);
	for($i = 0; $i < 3000000; $i++) {
		sqrt($t);
	}
	return microtime(true) - $timeStart;
}

function io_test() {
	$fp = fopen(__FILE__, 'r');
	$timeStart = microtime(true);
	for($i = 0; $i < 10000; $i++) {
		fread($fp, 10240);
		rewind($fp);
	}
	$timeEnd = microtime(true);
	fclose($fp);
	return $timeEnd - $timeStart;
}

function formatsize($size,$key = 0) {
	if($size < 0) {
		return '0B';
	} else {
		$danwei = array('B','K','M','G','T','P');
		while($size > 1024) {
			$size = $size / 1024;
			$key++;
		}
		$return = round($size, 3).' '.$danwei[$key];
		return $return;
	}
}

function show($varName) {
	switch($result = get_cfg_var($varName))	{
		case 0:
			return '<font color="red">×</font>';
		break;
		case 1:
			return '<font color="green">√</font>';
		break;
		default:
			return $result;
		break;
	}
}

function isfun($funName = '') {
    if (!$funName || trim($funName) == '' || preg_match('~[^a-z0-9\_]+~i', $funName, $tmp)) return '错误';
	return (false !== function_exists($funName)) ? '<font color="green">√</font>' : '<font color="red">×</font>';
}

function rt($client_ip) {
	$meminfo = meminfo();
	$dt = round(@disk_total_space(".")/(1024*1024*1024),3); //总
	$df = round(@disk_free_space(".")/(1024*1024*1024),3); //可用
	
	$strs = @file("/proc/net/dev"); 
	for($i=2; $i < count($strs); $i++ ) {
		preg_match_all( "/([^\s]+):[\s]{0,}(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/", $strs[$i], $info );
		$NetOutSpeed[$i] = $info[10][0];
		$NetInputSpeed[$i] = $info[2][0];
		$NetInput[$i] = formatsize($info[2][0]);
		$NetOut[$i]  = formatsize($info[10][0]);
	}
	$return = array();
	$return['useSpace'] = $dt-$df;
	$return['freeSpace'] = $df;
	$return['hdPercent'] = (floatval($dt)!=0) ? round($return['useSpace'] / $dt*100,2) : 0;
	$return['barhdPercent'] = $return['hdPercent'].'%';	
	$return['TotalMemory'] = formatsize($meminfo['MemTotal'], 1);
	$return['UsedMemory'] = formatsize($meminfo['MemTotal'] - $meminfo['MemFree'], 1);
	$return['FreeMemory'] = formatsize($meminfo['MemFree'], 1);
	$return['CachedMemory'] = formatsize($meminfo['Cached'], 1);
	$return['Buffers'] = formatsize($meminfo['Buffers'], 1);
	$return['TotalSwap'] = formatsize($meminfo['SwapTotal'], 1);
	$return['swapUsed'] = formatsize($meminfo['SwapTotal'] - $meminfo['SwapFree'], 1);
	$return['swapFree'] = formatsize($meminfo['SwapFree'], 1);
	$return['loadAvg'] = loadavg();
	$uptime = uptime();
	$day = floor($uptime / 86400).'天';
	$hour = floor(($uptime % 86400) / 3600).'小时';
	$min = floor(($uptime % 3600) / 60).'分钟';
	$sec = floor($uptime % 60).'秒';
	$return['uptime'] = $day.$hour.$min.$sec;
	$return['freetime'] = '';
	$return['bjtime'] = '';
	$return['stime'] = date('Y-m-d H:i:s');
	$return['memRealUsed'] = formatsize($meminfo['MemTotal'] - $meminfo['MemFree'] - $meminfo['Cached'] - $meminfo['Buffers'], 1);
	$return['memRealFree'] = formatsize($meminfo['MemFree'] + $meminfo['Cached'] + $meminfo['Buffers'], 1);
	$return['memRealPercent'] = round(($meminfo['MemTotal'] - $meminfo['MemFree'] - $meminfo['Cached'] - $meminfo['Buffers']) / $meminfo['MemTotal'] * 100, 2);
	$return['memPercent'] = round(($meminfo['MemTotal'] - $meminfo['MemFree']) / $meminfo['MemTotal'] * 100, 2);
	$return['barmemPercent'] = $return['memPercent'].'%';
	$return['barmemRealPercent'] = $return['memRealPercent'].'%';
	$return['memCachedPercent'] = round($meminfo['Cached'] / $meminfo['MemTotal'] * 100, 2);
	$return['barmemCachedPercent'] = $return['memCachedPercent'].'%';
	$return['swapPercent'] = round(($meminfo['SwapTotal'] - $meminfo['SwapFree']) / $meminfo['SwapTotal'] * 100, 2);
	$return['barswapPercent'] = $return['swapPercent'].'%';
	$return['corestat'] = corestat();
	for($x=2;$x<=count($strs);$x++) {
		if(isset($NetOut[$x])) {
			$return['NetOut'.$x] = $NetOut[$x];
			$return['NetInput'.$x] = $NetInput[$x];
			$return['NetOutSpeed'.$x] = $NetOutSpeed[$x];
			$return['NetInputSpeed'.$x] = $NetInputSpeed[$x];
		}
	}
	$return['online_num'] = count_online_num(time(), $client_ip);
	return $return;
}

function GetCoreInformation() {
	$data = file('/proc/stat');
	$cores = array();
	foreach($data as $line) {
		if(preg_match('/^cpu[0-9]/', $line)) {
			$info = explode(' ', $line);
			$cores[] = array('user'=>$info[1],'nice'=>$info[2],'sys' => $info[3],'idle'=>$info[4],'iowait'=>$info[5],'irq' => $info[6],'softirq' => $info[7]);
		}
	}
	return $cores;
}

function GetCpuPercentages($stat1, $stat2) {
	if(count($stat1)!==count($stat2)) return;
	$cpus = array();
	for( $i = 0, $l = count($stat1); $i < $l; $i++) {
		$dif = array();
		$dif['user'] = $stat2[$i]['user'] - $stat1[$i]['user'];
		$dif['nice'] = $stat2[$i]['nice'] - $stat1[$i]['nice'];
		$dif['sys'] = $stat2[$i]['sys'] - $stat1[$i]['sys'];
		$dif['idle'] = $stat2[$i]['idle'] - $stat1[$i]['idle'];
		$dif['iowait'] = $stat2[$i]['iowait'] - $stat1[$i]['iowait'];
		$dif['irq'] = $stat2[$i]['irq'] - $stat1[$i]['irq'];
		$dif['softirq'] = $stat2[$i]['softirq'] - $stat1[$i]['softirq'];
		$total = array_sum($dif);
		$cpu = array();
		foreach($dif as $x=>$y) $cpu[$x] = round($y / $total * 100, 2);
		$cpus['cpu'.$i] = $cpu;
	}
	return $cpus;
}

function corestat() {
	$stat1 = GetCoreInformation();
	sleep(1);
	$stat2 = GetCoreInformation();
	$data = GetCpuPercentages($stat1, $stat2);
	return $data['cpu0']['user']."%us,  ".$data['cpu0']['sys']."%sy,  ".$data['cpu0']['nice']."%ni, ".$data['cpu0']['idle']."%id,  ".$data['cpu0']['iowait']."%wa,  ".$data['cpu0']['irq']."%irq,  ".$data['cpu0']['softirq']."%softirq";
}

$http_worker->onMessage = function($connection, $data) {
	echo json_encode($data)."\n";
	if(isset($data['get']['act'])) {
		switch($data['get']['act']) {
			case 'integer_test':
				$connection->send(integer_test());
			break;
			case 'float_test':
				$connection->send(float_test());
			break;
			case 'io_test':
				$connection->send(io_test());
			break;
			default:
		}
	}

	if(isset($data['get']['act']) and $data['get']['act'] == 'rt') {
		$json = htmlspecialchars($data['get']['callback']).'('.json_encode(rt($data['server']['REMOTE_ADDR'])).')';
		$connection->send($json);
	} elseif(strstr($data['server']['REQUEST_URI'], 'phpinfo')) {
		$connection->send('<pre>'.`php -i`.'</pre>');
	} elseif(strstr($data['server']['REQUEST_URI'], 'functions')) {
		$cmd = 'php -r "print_r(get_defined_functions());"';
		exec($cmd, $result);
		$result = implode("\n", $result);
		$functions = '<pre>'.$result.'</pre>';
		$connection->send($functions);
	} else {
		$time_start = microtime(true);
		$os = explode(" ", php_uname());
		$get_loaded_extensions = get_loaded_extensions();
		
		if(in_array('redis', $get_loaded_extensions)) {
			$redis_support = '<font color="green">√</font>';
		} else {
			$redis_support = '<font color="red">×</font>';
		}
		
		if('/'==DIRECTORY_SEPARATOR) {
			$kernel = $os[2];
			$hostname = $os[1];
			} else {
			$kernel = $os[1];
			$hostname = $os[2];
		}
		
		$dt = round(@disk_total_space(".")/(1024*1024*1024),3); //总
		$df = round(@disk_free_space(".")/(1024*1024*1024),3); //可用
		$du = $dt-$df; //已用
		$hdPercent = round ($du / $dt * 100 , 2);
		
		$strs = @file("/proc/net/dev"); 
		$js = '';
		$ajax = '';
		for ($i=2; $i<count($strs);$i++) {
			preg_match_all( "/([^\s]+):[\s]{0,}(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/", $strs[$i], $info );
			$NetOutSpeed[$i] = $info[10][0];
			$NetInputSpeed[$i] = $info[2][0];
			$NetInput[$i] = formatsize($info[2][0]);
			$NetOut[$i]  = formatsize($info[10][0]);
			$ajax .= '$("#NetOut'.$i.'").html(dataJSON.NetOut'.$i.');'."\n";
			$ajax .= '$("#NetInput'.$i.'").html(dataJSON.NetInput'.$i.');'."\n";
			$ajax .= '$("#NetOutSpeed'.$i.'").html(ForDight((dataJSON.NetOutSpeed'.$i.'-OutSpeed'.$i.'),3));	OutSpeed'.$i.'=dataJSON.NetOutSpeed'.$i.';'."\n";
			$ajax .= '$("#NetInputSpeed'.$i.'").html(ForDight((dataJSON.NetInputSpeed'.$i.'-InputSpeed'.$i.'),3));	InputSpeed'.$i.'=dataJSON.NetInputSpeed'.$i.';'."\n";
			$js .= 'var OutSpeed'.$i.'='.$NetOutSpeed[$i].';'."\n";
			$js .= 'var InputSpeed'.$i.'='.$NetInputSpeed[$i].';'."\n";
		}
		$head = "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">
<html xmlns=\"http://www.w3.org/1999/xhtml\">
<head>
<title>雅黑PHP探针[简体版]v0.4.7</title>
<meta http-equiv=\"X-UA-Compatible\" content=\"IE=EmulateIE7\" />
<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />
<!-- Powered by: Yahei.Net -->
<style type=\"text/css\">
*{font-family:Microsoft Yahei,Tahoma,Arial}body{margin:0 auto;background-color:#fafafa;text-align:center;font-size:9pt;font-family:Tahoma,Arial}body,h1{padding:0}h1{margin:0;color:#333;font-size:26px;font-family:Lucida Sans Unicode,Lucida Grande,sans-serif}h1 small{font-weight:700;font-size:11px;font-family:Tahoma}a{color:#666}a,a.black{text-decoration:none}a.black{color:#000}table{clear:both;margin:0 0 10px;padding:0;width:100%;border-collapse:collapse;box-shadow:1px 1px 1px #ccc;border-spacing:0;-ms-filter:\"progid:DXImageTransform.Microsoft.Shadow(Strength=2,Direction=135,Color='#CCCCCC')\"}th{padding:3px 6px;border:1px solid #ccc;background:#dedede;color:#626262;text-align:left;font-weight:700}tr{padding:0;background:#fff}td{padding:3px 6px;border:1px solid #ccc}.w_logo{width:13%;color:#333;FONT-SIZE:15px}.w_logo,.w_top{height:25px;text-align:center}.w_top{width:8.7%}.w_top:hover{background:#dadada}.w_foot{height:25px;background:#dedede;text-align:center}input{padding:2px;border-top:1px solid #666;border-right:1px solid #ccc;border-bottom:1px solid #ccc;border-left:1px solid #666;background:#fff;font-size:9pt}input.btn{padding:0 6px;height:20px;border:1px solid #999;background:#f2f2f2;color:#666;font-weight:700;font-size:9pt;line-height:20px}.bar{border:1px solid #999}.bar,.bar_1{overflow:hidden;margin:2px 0 5px;padding:1px;width:89%;height:5px;background:#fff;font-size:2px}.bar_1{border:1px dotted #999}.barli_red{background:#f60}.barli_blue,.barli_red{margin:0;padding:0;height:5px}.barli_blue{background:#09f}.barli_green{background:#36b52a}.barli_black,.barli_green{margin:0;padding:0;height:5px}.barli_black{background:#333}.barli_1{background:#999}.barli,.barli_1{margin:0;padding:0;height:5px}.barli{background:#36b52a}#page{margin:0 auto;padding:0 auto;width:60pc;text-align:left}#header{position:relative;padding:5px}.w_small{font-family:Courier New}.w_number{color:#f800fe}.sudu{padding:0;background:#5dafd1}.suduk{margin:0;padding:0}.resNo{color:red}.word{word-break:break-all}
</style>

<script language=\"JavaScript\" type=\"text/javascript\" src=\"http://cdn.bootcss.com/jquery/3.1.1/jquery.min.js\"></script>

<script>
function caola_test(server_test) {
	var time1 = 0;
	$.ajax({
		type: 'get',
		dataType: 'text',
		data: {'act': server_test},
		beforeSend: function(){
			time1 = new Date().getTime();
		},
		success: function(data) {
			if(server_test != 'netspeed_test'){
				if(data){
					//$('#'+server_test).text(parseFloat(data).toFixed(6) + ' 秒');
					$('#'+server_test).text(data + ' 秒');
				}
			}else{
				var time2 = new Date().getTime();
				var all_time = (time2-time1)/1000; //耗时毫秒单位转为秒
				var my_mb = ((1000/all_time) * 8) / 102.4;
				var mynetspeed = '下载1000KB数据用时 <font color=\"#cc0000\">'+ all_time.toString() +'</font> 秒，下载速度：<font color=\"#cc0000\">'+ (1000/all_time).toFixed(2).toString() + '</font> kb/s，需测试多次取平均值，超过10M直接看下载速度';
				$('#'+server_test).html(mynetspeed);
				$('#network_speed').css('width', ((my_mb >= 100) ? '100' : my_mb) + '%');
				$('#network_speed span').text((my_mb/10).toFixed(2) + 'M / 10M');
			}
		}
	});
}

$(document).ready(function(){getJSONData();});
".$js."

function getJSONData()
{
	setTimeout(\"getJSONData()\", 1000);
	$.getJSON('?act=rt&callback=?', displayData);
}
function ForDight(Dight,How)
{ 
  if (Dight<0){
  	var Last=0+\"B/s\";
  }else if (Dight<1024){
  	var Last=Math.round(Dight*Math.pow(10,How))/Math.pow(10,How)+\"B/s\";
  }else if (Dight<1048576){
  	Dight=Dight/1024;
  	var Last=Math.round(Dight*Math.pow(10,How))/Math.pow(10,How)+\"K/s\";
  }else{
  	Dight=Dight/1048576;
  	var Last=Math.round(Dight*Math.pow(10,How))/Math.pow(10,How)+\"M/s\";
  }
	return Last;
}


	function displayData(dataJSON)
{
	$(\"#useSpace\").html(dataJSON.useSpace);
	$(\"#freeSpace\").html(dataJSON.freeSpace);
	$(\"#hdPercent\").html(dataJSON.hdPercent);
	$(\"#barhdPercent\").width(dataJSON.barhdPercent);
	$(\"#UsedMemory\").html(dataJSON.UsedMemory);
	$(\"#FreeMemory\").html(dataJSON.FreeMemory);
	$(\"#CachedMemory\").html(dataJSON.CachedMemory);
	$(\"#Buffers\").html(dataJSON.Buffers);
	$(\"#TotalSwap\").html(dataJSON.TotalSwap);
	$(\"#swapUsed\").html(dataJSON.swapUsed);
	$(\"#swapFree\").html(dataJSON.swapFree);
	$(\"#swapPercent\").html(dataJSON.swapPercent);
	$(\"#loadAvg\").html(dataJSON.loadAvg);
	$(\"#uptime\").html(dataJSON.uptime);
	$(\"#freetime\").html(dataJSON.freetime);
	$(\"#stime\").html(dataJSON.stime);
	$(\"#memRealUsed\").html(dataJSON.memRealUsed);
	$(\"#memRealFree\").html(dataJSON.memRealFree);
	$(\"#memRealPercent\").html(dataJSON.memRealPercent);
	$(\"#memPercent\").html(dataJSON.memPercent);
	$(\"#barmemPercent\").width(dataJSON.memPercent);
	$(\"#barmemRealPercent\").width(dataJSON.barmemRealPercent);
	$(\"#memCachedPercent\").html(dataJSON.memCachedPercent);
	$(\"#barmemCachedPercent\").width(dataJSON.barmemCachedPercent);
	$(\"#barswapPercent\").width(dataJSON.barswapPercent);
	$(\"#corestat\").html(dataJSON.corestat);
	$(\"#online_num\").html(dataJSON.online_num);
".$ajax."
}
</script>
</head>
<body>
<a name=\"w_top\"></a>
<div id=\"page\">
	<table>
		<tr>
			<th class=\"w_logo\">雅黑PHP探针</th>
			<th class=\"w_top\"><a href=\"#w_php\">PHP参数</a></th>
			<th class=\"w_top\"><a href=\"#w_module\">组件支持</a></th>
			<th class=\"w_top\"><a href=\"#w_module_other\">第三方组件</a></th>
			<th class=\"w_top\"><a href=\"#w_db\">数据库支持</a></th>
			<th class=\"w_top\"><a href=\"#w_performance\">性能检测</a></th>
			<th class=\"w_top\"><a href=\"https://github.com/wwng2333/tz\">探针下载</a></th>
		</tr>
	</table>";

	$cpuinfo = cpuinfo();
	$meminfo = meminfo();
	$memused = round(($meminfo['MemTotal'] - $meminfo['MemFree']) / $meminfo['MemTotal'] * 100, 2);
	$MemRealUsed = $meminfo['MemTotal'] - $meminfo['MemFree'] - $meminfo['Cached'] - $meminfo['Buffers'];
	$MemRealFree = $meminfo['MemFree'] + $meminfo['Cached'] + $meminfo['Buffers'];
	$MemRealPercent = round($MemRealUsed / $meminfo['MemTotal'] * 100, 2);
	$CachedPercent = round($meminfo['Cached'] / $meminfo['MemTotal'] * 100, 2);
	$SwapUsed = $meminfo['SwapTotal'] - $meminfo['SwapFree'];
	$SwapUsedPercent = round($SwapUsed / $meminfo['SwapTotal'] * 100, 2);
	
	$day = floor(uptime() / 86400).'天';
	$hour = floor((uptime() % 86400) / 3600).'小时';
	$min = floor((uptime() % 3600) / 60).'分钟';
	$sec = floor(uptime() % 60).'秒';
	$uptime = $day.$hour.$min.$sec;
	
	$disFuns = get_cfg_var("disable_functions");
	if(empty($disFuns))	{
		$disFuns = '<font color=red>×</font>';
	} else {
		$disFuns_array =  explode(',',$disFuns);
		for($i=0;$i<count($disFuns_array);$i++) {
			if ($i != 0 && $i % 5 == 0) {
				array_splice($disFuns_array, $i, 0, array('<br/>')); 
			}
		}
		$disFuns = implode('  ', $disFuns_array);
	}
	
	isset($_COOKIE) ? $cookie = '<font color="green">√</font>' : $cookie = '<font color="red">×</font>';
	get_cfg_var("SMTP") ? $smtp_enable = '<font color="green">√</font>' : $smtp_enable = '<font color="red">×</font>';
	get_cfg_var("SMTP") ? $smtp_addr = get_cfg_var("SMTP") : $smtp_addr = '<font color="red">×</font>';

	$network = '';
	if (false !== ($strs = @file("/proc/net/dev"))) {
		$network .= '<table>'."\n";
		$network .= '<tr><th colspan="5">网络使用状况</th></tr>'."\n";
		for ($i = 2; $i < count($strs); $i++ ) {
		preg_match_all( "/([^\s]+):[\s]{0,}(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/", $strs[$i], $info );
			$network .= '<tr>'."\n";
			$network .= '<td width="13%">'.$info[1][0].' : </td>';
			$network .= '<td width="29%">入网: <font color=\'#CC0000\'><span id="NetInput'.$i.'">'.$NetInput[$i].'</span></font></td>'."\n";
			$network .= '<td width="14%">实时: <font color=\'#CC0000\'><span id="NetInputSpeed'.$i.'">0B/s</span></font></td>'."\n";
			$network .= '<td width="29%">出网: <font color=\'#CC0000\'><span id="NetOut'.$i.'">'.$NetOut[$i].'</span></font></td>'."\n";
			$network .= '<td width="14%">实时: <font color=\'#CC0000\'><span id="NetOutSpeed'.$i.'">0B/s</span></font></td>'."\n";
			$network .= '</tr>'."\n";
		}
		$network .= '</table>'."\n";
	}
	
	if(function_exists(gd_info)) {
		$gd_info = @gd_info();
		$gd_info = $gd_info["GD Version"];
	} else {
		$gd_info = '<font color="red">×</font>';
	}
	
	if(extension_loaded('sqlite3')) {
		$sqliteVer = SQLite3::version();
		$sqlite = '<font color=green>√</font>　';
		$sqlite .= "SQLite3　Ver ";
		$sqlite .= $sqliteVer['versionString'];
	} else {
		$sqlite = isfun("sqlite_close");
		if(isfun("sqlite_close") == '<font color="green">√</font>') {
			$sqlite .= "&nbsp; 版本： ".@sqlite_libversion();
		}
	}
	
	if(!isset($data['server']['SERVER_PORT'])) $data['server']['SERVER_PORT'] = 80;

	$test = $head.'
<!--服务器相关参数-->
<table>
  <tr><th colspan="4">服务器参数</th></tr>
  <tr>
	<td>服务器域名/IP地址</td>
	<td colspan="3">'.@get_current_user().' - '.$data['server']['SERVER_NAME'].' ('.gethostbyname($data['server']['SERVER_NAME']).')&nbsp;&nbsp;你的IP地址是：'.$data['server']['REMOTE_ADDR'].'</td>
  </tr>
  <tr>
	<td>服务器标识</td>
	<td colspan="3">'.php_uname().'</td>
  </tr>
  <tr>
	<td>浏览器 UserAgent</td>
	<td colspan="3">'.$data['server']['HTTP_USER_AGENT'].'</td>
  </tr>
  <tr>
	<td width="13%">服务器操作系统</td>
	<td width="37%">'.$os[0].' &nbsp;内核版本：'.$kernel.'</td>
	<td width="13%">服务器解译引擎</td>
	<td width="37%">'.$data['server']['SERVER_SOFTWARE'].'</td>
  </tr>
  <tr>
		<td>服务器语言</td>
		<td>'.$data['server']['HTTP_ACCEPT_LANGUAGE'].'</td>
		<td>服务器端口</td>
		<td>'.$data['server']['SERVER_PORT'].' @ '.$data['server']['SERVER_PROTOCOL'].'</td>
  </tr>
  <tr>
		<td>服务器主机名</td>
		<td>'.str_replace("\n", '', `hostname`).'</td>
		<td>绝对路径</td>
		<td>'.getcwd().'</td>
	</tr>
  <tr>
		<td>当前在线人数</td>
		<td><span id="online_num">1</span></td>
		<td>探针路径</td>
		<td>'.str_replace('\\', '/', __FILE__).'</td>
	</tr>
</table>

<table>
  <tr><th colspan="6">服务器实时数据</th></tr>
  <tr>
	<td width="13%" >服务器当前时间</td>
	<td width="37%" ><span id="stime">'.date('Y-m-d H:i:s').'</span></td>
	<td width="13%" >服务器已运行时间</td>
	<td width="37%" colspan="3"><span id="uptime">'.$uptime.'</span></td>
  </tr>
  <tr>
	<td width="13%">CPU型号 ['.$cpuinfo['cpu_num'].'核]</td>
	<td width="87%" colspan="5">'.$cpuinfo['cpu_model']['0']['model'].' | 频率:'.$cpuinfo['cpu_mhz']['0'].' | 二级缓存:'.$cpuinfo['cpu_cache']['0'].' | Bogomips:'.$cpuinfo['cpu_bogomips']['0'].' × '.$cpuinfo['cpu_num'].'</td>
  </tr>
  <tr>
	<td>CPU使用状况</td>
	<td colspan="5"><span id="corestat">0%us, 0%sy, 0%ni, 100%id, 0%wa, 0%irq, 0%softirq</span></td>
  </tr>
  <tr>
	<td>硬盘使用状况</td>
	<td colspan="5">
		总空间 '.$dt.' G，
		已用 <font color=\'#333333\'><span id="useSpace">'.$du.'</span></font> G，
		空闲 <font color=\'#333333\'><span id="freeSpace">'.$df.'</span></font> G，
		使用率 <span id="hdPercent">'.$hdPercent.'</span> %
		<div class="bar"><div id="barhdPercent" class="barli_black" style="width:'.$hdPercent.'%">&nbsp;</div> </div>
	</td>
  </tr>
  <tr>
		<td>内存使用状况</td>
		<td colspan="5">
		  物理内存：共<font color=\'#CC0000\'> '.formatsize($meminfo['MemTotal'], 1).' </font>
		   , 已用 <font color=\'#CC0000\'><span id="UsedMemory"> '.formatsize($meminfo['MemTotal'] - $meminfo['MemFree'], 1).' </span></font>
		  , 空闲 <font color=\'#CC0000\'><span id="FreeMemory"> '.formatsize($meminfo['MemFree'], 1).' </span></font>
		  , 使用率 <span id="memPercent">'.$memused.'</span> %
		  <div class="bar"><div id="_barmemPercent" class="barli_green" style="width:'.$memused.'%">&nbsp;</div> </div>
		  Cache化内存为 <span id="CachedMemory"> '.formatsize($meminfo['Cached'], 1).' </span>
		  , 使用率 
		  <span id="memCachedPercent">'.$CachedPercent.'</span>
		  %	| Buffers缓冲为  <span id="Buffers"> '.formatsize($meminfo['Buffers'], 1).' </span>
		  <div class="bar"><div id="barmemCachedPercent" class="barli_blue" style="width:'.$CachedPercent.'%">&nbsp;</div></div>
		  真实内存使用
		  <span id="memRealUsed">'.formatsize($MemRealUsed, 1).'</span>
		  , 真实内存空闲
		  <span id="memRealFree">'.formatsize($MemRealFree, 1).'</span>
		  , 使用率
		  <span id="memRealPercent">'.$MemRealPercent.'</span>
		  %
		  <div class="bar_1"><div id="barmemRealPercent" class="barli_1" style="width:'.$MemRealPercent.'%">&nbsp;</div></div> 
		  SWAP区：共 '.formatsize($meminfo['SwapTotal'], 1).' , 已使用
		  <span id="swapUsed">'.formatsize($SwapUsed, 1).'</span>
		  , 空闲
		  <span id="swapFree">'.formatsize($meminfo['SwapFree'], 1).'</span>
		  , 使用率
		  <span id="swapPercent">'.$SwapUsedPercent.'</span>
		  %
		  <div class="bar"><div id="barswapPercent" class="barli_red" style="width:'.$SwapUsedPercent.'%" >&nbsp;</div> </div>
		  </td>
	</tr>
	<tr>
		<td>系统平均负载</td>
		<td colspan="5" class="w_number"><span id="loadAvg">'.loadavg().'</span></td>
	</tr>
</table>

'.$network.'
	
<table width="100%" cellpadding="3" cellspacing="0" align="center">
  <tr>
	<th colspan="4">PHP已编译模块检测</th>
  </tr>
  <tr>
	<td colspan="4"><span class="w_small">'._get_loaded_extensions().'</td>
  </tr>
</table>

<a name="w_php"></a>
<table>
  <tr><th colspan="4">PHP相关参数</th></tr>
  <tr>
	<td width="32%">PHP信息（phpinfo）：</td>
	<td width="18%">
	<a href=\'phpinfo\' target=\'_blank\'>PHPINFO</a>
	</td>
	<td width="32%">PHP版本（php_version）：</td>
	<td width="18%">'.PHP_VERSION.'</td>
  </tr>
  <tr>
	<td>PHP运行方式：</td>
	<td>'.strtoupper(php_sapi_name()).'</td>
	<td>脚本占用最大内存（memory_limit）：</td>
	<td>'.show("memory_limit").'</td>
  </tr>
  <tr>
	<td>PHP安全模式（safe_mode）：</td>
	<td>'.show("safe_mode").'</td>
	<td>POST方法提交最大限制（post_max_size）：</td>
	<td>'.show("post_max_size").'</td>
  </tr>
  <tr>
	<td>上传文件最大限制（upload_max_filesize）：</td>
	<td>'.show("upload_max_filesize").'</td>
	<td>浮点型数据显示的有效位数（precision）：</td>
	<td>'.show("precision").'</td>
  </tr>
  <tr>
	<td>脚本超时时间（max_execution_time）：</td>
	<td>'.show("max_execution_time").'</td>
	<td>socket超时时间（default_socket_timeout）：</td>
	<td>'.show("default_socket_timeout").'</td>
  </tr>
  <tr>
	<td>PHP页面根目录（doc_root）：</td>
	<td>'.show("doc_root").'</td>
	<td>用户根目录（user_dir）：</td>
	<td>'.show("user_dir").'</td>
  </tr>
  <tr>
	<td>dl()函数（enable_dl）：</td>
	<td>'.show("enable_dl").'</td>
	<td>指定包含文件目录（include_path）：</td>
	<td>'.show("include_path").'</td>
  </tr>
  <tr>
	<td>显示错误信息（display_errors）：</td>
	<td>'.show("display_errors").'</td>
	<td>自定义全局变量（register_globals）：</td>
	<td>'.show("register_globals").'</td>
  </tr>
  <tr>
	<td>数据反斜杠转义（magic_quotes_gpc）：</td>
	<td>'.show("magic_quotes_gpc").'</td>
	<td>"&lt;?...?&gt;"短标签（short_open_tag）：</td>
	<td>'.show("short_open_tag").'</td>
	 </tr>
	 <tr>
		<td>"&lt;% %&gt;"ASP风格标记（asp_tags）：</td>
		<td>'.show("asp_tags").'</td>
		<td>忽略重复错误信息（ignore_repeated_errors）：</td>
		<td>'.show("ignore_repeated_errors").'</td>
	  </tr>
	  <tr>
		<td>忽略重复的错误源（ignore_repeated_source）：</td>
		<td>'.show("ignore_repeated_errors").'</td>
		<td>报告内存泄漏（report_memleaks）：</td>
		<td>'.show("report_memleaks").'</td>
	  </tr>
	  <tr>
		<td>自动字符串转义（magic_quotes_gpc）：</td>
		<td>'.show("magic_quotes_gpc").'</td>
		<td>外部字符串自动转义（magic_quotes_runtime）：</td>
		<td>'.show("magic_quotes_runtime").'</td>
	  </tr>
	  <tr>
		<td>打开远程文件（allow_url_fopen）：</td>
		<td>'.show("allow_url_fopen").'</td>
		<td>声明argv和argc变量（register_argc_argv）：</td>
		<td>'.show("register_argc_argv").'</td>
	  </tr>
	  <tr>
		<td>Cookie 支持：</td>
		<td>'.$cookie.'</td>
		<td>拼写检查（ASpell Library）：</td>
		<td>'.isfun("aspell_check_raw").'</td>
	  </tr>
	   <tr>
		<td>高精度数学运算（BCMath）：</td>
		<td>'.isfun("bcadd").'</td>
		<td>PREL相容语法（PCRE）：</td>
		<td>'.isfun("preg_match").'</td>
	   <tr>
		<td>PDF文档支持：</td>
		<td>'.isfun("pdf_close").'</td>
		<td>SNMP网络管理协议：</td>
		<td>'.isfun("snmpget").'</td>
	  </tr> 
	   <tr>
		<td>VMailMgr邮件处理：</td>
		<td>'.isfun("vm_adduser").'</td>
		<td>Curl支持：</td>
		<td>'.isfun("curl_init").'</td>
	  </tr> 
	   <tr>
		<td>SMTP支持：</td>
		<td>'.$smtp_enable.'</td>
		<td>SMTP地址：</td>
		<td>'.$smtp_addr.'</td>
	  </tr> 
		<tr>
			<td>默认支持函数（enable_functions）：</td>
			<td colspan="3"><a href=\'functions\' target=\'_blank\' class=\'static\'>请点这里查看详细！</a></td>		
		</tr>
		<tr>
			<td>被禁用的函数（disable_functions）：</td>
			<td colspan="3" class="word">'.$disFuns.'</td>
		</tr>
</table>
	
<a name="w_module"></a>
<!--组件信息-->
<table>
	  <tr><th colspan="4" >组件支持</th></tr>
	  <tr>
		<td width="32%">FTP支持：</td>
		<td width="18%">'.isfun("ftp_login").'</td>
		<td width="32%">XML解析支持：</td>
		<td width="18%">'.isfun("xml_set_object").'</td>
	  </tr>
	  <tr>
		<td>Session支持：</td>
		<td>'.isfun("session_start").'</td>
		<td>Socket支持：</td>
		<td>'.isfun("socket_accept").'</td>
	  </tr>
	  <tr>
		<td>Calendar支持</td>
		<td>'.isfun('cal_days_in_month').'</td>
		<td>允许URL打开文件：</td>
		<td>'.show("allow_url_fopen").'</td>
	  </tr>
	  <tr>
		<td>GD库支持：</td>
		<td>'.$gd_info.'</td>
		<td>压缩文件支持(Zlib)：</td>
		<td>'.isfun("gzclose").'</td>
	  </tr>
	  <tr>
		<td>IMAP电子邮件系统函数库：</td>
		<td>'.isfun("imap_close").'</td>
		<td>历法运算函数库：</td>
		<td>'.isfun("JDToGregorian").'</td>
	  </tr>
	  <tr>
		<td>正则表达式函数库：</td>
		<td>'.isfun("preg_match").'</td>
		<td>WDDX支持：</td>
		<td>'.isfun("wddx_add_vars").'</td>
	  </tr>
	  <tr>
		<td>Iconv编码转换：</td>
		<td>'.isfun("iconv").'</td>
		<td>mbstring：</td>
		<td>'.isfun("mb_eregi").'</td>
	  </tr>
	  <tr>
		<td>高精度数学运算：</td>
		<td>'.isfun("bcadd").'</td>
		<td>LDAP目录协议：</td>
		<td>'.isfun("ldap_close").'</td>
	  </tr>
	  <tr>
		<td>MCrypt加密处理：</td>
		<td>'.isfun("mcrypt_cbc").'</td>
		<td>哈稀计算：</td>
		<td>'.isfun("mhash_count").'</td>
	  </tr>
</table>
	
<a name="w_module_other"></a>
<table>
	  <tr><th colspan="4" >第三方组件</th></tr>
	  <tr>
		<td width="32%">Zend版本</td>
		<td width="18%">'.zend_version().'</td>
		<td width="32%">Zend OPcache</td>
		<td width="18%">'.Check_Third_Pard('Zend OPcache').'</td>
	  </tr>
	  <tr>
		<td>XCache</td>
		<td>'.Check_Third_Pard('XCache').'</td>
		<td>ioncube</td>
		<td>'.Check_Third_Pard('ionCube Loader').'</td>
	  </tr>
	  <tr>
		<td>Memcache</td>
		<td>'.Check_Third_Pard('memcache').'</td>
		<td>Redis</td>
		<td>'.$redis_support.'</td>
	  </tr>
</table>

<a name="w_db"></a>
<!--数据库支持-->
<table>
	  <tr><th colspan="4">数据库支持</th></tr>
	  <tr>
		<td width="32%">MySQL 数据库：</td>
		<td width="18%">'.isfun("mysqli_close").'</td>
		<td width="32%">ODBC 数据库：</td>
		<td width="18%">'.isfun("odbc_close").'</td>
	  </tr>
	  <tr>
		<td>Oracle 数据库：</td>
		<td>'.isfun("ora_close").'</td>
		<td>SQL Server 数据库：</td>
		<td>'.isfun("mssql_close").'</td>
	  </tr>
	  <tr>
		<td>dBASE 数据库：</td>
		<td>'.isfun("dbase_close").'</td>
		<td>mSQL 数据库：</td>
		<td>'.isfun("msql_close").'</td>
	  </tr>
	  <tr>
		<td>SQLite 数据库：</td>
		<td>'.$sqlite.'</td>
		<td>Postgre SQL 数据库：</td>
		<td>'.isfun("pg_close").'</td>
	  </tr>
</table>

<a name="w_performance"></a>
<table>
	  <tr><th colspan="5">服务器性能检测</th></tr>
	  <tr align="center">
		<td width="19%">参照对象</td>
		<td width="17%">整数运算能力检测<br />(1+1运算300万次)</td>
		<td width="17%">浮点运算能力检测<br />(圆周率开平方300万次)</td>
		<td width="17%">数据I/O能力检测<br />(读取10K文件1万次)</td>
		<td width="30%">CPU信息</td>
	  </tr>
	  <tr align="center">
		<td align="left">美国 LinodeVPS</td>
		<td>0.357秒</td>
		<td>0.802秒</td>
		<td>0.023秒</td>
		<td align="left">4 x Xeon L5520 @ 2.27GHz</td>
	  </tr> 
	  <tr align="center">
		<td align="left">美国 PhotonVPS.com</td>
		<td>0.431秒</td>
		<td>1.024秒</td>
		<td>0.034秒</td>
		<td align="left">8 x Xeon E5520 @ 2.27GHz</td>
	  </tr>
	  <tr align="center">
		<td align="left">德国 SpaceRich.com</td>
		<td>0.421秒</td>
		<td>1.003秒</td>
		<td>0.038秒</td>
		<td align="left">4 x Core i7 920 @ 2.67GHz</td>
	  </tr>
	  <tr align="center">
		<td align="left">美国 RiZie.com</td>
		<td>0.521秒</td>
		<td>1.559秒</td>
		<td>0.054秒</td>
		<td align="left">2 x Pentium4 3.00GHz</td>
	  </tr>
	  <tr align="center">
		<td align="left">埃及 CitynetHost.com</a></td>
		<td>0.343秒</td>
		<td>0.761秒</td>
		<td>0.023秒</td>
		<td align="left">2 x Core2Duo E4600 @ 2.40GHz</td>
	  </tr>
	  <tr align="center">
		<td align="left">美国 IXwebhosting.com</td>
		<td>0.535秒</td>
		<td>1.607秒</td>
		<td>0.058秒</td>
		<td align="left">4 x Xeon E5530 @ 2.40GHz</td>
	  </tr>
	  <tr align="center">
		<td>本台服务器</td>
		<td><span id="integer_test">未测试</span><br><button title="1+1 运算 300 万次" onclick="caola_test(\'integer_test\');">整型测试</button></td>
		<td><span id="float_test">未测试</span><br><button title="圆周率开平方 运算 300 万次" onclick="caola_test(\'float_test\');">浮点测试</button></td>
		<td><span id="io_test">未测试</span><br><button title="读取 10Kb 文件 1 万次" onclick="caola_test(\'io_test\');">IO测试</button></td>
		<td></td>
	  </tr>
</table>

<table>
		<tr>
			<td class="w_foot"><A HREF="http://www.Yahei.Net" target="_blank">雅黑PHP探针[简体版]v0.4.7</A></td>
			<td class="w_foot">Processed in '.(microtime(true) - $time_start).' seconds. '.round(memory_get_usage()/1024/1024, 2).'MB memory usage.</td>
			<td class="w_foot"><a href="#w_top">返回顶部</a></td>
		</tr>
</table>
	
</div>
</body>
</html>
	';
		$connection->send($test);
	}
};

// 运行worker
Worker::runAll();
