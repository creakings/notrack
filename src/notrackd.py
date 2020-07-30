#!/usr/bin/env python3
#Title : NoTrackD
#Description : NoTrack Daemon
#Author : QuidsUp
#Date : 2020-07-24
#Version : 2020.07
#Usage :

#Standard imports
from datetime import datetime
import os
import time
import signal
import sys

#Local imports
from analytics import NoTrackAnalytics
from config import NoTrackConfig
from logparser import NoTrackParser
from ntrkfolders import FolderList

runtime_analytics = 0.0
runtime_blocklist = 0.0
runtime_parser = 0.0

endloop = False

ntrkparser = NoTrackParser()
ntrkanalytics = NoTrackAnalytics()
folders = FolderList()
config = NoTrackConfig(folders.tempdir, folders.wwwconfdir)

def check_file_age(filename):
    """
    Does file exist?
    Check last modified time is within MAX_AGE (2 days)

    Parameters:
        filename (str): File
    Returns:
        True update list
        False list within MAX_AGE
    """
    print('\tChecking age of %s' % filename)

    if not os.path.isfile(filename):
        print('\tFile missing')
        return True

    if CURRENT_TIME > (os.path.getmtime(filename) + MAX_AGE):
        print('\tFile older than 2 days')
        return True

    print('\tFile in date, not downloading')
    return False

def exit_gracefully(signum, frame):
    """
    Ends pause wait and moves NoTrack to a new state - Enabled or Disabled depending
    on the signal value received

    Parameters:
        signum (int): Signal
        frame (int): Frame
    """
    global endloop
    #if signum == signal.SIGABRT:                       #Abort moves state to Disabled
    #self.__enable_blocking = False
    endloop = True                                         #Trigger breakout of main loop

def set_lastrun_times():
    global runtime_analytics, runtime_blocklist

    dtanalytics = datetime.strptime(time.strftime("%y-%m-%d %H:00:00"), '%y-%m-%d %H:%M:%S')
    dtblocklist = datetime.strptime(time.strftime("%y-%m-%d 04:11:00"), '%y-%m-%d %H:%M:%S')

    runtime_analytics = dtanalytics.timestamp()
    runtime_blocklist = dtblocklist.timestamp()

def analytics():
    ntrkanalytics.checkmalware()
    ntrkanalytics.checktrackers()

def logparser():
    if config.status & config.STATUS_INCOGNITO:
        print('Incognito mode')
        ntrkparser.blank_dnslog()
    else:
        ntrkparser.parsedns()

def check_tempfiles():
    if os.path.isfile(folders.tempdir + 'status.php'):
        config.save_status()


def main():
    global endloop
    global runtime_analytics, runtime_blocklist, runtime_parser

    signal.signal(signal.SIGINT, exit_gracefully)  #2 Inturrupt
    signal.signal(signal.SIGABRT, exit_gracefully) #6 Abort
    signal.signal(signal.SIGTERM, exit_gracefully) #9 Terminate

    current_time = 0.0
    current_time = time.time()

    print()
    print('NoTrack Daemon')

    #Initial setup
    ntrkparser.readblocklist()

    set_lastrun_times()

    while not endloop:
        current_time = time.time()

        check_tempfiles()

        if (runtime_analytics + 3600) <= current_time:
            runtime_analytics = current_time
            analytics()

        if (runtime_parser + 240) <= current_time:
            runtime_parser = current_time
            logparser()

        time.sleep(2)

    #nowtime = time.localtime()




if __name__ == "__main__":
    main()
