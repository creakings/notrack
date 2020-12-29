#!/usr/bin/env python3
#Title       : NoTrack
#Description : This script will download latest block lists from various sources, then parse them into Dnsmasq
#Author      : QuidsUp
#Date        : 2015-01-14
#Version     : 20.12
#Usage       : sudo python notrack.py

#Standard imports
import os
import shutil
import sys
import time

#Local imports
import errorlogger
import folders
from blocklists import *
from config import NoTrackConfig
from host import Host
from ntrkshared import *
from ntrkmariadb import DBWrapper
from ntrkregex import *
from ntrkservices import Services
from statusconsts import *

#Create logger
logger = errorlogger.logging.getLogger(__name__)

#######################################
# Constants
#######################################
MAX_AGE = 172800 #2 days in seconds

class BlockParser:
    def __init__(self, dns_blockip):
        self.bl_custom = ''
        self.__dedupcount = 0                              #Per list deduplication count
        self.__domaincount = 0                             #Per list of added domains
        self.__totaldedupcount = 0
        self.__dnsserver_blacklist = ''                    #String for DNS Server Blacklist file
        self.__dnsserver_whitelist = ''                    #String for DNS Server Whitelist file

        self.__blocklist = list()                          #List of tupples for the blocklist
        self.__blockdomainset = set()                      #Domains in blocklist
        self.__blocktldset = set()                         #TLDs blocked
        self.__whiteset = set()                            #Domains in whitelist

        self.__services = Services()                       #Declare service class
        self.__dbwrapper = DBWrapper()                     #Declare MariaDB Wrapper

        #Fill in users blacklist and tld list locations
        blocklistconf['bl_blacklist'][1] = folders.blacklist
        blocklistconf['bl_tld'][1] = folders.tldist

        #Fill in __dnsserver_blacklist and __dnsserver_whitelist based on host IP
        self.__get_hostdetails(dns_blockip)


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


    def __get_hostdetails(self, dns_blockip):
        """
        Get Host Name and IP address for __dnsserver_blacklist and __dnsserver_whitelist
        """
        host = Host(dns_blockip)                   #Declare host class
        logger.info(f'Hostname: {host.name}, IP Address: {host.ip}')

        #Setup the template strings for writing out to black/white list files
        [self.__dnsserver_blacklist, self.__dnsserver_whitelist] = self.__services.get_dnstemplatestr(host.name, host.ip)


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
                    zipobj.extract(compressedfile, f'{folders.tempdir}/')
                    logger.debug(f'Extracting {compressedfile}')
                    move_file(f'{folders.tempdir}/{compressedfile}', destination)


    def __add_domain(self, subdomain, comment, source):
        """
        Process supplied domain and add it to self.__blocklist
        1. Extract domain.co.uk from say subdomain.domain.co.uk
        2. Check if domain.co.uk is in self.__blockdomainset
        3. If subdomain is actually a domain then record domain in self.__blockdomainset
        4. Reverse subdomain
        5. Append to self.__blocklist as [reverse, subdomain, comment, source]

        Parameters:
            subdomain (str): Subdomain or domain
            comment (str): A comment
            source (str): Block list name
        """
        reverse = ''                                       #Reversed domain

        matches = Regex_Domain.search(subdomain)

        if matches == None:                                #Could be a TLD instead?
            self.__add_tld(subdomain, comment, source)
            return

        if matches.group(0) in self.__blockdomainset:      #Blocked by domain or whitelisted?
            #logger.debug(f'{subdomain} is already in blockdomainset as {matches.group(0)}')
            self.__dedupcount += 1
            self.__totaldedupcount += 1
            return

        if matches.group(2) in self.__blocktldset:         #Blocked by TLD?
            #logger.debug(f'{subdomain} is blocked by TLD {matches.group(2)}')
            return

        if matches.group(0) == subdomain:                  #Add domain.co.uk to blockdomainset
            #logger.debug(f'Adding domain {subdomain}')
            self.__blockdomainset.add(subdomain)

        #Reverse the domain for later sorting and deduplication
        #An Extra dot is required to match other subdomains and avoid similar spellings
        reverse = subdomain[::-1] + '.'
        self.__blocklist.append(tuple([reverse, subdomain, comment, source]))
        self.__domaincount += 1


    def __add_tld(self, tld, comment, source):
        """
        Process TLD and add it to __blocktldset

        Parameters:
            tld (str): A possible Top Level Domain
            comment (str): A comment
            source (str): Block list name
        """
        matches = Regex_TLD.search(tld)

        if matches == None:                                #Don't know what it is
            return

        self.__blocktldset.add(tld)
        reverse = tld[::-1] + '.'
        self.__blocklist.append(tuple([reverse, tld, comment, source]))
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


    def __process_customlist(self, lines, linecount, listname):
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

        for line in lines:                                 #Read through list
            if self.__match_plainline(line, 'custom'):     #Try against Plain line
                continue
            if self.__match_easyline(line, 'custom'):      #Try against Easy List
                continue
            if self.__match_unixline(line, 'custom'):      #Try against Unix List
                continue
            self.__match_defanged(line, 'custom')          #Finally try against Defanged

        logger.info(f'Added {self.__domaincount}, Deduplicated {self.__dedupcount}')
        self.__dbwrapper.blockliststats_insert(listname, linecount, self.__domaincount)


    def __process_csv(self, filelines, linecount, listname):
        """
        List of domains in a CSV file, assuming cell 1 = domain, cell 2 = comment
        1. Reset dedup and domain counters
        2. Read list of filelines
        3. Check regex match against Regex_CSV
        4. Add domain and comment

        Parameters:
            filelines (list): List of lines from file to being processed
            listname (str): Blocklist name
        """
        self.__dedupcount = 0                              #Reset per list dedup count
        self.__domaincount = 0                             #Reset per list domain count

        for line in filelines:                                 #Read through list
            matches = Regex_CSV.match(line)

            if matches is not None:                        #Has a match been found?
                #Add Group 1 - Domain, Group 2 - Comment
                self.__add_domain(matches.group(1), matches.group(2), listname)

        logger.info(f'Added {self.__domaincount}, Deduplicated {self.__dedupcount}')
        self.__dbwrapper.blockliststats_insert(listname, linecount, self.__domaincount)


    def __process_easylist(self, lines, linecount, listname):
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

        for line in lines:                                 #Read through list
            matches = Regex_EasyLine.search(line)          #Search for first match

            if matches is not None:                        #Has a match been found?
                self.__add_domain(matches.group(1), '', listname)     #Add group 1 - Domain

        logger.info(f'Added {self.__domaincount}, Deduplicated {self.__dedupcount}')
        self.__dbwrapper.blockliststats_insert(listname, linecount, self.__domaincount)


    def __process_plainlist(self, lines, linecount, listname):
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

        for line in lines:                                 #Read through list
            splitline = line.split('#', 1)                 #Split by hash delimiter

            if splitline[0] == '\n' or splitline[0] == '': #Ignore Comment line or Blank
                continue

            if len(splitline) > 1:                         #Line has a comment
                self.__add_domain(splitline[0][:-1], splitline[1][:-1], listname)

            else:                                          #No comment, leave it blank
                self.__add_domain(splitline[0][:-1], '', listname)

        logger.info(f'Added {self.__domaincount}, Deduplicated {self.__dedupcount}')
        self.__dbwrapper.blockliststats_insert(listname, linecount, self.__domaincount)


    def __process_unixlist(self, lines, linecount, listname):
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

        for line in lines:                                 #Read through list
            matches = Regex_UnixLine.search(line)          #Search for first match
            if matches is not None:                        #Has a match been found?
                self.__add_domain(matches.group(1), '', listname)  #Add group 1 - Domain

        logger.info(f'Added {self.__domaincount}, Deduplicated {self.__dedupcount}')
        self.__dbwrapper.blockliststats_insert(listname, linecount, self.__domaincount)


    def __process_whitelist(self):
        """
        Load items from whitelist file into self.__blockdomainset array
            (A domain being in the self.__blocklist will prevent it from being added later)
        """
        whitedict_len = 0
        sqldata = list()
        splitline = list()

        print('Processing whitelist')

        filelines = load_file(folders.whitelist)    #Load White list

        if filelines == None:
            logger.info('Nothing in whitelist')
            delete(f'{folders.dnslists}whitelist.list')
            return

        for line in filelines:                             #Process each line
            splitline = line.split('#', 1)
            if splitline[0] == '\n' or splitline[0] == '': #Ignore Comment line or Blank
                continue

            self.__blockdomainset.add(splitline[0][:-1])
            self.__whiteset.add(splitline[0][:-1])

            if len(splitline) > 1:                         #Line has a comment
                sqldata.append(tuple(['whitelist', splitline[0][:-1], True, splitline[1][:-1]]))
            else:                                          #No comment, leave it blank
                sqldata.append(tuple(['whitelist', splitline[0][:-1], True, '']))

        #Count number of domains white listed
        whitedict_len = len(self.__whiteset)

        if whitedict_len > 0:
            logger.info(f'Number of domains in whitelist: {whitedict_len}')
            self.__dbwrapper.blocklist_insertdata(sqldata)
        else:
            logger.info('Nothing in whitelist')
            delete(f'{folders.dnslists}whitelist.list')


    def __tld_whitelist(self):
        """
        Any domains in whitelist impacted by the TLD blocks?
        This should be done after TLD and users block lists are processed
        """

        filelines = list()

        #Check for white listed domains that are blocked by tld
        for line in self.__whiteset:
            matches = Regex_Domain.search(line)            #Only need the tld
            if matches.group(2) in self.__blocktldset:     #Is tld in self.__blocktldset?
                filelines.append(self.__add_whitelist(line))

        if len(filelines) > 0:                         #Any domains in whitelist?
            logger.debug(f'{len(filelines)} domains added to whitelist in order avoid block from TLD')
            save_file(filelines, f'{folders.dnslists}whitelist.list')

        else:
            logger.debug('No domains require whitelisting')
            delete(f'{folders.dnslists}whitelist.list')

        self.__whiteset.clear()                            #whiteset is no longer required


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
        if not os.path.isfile(filename):
            logger.warning(f'{filename} is missing')
            return True

        if time.time() > (os.path.getmtime(filename) + MAX_AGE):
            logger.info(f'{filename} older than 2 days')
            return True

        logger.info(f'{filename} in date, skip downloading new copy')
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
            outputfile = f'{folders.tempdir}/{listname}.zip'

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
        linecount = 0

        for bl in blocklistconf.items():
            blname = bl[0]
            blenabled = bl[1][0]
            blurl = bl[1][1]
            bltype = bl[1][2]

            if not blenabled:                              #Skip disabled blocklist
                continue

            print(f'Processing {blname}')

            #Is this a downloadable file or locally stored?
            if blurl.startswith('http') or blurl.startswith('ftp'):
                blfilename = f'{folders.tempdir}/{blname}.txt' #Download to temp folder
                if self.__check_file_age(blfilename):                 #Does file need freshening?
                    self.__download_list(blurl, blname, blfilename)

            else:                                          #Local file
                blfilename = blurl;                        #URL is actually the filename

            filelines = load_file(blfilename)              #Read temp file
            linecount = len(filelines)

            if not filelines:                              #Anything read from file?
                logger.warning(f'Data missing unable to process {blname}')
                continue

            if bltype == TYPE_PLAIN:
                self.__process_plainlist(filelines, linecount, blname)
            elif bltype == TYPE_EASYLIST:
                self.__process_easylist(filelines, linecount, blname)
            elif bltype == TYPE_UNIXLIST:
                self.__process_unixlist(filelines, linecount, blname)
            elif bltype == TYPE_CSV:
                self.__process_csv(filelines, linecount, blname)

            print(f'Finished processing {blname}')


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

        print('Processing Custom Blocklists')
        if self.bl_custom == '':
            logger.debug('No custom blocklists files or URLs set')
            return

        customurllist = self.bl_custom.split(',')          #Explode comma seperated vals

        for blurl in customurllist:
            i += 1
            blname = f'bl_custom{i}'                       #Make up a name
            logger.info(f'{blname} - {blurl}')

            #Is this a downloadable file or locally stored?
            if blurl.startswith('http') or blurl.startswith('ftp'):
                #Download to temp folder with loop position in file name
                blfilename = f'{folders.tempdir}/{blname}.txt'
                if self.__check_file_age(blfilename):      #Does file need freshening?
                    self.__download_list(blurl, blname, blfilename)

            else:                                          #Local file
                blfilename = blurl;

            filelines = load_file(blfilename)              #Read temp file
            if not filelines:                              #Anything read from file?
                logger.warning(f'File missing, unable to process {blname}')
                continue

            self.__process_customlist(filelines, blname)
            logger.info(f'Finished processing {blname}')


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

        save_file(dns_blacklist, f'{folders.dnslists}notrack.list')
        self.__dbwrapper.blocklist_insertdata(sqldata)


    def create_blocklist(self):
        """
        Create blocklist and restart DNS Server
        """
        self.__dbwrapper.blocklist_createtable()                      #Create SQL Tables
        self.__dbwrapper.blockliststats_createtable()
        self.__dbwrapper.blocklist_cleartable()                       #Clear SQL Tables

        self.__process_whitelist()                                    #Need whitelist first
        self.__action_lists()                                         #Action default lists
        self.__action_customlists()                                   #Action users custom lists
        self.__tld_whitelist()

        print('Finished processing all block lists')
        print(f'Total number of domains added: {len(self.__blocklist)}')
        print(f'Total number of domains deduplicated: {self.__totaldedupcount}')

        self.__dedup_lists()                                          #Dedup then insert domains
        self.__services.restart_dnsserver()


    def disable_blocking(self):
        """
        Move blocklist to temp folder
        """
        if move_file(folders.main_blocklist, folders.temp_blocklist):
            logger.info('Moving blocklist to temp folder')
        else:
            logger.warning('Blocklist missing')

        self.__services.restart_dnsserver()


    def enable_blockling(self):
        """
        Move temp blocklist back to DNS config folder
        """
        if move_file(folders.temp_blocklist, folders.main_blocklist):
            logger.info('Moving temp blocklist back')
            self.__services.restart_dnsserver()

        else:
            logger.warning('Temp blocklist missing, I will recreate it')
            self.create_blocklist()


    def load_blconfig(self):
        """
        """
        blconfig = ''                                      #Blocklist Config File
        filelines = list()

        blconfig = f'{folders.webconfigdir}/bl.php'

        if not os.path.isfile(blconfig):
            logger.warning('Blocklist config is missing, using default values')
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
        Set Blocklist status from config
        """
        newstatus = False

        if status == 'true':
            newstatus = True

        if blname in blocklistconf:
            blocklistconf[blname][0] = newstatus;
            return True;

        return False;


def main():
    print('NoTrack Block List Parser')
    config = NoTrackConfig()
    check_root()

    blockparser = BlockParser(config.dns_blockip)
    blockparser.load_blconfig()
    blockparser.create_blocklist()
    print('Finished created block list for NoTrack :-)')
    print()

if __name__ == "__main__":
    main()
