#!/usr/bin/env python3
#Title       : NoTrack Config
#Description :
#Author      : QuidsUp
#Date        : 2020-07-30
#Version     : 20.08
#Usage       : python3 config.py

#Standard Imports
import os
import re
import sys
import time

#Local imports
from inputvalidation import *
from ntrkfolders import FolderList
from ntrkshared import *
from statusconsts import *


class NoTrackConfig:
    def __init__(self):
        self.__folders = FolderList()

        #DHCP Settings
        self.__dhcp = {
            'dhcp_authoritative': 0,
            'dhcp_enabled': 0,
            'dhcp_leasetime': '24h',
            'dhcp_gateway': '192.168.0.0',
            'dhcp_rangestart': '192.168.0.64',
            'dhcp_rangeend': '192.168.0.254',
        }
        self.__dhcp_hosts = dict()

        self.__filter_dhcp = {
            'dhcp_authoritative': FILTER_BOOL,
            'dhcp_enabled': FILTER_BOOL,
            'dhcp_leasetime': r'^\d{1,2}[dDhH]',
            'dhcp_gateway'   : FILTER_IP,
            'dhcp_rangestart': FILTER_IP,
            'dhcp_rangeend'  : FILTER_IP,
        }

        #DNS Settings
        self.__dns = {
            'dns_blockip': '192.168.0.2',
            'dns_interface': 'eth0',
            'dns_listenip': '127.0.0.1',
            'dns_listenport': '53',
            'dns_logretention': '60',
            'dns_name': 'notrack.local',
            'dns_server': 'OpenDNS',
            'dns_serverip1': '208.67.222.222',
            'dns_serverip2': '208.67.220.220',
        }
        self.__filter_dns = {
            'dns_blockip': FILTER_IP,
            'dns_interface': r'\w{2,30}',
            'dns_listenip': FILTER_IP,
            'dns_listenport': r'\d{1,5}',
            'dns_logretention': r'\d{1,3}',
            'dns_name': FILTER_DOMAIN,
            'dns_server': r'\w{5,30}',
            'dns_serverip1': FILTER_IP,
            'dns_serverip2': FILTER_IP,
        }

        self.__settingsfiles = {                           #Actual filenames for settings
            'bl.php' : f'{self.__folders.webconfigdir}/bl.php',
            'status.php' : f'{self.__folders.webconfigdir}/status.php',
            'blacklist.txt' : f'{self.__folders.webconfigdir}/blacklist.txt',
            'whitelist.txt' : f'{self.__folders.webconfigdir}/whitelist.txt',
        }

        self.__blocklist_mtimes = {                        #Files for blocklist configs
            'bl.php' : self.__get_filemtime('bl.php'),
            'blacklist.txt' : self.__get_filemtime('blacklist.txt'),
            'whitelist.txt' : self.__get_filemtime('whitelist.txt'),
        }

        self.status_mtime = 0.0

        status = STATUS_ENABLED
        unpausetime = 0

        print()
        print('Loading NoTrack config files')
        self.load_status()

    @property
    def dns_blockip(self):
        return self.__dns_blockip


    def __get_filemtime(self, setting):
        """
        Get last modified time of a file

        Parameters:
            setting (str): name of a file in __settingsfiles
        Returns:
            Last modified time when file exists
            0.0 when file is missing
        """
        filename = ''                                      #Actual filename
        filename = self.__settingsfiles.get(setting)       #Get actual filename

        if os.path.isfile(filename):                       #Check file exists
            return os.path.getmtime(filename)              #Return last modified time
        else:
            return 0.0                                     #File missing - return zero


    def __load_settingsfile(self, filename):
        """
        """

        filelines = []

        filelines = load_file(filename)

        for line in filelines:
            matches = re.match(r"^\$(?:config|this)\->([\w]+)(?: = '?)?((?:[\w\.:\-]+)|(?:\(.+\)))'?;\n$", line)
            if matches is not None:
                if matches[1] == 'dhcp_addhost':
                    self.__dhcp_addhost(matches[2])
                elif matches[1].startswith('dhcp'):
                    self.__dhcp_setvalue(matches[1], matches[2])
                elif matches[1].startswith('dns'):
                    self.__dns_setvalue(matches[1], matches[2])


    def __dhcp_addhost(self, line):
        matches = re.match(r"^\('(.+)', '(.+)', '(.+)', '(.+)'\)$", line)
        if matches is None:
            return False

        self.__dhcp_hosts[matches[1]] = tuple([matches[2], matches[3], matches[4]])


    def __dhcp_setvalue(self, varname, value):
        if varname in self.__dhcp:
            if re.match(self.__filter_dhcp[varname], value) is not None:
                self.__dhcp[varname] = value


    def __dns_setvalue(self, varname, value):
        if varname in self.__dns:
            if re.match(self.__filter_dns[varname], value) is not None:
                self.__dns[varname] = value


    def check_blocklist_mtimes(self):
        """
        Compare last modified time of blocklist config bl.php with last known modified time

        Parameters:
            None
        Returns:
            True when modified time has changed or is unknown
            False when modified time is the same
        """
        mtime = 0.0

        for blfile in self.__blocklist_mtimes:
            mtime = self.__get_filemtime(blfile)
            if self.__blocklist_mtimes[blfile] != mtime:   #Compare file modified time
                self.__blocklist_mtimes[blfile] = mtime    #Set new modified time
                return True

        return False


    def check_status_mtime(self):
        """
        Check last modified time of status.php compared to last known value
        """
        if self.__get_filemtime('status.php') != self.status_mtime:
            return True

        return False


    def load_serverconf(self):
        self.__load_settingsfile(f'{self.__folders.webconfigdir}/server.php')

    def load_status(self):
        """
        Load status.php to get status and unpausetime
        """
        filelines = []

        filelines = load_file(self.__settingsfiles.get('status.php'))
        for line in filelines:
            matches = re.match(r'^\$this\->set_status\((\d{1,2}), (\d+)\);\n$', line)
            if matches is not None:
                self.status = int(matches[1])
                self.unpausetime = int(matches[2])
                print(f'Status: {self.status}')            #Show new status
                print(f'Unpausetime: {self.unpausetime}')  #Show new unpausetime

        #Make sure status should be paused by checking if unpausetime < current time
        if self.status & STATUS_PAUSED and self.unpausetime > 0:
            if self.unpausetime < time.time():
                print('Incorrect status, setting as unpaused')
                self.status -= STATUS_PAUSED
                self.unpausetime = 0

        self.status_mtime = self.__get_filemtime('status.php')


    def save_dhcpconf(self):
        """
        Create dhcp.conf, which is read by dnsmasq
        Consists of DHCP Status, DHCP Range, and Static hosts
        """
        filelines = []

        #If dhcp is disabled, then delete dhcp.conf file
        if self.__dhcp['dhcp_enabled'] == '0':
            print(filelines)
            #delete_file(self.__folders.dhcpconf)
            return

        filelines.append(f"dhcp-option=3,{self.__dhcp['dhcp_gateway']}\n")
        filelines.append(f"dhcp-range={self.__dhcp['dhcp_rangestart']},{self.__dhcp['dhcp_rangeend']},{self.__dhcp['dhcp_leasetime']}\n")

        #Only add dhcp_authoritative if it is enabled
        if self.__dhcp['dhcp_authoritative'] == '1':
            filelines.append("dhcp-authoritative\n")

        filelines.append("\n")                             #Seperator

        for sysip in self.__dhcp_hosts:
            #Name, Type
            filelines.append(f"#{self.__dhcp_hosts[sysip][1]},{self.__dhcp_hosts[sysip][2]}\n")
            #MAC, IP
            filelines.append(f"dhcp-host={self.__dhcp_hosts[sysip][0]},{sysip}\n")

        print(filelines)
        #save_file(filelines, self.__folders.dhcpconf)

    def save_localhosts(self):
        """
        Prepare and save /etc/localhosts.list
        """
        filelines = []

        filelines.append(f"{self.__dns['dns_blockip']}\t{self.__dns['dns_name']}\n")

        for sysip in self.__dhcp_hosts:
            filelines.append(f"{sysip}\t{self.__dhcp_hosts[sysip][1]}\n")

        print(filelines)
        #save_file(filelines, self.__folders.localhosts')


    def save_serverconf(self):
        """
        Prepare and save /etc/dnsmasq/server.conf
        """
        filelines = []

        filelines.append(f"server={self.__dns['dns_serverip1']}\n")
        filelines.append(f"server={self.__dns['dns_serverip2']}\n")
        filelines.append(f"interface={self.__dns['dns_listenip']}\n")
        filelines.append(f"listen-address={self.__dns['dns_interface']}\n")

        if self.__dns['dns_listenport'] != '53':
            filelines.append(f"port={self.__dns['dns_listenport']}\n")


        print(filelines)
        #save_file(filelines, self.__folders.serverconf')


def main():
    ntrkconfig = NoTrackConfig()
    ntrkconfig.load_serverconf()
    ntrkconfig.save_localhosts()
    ntrkconfig.save_dhcpconf()
    ntrkconfig.save_serverconf()




if __name__ == "__main__":
    main()
