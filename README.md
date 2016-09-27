# ddos-deflate-nginx-cloudflare
This is ddos-deflate version made to mitigate Layer 7 dos/ddos attacks made to work with nginx and optionally Cloudflare

## About
ddos-deflate-nginx-cloudflare is a lightweight shell and php script designed to assist in
the process of blocking a denial of service attack on layer 7. This is the simplest solution to mitigate dos and ddos attacks on layer 7. It's designed to work with nginx. I was inspired by software https://github.com/jgmdev/ddos-deflate which helped  me to mitigate ddos attacks but since i have switched to Cloudflare for possibilities to avoid other types of ddos attacks before they reach my server, preventing access for blacklisted ips and advantages of cdn and much more. Disclaimer - of course Cloudflare can block layer 7 attacks as well, but not fully and not in all cases - it depends on many factors and chosen plan with their services, with free plain we receive only basic security etc, with this tool switching to paid plans may not be necessary in most cases. Original ddos-deflate from jgmdev repository is useless due fact that it would not block attacker but entire cloudflare server ip since user will connect through CF servers.

I would call my version as ddos-deflate 2.0, it mitigates ddos layer 7 attacks flawlessly. Even cloudflare can't itself give good security to protect against application layer attacks, if we can estimate how many requests each user can potentially make then it's easy to mitigate such attacks with this software.

This software is designed to work with nginx and Cloudflare, but it will also work fine without Cloudflare - no need to reconfigure anything. It works thanks to nginx module called ngx_http_realip_module to resolve real client ip behind cloudflare within header passed by CF. All logs from nginx will also contain real user ip. Cloudflare server ips are being checked na updated each day considering 5s daemon interval. Before installing you should double check nginx installation paths in ddos-deflate.sh script and config.json. It was tested on ubuntu and paths should be correct on all ubuntu versions as well on debian.
If you just need real ip behind cloudflare you should read this https://support.cloudflare.com/hc/en-us/articles/200170706-How-do-I-restore-original-visitor-IP-with-Nginx-

## Installation
```
git clone https://github.com/karek314/ddos-deflate-nginx-cloudflare
cd ddos-deflate-nginx-cloudflare
bash ddos-deflate.sh install
```
## Usage
```
################################################
ddos-deflate+cloudflare version 1.0 by karek314
version 1.0

Available commands
install - install all dependencies
start - starting ddos-deflate daemon
stop - stopping ddos-deflate daemon
attach - attach to ddos-deflate daemon (CTRL+A+D to quit)
autostart_add - adding ddos-deflate daemon to autostart
autostart_remove - removing ddos-deflate daemon from autostart
config - configuration file
connections - display 'current' connections handled by nginx (connections in last N seconds)
reqpersec - display live request per second
help - show this message
```

In order to start defending yourself you need to start daemon and also add it to autostart
```
autostart_add - adding ddos-deflate daemon to autostart
start - starting ddos-deflate daemon
```

You can also manually monitor current request per second and get list of all ips sorted by number of requests in N time

bash ddos-deflate.sh reqpersec
```
example
[259req/sec] Load Average:10.63
[259req/sec] Load Average:10.63
[565req/sec] Load Average:10.63
[366req/sec] Load Average:10.63
[351req/sec] Load Average:10.5
[360req/sec] Load Average:10.5
```

bash ddos-deflate.sh connections
```
Detected 36 users in last 5s
18 connections from x.x.x.xx
12 connections from x.x.x.xx
10 connections from x.x.x.xx
8 connections from x.x.x.xx
7 connections from x.x.x.xx
7 connections from x.x.x.xx
...
Total 126 requests has been made in last 5s
[25.2req/sec]
```

each banned ip will appear in daemon_logs.txt log file within the same directory
```
[09/27/2016 05:20:50 pm] - Banning IP:10.0.0.2 for 216/150 connections
[09/27/2016 05:20:50 pm] - IP:10.0.0.2 has been banned for 600s
[09/27/2016 05:22:05 pm] - Banning IP:10.0.0.2 for 239/150 connections
[09/27/2016 05:22:05 pm] - This ip has been already banned
[09/27/2016 05:22:50 pm] - Banning IP:10.0.0.2 for 248/150 connections
[09/27/2016 05:22:50 pm] - This ip has been already banned
[09/27/2016 05:26:50 pm] - Banning IP:10.0.0.2 for 235/150 connections
[09/27/2016 05:26:50 pm] - This ip has been already banned
[09/27/2016 05:30:55 pm] - Removing banned IP:10.0.0.2
[09/27/2016 05:31:25 pm] - Banning IP:10.0.0.2 for 1283/150 connections
[09/27/2016 05:31:25 pm] - IP:10.0.0.2 has been banned for 600s
[09/27/2016 05:31:30 pm] - Banning IP:10.0.0.2 for 1452/150 connections
[09/27/2016 05:31:30 pm] - This ip has been already banned
[09/27/2016 05:41:30 pm] - Removing banned IP:10.0.0.2
```
Each banned ip will be dropped with 403 error


## Additional configuration
In config.json you can modify length of ban time in seconds,  number of connections to ban ip, lines to analyse  - you don't need to worry about this one if your server handle less than  1000 request per seconds,  it should be equal to (max expected request per sec * interval_in_sec_considering_simultaneous_connections), if you have any problems with running tool make sure to check nginx date format in access.log and apply correct configuration as following http://php.net/manual/en/function.date.php

```
"ban_time":"600",
"ban_when_n_connections":"150",
"lines_to_analyse":"5000",
"nginx_log_path":"/var/log/nginx/access.log",
"interval_in_sec_considering_simultaneous_connections":"5",
"nginx_dateformat":"d/M/Y H:i:s",
"daemon_interval_in_sec":"5"
```
