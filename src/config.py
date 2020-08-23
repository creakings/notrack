#!/usr/bin/env python3
#Title       : NoTrack Config
#Description :
#Author      : QuidsUp
#Date        : 2020-07-30
#Version     : 20.08
#Usage       : python3 config.py

import os
import re
import sys
import time

from statusconsts import *
from ntrkshared import *

class NoTrackConfig:
    def __init__(self, webconfigdir):
        self.__webconfigdir = webconfigdir

        self.__settingsfiles = {                           #Actual filenames for settings
            'bl.php' : f'{self.__webconfigdir}/bl.php',
            'status.php' : f'{self.__webconfigdir}/status.php',
            'blacklist.txt' : f'{self.__webconfigdir}/blacklist.txt',
            'whitelist.txt' : f'{self.__webconfigdir}/whitelist.txt',
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


