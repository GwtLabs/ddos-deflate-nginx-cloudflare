<?php 
error_reporting(E_ALL);
include 'utils.php';
$config_json = file_get_contents('config.json');
$config = json_decode($config_json,true);
$mode = $argv[1];

if ($mode == 'connections') {
	$array = getArrayOfIpsSorted($config);
	$count = count($array);
	echo "\nDetected ".$count." users in last ".$config['interval_in_sec_considering_simultaneous_connections']."s";
	$connections = 0;
	foreach ($array as $key => $value) {
		echo "\n".$value." connections from ".$key;
		$connections = $connections + $value;
	}
	$req_per_sec = $connections/$config['interval_in_sec_considering_simultaneous_connections'];
	echo "\nTotal ".$connections." requests has been made in last ".$config['interval_in_sec_considering_simultaneous_connections']."s";
	echo "\n[".$req_per_sec."req/sec]\n";
} else if ($mode == 'reqpersec') {
	$n_lines_analyse = $config['lines_to_analyse'];
	$nginx_log_path = $config['nginx_log_path'];
	while(1){
		$load = sys_getloadavg();
		$output_first = shell_exec('tail -'.$n_lines_analyse.' '.$nginx_log_path);
		$outputarray = explode("\n", $output_first);
		$last_line = $outputarray[$n_lines_analyse-1];
		sleep(1);
		$output_next = shell_exec('tail -'.$n_lines_analyse.' '.$nginx_log_path);
		$outputarraycheck = explode("\n", $output_next);
		$key = array_search($last_line, $outputarraycheck);
		$req_per_sec = $n_lines_analyse-$key-1;
		echo "\n[".$req_per_sec."req/sec] Load Average:".$load[0];
	}
} else {
	die("\nSpecify method\n");
}

?>