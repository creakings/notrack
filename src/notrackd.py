#!/usr/bin/env python3
#Title : NoTrackD
#Description : NoTrack Daemon
#Author : QuidsUp
#Date : 2020-07-24
#Version : 2020.07
#Usage :

#Standard imports

#Local imports
from ntrkmariadb import DBWrapper
from ntrkregex import *


dbwrapper = DBWrapper()                     #Declare MariaDB Wrapper


def main():
    print('NoTrack Daemon')

if __name__ == "__main__":
    main()
