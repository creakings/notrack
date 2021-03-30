#!/usr/bin/env python3
#Title       : NoTrack Error Logging
#Description : Sets up logging level and is loaded as part of other NoTrack modules
#Author      : QuidsUp
#Date        : 2020-12-29
#Version     : 20.12
#Usage       : N/A this module is loaded as part of other NoTrack modules

#Standard imports
import logging
import sys

#Setup default logging config
logging.basicConfig(
    level=logging.WARNING,
    #level=logging.INFO,
    #level=logging.DEBUG,
    format='%(asctime)s %(levelname)-6s %(name)-12s %(message)s',
    datefmt='%Y-%m-%d %H:%M:%S',
    handlers=[
        logging.StreamHandler(sys.stdout)
    ]
)
