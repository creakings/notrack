#NoTrack Pause
#Description : NoTrack Pause can pause/stop/start blocking in NoTrack by moving blocklists away from /etc/dnsmasq.d
#Author : QuidsUp

#Standard imports
import os
import re
import shutil
import signal
import subprocess
import time


#######################################
# Constants
#######################################
STATUS_ENABLED=1
STATUS_DISABLED=2
STATUS_PAUSED=4
STATUS_INCOGNITO=8
STATUS_NOTRACKRUNNING=64
STATUS_ERROR=128


class NoTrackPause:
    __incognito = 0                                        #Hold users incognito state
    __templist = ''                                        #Location for temp block list
    __blocklist = ''                                       #Location for main block list
    __end_pause = False                                    #
    __enable_blocking = True
    __pausetime = 0



    """ NoTrack Pause Initialisation

    Args:
        temp folder from folders.temp
        main blocklist from folders.main_blocklist
    Returns:
        None
    """
    def __init__(self, tempfolder, main_blocklist):
        self.__templist = tempfolder + 'notracktemp.list'
        self.__blocklist = main_blocklist

        signal.signal(signal.SIGINT, self.__exit_gracefully)
        signal.signal(signal.SIGABRT, self.__exit_gracefully)
        signal.signal(signal.SIGTERM, self.__exit_gracefully)



    def __exit_gracefully(self,signum, frame):
        if signum == signal.SIGABRT:
            self.__enable_blocking = False

        self.__end_pause = True

    """ Move File
        1. Check source exists
        2. Move file
        3. Check move has been successful
    Args:
        source
        destination
    Returns:
        True on success
        False on failure
    """
    def __move_file(self, source, destination):
        if not os.path.isfile(source):                     #Check source exists
            return False

        shutil.move(source, destination)                   #Move specified file

        if not os.path.isfile(destination):                #Check move has been successful
            return False

        return True


    def __backup_list(self):
        return self.__move_file(self.__blocklist, self.__templist)


    def __restore_list(self):
        return self.__move_file(self.__templist, self.__blocklist)


    """ Parent Running
        1. Get current pid and script name
        2. Run pgrep -a python3 - look for instances of python3 running
        3. Look through results of above command
        4. Check for my script name not equalling my pid
    Args:
        None
    Returns:
        0 - No other instances running
        > 0 - First match of another instance
    """
    def __parent_running(self):
        Regex_Pid = re.compile('^(\d+)\spython3?\s([\w\.\/]+)')
        mypid = os.getpid()                                    #Current PID
        myname = os.path.basename(__file__)                    #Current Script Name
        cmd = 'pgrep -a python3'

        try:
            res = subprocess.check_output(cmd,shell=True, universal_newlines=True)
        except subprocess.CalledProcessError as e:
            print('error', e.output, e.returncode)
        else:
            for line in res.splitlines():
                matches = Regex_Pid.search(line)
                if matches is not None:
                    if matches.group(2).find(myname) and int(matches.group(1)) != mypid:
                        if matches.group(2).find('pause'):
                            return int(matches.group(1))

        return 0


    def __terminate_pause(self, resume_blocking):
        parentpid = self.__parent_running()
        sig = 0

        if parentpid == 0:
            print('No process to stop')
            return

        if resume_blocking:
            sig = signal.SIGINT
        else:
            sig = signal.SIGABRT

        print('Sending signal %d to pid %d' % (sig, parentpid))
        os.kill(parentpid, sig)



    """ Disable Blocking
        1. Verify current status
        2. Check what state we are moving from into disabled
        3. Move blocklist to temp
    Args:
        config['status']
    Returns:
        STATUS_DISABLED + STATUS_INCOGNITO (if set) on success
        STATUS_ERROR when something has gone wrong
    """
    def disable_blocking(self, confstatus):
        status = self.get_status(confstatus)

        print('Disabling Blocking')

        if status == STATUS_ENABLED:
            print('Changing status from enabled to disabled')
            if self.__backup_list():
                print('Block list moved to temp folder')
            else:
                print('Disable_blocking: Warning - Failed to backup list')
                return STATUS_ERROR
        elif status == STATUS_PAUSED:
            print('Changing status from paused to disabled')
            self.__terminate_pause(False)
        elif status == STATUS_DISABLED:
            print('Status is already disabled')
        elif status == STATUS_ERROR:
            print('Status in error')
            return STATUS_ERROR

        return STATUS_DISABLED + self.__incognito



    """ Enable Blocking
        1. Verify current status
        2. Check what state we are moving from into disabled
        3. Move blocklist to temp
    Args:
        config['status']
    Returns:
        STATUS_DISABLED + STATUS_INCOGNITO (if set) on success
        STATUS_ERROR when something has gone wrong
    """
    def enable_blocking(self, confstatus):
        status = self.get_status(confstatus)

        print('Enabling Blocking')

        if status == STATUS_ENABLED:
            print('Status is already enabled')
        elif status == STATUS_PAUSED:
            print('Changing status from paused to enabled')
            self.__terminate_pause(True)
        elif status == STATUS_DISABLED:
            print('Changing status from disabled to enabled')
            if self.__restore_list():
                print('Block list returned')
            else:
                print('Enable_blocking: Warning - Failed to restore list')
                return STATUS_ERROR
        elif status == STATUS_ERROR:
            print('Status in error')
            return STATUS_ERROR

        return STATUS_ENABLED + self.__incognito


    """ Pause Blocking
        1. Verify current status
        2. Check what state we are moving from into disabled
        3. Move blocklist to temp
    Args:
        config['status']
    Returns:
        STATUS_DISABLED + STATUS_INCOGNITO (if set) on success
        STATUS_ERROR when something has gone wrong
    """
    def pause_blocking(self, confstatus, mins):
        unpause_time = 0

        self.__pausetime = mins * 60
        status = self.get_status(confstatus)
        unpause_time = int(time.time() + self.__pausetime)

        print('Pausing Blocking for %d mins' % mins)

        if status == STATUS_ENABLED:
            print('Changing status from enabled to disabled')
            if self.__backup_list():
                print('Block list moved to temp folder')
            else:
                print('Disable_blocking: Warning - Failed to backup list')
                return [STATUS_ERROR, 0]
        elif status == STATUS_PAUSED:
            print('Changing pause time')
            self.__terminate_pause(False)
        elif status == STATUS_DISABLED:
            print('Changing status from disabled to paused')
        elif status == STATUS_ERROR:
            print('Status in error')
            return [STATUS_ERROR, 0]

        return [STATUS_PAUSED + self.__incognito, unpause_time]


    def wait(self):
        i = 0
        newstatus = 0

        print('Waiting...')
        while not self.__end_pause:
            time.sleep(1)
            i += 1
            if i > self.__pausetime:
                self.__end_pause = True

        print('End pause')

        if self.__enable_blocking:
            if self.__restore_list():
                print('Block list returned')
                return STATUS_ENABLED + self.__incognito
            else:
                print('Enable_blocking: Warning - Failed to restore list')
                return STATUS_ERROR
        else:
            print('Setting status to disabled')
            return STATUS_DISABLED + self.__incognito


    """ Get Detailed Status
        Print out the status
    Args:
        config['status'], config['unpause_time']
    Returns:
        None
    """
    def get_detailedstatus(self, confstatus, confunpause_time):
        status = int(confstatus)
        unpause_time = int(confunpause_time)

        mainexists = os.path.isfile(self.__blocklist)
        tempexists = os.path.isfile(self.__templist)

        print ('Checking NoTrack Status:')

        if status & STATUS_ENABLED and mainexists:
            print('Status: Enabled')
        elif status & STATUS_ENABLED:
            print('Status: Should be enabled, but blocklist is missing')

        if status & STATUS_DISABLED and tempexists:
            print('Status: Disabled')
        elif status & STATUS_DISABLED:
            print('Status: Should be disabled, but temp blocklist is missing')

        if status & STATUS_PAUSED and tempexists:
            print('Status: Paused until %s' % time.ctime(unpause_time))
        elif status & STATUS_PAUSED:
            print('Status: Should be paused, but temp blocklist is missing')

        if status & STATUS_INCOGNITO:
            print('Status: Incognito')

        print()

    """ Get Status
        Confim system status is as config['status'] specifies.
        e.g. main or temp blocklist could be missing
    Args:
        config['status']
    Returns:
        None
    """
    def get_status(self, confstatus):
        status = int(confstatus)

        mainexists = os.path.isfile(self.__blocklist)
        tempexists = os.path.isfile(self.__templist)

        if status & STATUS_INCOGNITO:
            self.__incognito = STATUS_INCOGNITO

        if status & STATUS_ENABLED and mainexists:
            return STATUS_ENABLED
        elif status & STATUS_ENABLED:
            return STATUS_ERROR

        if status & STATUS_DISABLED and tempexists:
            return STATUS_DISABLED
        elif status & STATUS_DISABLED:
            return STATUS_ERROR

        if status & STATUS_PAUSED and tempexists:
            return STATUS_PAUSED
        elif status & STATUS_PAUSED:
            return STATUS_ERROR

        return STATUS_ERROR                                #Shouldn't get here

