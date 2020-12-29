#!/usr/bin/env python3
#Title       : NoTrack Log Parser
#Description : NoTrack Daemon
#Author      : QuidsUp
#Date        : 2020-07-24
#Version     : 20.12
#Usage       : sudo python3 logparser.py

#Standard imports
from datetime import date
import os
import re

#Local imports
import errorlogger
from ntrkmariadb import DBWrapper
from ntrkregex import Regex_Domain, Regex_TLD

#Create logger
logger = errorlogger.logging.getLogger(__name__)

class NoTrackParser():
    def __init__(self):
        self.__DNSLOGFILE = '/var/log/notrack.log'
        self.__blocklist_sources = dict()                  #Domains and bl_source from blocklist table
        self.__quick_blsources = dict()                    #Quick lookup for domains and bl_source
        self.__dbwrapper = DBWrapper()                     #Declare MariaDB Wrapper

        self.__Regex_DnsmasqLine = re.compile(r'^(?P<log_month>\w{3})  ?(?P<log_day>\d{1,2}) (?P<log_time>\d{2}:\d{2}:\d{2}) dnsmasq\[\d{1,7}\]: (?P<serial>\d+) (?P<sys>[\d\.:]+)\/\d+ (?P<action>query|reply|config|cached|\/etc\/localhosts\.list)(?:\[A{1,4}\])? (?P<domain>[\w\.\-]{2,254}) (?:is|to|from) (?P<res>[\w\.:<>]*)$')

        self.__dbwrapper.dnslog_createtable()              #Make sure dnslog table exists


    def blank_dnslog(self):
        """
        Overwrite the dnslog file with nothing
        """
        logger.info('Blanking dnslog file')

        try:
            f = open(self.__DNSLOGFILE, 'w')               #Open log file for ascii writing
        except IOError as e:
            logger.error(f'Unable to write to {self.__DNSLOGFILE}')
            logger.error(e)
            return False
        except OSError as e:
            logger.error(f'Unable to write to {self.__DNSLOGFILE}')
            logger.error(e)
            return False
        else:
            f.write('')                                    #Write blank
        finally:
            f.close()
        return True


    def __load_dnslog(self):
        """
        Load contents of file and return as a list
        1. Check file exists
        2. Read all lines of file

        Returns:
            List of all lines in file
            Empty list if file doesn't exist or error occured
        """
        logger.info('Loading dnslog file')

        if not os.path.isfile(self.__DNSLOGFILE):
            logger.error(f'Unable to load {self.__DNSLOGFILE}, file is missing')
            return []

        try:
            f = open(self.__DNSLOGFILE, 'r')               #Open log file for reading
        except IOError as e:
            logger.error(f'Unable to read {self.__DNSLOGFILE}')
            logger.error(e)
            return []
        except OSError as e:
            logger.error(f'Unable to read {self.__DNSLOGFILE}')
            logger.error(e)
            return []
        else:
            filelines = f.readlines()
        finally:
            f.close()

        return filelines


    def __process_dnslog(self, filelines):
        """
        In order to avoid repeat entries, log a query into tempqueries by its serial
        Once the result has been matched drop serial number from tempqueries
        log entries processed are stored in queries list
        After processing upload queries into dnslog table
        """
        curyear = date.today().year                        #Current Year (Missing from dnsmasq)
        curmonth = date.today().month                      #Current Month (Numeric value required)
        bl_source = ''                                     #Block List Source
        domain = ''
        serial = ''                                        #dnsmasq groups by a serial number
        sys = ''                                           #System (IP) which made the request

        lineitem = []                                      #Named regex items from each line
        queries = []                                       #List of queries to upload
        tempqueries = dict()                               #Tracking by serial

        for line in filelines:
            matches = self.__Regex_DnsmasqLine.match(line) #Only process certain entries
            if matches is None:                            #Ignore 'forward' entries and any other system info from dnsmasq
                continue

            lineitem = matches.groupdict()                 #Named regex items
            domain = lineitem['domain']
            serial = lineitem['serial']
            sys = lineitem['sys']

            if lineitem['action'] != 'query':              #Beautify domains on a query response
                if domain.startswith('www.'):
                    domain = domain.lstrip('www.')         #Remove preceding www.

            if lineitem['action'] == 'query':              #Domain Query
                log_date = self.__get_date(curyear, curmonth, lineitem['log_day'])
                #Query contains the fewest records, there so we calculate the ISO formatted date now
                tempqueries[serial] = f"{log_date} {lineitem['log_time']}"

            elif lineitem['action'] == 'reply':            #Domain Allowed (new response)
                if serial in tempqueries:
                    if lineitem['res'] == '<CNAME>':       #CNAME results in another query against the serial number
                        queries.append(tuple([tempqueries[serial], sys, domain, '1', 'cname']))
                    else:                                  #Answer found, drop the serial number
                        queries.append(tuple([tempqueries[serial], sys, domain, '1', 'allowed']))
                        tempqueries.pop(serial)

            elif lineitem['action'] == 'cached':           #Domain Allowed (cached)
                if serial in tempqueries:
                    if lineitem['res'] == '<CNAME>':       #CNAME might not happen here
                        queries.append(tuple([tempqueries[serial], sys, domain, '1', 'cname']))
                    else:                                  #Answer found, drop the serial number
                        queries.append(tuple([tempqueries[serial], sys, domain, '1', 'cached']))
                        tempqueries.pop(serial)

            elif lineitem['action'] == 'config':           #Domain Blocked by NoTrack
                if serial in tempqueries:
                    #Find out which blocklist prevented the DNS lookup
                    bl_source = self.__get_blsource(domain)
                    queries.append(tuple([tempqueries[serial], sys, domain, '2', bl_source]))
                    tempqueries.pop(serial)

            elif lineitem['action'] == '/etc/localhosts.list': #LAN Query
                if serial in tempqueries:
                    queries.append(tuple([tempqueries[serial], sys, domain, '1', 'local']))
                    tempqueries.pop(serial)

        if len(queries) == 0:                              #Anything processed?
            return

        self.__dbwrapper.dnslog_insertdata(queries)        #Upload to dnslog table on MariaDB


    def __get_blsource(self, domain):
        """
        Returns the blocklist resposible for blocking a certain domain
        Quick sources is provided as user may get repetitive subdomains being requested

        Parameters:
            domain (str): Domain Requested

        Returns:
            List of all lines in file
            Empty list if file doesn't exist
        """
        if domain in self.__quick_blsources:               #Check quick list
            return self.__quick_blsources[domain]

        topdomain = ''                                     #site.com or site.co.uk
        topdomain = self.__get_topdomain(domain)

        if topdomain in self.__blocklist_sources:          #Check topdomain in blocklist sources
            self.__quick_blsources[domain] = self.__blocklist_sources[topdomain]
            return self.__blocklist_sources[topdomain]

        if domain in self.__blocklist_sources:             #Check domain in blocklist sources
            self.__quick_blsources[domain] = self.__blocklist_sources[domain]
            return self.__blocklist_sources[domain]

        #At this point check TLD then subdomains will have to be reviewed in reverse order
        checkstr = ''                                      #subdomain + topdomain
        subdomainstr = ''                                  #growing subdomains string
        subdomains = []                                    #subdomain1,subdomain2
        tld = ''                                           #Top Level Domain

        tld = self.__get_tld(domain)                       #Check against bl_tld
        if tld in self.__blocklist_sources:
            self.__quick_blsources[domain] = self.__blocklist_sources[tld]
            return self.__blocklist_sources[tld]

        subdomains = self.__get_subdomains(domain, topdomain)
        for i in range(len(subdomains) -1, 0, -1):         #Length of subdomains to zero
            #Insert this subdomain at the begining, i.e read from right to left
            subdomainstr = f'{subdomains[i]}.{subdomainstr}'
            checkstr = f'{subdomainstr}{topdomain}'        #subdomain + topdomain
            if checkstr in self.__blocklist_sources:       #Has subdomain + topdomain been found?
                self.__quick_blsources[domain] = self.__blocklist_sources[checkstr]
                return self.__blocklist_sources[checkstr]

        return 'invalid'                                   #Fallback response



    def __get_date(self, curyear, curmonth, log_day):
        """
        Get date formatted for MariaDB
        """
        return date(curyear, curmonth, int(log_day)).isoformat()


    def __get_subdomains(self, domain, topdomain):
        """
        Get the subdomains from a domain

        Parameters:
            domain (str): Domain Requested
            topdomain (str): site.com or site.co.uk from domain

        Returns:
            List subdomains if any subdomains have been requested
            Or empty list for no subdomains
        """
        if domain == topdomain:                            #No subdomains
            return []

        subdomainstr = ''                                  #String of subdomains
        subdomainstr = domain[:-(len(topdomain)+1)]        #Remove the topdomain
        return subdomainstr.split('.')                     #Return list of subdomains split by '.'



    def __get_topdomain(self, domain):
        """
        Get the top domain from a domain, i.e. site.com or site.co.uk

        Parameters:
            domain (str): Domain Requested

        Returns:
            topdomain
        """
        matches = Regex_Domain.findall(domain)
        if matches is None:                                #Shouldn't happen
            return domain
        else:
            return matches[0][0] + matches[0][1]


    def __get_tld(self, domain):
        """
        Get Top Level Domain
        """
        matches = Regex_TLD.findall(domain)
        if matches is None:
            return domain
        else:
            return matches[0]


    def parsedns(self):
        """
        Parse the dnslog file into dnslog table on MariaDB
        """
        filelines = []
        filelines = self.__load_dnslog()                   #Load dnslog file

        if len(filelines) < 4:                             #Minimum for processing
            logger.info('Nothing in dnslog, skipping')
            return

        self.blank_dnslog()                                #Empty log file to avoid repeat entries
        self.__process_dnslog(filelines)                   #Process log file then upload to MariaDB


    def readblocklist(self):
        """
        Load blocklist domain and bl_source into __blocklist_sources
        """
        tabledata = []

        logger.info('Loading blocklist data from MariaDB into Log Parser')
        tabledata = self.__dbwrapper.blocklist_getdomains_listsource()

        self.__blocklist_sources.clear()                   #Clear old data
        self.__quick_blsources.clear()

        for domain, bl_source in tabledata:
            self.__blocklist_sources[domain] = bl_source

        logger.info(f'Number of domains in blocklist: {len(self.__blocklist_sources)}')


    def trimlogs(self, days):
        """
        Trim rows older than a specified number of days from analytics and dnslog table
        Parameters:
            days (int): Interval of days to keep
                        When days is set to zero nothing will be deleted
        """
        self.__dbwrapper.analytics_trim(days)
        self.__dbwrapper.dnslog_trim(days)


def main():
    print('NoTrack Log Parser')

    ntrkparser = NoTrackParser()
    ntrkparser.readblocklist()
    ntrkparser.parsedns()

    print('NoTrack log parser complete :-)')
    print()


if __name__ == "__main__":
    main()
