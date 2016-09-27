<?php 
error_reporting(0);

function getArrayOfIpsSorted($config){
	$n_lines_analyse = $config['lines_to_analyse'];
	$nginx_log_path = $config['nginx_log_path'];
	$interval = $config['interval_in_sec_considering_simultaneous_connections'];
	$output = shell_exec('tail -'.$n_lines_analyse.' '.$nginx_log_path);
	$line_by_line = explode("\n", $output);
	$outputarray = array();
	foreach ($line_by_line as $key => $value) {
		preg_match('/^(\S+) (\S+) (\S+) \[([^:]+):(\d+:\d+:\d+) ([^\]]+)\] \"(\S+) (.*?) (\S+)\" (\S+) (\S+) (\".*?\") (\".*?\")$/',$value, $matches);
		if ($matches) {
			$date = $matches[4];
			$time = $matches[5];
			$ip = $matches[1];
			$date_time = $date." ".$time;
			$datetime = DateTime::createFromFormat($config['nginx_dateformat'], $date_time);
			$timestamp = $datetime->getTimestamp();
			$current_timestamp = time();
			$difference = $current_timestamp - $timestamp;
			if ($difference < $interval) {
				if (!$outputarray[$ip]) {
					$outputarray[$ip] = 1;
				} else {
					$outputarray[$ip] = $outputarray[$ip] + 1;
				}
			}
			//echo "\nTimestamp:".$timestamp." IP:".$ip." Date:".$date_time;
		}
	}
	asort($outputarray);
	return array_reverse($outputarray);
}

?>