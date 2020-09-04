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
folders = FolderList()
config = NoTrackConfig()


def blocklist_update():
    """
    Once a day update of the NoTrack blocklists
    Or when blocklist config has been adjusted by the user TODO
    """
    print()
    print('Updating Blocklist')

    blockparser = BlockParser()
    blockparser.load_blconfig()
    blockparser.create_blocklist()                         #Create / Update Blocklists
    time.sleep(6)                                          #Prevent race condition
    ntrkparser.readblocklist()                             #Reload the blocklist on the log parser
    set_lastrun_times()


def change_status():
    """

    """
    print()
    print('Changing status of NoTrack')

    blockparser = BlockParser()

    if config.status & STATUS_ENABLED:
        blockparser.enable_blockling()
    else:
        blockparser.disable_blocking()


def check_pause(current_time):
    """
    Check if pause time has been reached
    Function should only be called if status is STATUS_PAUSED

    Parameters:
        current_time (int): The current epoch time value

    """
    if config.unpausetime < current_time:
        print()
        print('Unpause time reached')
        blockparser = BlockParser()
        blockparser.enable_blockling()
        config.status -= STATUS_PAUSED
        config.status += STATUS_ENABLED
        config.unpausetime = 0


def get_status(currentstatus):
    """
    Confirm system status is as currentstatus specifies
    e.g. main or temp blocklist could be missing

    Parameters:
        currentstatus (int): The current belived status
    Returns:
        Actual Status
    """

    mainexists = os.path.isfile(folders.main_blocklist)
    tempexists = os.path.isfile(folders.temp_blocklist)

    if currentstatus & STATUS_ENABLED and mainexists:      #Check Enabled Status
        return currentstatus
    elif currentstatus & STATUS_ENABLED:
        return STATUS_ERROR

    if currentstatus & STATUS_DISABLED and tempexists:     #Check Disabled Status
        return currentstatus
    elif currentstatus & STATUS_DISABLED:
        return STATUS_ERROR

    if currentstatus & STATUS_PAUSED and tempexists:       #Check Paused Status
        return currentstatus
    elif currentstatus & STATUS_PAUSED:
        return STATUS_ERROR

    return STATUS_ERROR                                    #Shouldn't get here


def exit_gracefully(signum, frame):
    """
    End the main loop when SIGINT, SIGABRT or SIGTERM is received

    Parameters:
        signum (int): Signal
        frame (int): Frame
    """
    global endloop
    #if signum == signal.SIGABRT:                          #Abort moves state to Disabled
    #self.__enable_blocking = False
    endloop = True                                         #Trigger breakout of main loop

def set_lastrun_times():
    """
    Ensure jobs run when ready
    1. Analytics - On the hour
    2. Blocklist Parser - 04:10 AM
    """
    global runtime_analytics, runtime_blocklist

    dtanalytics = datetime.strptime(time.strftime("%y-%m-%d %H:00:00"), '%y-%m-%d %H:%M:%S')
    dtblocklist = datetime.strptime(time.strftime("%y-%m-%d 04:10:00"), '%y-%m-%d %H:%M:%S')

    runtime_analytics = dtanalytics.timestamp()
    runtime_blocklist = dtblocklist.timestamp()


def analytics():
    """
    Run NoTrack Analytics
    """
    ntrkanalytics.checkmalware()
    ntrkanalytics.checktrackers()


def logparser():
    """
    Run NoTrack Log Parser
    """
    if config.status & STATUS_INCOGNITO:                   #No parsing with incognito
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

    print('NoTrack Daemon')

    if get_status(config.status) != config.status:
        change_status()
        time.sleep(5)

    #Initial setup
    ntrkparser.readblocklist()
    set_lastrun_times()

    while not endloop:
        current_time = time.time()

        if config.check_status_mtime():
            print()
            print('Status config updated')
            config.load_status()
            if get_status(config.status) != config.status:
                change_status()

        elif config.check_blocklist_mtimes():
            print()
            print('Blocklist config updated')
            blocklist_update()

        if (config.status & STATUS_PAUSED):
            check_pause(current_time)

        if (runtime_analytics + 3600) <= current_time:
            runtime_analytics = current_time
            analytics()

        if (runtime_parser + 240) <= current_time:
            runtime_parser = current_time
            logparser()

        if (runtime_blocklist + 86400) <= current_time:
            blocklist_update()

        time.sleep(3)


if __name__ == "__main__":
    main()
