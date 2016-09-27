#!/bin/bash

start_daemon() {
  echo ""
  echo "Starting ddos-deflate daemon"
  screen -dmS ddos-deflate-daemon php daemon.php
  sleep 1

  if [ $? == 0 ]; then
    echo "√ Started"
  else
    echo "X Error"
  fi
}


stop_daemon() {
  echo ""
  echo "Stopping ddos-deflate daemon"
  screen -X -S "ddos-deflate-daemon" quit
  if [ $? == 0 ]; then
    echo "√ stopped"
  else
    echo "X can't stop"
  fi
}

help_msg(){
  echo "################################################"
  echo "ddos-deflate+cloudflare version 1.0 by karek314"
  echo "version 1.0"
  echo ""
  echo "Available commands"
  echo "install - install all dependencies"
  echo "start - starting ddos-deflate daemon"
  echo "stop - stopping ddos-deflate daemon"
  echo "attach - attach to ddos-deflate daemon (CTRL+A+D to quit)"
  echo "autostart_add - adding ddos-deflate daemon to autostart"
  echo "autostart_remove - removing ddos-deflate daemon from autostart"
  echo "config - configuration file"
  echo "connections - display 'current' connections handled by nginx (connections in last N seconds)"
  echo "reqpersec - display live request per second"
  echo "help - show this message"
}

install_d(){
  apt-get install -y nano php5 php5-cli php5-curl screen
  touch /etc/nginx/blacklisted_ips.conf
  touch /etc/nginx/cloudflare_settings.conf
  sed -i '/http {/a include blacklisted_ips.conf; include cloudflare_settings.conf;' /etc/nginx/nginx.conf
  nginx -t
  service nginx reload
  echo "√ installed successfully"
  help_msg
}

config(){
  nano config.json
}

attach(){
  screen -x ddos-deflate-daemon
}

req_per_sec(){
  php cli.php reqpersec
}

autostart_add(){
  (crontab -l 2>/dev/null; echo "@reboot $PWD/ddos-deflate.sh start") | crontab -
  echo "√ added successfully"
}

autostart_remove(){
  crontab -e
}

connections(){
  php cli.php connections
}

case $1 in
"start")
  start_daemon
  ;;
"help")
  help_msg
  ;;
"attach")
  attach
  ;;
"install")
  install_d
  ;;
"autostart_add")
  autostart_add
  ;;
"autostart_remove")
  autostart_remove
  ;;
"config")
  config
  ;;
"reqpersec")
  req_per_sec
  ;;
"connections")
  connections
  ;;
"stop")
  stop_daemon
  ;;
"restart")
  stop_daemon
  start_daemon
  ;;
*)
  help_msg
  ;;
esac
