#!/usr/bin/env python3
#Title : NoTrack Upgrade
#Description :
#Author : QuidsUp
#Date : 2020-03-28
#Version : 0.9.5
#Usage : sudo python

from pathlib import Path, PurePath
import os
import shutil
import subprocess
import sys
import tempfile

#Local imports
from ntrkshared import *

class NoTrackUpgrade():
    """
    Class to carry out upgrade of NoTrack
    """

    def __init__(self):
        """
        Find location of NoTrack install folder
        """
        self.__REPO = 'https://gitlab.com/quidsup/notrack.git'
        self.__GITLAB_DOWNLOAD = 'https://gitlab.com/quidsup/notrack/-/archive/master/notrack-master.zip'
        self.__TEMP_DOWNLOAD = tempfile.gettempdir() + '/notrack-master.zip'

        self.install_location = ''
        self.username = ''

        self.__get_install_location()
        #Get username for the install_location (root if /opt/notrack or user for /home)
        self.username = self.__get_username(self.install_location)


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

    def __get_install_location(self):
        """
        Find where NoTrack has been installed
        There are a few methods to try and locate the NoTrack install folder
        """

        if self.__is_symlink('/var/www/html/admin'):       #1. Try admin symlink
            self.install_location = self.__read_symlink('/var/www/html/admin')
        elif self.__is_symlink('/usr/local/sbin/notrack'): #2. Try sbin/admin symlink
            self.install_location = self.__read_symlink('/usr/local/sbin/notrack')
        elif Path('/opt/notrack').is_dir():                #3. Check in /opt
            self.install_location = '/opt/notrack'
        elif not self.__check_homefolders():               #4. Check home folders
            print('NoTrackUpgrade: Error - Unable to find location of NoTrack')
            sys.exit(20)


    def __get_username(self, ntrkdir):
        """
        Find username depending on OS Type
        TODO Complete for other OS's
        """
        if os.name == 'posix':
            return self.__get_unix_username(ntrkdir)


    def __get_unix_username(self, ntrkdir):
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


    def __check_git(self):
        """
        Checks if git is available
        """
        return shutil.which('git')


    def __git_clone(self):
        """
        Attempt a Git Clone
        """
        cmd = ['sudo', '-u', self.username, 'git', 'clone', self.__REPO, self.install_location]
        print('Cloning NoTrack to %s with Git' % self.install_location)

        p = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, universal_newlines=True)

        if p.returncode == 0:                              #Success
            print(p.stdout)
            return True

        print(p.stderr)
        return False


    def __git_upgrade(self):
        """
        Attempt a Git Pull
        """
        cmd = ['sudo', '-u', self.username, 'git', 'pull']
        print('Pulling latest changes')

        p = subprocess.run(cmd, cwd=self.install_location, stdout=subprocess.PIPE, stderr=subprocess.PIPE, universal_newlines=True)

        if p.returncode == 0:                              #Success
            print(p.stdout)
            return True

        print(p.stderr)
        return False


    def __download_from_git(self):
        if not download_file(self.__GITLAB_DOWNLOAD, self.__TEMP_DOWNLOAD):
            print('Unable to download from Gitlab', file=sys.stderr)
            sys.exit(22)

        unzip_multiple_files(self.__TEMP_DOWNLOAD, tempfile.gettempdir())
        delete_folder(self.install_location + '-old')      #Delete backup copy
        move_file(self.install_location, self.install_location + '-old')

        if not move_file(tempfile.gettempdir() + '/notrack-master', self.install_location):
            print('Download missing', file=sys.stderr)
            sys.exit(22)

    def __legacy_copyto_localsbin(self):
        scriptfolder = self.install_location + '/scripts/'
        sbinfolder = '/usr/local/sbin/'

        delete_file(sbinfolder + 'notrack')
        copy_file(scriptfolder + 'notrack.sh', sbinfolder + 'notrack.sh')
        move_file(sbinfolder + 'notrack.sh', sbinfolder + 'notrack', 0o775)

        delete_file(sbinfolder + 'ntrk-upgrade')
        copy_file(scriptfolder + 'ntrk-upgrade.sh', sbinfolder + 'ntrk-upgrade.sh')
        move_file(sbinfolder + 'ntrk-upgrade.sh', sbinfolder + 'ntrk-upgrade', 0o775)



    def __modern_check_localsbin(self):
        scriptfolder = self.install_location + '/scripts/'
        sbinfolder = '/usr/local/sbin/'

        #Delete old NoTrack files from /usr/local/sbin
        #Change permissions of scripts to 775
        #Create symlink in /usr/local/sbin pointing to scripts folder
        delete_file(sbinfolder + 'ntrk-upgrade')
        os.chmod(scriptfolder + 'ntrkupgrade.py', 0o775)
        os.symlink(scriptfolder + 'ntrkupgrade.py', sbinfolder + 'ntrk-upgrade')

    def do_upgrade(self):
        """if self.__check_git():
            if not self.__git_upgrade():
                self.__git_clone()

        #else:
            #self.__download_from_git()
        """
        if check_module('mysql'):
            self.__modern_check_localsbin()
        else:
            self.__legacy_copyto_localsbin()

        #self.__legacy_copyto_localsbin()


ntrkupgrade = NoTrackUpgrade()

print(ntrkupgrade.install_location, ntrkupgrade.username)
ntrkupgrade.do_upgrade()
"""


#sudocheck=$(grep www-data /etc/sudoers)                              #Check sudo permissions for lighty possibly DEPRECATED
#if [[ $sudocheck == "" ]]; then
  #echo "Adding NoPassword permissions for www-data to execute script /usr/local/sbin/ntrk-exec as root"
  #echo -e "www-data\tALL=(ALL:ALL) NOPASSWD: /usr/local/sbin/ntrk-exec" | tee -a /etc/sudoers
#fi

if [ -e "$FILE_CONFIG" ]; then                                       #Remove Latestversion number from Config file
  echo "Removing version number from Config file"
  grep -v "LatestVersion" "$FILE_CONFIG" > /tmp/notrack.conf
  mv /tmp/notrack.conf "$FILE_CONFIG"
  echo
fi

#mysql --user=ntrk --password=ntrkpass -D ntrkdb -e "DROP TABLE IF EXISTS live,historic,lightyaccess;"

echo "NoTrack upgrade complete :-)"
echo

"""