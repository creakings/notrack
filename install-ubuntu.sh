#!/usr/bin/env bash
#Title   : NoTrack Installer
#Description : This script will install NoTrack and then configure dnsmasq and lighttpd
#Authors : QuidsUp, floturcocantsee, rchard2scout, fernfrost
#Usage   : bash install.sh
#Version : 20.10

#######################################
# User Configerable Settings
#######################################
INSTALL_LOCATION=""                              #define custom installation path
NOTRACK_REPO="https://github.com/quidsup/notrack.git"
HOSTNAME=""
NETWORK_DEVICE=""
WEB_USER=""
WEB_FOLDER="/var/www/html"
SERVERIP1="1.1.1.1"
SERVERIP2="1.0.0.1"
LISTENIP="127.0.0.1"

#######################################
# Constants
#######################################
readonly VERSION="20.10"


#######################################
# Global Variables
#######################################
DBUSER="ntrk"
DBPASSWORD="ntrkpass"
DBNAME="ntrkdb"
SUDO_REQUIRED=false                              #true if installing to /opt


#######################################
# Copy
#   Copies either a file or directory
#
# Globals:
#   None
# Arguments:
#   $1: Source
#   $2: Destination
# Returns:
#   None
#######################################
function copy() {
  if [ -f "$1" ]; then                                     #Does file exist?
    echo "Copying $1 to $2"
    sudo cp "$1" "$2"
  elif [ -d "$1" ]; then                                   #Does directory exist?
    echo "Copying folder $1 to $2"
    sudo cp -r "$1" "$2"
  else                                                     #Or unable find source
    echo "WARNING: Unable to find $1 :-("
  fi
}


#######################################
# Create File
# Checks if a file exists and creates it
#
# Globals:
#   None
# Arguments:
#   #$1 File to create
# Returns:
#   None
#######################################
function create_file() {
  if [ ! -e "$1" ]; then                         #Does file already exist?
    echo "Creating file: $1"
    sudo touch "$1"                              #If not then create it
    sudo chmod 664 "$1"                          #RW RW R permissions
  fi
}


#######################################
# Create Folder
#   Creates a folder if it doesn't exist
# Globals:
#   None
# Arguments:
#   $1 - Folder to create
# Returns:
#   None
#######################################
function create_folder() {
  if [ ! -d "$1" ]; then                         #Does folder exist?
    echo "Creating folder: $1"                   #Tell user folder being created
    sudo mkdir "$1"                              #Create folder
  fi
}


#######################################
# Exit script with exit code
# Globals:
#   None
# Arguments:
#   $1 Error Message
#   $2 Exit Code
# Returns:
#   Exit Code
#######################################
error_exit() {
  echo "Error :-( $1"
  echo "Aborting"
  exit "$2"
}

#######################################
# Rename File
#   Renames Source file to Destination
#   Set permissions to -rwxr-xr-x
#
# Globals:
#   None
# Arguments:
#   $1: Source
#   $2: Destination
# Returns:
#   None
#######################################
function rename_file() {
  if [ -e "$1" ]; then                                     #Does file exist?
    sudo mv "$1" "$2"
    sudo chmod 755 "$2"
  else
    echo "WARNING: Unable to rename file $1 :-("
  fi
}


#######################################
# Set Ownership of either a file or folder
#
# Globals:
#   None
# Arguments:
#   $1 File or Folder
#   $2 User
#   $3 Group
# Returns:
#   None
#######################################
function set_ownership() {
  if [ -d "$1" ]; then
    echo "Setting ownership of folder $1 to $2:$3"
    sudo chown -hR "$2":"$3" "$1"
  elif [ -e "$1" ]; then
    echo "Setting ownership of file $1 to $2:$3"
    sudo chown "$2":"$3" "$1"
  else
    echo "Set_Ownership: Error - $1 is missing"
  fi
}


#######################################
# Set Permissions of either a file or folder
#
# Globals:
#   None
# Arguments:
#   $1 File or Folder
#   $2 Permissions
# Returns:
#   None
#######################################
function set_permissions() {
  if [ -d "$1" ]; then
    echo "Setting permissions of folder $1 to $2"
    sudo chmod -R "$2" "$1"
  elif [ -e "$1" ]; then
    echo "Setting permissions of file $1 to $2"
    sudo chmod "$2" "$1"
  else
    echo "Set_Permissions: Error - $1 is missing"
  fi
}


#######################################
# Restart service
#    with either systemd or sysvinit or runit
#
# Globals:
#   None
# Arguments:
#   None
# Returns:
#   None
#######################################
function service_restart() {
  if [[ -n $1 ]]; then
    echo "Restarting $1"
    if [ "$(command -v systemctl)" ]; then       #systemd
      sudo systemctl restart "$1"
    elif [ "$(command -v service)" ]; then       #sysvinit
      sudo service "$1" restart
    elif [ "$(command -v sv)" ]; then            #runit
      sudo sv restart "$1"
    else
      error_exit "Unable to restart services. Unknown service supervisor" "21"
    fi
  fi
}


#######################################
# Start service
#   Start and Enable systemd based services
#   TODO complete for sv and sysvinit
#
# Globals:
#   None
# Arguments:
#   None
# Returns:
#   None
#######################################
function service_start() {
  if [[ -n $1 ]]; then
    echo "Starting $1"
    if [ "$(command -v systemctl)" ]; then                 #systemd
      sudo systemctl enable "$1"
      sudo systemctl start "$1"
    fi
  fi
}

#######################################
# Draw prompt menu
#   1. Clear Screen
#   2. Draw menu
#   3. Read single character of user input
#   4. Evaluate user input
#   4a. Check if value is between 0-9
#   4b. Check if value is between 1 and menu size. Return out of function if sucessful
#   4c. Check if user pressed the up key (ending A), Move highlighted point
#   4d. Check if user pressed the up key (ending B), Move highlighted point
#   4e. Check if user pressed Enter key, Return out of function
#   4f. Check if user pressed Q or q, Exit out with error code 1
#   5. User failed to input valid selection. Loop back to #2
#
# Globals:
#   None
# Arguments:
#   $1 = Title, $2, $3... Option 1, 2
# Returns:
#   $? = Choice user made
#######################################
function menu() {
  local choice
  local highlight
  local menu_size

  highlight=1
  menu_size=0
  clear
  while true; do
    for i in "$@"; do
      if [ $menu_size == 0 ]; then                #$1 Is Title
        echo -e "$1"
        echo
      else
        if [ $highlight == $menu_size ]; then
          echo " * $menu_size: $i"
        else
          echo "   $menu_size: $i"
        fi
      fi
      ((menu_size++))
    done

    read -r -sn1 choice;
    echo "$choice"
    if [[ $choice =~ ^[0-9]+$ ]]; then           #Has the user chosen 0-9
      if [[ $choice -ge 1 ]] && [[ $choice -lt $menu_size ]]; then
        return "$choice"
      fi
    elif [[ $choice ==  "A" ]]; then             #Up
      if [ $highlight -le 1 ]; then              #Loop around list
        highlight=$((menu_size-1))
        echo
      else
        ((highlight--))
      fi
    elif [[ $choice ==  "B" ]]; then             #Down
      if [ $highlight -ge $((menu_size-1)) ]; then #Loop around list
        highlight=1
        echo
      else
        ((highlight++))
      fi
    elif [[ $choice == "" ]]; then               #Enter
      return "$highlight"                        #Return Highlighted value
    elif [[ $choice == "q" ]] || [[ $choice == "Q" ]]; then
      exit 1
    fi
    #C Right, D Left

    menu_size=0
    clear
  done
}


#######################################
# Prompt for Install Location
# Globals:
#   INSTALL_LOCATION
# Arguments:
#   None
# Returns:
#   None
#######################################
function prompt_installloc() {
  if [[ -n $INSTALL_LOCATION ]]; then
    return
  fi

  local homefolder="${HOME}"

  #Find users home folder if installer was run as root
  if [[ $homefolder == "/root" ]]; then
    homefolder="$(getent passwd | grep /home | grep -v syslog | cut -d: -f6)"
    if [ "$(wc -w <<< "$homefolder")" -gt 1 ]; then          #How many users found?
      echo "Unable to estabilish which Home folder to install to"
      echo "Either run this installer without using sudo / root, or manually set the \$INSTALL_LOCATION variable"
      echo "\$INSTALL_LOCATION=\"/home/you/NoTrack\""
      exit 15
    fi
  fi

  menu "Select Install Folder" "Home $homefolder" "Opt /opt" "Cancel"
  case $? in
    1)
      INSTALL_LOCATION="$homefolder/notrack"
    ;;
    2)
      INSTALL_LOCATION="/opt/notrack"
      SUDO_REQUIRED=true
    ;;
    3)
      error_exit "Aborting Install" 1
    ;;
  esac

  if [[ $INSTALL_LOCATION == "" ]]; then
    error_exit "Install folder not set" 15
  fi
}


#######################################
# Prompt for network device
# Globals:
#   NETWORK_DEVICE
# Arguments:
#   None
# Returns:
#   None
#######################################
function prompt_network_device() {
  local count_net_dev=0
  local device=""
  local -a device_list
  local menu_choice

  if [[ -n $NETWORK_DEVICE ]]; then                        #Check if NETWORK_DEVICE is set
    return
  fi

  if [ ! -d /sys/class/net ]; then               #Check net devices folder exists
    echo "Error. Unable to find list of Network Devices"
    echo "Edit user customisable setting \$NETWORK_DEVICE with the name of your Network Device"
    echo "e.g. \$NETWORK_DEVICE=\"eth0\""
    exit 11
  fi

  for device in /sys/class/net/*; do             #Read list of net devices
    device="${device:15}"                        #Trim path off
    if [[ $device != "lo" ]]; then               #Exclude loopback
      device_list[$count_net_dev]="$device"
      ((count_net_dev++))
    fi
  done

  if [ "$count_net_dev" -eq 0 ]; then             #None found
    echo "Error. No Network Devices found"
    echo "Edit user customisable setting \$NETWORK_DEVICE with the name of your Network Device"
    echo "e.g. \$NETWORK_DEVICE=\"eth0\""
    exit 11

  elif [ "$count_net_dev" -eq 1 ]; then           #1 Device
    NETWORK_DEVICE=${device_list[0]}             #Simple, just set it
  elif [ "$count_net_dev" -gt 0 ]; then
    menu "Select Network Device" "${device_list[*]}"
    menu_choice=$?
    NETWORK_DEVICE=${device_list[$((menu_choice-1))]}
  elif [ "$count_net_dev" -gt 9 ]; then          #10 or more use bash prompt
    clear
    echo "Network Devices detected: ${device_list[*]}"
    echo -n "Select Network Device to use for DNS queries: "
    read -r choice
    NETWORK_DEVICE=$choice
    echo
  fi

  if [[ -z $NETWORK_DEVICE ]]; then                        #Final confirmation
    error_exit "Network Device not entered, unable to proceed" 11
  fi
}

#######################################
# Attempt to find hostname of system
#
# Globals:
#   HOSTNAME
# Arguments:
#   None
# Returns:
#   None
#######################################
function get_hostname() {
  if [[ -n $HOSTNAME ]]; then                              #Check if HOSTNAME is not null
    return
  fi

  if [ -e /etc/sysconfig/network ]; then                   #Get first entry for localhosts
    HOSTNAME=$(grep "HOSTNAME" /etc/sysconfig/network | cut -d "=" -f 2 | tr -d [[:space:]])
  elif [ -e /etc/hostname ]; then
    HOSTNAME=$(cat /etc/hostname)
  else
    echo "get_hostname: WARNING - Unable to find hostname"
  fi
}


#######################################
#  Disable Dnsmasq Stub
#   Disable Stub Listener in Dnsmasq systemd services
#
# Globals:
#   None
# Arguments:
#   None
# Returns:
#   None
#
#######################################
function disable_dnsmasq_stub() {
  local resolveconf="/etc/systemd/resolved.conf"

  if [ "$(command -v systemctl)" ]; then                   #Only relevant for systemd
    if [ -e "$resolveconf" ]; then                         #Does resolve.conf file exist?
      echo "Disabling Systemd DNS stub resolver"
      echo "Setting DNSStubListener=no in $resolveconf"
      sudo sed -i "s/#DNSStubListener=yes/DNSStubListener=no/" "$resolveconf" &> /dev/null

      service_restart "systemd-resolved.service"
      service_restart "dnsmasq.service"
    fi
  fi
  echo "========================================================="
}

#######################################
# Installs deb packages using apt for Ubuntu / Debian based systems
#
# Globals:
#   None
# Arguments:
#   None
# Returns:
#   None
#######################################
function install_deb() {
  echo "Refreshing apt"
  sudo apt update
  echo

  echo "Preparing to install Deb packages..."
  sleep 2s
  echo "Installing dependencies"
  sleep 2s
  sudo apt -y install git unzip
  echo
  echo "Installing DNS Server Dnsmasq"
  sleep 2s
  sudo apt -y install dnsmasq
  echo
  echo "Installing MariaDB"
  sleep 2s
  sudo apt -y install mariadb-server
  echo
  echo "Installing Webserver Nginx"
  sleep 2s
  sudo apt -y install nginx
  echo
  echo "Creating snakeoil SSL cert"
  sudo apt -y install ssl-cert
  sudo make-ssl-cert generate-default-snakeoil
  echo
  echo "Installing PHP"
  sleep 2s
  sudo apt -y install memcached php-memcache php php-fpm php-curl php-mysql
  echo
  echo "Installing Python3"
  sleep 2s
  sudo apt -y install python3 python3-mysql.connector

  echo "Finished installing Deb packages"
  echo "========================================================="
  echo
}


#######################################
# Git Clone
#   Clone NoTrack using Git
# Globals:
#   INSTALL_LOCATION, NOTRACK_REPO, SUDO_REQUIRED
# Arguments:
#   None
# Returns:
#   None
#######################################
function git_clone() {
  echo "Downloading NoTrack using Git"

  if [ $SUDO_REQUIRED == true ]; then
    sudo git clone --depth=1 "$NOTRACK_REPO" "$INSTALL_LOCATION"
  else
    git clone --depth=1 "$NOTRACK_REPO" "$INSTALL_LOCATION"
  fi
  echo
}


#######################################
# Find the service name for the webserver
#
# Globals:
#   WEB_USER
# Arguments:
#   None
# Returns:
#   None
#######################################
function find_web_user() {
  if [[ -n $WEB_USER ]]; then                              #Check if WEB_USER is not null
    echo "Web service user already set to: $WEB_USER"
    return
  fi

  if getent passwd www-data &> /dev/null; then             #Ubuntu uses www-data
    WEB_USER="www-data"
  elif getent passwd nginx &> /dev/null; then              #Redhat uses nginx
    WEB_USER="nginx"
  elif getent passwd _nginx &> /dev/null; then             #Void uses _nginx
    WEB_USER="_nginx"
  elif getent passwd http &> /dev/null; then               #Arch uses http
    WEB_USER="http"
  else
    echo "Unable to find account for web service :-("
    echo "Check /etc/passwd for the web service account and then ammend \$WEB_USER value in this installer"
    exit 9
  fi

  echo "Web service is using $WEB_USER account"
}


#######################################
# Setup LocalHosts
#   Create initial entry in /etc/localhosts.list
# Globals:
#   INSTALL_LOCATION
# Arguments:
#   None
# Returns:
#   None
#######################################
function setup_localhosts() {
  local localhostslist="/etc/localhosts.list"

  create_file "$localhostslist"                            #Local host IPs

  if [[ -n $HOSTNAME ]]; then                              #Has a hostname been found?
    echo "Setting up your /etc/localhosts.list for Local Hosts"
    echo -e "127.0.0.1\t$HOSTNAME" | sudo tee -a "$localhostslis" &> /dev/null
  fi
}


#######################################
# Setup Dnsmasq
#   Copy custom config settings into dnsmasq.conf and create log file
#   Create initial entry in /etc/localhosts.list
# Globals:
#   INSTALL_LOCATION, LISTENIP, SERVERIP1, SERVERIP2, NETWORK_DEVICE
# Arguments:
#   None
# Returns:
#   None
#######################################
function setup_dnsmasq() {
  local dnsmasqconf="/etc/dnsmasq.conf"
  local serversconf="/etc/dnsmasq.d/servers.conf"

  echo "Configuring Dnsmasq"

  copy "$dnsmasqconf" "$dnsmasqconf.old"              #Backup old config
  create_folder "/etc/dnsmasq.d"                           #Issue #94 folder not created
  create_file "/var/log/notrack.log"                       #DNS logs storage
  set_ownership "/var/log/notrack.log" "dnsmasq" "root"
  set_permissions "/var/log/notrack.log" "664"

  #Copy config files modified for NoTrack
  echo "Copying Dnsmasq config files from $INSTALL_LOCATION to /etc/conf"
  copy "$INSTALL_LOCATION/conf/dnsmasq.conf" "$dnsmasqconf"

  #Create initial Server Config. Note settings can be changed later via web admin
  echo "Creating DNS Server Config $serversconf"
  create_file "$serversconf"                               #DNS Server Config

  echo "server=$SERVERIP1" | sudo tee -a "$serversconf" &> /dev/null
  echo "server=$SERVERIP2" | sudo tee -a "$serversconf" &> /dev/null
  echo "interface=$NETWORK_DEVICE" | sudo tee -a "$serversconf" &> /dev/null
  echo "listen-address=$LISTENIP" | sudo tee -a "$serversconf" &> /dev/null

  service_start "dnsmasq"
  service_restart "dnsmasq"

  echo "Setup of Dnsmasq complete"
  echo "========================================================="
  echo
  sleep 2s
}


#######################################
# Setup nginx config files
#   Find web service account
#   Copy NoTrack nginx config to /etc/nginx/sites-available/default
#   Find the version of PHP
#   Add PHP Version to the nginx config
#
# Globals:
#   INSTALL_LOCATION
# Arguments:
#   None
# Returns:
#   None
#######################################
function setup_nginx() {
  local phpinfo=""
  local phpver=""

  echo
  echo "Setting up nginx"

  find_web_user

  #Backup the old nginx default config
  rename_file "/etc/nginx/sites-available/default" "/etc/nginx/sites-available/default.old"
  #Replace the default nginx config
  copy "$INSTALL_LOCATION/conf/nginx.conf" "/etc/nginx/sites-available/nginx.conf"
  rename_file "/etc/nginx/sites-available/nginx.conf" "/etc/nginx/sites-available/default"

  #FastCGI server needs to contain the current PHP version
  echo "Finding version of PHP"
  phpinfo="$(php --version)"                               #Get info from php version

  #Perform a regex check to extract version number from PHP (x.y).z
  if [[ $phpinfo =~ ^PHP[[:space:]]([0-9]{1,2}\.[0-9]{1,2}) ]]; then
    phpver="${BASH_REMATCH[1]}"
    echo "Found PHP version $phpver"
    sudo sed -i "s/%phpver%/$phpver/" /etc/nginx/sites-available/default
  else
    echo "I can't find the PHP version :-( You will have to replace %phpver% in /etc/nginx/sites-available/default"
    sleep 8s
  fi

  service_start "php$phpver-fpm"
  service_start "nginx"

  echo "Setup of nginx complete"
  echo "========================================================="
  echo
  sleep 2s
}


#######################################
# Setup MariaDB
#   Setup user account and password (TODO) for Maria DB
# Globals:
#   DBUSER, DBPASSWORD, DBNAME
# Arguments:
#   None
# Returns:
#   None
#######################################
function setup_mariadb() {
  #local dbconfig="$INSTALL_LOCATION/admin/settings/dbconfig.php" FUTURE FEATURE
  local rootpass=""

  echo "Setting up MariaDB"

  #Create a random password
  #DBPASSWORD="$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 40 | head -n 1)"

  service_start "mariadb"

  echo "Creating User $DBUSER:"
  sudo mysql --user=root --password="$rootpass" -e "CREATE USER '$DBUSER'@'localhost' IDENTIFIED BY '$DBPASSWORD';"

  #Check to see if ntrk user has been added
  if [[ ! $(sudo mysql -sN --user=root --password="$rootpass" -e "SELECT User FROM mysql.user") =~ ntrk[[:space:]]root ]]; then
    error_exit "MariaDB command failed, have you entered incorrect root password?" "35"
  fi

  echo "Creating Database $DBNAME:"
  sudo mysql --user=root --password="$rootpass" -e "CREATE DATABASE $DBNAME;"

  echo "Setting privilages for ntrk user"
  sudo mysql --user=root --password="$rootpass" -e "GRANT ALL PRIVILEGES ON $DBNAME.* TO 'ntrk'@'localhost';"
  sudo mysql --user=root --password="$rootpass" -e "GRANT FILE ON *.* TO 'ntrk'@'localhost';"
  #GRANT INSERT, SELECT, DELETE, UPDATE ON database.* TO 'user'@'localhost' IDENTIFIED BY 'password';
  sudo mysql --user=root --password="$rootpass" -e "FLUSH PRIVILEGES;"

  # NOTE This feature will be enabled in NoTrack 0.9.7
  #add password to local dbconfig.php
  #touch "$dbconfig"
  #echo "<?php" > "$dbconfig"
  #echo "//Local MariaDB password generated at install" >> "$dbconfig"
  #echo "\$dbconfig->password = '$dbpassword';" >> "$dbconfig"
  #echo "?>" >> "$dbconfig"

  echo "========================================================="
  echo
}


#######################################
# Copy NoTrack web admin files
#
# Globals:
#   INSTALL_LOCATION, WEB_FOLDER, WEB_USER, HOSTNAME
# Arguments:
#   None
# Returns:
#   None
#######################################
function setup_webadmin() {
  local phpinfo=""
  local phpver=""

  echo "Copying webadmin files to $WEB_FOLDER"

  copy "$INSTALL_LOCATION/admin" "$WEB_FOLDER/admin"
  copy "$INSTALL_LOCATION/sink" "$WEB_FOLDER/sink"
  echo "$WEB_USER taking over $WEB_FOLDER"
  sudo chown "$WEB_USER":"$WEB_USER" -hR "$WEB_FOLDER"
  echo
}

#######################################
# Setup NoTrack
#   1. Create systemd service using template notrack.service
#   2. Initial run of blockparser
#
# Globals:
#   INSTALL_LOCATION, IP_VERSION, NETWORK_DEVICE
# Arguments:
#   None
# Returns:
#   None
#######################################
function setup_notrack() {
  copy "$INSTALL_LOCATION/init-scripts/notrack.service" "/etc/systemd/system"
  sudo sed -i "s:%install_location%:$INSTALL_LOCATION:g" "/etc/systemd/system/notrack.service"
  sudo systemctl enable --now notrack.service

  echo "Downloading and parsing blocklists"
  sleep 2s
  sudo python3 "$INSTALL_LOCATION/src/blockparser.py"
  echo
}


#######################################
# Welcome Screen
# Globals:
#   None
# Arguments:
#   None
# Returns:
#   None
#######################################
function show_welcome() {
  echo "Welcome to NoTrack v$VERSION"
  echo
  echo "This installer will transform your system into a network-wide Tracker Blocker"
  echo "Install Guides: https://youtu.be/MHsrdGT5DzE"
  echo "                https://github.com/quidsup/notrack/wiki"
  echo
  echo
  echo "Press any key to continue..."
  read -rn1
}


#######################################
# Finish Screen
# Globals:
#   INSTALL_LOCATION, REBOOT_REQUIRED, HOSTNAME
# Arguments:
#   None
# Returns:
#   None
#######################################
function show_finish() {
  echo "========================================================="
  echo
  echo -e "NoTrack Install Complete :-)"
  echo "Access the admin console at: http://$HOSTNAME/admin"
  echo
  echo "Post Install Checklist:"
  echo -e "\t\u2022 Secure MariaDB Installation"
  echo -e "\t    Run: /usr/bin/mysql_secure_installation"
  echo
  echo -e "\t\u2022 Enable DHCP"
  echo -e "\t    http://$HOSTNAME/dhcp"
  echo
  echo
  echo "========================================================="
  echo
}


#######################################
# Main
#######################################
if [[ $(command -v sudo) == "" ]]; then          #Is sudo available?
  error_exit "NoTrack requires Sudo to be installed for Admin functionality" "10"
fi

show_welcome
get_hostname
prompt_installloc
prompt_network_device

clear
echo "Installing to : $INSTALL_LOCATION"          #Final report before Installing
echo "Hostname      : $HOSTNAME"
echo "Network Device: $NETWORK_DEVICE"
echo "Primary DNS   : $SERVERIP1"
echo "Secondary DNS : $SERVERIP2"
echo "Listening IP  : $LISTENIP"
echo
echo "Note: Primary and Secondary DNS can be changed later with the admin config"
echo

seconds=$((6))
while [ $seconds -gt 0 ]; do
   echo -ne "$seconds\033[0K\r"
   sleep 1
   : $((seconds--))
done

install_deb

git_clone
setup_localhosts
setup_dnsmasq
disable_dnsmasq_stub
setup_nginx
setup_mariadb
setup_webadmin
setup_notrack
show_finish
