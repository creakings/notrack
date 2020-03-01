#!/usr/bin/env python3
#Title : NoTrack Exec
#Description : NoTrack Exec carries out certain jobs parsed from www-data user
#              and then runs them as root user
#Author : QuidsUp
#Original Bash version : 2015-02-02
#Python3 version: 2020-02-28
#Usage : ntrk-exec [command]

import argparse
import os
import shutil
import stat
import subprocess
import sys


class DBConfig:
    user = 'ntrk'
    password = 'ntrkpass'
    dbname = 'ntrkdb'

class FolderList:
    cron_ntrkparse = ''
    etc = ''
    etc_notrack = ''
    log = ''
    notrack = ''
    temp = ''
    wwwsink = ''

    def __init__(self):
        if os.name == 'posix':
            self.cron_ntrkparse = '/etc/cron.d/ntrk-parse'
            self.etc = '/etc/'
            self.etc_notrack = '/etc/notrack'
            self.log = '/var/log/'
            self.notrack = '/usr/local/sbin/notrack'
            self.temp = '/tmp/'
            self.wwwsink = '/var/www/html/sink/'


#Services is a class for identifing Service Supervisor, Web Server, and DNS Server
#Restarting the Service will use the appropriate Service Supervisor
class Services:
    __supervisor = ''
    __supervisor_name = ''
    __webserver = ''
    __dnsserver = ''

    def __init__(self):
        #Find service supervisor by checking if each application exists
        if shutil.which('systemctl') != None:
            self.__supervisor = 'systemctl'
            self.__supervisor_name = 'systemd'
        elif shutil.which('service') != None:
            self.__supervisor = 'service'
            self.__supervisor_name = 'systemctl'
        elif shutil.which('sv') != None:
            self.__supervisor = 'sv'
            self.__supervisor_name = 'ruinit'
        else:
            print('Services Init: Fatal Error - Unable to identify service supervisor')
            sys.exit(7)
        print('Services Init: Identified Service manager %s' % self.__supervisor_name)

        #Find DNS server by checking if each application exists
        if shutil.which('dnsmasq') != None:
            self.__dnsserver = 'dnsmasq'
        elif shutil.which('bind') != None:
            self.__dnsserver = 'bind'
        else:
            print('Services Init: Fatal Error - Unable to identify DNS server')
            sys.exit(8)
        print('Services Init: Identified DNS server %s' % self.__dnsserver)

        #Find Web server by checking if each application exists
        if shutil.which('lighttpd') != None:
            self.__webserver = 'lighttpd'
        elif shutil.which('apache') != None:
            self.__webserver = 'apache'
        else:
            print('Services Init: Fatal Error - Unable to identify Web server')
            sys.exit(9)
        print('Services Init: Identified Web server %s' % self.__webserver)


    """ Restart Service
        Restart specified service and return code
    Args:
        service to restart
    Returns:
        True on Success (return code zero)
        False on Failure (return code non-zero)
    """
    def __restart_service(self, service):
        p = subprocess.run(['sudo', self.__supervisor, 'restart', service], stderr=subprocess.PIPE)

        if p.returncode != 0:
            print('Services restart_service: Failed to restart %s' % service)
            print(p.stderr)                                #TODO: Bad formatting output
            return False
        else:
            print('Successfully restarted %s' % service)
            return True

    #Restart DNS Server - returns the result of restart_service
    def restart_dnsserver(self):
        return self.__restart_service(self.__dnsserver)

    #Restart Web Server - returns the result of restart_service
    def restart_webserver(self):
        return self.__restart_service(self.__webserver)


dbconf = DBConfig()
folders = FolderList()
services = Services()

services.restart_dnsserver()
"""

#######################################
# Constants
#######################################
readonly ACCESSLOG="/var/log/ntrk-admin.log"
readonly FILE_CONFIG="/etc/notrack/notrack.conf"
readonly TEMP_CONFIG="/tmp/notrack.conf"
readonly DNSMASQ_CONF="/etc/dnsmasq.conf"
readonly DHCP_CONFIG="/etc/dnsmasq.d/dhcp.conf"
readonly LOCALHOSTS="/etc/localhosts.list"

"""

""" Block Message
Sets Block message for sink page
    1. Output required data into wwwsink/index.html'
    2. Find which group is running the webserver (http or www-user)
    3. Set ownership of the file for relevant group
    4. Set file permissions to 774
Args:
    Message
Returns:
    None
"""
def block_message(msg):
    import grp

    groupname = ''

    print('Opening %sindex.html for writing' % folders.wwwsink)

    f = open(folders.wwwsink + 'index.html', 'w')
    if msg == 'message':
        print('Setting Block message Blocked by NoTrack')
        f.write('<p>Blocked by NoTrack</p>')
    elif msg == 'pixel':
        print('Setting Block message to pixel')
        f.write('<img src="data:image/gif;base64,R0lGODlhAQABAAAAACwAAAAAAQABAAA=" alt="" />')

    f.close()                                              #Close index.html


    #Find the group name. Arch uses http, other distros use www-data
    print ('Finding group name for webserver')

    try:
        grp.getgrnam('www-data')
    except KeyError:
        print('No www-data group')
    else:
        groupname = 'www-data'
        print('Found group www-data')

    try:
        grp.getgrnam('http')
    except KeyError:
        print('No http group')
    else:
        groupname = 'http'
        print('Found group http')

    #Set ownership of index.html
    print('Setting ownership of %sindex.html to %s' % (folders.wwwsink, groupname))
    shutil.chown(folders.wwwsink + 'index.html', user=groupname, group=groupname)

    #Set permissions of index.html
    if os.name == 'posix':
        print('Setting permissions of %sindex.html to 775' % folders.wwwsink)
        os.chmod(folders.wwwsink + 'index.html', stat.S_IRWXU | stat.S_IRWXG | stat.S_IROTH)
    else:
        print('Setting permissions of %sindex.html to Archive' % folders.wwwsink)
        os.chmod(folders.wwwsink + 'index.html', stat.FILE_ATTRIBUTE_ARCHIVE)

"""
#--------------------------------------------------------------------
# Create File
# Checks if a file exists and creates it
#
# Globals:
#   None
# Arguments:
#   #$1 File to create
# Returns:
#   None
#--------------------------------------------------------------------
function create_file() {
  if [ ! -e "$1" ]; then                         #Does file already exist?
    echo "Creating file: $1"
    sudo touch "$1"                              #If not then create it
    sudo chmod 664 "$1"                          #RW RW R permissions
  fi
}

"""


""" Save List
    Copies a specified list from temp folder to /etc/notrack
    Start a copy of notrack in wait mode (optional)
    This will allow user time to make further changes before lists are updated
    If there is a copy of notrack waiting the forked process will be closed

Args:
    filename - list filename
    runnotrack - Execute NoTrack in wait mode
Returns:
    None
"""
def save_list(filename, runnotrack):
    if os.path.isfile(folders.temp + filename):
        #Set ownership to root
        print('Setting ownership of %s%s to root:root' % (folders.temp, filename))
        shutil.chown(folders.temp + filename, user='root', group='root')

        #Set permissions to RW R R'
        print('Setting permissions of %s%s to 644' % (folders.temp, filename))
        os.chmod(folders.temp + filename, 0o644)

        #Copy specified file
        print('Copying %s from %s to %s' % (filename, folders.temp, folders.etc_notrack))
        shutil.copy(folders.temp + filename, folders.etc_notrack + filename)

        #Optional - run notrack in delayed (wait) mode
        if runnotrack:
            print('Running NoTrack with delay mode enabled')
            #Fork process of notrack --wait into background
            subprocess.Popen([folders.notrack, ' --wait'], stdout=subprocess.PIPE )

    else:
      print('%s%s is missing' % (folders.temp, filename))


"""
#--------------------------------------------------------------------
# Create Access Log
#
# Globals:
#   ACCESSLOG
# Arguments:
#   None
# Returns:
#   None
#--------------------------------------------------------------------
function create_accesslog() {
  if [ ! -e "$ACCESSLOG" ]; then
    echo "Creating $ACCESSLOG"
    touch "$ACCESSLOG"
    chmod 666 "$ACCESSLOG"
  fi
}


#--------------------------------------------------------------------
# Delete History
#
# Globals:
#   None
# Arguments:
#   None
# Returns:
#   None
#--------------------------------------------------------------------
delete_history() {
  echo "Deleting contents of dnslog and weblog tables"
mysql --user="$USER" --password="$PASSWORD" -D "$DBNAME" << EOF
DELETE LOW_PRIORITY FROM dnslog;
ALTER TABLE dnslog AUTO_INCREMENT = 1;
DELETE LOW_PRIORITY FROM weblog;
ALTER TABLE weblog AUTO_INCREMENT = 1;
EOF
  #echo "Deleting Log Files in /var/log/lighttpd" DEPRECATED
  #rm /var/log/lighttpd/*                         #Delete all files in lighttpd log folder
  #touch /var/log/lighttpd/access.log             #Create new access log and set privileges
  #chown www-data:root /var/log/lighttpd/access.log
  #chmod 644 /var/log/lighttpd/access.log
  #touch /var/log/lighttpd/error.log              #Create new error log and set privileges
  #chown www-data:root /var/log/lighttpd/error.log
  #chmod 644 /var/log/lighttpd/error.log
}


#--------------------------------------------------------------------
# Add Value
#   Add Value to SQL Config Table
# Globals:
#   None
# Arguments:
#   1: type
#   2: option_name
#   3: option_value
#   4: option_enabled (# = false, 1 = true)
# Returns:
#   None
#--------------------------------------------------------------------

"""

""" Parsing Time
    Update CRON parsing interval for ntrk-parse
Args:
    Interval in minutes
Returns:
    None
"""
def parsing_time(interval):
    print('Updating parsing time')

    #interval=$(grep "ParsingTime" "$FILE_CONFIG")  #Load single value from Config
    if interval < 0 or interval > 99:
        print('Warning: Invalid interval specified')
        return

    print('Setting job interval of %d minutes in %s' % (interval, folders.cron_ntrkparse))

    f = open(folders.cron_ntrkparse, 'w')
    print('*/%d * * * *\troot\t/usr/local/sbin/ntrk-parse' % interval, file=f)
    f.close()                                              #Close cron_ntrkparse file


"""
#--------------------------------------------------------------------
# Write Dhcp Config
#   1: Check dhcp.conf exists in /tmp
#   2: Change ownership and permissions
#   3: Copy to /etc/dnsmasq.d/dhcp.conf
#   4: Restart Dnsmasq TODO
#
# Globals:
#   DHCP_CONFIG
# Arguments:
#   None
# Returns:
#   None

#--------------------------------------------------------------------
function write_dhcp() {
  local dhcp_temp="/tmp/dhcp.conf"

  if [ -e "$dhcp_temp" ]; then
    chown root:root "$dhcp_temp"
    chmod 644 "$dhcp_temp"
    echo "Copying $dhcp_temp to $DHCP_CONFIG"
    mv "$dhcp_temp" "$DHCP_CONFIG"
    echo
    service_restart dnsmasq
  fi
}



#--------------------------------------------------------------------
# Write Static Hosts Config
#   1: Check localhosts.list exists in /tmp
#   2: Change ownership and permissions
#   3: Copy to /etc/dnsmasq.d/dhcp.conf
#   4: Restart Dnsmasq TODO
#
# Globals:
#   LOCALHOSTS
# Arguments:
#   None
# Returns:
#   None

#--------------------------------------------------------------------
function write_localhosts() {
  local localhosts_temp="/tmp/localhosts.list"
  local hostname=""
  local hostip=""

  #Retrive this system from localhosts file
  if [ -e /etc/sysconfig/network ]; then
    hostname=$(grep "HOSTNAME" /etc/sysconfig/network | cut -d "=" -f 2 | tr -d [[:space:]])
  elif [ -e /etc/hostname ]; then
    hostname=$(cat /etc/hostname)
  fi

  #Get the full line from localhosts if the hostname has been found
  if [[ -n $hostname ]]; then
    hostip=$(grep "$hostname" "$LOCALHOSTS")
  fi

  #Check temp file exists
  if [ -e "$localhosts_temp" ]; then
    chown root:root "$localhosts_temp"
    chmod 644 "$localhosts_temp"
    echo "Copying $localhosts_temp to $LOCALHOSTS"
    mv "$localhosts_temp" "$LOCALHOSTS"

    #Add the full line of hostip and hostname to the end of localhosts
    if [[ -n $hostip ]]; then
      echo "Adding $hostip back to $LOCALHOSTS"
      echo "$hostip" | sudo tee -a "$LOCALHOSTS" &> /dev/null
    fi
  fi
}



#--------------------------------------------------------------------
# Write Dnsmasq Config
#
# Globals:
#   USER, PASSWORD, DBNAME
# Arguments:
#   None
# Returns:
#   None
#--------------------------------------------------------------------
function write_dnsmasq() {
  echo "here"
}


#--------------------------------------------------------------------
# Update Config
#
# Globals:
#   FILE_CONFIG, TEMP_CONFIG
# Arguments:
#   None
# Returns:
#   None
#--------------------------------------------------------------------
function update_config() {
  if [ -e "/tmp/notrack.conf" ]; then
    chown root:root "$TEMP_CONFIG"
    chmod 644 /tmp/notrack.conf
    echo "Copying $TEMP_CONFIG to $FILE_CONFIG"
    mv "$TEMP_CONFIG" "$FILE_CONFIG"
    echo
  fi
}


#--------------------------------------------------------------------
# Upgrade NoTrack
#
# Globals:
#   None
# Arguments:
#   None
# Returns:
#   None
#--------------------------------------------------------------------
function upgrade_notrack() {
  if [ -e /usr/local/sbin/ntrk-upgrade ]; then
    echo "Running NoTrack Upgrade"
    sudo /usr/local/sbin/ntrk-upgrade #2>&1
  else
    echo "NoTrack Upgrade is missing, using fallback notrack.sh"
    sudo /usr/local/sbin/notrack -u
  fi
}


#Main----------------------------------------------------------------
if [[ "$(id -u)" != "0" ]]; then                 #Check if running as root
  echo "Error this script must be run as root"
  exit 2
fi



if [ "$1" ]; then                         #Have any arguments been given
  if ! Options=$(getopt -o hps -l accesslog,bm-msg,bm-pxl,delete-history,force,parsing,run-notrack,restart,save-conf,shutdown,upgrade,read:,write:,pause:,copy: -- "$@"); then
    # something went wrong, getopt will put out an error message for us
    exit 1
  fi

  set -- $Options

  while [ $# -gt 0 ]
  do
    case $1 in
      esslog)
        create_accesslog
      ;;

      --copy)
        if [[ $2 == "'black'" ]]; then
          copy_blacklist
        elif [[ $2 == "'white'" ]]; then
          copy_whitelist
        elif [[ $2 == "'tld'" ]]; then
          copy_tldlists
        else
          echo "Invalid file"
        fi
      ;;
      --delete-history)
        delete_history
      ;;
      --force)
        /usr/local/sbin/notrack --force > /dev/null &
      ;;
      -p)                                        #Play
        /usr/local/sbin/ntrk-pause --start  > /dev/null &
      ;;

      --pause)
        pausetime=$(sed "s/'//g" <<< "$2")       #Remove single quotes
        echo "$pausetime"
        /usr/local/sbin/ntrk-pause --pause "$pausetime"  > /dev/null &
      ;;
      --read)
        if [[ $2 == "'dnsmasq'" ]]; then
          read_dnsmasq
        elif [[ $2 == "'dhcp'" ]]; then
          read_dhcp
        fi
      ;;
      --restart)
        reboot > /dev/null &
      ;;
      -s)                                        #Stop
        /usr/local/sbin/ntrk-pause --stop  > /dev/null &
      ;;
      --shutdown)
        shutdown now  > /dev/null &
      ;;
      --run-notrack)
        /usr/local/sbin/notrack > /dev/null &
      ;;
      --save-conf)
        update_config
      ;;
      --upgrade)
        upgrade_notrack
      ;;
      --write)
        if [[ $2 == "'dnsmasq'" ]]; then
          write_dnsmasq
        elif [[ $2 == "'dhcp'" ]]; then
          write_dhcp
        elif [[ $2 == "'localhosts'" ]]; then
          write_localhosts
        fi
      ;;
      (--) shift; break;;
      (-*) echo "$0: error - unrecognized option $1" 1>&2; exit 6;;
      (*) break;;
    esac
    shift
  done
else
  echo "No arguments given"
fi
"""
parser = argparse.ArgumentParser(description = 'NoTrack Exec:')
parser.add_argument('-p', "--play", help='Start Blocking', action='store_true')
parser.add_argument('-s', "--stop", help='Stop Blocking', action='store_true')
parser.add_argument('--pause', help='Pause Blocking', type=int)
parser.add_argument('--accesslog', help='Create Access log file', action='store_true')
parser.add_argument("--deletehistory", help='Message on Sink Page', action='store_true')
parser.add_argument('--force', help='Force run NoTrack', action='store_true')
parser.add_argument('--run', help='Run NoTrack', action='store_true')
parser.add_argument('--parsing', help='Parser update time', type=int)
parser.add_argument('--restart', help='Restart System', action='store_true')
parser.add_argument('--shutdown', help='Shutdown System', action='store_true')
parser.add_argument('--upgrade', help='Upgrade NoTrack', action='store_true')
parser.add_argument("--save", help='Replace specified file', choices=['conf', 'dhcp', 'localhosts', 'black', 'white', 'tld'])
parser.add_argument("--sink", help='Block Message on Sink page', choices=['message', 'pixel'])

args = parser.parse_args()

if args.save:
    if args.save == 'black':
        save_list('blacklist.txt', True)
    elif args.save == 'white':
        save_list('whitelist.txt', True)
    elif args.save == 'tld':
        save_list('domain-blacklist.txt', False)
        save_list('domain-whitelist.txt', False)
if args.sink:
    block_message(args.sink)

if args.parsing:
    parsing_time(args.parsing)

if (args.play):
    print('Play')
if (args.pause):
    print('Pause %d' % args.pause)
if (args.deletehistory):
    print("del")
