#!/usr/bin/bash
#Title : NoTrack Uninstaller
#Description : This script removes the files NoTrack created, and then return dnsmasq and lighttpd to their default configuration
#Author : QuidsUp
#Usage : sudo bash uninstall.sh


#######################################
# User Configerable Settings
#######################################
readonly FOLDER_SBIN="/usr/local/sbin"                     #User configurable
readonly FOLDER_ETC="/etc"                                 #User configurable


#######################################
# Constants
#######################################
readonly USER="ntrk"
readonly PASSWORD="ntrkpass"
readonly DBNAME="ntrkdb"


#######################################
# Global Variables
#######################################
INSTALL_LOCATION="$HOME/notrack"


#######################################
# Delete SQL Tables
#
# Globals:
#   USER, PASSWORD, DBNAME
# Arguments:
#   None
# Returns:
#   None
#
#######################################
function delete_tables() {
  echo "Deleting MariaDB tables"
mysql --user="$USER" --password="$PASSWORD" -D "$DBNAME" << EOF
DROP TABLE IF EXISTS blocklist, dnslog, weblog, config, users, whois, analytics;
DROP DATABASE ntrkdb;
EOF

}

#######################################
# Stop service
#   with either systemd or sysvinit or runit
# Globals:
#   None
# Arguments:
#   $1. Service name
# Returns:
#   None
#
#######################################
function service_stop() {
  if [[ -n $1 ]]; then
    echo "Stopping $1"
    if [ "$(command -v systemctl)" ]; then     #systemd
      sudo systemctl stop "$1"
    elif [ "$(command -v service)" ]; then     #sysvinit
      sudo service "$1" stop
    elif [ "$(command -v sv)" ]; then          #runit
      sudo sv down "$1"
    else
      echo "Unable to stop services. Unknown service supervisor"
      exit 21
    fi
  fi
}


#######################################
# Copy File
#   Copies source file to destination if it exists
# Globals:
#   None
# Arguments:
#   $1. Source
#   $2. Target
# Returns:
#   None
#
#######################################
function copy_file() {
  if [ -e "$1" ]; then                                     #Check file exists
    echo "Copying $1 to $2"
    cp "$1" "$2"
  else
    echo "File $1 not found"
  fi
}


#######################################
# Delete File
#   Deletes a file if it exists
# Globals:
#   None
# Arguments:
#   $1. Source
# Returns:
#   None
#
#######################################
function delete_file() {
  if [ -e "$1" ]; then                                     #Check file exists
    echo "Deleting file $1"
    rm "$1"
  fi
}


#######################################
# Delete Folder
#  Deletes a folder if it exists
# Globals:
#   None
# Arguments:
#   $1. Source
# Returns:
#   None
#
#######################################
delete_folder() {
  if [ -d "$1" ]; then                                     #Check folder exists
    echo "Deleting folder $1"
    rm -rf "$1"
  fi
}


#######################################
# Find NoTrack
#   This function finds where NoTrack is installed
#   1. Check current folder
#   2. Check users home folders
#   3. Check /opt/notrack
#   4. If not found then abort
#
# Globals:
#   INSTALL_LOCATION
# Arguments:
#   None
# Returns:
#   1 if found
#
#######################################
function find_notrack() {
  local homefolders=""

  if [ -e "$(pwd)/notrack.sh" ]; then                      #Check current folder
    INSTALL_LOCATION="$(pwd)"
    return 1
  fi

  for homefolders in /home/*; do                           #Check all folders under /home
    if [ -d "$homefolders/NoTrack" ]; then 
      INSTALL_LOCATION="$homefolders/NoTrack"
      break
    elif [ -d "$homefolders/notrack" ]; then 
      INSTALL_LOCATION="$homefolders/notrack"
      break
    fi
  done

  if [[ $INSTALL_LOCATION == "" ]]; then                   #Check /opt
    if [ -d "/opt/notrack" ]; then
      INSTALL_LOCATION="/opt/notrack"
    else
      echo "Error Unable to find NoTrack folder"
      echo "When NoTrack was installed in a custom location please specify it in uninstall.sh"
      echo "Aborting"
      exit 22
    fi
  fi

  return 1
}


#Main----------------------------------------------------------------
find_notrack                                               #Where is NoTrack located?

if [[ "$(id -u)" != "0" ]]; then
  echo "Root access is required to uninstall NoTrack"
  echo "Usage: sudo bash uninstall.sh"
  exit 5
  #su -c "$0" "$@" - This could be an alternative for systems without sudo
fi

echo "This script will remove the files created by NoTrack, and then returns dnsmasq and lighttpd to their default configuration"
echo "NoTrack Installation Folder: $INSTALL_LOCATION"
echo
read -p "Continue (Y/n)? " -n1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
  echo "Aborting"
  exit 1
fi


service_stop dnsmasq
service_stop lighttpd
echo

echo "Deleting Symlinks for Web Folders"
echo "Deleting Sink page"
delete_file "/var/www/html/sink/index.html"
delete_folder "/var/www/html/sink"
echo "Deleting Admin symlink"
delete_file "/var/www/html/admin"
echo

echo "Restoring Configuration files"
echo "Restoring Dnsmasq config"
copy_file "/etc/dnsmasq.conf.old" "/etc/dnsmasq.conf"
echo "Restoring Lighttpd config"
copy_file "/etc/lighttpd/lighttpd.conf.old" "/etc/lighttpd/lighttpd.conf"
echo "Removing Local Hosts file"
delete_file "/etc/localhosts.list"
echo

echo "Removing Cron jobs"
delete_file "/etc/cron.d/ntrk-parse"                       #Remove old symlinks
delete_file "/etc/cron.daily/notrack"
delete_file "/etc/cron.hourly/ntrk-analytics"
echo

echo "Deleting NoTrack scripts"
delete_file "$FOLDER_SBIN/notrack"
delete_file "$FOLDER_SBIN/ntrk-analytics"
delete_file "$FOLDER_SBIN/ntrk-exec"
delete_file "$FOLDER_SBIN/ntrk-pause"
delete_file "$FOLDER_SBIN/ntrk-parser"
echo

echo "Removing root permissions for www-data to launch ntrk-exec"
sed -i '/www-data/d' /etc/sudoers

echo "Deleting /etc/notrack Folder"
delete_folder "$FOLDER_ETC/notrack"
echo 

echo "Deleting Install Folder"
delete_folder "$INSTALL_LOCATION"
echo

echo "Finished deleting all files"
echo

delete_tables

echo "The following packages will also need removing:"
echo -e "\tdnsmasq"
echo -e "\tlighttpd"
echo -e "\tmariadb-server"
echo -e "\tphp"
echo -e "\tphp-cgi"
echo -e "\tphp-curl"
echo -e "\tphp-memcache"
echo -e "\tphp-mysql"
echo -e "\tmemcached"
echo
