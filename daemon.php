<?php 
error_reporting(E_ALL);
include 'utils.php';
$config_json = file_get_contents('config.json');
$config = json_decode($config_json,true);
$sleep_const = $config['daemon_interval_in_sec'];
$allowed_connection_limit = $config['ban_when_n_connections'];
$ban_time = $config['ban_time'];
$black_list_conf = "/etc/nginx/blacklisted_ips.conf";
$cf_ips_conf = "/etc/nginx/cloudflare_settings.conf";

$i = 0;
$c_c = 17280;
while (1) {
	$i++;
	if ($c_c >= 17280) {
		//Update cloduflare ips each 17280 iterations which is equal to 24h with 5s daemon interval
		updateCloudFlareIps($cf_ips_conf);
		$c_c = 0;
	}
	$c_c++;
	$starttime = microtime(true);
	echo "\nIteration:".$i;

	$array = getArrayOfIpsSorted($config);
	$count = count($array);
	echo "\nDetected ".$count." users in last ".$config['interval_in_sec_considering_simultaneous_connections']."s";
	$connections = 0;
	foreach ($array as $key => $value) {
		if ($value >= $allowed_connection_limit) {
			save_log("Banning IP:".$key." for ".$value."/".$allowed_connection_limit." connections");
			ban_ip($key,$ban_time,$black_list_conf);
		}
	}
	checkBannedIpForUnbanOpportunity($black_list_conf);

	$duration = microtime(true) - $starttime;
	echo "\nExecution Time:".$duration.'s';
	$sleep = abs($sleep_const - $duration);
	echo "\nSleeping for:".$sleep;
	usleep($sleep*1000000);
}

function checkBannedIpForUnbanOpportunity($black_list_conf){
	$output = file_get_contents($black_list_conf);
	$line_by_line = remove_empty(explode("\n", $output));
	$isIpBannedAlready = 0;
	echo "\nListing all banned ips:";
	$rewrited_array = array();
	foreach ($line_by_line as $key => $value) {
		if ($value) {
			$value_array = explode('#', $value);
			$banned_ip = $value_array[0];
			$banned_ip = str_replace('deny ', '', $banned_ip);
			$banned_ip = str_replace(';', '', $banned_ip);
			$unban_timestamp = $value_array[1];
			$current_timestamp = time();
			$diff = $unban_timestamp - $current_timestamp;
			echo "\n".$value." connections from ".$key." left ".$diff.'s ban';
			if ($diff < 0) {
				save_log("Removing banned IP:".$banned_ip);
			} else {
				array_push($rewrited_array, $value);
			}
		}
	}
	if (!array_equal($rewrited_array,$line_by_line)) {
		$new_file = '';
		foreach ($rewrited_array as $key => $value) {
			$new_file = $new_file."\n".$value;
		}
		file_put_contents($black_list_conf, $new_file);
		reloadNginxSettings();
		echo "Saved new blacklist conf";
	} else {
		echo "\nArrays are the same, keeping old records";
	}
}

function ban_ip($ip,$ban_time,$black_list_conf){
	$output = file_get_contents($black_list_conf);
	$line_by_line = explode("\n", $output);
	$isIpBannedAlready = 0;
	echo "\nListing all banned ips:";
	foreach ($line_by_line as $key => $value) {
		if ($value) {
			$value_array = explode('#', $value);
			$banned_ip = $value_array[0];
			$banned_ip = str_replace('deny ', '', $banned_ip);
			$banned_ip = str_replace(';', '', $banned_ip);
			$unban_timestamp = $value_array[1];
			$current_timestamp = time();
			$difference_to_unban = $unban_timestamp - $current_timestamp;
			echo "\nCurrently banned ip:".$banned_ip." unban will occur after:".$difference_to_unban."s";
			if ($banned_ip == $ip) {
				$isIpBannedAlready = 1;
				save_log("This ip has been already banned");
			}
		}
	}
	if (!$isIpBannedAlready) {
		$timestamp_c = time();
		$unban_timestamp = $timestamp_c + $ban_time;
		$content = "\ndeny ".$ip.";#".$unban_timestamp;
		file_put_contents($black_list_conf, $content, FILE_APPEND);
		reloadNginxSettings();
		save_log("IP:".$ip." has been banned for ".$ban_time."s");
	}
}

function updateCloudFlareIps($cf_ips_conf){
	echo "\nUpdating CF ips";
	$ipv4_list = getForURL('https://www.cloudflare.com/ips-v4');
	$ipv6_list = getForURL('https://www.cloudflare.com/ips-v6');
	$line_by_line_ipv4 = remove_empty(explode("\n", $ipv4_list));
	$line_by_line_ipv6 = remove_empty(explode("\n", $ipv6_list));
	$real_cf_ip_list = array();
	foreach ($line_by_line_ipv4 as $key => $value) {
		array_push($real_cf_ip_list, "set_real_ip_from ".$value.";");
	}
	foreach ($line_by_line_ipv6 as $key => $value) {
		array_push($real_cf_ip_list, "set_real_ip_from ".$value.";");
	}
	array_push($real_cf_ip_list, "real_ip_header CF-Connecting-IP;");
	echo "\nNew CF ips acquired";
	var_dump($real_cf_ip_list);
	echo "\nSaving now";
	$new_file = '';
	foreach ($real_cf_ip_list as $key => $value) {
		$new_file = $new_file."\n".$value;
	}
	file_put_contents($cf_ips_conf, $new_file);
	reloadNginxSettings();
	echo "New CF ips saved and applied! - Next check in ~24h";
}

function getForURL($url){
	$curl = curl_init();
	curl_setopt_array($curl, array(
    	CURLOPT_RETURNTRANSFER => 1,
    	CURLOPT_URL => $url,
    	CURLOPT_USERAGENT => 'DDOS-DEFLATE for Cloudflare & Nginx [v1.0]'
	));
	$resp = curl_exec($curl);
	curl_close($curl);
	return $resp;
}

function array_equal($a, $b) {
    return (
         is_array($a) && is_array($b) && 
         count($a) == count($b) &&
         array_diff($a, $b) === array_diff($b, $a)
    );
}

function remove_empty($array) {
  return array_filter($array, '_remove_empty_values');
}

function _remove_empty_values($value) {
  return !empty($value) || $value === 0;
}

function reloadNginxSettings(){
	$output = shell_exec('/etc/init.d/nginx reload');
	echo "\n".$output;
}

function save_log($msg){
	$date = date('m/d/Y h:i:s a', time());
    $msg = "\n[".$date."] - ".$msg;
    $file = 'daemon_logs.txt';
    file_put_contents($file, $msg, FILE_APPEND);
    echo $msg;
 }

?>