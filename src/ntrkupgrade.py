#!/usr/bin/env python3
#Title       : NoTrack Upgrade
#Description : This script carries out upgrade for NoTrack
#Author      : QuidsUp
#Date        : 2020-03-28
#Version     : 0.9.6
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
        webconfigdir (str): folders.webconfigdir
    """

    def __init__(self, ntrkfolders):

        #Set folder locations
        #self.__REPO = 'https://gitlab.com/quidsup/notrack.git'
        self.__REPO = 'https://github.com/quidsup/NoTrack'
        #self.__GITLAB_DOWNLOAD = 'https://gitlab.com/quidsup/notrack/-/archive/master/notrack-master.zip'
        self.__TEMPDIR = tempdir
        self.__WEBCONFDIR = webconfigdir
        self.__TEMP_DOWNLOAD = tempdir + 'notrack-master.zip'

        self.__latestversion = ''
        self.location = ''
        self.username = ''

        self.__find_notrack()                    #Where has NoTrack been installed?
        self.__find_username()                   #Get username for the install location


    def __find_notrack(self):
        """
        Based on current working directory
        """
        cwd = os.getcwd()                                  #Get current working directory
        self.location = os.path.dirname(cwd)               #Get parent folder


    def __find_username(self):
        """
        Find username depending on OS Type
        TODO Complete for other OS's
        """
        if os.name == 'posix':
            self.username = self.__find_unix_username()


    def __find_unix_username(self):
        """
        Match the home folder against username with data from /etc/passwd

        Parameters:
            None
        Returns:
            username or root
        """
        import pwd
        passwd = pwd.getpwall()                            #Everything from /etc/passwd

        if not self.location.startswith('/home'):  #Return root for any non-home directory
            return 'root'

        for obj in passwd:
            if obj.pw_dir == '/':                          #Disregard anything for root folder
                continue
            #Check if there is any match with this users home folder location
            if self.location.startswith(obj.pw_dir):
                return obj.pw_name                         #Yes, return username

        return 'root'                                      #No match found, return root


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

        cmd = ['sudo', '-u', self.username, 'git', 'clone', self.__REPO, self.location]
        print('Cloning NoTrack into %s with Git' % self.location)

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

        p = subprocess.run(cmd, cwd=self.location, stdout=subprocess.PIPE, stderr=subprocess.PIPE, universal_newlines=True)

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
        4. Move /tmp/notrack-master to location
        """

        temp_dldir = self.__TEMPDIR + 'notrack-master'

        #Download notrack-master.zip
        if not download_file(self.__GITLAB_DOWNLOAD, self.__TEMP_DOWNLOAD):
            print('Unable to download from Gitlab', file=sys.stderr)
            sys.exit(22)

        #Unzip notrack-master to /tmp
        unzip_multiple_files(self.__TEMP_DOWNLOAD, self.__TEMPDIR)

        #Delete old backup of NoTrack and then move current folder to backup
        #delete_folder(self.location + '-old')      #Delete backup copy
        #copy_file(self.location, self.location + '-old')

        #Delete old contents of NoTrack folder
        delete_folder(self.location + '/admin')
        delete_folder(self.location + '/conf')
        delete_folder(self.location + '/scripts')
        delete_folder(self.location + '/sink')
        delete_file(self.location + '/changelog.txt')
        delete_file(self.location + '/install.sh')
        delete_file(self.location + '/LICENSE')
        delete_file(self.location + '/README.md')
        delete_file(self.location + '/TODO')

        #Move new files to NoTrack folder
        move_file(temp_dldir + '/admin', self.location)
        move_file(temp_dldir + '/conf', self.location)
        move_file(temp_dldir + '/scripts', self.location)
        move_file(temp_dldir + '/sink', self.location)
        move_file(temp_dldir + '/changelog.txt', self.location)
        move_file(temp_dldir + '/install.sh', self.location)
        move_file(temp_dldir + '/LICENSE', self.location)
        move_file(temp_dldir + '/README.md', self.location)
        move_file(temp_dldir + '/TODO', self.location)

        delete_file(temp_dldir)


    def __check_for_upgrade(self):
        """
        Check for upgrade
        Extract latestversion variable from bl_notrack.txt
        """
        print('Checking what the latest version of NoTrack is')

        #Is bl_notrack.txt available?
        if not Path(self.__TEMPDIR + 'bl_notrack.txt').is_file():
            print('Temporary copy of NoTrack block list is not available')
            print('I don\'t know what the latest version of NoTrack is :-(')
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

    def __reset_latest_version(self):
        """
        Reset PHP latest version setting file
        """
        print('Resetting latestversion.php')
        with open (self.__WEBCONFDIR + 'latestversion.php', 'w') as f:
            f.write('<?php\n')
            f.write("$config->set_latestversion('0.0');\n")
            f.write('?>\n')
            f.close()                                      #Close file

        os.chmod(self.__WEBCONFDIR + 'latestversion.php', 0o666) #-rw-rw-rw

        return True                                        #New version updated at this point


    def __update_latest_version(self):
        """
        Update PHP latest version setting file

        Returns:
            True - New version available
            False - Running current version
        """
        if self.__latestversion == '':                     #Failed to get latest version
            return False

        if self.__latestversion == VERSION:                #Already latest version
            print('Running current version', VERSION)
            return False

        print('New version available:', self.__latestversion)
        print('Updating latestversion.php')
        with open (self.__WEBCONFDIR + 'latestversion.php', 'w') as f:
            f.write('<?php\n')
            f.write("$config->set_latestversion('%s');\n" % self.__latestversion)
            f.write('?>\n')
            f.close()                                      #Close file

        os.chmod(self.__WEBCONFDIR + 'latestversion.php', 0o666) #-rw-rw-rw

        return True                                        #New version updated at this point


    def do_upgrade(self):
        """
        Do Upgrade
        1. Download latest updates
        2. Check if mysql module is available
        3a. If it is install Python3 version of NoTrack
        3b. Otherwise fallback to using the legacy Bash version
        4. Reset latest version
        """

        print('Upgrading NoTrack')

        if self.__check_git():
            if not self.__git_pull():
                self.__git_clone()

        else:
            self.__download_from_git()

        if check_module('mysql.connector'):
            self.__modern_check_localsbin()
        else:
            self.__legacy_copyto_localsbin()

        self.__reset_latest_version()                      #Zero out the latest version


    def get_latestversion(self):
        """
        Returns the latest version
        Will also update PHP settings

        Returns:
            True - New version available
            False - Running current version
        """
        if self.__latestversion == '':                     #Is latest version known?
            self.__check_for_upgrade()                     #Read bl_notrack.txt

        return self.__update_latest_version()              #Update PHP settings


def main():
    folders = FolderList()
    ntrkupgrade = NoTrackUpgrade(folders.tempdir, folders.webconfigdir)


    print('NoTrack Upgrader')
    #check_root()

    print('Found Install Location:', ntrkupgrade.location)
    print('Found Username:', ntrkupgrade.username)
    print()

    #ntrkupgrade.get_latestversion()
    #ntrkupgrade.do_upgrade()

    print('NoTrack upgrade complete :-)')

if __name__ == "__main__":
    main()


