#!/usr/bin/env python3
#Title       : NoTrack Services
#Description : Finds service supervisor and can restart DNS / Web servers
#Author      : QuidsUp
#Date        : 2020-04-04
#Version     : 20.12

#Standard Imports
import shutil
import subprocess

#Local imports
import errorlogger

#Create logger
logger = errorlogger.logging.getLogger(__name__)

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
            Services.__supervisor_name = 'sysvinit'
        elif shutil.which('sv') != None:
            Services.__supervisor = 'sv'
            Services.__supervisor_name = 'ruinit'
        else:
            logger.error('Unknown service supervisor')
            raise Exception('Unable to proceed without service supervisor')
        logger.debug(f'Identified Service manager: {Services.__supervisor_name}')


    def __find_dnsserver(self):
        """
        Find DNS server by checking if each application exists
        """
        if shutil.which('dnsmasq') != None:
            Services.__dnsserver = 'dnsmasq'
        elif shutil.which('bind') != None:
            Services.__dnsserver = 'bind'
        else:
            logger.error('Unknown DNS server')
            raise Exception('Unable to proceed')
        logger.debug(f'Identified DNS server: {Services.__dnsserver}')


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
            logger.error('Unknown Web server')
            raise Exception('Unable to proceed')
        logger.debug(f'Identified Web server: {Services.__webserver}')


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
            logger.error(f'Failed to restart {service}')
            logger.error(p.stderr)
            return False
        else:
            logger.info(f'Successfully restarted {service}')
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

