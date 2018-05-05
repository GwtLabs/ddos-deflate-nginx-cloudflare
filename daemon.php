<?php

error_reporting(E_ALL);
include 'utils.php';

$config_json = file_get_contents('config.json');
$config = json_decode($config_json, true);

$daemon = new Daemon($config);
$daemon->run();

class Daemon
{
    private $sleep_const = 5;
    private $allowed_connection_limit = 150;
    private $black_list_conf = '/etc/nginx/blacklisted_ips.conf';
    private $cf_ips_conf = '/etc/nginx/cloudflare_settings.conf';
    private $add_points_per_baned_ip = 10;
    private $sub_points_per_tick = 1;
    private $i = 0;
    private $c_c = 17280;
    private $under_attack_pointer = 0;
    private $config = [];
    private $bannedIpList = [];
    private $nowBanned = [];

    public function __construct($config)
    {
        $this->config = $config;
        $this->sleep_const = $config['daemon_interval_in_sec'];
        $this->allowed_connection_limit = $config['ban_when_n_connections'];
        $this->add_points_per_baned_ip = 10;
        $this->under_attack_pointer = 0;
    }

    public function run()
    {
        while (1) {
            $this->i++;
            if ($this->c_c >= 17280) {
                //Update cloduflare ips each 17280 iterations which is equal to 24h with 5s daemon interval
                $this->updateCloudFlareIps($this->cf_ips_conf);
                $this->c_c = 0;
            }
            $this->c_c++;
            $starttime = microtime(true);
            echo "\n===============================";
            echo "\nIteration:" . $this->i;
            echo "\nUnder Attack Pointer: " . $this->under_attack_pointer;

            $this->refreshBannedIpList();
            $ips = getArrayOfIpsSorted($this->config);
            echo "\nDetected " . count($ips) . ' users in last ' . $this->config['interval_in_sec_considering_simultaneous_connections'] . ' s.';
            foreach ($ips as $ip => $connections) {
                if ($connections >= $this->allowed_connection_limit) {
                    $this->toBan($ip, $connections);
                }
            }
            $this->saveBannedIpList();
            $this->checkBannedIpForUnbanOpportunity();

            if ($this->under_attack_pointer > 0) {
                $this->under_attack_pointer -= $this->sub_points_per_tick;
                $this->save_log('Under attack pointer reduced to: ' . $this->under_attack_pointer);
            }
            $duration = microtime(true) - $starttime;
            echo "\nExecution Time: " . $duration . ' s.';
            $sleep = abs($this->sleep_const - $duration);
            echo "\nSleeping for:" . $sleep;
            usleep($sleep * 1000000);
        }
    }

    private function refreshBannedIpList()
    {
        $output = file_get_contents($this->black_list_conf);
        $line_by_line = $this->remove_empty(explode("\n", $output));
        echo "\nBanned ips: " . count($line_by_line);
        $list = [];
        foreach ($line_by_line as $line) {
            if ($line) {
                $value = explode('#', $line);
                $banned_ip = $value[0];
                $banned_ip = str_replace('deny ', '', $banned_ip);
                $banned_ip = str_replace(';', '', $banned_ip);
                $list[$banned_ip] = $value[1];
            }
        }
        $this->bannedIpList = $list;
    }

    private function isAlredyBanned($ip)
    {
        return array_key_exists($ip, $this->bannedIpList);
    }

    private function getBanTime()
    {
        if ($this->under_attack_pointer <= $this->add_points_per_baned_ip * 2) {
            return $this->config['ban_time_lo'];
        } elseif ($this->under_attack_pointer > $this->add_points_per_baned_ip * 10) {
            return $this->config['ban_time_hi'];
        }
        return $this->config['ban_time_md'];
    }

    private function checkBannedIpForUnbanOpportunity()
    {
        $new_file = '';
        $removed = false;
        $time = time();
        foreach ($this->bannedIpList as $ip => $ubTime) {
            if ($time > $ubTime) {
                $this->save_log('Removing banned IP: ' . $ip);
                $removed = true;
            } else {
                $new_file .= 'deny ' . $ip . ';#' . $ubTime . "\n";
            }
        }
        if (!$removed) {
            return;
        }
        file_put_contents($this->black_list_conf, $new_file);
        $this->reloadNginxSettings();
        echo "\nSaved new blacklist conf.";
    }

    private function addNewBan($ip, $time)
    {
        $this->under_attack_pointer += $this->add_points_per_baned_ip;
        $this->save_log('Under attack pointer increased to: ' . $this->under_attack_pointer);
        $this->nowBanned[$ip] = $time;
    }

    private function toBan($ip, $connections)
    {
        if ($this->isAlredyBanned($ip)) {
            $this->save_log('IP ' . $ip . ' has been already banned.');
            return;
        }
        $this->save_log('Banning IP: ' . $ip . ' for ' . $connections . '/' . $this->allowed_connection_limit . ' connections.');
        $this->addNewBan($ip, $this->getBanTime());
    }

    private function saveBannedIpList()
    {
        if (!$this->nowBanned) {
            return;
        }
        $now = time();
        foreach ($this->nowBanned as $ip => $time) {
            $unbanTime = $now + $time;
            $this->bannedIpList[$ip] = $unbanTime;
            $content = 'deny ' . $ip . ';#' . $unbanTime . "\n";
            file_put_contents($this->black_list_conf, $content, FILE_APPEND);
            $this->save_log('IP: ' . $ip . ' has been banned for ' . $time . ' s.');
        }
        $this->nowBanned = [];
        $this->reloadNginxSettings();
    }

    private function updateCloudFlareIps($cf_ips_conf)
    {
        echo "\nUpdating CF ips";
        $ipv4_list = $this->getForURL('https://www.cloudflare.com/ips-v4');
        $ipv6_list = $this->getForURL('https://www.cloudflare.com/ips-v6');
        $line_by_line_ipv4 = $this->remove_empty(explode("\n", $ipv4_list));
        $line_by_line_ipv6 = $this->remove_empty(explode("\n", $ipv6_list));
        $real_cf_ip_list = [];
        foreach ($line_by_line_ipv4 as $key => $value) {
            array_push($real_cf_ip_list, 'set_real_ip_from ' . $value . ';');
        }
        foreach ($line_by_line_ipv6 as $key => $value) {
            array_push($real_cf_ip_list, 'set_real_ip_from ' . $value . ';');
        }
        if (!$real_cf_ip_list) {
            $this->save_log('[Warning] CF ips is empty! Canceled.');
            return;
        }
        array_push($real_cf_ip_list, 'real_ip_header CF-Connecting-IP;');
        echo "\nNew CF ips acquired";
        var_dump($real_cf_ip_list);
        echo "\nSaving now";
        $new_file = '';
        foreach ($real_cf_ip_list as $key => $value) {
            $new_file = $new_file . "\n" . $value;
        }
        file_put_contents($cf_ips_conf, $new_file);
        $this->reloadNginxSettings();
        echo 'New CF ips saved and applied! - Next check in ~24h';
    }

    private function getForURL($url)
    {
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

    private function remove_empty($array)
    {
        return array_filter($array, [$this, '_remove_empty_values']);
    }

    private function _remove_empty_values($value)
    {
        return !empty($value) || $value === 0;
    }

    private function reloadNginxSettings()
    {
        $output = shell_exec('/etc/init.d/nginx reload');
        echo "\n" . $output;
    }

    private function save_log($msg)
    {
        $now = time();
        $date = date('Y-m-d H:i:s', $now);
        $msg = '[' . $date . '] - ' . $msg . "\n";
        $file = 'log/daemon-' . date('Y-m-d', $now) . '.log';
        file_put_contents($file, $msg, FILE_APPEND);
        echo $msg;
    }
}
