<?php
$filename = array('autoload.php','Autoloader.php');
$bin_name = is_readable('/proc/self/exe') ? readlink('/proc/self/exe') : 'php';

for($i=0;$i<count($filename);$i++) {
	$real_filename = __DIR__ .'/vendor/'.$filename[$i];
	if(is_readable($real_filename)) {
		require_once $real_filename;
		break;
	} elseif(isset($filename[$i + 1])) {
		continue;
	} else {
		die("You need to install workerman!\nCheckout https://github.com/walkor/Workerman\n");
	}
}

use Workerman\Worker;

#Worker::$stdoutFile = 'tz.log';
$http_worker = new Worker("http://0.0.0.0:2345");
$http_worker->name = 'Proberv';
$http_worker->user = 'root';
$http_worker->count = 3;

function writeover($filename, $data, $method = 'w', $chmod = 0) {
	$handle = fopen($filename, $method);
	if(!$handle) die("文件打开失败");
	flock($handle, LOCK_EX);
	fwrite($handle, $data);
	flock($handle, LOCK_UN);
	fclose($handle);
	$chmod && @chmod($filename, 0777);
}

function count_online_num($time, $ip) {
	if($ip != '') {
		$fileCount = sys_get_temp_dir().'/online.json';
		$gap = 60; //一分钟
		if (!file_exists($fileCount)) {
			$arr[$ip] = $time;
			writeover($fileCount, json_encode($arr), 'w', 1);
			return 1;
		} else {
			$json = file_get_contents($fileCount);
			$arr = json_decode($json,true);
			$arr[$ip] = $time;
			foreach($arr as $a_ip => $a_time) if($time - $a_time > $gap) unset($arr[$a_ip]);
			writeover($fileCount, json_encode($arr), 'w', 1);
		}
		return count($arr);
	}
}

function Check_Third_Pard($name) {
	return (!get_extension_funcs($name)) ? '<font color="red">×</font>' : '<font color="green">√</font>';
}

function get_key($keyName) {
	exec("sysctl $keyName", $return, $errno);
	$return = str_replace($keyName.': ', '', implode("\n", $return));
	return ($errno > 0) ? false : $return;
}

function remove_spaces($input) {
	while(strstr($input, '  ')) $input = str_replace('  ', ' ', $input);
	return $input;
}

function cpuinfo() {
	global $os;
	$os_real = explode(' ', php_uname());
	$os_count = count($os_real) - 1;
	if(in_array($os_real[$os_count], array('armv6l','armv7l','armv8l','mips','mipsel','aarch64'))) {
		if(is_file('/system/build.prop')) { //Android
			$res['cpu_model']['0']['model'] = cpuinfo_get('cpuname');
			$res['cpu_mhz']['0'] = android_get_cpu_freq();
			$res['cpu_num'] = cpuinfo_get('cpu_num');
			return $res;
		} else { //busybox
			$res['cpu_model']['0']['model'] = cpuinfo_get('cpuname') ? cpuinfo_get('cpuname') : dmesg_get_cpu_name();
			$res['cpu_mhz']['0'] = dmesg_get_cpu_freq_normal();
			if(!$res['cpu_mhz']['0']) $res['cpu_mhz']['0'] = dmesg_get_cpu_freq_normal_mt7621();
			$res['cpu_num'] = cpuinfo_get('cpu_num');
			$res['cpu_bogomips']['0'] = cpuinfo_get('bogomips');
			return $res;
		}
	} else {
		switch($os[0]) {
			case 'FreeBSD':
				$res['cpu_model']['0']['model'] = get_key('hw.model');
				$res['cpu_mhz']['0'] = get_key('machdep.tsc_freq') / 1000000;
				$res['cpu_num'] = get_key('hw.ncpu');
				return $res;
			break;
			default:
				if(!is_readable('/proc/cpuinfo')) return false;
				$cpuinfo = file_get_contents('/proc/cpuinfo');
				preg_match_all('/model\s+name\s*\:\s*(.*)/i', $cpuinfo, $model); //型号
				preg_match_all('/cpu\s+MHz\s*\:\s*(.*)/i', $cpuinfo, $mhz);
				preg_match_all('/cache\s+size\s*\:\s*(.*)/i', $cpuinfo, $cache);
				preg_match_all('/bogomips\s*\:\s*(.*)/i', $cpuinfo, $bogomips);
				if(empty($model[1])) return false;
				$res['cpu_num'] = count($model[1]);
				$models = array();
				foreach(array_count_values($model[1]) as $model_k => $model_v) $models[] = array('model' => $model_k, 'total' => $model_v, 'key' => array_search($model_k,$model[1]));
				$res['cpu_model'] = $models;
				$res['cpu_mhz'] = $mhz[1];
				$res['cpu_cache'] = $cache[1];
				$res['cpu_bogomips'] = $bogomips[1];
				return $res;
			break;
		}
	}
}

function dmesg_get_cpu_freq_normal() {
	$l_clock = array();
	exec('dmesg | grep Clocks', $clocks, $errno);
	if($errno > 0) return false;
	if(is_array($clocks)) $clocks = implode('', $clocks);
	$clocks = str_replace(' ', '', $clocks);
	$tmp = explode('Clocks:', $clocks);
	$tmp = explode(',', $tmp[1]);
	if(!isset($tmp[1])) return false;
	for($i=0;$i<count($tmp);$i++) {
		$l_tmp = explode(':', $tmp[$i]);
		$k = strtolower($l_tmp[0]);
		$v = $l_tmp[1];
		$l_clock[$k] = $v;
	}
	if(isset($l_clock['cpu'])) {
		$freq = (float)$l_clock['cpu'];
		return number_format($freq, 3, '.', '');
	} else {
		return false;
	}
}

function dmesg_get_cpu_freq_normal_mt7621() {
	$l_clock = array();
	exec('dmesg | grep frequency', $frequency, $errno);
	if($errno > 0) return false;
	if(is_array($frequency)) $frequency = implode('', $frequency);
	$tmp = explode(': ', $frequency);
	$tmp = explode('/', $tmp[1]);
	return is_numeric($tmp[0]) ? number_format($tmp[0], 3, '.', '') : false;
}

function dmesg_get_cpu_name() {
	exec('dmesg | grep SoC', $cpuname);
	if(is_array($cpuname)) $cpuname = implode('', $cpuname);
	$tmp = explode(':', $cpuname);
	return (isset($tmp[1])) ? trim($tmp[1]) : false;
}

function android_get_cpu_freq() {
	$file = '/sys/devices/system/cpu/cpu0/cpufreq/cpuinfo_cur_freq';
	return is_readable($file) ? number_format((file_get_contents($file) / 1000), 3, '.', '') : false;
}

function cpuinfo_get($what) {
	global $__n_cpuinfo,$___cached,$cpu_num;
	if(!isset($cpu_num)) $cpu_num = 0;
	$file = '/proc/cpuinfo';
	if(isset($___cached)) {
		goto result_return;
	} else {
		$__l_cpuinfo = explode("\n", rtrim(file_get_contents($file)));
		for($i=0;$i<count($__l_cpuinfo);$i++) {
			$tmp = explode(':', $__l_cpuinfo[$i]);
			$key = trim($tmp[0]);
			if($key == 'processor') $cpu_num++;
			if(strtolower($key) == 'bogomips') $key = 'bogomips';
			if(count($tmp > 2)) {
				unset($tmp[0]);
				$val = trim(implode(':', $tmp));
			} else {
				$val = trim($tmp[1]);
			}
			$__n_cpuinfo[$key] = $val;
		}
		if(isset($__n_cpuinfo['bogomips'])) $___cached['bogomips'] = $__n_cpuinfo['bogomips'];
		if(isset($__n_cpuinfo['Hardware'])) $___cached['cpuname'] = $__n_cpuinfo['Hardware'];
		if(isset($__n_cpuinfo['system type'])) $___cached['cpuname'] = $__n_cpuinfo['system type'];
		goto result_return;
	}
	result_return:
	switch($what) {
		case 'cpu_num':
			return $cpu_num;
		break;
		default:
			return (isset($___cached[$what])) ? $___cached[$what] : false;
		break;
	}
}

function uptime() {
	global $os;
	switch($os[0]) {
		case 'FreeBSD':
			$line = get_key('kern.boottime');
			$line = explode('}', $line);
			$line = explode(',', $line[0]);
			$line = explode('=', $line[1]);
			$line = trim($line[1]);
			return $line;
		break;
		default:
			return (is_readable('/proc/uptime')) ? trim(current(explode(' ', file_get_contents('/proc/uptime')))) : false;
		break;
	}
}

function meminfo() {
	global $os;
	switch($os[0]) {
		case 'FreeBSD':
			$res['MemTotal'] = get_key("hw.physmem") / 1024;
			return $res;
		break;
		default:
			if(!is_readable('/proc/meminfo')) return false;
			$meminfo = file_get_contents('/proc/meminfo');
			$res['MemTotal'] = preg_match('/MemTotal\s*\:\s*(\d+)/i', $meminfo, $MemTotal) ? (int)$MemTotal[1] : 0;
			$res['MemFree'] = preg_match('/MemFree\s*\:\s*(\d+)/i', $meminfo, $MemFree) ? (int)$MemFree[1] : 0;
			$res['Cached'] = preg_match('/Cached\s*\:\s*(\d+)/i', $meminfo, $Cached) ? (int)$Cached[1] : 0;
			$res['Buffers'] = preg_match('/Buffers\s*\:\s*(\d+)/i', $meminfo, $Buffers) ? (int)$Buffers[1] : 0;
			$res['SwapTotal'] = preg_match('/SwapTotal\s*\:\s*(\d+)/i', $meminfo, $SwapTotal) ? (int)$SwapTotal[1] : 0;
			$res['SwapFree'] = preg_match('/SwapFree\s*\:\s*(\d+)/i', $meminfo, $SwapFree) ? (int)$SwapFree[1] : 0;
			return $res;
		break;
	}
}

function loadavg() {
	global $os;
	switch($os[0]) {
		case 'FreeBSD':
			exec('uptime', $result, $errno);
			if($errno > 0) {
				return false;
			} else {
				$result = implode('', $result);
				$temp = explode(': ', $result);
				$loadavg = str_replace(',', '', $temp[1]);
				return $loadavg;
			}
		break;
		default:
			if(!is_readable('/proc/loadavg')) return false;
			$loadavg = explode(' ', file_get_contents('/proc/loadavg'));
			return implode(' ', current(array_chunk($loadavg, 4)));
		break;
	}
}

function _get_loaded_extensions() {
	$array = get_loaded_extensions();
	$count = count($array);
	$return = '';
	for($i=0;$i<$count;$i++) {
		$return .= ($i != 0 && $i % 13 == 0) ? $array[$i].'<br/>' : $array[$i].'&nbsp;&nbsp;';
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

function formatsize_byte($byte) {
	$size = $byte / 8;
	if($size < 0) {
		return '0B';
	} else {
		$danwei = array('B','K','M','G','T','P');
		while($size > 1024) {
			$size = $size / 1024;
			$key++;
		}
		return round($size, 3).' '.$danwei[$key];
	}
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
		return round($size, 3).' '.$danwei[$key];
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

function get_format_level($string) {
	return str_replace((float)$string, '', $string);
}

function rt($client_ip) {
	global $os;
	$meminfo = meminfo();
	
	switch($os[0]) {
		case 'Windows':
			$netstat = windows_netstat_get();
			$return['NetInputSpeed'] = $netstat[1] / 8;
			$return['NetOutSpeed'] = $netstat[2] / 8;
			$return['NetInput'] = formatsize_byte($netstat[1]);
			$return['NetOut'] = formatsize_byte($netstat[2]);
		break;
		default:
			$return = [];
			$return['TotalMemory'] = formatsize($meminfo['MemTotal'], 1);
			$return['UsedMemory'] = formatsize($meminfo['MemTotal'] - $meminfo['MemFree'], 1);
			$return['FreeMemory'] = formatsize($meminfo['MemFree'], 1);
			$return['CachedMemory'] = formatsize($meminfo['Cached'], 1);
			$return['Buffers'] = formatsize($meminfo['Buffers'], 1);
			$return['TotalSwap'] = formatsize($meminfo['SwapTotal'], 1);
			$return['swapUsed'] = formatsize($meminfo['SwapTotal'] - $meminfo['SwapFree'], 1);
			$return['swapFree'] = formatsize($meminfo['SwapFree'], 1);
			$return['loadAvg'] = loadavg();
			$cached_uptime = uptime();
			$day = floor($cached_uptime / 86400).'天';
			$hour = floor(($cached_uptime % 86400) / 3600).'小时';
			$min = floor(($cached_uptime % 3600) / 60).'分钟';
			$sec = floor($cached_uptime % 60).'秒';
			$return['uptime'] = $day.$hour.$min.$sec;
			$return['memRealUsed'] = formatsize($meminfo['MemTotal'] - $meminfo['MemFree'] - $meminfo['Cached'] - $meminfo['Buffers'], 1);
			$return['memRealFree'] = formatsize($meminfo['MemFree'] + $meminfo['Cached'] + $meminfo['Buffers'], 1);
			$return['memRealPercent'] = round(($meminfo['MemTotal'] - $meminfo['MemFree'] - $meminfo['Cached'] - $meminfo['Buffers']) / $meminfo['MemTotal'] * 100, 2);
			$return['memPercent'] = round(($meminfo['MemTotal'] - $meminfo['MemFree']) / $meminfo['MemTotal'] * 100, 2);
			$return['barmemPercent'] = $return['memPercent'].'%';
			$return['barmemRealPercent'] = $return['memRealPercent'].'%';
			$return['memCachedPercent'] = round($meminfo['Cached'] / $meminfo['MemTotal'] * 100, 2);
			$return['barmemCachedPercent'] = $return['memCachedPercent'].'%';
			if($meminfo['SwapTotal'] > 0) {
				$return['swapPercent'] = round(($meminfo['SwapTotal'] - $meminfo['SwapFree']) / $meminfo['SwapTotal'] * 100, 2);
				$return['barswapPercent'] = $return['swapPercent'].'%';
			} else {
				$return['swapPercent'] = false;
			}
			$return['corestat'] = corestat();
			$return['online_num'] = count_online_num(time(), $client_ip);
			
			$net = net();
			foreach($net as $interface => $net_now)
			{
				$return = array_merge($return, net_json_array_generate($interface, $net_now));
			}
	}
	
	$dt = formatsize(@disk_total_space(".")); //总
	$df = formatsize(@disk_free_space(".")); //可用
	$return['useSpace'] = (float)$dt - (float)$df.get_format_level($dt);
	$return['freeSpace'] = (float)$df.get_format_level($df);
	$return['hdPercent'] = (floatval($dt)!=0) ? round((float)$return['useSpace'] / (float)$dt * 100, 2) : 0;
	$return['barhdPercent'] = $return['hdPercent'].'%';	
	$return['stime'] = date('Y-m-d H:i:s');
	
	return $return;
}

function GetCoreInformation() {
	$file = '/proc/stat';
	if(!is_readable($file)) return false;
	$data = file($file);
	$cores = array();
	foreach($data as $line) {
		if(preg_match('/^cpu[0-9]/', $line)) {
			$info = explode(' ', $line);
			$cores[] = array('user' => $info[1],'nice' => $info[2],'sys' => $info[3],'idle' => $info[4],'iowait' => $info[5],'irq' => $info[6],'softirq' => $info[7]);
		}
	}
	return $cores;
}

function GetCpuPercentages($stat1, $stat2) {
	if(count($stat1) !== count($stat2)) return;
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
		foreach($dif as $x => $y) if($total != 0) $cpu[$x] = round($y / $total * 100, 2);
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

function svr_test_result($provider, $int_result, $float_result, $io_result, $cpu_num, $cpu_name, $cpu_freq) {
	$cpu_freq = number_format($cpu_freq, 2, '.', '');
	return "<tr align=\"center\">\n<td align=\"left\">".$provider."</td>\n<td>".$int_result."秒</td>\n<td>".$float_result."秒</td>\n<td>".$io_result."秒</td>\n<td align=\"left\">".$cpu_num." x ".$cpu_name." @ ".$cpu_freq."GHz</td>\n</tr>";
}

function _get_workerman_status() {
	$filename = sys_get_temp_dir().'/workerman.status';
	if(is_readable($filename)) {
		$status = file_get_contents($filename);
		$status = explode("\n", $status);
		foreach($status as $k => $v) $status[$k] = rtrim($v);
		$header = "<table width=\"100%\" cellpadding=\"3\" cellspacing=\"0\" align=\"center\">\n<tr>\n<th colspan=\"4\">Workerman Status</th>\n</tr>\n<tr>\n<td colspan=\"4\"><span class=\"w_small\">";
		$footer = "</td>\n</tr>\n</table>";
		return $header.str_replace(' ', '&nbsp;', implode('<br/>', $status)).$footer;
	} else {
		return '';
	}
}

function windows_netstat_get() {
	exec('netstat -e', $result, $errno);
	if($errno > 0) return false;
	for($i=0;$i<count($result);$i++) {
		$result[$i] = explode(' ', remove_spaces($result[$i]));
	}
	foreach($result as $array) {
		if($array[0] == 'Bytes') {
			return $array;
		} else {
			continue;
		}
	}
}

function net()
{
	$net = file("/proc/net/dev");
	$netcount = count($net);
	for($i=2;$i<$netcount;$i++)
	{
		$arrnow = explode(': ', net_clean($net[$i]));
		$name = $arrnow[0];
		$result[$name] = [];
		$tmp = explode(' ', $arrnow[1]);
		$result[$name]['in'] = $tmp[0];
		$result[$name]['out'] = $tmp[8];
		//var_dump($result[$name]);
	}

	asort($result); //从低到高排序
	//var_dump($result);
	return $result;
}

function net_clean($input)
{
	while(strstr($input, '  ')) $input = str_replace('  ', ' ', $input);
	return trim($input);
}

function net_ajax_generate($net_now)
{
	global $ajax;
	if(!isset($ajax)) $ajax = '';
	$ajax .= sprintf('$("#NetOut%s").html(dataJSON.NetOut%s);'."\n", $net_now, $net_now);
	$ajax .= sprintf('$("#NetInput%s").html(dataJSON.NetInput%s);'."\n", $net_now, $net_now);
	$ajax .= sprintf('$("#NetOutSpeed%s").html(ForDight((dataJSON.NetOutSpeed%s-OutSpeed%s),3)); OutSpeed%s=dataJSON.NetOutSpeed%s;'."\n", $net_now, $net_now, $net_now, $net_now, $net_now);
	$ajax .= sprintf('$("#NetInputSpeed%s").html(ForDight((dataJSON.NetInputSpeed%s-InputSpeed%s),3)); InputSpeed%s=dataJSON.NetInputSpeed%s;'."\n", $net_now, $net_now, $net_now, $net_now, $net_now);
}

function net_js_generate($interface, $detail)
{
	global $js, $titleajax;
	if(!isset($js)) $js = '';
	$js .= sprintf('var OutSpeed%s=%s;'."\n", $interface, $detail['out']);
	$js .= sprintf('var InputSpeed%s=%s;'."\n", $interface, $detail['in']);
	$titleajax = sprintf('$(\'title\').html(ForDight((dataJSON.NetOutSpeed%s-OutSpeed%s),3));'."\n", $interface, $interface);
}

function network_generate($interface, $detail)
{
	global $network;
	if(!isset($network) or !strstr($network, '<table>')) $network = "<table>\n<tr><th colspan=\"5\">网络使用状况</th></tr>\n<tr>\n";
	$network .= sprintf('<td width="13%%">%s : </td><td width="29%%">入网: <font color=\'#CC0000\'><span id="NetInput%s">%s</span></font></td>'."\n", $interface, $interface, formatsize($detail['in']));
	$network .= sprintf('<td width="14%%">实时: <font color=\'#CC0000\'><span id="NetInputSpeed%s">0B/s</span></font></td>'."\n", $interface);
	$network .= sprintf('<td width="29%%">出网: <font color=\'#CC0000\'><span id="NetOut%s">%s</span></font></td>'."\n", $interface, formatsize($detail['in']));
	$network .= sprintf('<td width="14%%">实时: <font color=\'#CC0000\'><span id="NetOutSpeed%s">0B/s</span></font></td>'."\n".'</tr>'."\n", $interface);
}

function net_json_array_generate($interface, $detail)
{
	$return = [];
	$return['NetOut'.$interface] = formatsize($detail['out']);
	$return['NetInput'.$interface] = formatsize($detail['in']);
	$return['NetOutSpeed'.$interface] = $detail['out'];
	$return['NetInputSpeed'.$interface] = $detail['in'];
	return $return;
}

$http_worker->onMessage = function($connection, $data) {
	global $os, $bin_name, $js, $network, $ajax, $titleajax;
	#echo json_encode($data)."\n";
	if(isset($_GET['act'])) {
		switch($_GET['act']) {
			case 'integer_test':
				$connection->send(integer_test());
			break;
			case 'float_test':
				$connection->send(float_test());
			break;
			case 'io_test':
				$connection->send(io_test());
			break;
			default: break;
		}
	}

	if(isset($_GET['act']) and $_GET['act'] == 'rt') {
		$json = htmlspecialchars($_GET['callback']).'('.json_encode(rt($data['server']['REMOTE_ADDR'])).')';
		$connection->send($json);
	} elseif(strstr($data['server']['REQUEST_URI'], 'phpinfo')) {
		$connection->send('<pre>'.`$bin_name -i`.'</pre>');
	} elseif(strstr($data['server']['REQUEST_URI'], 'functions')) {
		$cmd = $bin_name.' -r "print_r(get_defined_functions());"';
		exec($cmd, $result);
		$result = implode("\n", $result);
		$functions = '<pre>'.$result.'</pre>';
		$connection->send($functions);
	} else {
		$time_start = microtime(true);
		$os = explode(' ', php_uname());
		$get_loaded_extensions = get_loaded_extensions();

		$redis_support = in_array('redis', $get_loaded_extensions) ? '<font color="green">√</font>' : '<font color="red">×</font>';

		if('/' == DIRECTORY_SEPARATOR) {
			$kernel = $os[2];
			$hostname = $os[1];
			} else {
			$kernel = $os[1];
			$hostname = $os[2];
		}
		
		$dt = formatsize(@disk_total_space(".")); //总
		$df = formatsize(@disk_free_space(".")); //可用
		$du = (float)$dt - (float)$df.get_format_level($dt); //已用
		$hdPercent = round((float)$du / (float)$dt * 100 , 2);
		
		$js = '';
		$ajax = '';
		$network = '';
		switch($os[0]) {
			case 'Windows':
				$netstat = windows_netstat_get();
				$NetOut = formatsize_byte($netstat[2]);
				$NetInput = formatsize_byte($netstat[1]);
				$js = "var OutSpeed=$netstat[2];\nvar InputSpeed=$netstat[1];";
				$ajax = "$(\"#NetOut\").html(dataJSON.NetOut);\n$(\"#NetInput\").html(dataJSON.NetInput);\n$(\"#NetOutSpeed\").html(ForDight((dataJSON.NetOutSpeed-OutSpeed),3));	OutSpeed=dataJSON.NetOutSpeed;\n$(\"#NetInputSpeed\").html(ForDight((dataJSON.NetInputSpeed-InputSpeed),3));	InputSpeed=dataJSON.NetInputSpeed;";
				$network = "<table><tr><th colspan=\"5\">网络使用状况</th></tr><tr><td width=\"13%\">本地连接 : </td><td width=\"29%\">入网: <font color='#CC0000'><span id=\"NetInput\">$NetInput</span></font></td><td width=\"14%\">实时: <font color='#CC0000'><span id=\"NetInputSpeed\">0B/s</span></font></td><td width=\"29%\">出网: <font color='#CC0000'><span id=\"NetOut\">$NetOut</span></font></td><td width=\"14%\">实时: <font color='#CC0000'><span id=\"NetOutSpeed\">0B/s</span></font></td></tr></table>";
			break;
			default:
				$net = net();

				foreach($net as $interface => $net_now)
				{
					net_ajax_generate($interface);
					net_js_generate($interface, $net_now);
					network_generate($interface, $net_now);
				}
				$network .= "</table>\n";
			break;
		}
		if($os[0] != 'Windows') {
			$linuxajax = '	$("#UsedMemory").html(dataJSON.UsedMemory);
	$("#FreeMemory").html(dataJSON.FreeMemory);
	$("#CachedMemory").html(dataJSON.CachedMemory);
	$("#Buffers").html(dataJSON.Buffers);
	$("#TotalSwap").html(dataJSON.TotalSwap);
	$("#swapUsed").html(dataJSON.swapUsed);
	$("#swapFree").html(dataJSON.swapFree);
	$("#swapPercent").html(dataJSON.swapPercent);
	$("#loadAvg").html(dataJSON.loadAvg);
	$("#uptime").html(dataJSON.uptime);
	$("#freetime").html(dataJSON.freetime);
	$("#stime").html(dataJSON.stime);
	$("#memRealUsed").html(dataJSON.memRealUsed);
	$("#memRealFree").html(dataJSON.memRealFree);
	$("#memRealPercent").html(dataJSON.memRealPercent);
	$("#memPercent").html(dataJSON.memPercent);
	$("#barmemPercent").width(dataJSON.memPercent);
	$("#barmemRealPercent").width(dataJSON.barmemRealPercent);
	$("#memCachedPercent").html(dataJSON.memCachedPercent);
	$("#barmemCachedPercent").width(dataJSON.barmemCachedPercent);
	$("#barswapPercent").width(dataJSON.barswapPercent);
	$("#corestat").html(dataJSON.corestat);'."\n";
		} else {
			$linuxajax = '';
		}
		$head = "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">
<html xmlns=\"http://www.w3.org/1999/xhtml\">
<head>
<title>雅黑PHP探针[Workerman版]v0.4.7</title>
<meta http-equiv=\"X-UA-Compatible\" content=\"IE=EmulateIE7\" />
<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />
<!-- Powered by: Yahei.Net -->
<style type=\"text/css\">
*{font-family:Microsoft Yahei,Tahoma,Arial}body{margin:0 auto;background-color:#fafafa;text-align:center;font-size:9pt;font-family:Tahoma,Arial}body,h1{padding:0}h1{margin:0;color:#333;font-size:26px;font-family:Lucida Sans Unicode,Lucida Grande,sans-serif}h1 small{font-weight:700;font-size:11px;font-family:Tahoma}a{color:#666}a,a.black{text-decoration:none}a.black{color:#000}table{clear:both;margin:0 0 10px;padding:0;width:100%;border-collapse:collapse;box-shadow:1px 1px 1px #ccc;border-spacing:0;-ms-filter:\"progid:DXImageTransform.Microsoft.Shadow(Strength=2,Direction=135,Color='#CCCCCC')\"}th{padding:3px 6px;border:1px solid #ccc;background:#dedede;color:#626262;text-align:left;font-weight:700}tr{padding:0;background:#fff}td{padding:3px 6px;border:1px solid #ccc}.w_logo{width:13%;color:#333;FONT-SIZE:15px}.w_logo,.w_top{height:25px;text-align:center}.w_top{width:8.7%}.w_top:hover{background:#dadada}.w_foot{height:25px;background:#dedede;text-align:center}input{padding:2px;border-top:1px solid #666;border-right:1px solid #ccc;border-bottom:1px solid #ccc;border-left:1px solid #666;background:#fff;font-size:9pt}input.btn{padding:0 6px;height:20px;border:1px solid #999;background:#f2f2f2;color:#666;font-weight:700;font-size:9pt;line-height:20px}.bar{border:1px solid #999}.bar,.bar_1{overflow:hidden;margin:2px 0 5px;padding:1px;width:89%;height:5px;background:#fff;font-size:2px}.bar_1{border:1px dotted #999}.barli_red{background:#f60}.barli_blue,.barli_red{margin:0;padding:0;height:5px}.barli_blue{background:#09f}.barli_green{background:#36b52a}.barli_black,.barli_green{margin:0;padding:0;height:5px}.barli_black{background:#333}.barli_1{background:#999}.barli,.barli_1{margin:0;padding:0;height:5px}.barli{background:#36b52a}#page{margin:0 auto;padding:0 auto;width:60pc;text-align:left}#header{position:relative;padding:5px}.w_small{font-family:Courier New}.w_number{color:#f800fe}.sudu{padding:0;background:#5dafd1}.suduk{margin:0;padding:0}.resNo{color:red}.word{word-break:break-all}
</style>

<script language=\"JavaScript\" type=\"text/javascript\" src=\"https://cdnjs.cloudflare.com/ajax/libs/jquery/3.4.1/jquery.min.js\"></script>

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
			} else {
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
".$titleajax.$linuxajax.$ajax."
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
	$SwapUsedPercent = ($meminfo['SwapTotal'] > 0) ? round($SwapUsed / $meminfo['SwapTotal'] * 100, 2) : '';
	
	$cached_uptime = uptime();
	$day = floor($cached_uptime / 86400).'天';
	$hour = floor(($cached_uptime % 86400) / 3600).'小时';
	$min = floor(($cached_uptime % 3600) / 60).'分钟';
	$sec = floor($cached_uptime % 60).'秒';
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
	
	$cookie = isset($_COOKIE) ? '<font color="green">√</font>' : '<font color="red">×</font>';
	$smtp_enable = get_cfg_var("SMTP") ? '<font color="green">√</font>' : '<font color="red">×</font>';
	$smtp_addr = get_cfg_var("SMTP") ? get_cfg_var("SMTP") : '<font color="red">×</font>';
	
	if(extension_loaded('gd')) {
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
	
	$swap = ($meminfo['SwapTotal'] > 0) ? '		  SWAP区：共 '.formatsize($meminfo['SwapTotal'], 1).' , 已使用
			  <span id="swapUsed">'.formatsize($SwapUsed, 1).'</span>
			  , 空闲
			  <span id="swapFree">'.formatsize($meminfo['SwapFree'], 1).'</span>
			  , 使用率
			  <span id="swapPercent">'.$SwapUsedPercent.'</span>
			  %
			  <div class="bar"><div id="barswapPercent" class="barli_red" style="width:'.$SwapUsedPercent.'%" >&nbsp;</div> </div>
		' : '';

	if(!isset($data['server']['SERVER_PORT'])) $data['server']['SERVER_PORT'] = 80;
	$cpu = $cpuinfo['cpu_model']['0']['model'];
	if($cpuinfo['cpu_mhz']['0']) $cpu .= ' | 频率:'.$cpuinfo['cpu_mhz']['0'];
	if(isset($cpuinfo['cpu_cache']['0'])) $cpu .= ' | 二级缓存:'.$cpuinfo['cpu_cache']['0'];
	if(isset($cpuinfo['cpu_bogomips']['0'])) $cpu .= ' | Bogomips:'.$cpuinfo['cpu_bogomips']['0'].' × '.$cpuinfo['cpu_num'];
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
		<td>'.$hostname.'</td>
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
	<td width="87%" colspan="5">'.$cpu.'</td>
  </tr>
  <tr>
	<td>CPU使用状况</td>
	<td colspan="5"><span id="corestat">0%us, 0%sy, 0%ni, 100%id, 0%wa, 0%irq, 0%softirq</span></td>
  </tr>
  <tr>
	<td>硬盘使用状况</td>
	<td colspan="5">
		总空间 '.$dt.' ，
		已用 <font color=\'#333333\'><span id="useSpace">'.$du.'</span></font>，
		空闲 <font color=\'#333333\'><span id="freeSpace">'.$df.'</span></font>，
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
		'.$swap.'
		  </td>
	</tr>
	<tr>
		<td>系统平均负载</td>
		<td colspan="5" class="w_number"><span id="loadAvg">'.loadavg().'</span></td>
	</tr>
</table>

'.$network._get_workerman_status().'

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
	  </tr>'.
	  svr_test_result('美国 LinodeVPS', 0.357, 0.802, 0.023, 4, 'Xeon L5520', 2.27).
	  svr_test_result('美国 PhotonVPS.com', 0.431, 1.024, 0.034, 4, 'Xeon E5520', 2.27).
	  svr_test_result('德国 SpaceRich.com', 0.421, 1.003, 0.038, 2, 'Core i7 920', 2.67).
	  svr_test_result('美国 RiZie.com', 0.521, 1.559, 0.054, 1, 'Pentium4', 3.00).
	  svr_test_result('埃及 CitynetHost.com', 0.343, 0.761, 0.023, 2, 'Core2Duo E4600', 2.40).
	  svr_test_result('美国 IXwebhosting.com', 0.535, 1.607, 0.058, 4, 'Xeon E5530', 2.40).
	  '
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
			<td class="w_foot"><A HREF="http://www.Yahei.Net" target="_blank">雅黑PHP探针[Workerman版]v0.4.7</A></td>
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