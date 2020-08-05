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
from blockparser import BlockParser
from config import NoTrackConfig
from logparser import NoTrackParser
from ntrkfolders import FolderList
from statusconsts import *

runtime_analytics = 0.0
runtime_blocklist = 0.0
runtime_parser = 0.0

status_mtime = 0.0

endloop = False

ntrkparser = NoTrackParser()
ntrkanalytics = NoTrackAnalytics()
blockparser = BlockParser()
folders = FolderList()
config = NoTrackConfig(folders.tempdir, folders.wwwconfdir)


def get_status(currentstatus):
    """
    Confirm system status is as config['status'] specifies
    e.g. main or temp blocklist could be missing

    Parameters:
        currentstatus(int)
    Returns:
        Actual Status
    """

    mainexists = os.path.isfile(folders.main_blocklist)
    tempexists = os.path.isfile(folders.temp_blocklist)

    if currentstatus & STATUS_ENABLED and mainexists:  #Check Enabled Status
        return currentstatus
    elif currentstatus & STATUS_ENABLED:
        return STATUS_ERROR

    if currentstatus & STATUS_DISABLED and tempexists: #Check Disabled Status
        return currentstatus
    elif currentstatus & STATUS_DISABLED:
        return STATUS_ERROR

    if currentstatus & STATUS_PAUSED and tempexists:   #Check Paused Status
        return currentstatus
    elif currentstatus & STATUS_PAUSED:
        return STATUS_ERROR

    return STATUS_ERROR                                #Shouldn't get here


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
    if config.status & STATUS_INCOGNITO:
        print('Incognito mode')
        ntrkparser.blank_dnslog()
    else:
        ntrkparser.parsedns()


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

        if config.check_status_mtime():
            print('Config updated')
            config.load_status()

        print(get_status(config.status))

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
