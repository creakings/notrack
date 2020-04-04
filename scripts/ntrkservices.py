#!/usr/bin/env python3
#Title : NoTrack Services
#Description :
#Author : QuidsUp
#Date : 2020-04-04
#Version : 0.9.5

import shutil
import subprocess
import sys

class Services:
    """
    NoTrack Services is a class for identifing Service Supervisor, Web Server, and DNS Server
    Restarting the Service will use the appropriate Service Supervisor
    """
    def __init__(self):
        self.__supervisor = ''                             #Supervisor command
        self.__supervisor_name = ''                        #Friendly name
        self.__webserver = ''
        self.__dnsserver = ''
        self.dhcp_config = ''

        self.__find_supervisor()
        self.__find_dnsserver()
        self.__find_webserver()


    def __find_supervisor(self):
        """
        Find service supervisor by checking if each application exists
        """
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
            print('Find_Supervisor: Fatal Error - Unable to identify service supervisor', file=sys.stderr)
            sys.exit(7)
        print('Services Init: Identified Service manager', self.__supervisor_name)


    def __find_dnsserver(self):
        """
        Find DNS server by checking if each application exists
        """
        if shutil.which('dnsmasq') != None:
            self.__dnsserver = 'dnsmasq'
            self.dhcp_config = '/etc/dnsmasq.d/dhcp.conf'
        elif shutil.which('bind') != None:
            self.__dnsserver = 'bind'
        else:
            print('Find_DNSServer: Fatal Error - Unable to identify DNS server', file=sys.stderr)
            sys.exit(8)
        print('Services Init: Identified DNS server', self.__dnsserver)


    def __find_webserver(self):
        """
        Find Web server by checking if each application exists
        """
        if shutil.which('lighttpd') != None:
            self.__webserver = 'lighttpd'
        elif shutil.which('apache') != None:
            self.__webserver = 'apache'
        else:
            print('Find_WebServer: Fatal Error - Unable to identify Web server', file=sys.stderr)
            sys.exit(9)
        print('Services Init: Identified Web server', self.__webserver)
        print()


    def __restart_service(self, service):
        """
        Restart specified service and check the error code

        Parameters:
            service (str): Service to restart
        Returns:
            True on Success (return code zero)
            False on Failure (return code non-zero)
        """
        p = subprocess.run(['sudo', self.__supervisor, 'restart', service], stderr=subprocess.PIPE, universal_newlines=True)

        if p.returncode != 0:
            print('Services restart_service: Failed to restart %s' % service)
            print(p.stderr)
            return False
        else:
            print('Successfully restarted %s' % service)
            return True


    def get_webuser(self):
        """
        Find the group for webserver
        Arch uses http, other distros use www-data
        """
        import grp

        groupname = ''
        print ('Finding service group for', self.__webserver)

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

        print()
        return groupname


    def get_dnstemplatestr(self, hostname, hostip):
        """
        Gets the necessary string for creating DNS Block list files based on DNS server

        Parameters:
            hostname (str): Host Name
            hostip (str): Host IP
        Returns:
            list of blacklist and whitelist string templates
        """
        blacklist = ''
        whitelist = ''

        if self.__dnsserver == 'dnsmasq':
            blacklist = 'address=/%s/' + hostip + '\n'
            whitelist = 'server=/%s/#\n'

        return tuple([blacklist, whitelist])


    def restart_dnsserver(self):
        """
        Restart DNS Server - returns the result of restart_service
        """
        return self.__restart_service(self.__dnsserver)


    def restart_webserver(self):
        """
        Restart Web Server - returns the result of restart_service
        """
        return self.__restart_service(self.__webserver)

