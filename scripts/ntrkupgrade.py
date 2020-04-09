#!/usr/bin/env python3
#Title       : NoTrack Upgrade
#Description : This script carries out upgrade for NoTrack
#Author      : QuidsUp
#Date        : 2020-03-28
#Version     : 0.9.5
#Usage       : sudo python3 ntrkupgrade.py

#Standard imports
from pathlib import Path, PurePath
import os
import shutil
import subprocess
import sys

#Local imports
from ntrkfolders import FolderList
from ntrkregex import Regex_Version
from ntrkshared import *


class NoTrackUpgrade():
    """
    Class to carry out upgrade of NoTrack
    Finds where NoTrack is installed
    Finds the username for NoTrack install location
    Downloads latest version of NoTrack either via Git or HTTPS download from Gitlab
    Checks if Python3 mysql.connector is installed
    Copies either modern Python3 or legacy Bash script to /usr/local/sbin

    Parameters:
        tempdir (str): folders.tempdir
        sbindir (str): folders.sbindir
        wwwconfdir (str): folders.wwwconfdir
    """

    def __init__(self, tempdir, sbindir, wwwconfdir):

        #Set folder locations
        self.__REPO = 'https://gitlab.com/quidsup/notrack.git'
        self.__GITLAB_DOWNLOAD = 'https://gitlab.com/quidsup/notrack/-/archive/master/notrack-master.zip'
        self.__SBINDIR = sbindir
        self.__TEMPDIR = tempdir
        self.__WWWCONFDIR = wwwconfdir
        self.__TEMP_DOWNLOAD = tempdir + 'notrack-master.zip'

        self.__latestversion = ''
        self.install_location = ''
        self.username = ''

        self.__find_notrack()                    #Where has NoTrack been installed?
        self.__find_username()                   #Get username for the install_location


    def __is_symlink(self, item):
        """
        Check if item is either a directory or file

        Parameters:
            item (str): Symlink location
        Returns:
            True valid symlink
            False not a symlink or target missing
        """
        if Path(item).is_dir() and Path(item).is_symlink():
            return True
        elif Path(item).is_file() and Path(item).is_symlink():
            return True

        return False


    def __read_symlink(self, item):
        """
        Returns parent directory of a symlink location

        Parameters:
            item (str): Symlink location
        Returns:
            Target parent e.g. (/home/user/notrack or /opt/notrack)
        """
        target = Path(item).resolve()                      #Get symlink target
        p = PurePath(target)                               #Path of target

        if Path(target).is_dir():                          #Directory returns parent
            return str(p.parent)
        else:
            return str(p.parent.parent)                    #File returns grandparent


    def __check_homefolders(self):
        """
        Check sub directories under /home for presense of /home/user/notrack directory
        """
        if not Path('/home').is_dir():
            return False

        p = Path('/home')
        for subdir in p.iterdir():
            if Path('%s/notrack' % subdir).is_dir():
                self.install_location = '%s/notrack' % subdir
                return True


    def __find_notrack(self):
        """
        Find where NoTrack has been installed
        There are a few methods to try and locate the NoTrack install folder
        Check symlink locations, check in /opt, finally check home folders
        """
        if self.__is_symlink('/var/www/html/admin'):       #1. Try admin symlink
            self.install_location = self.__read_symlink('/var/www/html/admin')
        elif self.__is_symlink('/usr/local/sbin/notrack'): #2. Try sbin/admin symlink
            self.install_location = self.__read_symlink('/usr/local/sbin/notrack')
        elif Path('/opt/notrack').is_dir():                #3. Check in /opt
            self.install_location = '/opt/notrack'
        elif not self.__check_homefolders():               #4. Check home folders
            print('Find_NoTrack: Error - Unable to find location of NoTrack', file=sys.stderr)
            sys.exit(20)


    def __find_unix_username(self, ntrkdir):
        """
        Match the home folder against username with data from /etc/passwd

        Parameters:
            ntrkdir (str): NoTrack install directory
        Returns:
            username or root
        """
        import pwd
        passwd = pwd.getpwall()                            #Everything from /etc/passwd

        for obj in passwd:
            #Check if there is any match with this users home folder location
            if ntrkdir.startswith(obj.pw_dir):
                return obj.pw_name                         #Yes, return username

        return 'root'                                      #No match found, return root


    def __find_username(self):
        """
        Find username depending on OS Type
        TODO Complete for other OS's
        """
        if os.name == 'posix':
            self.username = self.__find_unix_username(self.install_location)


    def __check_git(self):
        """
        Checks if git is available
        """
        return shutil.which('git')


    def __git_clone(self):
        """
        Attempt a Git Clone as a fallback when Git pull fails
        Switch from using root to appropriate username

        Returns:
            True on success
            False when Git has returned a non-zero code
        """
        cmd = []                                           #Command to execute

        cmd = ['sudo', '-u', self.username, 'git', 'clone', self.__REPO, self.install_location]
        print('Cloning NoTrack into %s with Git' % self.install_location)

        p = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, universal_newlines=True)

        if p.returncode == 0:                              #Success
            print(p.stdout)
            return True
        elif p.returncode == 1:                            #Fatal Error
            print(p.stderr)
            print('Git_Clone: Error - Not continuing')
            sys.exit(21)

        print(p.stderr)                                    #Something wrong
        return False


    def __git_pull(self):
        """
        Attempt a Git Pull
        Switch from using root to appropriate username

        Returns:
            True on success
            False when Git has returned a non-zero code
        """
        cmd = []                                           #Command to execute

        cmd = ['sudo', '-u', self.username, 'git', 'pull']
        print('Pulling latest changes')

        p = subprocess.run(cmd, cwd=self.install_location, stdout=subprocess.PIPE, stderr=subprocess.PIPE, universal_newlines=True)

        if p.returncode == 0:                              #Success
            print(p.stdout)
            return True
        elif p.returncode == 1:                            #Fatal Error
            print(p.stderr)
            print('Git_Pull: Error - Not continuing')
            sys.exit(21)

        print(p.stderr)                                    #Something wrong
        return False


    def __download_from_git(self):
        """
        Fallback to downloading zip file from Gitlab when Git is not installed
        1. Download notrack-master.zip file from Gitlab
        2. Backup install location to x-old
        3. Unzip notrack-master.zip to /tmp/notrack-master
        4. Move /tmp/notrack-master to install_location
        """

        #Download notrack-master.zip
        if not download_file(self.__GITLAB_DOWNLOAD, self.__TEMP_DOWNLOAD):
            print('Unable to download from Gitlab', file=sys.stderr)
            sys.exit(22)

        #Unzip notrack-master
        unzip_multiple_files(self.__TEMP_DOWNLOAD, self.__TEMPDIR + 'notrack-master')

        #Delete old backup of NoTrack and then move current folder to backup
        delete_folder(self.install_location + '-old')      #Delete backup copy
        move_file(self.install_location, self.install_location + '-old')

        #Move extracted notrack-master
        if not move_file(self.__TEMPDIR + 'notrack-master', self.install_location):
            print('Download missing, restoring backup')
            move_file(self.install_location + '-old', self.install_location)
            print('Unzip of notrack-master.zip failed', file=sys.stderr)
            sys.exit(22)


    def __legacy_copyto_localsbin(self):
        """
        Legacy copy scripts to /usr/local/sbin
        NOTE remove sometime in 2021 as this method is DEPRECATED
        """
        scriptfolder = self.install_location + '/scripts/'

        print()
        print('WARNING: Missing Python3 dependancy mysql.connector')
        print('Falling back to using legacy Bash scripts')
        print()

        #NoTrack
        delete_file(self.__SBINDIR + 'notrack')
        print('Copying notrack.sh to %snotrack' % self.__SBINDIR)
        copy_file(scriptfolder + 'notrack.sh', self.__SBINDIR + 'notrack.sh')
        move_file(self.__SBINDIR + 'notrack.sh', self.__SBINDIR + 'notrack', 0o775)
        print()

        #NoTrack Analytics
        delete_file(self.__SBINDIR + 'ntrk-analytics')
        print('Copying ntrk-analytics.sh to %sntrk-analytics' % self.__SBINDIR)
        copy_file(scriptfolder + 'ntrk-analytics.sh', self.__SBINDIR + 'ntrk-analytics.sh')
        move_file(self.__SBINDIR + 'ntrk-analytics.sh', self.__SBINDIR + 'ntrk-analytics', 0o775)
        print()

        #NoTrack Exec
        delete_file(self.__SBINDIR + 'ntrk-exec')
        print('Copying ntrk-exec.sh to %sntrk-exec' % self.__SBINDIR)
        copy_file(scriptfolder + 'ntrk-exec.sh', self.__SBINDIR + 'ntrk-exec.sh')
        move_file(self.__SBINDIR + 'ntrk-exec.sh', self.__SBINDIR + 'ntrk-exec', 0o775)
        print()

        #NoTrack Parse
        delete_file(self.__SBINDIR + 'ntrk-parse')
        print('Copying ntrk-parse.sh to %sntrk-parse' % self.__SBINDIR)
        copy_file(scriptfolder + 'ntrk-parse.sh', self.__SBINDIR + 'ntrk-parse.sh')
        move_file(self.__SBINDIR + 'ntrk-parse.sh', self.__SBINDIR + 'ntrk-parse', 0o775)
        print()

        #NoTrack Pause
        delete_file(self.__SBINDIR + 'ntrk-pause')
        print('Copying ntrk-pause.sh to %sntrk-pause' % self.__SBINDIR)
        copy_file(scriptfolder + 'ntrk-pause.sh', self.__SBINDIR + 'ntrk-pause.sh')
        move_file(self.__SBINDIR + 'ntrk-pause.sh', self.__SBINDIR + 'ntrk-pause', 0o775)
        print()

        #NoTrack Upgrade
        delete_file(self.__SBINDIR + 'ntrk-upgrade')
        print('Copying ntrkupgrade.py to %sntrk-upgrade' % self.__SBINDIR)
        copy_file(scriptfolder + 'ntrkupgrade.py', self.__SBINDIR + 'ntrkupgrade.py')
        move_file(self.__SBINDIR + 'ntrkupgrade.py', self.__SBINDIR + 'ntrk-upgrade', 0o775)
        print()


    def __modern_check_localsbin(self):
        """
        Modern utilise symlinks to Python scripts, appeared in v0.9.5
        1. Delete old NoTrack files from /usr/local/sbin
        2. Change permissions of scripts to 775
        3. Create symlink in /usr/local/sbin pointing to scripts folder
        """
        scriptfolder = self.install_location + '/scripts/'

        print('Using modern NoTrack Python3 scripts')
        print()

        #NoTrack
        delete_file(self.__SBINDIR + 'notrack')
        print('Creating %snotrack symlink' % self.__SBINDIR)
        os.chmod(scriptfolder + 'notrack.py', 0o775)
        os.symlink(scriptfolder + 'notrack.py', self.__SBINDIR + 'notrack')
        print()

        #NoTrack Analytics NOTE This is still a bash script
        delete_file(self.__SBINDIR + 'ntrk-analytics')
        print('Copying ntrk-analytics.sh to %sntrk-analytics' % self.__SBINDIR)
        copy_file(scriptfolder + 'ntrk-analytics.sh', self.__SBINDIR + 'ntrk-analytics.sh')
        move_file(self.__SBINDIR + 'ntrk-analytics.sh', self.__SBINDIR + 'ntrk-analytics', 0o775)
        print()

        #NoTrack Exec
        delete_file(self.__SBINDIR + 'ntrk-exec')
        print('Creating %sntrk-exec symlink' % self.__SBINDIR)
        os.chmod(scriptfolder + 'ntrk-exec.py', 0o775)
        os.symlink(scriptfolder + 'ntrk-exec.py', self.__SBINDIR + 'ntrk-exec')
        print()

        #NoTrack Parse NOTE This is still a bash script
        delete_file(self.__SBINDIR + 'ntrk-parse')
        print('Copying ntrk-parse.sh to %sntrk-parse' % self.__SBINDIR)
        copy_file(scriptfolder + 'ntrk-parse.sh', self.__SBINDIR + 'ntrk-parse.sh')
        move_file(self.__SBINDIR + 'ntrk-parse.sh', self.__SBINDIR + 'ntrk-parse', 0o775)
        print()

        #NoTrack Upgrade
        delete_file(self.__SBINDIR + 'ntrk-upgrade')
        print('Creating %sntrk-upgrade symlink' % self.__SBINDIR)
        os.chmod(scriptfolder + 'ntrkupgrade.py', 0o775)
        os.symlink(scriptfolder + 'ntrkupgrade.py', self.__SBINDIR + 'ntrk-upgrade')
        print()

        #NOTE NoTrack Pause is no longer required
        delete_file(self.__SBINDIR + 'ntrk-pause')


    def __check_for_upgrade(self):
        """
        Check for upgrade
        Extract latestversion variable from bl_notrack.txt
        """
        print('Checking what the latest version is of NoTrack')

        #Is bl_notrack.txt available?
        if not Path(self.__TEMPDIR + 'bl_notrack.txt').is_file():
            print('Check_For_Upgrade: Error - Temporary copy of NoTrack block list is not available')
            print('Either the file is missing due to a system reboot, or NoTrack block list is not enabled')
            print('To enable bl_notrack:')
            print('Edit /etc/notrack/notrack.conf, set bl_notrack = 1')
            print()
            return

        #Open the temp bl_notrack.txt file
        with open (self.__TEMPDIR + 'bl_notrack.txt', 'r') as f:
            for line in f:
                if Regex_Version.findall(line):            #Use regex to find the correct line
                    self.__latestversion = Regex_Version.findall(line)[0]
                    break                                  #No further reading required

            f.close()                                      #Close file


    def __update_latest_version(self):
        """
        Update PHP latest version setting file
        """
        if self.__latestversion == '':                     #Failed to get latest version
            return

        if self.__latestversion == VERSION:                #Already latest version
            print('Running current version', VERSION)
            return

        print('New version available:', self.__latestversion)
        print('Print updating latestversion.php')
        with open (self.__WWWCONFDIR + 'latestversion.php', 'w') as f:
            f.write('<?php\n')
            f.write("$config->LatestVersion = '%s';\n" % self.__latestversion)
            f.write('?>\n')
            f.close()                                      #Close file

        print('Setting permissions of latestversion.php to -rw-rw-rw')
        os.chmod(self.__WWWCONFDIR + 'latestversion.php', 0o666)


    def do_upgrade(self):
        """
        Do Upgrade
        1. Download latest updates
        2. Check if mysql module is available
        3a. If it is install Python3 version of NoTrack
        3b. Otherwise fallback to using the legacy Bash version
        """

        if self.__check_git():
            if not self.__git_pull():
                self.__git_clone()

        else:
            self.__download_from_git()

        if check_module('mysql.connector'):
            self.__modern_check_localsbin()
        else:
            self.__legacy_copyto_localsbin()


    def get_latestversion(self):
        """
        Returns the latest version
        Will also update PHP settings
        """
        if self.__latestversion == '':                     #Is latest version known?
            self.__check_for_upgrade()                     #Read bl_notrack.txt

        self.__update_latest_version()                     #Update PHP settings

        return self.__latestversion



def main():
    folders = FolderList()
    ntrkupgrade = NoTrackUpgrade(folders.tempdir, folders.sbindir, folders.wwwconfdir)

    print('NoTrack Upgrader')
    check_root()

    print('Found Install Location:', ntrkupgrade.install_location)
    print('Found Username:', ntrkupgrade.username)
    print()

    #ntrkupgrade.get_latestversion()
    ntrkupgrade.do_upgrade()

    print('NoTrack upgrade complete :-)')

if __name__ == "__main__":
    main()

"""


#sudocheck=$(grep www-data /etc/sudoers)                              #Check sudo permissions for lighty possibly DEPRECATED
#if [[ $sudocheck == "" ]]; then
  #echo "Adding NoPassword permissions for www-data to execute script /usr/local/sbin/ntrk-exec as root"
  #echo -e "www-data\tALL=(ALL:ALL) NOPASSWD: /usr/local/sbin/ntrk-exec" | tee -a /etc/sudoers
#fi
"""
