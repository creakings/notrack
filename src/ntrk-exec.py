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

