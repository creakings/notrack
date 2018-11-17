#!/bin/bash
#Title : NoTrack Upgrader
#Description : 
#Author : QuidsUp
#Date : 2016-03-22
#Usage : ntrk-upgrade
#Last updated with NoTrack v0.9 - 26 Aug 2018

#######################################
# Constants
#######################################
readonly FILE_CONFIG="/etc/notrack/notrack.conf"

#######################################
# Global Variables
#######################################
INSTALL_LOCATION=""
USERNAME=""


#--------------------------------------------------------------------
# Copy File
#   Checks if Source file exists, then copies it to Destination
#
# Globals:
#   INSTALL_LOCATION
# Arguments:
#   $1: Source
#   $2: Destination
# Returns:
#   None
#--------------------------------------------------------------------
function copy_file() {
  if [ -e "$INSTALL_LOCATION/$1" ]; then                   #Does file exist?
    cp "$INSTALL_LOCATION/$1" "$2"
    echo "Copying $1 to $2"
  else
    echo "WARNING: Unable to find file $1 :-("             #Display a warning if file doesn't exist
  fi
}


#--------------------------------------------------------------------
# Rename File
#   Renames Source file to Destination
#   Chmod 755 Destination file
#
# Globals:
#   None
# Arguments:
#   $1: Error Message
#   $2: Exit Code
# Returns:
#   None
#--------------------------------------------------------------------
function rename_file() {
  if [ -e "$1" ]; then                                     #Does file exist?
    mv "$1" "$2"
    chmod 755 "$2"
  else
    echo "WARNING: Unable to rename file $1 :-("
  fi
}


#--------------------------------------------------------------------
# Find NoTrack
#   Find where NoTrack is installed
#   1. Check users home folders
#   2. Check /opt/notrack
#   3. Check /root
#
# Globals:
#   INSTALL_LOCATION, USERNAME
# Arguments:
#   None
# Returns:
#   None
#--------------------------------------------------------------------
function find_notrack {
  for homefolder in /home/*; do                                  #Try and find notrack folder in /home/users
    if [ -d "$homefolder/notrack" ]; then 
      INSTALL_LOCATION="$homefolder/notrack"
      USERNAME=$(grep "$homefolder" /etc/passwd | cut -d : -f1)  #Get username from /etc/passwd by home folder name
      break
    fi
  done

  if [[ $INSTALL_LOCATION == "" ]]; then                         #Not in /home, look elsewhere
    if [ -d "/opt/notrack" ]; then
      INSTALL_LOCATION="/opt/notrack"
      USERNAME="root"
    elif [ -d "/root/notrack" ]; then
      INSTALL_LOCATION="/root/notrack"
      USERNAME="root"
    elif [ -d "/notrack" ]; then
      INSTALL_LOCATION="/notrack"
      USERNAME="root"
    else
      echo "Error Unable to find NoTrack folder :-("
      echo "Aborting"
      exit 22
    fi
  fi

}

#--------------------------------------------------------------------
if [[ "$(id -u)" != "0" ]]; then
  echo "Root access is required to carry out upgrade of NoTrack"
  echo "Usage: sudo ntrk-upgrade"
  exit 21
fi

echo "Upgrading NoTrack"
find_notrack

echo "Install Location $INSTALL_LOCATION"
echo "Username: $USERNAME"
echo

#Alt command for sudoless systems
#su -c "cd /home/$USERNAME/$PROJECT ; svn update" -m "$USERNAME" 

#Switch to different user to carry out upgrade
sudo -u $USERNAME bash << ROOTLESS
if [ "$(command -v git)" ]; then                                     #Utilise Git if its installed
  echo "Pulling latest updates of NoTrack using Git"
  cd "$INSTALL_LOCATION"
  git pull
  if [ $? != "0" ]; then                                             #Git repository not found
    echo "Git repository not found"
    if [ -d "$INSTALL_LOCATION-old" ]; then                          #Delete NoTrack-old folder if it exists
      echo "Removing old NoTrack folder"
      rm -rf "$INSTALL_LOCATION-old"
    fi
    echo "Moving $INSTALL_LOCATION folder to $INSTALL_LOCATION-old"
    mv "$INSTALL_LOCATION" "$INSTALL_LOCATION-old"
    echo "Cloning NoTrack to $INSTALL_LOCATION with Git"
    git clone --depth=1 https://gitlab.com/quidsup/notrack.git "$INSTALL_LOCATION"
  fi
else                                                                 #Git not installed, fallback to wget
  echo "Downloading latest version of NoTrack from https://gitlab.com/quidsup/notrack/-/archive/master/notrack-master.zip"
  wget -O /tmp/notrack-master.zip https://gitlab.com/quidsup/notrack/-/archive/master/notrack-master.zip
  if [ ! -e /tmp/notrack-master.zip ]; then                          #Check to see if download was successful
    #Abort we can't go any further without any code from git
    echo "Error Download from gitlab has failed"
    exit 23
  fi

  if [ -d "$INSTALL_LOCATION" ]; then                                #Check if NoTrack folder exists  
    if [ -d "$INSTALL_LOCATION-old" ]; then                          #Delete NoTrack-old folder if it exists
      echo "Removing old NoTrack folder"
      rm -rf "$INSTALL_LOCATION-old"
    fi
    echo "Moving $INSTALL_LOCATION folder to $INSTALL_LOCATION-old"
    mv "$INSTALL_LOCATION" "$INSTALL_LOCATION-old"
  fi

  echo "Unzipping notrack-master.zip"
  unzip -oq /tmp/notrack-master.zip -d /tmp
  echo "Copying folder across to $INSTALL_LOCATION"
  mv /tmp/notrack-master "$INSTALL_LOCATION"
  echo "Removing temporary files"
  rm /tmp/notrack-master.zip                                         #Cleanup
fi

ROOTLESS

if [ $? == 23 ]; then                                                #Code hasn't downloaded
  exit 23
fi

echo
echo "Copying updated scripts"
copy_file "scripts/notrack.sh" "/usr/local/sbin/"                    #NoTrack.sh
rename_file "/usr/local/sbin/notrack.sh" "/usr/local/sbin/notrack"

copy_file "scripts/ntrk-exec.sh" "/usr/local/sbin/"                  #ntrk-exec.sh
rename_file "/usr/local/sbin/ntrk-exec.sh" "/usr/local/sbin/ntrk-exec"

copy_file "scripts/ntrk-pause.sh" "/usr/local/sbin/"                 #ntrk-pause.sh
rename_file "/usr/local/sbin/ntrk-pause.sh" "/usr/local/sbin/ntrk-pause"

copy_file "scripts/ntrk-upgrade.sh" "/usr/local/sbin/"               #ntrk-upgrade.sh
rename_file "/usr/local/sbin/ntrk-upgrade.sh" "/usr/local/sbin/ntrk-upgrade"

copy_file "scripts/ntrk-parse.sh" "/usr/local/sbin/"                 #ntrk-parse.sh
rename_file "/usr/local/sbin/ntrk-parse.sh" "/usr/local/sbin/ntrk-parse"
echo "Finished copying scripts"
echo

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
