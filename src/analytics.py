#!/usr/bin/env python3
#Title       : NoTrack Analytics
#Description : Analyse dns_logs table for suspect lookups to malicious or unknown tracking domains
#Author      : QuidsUp
#Date        : 2020-06-20
#Version     : 21.02
#Usage       : python3 ntrk-analytics.py


#Standard Imports
import re

#Local imports
import errorlogger
from ntrkmariadb import DBWrapper

#Create logger
logger = errorlogger.logging.getLogger(__name__)

class NoTrackAnalytics():
    def __init__(self):
        self.__domainsfound = set()                        #Prevent duplicates
        self.__blocklists = list()                         #Active blocklists
        self.__whitelist = list()                          #Users whitelist

        self.__ignorelist = {                              #Regex pattern of domains to ignore
          'akadns\.net$',
          'amazonaws\.com$',
          'edgekey\.net$',
          'elasticbeanstalk\.com$',
          '\w{3}pixel\.[\w-]{1,63}$',                      #Prevent false positive where pixel is part of domain name e.g. retropixel
        }

        self.__dbwrapper = DBWrapper()                     #Declare MariaDB Wrapper
        self.__dbwrapper.analytics_createtable()           #Check analytics table exists

        #Load the black and whitelists from blocklist table
        self.__blocklists = self.__dbwrapper.blocklist_getactive()
        self.__whitelist = self.__dbwrapper.blocklist_getwhitelist()


    def __searchmalware(self, bl):
        """
        Search for results from a specific malware blocklist
        Parse found results to __review_results

        Parameters:
            bl (str): Blocklist name
        """
        tabledata = []                                     #Results of MariaDB search

        logger.info(f'Searching for domains from: {bl}')
        tabledata = self.__dbwrapper.dnslog_searchmalware(bl)

        if len(tabledata) == 0:
            logger.info('No results found :-)')
            return

        self.__review_results(f'malware-{bl}', tabledata)  #Specify name of malware list


    def __searchregex(self, pattern, listtype):
        """
        Search for specified results pattern
        Parse found results to __review_results

        Parameters:
            pattern (str): Regex pattern to search
        """
        tabledata = []                                     #Results of MariaDB search

        logger.info(f'Searching for regular expression: {pattern}')
        tabledata = self.__dbwrapper.dnslog_searchregex(pattern)

        if len(tabledata) == 0:
            logger.info('No results found :-)')
            return

        self.__review_results(listtype, tabledata)


    def __is_ignorelist(self, domain):
        """
        Check if domain is in ignore list
        Some domains should be ignored as they're secondary DNS lookups or CDN's

        Parameters:
            domain (str): Domain to check
        Returns:
            True: Domain is in ignorelist
            False: Domain is not in ignorelist
        """
        pattern = ''                                       #Regex pattern to check

        for pattern in self.__ignorelist:                  #Check everything in ignorelist
            if re.search(pattern, domain) is not None:     #Something matched?
                logger.info(f'{domain} matched pattern {pattern} in ignorelist')
                return True

        return False


    def __is_whitelist(self, domain):
        """
        Check if domain is in whitelist

        Parameters:
            domain (str): Domain to check
        Returns:
            True: Domain is in whitelist
            False: Domain is not in whitelist
        """
        if len(self.__whitelist) == 0:                     #Check if whitelist is empty
            return False                                   #No point in going any further with whitelist checks

        pattern = ''                                       #Regex pattern to check

        for pattern in self.__whitelist:                   #Treat whitelist results as a regex pattern
            #"domain" could be a subdomain, so we check for a match from the end backwards
            if re.match(pattern + '$', domain) is not None:
                logger.info(f'{domain} matched pattern {pattern} in whitelist')
                return True

        return False


    def __is_domainadded(self, domain):
        """
        Check if domain has already been added this run

        Parameters:
            domain (str): Domain to check
        Returns:
            True: Domain has been added
            False: Domain has not been added
        """
        if domain in self.__domainsfound:
            logger.debug(f'{domain} has already been added')
            return True

        return False


    def __review_results(self, issue, tabledata):
        """
        Check the results found before adding to the analytics table

        Parameters:
            issue (str): Tracker, or Malware-x
            tabledata (list): list of tuples found from the MariaDB search
        """
        analytics_severity = ''
        new_severity = ''                                  #New new_severity for updating dnslog
        new_bl_source = ''

        #Columns:
        #0: id
        #1: datetime
        #2: sys
        #3: dns_request
        #4: severity
        #5: bl_source

        for row in tabledata:
            if self.__is_ignorelist(row[3]):               #Check if domain is in ignore list
                continue

            if self.__is_whitelist(row[3]):                #Check if domain is in whitelist
                continue

            #Set the new DNS result
            if issue == 'advert' or issue == 'tracker':
                analytics_severity = '1'
                new_severity = '3'
                new_bl_source = issue
            else:                                          #Has Malware been allowed or blocked. Check further
                if row[4] == '2':                          #Malware blocked
                    analytics_severity = '2'
                    new_severity = '3'
                    new_bl_source = row[5]                 #Leave name of bl_source in
                else:                                      #Malware accessed
                    analytics_severity = '3'
                    new_severity = '3'

            #Update dnslog record to show malware / tracker accessed instead of blocked / allowed
            self.__dbwrapper.dnslog_updaterecord(row[0], new_severity, new_bl_source)

            #Make sure that domain has not been added to analytics recently?
            if not self.__is_domainadded(row[3]):
                self.__domainsfound.add(row[3])         #Add domain to domainsfound dict
                #log_time, system, dns_request, analytics_severity, issue
                self.__dbwrapper.analytics_insertrecord(row[1], row[2], row[3], analytics_severity, issue)

            logger.debug(f'{issue} - {row[0]}, {row[1].isoformat(sep=" ")}, {row[3]}')


    def checkmalware(self):
        """
        Check if any domains from all the enabled malware blocklists have been accessed
        """
        logger.info('Checking to see if any known malware domains have been accessed')

        if 'bl_notrack_malware' in self.__blocklists:
            self.__searchmalware('bl_notrack_malware')
        if 'bl_hexxium' in self.__blocklists:
            self.__searchmalware('bl_hexxium')
        if 'bl_cedia' in self.__blocklists:
            self.__searchmalware('bl_cedia')
        if 'bl_cedia_immortal' in self.__blocklists:
            self.__searchmalware('bl_cedia_immortal')
        if 'bl_malwaredomainlist' in self.__blocklists:
            self.__searchmalware('bl_malwaredomainlist')
        if 'bl_malwaredomains' in self.__blocklists:
            self.__searchmalware('bl_malwaredomains')
        if 'bl_swissransom' in self.__blocklists:
            self.__searchmalware('bl_swissransom')


    def checktrackers(self):
        """
        Check if any accessed domains match known tracker or advertising patterns
        """

        #Checks for Pixels, Telemetry, and Trackers
        logger.info('Checking to see if any trackers or advertising domains have been accessed')
        self.__searchregex('^analytics\\\.', 'tracker')                  #analytics as a subdomain
        self.__searchregex('^beacons?\\\.', 'tracker')                   #beacon(s) as a subdomain
        self.__searchregex('^cl(c|ck|icks?|kstat)\\\.', 'tracker')       #clc, clck, clicks?, clkstat as a subdomain
        self.__searchregex('^counter\\\.', 'tracker')                    #counter as a subdomain
        self.__searchregex('^eloq(ua)?(\-trackings?)?\\\.', 'tracker')   #Oracle eloq, eloqua, eloqua-tracking
        self.__searchregex('^log(s|ger)?\\\.', 'tracker')                #log, logs, logger as a subdomain (exclude login.)
        self.__searchregex('^pxl?\\\.', 'tracker')                       #px, pxl, as a subdomain
        self.__searchregex('pixel[^\\\.]{0,8}\\\.', 'tracker')           #pixel, followed by 0 to 8 non-dot chars anywhere
        self.__searchregex('^(aa\-|app|s)?metri[ck][as]\\\.', 'tracker') #aa-metrics, appmetrics, smetrics, metrics, metrika as a subdomain
        self.__searchregex('telemetry', 'tracker')                       #telemetry anywhere
        self.__searchregex('trk[^\\\.]{0,3}\\\.', 'tracker')             #trk, followed by 0 to 3 non-dot chars anywhere
        #Have to exclude tracker. (bittorent), security-tracker (Debian), and tracking-protection (Mozilla)
        self.__searchregex('^trace\\\.', 'tracker')                      #trace as a subdomain
        self.__searchregex('track(ing|\\\-[a-z]{2,8})?\\\.', 'tracker')  #track, tracking, track-eu as a subdomain / domain.
        self.__searchregex('^visit\\\.', 'tracker')                      #visit as a subdomain
        self.__searchregex('^v?stats?\\\.', 'tracker')                   #vstat, stat, stats as a subdomain

        #Checks for Advertising
        self.__searchregex('^ad[sv]\\\.', 'advert')                      #ads or adv
        self.__searchregex('^adserver', 'advert')
        self.__searchregex('^advert', 'advert')


    def get_blocklists(self):
        """
        Get active blocklists and whitelist
        """
        self.__blocklists = self.__dbwrapper.blocklist_getactive()
        self.__whitelist = self.__dbwrapper.blocklist_getwhitelist()


def main():
    print('NoTrack DNS Log Analytics')
    ntrkanalytics = NoTrackAnalytics()

    ntrkanalytics.checktrackers()
    ntrkanalytics.checkmalware()

    print('NoTrack log analytics complete :-)')
    print()

if __name__ == "__main__":
    main()

