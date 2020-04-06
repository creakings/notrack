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
            self.sbindir = '/usr/local/sbin/'
            #self.logdir = '/var/log/'

            self.accesslog = '/var/log/ntrk-admin.log'
            self.cron_ntrkparse = '/etc/cron.d/ntrk-parse'
            self.main_blocklist = '/etc/dnsmasq.d/notrack.list'
            self.dnslists = '/etc/dnsmasq.d/'
            self.blacklist = '/etc/notrack/blacklist.txt'
            self.whitelist = '/etc/notrack/whitelist.txt'
            self.tld_blacklist = '/etc/notrack/domain-blacklist.txt'
            self.tld_whitelist = '/etc/notrack/domain-whitelist.txt'
            self.tld_csv = '/var/www/html/admin/include/tld.csv'
            self.notrack_config = '/etc/notrack/notrack.conf'
            self.etc_notrack = '/etc/notrack/'

            #NoTrack Apps
            self.notrack = '/usr/local/sbin/notrack'
            self.ntrk_pause = '/usr/local/sbin/ntrk-pause'
            self.ntrk_upgrade = '/usr/local/sbin/ntrk-upgrade'

            self.__find_unix_webdir()


    def __find_unix_webdir(self):
	"""
	Find UNIX webdir
	"""
        if os.path.isdir('/var/www/html/notrack'):
            self.wwwconfdir = '/var/www/html/notrack/admin/settings/'
            self.wwwsink = '/var/www/html/notrack/sink/'
        elif os.path.isdir('/var/www/html'):
            self.wwwconfdir = '/var/www/html/admin/settings/'
            self.wwwsink = '/var/www/html/sink/'
        else:
            print('Find_Unix_WebDir: Fatal Error - Unable to find web folder', file=sys.stderr)
            sys.exit(10)

