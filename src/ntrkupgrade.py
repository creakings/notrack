#!/usr/bin/env python3
#Title       : NoTrack Upgrade
#Description : This script carries out upgrade for NoTrack
#Author      : QuidsUp
#Date        : 2020-03-28
#Version     : 0.9.6
#Usage       : sudo python3 ntrkupgrade.py

#Standard imports
from pathlib import Path, PurePath
import logging
import os
import shutil
import subprocess
import sys

#Local imports
import folders
from ntrkregex import Regex_Version
from ntrkshared import *

#Create logger
logger = logging.getLogger(__name__)
logger.setLevel(logging.INFO)



class NoTrackUpgrade():
    """
    Class to carry out upgrade of NoTrack
    Finds where NoTrack is installed
    Finds the username for NoTrack install location
    Downloads latest version of NoTrack either via Git or HTTPS download from Gitlab
    Checks if Python3 mysql.connector is installed
    Copies either modern Python3 or legacy Bash script to /usr/local/sbin


    """

    def __init__(self):

        #Set folder locations
        self.__REPO = 'https://gitlab.com/quidsup/notrack.git'
        #self.__REPO = 'https://github.com/quidsup/NoTrack'
        self.__GIT_DOWNLOAD = 'https://gitlab.com/quidsup/notrack/-/archive/master/notrack-master.zip'

        #self.__TEMPDIR = folders.tempdir
        #self.__WEBCONFDIR = webconfigdir


        self.__latestversion = ''
        self.location = ''
        self.username = ''

        self.__webstat = get_owner(folders.webdir)

        self.__find_notrack()                    #Where has NoTrack been installed?
        self.__find_username()                   #Get username for the install location

        self.__find_latest_version()
        self.__compare_version()



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


    def __find_latest_version(self):
        """
        Get latest version value from bl_notrack.txt
        """

        #Is bl_notrack.txt available?
        if not Path(f'{folders.tempdir}/bl_notrack.txt').is_file():
            logger.warning('Temporary copy of NoTrack block list is not available, I don\'t know what the latest version of NoTrack is :-(')
            logger.warning('Either the file is missing due to a system reboot, or NoTrack block list is not enabled')
            return

        #Open the temp bl_notrack.txt file
        with open (f'{folders.tempdir}/bl_notrack.txt', 'r') as f:
            for line in f:
                if Regex_Version.findall(line):            #Use regex to find the correct line
                    self.__latestversion = Regex_Version.findall(line)[0]
                    logger.info(f'Latest version of NoTrack is {self.__latestversion}')
                    break                                  #No further reading required

            f.close()                                      #Close bl_notrack.txt


    def __compare_version(self):
        """
        Compare the current version against the latest version
        Use integer comparison and remove the dots from the string version
        """
        intcurrent = 0
        intlatest = 0

        intcurrent = int(VERSION.replace('.', ''))
        intlatest = int(self.__latestversion.replace('.', ''))

        if intcurrent == intlatest:
            print('Already running latest version of NoTrack')
            self.__notification_delete()
        elif intcurrent < intlatest:
            print(f'New version of NoTrack available {self.__latestversion}')
            self.__notification_create()
        else:
            print('Latest version of NoTrack is earlier than your version, ignoring')
            self.__notification_delete()


    def __notification_create(self):
        """
        Create latestversion.php with the necessary code
        """
        latestversionphp = f'{folders.webconfigdir}/latestversion.php'

        with open (latestversionphp, 'w') as f:
            f.write('<?php\n')
            f.write(f"$latestversion = '{self.__latestversion}';\n")
            f.write(f"echo $latestversion;\n")
            f.write('?>\n')
            f.close()                                      #Close latestversion.php

        set_owner(latestversionphp, self.__webstat.st_uid, self.__webstat.st_gid)
        os.chmod(latestversionphp, 0o644) #-rw-r-r


    def __notification_delete(self):
        """
        Delete latestversion.php
        """
        delete(f'{folders.webconfigdir}/latestversion.php')


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
        print(f'Cloning NoTrack into {self.location} with Git')

        p = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, universal_newlines=True)

        if p.returncode == 0:                              #Success
            print(p.stdout)
            return True
        elif p.returncode == 1:                            #Fatal Error
            logger.error(p.stderr)
            logger.error('Fatal error with Git Clone')
            return False

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
            logger.error(p.stderr)
            logger.error('Fatal error with Git Pull')
            return False

        logger.error(p.stderr)                             #Something wrong

        return self.__git_clone()                          #Try to clone instead


    def __download_from_git(self):
        """
        Fallback to downloading zip file from Gitlab when Git is not installed
        1. Download notrack-master.zip file from Gitlab
        2. Backup install location to x-old
        3. Unzip notrack-master.zip to /tmp/notrack-master
        4. Move /tmp/notrack-master to location
        """

        temp_dlzip = f'{folders.tempdir}/notrack-master.zip'
        temp_dldir = f'{folders.tempdir}/notrack-master'

        #Download notrack-master.zip
        if not download_file(self.__GIT_DOWNLOAD, temp_dlzip):
            print('Unable to download from Gitlab', file=sys.stderr)
            sys.exit(22)

        #Unzip notrack-master to /tmp
        unzip_multiple_files(temp_dlzip, f'{folders.tempdir}/')

        #Delete old backup of NoTrack and then move current folder to backup
        delete(f'{self.location}-old')      #Delete backup copy
        copy_file(self.location, f'{self.location}-old')

        #Delete old contents of NoTrack folder
        delete(f'{self.location}/admin')
        delete(f'{self.location}/conf')
        delete(f'{self.location}/src')
        delete(f'{self.location}/sink')
        delete(f'{self.location}/changelog.txt')
        delete(f'{self.location}/install.sh')
        delete(f'{self.location}/LICENSE')
        delete(f'{self.location}/README.md')
        delete(f'{self.location}/TODO')

        #Move new files to NoTrack folder
        move_file(f'{temp_dldir}/admin', self.location)
        move_file(f'{temp_dldir}/conf', self.location)
        move_file(f'{temp_dldir}/scripts', self.location)
        move_file(f'{temp_dldir}/src', self.location)
        move_file(f'{temp_dldir}/changelog.txt', self.location)
        move_file(f'{temp_dldir}/install.sh', self.location)
        move_file(f'{temp_dldir}/LICENSE', self.location)
        move_file(f'{temp_dldir}/README.md', self.location)
        move_file(f'{temp_dldir}/TODO', self.location)

        delete(temp_dldir)


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


    #def


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
                logger.warning('Code download failed, unable to continue')
                return

        #else:
        #    self.__download_from_git()

        self.__copy_webfiles()

        self.__notification_delete()


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
    print('NoTrack Upgrader')
    ntrkupgrade = NoTrackUpgrade()


    #check_root()

    #ntrkupgrade.get_latestversion()
    #ntrkupgrade.do_upgrade()

    print('NoTrack upgrade complete :-)')

if __name__ == "__main__":
    main()


