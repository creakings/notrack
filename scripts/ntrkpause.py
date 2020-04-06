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
STATUS_ENABLED = 1
STATUS_DISABLED = 2
STATUS_PAUSED = 4
STATUS_INCOGNITO = 8
STATUS_NOTRACKRUNNING = 64 #DEPRECATED
STATUS_ERROR = 128

class NoTrackPause:
    """
    Provides the ability to change blocking state of NoTrack by moving the blocklist file
    to a temporary location.
    NoTrack Status options:
        Enabled / Play: NoTrack block list is placed in /etc/dnsmasq.d/notrack.list
        Disabled / Stop: NoTrack block list is placed in /tmp/notracktemp.list
        Paused: NoTrack block list is placed in /tmp/notracktemp.list and a sleep timer is
                run for the specified pause time. Pause can be ended with SIGABRT, SIGTERM or SIGINT
        Error: Expected status does not match the actual block list file location

    Parameters:
        tempdir (str): folders.temp
        main_blocklist (str): folders.main_blocklist
    """
    def __init__(self, tempdir, main_blocklist):
        self.__incognito = 0                               #Hold users incognito state
        self.__templist = ''                               #Location for temp block list
        self.__blocklist = ''                              #Location for main block list
        self.__end_pause = False                           #When to end wait
        self.__enable_blocking = True                      #Enable blocking after pause
        self.__pausetime = 0
        self.__templist = tempdir + 'notracktemp.list'
        self.__blocklist = main_blocklist

        #Assign Exit Signals
        signal.signal(signal.SIGINT, self.__exit_gracefully)  #2 Inturrupt = Pause > Play
        signal.signal(signal.SIGABRT, self.__exit_gracefully) #6 Abort = Pause > Stop
        signal.signal(signal.SIGTERM, self.__exit_gracefully) #9 Terminate = Pause > Play


    def __backup_list(self):
        """
        Move block list from etc to temp
        """
        return move_file(self.__blocklist, self.__templist)


    def __restore_list(self):
        """
        Move block list from temp to etc
        """
        return move_file(self.__templist, self.__blocklist)


    def __exit_gracefully(self, signum, frame):
        """
        Ends pause wait and moves NoTrack to a new state - Enabled or Disabled depending
        on the signal value received

        Parameters:
            signum (int): Signal
            frame (int): Frame
        """
        if signum == signal.SIGABRT:                       #Abort moves state to Disabled
            self.__enable_blocking = False

        self.__end_pause = True                            #Trigger breakout of wait loop


    def __parent_running(self):
        """
        Get current pid and script name
        Run pgrep -a python3 - look for instances of python3 running
        Look through results of above command
        Check for my script name not equalling my pid

        Returns:
            0 no other instances running
            >0 first match of another instance
        """
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
        """
        Find PID of the NoTrack script currently in a paused state
        Send appropriate signal to kill process
        Pause to prevent a race condition where closing process could overwrite the config

        Parameters:
            resume_blocking (boolean): If True, send SIGINT signal. If False, send SIGABRT signal
        """
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


    def disable_blocking(self, confstatus):
        """
        Verify current status
        Check what state we are moving from into disabled
        Move blocklist to temp

        Parameters:
            confstatus (str): config['status']
        Returns:
            STATUS_DISABLED + STATUS_INCOGNITO (if set) on success
            STATUS_ERROR when something has gone wrong
        """
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


    def enable_blocking(self, confstatus):
        """
        Verify current status
        Check what state we are moving from into enabled
        Move blocklist to temp

        Parameters:
            confstatus (str): config['status']
        Returns:
            STATUS_ENABLED + STATUS_INCOGNITO (if set) on success
            STATUS_ERROR when something has gone wrong
        """
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


    def pause_blocking(self, confstatus, mins):
        """
        Calculate unpause time
        Verify current status
        Check what state we are moving from into paused
        Move blocklist to temp

        Parameters:
            confstatus (str): config['status']
            mins (int): Pause time in minutes
        Returns:
            [STATUS_ENABLED + STATUS_INCOGNITO (if set) on success, Unix time to unpause]
            [STATUS_ERROR when something has gone wrong, 0]
        """
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


    def wait(self):
        """
        This function allows pause_blocking
        1. Loop until unpause time is reached or signal received to short
        2. Setup new status depending whether __end_pause is set to True or False

        Returns:
            New STATUS + STATUS_INCOGNITO (if set) on success
            STATUS_ERROR when something has gone wrong
        """
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


    def get_detailedstatus(self, confstatus, confunpause_time):
        """
        Print out the status

        Parameters:
            confstatus (str): config['status']
            confunpause_time (int): config['unpause_time']
        """
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


    def get_status(self, confstatus):
        """
        Confirm system status is as config['status'] specifies
        e.g. main or temp blocklist could be missing

        Parameters:
            confstatus (str): config['status']
        Returns:
            Actual Status
        """
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

