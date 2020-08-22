#!/usr/bin/env python3
#Title      : NoTrack Folders
#Description: FoldersList Class for Multiple NoTrack components
#Author     : QuidsUp
#Date       : 2020-04-04
#Version    : 0.9.5

#Standard imports
import os
import sys
import tempfile

class FolderList:
    """
    Class FolderList stores the various file and folder locations by OS
    TODO complete for Windows
    """
    def __init__(self):
        if os.name == 'posix':

            #Directories
            self.etcdir = '/etc/'
            self.tempdir = tempfile.gettempdir() + '/'
            self.sbindir = '/usr/local/sbin/'              #DEPRECATED
            self.webdir = ''
            #self.logdir = '/var/log/'

            self.__find_unix_webdir()                      #Get webserver location

            self.accesslog = '/var/log/ntrk-admin.log'
            self.cron_ntrkparse = '/etc/cron.d/ntrk-parse'
            self.main_blocklist = '/etc/dnsmasq.d/notrack.list'
            self.temp_blocklist = self.tempdir + 'notracktemp.list'
            self.dnslists = '/etc/dnsmasq.d/'
            self.blacklist = f'{self.webconfigdir}/blacklist.txt'
            self.whitelist = f'{self.webconfigdir}/whitelist.txt'
            self.tld_blacklist = '/etc/notrack/domain-blacklist.txt'
            self.tld_whitelist = '/etc/notrack/domain-whitelist.txt'
            self.tld_csv = f'{self.webdir}/include/tld.csv'
            self.notrack_config = '/etc/notrack/notrack.conf'
            self.etc_notrack = '/etc/notrack/'

            #NoTrack Apps
            self.notrack = '/usr/local/sbin/notrack'       #DEPRECATED
            self.ntrk_pause = '/usr/local/sbin/ntrk-pause' #DEPRECATED
            self.ntrk_upgrade = '/usr/local/sbin/ntrk-upgrade' #DEPRECATED




    def __find_unix_webdir(self):
        """
        Find UNIX webdir
        """
        if os.path.isdir('/var/www/html/notrack/admin'):   #Optional location for notrack
            self.webconfigdir = '/var/www/html/notrack/admin/settings'
            self.webdir = '/var/www/html/notrack/admin'
            self.wwwsink = '/var/www/html/notrack/sink/'   #DEPRECATED
        elif os.path.isdir('/var/www/html/admin'):
            self.webconfigdir = '/var/www/html/admin/settings'
            self.webdir = '/var/www/html/admin'
            self.wwwsink = '/var/www/html/sink/'           #DEPRECATED
        else:
            print('Find_Unix_WebDir: Fatal Error - Unable to find web folder', file=sys.stderr)
            sys.exit(10)

