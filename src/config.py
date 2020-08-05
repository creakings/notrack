#!/usr/bin/env python3
#Title       : NoTrack Config
#Description :
#Author      : QuidsUp
#Date        : 2020-07-30
#Version     : 20.07
#Usage       : python3 config.py

import os
import re
import sys
import time

from statusconsts import *
from ntrkshared import *

class NoTrackConfig:
    def __init__(self, tempdir, wwwconfdir):
        self.__tempdir = tempdir
        self.__wwwconfdir = wwwconfdir

        self.status_last_mtime = 0.0

        status = STATUS_ENABLED
        unpausetime = 0

        print()
        print('Loading NoTrack config files')
        self.load_status()


    def __get_filemtime(self, filename):
        if not os.path.isfile(filename):
            print(f'{filename} is missing')
            return sys.maxsize

        return os.path.getmtime(filename)



    def check_status_mtime(self):
        if (self.status_last_mtime == 0.0):
            return True

        if (self.__get_filemtime(f'{self.__wwwconfdir}status.php') > self.status_last_mtime):
            return True

        return False


    def is_status_valid(self, lines):
        """
        Validate either temp new or existing status config

        Parameters:
            lines (list): lines from a file
        Returns:
            True on success, False on invalid file
        """
        if len(lines) != 3:
            print(f'Invalid file length: {len(lines)}')
            return False

        if lines[0] != '<?php\n':                          #Require PHP start
            return False

        matches = re.match(r'^\$this\->set_status\((\d{1,2}), (\d+)\);\s$', lines[1])
        if matches is not None:
            self.status = int(matches[1])
            self.unpausetime = int(matches[2])
            print(f'Status: {self.status}')                #Show new status
            print(f'Unpausetime: {self.unpausetime}')      #Show new unpausetime
        else:
            return False

        if lines[2] != '?>\n':                             #Require PHP end
            return False

        return True


    def load_status(self):
        """
        Load status.php to get status and unpausetime
        """

        lines = []
        print('Loading config status')

        lines = load_file(self.__wwwconfdir + 'status.php')
        self.is_status_valid(lines)

        if self.status & STATUS_PAUSED and self.unpausetime > 0:
            if self.unpausetime < time.time():
                self.status -= STATUS_PAUSED
                self.unpausetime = 0

        self.status_last_mtime = self.__get_filemtime(f'{self.__wwwconfdir}status.php')


