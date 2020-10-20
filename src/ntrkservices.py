#!/usr/bin/env python3
#Title : NoTrack Services
#Description :
#Author : QuidsUp
#Date : 2020-04-04
#Version : 20.10

import shutil
import subprocess
import sys

class Services:
    """
    NoTrack Services is a class for identifing Service Supervisor, Web Server, and DNS Server
    Restarting the Service will use the appropriate Service Supervisor
    """
    def __init__(self):
        Services.__supervisor = ''                             #Supervisor command
        Services.__supervisor_name = ''                        #Friendly name
        Services.__webserver = ''
        Services.__dnsserver = ''

        self.__find_supervisor()
        self.__find_dnsserver()
        self.__find_webserver()


    def __find_supervisor(self):
        """
        Find service supervisor by checking if each application exists
        """
        if shutil.which('systemctl') != None:
            Services.__supervisor = 'systemctl'
            Services.__supervisor_name = 'systemd'
        elif shutil.which('service') != None:
            Services.__supervisor = 'service'
            Services.__supervisor_name = 'systemctl'
        elif shutil.which('sv') != None:
            Services.__supervisor = 'sv'
            Services.__supervisor_name = 'ruinit'
        else:
            print('Find_Supervisor: Fatal Error - Unable to identify service supervisor', file=sys.stderr)
            sys.exit(7)
        print(f'Identified Service manager: {Services.__supervisor_name}')


    def __find_dnsserver(self):
        """
        Find DNS server by checking if each application exists
        """
        if shutil.which('dnsmasq') != None:
            Services.__dnsserver = 'dnsmasq'
        elif shutil.which('bind') != None:
            Services.__dnsserver = 'bind'
        else:
            print('Fatal Error - Unable to identify DNS server', file=sys.stderr)
            sys.exit(8)
        print(f'Identified DNS server: {Services.__dnsserver}')


    def __find_webserver(self):
        """
        Find Web server by checking if each application exists
        """
        if shutil.which('nginx') != None:
            Services.__webserver = 'nginx'
        elif shutil.which('lighttpd') != None:
            Services.__webserver = 'lighttpd'
        elif shutil.which('apache') != None:
            Services.__webserver = 'apache'
        else:
            print('Fatal Error - Unable to identify Web server', file=sys.stderr)
            sys.exit(9)
        print(f'Identified Web server: {Services.__webserver}')
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
        cmd = list()

        if Services.__supervisor == 'systemctl':
            cmd = ['sudo', 'systemctl', 'restart', f'{service}.service']

        else:
            cmd = ['sudo', Services.__supervisor, 'restart', service]

        p = subprocess.run(cmd, stderr=subprocess.PIPE, universal_newlines=True)

        if p.returncode != 0:
            print(f'Failed to restart {service}')
            print(p.stderr)
            print()
            return False
        else:
            print(f'Successfully restarted {service}')
            print()
            return True


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

        if Services.__dnsserver == 'dnsmasq':
            blacklist = 'address=/%s/' + hostip + '\n'
            whitelist = 'server=/%s/#\n'

        return tuple([blacklist, whitelist])


    def restart_dnsserver(self):
        """
        Restart DNS Server - returns the result of restart_service
        """
        return self.__restart_service(Services.__dnsserver)


    def restart_webserver(self):
        """
        Restart Web Server - returns the result of restart_service
        """
        return self.__restart_service(Services.__webserver)


    def restart_notrack(self):
        """
        Restart NoTrackD - returns the result of restart_service
        """
        return self.__restart_service('notrack')

