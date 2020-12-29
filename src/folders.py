#!/usr/bin/env python3
#Title      : NoTrack Folders
#Description: FoldersList Class for Multiple NoTrack components
#Author     : QuidsUp
#Date       : 2020-04-04
#Version    : 20.12
#Usage      : N/A this module is loaded as part of other NoTrack modules

#Standard imports
import os
import sys
import tempfile

etcdir = ''
tempdir = ''
webdir = ''
webconfigdir = ''
main_blocklist = ''
temp_blocklist = ''
dnslists = ''
blacklist = ''
whitelist = ''
tldist = ''
tld_csv = ''
localhosts = ''
dhcpconf = ''
serverconf = ''

def find_unix_webdir():
    """
    Find UNIX webdir
    """
    global webdir, webconfigdir
    #1. Optional location for NoTrack
    if os.path.isdir('/var/www/html/notrack/admin'):
        webconfigdir = '/var/www/html/notrack/admin/settings'
        webdir = '/var/www/html/notrack/admin'
    #2. Default location for NoTrack
    elif os.path.isdir('/var/www/html/admin'):
        webconfigdir = '/var/www/html/admin/settings'
        webdir = '/var/www/html/admin'
    else:
        raise Exception('Unable to find web folder')



if os.name == 'posix':
    #Directories
    etcdir = '/etc'
    tempdir = tempfile.gettempdir()
    webdir = ''
    #logdir = '/var/log/'

    find_unix_webdir()              #Get webserver location

    main_blocklist = '/etc/dnsmasq.d/notrack.list'
    temp_blocklist = f'{tempdir}/notracktemp.list'
    dnslists = '/etc/dnsmasq.d/'
    blacklist = f'{webconfigdir}/blacklist.txt'
    whitelist = f'{webconfigdir}/whitelist.txt'
    tldist = f'{webconfigdir}/tldlist.txt'
    tld_csv = f'{webdir}/include/tld.csv'

    #Output Config Files
    localhosts = f'{etcdir}/localhosts.list'
    dhcpconf = f'{etcdir}/dnsmasq.d/dhcp.conf'
    serverconf = f'{etcdir}/dnsmasq.d/server.conf'


