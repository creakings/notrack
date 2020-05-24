#!/usr/bin/env python3
#Title  : NoTrack Exec
#Description: NoTrack Exec carries out certain jobs parsed from www-data user
#             and then runs them as root user
#Depends: python3-mysql.connector
#Author : QuidsUp
#Created: 2020-02-28
#Version: 0.9.5
#Usage  : ntrk-exec [command]

#Standard imports
import argparse
import os
import shutil
import stat
import subprocess
import sys

#Local imports
from ntrkfolders import FolderList
from ntrkservices import Services
from ntrkshared import *

#Host gets the Name and IP address of this system
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



#End Classes---------------------------------------------------------


def block_message(msg):
    """
    Block Message
    Sets Block message for sink page
    1. Output required data into wwwsink/index.html'
    2. Find which group is running the webserver (http or www-user)
    3. Set ownership of the file for relevant group
    4. Set file permissions to 775

    Parameters:
        msg (str): Message
    Returns:
        None
    """
    services = Services()
    groupname = ''

    groupname = services.get_webuser()

    print('Opening %sindex.html for writing' % folders.wwwsink)

    f = open(folders.wwwsink + 'index.html', 'w')
    if msg == 'message':
        print('Setting Block message Blocked by NoTrack')
        f.write('<p>Blocked by NoTrack</p>')
    elif msg == 'pixel':
        print('Setting Block message to pixel')
        f.write('<img src="data:image/gif;base64,R0lGODlhAQABAAAAACwAAAAAAQABAAA=" alt="" />')

    f.close()                                              #Close index.html

    #Set ownership of index.html
    print('Setting ownership of %sindex.html to %s' % (folders.wwwsink, groupname))
    shutil.chown(folders.wwwsink + 'index.html', user=groupname, group=groupname)

    #Set permissions of index.html
    if os.name == 'posix':
        print('Setting permissions of %sindex.html to 775' % folders.wwwsink)
        os.chmod(folders.wwwsink + 'index.html', 0o775)
    else:
        print('Setting permissions of %sindex.html to Archive' % folders.wwwsink)
        os.chmod(folders.wwwsink + 'index.html', stat.FILE_ATTRIBUTE_ARCHIVE)


def copy_dhcp():
    """
    Copy DHCP Config
    1: Check dhcp.conf exists in /tmp
    2: Change ownership and permissions
    3: Copy to /etc/dnsmasq.d/dhcp.conf
    4: Restart DNS Server
    """
    dhcp_config = 'dhcp.conf'
    services = Services()

    #Has the DHCP config file been set when services were discovered?
    if services.dhcp_config == '':
        print('Copy_dhcp: Error - This function only works with Dnsmasq')
        return

    copy_file(folders.tempdir + dhcp_config, services.dhcp_config)
    services.restart_dnsserver()                           #Restart the DNS Server


def copy_list(listname):
    """
    Copy List
    1. Copies either black or white list from temp folder to /etc/notrack
    2. Start a copy of notrack in wait mode
    This will allow user time to make further changes before lists are updated
    If there is a copy of notrack waiting the forked process will be closed

    Parameters:
        listname (str): List Filename
    Returns:
        None
    """
    copy_file(folders.tempdir + listname, folders.etc_notrack + listname)
    run_notrack('wait')                                    #Run notrack in wait mode


def copy_tldlists():
    """
    Copy TLD Lists
    Copy both TLD black and white list from temp folder to /etc/notrack
    """
    copy_file(folders.tempdir + 'domain-blacklist.txt', folders.etc_notrack + 'domain-blacklist.txt')
    copy_file(folders.tempdir + 'domain-whitelist.txt', folders.etc_notrack + 'domain-whitelist.txt')


def copy_localhosts():
    """
    Save Static Hosts Config
    1: Check localhosts.list exists in /tmp
    2: Change ownership and permissions
    3: Copy to /etc/dnsmasq.d/dhcp.conf
    """
    host = Host()
    localhosts_temp = ''

    localhosts_temp = folders.tempdir + 'localhosts.list'

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

    copy_file(localhosts_temp, folders.etcdir + 'localhosts.list')


def create_accesslog():
    """
    Create Access Log
    Create /var/log/ntrk-admin.log and set permissions to 666
    """
    print('Checking to see if %s exists' % folders.accesslog)
    if os.path.isfile(folders.accesslog):
        print('File exists, no action required')
    else:
        print('File missing, creating it')
        f = open(folders.accesslog, 'w')
        f.close()
        print('Setting permissions to rw-rw-rw-');
        os.chmod(folders.accesslog, 0o666)


def delete_history():
    """
    Call dbwrapper class delete_history to delete all rows from dnslog and weblog
    """
    from ntrkmariadb import DBWrapper
    dbwrapper = DBWrapper()                                    #Declare MariaDB Wrapper

    dbwrapper.delete_history();


def parsing_time(interval):
    """
    Parsing Time
    Update CRON parsing interval for ntrk-parse to specified interval in minutes

    Parameters:
        interval (int): Interval in minutes
    Returns:
        None
    """
    print('Updating parsing time')

    if interval < 0 or interval > 99:
        print('Warning: Invalid interval specified')
        return

    print('Setting job interval of %d minutes in %s' % (interval, folders.cron_ntrkparse))

    f = open(folders.cron_ntrkparse, 'w')
    print('*/%d * * * *\troot\t/usr/local/sbin/ntrk-parse' % interval, file=f)
    f.close()                                              #Close cron_ntrkparse file


def ntrk_pause(mode, duration=0):
    """
    NoTrack Pause
    Run NoTrack with specified mode
    TODO fork natively with python3
    """

    if mode == 'pause':
        #Fork process of ntrk-pause into background
        print('Launching NoTrack to Pause for %d minutes' % duration)
        #subprocess.Popen([folders.notrack, '--pause', str(duration)])
        #TODO Popen wouldn't work with arguments
        os.system(folders.notrack + ' --pause ' + str(duration) + ' > /dev/null &')

    else:
        print('Launching NoTrack to %s' % mode)
        #Fork process of ntrk-pause --mode into background
        #subprocess.Popen([folders.ntrk_pause, ' --' + mode], stdout=subprocess.PIPE )
        os.system(folders.notrack + ' --' + mode + ' > /dev/null &')


def run_notrack(mode=''):
    """
    Run NoTrack
    1. Check NoTrack exists
    2. Run NoTrack

    Parameters:
        mode (str): Optional Mode to run NoTrack in
    Returns:
        None
    """
    if not os.path.isfile(folders.notrack):                #Does notrack exist?
        print('Error %s is missing :-(' % folders.notrack, file=sys.stderr)
        sys.exit(24)

    if mode == '' or mode == 'now':
        #Fork process of notrack into background
        print('Launching NoTrack')
        #subprocess.Popen(folders.notrack, stdout=subprocess.PIPE )
        os.system(folders.notrack + ' > /dev/null &')
    else:
        #Fork process of notrack --mode into background
        print('Launching NoTrack with %s mode' % mode)
        #subprocess.Popen([folders.notrack, '--' + mode], stdout=subprocess.PIPE )
        os.system(folders.notrack + ' --' + mode + ' > /dev/null &')


def upgrade_notrack():
    """
    Upgrade NoTrack
    1. Check ntrk-upgrade exists
    2. Run and wait for ntrk-upgrade to complete
    3. Print the output of ntrk-upgrade
    4. Check for errors
    """
    if not os.path.isfile(folders.ntrk_upgrade):           #Does ntrk-upgrade exist?
        print('Upgrade_notrack: Error %s is missing' % folders.ntrk_upgrade, file=sys.stderr)
        sys.exit(20)

    process = subprocess.run([folders.ntrk_upgrade], stdout=subprocess.PIPE, stderr=subprocess.PIPE, universal_newlines=True)

    print(process.stdout)                                  #Show the terminal output

    if process.returncode != 0:                            #Check return code
        print('Upgrade_notrack: Error with upgrade')
        print(process.stderr)                              #TODO no such functionality yet


def restart_system():
    """
    Restart System
    1. Set Restart command depending on OS
    2. Run Restart command
    """
    cmd = ''

    print('Restarting system')
    if os.name == 'nt':
        cmd = 'shutdown -r'
    elif os.name == 'posix':
        cmd = 'reboot > /dev/null &'

    os.system(cmd)                                         #Run restart command


def shutdown_system():
    """
    Shutdown System
    1. Set Shutdown command depending on OS
    2. Run Shutdown command
    """
    cmd = ''

    print('Powering off system')
    if os.name == 'nt':
        cmd = 'shutdown -s'
    elif os.name == 'posix':
        cmd = 'shutdown now > /dev/null &'

    os.system(cmd)                                         #Run shutdown command


#Main----------------------------------------------------------------
folders = FolderList()

check_root()

parser = argparse.ArgumentParser(description = 'NoTrack Exec:')
parser.add_argument('-p', "--play", help='Start Blocking', action='store_true')
parser.add_argument('-s', "--stop", help='Stop Blocking', action='store_true')
parser.add_argument('--pause', help='Pause Blocking', type=int)
parser.add_argument('--accesslog', help='Create Access log file', action='store_true')
parser.add_argument("--deletehistory", help='Message on Sink Page', action='store_true')
parser.add_argument('--parsing', help='Parser update time', type=int)
parser.add_argument('--restart', help='Restart System', action='store_true')
parser.add_argument('--shutdown', help='Shutdown System', action='store_true')
parser.add_argument('--upgrade', help='Upgrade NoTrack', action='store_true')
parser.add_argument('--run', help='Run NoTrack', choices=['now', 'wait', 'force'])
parser.add_argument("--save", help='Replace specified file', choices=['conf', 'dhcp', 'localhosts', 'black', 'white', 'tld'])
parser.add_argument("--sink", help='Block Message on Sink page', choices=['message', 'pixel'])

args = parser.parse_args()

if args.save:                                              #Save a config file
    if args.save == 'conf':
        copy_file(folders.tempdir + 'notrack.conf', folders.etc_notrack + 'notrack.conf')
    elif args.save == 'black':
        copy_list('blacklist.txt')
    elif args.save == 'white':
        copy_list('whitelist.txt')
    elif args.save == 'dhcp':
        copy_dhcp()
    elif args.save == 'localhosts':
        copy_localhosts()
    elif args.save == 'tld':
        copy_tldlists()

if args.play:                                              #Play / Pause / Stop
    ntrk_pause('play')
if args.pause:
    ntrk_pause('pause', args.pause)
if args.stop:
    ntrk_pause('stop')

if args.run:                                               #Run NoTrack - Wait / Force
    run_notrack(args.run)

if args.upgrade:                                           #Upgrade NoTrack
    upgrade_notrack()

if args.sink:                                              #New Sink page - block / pixel
    block_message(args.sink)

if args.parsing:                                           #Cron Parsing time
    parsing_time(args.parsing)

if args.deletehistory:                                     #Delete history
    if not check_module('mysql'):
        delete_history()
    else:
        print('Missing package python3-mysql.connector :-(')
        print('Please install python3-mysql.connector from your package manager', file=sys.stderr)
        sys.exit(30)

if args.accesslog:                                         #Create Access log file
    create_accesslog()

if args.restart:                                           #Restart / Shutdown system
    restart_system()
if args.shutdown:
    shutdown_system()
