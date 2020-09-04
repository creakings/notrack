#!/usr/bin/env python3
#Title      : NoTrack Folders
#Description: FoldersList Class for Multiple NoTrack components
#Author     : QuidsUp
#Date       : 2020-04-04
#Version    : 20.09

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
            etcdir = '/etc'
            FolderList.tempdir = tempfile.gettempdir()
            FolderList.webdir = ''
            #FolderList.logdir = '/var/log/'

            self.__find_unix_webdir()                      #Get webserver location

            #FolderList.accesslog = '/var/log/ntrk-admin.log' DEPRECATED
            FolderList.main_blocklist = '/etc/dnsmasq.d/notrack.list'
            FolderList.temp_blocklist = f'{FolderList.tempdir}/notracktemp.list'
            FolderList.dnslists = '/etc/dnsmasq.d/'
            FolderList.blacklist = f'{FolderList.webconfigdir}/blacklist.txt'
            FolderList.whitelist = f'{FolderList.webconfigdir}/whitelist.txt'
            FolderList.tld_blacklist = '/etc/notrack/domain-blacklist.txt'
            FolderList.tld_whitelist = '/etc/notrack/domain-whitelist.txt'
            FolderList.tld_csv = f'{FolderList.webdir}/include/tld.csv'

            #Output Config Files
            FolderList.localhosts = f'{etcdir}/localhosts.list'
            FolderList.dhcpconf = f'{etcdir}/dnsmasq.d/dhcp.conf'
            FolderList.serverconf = f'{etcdir}/dnsmasq.d/server.conf'


    def __find_unix_webdir(self):
        """
        Find UNIX webdir
        """
        if os.path.isdir('/var/www/html/notrack/admin'):   #Optional location for notrack
            FolderList.webconfigdir = '/var/www/html/notrack/admin/settings'
            FolderList.webdir = '/var/www/html/notrack/admin'
        elif os.path.isdir('/var/www/html/admin'):
            FolderList.webconfigdir = '/var/www/html/admin/settings'
            FolderList.webdir = '/var/www/html/admin'
        else:
            print('Find_Unix_WebDir: Fatal Error - Unable to find web folder', file=sys.stderr)
            sys.exit(10)

