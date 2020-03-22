#NoTrackPause
#Author: QuidsUp
#Class provides functions to Enable / Disable / Pause blocking of NoTrack by moving
# notrack.list block list from DNS server config into temp folder.
#When paused sleep function is utilised to delay return from disabled to enabled state.
#Sending signal into the running script will end the pause gracefully and return to
# either Enabled or Disabled.
#SIGINT / SIGTERM = Paused > Enable
#SIGABRT = Paused > Enable

#Standard imports
import os
import re
import signal
import subprocess
import time

#Local imports
from ntrkshared import move_file

#Constants
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
    __end_pause = False                                    #When to end wait
    __enable_blocking = True                               #Enable blocking after pause
    __pausetime = 0


    """ NoTrack Pause Initialisation
        1. Store block list file locations
        2. Assign __exit_gracefully function to Signal Inturrupt, Abort and Terminate
    Args:
        temp folder from folders.temp
        main blocklist from folders.main_blocklist
    Returns:
        None
    """
    def __init__(self, tempfolder, main_blocklist):
        self.__templist = tempfolder + 'notracktemp.list'
        self.__blocklist = main_blocklist

        signal.signal(signal.SIGINT, self.__exit_gracefully)  #2 Inturrupt = Pause > Play
        signal.signal(signal.SIGABRT, self.__exit_gracefully) #6 Abort = Pause > Stop
        signal.signal(signal.SIGTERM, self.__exit_gracefully) #9 Terminate = Pause > Play


    """ Backup List
    """
    def __backup_list(self):
        return move_file(self.__blocklist, self.__templist)


    """ Restore List
    """
    def __restore_list(self):
        return move_file(self.__templist, self.__blocklist)


    """ Exit Gracefully
        Ends pause wait and moves NoTrack to a new state - Enabled or Disabled depending
        on the signal value received.
    Args:
        Signal, Frame
    Returns:
        None
    """
    def __exit_gracefully(self, signum, frame):
        if signum == signal.SIGABRT:                       #Abort moves state to Disabled
            self.__enable_blocking = False

        self.__end_pause = True                            #Trigger breakout of wait loop


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


    """ Terminate Pause
        1. Find PID of the NoTrack script currently in a Paused state
        2. Send appropriate signal to kill process
        3. Pause to prevent a race condition where closing process could overwrite the config
    Args:
        resume_blocking - True, send SIGINT signal
        resume_blocking - False, send SIGABRT signal
    Returns:
        None
    """
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
        time.sleep(0.5)                                    #Prevent race condition


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

        if status == STATUS_ENABLED:                       #Enabled > Disabled
            print('Changing status from enabled to disabled')
            if self.__backup_list():                       #List backed up successfully?
                print('Block list moved to temp folder')
            else:                                          #No - Return Error
                print('Disable_blocking: Warning - Failed to backup list')
                return STATUS_ERROR
        elif status == STATUS_PAUSED:                      #Paused > Disabled
            print('Changing status from paused to disabled')
            self.__terminate_pause(False)
        elif status == STATUS_DISABLED:                    #Disabled > Disabled
            print('Status is already disabled')
        elif status == STATUS_ERROR:                       #Error
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

        if status == STATUS_ENABLED:                       #Enabled > Enabled
            print('Status is already enabled')
        elif status == STATUS_PAUSED:                      #Paused > Enabled
            print('Changing status from paused to enabled')
            self.__terminate_pause(True)
        elif status == STATUS_DISABLED:                    #Disabled > Enabled
            print('Changing status from disabled to enabled')
            if self.__restore_list():                      #List restored successfully?
                print('Block list returned')
            else:                                          #No - Return Error
                print('Enable_blocking: Warning - Failed to restore list')
                return STATUS_ERROR
        elif status == STATUS_ERROR:                       #Error
            print('Status in error')
            return STATUS_ERROR

        return STATUS_ENABLED + self.__incognito


    """ Pause Blocking
        1. Calculate unpause time
        2. Verify current status
        3. Check what state we are moving from into paused
        4. Move blocklist to temp
    Args:
        config['status'], Pause time in minutes
    Returns:
        [STATUS_PAUSED + STATUS_INCOGNITO (if set) on success, Unix time to unpause]
        [STATUS_ERROR when something has gone wrong, 0]
    """
    def pause_blocking(self, confstatus, mins):
        unpause_time = 0                                   #Unix time in seconds

        self.__pausetime = mins * 60                       #Calculate how many seconds to pause for
        unpause_time = int(time.time() + self.__pausetime) #Round unix time
        status = self.get_status(confstatus)               #Check current status

        print('Pause Blocking for %d minutes' % mins)

        if status == STATUS_ENABLED:                       #Enabled > Paused
            print('Changing status from enabled to paused')
            if self.__backup_list():                       #List backed up successfully?
                print('Block list moved to temp folder')
            else:                                          #No - Return Error
                print('Disable_blocking: Warning - Failed to backup list')
                return [STATUS_ERROR, 0]
        elif status == STATUS_PAUSED:                      #Paused > Paused
            print('Changing pause time')
            self.__terminate_pause(False)                  #Stop old pause process
        elif status == STATUS_DISABLED:                    #Disabled > Paused
            print('Changing status from disabled to paused')
        elif status == STATUS_ERROR:                       #Error
            print('Status in error')
            return [STATUS_ERROR, 0]

        return [STATUS_PAUSED + self.__incognito, unpause_time]


    """ Wait
        This function follows pause_blocking
        1. Loop until unpause time is reached or signal received to abort
        2. Setup new status based depending whether __end_pause is set to True or False
    Args:
        None
    Returns:
        New STATUS + STATUS_INCOGNITO (if set) on success
        STATUS_ERROR when something has gone wrong
    """
    def wait(self):
        i = 0
        newstatus = 0                                      #New status after loop ended

        print('Waiting...')
        while not self.__end_pause:                        #Wait until end condition is met
            time.sleep(1)                                  #Sleep 1 second
            i += 1
            if i > self.__pausetime:                       #Has __pausetime been reached?
                self.__end_pause = True                    #Yes - Leave on next pass

        print('Ending pause')

        if self.__enable_blocking:                         #Pause > Enabled
            if self.__restore_list():                      #List restored successfully?
                print('Block list returned')
                return STATUS_ENABLED + self.__incognito
            else:                                          #No - Return Error
                print('Enable_blocking: Warning - Failed to restore list')
                return STATUS_ERROR
        else:                                              #Pause > Disabled / Pause
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

        if status & STATUS_ENABLED and mainexists:         #Check Enabled Status
            print('Status: Enabled')
        elif status & STATUS_ENABLED:
            print('Status: Should be enabled, but blocklist is missing')

        if status & STATUS_DISABLED and tempexists:        #Check Disabled Status
            print('Status: Disabled')
        elif status & STATUS_DISABLED:
            print('Status: Should be disabled, but temp blocklist is missing')

        if status & STATUS_PAUSED and tempexists:          #Check Paused Status
            print('Status: Paused until %s' % time.ctime(unpause_time))
        elif status & STATUS_PAUSED:
            print('Status: Should be paused, but temp blocklist is missing')

        if status & STATUS_INCOGNITO:                      #Check Incognito Status
            print('Status: Incognito')
        print()


    """ Get Status
        Confim system status is as config['status'] specifies.
        e.g. main or temp blocklist could be missing
    Args:
        config['status']
    Returns:
        STATUS
    """
    def get_status(self, confstatus):
        status = int(confstatus)

        mainexists = os.path.isfile(self.__blocklist)
        tempexists = os.path.isfile(self.__templist)

        if status & STATUS_INCOGNITO:                      #Store Incognito for later
            self.__incognito = STATUS_INCOGNITO

        if status & STATUS_ENABLED and mainexists:         #Check Enabled Status
            return STATUS_ENABLED
        elif status & STATUS_ENABLED:
            return STATUS_ERROR

        if status & STATUS_DISABLED and tempexists:        #Check Disabled Status
            return STATUS_DISABLED
        elif status & STATUS_DISABLED:
            return STATUS_ERROR

        if status & STATUS_PAUSED and tempexists:          #Check Paused Status
            return STATUS_PAUSED
        elif status & STATUS_PAUSED:
            return STATUS_ERROR

        return STATUS_ERROR                                #Shouldn't get here

