#!/usr/bin/env python3
#Title       : NoTrack Analytics
#Description : Analyse dns_logs table for suspect lookups to malicious or unknown tracking sites
#Author      : QuidsUp
#Date        : 2020-06-20
#Version     : 20.07
#Usage       : python3 ntrk-analytics.py


#Standard Imports
import re

#Local imports
from ntrkmariadb import DBWrapper

class NoTrackAnalytics():
    def __init__(self):
        self.__domainsfound = set()                        #Prevent duplicates
        self.__blocklists = []                             #Active blocklists
        self.__whitelist = []                              #Users whitelist

        self.__ignorelist = {                              #Regex pattern of domains to ignore
          'akadns\.net$',
          'amazonaws\.com$',
          'edgekey\.net$',
          '\w{3}pixel\.[\w-]{1,63}$',                      #Prevent false positive where pixel is part of domain name e.g. retropixel
        }

        self.__dbwrapper = DBWrapper()                     #Declare MariaDB Wrapper

        self.__dbwrapper.analytics_createtable()
        self.get_blocklists()


    def __searchmalware(self, bl):
        """
        Search for results from a specific malware blocklist
        Parse found results to __review_results

        Parameters:
            bl (str): Blocklist name
        """
        tabledata = []                                     #Results of MariaDB search
        tabledatalen = 0

        print(f'Searching for domains from: {bl}')

        tabledata = self.__dbwrapper.dnslog_searchmalware(bl)
        tabledatalen = len(tabledata)

        if tabledatalen == 0:
            print('No results found :-)')
            print()
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
        tabledatalen = 0

        print('Trying regular expression: %s' % pattern)

        tabledata = self.__dbwrapper.dnslog_searchregex(pattern)
        tabledatalen = len(tabledata)

        if tabledatalen == 0:
            print('No results found :-)')
            print()
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

        for pattern in self.__ignorelist:                  #Check everything in ignorelist
            #print(pattern)
            #print(re.search(pattern, domain))
            if re.search(pattern, domain) is not None:
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

        for pattern in self.__whitelist:                   #Treat whitelist results as a regex pattern
            if re.match(pattern + '$', domain) is None:    #"domain" could be a subdomain, so we check for a match from the end backwards
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
                print('%s is in ignore list' % row[3])
                continue

            if self.__is_whitelist(row[3]):                #Check if domain is in whitelist
                print(f'{row[3]} is in whitelist')
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

            if self.__is_domainadded(row[3]):              #Has domain been added to analytics recently?
                print(f'{row[3]} has already been added')
            else:
                self.__domainsfound.add(row[3])         #Add domain to domainsfound dict
                #log_time, system, dns_request, analytics_severity, issue
                self.__dbwrapper.analytics_insertrecord(row[1], row[2], row[3], analytics_severity, issue)

            print(f'{row[0]}, {row[1].isoformat(sep=" ")}, {row[3]}')

        print()


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
        self.__searchregex('^analytics\\\.', 'tracker')                  #analytics as a subdomain
        self.__searchregex('^cl(c|ck|icks?|kstat)\\\.', 'tracker')       #clc, clck, clicks?, clkstat as a subdomain
        self.__searchregex('^log(s|ger)?\\\.', 'tracker')                #log, logs, logger as a subdomain (exclude login.)
        self.__searchregex('^pxl?\\\.', 'tracker')                       #px, pxl, as a subdomain
        self.__searchregex('pixel[^\\\.]{0,8}\\\.', 'tracker')           #pixel, followed by 0 to 8 non-dot chars anywhere
        self.__searchregex('^s?metrics\\\.', 'tracker')                  #smetrics, metrics as a subdomain
        self.__searchregex('telemetry', 'tracker')                       #telemetry anywhere
        self.__searchregex('trk[^\\\.]{0,3}\\\.', 'tracker')             #trk, followed by 0 to 3 non-dot chars anywhere
        #Have to exclude tracker. (bittorent), security-tracker (Debian), and tracking-protection (Mozilla)
        self.__searchregex('^trace\\\.', 'tracker')                      #trace as a subdomain
        self.__searchregex('track(ing|\\\-[a-z]{2,8})?\\\.', 'tracker')  #track, tracking, track-eu as a subdomain / domain.
        self.__searchregex('^visit\\\.', 'tracker')                      #visit as a subdomain
        self.__searchregex('^v?stats?\\\.', 'tracker')                   #vstat, stat, stats as a subdomain

        #Checks for Advertising
        self.__searchregex('^ads\\\.', 'advert')
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

    ntrkanalytics.checkmalware()
    ntrkanalytics.checktrackers()

    print('NoTrack log analytics complete :-)')
    print()

if __name__ == "__main__":
    main()

