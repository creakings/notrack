#!/usr/bin/env python3
#Title       : NoTrack
#Description : Place holder to replace interactive elements of notrack.sh
#Author      : QuidsUp
#Date        : Original 2015-01-14
#Version     : Unreleased
#Usage       : python3 notrack.py

#Standard imports
import argparse
import os
import sys

#Local imports
from ntrkshared import *


#######################################
# Constants
#######################################


def show_version():
    """
    Show version number and exit
    """
    print(f'NoTrack Version {VERSION}')
    print()
    sys.exit(0)



def main():
    parser = argparse.ArgumentParser(description = 'NoTrack')
    parser.add_argument('-v', '--version', help='Get version number', action='store_true')
    args = parser.parse_args()

    if args.version:                                           #Showing version?
        show_version()
    else:
        print('This is a placeholder. Alternative scripts to run:')
        print('  blockparser.py\tDownload new blocklists')
        print('  ntrkupgrade.py\tUpgrade NoTrack')


if __name__ == "__main__":
    main()
