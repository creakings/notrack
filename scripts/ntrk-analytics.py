#!/usr/bin/env python3
#Title       : NoTrack Analytics
#Description : Analyse dns_logs table for suspect lookups to malicious or unknown tracking sites
#Author      : QuidsUp
#Date        : 2020-06-20
#Version     : 0.9.6
#Usage       : python3 ntrk-analytics.py


#Standard Imports
import re

#Local imports
from ntrkmariadb import DBWrapper

class NoTrackAnalytics():
    def __init__(self):
        self.__domainsfound = {}
        self.__blocklists = []
        self.__whitelist = []

        self.__ignorelist = [
          'akadns\.net$',
        ]

        self.__dbwrapper = DBWrapper()                     #Declare MariaDB Wrapper

        #self.__dbwrapper.analytics_createtable()
        self.__blocklists = self.__dbwrapper.blocklist_getactive()
        self.__whitelist = self.__dbwrapper.blocklist_getwhitelist()


    def __searchmalware(self, bl):
        """
        Search for results from a specific malware blocklist
        Parse found results to __review_results

        Parameters:
            bl (str): Blocklist name
        """
        tabledata = []
        tabledatalen = 0

        print('Searching for domains from %s' % bl)

        tabledata = self.__dbwrapper.dnslog_searchmalware(bl)
        tabledatalen = len(tabledata)

        if tabledatalen == 0:
            print('No results found :-)')

        self.__review_results('Malware-' + bl, tabledata)  #Specify name of malware list


    def __searchtracker(self, pattern):
        """
        Search for specified results pattern
        Parse found results to __review_results
        """
        tabledata = []
        tabledatalen = 0

        print('Trying regular expression %s' % pattern)

        tabledata = self.__dbwrapper.dnslog_searchtracker(pattern)
        tabledatalen = len(tabledata)

        if tabledatalen == 0:
            print('No results found :-)')

        self.__review_results('Tracker', tabledata)


    def __is_ignorelist(self, domain):
        """
        Check if domain is in ignore list
        Some domains should be ignored as they are secondary DNS lookups or CDN's

        Parameters:
            domain (str): Domain to check
        Returns:
            True: Domain is in ignorelist
            False: Domain is not in ignorelist
        """

        for pattern in self.__ignorelist:
            if re.match(pattern, domain) is None:
                return False

        return True


    def __is_whitelist(self, domain):
        """
        Check if domain is in whitelist

        Parameters:
            domain (str): Domain to check
        Returns:
            True: Domain is in whitelist
            False: Domain is not in whitelist
        """
        if len(self.__whitelist) == 0:                       #Check if whitelist is empty
            return False

        for pattern in self.__whitelist:
            if re.match(pattern + '$', domain) is None:
                return False

        return True

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
            return True

        return False

    def __review_results(self, issue, tabledata):
        """
        Check the results found before adding to the analytics table

        Parameters:
            issue (str): Tracker, Malware-x
            tabledata (list): list of tuples found from the SQL search
        """
        new_dns_result = ''                                #New result for updating dnslog

        #Columns:
        #0: id
        #1: datetime
        #2: sys
        #3: dns_request
        #4: dns_result

        for row in tabledata:
            if self.__is_ignorelist(row[3]):               #Check if domain is in ignore list
                print('%s is in ignore list' % row[3])
                continue

            if self.__is_whitelist(row[3]):                #Check if domain is in whitelist
                print('%s is in whitelist' % row[3])
                continue

            #Set the new DNS result
            if issue == 'Tracker':                         #Tracker accessed
                new_dns_result = 'T'
            else:
                if row[4] == 'B':
                    new_dns_result = 'B'                   #Malware blocked
                else:
                    new_dns_result = 'M'                   #Malware accessed

            #Update dnslog record to show malware / tracker instead of blocked / allowed
            self.__dbwrapper.dnslog_updaterecord(row[0], new_dns_result)

            if self.__is_domainadded(row[3]):              #Has domain been added to analytics recently?
                print('%s has already been added' % row[3])
            else:
                self.__domainsfound[row[3]] = True         #Add domain to domainsfound dict
                #log_time, system, dns_request, dns_result, issue
                self.__dbwrapper.analytics_insertrecord(row[1], row[2], row[3], row[4], issue)

            print(row) #TODO Format



    def checkmalware(self):
        """
        Check if any domains from all the enabled malware blocklists have been accessed
        """
        print('Checking to see if any known malware domains have been accessed')

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
        print('Searching for any trackers or advertising domains accessed')
        self.__searchtracker('^analytics\.')               #analytics as a subdomain
        self.__searchtracker('^cl(c|ck|ick|kstat)\.')         #clc, clck, click, clkstat as a subdomain
        self.__searchtracker('^log(s|ger)?\\\.')                #log, logs, logger as a subdomain (exclude login.)
        self.__searchtracker('^pxl?\\\.')                       #px, pxl, as a subdomain
        self.__searchtracker('pixel[^\\\.]{0,8}\\\.')           #pixel, followed by 0 to 8 non-dot chars anywhere
        self.__searchtracker('telemetry')                       #telemetry anywhere
        self.__searchtracker('trk[^\\\.]{0,3}\\\.')             #trk, followed by 0 to 3 non-dot chars anywhere
        #Have to exclude tracker. (bittorent), security-tracker (Debian), and tracking-protection (Mozilla)
        self.__searchtracker('track(ing|\\\-[a-z]{2,8})?\\\.')  #track, tracking, track-eu as a subdomain / domain.
        self.__searchtracker('^v?stats?\\\.')                   #vstat, stat, stats as a subdomain

        #Checks for Advertising
        self.__searchtracker('^ads\\\.')
        self.__searchtracker('^adserver')
        self.__searchtracker('^advert')


def main():
    print('NoTrack DNS Log Analytics')
    ntrkanalytics = NoTrackAnalytics()

    ntrkanalytics.checkmalware()
    ntrkanalytics.checktrackers()

    print('NoTrack log analytics complete :-)')
    print()

if __name__ == "__main__":
    main()

