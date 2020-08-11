#!/usr/bin/env python3
#Title : NoTrack
#Description : This script will download latest block lists from various sources, then parse them into Dnsmasq
#Author : QuidsUp
#Date : 2015-01-14
#Version : 0.9.5
#Usage : sudo python notrack.py
#Standard imports


import csv
import os
import re #TODO load_config still have regular expressions to move to ntrkregex
import shutil
import sys
import time

#Local imports
from blocklists import *
from host import Host
from ntrkfolders import FolderList
from ntrkshared import *
from ntrkmariadb import DBWrapper
from ntrkpause import *
from ntrkregex import *
from ntrkservices import Services
from statusconsts import *

#######################################
# Constants
#######################################
MAX_AGE = 172800 #2 days in seconds
CURRENT_TIME = time.time()


#######################################
# Global Variables
#######################################

config = {
    'NetDev' : 'eth0',
    'IPVersion' : 'IPv4',
    'Search' : 'DuckDuckGo',
    'SearchUrl' : 'https://duckduckgo.com/?q=',
    'WhoIs' : 'Who.is',
    'WhoIsUrl' : 'https://who.is/whois/',
    'Username' : '',
    'Password' : '',
    'Delay' : '30',
    'Suppress' : '',
    'ParsingTime' : '4',
    'api_key' : '',
    'api_readonly' : '',
    'bl_custom' : '',                                      #Special processing required
    'blockmessage' : 'pixel',
    'ipaddress' : '127.0.0.1',
    'whoisapi' : '',
    'status' : '1',
    'unpausetime' : '0',
    'autoupgrade' : '0'
}

class BlockParser:
    def __init__(self):
        print('Initialising Block List Parser')

        self.bl_custom = ''
        self.__dedupcount = 0                              #Per list deduplication count
        self.__domaincount = 0                             #Per list of added domains
        self.__totaldedupcount = 0
        self.__dnsserver_blacklist = ''                    #String for DNS Server Blacklist file
        self.__dnsserver_whitelist = ''                    #String for DNS Server Whitelist file

        self.__blocklist = list()                          #List of tupples for the blocklist
        self.__blockdomianset = set()                      #Domains in blocklist
        self.__blocktldset = set()                         #TLDs blocked
        self.__whiteset = set()                            #Domains in whitelist

        self.__folders = FolderList()
        self.__services = Services()                       #Declare service class
        self.__dbwrapper = DBWrapper()                     #Declare MariaDB Wrapper

        blocklistconf['bl_blacklist'][1] = self.__folders.blacklist

        host = Host(config['ipaddress'])                   #Declare host class
        print('NoTrack version %s' % VERSION)
        print('Hostname: %s' % host.name)
        print('IP Address: %s' % host.ip)

        #Setup the template strings for writing out to black/white list files
        [self.__dnsserver_blacklist, self.__dnsserver_whitelist] = self.__services.get_dnstemplatestr(host.name, host.ip)


    def __add_blacklist(self, domain):
        """
        Formatted string for a blacklist line
        """
        return self.__dnsserver_blacklist % domain


    def __add_whitelist(self, domain):
        """
        Formatted string for a whitelist line
        """
        return self.__dnsserver_whitelist % domain


    def __read_csv(self, filename):
        """
        Load contents of csv and return as a list
        1. Check file exists
        2. Read all lines of csv

        Parameters:
            filename (str): File to load
        Returns:
            List of all lines in file
            Empty list if file doesn't exist
        """
        data = []

        if not os.path.isfile(filename):
            print(f'Unable to load {filename}, file is missing', file=sys.stderr)
            return []

        f = open(filename)
        reader = csv.reader(f)
        data = (list(reader))
        f.close()

        return data


    def __extract_list(self, sourcezip, destination):
        """
        Unzip a file to destination

        Parameters:
            sourcezip (str): Zip file
            destination (str): Output destination
        """
        from zipfile import ZipFile

        with ZipFile(sourcezip) as zipobj:
            for compressedfile in zipobj.namelist():
                if compressedfile.endswith('.txt'):
                    zipobj.extract(compressedfile, self.__folders.tempdir)
                    print(f'Extracting {compressedfile}')
                    move_file(self.__folders.tempdir + compressedfile, destination)


    def __add_domain(self, subdomain, comment, source):
        """
        Process supplied domain and add it to self.__blocklist
        1. Extract domain.co.uk from say subdomain.domain.co.uk
        2. Check if domain.co.uk is in self.__blockdomianset
        3. If subdomain is actually a domain then record domain in self.__blockdomianset
        4. Reverse subdomain
        5. Append to self.__blocklist as [reverse, subdomain, comment, source]

        Parameters:
            subdomain (str): Subdomain or domain
            comment (str): A comment
            source (str): Block list name
        """
        reverse = ''                                       #Reversed domain

        matches = Regex_Domain.search(subdomain)

        if matches == None:                                #Shouldn't happen
            return

        if matches.group(0) in self.__blockdomianset:      #Blocked by domain or whitelisted?
            #print('\t%s is already in self.__blockdomianset as %s' % (subdomain, matches.group(0)))
            self.__dedupcount += 1
            self.__totaldedupcount += 1
            return

        if matches.group(2) in self.__blocktldset:         #Blocked by TLD?
            #print('\t%s is blocked by TLD as %s' % (subdomain, matches.group(2)))
            return

        if matches.group(0) == subdomain:                  #Add domain.co.uk to self.__blockdomianset
            #print('Adding domain %s' % subdomain)
            self.__blockdomianset.add(subdomain)

        #Reverse the domain for later sorting and deduplication
        #An Extra dot is required to match other subdomains and avoid similar spellings
        reverse = subdomain[::-1] + '.'
        self.__blocklist.append(tuple([reverse, subdomain, comment, source]))
        self.__domaincount += 1


    def __match_defanged(self, line, listname):
        """
        Checks custom blocklist file line against Defanged List line regex

        Parameters:
            line (str): Line from file
            listname (str): Blocklist name
        Returns:
            True on successful match
            False when no match is found
        """
        matches = Regex_Defanged.search(line)                  #Search for first match

        if matches is not None:                                #Has a match been found?
            #Add group 1 - Domain and replace defanged [.] with .
            self.__add_domain(matches.group(1).replace('[.]', '.'), '', listname)
            return True

        return False                                           #Nothing found, return False


    def __match_easyline(self, line, listname):
        """
        Checks custom blocklist file line against Easy List line regex

        Parameters:
            line (str): Line from file
            listname (str): Blocklist name
        Returns:
            True on successful match
            False when no match is found
        """
        matches = Regex_EasyLine.search(line)                  #Search for first match

        if matches is not None:                                #Has a match been found?
            self.__add_domain(matches.group(1), '', listname)  #Add group 1 - Domain
            return True

        return False                                           #Nothing found, return False


    def __match_plainline(self, line, listname):
        """
        Checks custom blocklist file line against Plain List line regex

        Parameters:
            line (str): Line from file
            listname (str): Blocklist name
        Returns:
            True on successful match
            False when no match is found
        """
        matches = Regex_PlainLine.search(line)                 #Search for first match

        if matches is not None:                                #Has a match been found?
            self.__add_domain(matches.group(1), matches.group(2), listname)
            return True

        return False                                           #Nothing found, return False


    def __match_unixline(self, line, listname):
        """
        Checks custom blocklist file line against Unix List line regex

        Parameters:
            line (str): Line from file
            listname (str): Blocklist name
        Returns:
            True on successful match
            False when no match is found
        """
        matches = Regex_UnixLine.search(line)                  #Search for first match

        if matches is not None:                                #Has a match been found?
            self.__add_domain(matches.group(1), matches.group(2), listname)
            return True

        return False                                           #Nothing found, return False


    def __process_customlist(self, lines, listname):
        """
        We don't know what type of list this is, so try regex match against different types
        1. Reset dedup and domain counters
        2. Read list of lines
        3. Try different regex matches

        Parameters:
            lines (list): List of lines
            listname (str): Blocklist name
        """
        self.__dedupcount = 0                              #Reset per list dedup count
        self.__domaincount = 0                             #Reset per list domain count

        print(f'{len(lines)} lines to process')

        for line in lines:                                 #Read through list
            if self.__match_plainline(line, 'custom'):     #Try against Plain line
                continue
            if self.__match_easyline(line, 'custom'):      #Try agaisnt Easy List
                continue
            if self.__match_unixline(line, 'custom'):      #Try against Unix List
                continue
            self.__match_defanged(line, 'custom')          #Finally try against Defanged

        print(f'Added {self.__domaincount} domains')       #Show stats for the list
        print(f'Deduplicated {self.__dedupcount} domains')


    def __process_easylist(self, lines, listname):
        """
        List of domains in Adblock+ filter format [https://adblockplus.org/filter-cheatsheet]
        1. Reset dedup and domain counters
        2. Read list of lines
        3. Check regex match against Regex_EasyLine
        4. Add domain

        Parameters:
            lines (list): List of lines
            listname (str): Blocklist name
        """
        self.__dedupcount = 0                              #Reset per list dedup count
        self.__domaincount = 0                             #Reset per list domain count

        print(f'{len(lines)} lines to process')

        for line in lines:                                 #Read through list
            matches = Regex_EasyLine.search(line)          #Search for first match

            if matches is not None:                        #Has a match been found?
                self.__add_domain(matches.group(1), '', listname)     #Add group 1 - Domain

        print(f'Added {self.__domaincount} domains')       #Show stats for the list
        print(f'Deduplicated {self.__dedupcount} domains')


    def __process_plainlist(self, lines, listname):
        """
        List of domains with optional # separated comments
        1. Reset dedup and domain counters
        2. Read list of lines
        3. Split each line by hash delimiter
        4. Add domain

        Parameters:
            lines (list): List of lines
            listname (str): Blocklist name
        """
        splitline = list()

        self.__dedupcount = 0                              #Reset per list dedup count
        self.__domaincount = 0                             #Reset per list domain count

        print(f'{len(lines)} lines to process')

        for line in lines:                                 #Read through list
            splitline = line.split('#', 1)                 #Split by hash delimiter

            if splitline[0] == '\n' or splitline[0] == '': #Ignore Comment line or Blank
                continue

            if len(splitline) > 1:                         #Line has a comment
                self.__add_domain(splitline[0][:-1], splitline[1][:-1], listname)

            else:                                          #No comment, leave it blank
                self.__add_domain(splitline[0][:-1], '', listname)

        print(f'Added {self.__domaincount} domains')       #Show stats for the list
        print(f'Deduplicated {self.__dedupcount} domains')


    def __process_unixlist(self, lines, listname):
        """
        List of domains starting with either 0.0.0.0 or 127.0.0.1 domain.com
        1. Reset dedup and domain counters
        2. Read list of lines
        3. Check regex match against Regex_UnixLine
        4. Add domain
        Parameters:
            lines (list): List of lines
            listname (str): Blocklist name
        """

        self.__dedupcount = 0                              #Reset per list dedup count
        self.__domaincount = 0                             #Reset per list domain count

        print(f'{len(lines)} lines to process')

        for line in lines:                                 #Read through list
            matches = Regex_UnixLine.search(line)          #Search for first match
            if matches is not None:                        #Has a match been found?
                self.__add_domain(matches.group(1), '', listname)  #Add group 1 - Domain

        print(f'Added {self.__domaincount} domains')       #Show stats for the list
        print(f'Deduplicated {self.__dedupcount} domains')


    def __process_tldlist(self):
        """
        Load users black & white tld lists
        Load NoTrack provided tld csv
        Create self.__blocktldset from high risk tld not in whitelist and low risk tld in blacklist
        Check for any domains in users whitelist that would be blocked by tld
        Save whitelist of domains from previous step
        """
        reverse = ''                                       #Reversed TLD
        dns_whitelist = list()
        tld_black = set()
        tld_white = set()
        tld_blackfile = list()
        tld_whitefile = list()
        tld_csv = list()

        self.__domaincount = 0                             #Reset per list domain count

        print('Processing Top Level Domain list')
        tld_blackfile = load_file(self.__folders.tld_blacklist)
        tld_whitefile = load_file(self.__folders.tld_whitelist)
        tld_csv = self.__read_csv(self.__folders.tld_csv)

        #Read tld's from tld_blacklist into tld_black dictionary
        for line in tld_blackfile:
            matches = Regex_TLDLine.search(line)
            if matches is not None:
                tld_black.add(matches.group(1))

        #Read tld's from tld_whitelist into tld_white dictionary
        for line in tld_whitefile:
            matches = Regex_TLDLine.search(line)
            if matches is not None:
                tld_white.add(matches.group(1))

        for row in tld_csv:
            reverse = row[0][::-1] + '.'

            if row[2] == '1':                              #Risk 1 - High Risk
                if row[0] not in tld_white:                #Is tld not in whitelist?
                    self.__blocktldset.add(row[0])         #Add high risk tld
                    self.__blocklist.append(tuple([reverse, row[0], row[1], 'bl_tld', ]))
                    self.__domaincount += 1

            else:
                if row[0] in tld_black:                    #Low risk, but in Black list
                    self.__blocktldset.add(row[0])         #Add low risk tld
                    self.__blocklist.append(tuple([reverse, row[0], row[1], 'bl_tld', ]))
                    self.__domaincount += 1

        print(f'Added {self.__domaincount} Top Level Domains')

        #Check for white listed domains that are blocked by tld
        for line in self.__whiteset:
            matches = Regex_Domain.search(line)            #Only need the tld
            if matches.group(2) in self.__blocktldset:     #Is tld in self.__blocktldset?
                dns_whitelist.append(self.__add_whitelist(line))

        if len(dns_whitelist) > 0:                         #Any domains in whitelist?
            print(f'{len(dns_whitelist)} domains added to whitelist in order avoid block from TLD')
            save_file(dns_whitelist, self.__folders.dnslists + 'whitelist.list')

        else:
            print('No domains require whitelisting')
            delete_file(self.__folders.dnslists + 'whitelist.list')

        self.__whiteset.clear()                            #self.__whiteset no longer required
        print()


    def __process_whitelist(self):
        """
        Load items from whitelist file into self.__blockdomianset array
            (A domain being in the self.__blocklist will prevent it from being added later)
        """
        whitedict_len = 0
        sqldata = list()
        splitline = list()

        print('Processing whitelist:')
        print(f'Loading whitelist {self.__folders.whitelist}')

        filelines = load_file(self.__folders.whitelist)    #Load White list

        if filelines == None:
            print('Nothing in whitelist')
            delete_file(self.__folders.dnslists + 'whitelist.list')
            return

        for line in filelines:                             #Process each line
            splitline = line.split('#', 1)
            if splitline[0] == '\n' or splitline[0] == '': #Ignore Comment line or Blank
                continue

            self.__blockdomianset.add(splitline[0][:-1])
            self.__whiteset.add(splitline[0][:-1])

            if len(splitline) > 1:                         #Line has a comment
                sqldata.append(tuple(['whitelist', splitline[0][:-1], True, splitline[1][:-1]]))
            else:                                          #No comment, leave it blank
                sqldata.append(tuple(['whitelist', splitline[0][:-1], True, '']))

        #Count number of domains white listed
        whitedict_len = len(self.__whiteset)

        if whitedict_len > 0:
            print(f'Number of domains in whitelist: {whitedict_len}')
            dbwrapper.blocklist_insertdata(sqldata)
        else:
            print('Nothing in whitelist')
            delete_file(self.__folders.dnslists + 'whitelist.list')
        print()


    def __check_file_age(self, filename):
        """
        Does file exist?
        Check last modified time is within MAX_AGE (2 days)

        Parameters:
            filename (str): File
        Returns:
            True update list
            False list within MAX_AGE
        """
        print(f'Checking age of {filename}')

        if not os.path.isfile(filename):
            print('File missing')
            return True

        if CURRENT_TIME > (os.path.getmtime(filename) + MAX_AGE):
            print('File older than 2 days')
            return True

        print('File in date, skip downloading new copy')
        return False


    def __download_list(self, url, listname, destination):
        """
        Download file
        Request file is unzipped (if necessary)

        Parameters:
            url (str): URL
            listname (str): List name
            destination (str): File destination
        Returns:
            True success
            False failed download
        """
        extension = ''
        outputfile = ''

        #Prepare for writing downloaded file to temp folder
        if url.endswith('zip'):                            #Check file extension
            extension = 'zip'
            outputfile = '%s%s.zip' % (self.__folders.tempdir, listname)

        else:                                              #Other - Assume txt for output
            extension = 'txt'
            outputfile = destination

        if not download_file(url, outputfile):
            return False

        if extension == 'zip':                             #Extract zip file?
            self.__extract_list(outputfile, destination)

        return True


    def __action_lists(self):
        """
        Go through config and process each enabled list
        1. Skip disabled lists
        2. Check if list is downloaded or locally stored
        3. For downloaded lists
        3a. Check file age
        3b. Download new copy if out of date
        4. Read file into filelines list
        5. Process list based on type
        """
        blname = ''                                        #Block list name (shortened)
        blenabled = False
        blurl = ''                                         #Block list URL
        bltype = ''                                        #Block list type
        blfilename = ''                                    #Block list file name

        for bl in blocklistconf.items():
            blname = bl[0]
            blenabled = bl[1][0]
            blurl = bl[1][1]
            bltype = bl[1][2]

            if not blenabled:                              #Skip disabled blocklist
                continue

            print(f'Processing {blname}:')

            #Is this a downloadable file or locally stored?
            if blurl.startswith('http') or blurl.startswith('ftp'):
                blfilename = self.__folders.tempdir + blname + '.txt' #Download to temp folder
                if self.__check_file_age(blfilename):                 #Does file need freshening?
                    self.__download_list(blurl, blname, blfilename)

            else:                                          #Local file
                blfilename = blurl;                        #URL is actually the filename

            if bltype == TYPE_SPECIAL:
                if blname == 'bl_tld':
                    self.__process_tldlist()
                    continue

            filelines = load_file(blfilename)              #Read temp file

            if not filelines:                              #Anything read from file?
                print('\tData missing unable to process %s' % blname)
                print()
                continue

            if bltype == TYPE_PLAIN:
                self.__process_plainlist(filelines, blname)
            elif bltype == TYPE_EASYLIST:
                self.__process_easylist(filelines, blname)
            elif bltype == TYPE_UNIXLIST:
                self.__process_unixlist(filelines, blname)

            print(f'Finished processing {blname}')
            print()


    def __action_customlists(self):
        """
        Go through config and process each enabled list
        1. Skip disabled lists
        2. Check if list is downloaded or locally stored
        3. For downloaded lists
        3a. Check file age
        3b. Download new copy if out of date
        4. Read file into filelines list
        5. Process list based on type
        """
        blname = ''
        blurl = ''                                         #Block list URL
        blfilename = ''                                    #Block list file name
        i = 0                                              #Loop position (for naming)
        customurllist = list()

        print('Processing Custom Blocklists:')
        if self.bl_custom == '':
            print('No custom blocklists set')
            print()
            return

        customurllist = self.bl_custom.split(',')     #Explode comma seperated vals

        for blurl in customurllist:
            i += 1
            blname = 'bl_custom%d' % i                     #Make up a name
            print(f'{blname} - {blurl}')

            #Is this a downloadable file or locally stored?
            if blurl.startswith('http') or blurl.startswith('ftp'):
                #Download to temp folder with loop position in file name
                blfilename = f'{self.__folders.tempdir}{blname}.txt'
                if self.__check_file_age(blfilename):      #Does file need freshening?
                    self.__download_list(blurl, blname, blfilename)

            else:                                          #Local file
                blfilename = blurl;

            filelines = load_file(blfilename)              #Read temp file
            if not filelines:                              #Anything read from file?
                print(f'Data missing unable to process {blname}')
                print()
                continue

            self.__process_customlist(filelines, blname)
            print(f'Finished processing {blname}')
            print()


    def __dedup_lists(self):
        """
        Final sort and then save list to file
        1. Sort the blocklist by the reversed domain (blocklist[x][0])
        2. Check if each item matches the beginning of the previous item
            (i.e. a subdomain of a blocked domain)
        3. Remove matched items from the list
        4. Add unique items into sqldata and blacklist
        5. Save blacklist to file
        6. Insert SQL data
        """
        prev = '\0'                                        #Previous has to be something (e.g. a null byte)
        dns_blacklist = list()
        sqldata = list()

        self.__dedupcount = 0
        print()
        print('Sorting and Deduplicating blocklist')

        self.__blocklist.sort(key=lambda x: x[0])          #Sort list on col0 "reversed"
        for item in self.__blocklist:
            if item[0].startswith(prev):
                #print('Removing:', item)
                #self.__blocklist.remove(item)
                self.__dedupcount += 1
            else:
                #self.__blocklist.append(tuple([reverse, subdomain, comment, source]))
                dns_blacklist.append(self.__add_blacklist(item[1]))
                sqldata.append(tuple([item[3], item[1], True, item[2]]))
                prev = item[0]

        print(f'Further deduplicated {self.__dedupcount} domains')
        print(f'Final number of domains in blocklist: {len(dns_blacklist)}')

        save_file(dns_blacklist, self.__folders.dnslists + 'notrack.list')
        self.__dbwrapper.blocklist_insertdata(sqldata)


    def __generate_blacklist(self):
        """
        Check to see if black list exists in NoTrack config folder
        If it doesn't then generate some example commented out domains to block
        """

        if os.path.isfile(self.__folders.blacklist):       #Check if black list exists
            return False

        tmp = list()                                       #List to build contents of file
        print('Creating Black List')
        tmp.append('#Use this file to create your own custom block list\n')
        tmp.append('#Run notrack script (sudo notrack) after you make any changes to this file\n')
        tmp.append('#doubleclick.net\n')
        tmp.append('#googletagmanager.com\n')
        tmp.append('#googletagservices.com\n')
        tmp.append('#polling.bbc.co.uk #BBC Breaking News Popup\n')
        save_file(tmp, self.__folders.blacklist)
        print()


    def __generate_whitelist(self):
        """
        Check to see if white list exists in NoTrack config folder
        If it doesn't then generate some example commented out domains to allow
        """

        if os.path.isfile(self.__folders.whitelist):       #Check if white list exists
            return False

        tmp = list()                                       #List to build contents of file
        print('Creating White List')
        tmp.append('#Use this file to create your own custom block list\n')
        tmp.append('#Run notrack script (sudo notrack) after you make any changes to this file\n')
        tmp.append('#doubleclick.net\n')
        tmp.append('#googletagmanager.com\n')
        tmp.append('#googletagservices.com\n')
        save_file(tmp, self.__folders.whitelist)
        print()


    def create_blocklist(self):
        """
        Create blocklist and restart DNS Server
        """
        print()
        self.__dbwrapper.blocklist_createtable()                      #Create SQL Tables
        self.__dbwrapper.blocklist_cleartable()                       #Clear SQL Tables

        self.__generate_blacklist()
        self.__generate_whitelist()

        self.__process_whitelist()                                    #Need whitelist first
        self.__action_lists()                                         #Action default lists
        self.__action_customlists()                                   #Action users custom lists

        print('Finished processing all block lists')
        print('Total number of domains added: %d' % len(self.__blocklist))
        print('Total number of domains deduplicated: %d' % self.__totaldedupcount)

        self.__dedup_lists()                                          #Dedup then insert domains
        self.__services.restart_dnsserver()


    def disable_blocking(self):
        """
        Move blocklist to temp folder
        """
        if move_file(self.__folders.main_blocklist, self.__folders.temp_blocklist):
            print('Moving blocklist to temp folder')
        else:
            print('Blocklist missing')

        self.__services.restart_dnsserver()


    def enable_blockling(self):
        """
        Move temp blocklist back to DNS config folder
        """
        if move_file(self.__folders.temp_blocklist, self.__folders.main_blocklist):
            print('Moving temp blocklist back')
            self.__services.restart_dnsserver()

        else:
            print('Temp blocklist missing, I will recreate it')
            self.create_blocklist()


    def load_blconfig(self):
        """
        """
        blconfig = ''
        filelines = list()

        blconfig = f'{self.__folders.wwwconfdir}bl.php'

        print()
        print('Loading blocklist config:')

        if not os.path.isfile(blconfig):
            print('Blocklist config is missing, using default values')
            return

        filelines = load_file(blconfig)

        for line in filelines:
            matches = Regex_BlockListStatus.match(line)
            if matches is not None:
                self.set_blocklist_status(matches[1], matches[2])
                continue

            matches = Regex_BlockListCustom.match(line)
            if matches is not None:
                self.bl_custom = matches[1]
                continue


    def set_blocklist_status(self, blname, status):
        """
        """
        newstatus = False

        if status == 'true':
            newstatus = True

        if blname in blocklistconf:
            blocklistconf[blname][0] = newstatus;
            return True;

        return False;


def main():
    check_root()

    blockparser = BlockParser()
    blockparser.load_blconfig()
    blockparser.create_blocklist()

if __name__ == "__main__":
    main()
