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
    ntrk_pause = ''
    ntrk_upgrade = ''
    temp = ''
    wwwsink = ''

    def __init__(self):
        if os.name == 'posix':
            self.cron_ntrkparse = '/etc/cron.d/ntrk-parse'
            self.etc = '/etc/'
            self.etc_notrack = '/etc/notrack/'
            self.log = '/var/log/'
            self.notrack = '/usr/local/sbin/notrack'
            self.ntrk_pause = '/usr/local/sbin/ntrk-pause'
            self.ntrk_upgrade = '/usr/local/sbin/ntrk-upgrade'
            self.temp = '/tmp/'
            self.wwwsink = '/var/www/html/sink/'


class Host:
    name = ''
    ip = ''

    def __init__(self):
        import socket

        self.name = socket.gethostname()                   #Host Name is easy to get

        #IP requires a connection out
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        #Connect to an unroutable 169.254.0.0/16 address on port 1
        s.connect(("169.254.0.255", 1))
        self.ip = s.getsockname()[0]
        s.close()



#Services is a class for identifing Service Supervisor, Web Server, and DNS Server
#Restarting the Service will use the appropriate Service Supervisor
class Services:
    __supervisor = ''
    __supervisor_name = ''
    __webserver = ''
    __dnsserver = ''
    dhcp_config = ''

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
            self.dhcp_config = '/etc/dnsmasq.d/dhcp.conf'
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
        p = subprocess.run(['sudo', self.__supervisor, 'restart', service], stderr=subprocess.PIPE, universal_newlines=True)

        if p.returncode != 0:
            print('Services restart_service: Failed to restart %s' % service)
            print(p.stderr)
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


""" Copy File
    1. Check source exists
    2. Set ownership to root
    3. Set permissions to 644

Args:
    source
    destination
Returns:
    True on success
    False on failure
"""
def copy_file(source, destination):
    if not os.path.isfile(source):
        print('Copy_file: Error %s is missing' % source)
        return False

    #Set ownership to root
    print('Setting ownership of %s to root:root' % source)
    shutil.chown(source, user='root', group='root')

    #Set permissions to RW R R'
    print('Setting permissions of %s to 644' % source)
    os.chmod(source, 0o644)

    #Copy specified file
    print('Moving %s to %s' % (source, destination))
    shutil.move(source, destination)

    if not os.path.isfile(destination):
        print('Copy_file: Error %s does not exist. Copy failed')
        return False

    return True


""" Copy DHCP Config
    1: Check dhcp.conf exists in /tmp
    2: Change ownership and permissions
    3: Copy to /etc/dnsmasq.d/dhcp.conf
    4: Restart Dnsmasq
Args:
    Interval in minutes
Returns:
    None
"""
def copy_dhcp():
    dhcp_config = 'dhcp.conf'
    services = Services()

    #Has the DHCP config file been set when services were discovered?
    if services.dhcp_config == '':
        print('Copy_dhcp: Error - This function only works with Dnsmasq')
        return

    copy_file(folders.temp + dhcp_config, services.dhcp_config)

    services.restart_dnsserver()


""" Copy List
    Copies either black or white list from temp folder to /etc/notrack
    Start a copy of notrack in wait mode
    This will allow user time to make further changes before lists are updated
    If there is a copy of notrack waiting the forked process will be closed

Args:
    list name
    runnotrack - Execute NoTrack in wait mode
Returns:
    None
"""
def copy_list(listname):
    copy_file(folders.temp + listname, folders.etc_notrack + listname)

    #Run notrack in delayed (wait) mode
    run_notrack('wait')


""" Copy TLD Lists
    Copy both TLD black and white list from temp folder to /etc/notrack
Args:
    None
Returns:
    None
"""
def copy_tldlists():
    copy_file(folders.temp + 'domain-blacklist.txt', folders.etc_notrack + 'domain-blacklist.txt')
    copy_file(folders.temp + 'domain-whitelist.txt', folders.etc_notrack + 'domain-whitelist.txt')

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



""" Save Static Hosts Config
    1: Check localhosts.list exists in /tmp
    2: Change ownership and permissions
    3: Copy to /etc/dnsmasq.d/dhcp.conf

Args:
    None
Returns:
    None
"""
def copy_localhosts():
    localhosts_temp = ''
    host = Host()

    localhosts_temp = folders.temp + 'localhosts.list'

    #Check temp file exists
    if not os.path.isfile(localhosts_temp):
        print('Copy_localhosts: Error %s is missing' % localhosts_temp)
        return

    #Check the Host Name and IP have been found TODO probably 127.0.0.1 for unknown?
    if host.name != '' and host.ip != '':
        print('Adding %s\t%s to localhosts.list' % (host.ip, host.name))
        f = open(localhosts_temp, 'a')                     #Open file for appending
        print('%s\t%s' % (host.ip, host.name), file=f)
        f.close()
        print()

    copy_file(localhosts_temp, folders.etc + 'localhosts.list')



""" NoTrack Pause
    1. Check ntrk-pause exists
    2. Run NoTrack

Args:
    None
Returns:
    None
"""
def ntrk_pause(mode, duration=0):
    from shlex import quote
    if not os.path.isfile(folders.ntrk_pause):             #Does ntrk-pause exist?
        print('Ntrk_pause: Error %s is missing' % folders.ntrk_pause)
        sys.exit(24)

    if mode == 'pause':
        #Fork process of ntrk-pause into background
        print('Launching ntrk-pause with Pause mode, duration %d' % duration)
        #subprocess.Popen([folders.ntrk_pause, '--pause 1'], stdout=subprocess.PIPE, shell=True)
        dur = str(duration)
        os.system(folders.ntrk_pause + ' --pause ' + dur + ' > /dev/null &')

    else:
        #Fork process of ntrk-pause --mode into background
        print('Launching ntrk-pause with %s mode' % mode)
        print()
        process = subprocess.run([folders.ntrk_pause, '--' + mode], stdout=subprocess.PIPE, stderr=subprocess.PIPE, universal_newlines=True)
        print(process.stdout)                                  #Show the terminal output
        #print(process)
        #subprocess.Popen([folders.ntrk_pause, ' --' + mode], stdout=subprocess.PIPE )



""" Run NoTrack
    1. Check NoTrack exists
    2. Run NoTrack

Args:
    None
Returns:
    None
"""
def run_notrack(mode=''):
    if not os.path.isfile(folders.ntrk_upgrade):           #Does ntrk-upgrade exist?
        print('Upgrade_notrack: Error %s is missing' % folders.ntrk_upgrade)
        sys.exit(24)

    if mode == '':
        #Fork process of notrack into background
        print('Launching NoTrack')
        subprocess.Popen(folders.notrack, stdout=subprocess.PIPE )
    else:
        #Fork process of notrack --mode into background
        print('Launching NoTrack with %s mode' % mode)
        subprocess.Popen([folders.notrack, '--' + mode], stdout=subprocess.PIPE )




""" Upgrade NoTrack
    1. Check ntrk-upgrade exists
    2. Run and wait for ntrk-upgrade to complete
    3. Print the output of ntrk-upgrade
    4. Check for errors

Args:
    None
Returns:
    None
"""
def upgrade_notrack():
    if not os.path.isfile(folders.notrack):           #Does ntrk-upgrade exist?
        print('Upgrade_notrack: Error %s is missing' % folders.ntrk_upgrade)
        sys.exit(20)

    process = subprocess.run([folders.ntrk_upgrade], stdout=subprocess.PIPE, stderr=subprocess.PIPE, universal_newlines=True)

    print(process.stdout)                                  #Show the terminal output

    if process.returncode != 0:                            #Check return code
        print('Upgrade_notrack: Error with upgrade')
        print(process.stderr)                              #TODO no such functionality yet

"""

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

      --restart)
        reboot > /dev/null &
      ;;
      -s)                                        #Stop
        /usr/local/sbin/ntrk-pause --stop  > /dev/null &
      ;;
      --shutdown)
        shutdown now  > /dev/null &
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
    if args.save == 'conf':
        copy_file(folders.temp + 'notrack.conf', folders.etc_notrack + 'notrack.conf')
    elif args.save == 'black':
        copy_list('blacklist.txt')
    elif args.save == 'white':
        copy_list('whitelist.txt')
    elif args.save == 'dhcp':
        copy_dhcp()
    elif args.save == 'localhosts':
        copy_localhosts()
    elif args.save == 'tld':
        copy_tldlist()

if args.sink:
    block_message(args.sink)

if args.parsing:
    parsing_time(args.parsing)

if args.play:
    ntrk_pause('start')
if args.pause:
    ntrk_pause('pause', args.pause)
if args.stop:
    ntrk_pause('stop')

if (args.deletehistory):
    print("del")

if args.run:
    run_notrack()
if args.force:
    run_notrack('force')

if args.upgrade:
    upgrade_notrack()