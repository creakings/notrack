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

#Regex for PHP Code from a config file
#Non-capturing group: $config or $this
#->
#Group 1: variable or function name
#Non-capturing group: =
#Group 2: - Non-capturing group: value / Non-capturing group: Function contents
#End with ;\n
Regex_PHPLine = re.compile(r"^\$(?:config|this)\->([\w]+)(?:\s*=\s*'?)?((?:[\w\.:\-]+)|(?:\(.+\)))'?;\n$")
Regex_StatusLine = re.compile(r"^\$(?:config|this)\->set_status\((\d{1,2}), (\d+)\);\n$")

class NoTrackConfig:
    def __init__(self):
        """
        Assign default values for all NoTrack config values required for Python scripts
        """
        self.__folders = FolderList()

        #DHCP Settings
        self.__config = {
            'dhcp_authoritative': 0,
            'dhcp_enabled': 0,
            'dhcp_leasetime': '24h',
            'dhcp_gateway': '192.168.0.0',
            'dhcp_rangestart': '192.168.0.64',
            'dhcp_rangeend': '192.168.0.254',
            'dns_blockip': '127.0.0.1',
            'dns_interface': 'eth0',
            'dns_listenip': '127.0.0.1',
            'dns_listenport': '53',
            'dns_logretention': '60',
            'dns_name': 'notrack.local',
            'dns_server': 'OpenDNS',
            'dns_serverip1': '208.67.222.222',
            'dns_serverip2': '208.67.220.220',
        }
        self.__dhcp_hosts = dict()                         #Dictionary of DHCP Hosts

        self.__filters = {                                 #Validate user input
            'dhcp_authoritative': FILTER_BOOL,
            'dhcp_enabled': FILTER_BOOL,
            'dhcp_leasetime': r'^\d{1,2}[dDhH]',
            'dhcp_gateway'   : FILTER_IP,
            'dhcp_rangestart': FILTER_IP,
            'dhcp_rangeend'  : FILTER_IP,
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

        self.__config_files = {                            #Actual config filenames
            'bl.php' : f'{self.__folders.webconfigdir}/bl.php',
            'server.php' : f'{self.__folders.webconfigdir}/server.php',
            'status.php' : f'{self.__folders.webconfigdir}/status.php',
            'blacklist.txt' : f'{self.__folders.webconfigdir}/blacklist.txt',
            'whitelist.txt' : f'{self.__folders.webconfigdir}/whitelist.txt',
            'tldlist.txt' : f'{self.__folders.webconfigdir}/tldlist.txt',
        }

        self.__config_mtimes = {                           #Config Last modified times
            'bl.php' : self.__get_filemtime('bl.php'),
            'server.php' : self.__get_filemtime('server.php'),
            'status.php' : self.__get_filemtime('status.php'),
            'blacklist.txt' : self.__get_filemtime('blacklist.txt'),
            'whitelist.txt' : self.__get_filemtime('whitelist.txt'),
            'tldlist.txt' : self.__get_filemtime('tldlist.txt'),
        }

        self.__status = STATUS_ENABLED                     #Default value for status
        self.__unpausetime = 0                             #Default value for unpausetime

        print('Loading NoTrack config files')
        self.load_status()
        self.load_serverconf()

    @property
    def dns_blockip(self):
        return self.__config['dns_blockip']

    @property
    def dns_logretention(self):
        return int(self.__config['dns_logretention'])

    @property
    def status(self):
        return self.__status

    @property
    def unpausetime(self):
        return self.__unpausetime


    def __get_filemtime(self, setting):
        """
        Get last modified time of a file

        Parameters:
            setting (str): name of a file in __config_files
        Returns:
            Last modified time when file exists
            0.0 when file is missing
        """
        filename = ''                                      #Actual filename
        filename = self.__config_files.get(setting)      #Get actual filename

        if os.path.isfile(filename):                       #Check file exists
            return os.path.getmtime(filename)              #Return last modified time
        else:
            return 0.0                                     #File missing - return zero


    def __dhcp_addhost(self, line):
        matches = re.match(r"^\('(.+)', '(.+)', '(.+)', '(.+)'\)$", line)
        if matches is None:
            return False

        self.__dhcp_hosts[matches[1]] = tuple([matches[2], matches[3], matches[4]])


    def __setvalue(self, varname, value):
        if varname in self.__config:
            if re.match(self.__filters[varname], value) is not None:
                self.__config[varname] = value





    def check_modified_times(self):
        """
        Check the last modified times for all files listed in __config_mtimes dictionary
        The following actions take place for certain files:
        1. status.php - load status.php and read new status and unpause times
        2. server.php - load server.php
           Save new copies of dhcp.conf, localhosts.list, server.conf
        3. No action is taken for blocklists being updated

        Parameters:
            None
        Returns:
            First filename encountered which has been modified
        """
        new_mtime = 0

        for setting, mtime in self.__config_mtimes.items():
            new_mtime = self.__get_filemtime(setting)
            if new_mtime != mtime:
                if setting == 'status.php':
                    self.load_status()
                elif setting == 'server.php':
                    self.load_serverconf()
                    self.save_dhcpconf()
                    self.save_localhosts()
                    self.save_serverconf()
                self.__config_mtimes[setting] = new_mtime;

                return setting

        return ''


    def load_serverconf(self):
        """
        Load server.php and read all the values from it
        """
        filelines = []

        filelines = load_file(self.__config_files.get('server.php'))

        for line in filelines:
            matches = Regex_PHPLine.match(line)
            if matches is not None:
                if matches[1] == 'dhcp_addhost':
                    self.__dhcp_addhost(matches[2])
                else:
                    self.__setvalue(matches[1], matches[2])


    def load_status(self):
        """
        Load status.php to get status and unpausetime
        """
        filelines = []

        filelines = load_file(self.__config_files.get('status.php'))
        for line in filelines:
            matches = Regex_StatusLine.match(line)
            if matches is not None:
                self.__status = int(matches[1])
                self.__unpausetime = int(matches[2])
                print(f'Status: {self.__status}, Unpausetime: {self.__unpausetime}')
                break

        #Make sure status should be paused by checking if unpausetime < current time
        if self.__status & STATUS_PAUSED and self.__unpausetime > 0:
            if self.__unpausetime < time.time():
                print('Unpause time exceeded, setting as unpaused')
                self.__status -= STATUS_PAUSED
                self.__unpausetime = 0


    def save_dhcpconf(self):
        """
        Create dhcp.conf, which is read by dnsmasq
        Consists of DHCP Status, DHCP Range, and Static hosts
        """
        filelines = []

        #If dhcp is disabled, then delete dhcp.conf file
        if self.__config['dhcp_enabled'] == '0':
            delete_file(self.__folders.dhcpconf)
            return

        print('Saving dhcp.conf')
        filelines.append(f"dhcp-option=3,{self.__config['dhcp_gateway']}\n")
        filelines.append(f"dhcp-range={self.__config['dhcp_rangestart']},{self.__config['dhcp_rangeend']},{self.__config['dhcp_leasetime']}\n")

        #Only add dhcp_authoritative if its enabled
        if self.__config['dhcp_authoritative'] == '1':
            filelines.append("dhcp-authoritative\n")

        filelines.append("\n")                             #Seperator

        for sysip in self.__dhcp_hosts:                    #Add all localhosts
            #Commented Name, Type, followed by uncommented MAC, IP
            filelines.append(f"#{self.__dhcp_hosts[sysip][1]},{self.__dhcp_hosts[sysip][2]}\n")
            filelines.append(f"dhcp-host={self.__dhcp_hosts[sysip][0]},{sysip}\n")

        save_file(filelines, self.__folders.dhcpconf)


    def save_localhosts(self):
        """
        Prepare and save /etc/localhosts.list
        """
        filelines = []

        print('Saving localhosts.list')
        filelines.append(f"{self.__config['dns_blockip']}\t{self.__config['dns_name']}\n")

        for sysip in self.__dhcp_hosts:                    #Add all localhosts
            filelines.append(f"{sysip}\t{self.__dhcp_hosts[sysip][1]}\n")

        save_file(filelines, self.__folders.localhosts)


    def save_serverconf(self):
        """
        Prepare and save /etc/dnsmasq/server.conf
        Consists of upstream DNS server IP's, listeing interface and port
        """
        filelines = []

        print('Saving server.conf')
        filelines.append(f"server={self.__config['dns_serverip1']}\n")
        filelines.append(f"server={self.__config['dns_serverip2']}\n")
        filelines.append(f"interface={self.__config['dns_interface']}\n")
        filelines.append(f"listen-address={self.__config['dns_listenip']}\n")

        if self.__config['dns_listenport'] != '53':        #Only add a non-standard port
            filelines.append(f"port={self.__config['dns_listenport']}\n")

        save_file(filelines, self.__folders.serverconf)


def main():
    ntrkconfig = NoTrackConfig()
    ntrkconfig.save_localhosts()
    ntrkconfig.save_dhcpconf()
    ntrkconfig.save_serverconf()




if __name__ == "__main__":
    main()
