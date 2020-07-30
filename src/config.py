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

from ntrkshared import *

class NoTrackConfig:
    def __init__(self, tempdir, wwwconfdir):
        self.__tempdir = tempdir
        self.__wwwconfdir = wwwconfdir

        self.STATUS_ENABLED = 1
        self.STATUS_DISABLED = 2
        self.STATUS_PAUSED = 4
        self.STATUS_INCOGNITO = 8
        self.STATUS_ERROR = 128

        status = self.STATUS_ENABLED
        unpausetime = 0

        print()
        print('Loading NoTrack config files')
        self.load_status()


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


    def save_status(self):
        """
        Check /tmp/status.php
        """
        lines = []

        print('Checking temporary status file')
        lines = load_file(self.__tempdir + 'status.php')

        if self.is_status_valid(lines):
            print('Valid status file')
            move_file(self.__tempdir + 'status.php', self.__wwwconfdir + 'status.php', 0o666)
        else:
            print('Invalid status file')
            delete_file(self.__tempdir + 'status.php')

