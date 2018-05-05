<?php
error_reporting(0);

function getArrayOfIpsSorted($config)
{
    $n_lines_analyse = $config['lines_to_analyse'];
    $nginx_log_path = $config['nginx_log_path'];
    $interval = $config['interval_in_sec_considering_simultaneous_connections'];
    $output = shell_exec('tail -' . $n_lines_analyse . ' ' . $nginx_log_path);
    $line_by_line = array_reverse(explode("\n", $output));
    $outputarray = [];
    $current_timestamp = time();
    foreach ($line_by_line as $key => $value) {
        preg_match('/^(\S+) (\S+) (\S+) \[([^:]+):(\d+:\d+:\d+) ([^\]]+)\] \"(\S+) (.*?) (\S+)\" (\S+) (\S+) (\".*?\") (\".*?\")$/', $value, $matches);
        if ($matches) {
            $date = $matches[4];
            $time = $matches[5];
            $ip = $matches[1];
            $date_time = $date . " " . $time;
            $datetime = DateTime::createFromFormat($config['nginx_dateformat'], $date_time);
            if ($current_timestamp - $datetime->getTimestamp() < $interval) {
                if (!$outputarray[$ip]) {
                    $outputarray[$ip] = 1;
                } else {
                    $outputarray[$ip] = $outputarray[$ip] + 1;
                }
                continue;
            }
            break;
            //echo "\nTimestamp:".$timestamp." IP:".$ip." Date:".$date_time;
        }
    }
    asort($outputarray);
    return array_reverse($outputarray);
}
