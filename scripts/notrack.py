#!/usr/bin/env python3
#Title : NoTrack
#Description : This script will download latest block lists from various sources, then parse them into Dnsmasq.
#Author : QuidsUp
#Date : 2015-01-14
#Version : 0.9.5
#Usage : sudo bash notrack.sh

from time import time
from urllib.request import Request, urlopen
from urllib.error import HTTPError, URLError
import argparse
import os
import re
import shutil
import stat
import subprocess
import sys
import mysql.connector as mariadb

#######################################
# Constants
#######################################
VERSION = '0.9.5'
TYPE_PLAIN = 1
TYPE_UNIXLIST = 2
TYPE_EASYLIST = 4
TYPE_CSV = 8
TYPE_SPECIAL = 64

FORCE = False
CURRENT_TIME = time()
MAX_AGE = 172800 #2 days in seconds

#######################################
# Global Lists / Dictionaries
#######################################
blocklist = []
blockdomiandict = {}
blocktlddict = {}
whitedict = {}

dnsserver_whitelist = 'server=/%s/#\n'
dnsserver_blacklist = 'address=/%s/192.168.62.90\n'

#######################################
# Global Variables
#######################################
dedupcount = 0
domaincount = 0
totaldedupcount = 0

Regex_Defanged = re.compile('^(?:f[txX]p|h[txX][txX]ps?)\[?:\]?\/\/([\w\.\-_\[\]]{1,250}\[?\.\]?[\w\-]{2,63})')

#Regex to extract domain.co.uk from subdomain.domain.co.uk
Regex_Domain = re.compile('([\w\-_]{1,63})(\.(?:co\.|com\.|org\.|edu\.|gov\.)?[\w\-_]{1,63}$)')

#Regex EasyList Line:
#|| Marks active domain entry
#Group 1: domain.com
#Non-capturing group: Domain ending
#Non-capturing group: Against document type: Acceptable - third-party, doc, popup
Regex_EasyLine = re.compile('^\|\|([\w\.\-_]{1,250}\.[\w\-]{2,63})(?:\^|\.)(?:\$third\-party|\$doc|\$popup|\$popup\,third\-party)?\n$')

#Regex Plain Line
#Group 1: domain.com
#Group 2: optional comment.
#Utilise negative lookahead to make sure that two hashes aren't next to each other,
# as this could be an EasyList element hider
Regex_PlainLine = re.compile('^([\w\.\-_]{1,250}\.[\w\-]{2,63})( #(?!#).*)?\n$')

#Regex TLD Line:
Regex_TLDLine = re.compile('^(\.\w{1,63})(?:\s#.*)?\n$')

#Regex Unix Line
Regex_UnixLine = re.compile('^(?:0|127)\.0\.0\.[01]\s+([\w\.\-_]{1,250}\.[\w\-]{2,63})\s*#?(.*)\n$')

blocklistconf = {
    'bl_tld' : [True, '', TYPE_SPECIAL],
    'bl_blacklist' : [True, '', TYPE_PLAIN],
    'bl_notrack' : [True, 'https://gitlab.com/quidsup/notrack-blocklists/raw/master/notrack-blocklist.txt', TYPE_PLAIN],
    'bl_notrack_malware' : [True, 'https://gitlab.com/quidsup/notrack-blocklists/raw/master/notrack-malware.txt', TYPE_PLAIN],
    'bl_cbl_all' : [False, 'https://zerodot1.gitlab.io/CoinBlockerLists/list.txt', TYPE_PLAIN],
    'bl_cbl_browser' : [False, 'https://zerodot1.gitlab.io/CoinBlockerLists/list_browser.txt', TYPE_PLAIN],
    'bl_cbl_opt' : [False, 'https://zerodot1.gitlab.io/CoinBlockerLists/list_optional.txt', TYPE_PLAIN],
    'bl_cedia' : [False, 'http://mirror.cedia.org.ec/malwaredomains/domains.zip', TYPE_CSV],
    'bl_cedia_immortal' : [True, 'http://mirror.cedia.org.ec/malwaredomains/immortal_domains.zip', TYPE_PLAIN],
    'bl_hexxium' : [False, 'https://hexxiumcreations.github.io/threat-list/hexxiumthreatlist.txt', TYPE_EASYLIST],
    'bl_disconnectmalvertising' : [False, 'https://s3.amazonaws.com/lists.disconnect.me/simple_malvertising.txt', TYPE_PLAIN],
    'bl_easylist' : [False, 'https://easylist-downloads.adblockplus.org/easylist_noelemhide.txt', TYPE_EASYLIST],
    'bl_easyprivacy' : [False, 'https://easylist-downloads.adblockplus.org/easyprivacy.txt', TYPE_EASYLIST],
    'bl_fbannoyance' : [False, 'https://easylist-downloads.adblockplus.org/fanboy-annoyance.txt', TYPE_EASYLIST],
    'bl_fbenhanced' : [False, 'https://www.fanboy.co.nz/enhancedstats.txt', TYPE_EASYLIST],
    'bl_fbsocial' : [False, 'https://secure.fanboy.co.nz/fanboy-social.txt', TYPE_EASYLIST],
    'bl_hphosts' : [False, 'http://hosts-file.net/ad_servers.txt', TYPE_UNIXLIST],
    'bl_malwaredomainlist' : [False, 'http://www.malwaredomainlist.com/hostslist/hosts.txt', TYPE_UNIXLIST],
    'bl_malwaredomains' : [False, 'http://mirror1.malwaredomains.com/files/justdomains', TYPE_PLAIN],
    'bl_pglyoyo' : [False, 'http://pgl.yoyo.org/adservers/serverlist.php?hostformat=;mimetype=plaintext', TYPE_PLAIN],
    'bl_someonewhocares' : [False, 'http://someonewhocares.org/hosts/hosts', TYPE_UNIXLIST],
    'bl_spam404' : [False, 'https://raw.githubusercontent.com/Dawsey21/Lists/master/adblock-list.txt', TYPE_EASYLIST],
    'bl_swissransom' : [False, 'https://ransomwaretracker.abuse.ch/downloads/RW_DOMBL.txt', TYPE_PLAIN],
    'bl_winhelp2002' : [False, 'http://winhelp2002.mvps.org/hosts.txt', TYPE_UNIXLIST],
    'bl_windowsspyblocker' : [False, 'https://raw.githubusercontent.com/crazy-max/WindowsSpyBlocker/master/data/hosts/update.txt', TYPE_UNIXLIST],
    'bl_areasy' : [False, 'https://easylist-downloads.adblockplus.org/Liste_AR.txt', TYPE_EASYLIST],                      #Arab
    'bl_chneasy' : [False, 'https://easylist-downloads.adblockplus.org/easylistchina.txt', TYPE_EASYLIST],                #China
    'bl_deueasy' : [False, 'https://easylist-downloads.adblockplus.org/easylistgermany.txt', TYPE_EASYLIST],              #Germany
    'bl_dnkeasy' : [False, 'https://adblock.dk/block.csv', TYPE_EASYLIST],                                                #Denmark
    'bl_fblatin' : [False, 'https://www.fanboy.co.nz/fanboy-espanol.txt', TYPE_EASYLIST],                                 #Portugal/Spain (Latin Countries)
    'bl_fineasy' : [False, 'https://raw.githubusercontent.com/finnish-easylist-addition/finnish-easylist-addition/master/Finland_adb_uBO_extras.txt', TYPE_EASYLIST],                                     #Finland
    'bl_fraeasy' : [False, 'https://easylist-downloads.adblockplus.org/liste_fr.txt', TYPE_EASYLIST],                     #France
    'bl_grceasy' : [False, 'https://www.void.gr/kargig/void-gr-filters.txt', TYPE_EASYLIST],                              #Greece
    'bl_huneasy' : [False, 'https://raw.githubusercontent.com/szpeter80/hufilter/master/hufilter.txt', TYPE_EASYLIST],    #Hungary
    'bl_idneasy' : [False, 'https://raw.githubusercontent.com/ABPindo/indonesianadblockrules/master/subscriptions/abpindo.txt',TYPE_EASYLIST ],#Indonesia
    'bl_isleasy' : [False, 'http://adblock.gardar.net/is.abp.txt', TYPE_EASYLIST],                                        #Iceland
    'bl_itaeasy' : [False, 'https://easylist-downloads.adblockplus.org/easylistitaly.txt', TYPE_EASYLIST],                #Italy
    'bl_jpneasy' : [False, 'https://raw.githubusercontent.com/k2jp/abp-japanese-filters/master/abpjf.txt', TYPE_EASYLIST],#Japan
    'bl_koreasy' : [False, 'https://raw.githubusercontent.com/gfmaster/adblock-korea-contrib/master/filter.txt', TYPE_EASYLIST],#Korea Easy List
    'bl_korfb' : [False, 'https://www.fanboy.co.nz/fanboy-korean.txt', TYPE_EASYLIST],                                    #Korea Fanboy
    'bl_koryous' : [False, 'https://raw.githubusercontent.com/yous/YousList/master/youslist.txt', TYPE_EASYLIST],         #Korea Yous
    'bl_ltueasy' : [False, 'http://margevicius.lt/easylistlithuania.txt', TYPE_EASYLIST],                                 #Lithuania
    'bl_lvaeasy' : [False, 'https://notabug.org/latvian-list/adblock-latvian/raw/master/lists/latvian-list.txt', TYPE_EASYLIST],#Latvia
    'bl_norfiltre' : [False, 'https://raw.githubusercontent.com/DandelionSprout/adfilt/master/NorwegianList.txt', TYPE_EASYLIST],   #Norway
    'bl_nldeasy' : [False, 'https://easylist-downloads.adblockplus.org/easylistdutch.txt', TYPE_EASYLIST],                #Netherlands
    'bl_poleasy' : [False, 'https://raw.githubusercontent.com/MajkiIT/polish-ads-filter/master/polish-adblock-filters/adblock.txt', TYPE_EASYLIST],#Polish
    'bl_ruseasy' : [False, 'https://easylist-downloads.adblockplus.org/ruadlist+easylist.txt', TYPE_EASYLIST],            #Russia
    'bl_spaeasy' : [False, 'https://easylist-downloads.adblockplus.org/easylistspanish.txt', TYPE_EASYLIST],              #Spain
    'bl_svneasy' : [False, 'https://raw.githubusercontent.com/betterwebleon/slovenian-list/master/filters.txt', TYPE_EASYLIST],#Slovenian
    'bl_sweeasy' : [False, 'https://www.fanboy.co.nz/fanboy-swedish.txt', TYPE_EASYLIST],                                 #Sweden
    'bl_viefb' : [False, 'https://www.fanboy.co.nz/fanboy-vietnam.txt', TYPE_EASYLIST],                                   #Vietnam Fanboy
    'bl_yhosts' : [False, 'https://raw.githubusercontent.com/vokins/yhosts/master/hosts', TYPE_UNIXLIST],                 #China yhosts
}

config = {
    'LatestVersion' : VERSION,
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
    'status' : 1,
    'unpausetime' : 0
}


#DBConfig loads the local settings for accessing MariaDB
class DBConfig:
    user = 'ntrk'
    password = 'ntrkpass'
    database = 'ntrkdb'

dbconf = DBConfig()

#FolderList stores the various file and folder locations by OS TODO complete for Windows
class FolderList:
    main_blocklist = ''
    blacklist = ''
    whitelist = ''
    tld_blacklist = ''
    tld_whitelist = ''
    tld_csv = ''
    notrack_config = ''
    temp = ''
    dnslists = ''

    def __init__(self):
        if os.name == 'posix':
            self.main_blocklist = '/etc/dnsmasq.d/notrack.list'
            self.dnslists = ''
            self.blacklist = '/etc/notrack/blacklist.txt'
            self.whitelist = '/etc/notrack/whitelist.txt'
            self.tld_blacklist = '/etc/notrack/domain-blacklist.txt'
            self.tld_whitelist = '/etc/notrack/domain-whitelist.txt'
            self.tld_csv = '/var/www/html/admin/include/tld.csv'
            self.notrack_config = '/etc/notrack/notrack.conf'
            self.temp = '/tmp/'

        blocklistconf['bl_blacklist'][1] = self.blacklist

folders = FolderList()


#Host gets the Name and IP address of this system
class Host:
    name = ''
    ip = ''

    def __init__(self):
        import socket

        self.name = socket.gethostname()                   #Host Name is easy to get

        #IP requires a connection out
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        #Connect to an unroutable 169.254.0.0/16 address on port 1
        s.connect(("169.254.0.255", 1))
        self.ip = s.getsockname()[0]
        s.close()



#Services is a class for identifing Service Supervisor, Web Server, and DNS Server
#Restarting the Service will use the appropriate Service Supervisor
class Services:
    __supervisor = ''                                      #Supervisor command
    __supervisor_name = ''                                 #Friendly name
    __webserver = ''
    __dnsserver = ''
    dhcp_config = ''

    def __init__(self):
        #Find service supervisor by checking if each application exists
        if shutil.which('systemctl') != None:
            self.__supervisor = 'systemctl'
            self.__supervisor_name = 'systemd'
        elif shutil.which('service') != None:
            self.__supervisor = 'service'
            self.__supervisor_name = 'systemctl'
        elif shutil.which('sv') != None:
            self.__supervisor = 'sv'
            self.__supervisor_name = 'ruinit'
        else:
            print('Services Init: Fatal Error - Unable to identify service supervisor')
            sys.exit(7)

        print('Services Init: Identified Service manager %s' % self.__supervisor_name)

        #Find DNS server by checking if each application exists
        if shutil.which('dnsmasq') != None:
            self.__dnsserver = 'dnsmasq'
            self.dhcp_config = '/etc/dnsmasq.d/dhcp.conf'
        elif shutil.which('bind') != None:
            self.__dnsserver = 'bind'
        else:
            print('Services Init: Fatal Error - Unable to identify DNS server')
            sys.exit(8)
        print('Services Init: Identified DNS server %s' % self.__dnsserver)

        #Find Web server by checking if each application exists
        if shutil.which('lighttpd') != None:
            self.__webserver = 'lighttpd'
        elif shutil.which('apache') != None:
            self.__webserver = 'apache'
        else:
            print('Services Init: Fatal Error - Unable to identify Web server')
            sys.exit(9)
        print('Services Init: Identified Web server %s' % self.__webserver)


    """ Restart Service
        Restart specified service and return code
    Args:
        service to restart
    Returns:
        True on Success (return code zero)
        False on Failure (return code non-zero)
    """
    def __restart_service(self, service):
        p = subprocess.run(['sudo', self.__supervisor, 'restart', service], stderr=subprocess.PIPE, universal_newlines=True)

        if p.returncode != 0:
            print('Services restart_service: Failed to restart %s' % service)
            print(p.stderr)
            return False
        else:
            print('Successfully restarted %s' % service)
            return True

    #Restart DNS Server - returns the result of restart_service
    def restart_dnsserver(self):
        return self.__restart_service(self.__dnsserver)

    #Restart Web Server - returns the result of restart_service
    def restart_webserver(self):
        return self.__restart_service(self.__webserver)

#End Classes---------------------------------------------------------

def add_blacklist(domain):
    return dnsserver_blacklist % domain

def add_whitelist(domain):
    return dnsserver_whitelist % domain

def str2bool(v):
    return v.lower() in ('1', 'true', 'yes')

""" Load Config
Args:
    None
Returns:
    None
"""
def load_config():
    global blocklistconf

    Regex_BLLine = re.compile('^(bl_[a-z_]+)\s+=\s+([01])\n')
    Regex_ConfLine = re.compile('^([\w_]+)\s+=\s+(.+)\n')

    conflines = read_file(folders.notrack_config)

    for line in conflines:
        matches = Regex_BLLine.search(line)
        if matches is not None:
            if matches.group(1) in blocklistconf:
                blocklistconf[matches.group(1)][0] = str2bool(matches.group(2))

        matches = Regex_ConfLine.search(line)
        if matches is not None:
            if matches.group(1) in config:
                config[matches.group(1)] = matches.group(2)

""" Create SQL Tables
    Create SQL tables for blocklist, in case it has been deleted
Args:
    None
Returns:
    None
"""
def create_sqltables():
    cursor = db.cursor()

    cmd = 'CREATE TABLE IF NOT EXISTS blocklist (id SERIAL, bl_source TINYTEXT, site TINYTEXT, site_status BOOLEAN, comment TEXT)';
    print('Checking SQL Table blocklist exists')

    cursor.execute(cmd);
    cursor.close()

def clear_table():
    cursor = db.cursor()

    cursor.execute('DELETE FROM blocklist')
    cursor.execute('ALTER TABLE blocklist AUTO_INCREMENT = 1')
    cursor.close()


def insert_data(sqldata):

    cursor = db.cursor()

    cmd = "INSERT INTO blocklist (id, bl_source, site, site_status, comment) VALUES (NULL, %s, %s, %s, %s)"

    cursor.executemany(cmd, sqldata)
    db.commit()
    cursor.close()

""" Add Domain
    Process supplied domain and and it to blocklist:
    1. Extract domain.co.uk from say subdomain.domain.co.uk
    2. Check if domain.co.uk is in blockdomiandict
    3. if subdomain is actually a Domain then record Domain in blockdomiandict
    4. Reverse subdomain
    5. Append to blocklist as [reverse, subdomain, comment, source]
Args:
    Subdomain - Subdomain or Domain
    Comment - A comment
    Source - Block list name
Returns:
    None
"""
def add_domain(subdomain, comment, source):
    global blocklist, dedupcount, domaincount, totaldedupcount

    reverse = ''

    matches = Regex_Domain.search(subdomain)
    if matches == None:                                    #Shouln't happen
        return

    if matches.group(0) in blockdomiandict:                #Blocked by domain or whitelisted?
        #print('\t%s is already in blockdomiandict as %s' % (subdomain, matches.group(0)))
        dedupcount += 1
        totaldedupcount += 1
        return

    if matches.group(2) in blocktlddict:                   #Blocked by TLD?
        #print('\t%s is blocked by TLD as %s' % (subdomain, matches.group(2)))
        return

    if matches.group(0) == subdomain:                      #Add domain.co.uk to blockdomiandict
        #print('Adding domain %s' % subdomain)
        blockdomiandict[subdomain] = True

    #Reverse the domain for later sorting and deduplication
    #As Extra dot is required to match other subdomains and avoid similar spellings
    reverse = subdomain[::-1] + '.'

    blocklist.append(tuple([reverse, subdomain, comment, source]))
    domaincount += 1


""" Read CSV
    Load contents of csv and return as a list
    1. Check file exists
    2. Read all lines of csv
Args:
    File to load
Returns:
    List of all lines in file
    Empty List if file doesn't exist
"""
def read_csv(filename):
    import csv
    data = []

    if not os.path.isfile(filename):
        print('\t%s is missing' % filename)
        return []

    f = open(filename)
    reader = csv.reader(f)
    data = (list(reader))
    f.close()

    return data


""" Read File
    Load contents of file and return as a list
    1. Check file exists
    2. Read all lines of file
Args:
    File to load
Returns:
    List of all lines in file
    Empty List if file doesn't exist
"""
def read_file(filename):
    filelines = []

    if not os.path.isfile(filename):
        print('\t%s is missing' % filename)
        return []

    print('\tReading data from %s' % filename)
    f = open(filename, 'r')
    filelines = f.readlines()
    f.close()

    return filelines


def save_file(domains, filename):
    f = open(filename, 'w')
    f.writelines(domains)
    f.close()


""" Match Defanged Line
    Checks custom blocklist file line against Defanged List line regex
Args:
    Line from file
    Blocklist Name
Returns:
    True on successful match
    False when no match is found
"""
def match_defanged(line, listname):
    matches = Regex_Defanged.search(line)                  #Search for first match
    if matches is not None:                                #Has a match been found?
        #Add group 1 - Domain and replace defanged [.] with .
        add_domain(matches.group(1).replace('[.]', '.'), '', listname)
        return True

    return False                                           #Nothing found, return False

""" Match Easy Line
    Checks custom blocklist file line against Easy List line regex
Args:
    Line from file
    Blocklist Name
Returns:
    True on successful match
    False when no match is found
"""
def match_easyline(line, listname):
    matches = Regex_EasyLine.search(line)                  #Search for first match
    if matches is not None:                                #Has a match been found?
        add_domain(matches.group(1), '', listname)         #Add group 1 - Domain
        return True

    return False                                           #Nothing found, return False


""" Match Plain Line
    Checks custom blocklist file line against Plain List line regex
Args:
    Line from file
    Blocklist Name
Returns:
    True on successful match
    False when no match is found
"""
def match_plainline(line, listname):
    matches = Regex_PlainLine.search(line)                 #Search for first match
    if matches is not None:                                #Has a match been found?
        add_domain(matches.group(1), matches.group(2), listname)
        return True

    return False                                           #Nothing found, return False


""" Match Unix Line
    Checks custom blocklist file line against Unix List line regex
Args:
    Line from file
    Blocklist Name
Returns:
    True on successful match
    False when no match is found
"""
def match_unixline(line, listname):
    matches = Regex_UnixLine.search(line)                  #Search for first match
    if matches is not None:                                #Has a match been found?
        add_domain(matches.group(1), matches.group(2), listname)
        return True

    return False                                           #Nothing found, return False


#readonly REGEX_DEFANGED="^(f[xX]p|ftp|h[xX][xX]ps?|https?):\/\/([\.A-Za-z0-9_-]+)\/?.*$"



""" Process Custom List
    1. Reset Dedup and Domain counters

Args:
    List of Lines
    Blocklist Name
Returns:
    None
"""
def process_customlist(lines, listname):
    global dedupcount, domaincount

    dedupcount = 0                                         #Reset per list dedup count
    domaincount = 0                                        #Reset per list domain count

    print('\t%d lines to process' % len(lines))

    for line in lines:                                     #Read through list
        if match_plainline(line, listname):
            continue
        if match_easyline(line, listname):
            continue
        if match_unixline(line, listname):
            continue
        if match_defanged(line, listname):
            continue

    print('\tAdded %d domains' % domaincount)              #Show stats for the list
    print('\tDeduplicated %d domains' % dedupcount)


""" Process Easy List
    List of domains in Adblock+ filter format [https://adblockplus.org/filter-cheatsheet]
    1. Reset Dedup and Domain counters
    2. Read list of lines
    3. Check regex match against Regex_EasyLine
    4. Add Domain
Args:
    List of Lines
    Blocklist Name
Returns:
    None
"""
def process_easylist(lines, listname):
    global dedupcount, domaincount

    dedupcount = 0                                         #Reset per list dedup count
    domaincount = 0                                        #Reset per list domain count

    print('\t%d lines to process' % len(lines))

    for line in lines:                                     #Read through list
        matches = Regex_EasyLine.search(line)              #Search for first match
        if matches is not None:                            #Has a match been found?
            add_domain(matches.group(1), '', listname)     #Add group 1 - Domain

    print('\tAdded %d domains' % domaincount)              #Show stats for the list
    print('\tDeduplicated %d domains' % dedupcount)


""" Process Plain List
    List of domains with optional # seperated comments
    1. Reset Dedup and Domain counters
    2. Read list of lines
    3. Split each line by hash delimiter
    4. Add Domain
Args:
    List of Lines
    Blocklist Name
Returns:
    None
"""
def process_plainlist(lines, listname):
    global dedupcount, domaincount
    splitline = []

    dedupcount = 0                                         #Reset per list dedup count
    domaincount = 0                                        #Reset per list domain count

    print('\t%d lines to process' % len(lines))

    for line in lines:                                     #Read through list
        splitline = line.split('#', 1)                     #Split by hash delimiter
        if splitline[0] == '\n' or splitline[0] == '':     #Ignore Comment line or Blank
            continue

        if len(splitline) > 1:                             #Line has a comment
            add_domain(splitline[0][:-1], splitline[1][:-1], listname)
        else:                                              #No comment, leave it blank
            add_domain(splitline[0][:-1], '', listname)

    print('\tAdded %d domains' % domaincount)              #Show stats for the list
    print('\tDeduplicated %d domains' % dedupcount)


""" Process Unix List
    List of domains starting with either 0.0.0.0 or 127.0.0.1 domain.com
    1. Reset Dedup and Domain counters
    2. Read list of lines
    3. Check regex match against Regex_UnixLine
    4. Add Domain
Args:
    List of Lines
    Blocklist Name
Returns:
    None
"""
def process_unixlist(lines, listname):
    global dedupcount, domaincount

    dedupcount = 0                                         #Reset per list dedup count
    domaincount = 0                                        #Reset per list domain count

    print('\t%d lines to process' % len(lines))

    for line in lines:                                     #Read through list
        matches = Regex_UnixLine.search(line)              #Search for first match
        if matches is not None:                            #Has a match been found?
            add_domain(matches.group(1), '', listname)     #Add group 1 - Domain

    print('\tAdded %d domains' % domaincount)              #Show stats for the list
    print('\tDeduplicated %d domains' % dedupcount)


""" Process TLD List
    1. Load users black & white tld lists
    2. Load NoTrack provided tld csv
    3. Create blocktlddict from High risk tld not in whitelist and Low risk tld in blacklist
    4. Check for any domains in users whitelist that would be blocked by tld
    5. Save whitelist of domains from (4)
Args:
    None
Returns:
    None
"""
def process_tldlist():
    global blocklist, blocktlddict, domaincount

    #local name=""
    #local risk=""
    #local tld=""
    tld_black = {}
    tld_white = {}
    dns_whitelist = []

    domaincount = 0                                        #Reset per list domain count

    print('Processing Top Level Domain list')

    tld_blackfile = read_file(folders.tld_blacklist)
    tld_whitefile = read_file(folders.tld_whitelist)
    tld_csv = read_csv(folders.tld_csv)

    #Read tld's from tld_blacklist into tld_black dictionary
    for line in tld_blackfile:
        matches = Regex_TLDLine.search(line)
        if matches is not None:
            tld_black[matches.group(1)] = True

    #Read tld's from tld_whitelist into tld_white dictionary
    for line in tld_whitefile:
        matches = Regex_TLDLine.search(line)
        if matches is not None:
            tld_white[matches.group(1)] = True

    for row in tld_csv:
        reverse = row[0][::-1] + '.'
        if row[2] == '1':                                  #Risk 1 - High Risk
            if row[0] not in tld_white:                    #Is tld not in whitelist?
                blocktlddict[row[0]] = True                #Add high risk tld
                blocklist.append(tuple([reverse, row[0], 'tld', True, row[1]]))
                domaincount += 1
        else:
            if row[0] in tld_black:                        #Low risk, but in Black list
                blocktlddict[row[0]] = True                #Add low risk tld
                blocklist.append(tuple([reverse, row[0], 'tld', True, row[1]]))
                domaincount += 1

    print('\tAdded %d Top Level Domains' % domaincount)


    #Check for whitelisted domains that are blocked by tld
    for line in whitedict:
        matches = Regex_Domain.search(line)                #Only need the tld
        if matches.group(2) in blocktlddict:               #Is tld in blocktlddict?
            dns_whitelist.append(add_whitelist(line))

    if len(dns_whitelist) > 0:                             #Any domains in whitelist?
        print('\t%d domains added to whitelist in order avoid block from TLD' % len(dns_whitelist))
        save_file(dns_whitelist, folders.dnslists + 'whitelist.txt')
    else:
        print('\tNo domains require whitelisting')
        #delete TODO

    print()


""" Process White List
    1. Load items from whitelist file into blockdomiandict array
       (A domain being in the blocklist will prevent it from being added later)

Args:
    None
Returns:
    None
"""
def process_whitelist():
    global blockdomiandict, whitedict

    sqldata = []
    splitline = []

    print('Processing Whitelist')
    print('\tLoading whitelist %s' % folders.whitelist)

    filelines = read_file(folders.whitelist)
    if filelines == None:
        #TODO Delete old file
        return

    for line in filelines:
        splitline = line.split('#', 1)
        if splitline[0] == '\n' or splitline[0] == '':     #Ignore Comment line or Blank
            continue

        blockdomiandict[splitline[0][:-1]] = True
        whitedict[splitline[0][:-1]] = True

        if len(splitline) > 1:                             #Line has a comment
            sqldata.append(tuple(['whitelist', splitline[0][:-1], True, splitline[1][:-1]]))
        else:                                              #No comment, leave it blank
            sqldata.append(tuple(['whitelist', splitline[0][:-1], True, '']))

    insert_data(sqldata)
    #TODO Delete old file when whitelist empty
    print('\tNumber of domains in whitelist: %d' % len(blockdomiandict))
    print('')



def check_file_age(filename):
    print('\tChecking age of %s' % filename)
    if FORCE:
        print('\tForced update')
        return True

    if not os.path.isfile(filename):
        print('\tFile missing')
        return True

    if CURRENT_TIME > (os.path.getmtime(filename) + MAX_AGE):
        print('\tFile older than 2 days')
        return True

    print('\tFile in date, not downloading')
    return False


def download_file(request, listname, destination):
    extension = ''
    outputfile = ''

    print('\tDownloading %s' % request)

    for i in range(1, 4):
        try:
            response = urlopen(request)
        except HTTPError as e:
            if e.code >= 500 and e.code < 600:
                #Take another attempt up to max of for loop
                print('\tHTTP Error %d: Server side error' % e.code)
            elif e.code == 400:
                print('\tHTTP Error 400: Bad request')
                return False
            elif e.code == 403:
                print('\tHTTP Error 403: Unauthorised Access')
                return False
            elif e.code == 404:
                print('\tHTTP Error 404: Not Found')
                return False
            elif e.code == 429:
                print('\tHTTP Error 429: Too many requests')
            print('\t%s' % request)
        except URLError as e:
            if hasattr(e, 'reason'):
                print('\tError downloading %s' % request)
                print('\tReason: %s' % e.reason)
                return False
            elif hasattr(e, 'code'):
                print('\t%s' % request)
                print('Server was unable to fulfill the request')
                print('\tHTTP Error: %d' % e.code)
                return False
        else:
            res_code = response.getcode()
            if res_code == 200:                            #200 - Success
                break
            elif res_code == 204:                          #204 - Success but nothing
                print('\tHTTP Response 204: No data found')
                return False
            else:
                print('\t%s' % request)
                print('\tHTTP Response %d' % res_code)

        sleep(i * 2)                                       #Throttle repeat attemps


    #Prepare for writing downloaded file to temp folder
    if request.endswith('zip'):                            #Check file extension
        extension = 'zip'
        outputfile = '%s%s.zip' % (folders.temp, listname)
    else:                                                  #Other - Assume txt for output
        extension = 'txt'
        outputfile = destination

    #Write file to temp folder
    f = open(outputfile, 'wb')
    f.write(response.read())                               #Write output of download
    f.close()

    if extension == 'zip':                                 #Extract zip file?
        extract_zip(outputfile, destination)

    return True


def extract_zip(inputfile, destination):
    from zipfile import ZipFile

    with ZipFile(inputfile) as zipobj:
        for compressedfile in zipobj.namelist():
            if compressedfile.endswith('.txt'):
                zipobj.extract(compressedfile, folders.temp)
                print('\tExtracting %s' % compressedfile)
                move_file(folders.temp + compressedfile, destination)


""" Move File
    1. Check source exists
    2. Move file
Args:
    source
    destination
Returns:
    True on success
    False on failure
"""
def move_file(source, destination):
    if not os.path.isfile(source):
        print('Move_file: Error %s is missing' % source)
        return False

    #Copy specified file
    print('\tMoving %s to %s' % (source, destination))
    shutil.move(source, destination)

    if not os.path.isfile(destination):
        print('Move_file: Error %s does not exist. Copy failed')
        return False

    return True


""" Action Lists
    Go through config and process each enabled list
    1. Skip disabled lists
    2. Check if list is downloaded or locally stored
    3. For download lists:
    3a. Check file age
    3b. Download new copy if out of date
    4. Read file into filelines list
    5. Process list based on type
Args:
    None
Returns:
    None
"""
def action_lists():
    blname = ''                                            #Block list name (shortened)
    blenabled = False
    blurl = ''                                             #Block list URL
    bltype = ''                                            #Block list type
    blfilename = ''                                        #Block list file name

    for bl in blocklistconf.items():
        blname = bl[0]
        blenabled = bl[1][0]
        blurl = bl[1][1]
        bltype = bl[1][2]

        if not blenabled:                                  #Skip disabled blocklist
            continue

        print('Processing %s:' % blname)

        #Is this a downloadable file or locally stored?
        if blurl.startswith('http') or blurl.startswith('ftp'):
            blfilename = folders.temp + blname + '.txt'    #Download to temp folder
            if check_file_age(blfilename):                 #Does file need freshening?
                download_file(blurl, blname, blfilename)
        else:                                              #Local file
            blfilename = blurl;                            #URL is actually the filename

        if bltype == TYPE_SPECIAL:
            if blname == 'bl_tld':
                process_tldlist()
                continue

        filelines = read_file(blfilename)                  #Read temp file

        if not filelines:                                  #Anything read from file?
            print('\tData missing unable to process %s' % blname)
            print()
            continue

        if bltype == TYPE_PLAIN:
            process_plainlist(filelines, blname)
        elif bltype == TYPE_EASYLIST:
            process_easylist(filelines, blname)
        elif bltype == TYPE_UNIXLIST:
            process_unixlist(filelines, blname)

        print('\tFinished processing %s' % blname)
        print()


""" Action Custom Lists
    Go through config and process each enabled list
    1. Skip disabled lists
    2. Check if list is downloaded or locally stored
    3. For download lists:
    3a. Check file age
    3b. Download new copy if out of date
    4. Read file into filelines list
    5. Process list based on type
Args:
    None
Returns:
    None
"""
def action_customlists():
    blname = ''
    blurl = ''                                             #Block list URL
    blfilename = ''                                        #Block list file name
    i = 0                                                  #Loop position (for naming)

    customurllist = []

    print('Processing Custom Blocklists:')

    if config['bl_custom'] == '':
        print('\tNo custom blocklists set')
        print()
        return

    customurllist = config['bl_custom'].split(',')         #Explode comma seperated vals

    for blurl in customurllist:
        i += 1
        blname = 'bl_custom%d' % i                         #Make up a name
        print('Processing %s - %s' % (blname, blurl))

        #Is this a downloadable file or locally stored?
        if blurl.startswith('http') or blurl.startswith('ftp'):
            #Download to temp folder with loop position in file name
            blfilename = '%s%s.txt' % (folders.temp, blname)
            if check_file_age(blfilename):                 #Does file need freshening?
                download_file(blurl, blname, blfilename)
        else:                                              #Local file
            blfilename = blurl;

        filelines = read_file(blfilename)                  #Read temp file

        if not filelines:                                  #Anything read from file?
            print('\tData missing unable to process %s' % blname)
            print()
            continue

        process_customlist(filelines, blname)

        print('\tFinished processing %s' % blname)
        print()


""" Deduplication
    Final sort and then save list to file
    1. Sort the blocklist by the reversed domain (blocklist[x][0])
    2. Check if each item matches the beginning of the previous item
       (i.e. a subdomain of a blocked domain)
    3. Remove matched items from the list
    4. Add unique items into sqldata and blacklist
    5. Save blacklist to file
    6. Insert SQL Data
Args:
    None
Returns:
    None
"""
def dedup_lists():
    global blocklist, dedupcount

    dns_blacklist = []
    sqldata = []
    prev = '\0'                                            #Previous has to be something

    dedupcount = 0

    print()
    print('Sorting and Deduplicating blocklist')

    blocklist.sort(key=lambda x: x[0])                     #Sort list on col0 "reversed"

    for item in blocklist:
        if item[0].startswith(prev):
            #print('Removing:', item)
            #blocklist.remove(item)
            dedupcount += 1
        else:
            #blocklist.append(tuple([reverse, subdomain, comment, source]))
            dns_blacklist.append(add_blacklist(item[1]))
            sqldata.append(tuple([item[3], item[1], True, item[2]]))
            prev = item[0]

    print('Further deduplicated %d domains' % dedupcount)
    print('Final number of domains in blocklist: %d' % len(dns_blacklist))

    save_file(dns_blacklist, folders.dnslists + 'notrack.list')
    insert_data(sqldata)


#Main----------------------------------------------------------------

#Add any OS specific folder locations
blocklistconf['bl_usersblacklist'] = tuple([True, folders.blacklist, TYPE_PLAIN])

load_config()

print('Opening connection to MariaDB')
db = mariadb.connect(user=dbconf.user, password=dbconf.password, database=dbconf.database)

create_sqltables()                                         #Create SQL Tables
clear_table()                                              #Clear SQL Tables
process_whitelist()                                        #Need whitelist first
action_lists()                                             #Action default lists
action_customlists()                                       #Action users custom lists

print('Finished processing all block lists')
print('Total number of domains added: %d' % len(blocklist))
print('Total number of domains deduplicated: %d' % totaldedupcount)
dedup_lists()                                              #Dedup then insert domains

print('Closing connection to MariaDB')
db.close()



#######################################
# User Configerable Settings
#######################################
"""
#Set NetDev to the name of network device e.g. "eth0" IF you have multiple network cards
NetDev=$(ip -o link show | awk '{print $2,$9}' | grep ": UP" | cut -d ":" -f 1)

#If NetDev fails to recognise a Local Area Network IP Address, then you can use IPVersion to assign a custom IP Address in /etc/notrack/notrack.conf
#e.g. IPVersion = 192.168.1.2
IPVersion="IPv4"

declare -A Config                                #Config array for Block Lists

#######################################
# Constants
#######################################
readonly VERSION="0.9.4"

readonly CHECKTIME=257400                        #Time in Seconds between downloading lists (3 days - 30mins)
readonly USER="ntrk"
readonly PASSWORD="ntrkpass"
readonly DBNAME="ntrkdb"

readonly REGEX_IPV4="^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}"
readonly REGEX_DEFANGED="^(f[xX]p|ftp|h[xX][xX]ps?|https?):\/\/([\.A-Za-z0-9_-]+)\/?.*$"
readonly REGEX_EASY="^\|\|([A-Za-z0-9._-]+)(\^|\/|$)(\$third-party|\$popup|\$popup\,third\-party)?$"
readonly REGEX_PLAINLINE="^([A-Za-z0-9_-]+\.[A-Za-z0-9._-]+)[[:space:]]?#?([^#]*)$"
readonly REGEX_UNIX="^(127\.0\.0\.1|0\.0\.0\.0)[[:space:]]+([A-Za-z0-9_-]+\.[A-Za-z0-9._-]+)[[:space:]]*#?(.*)\n?$"

declare -A urls                                  #Block lists locations



#######################################
# Global Variables
#######################################
FORCE=0                                          #Force update block list
EXECTIME=$(date +%s)                             #Time at Execution
filetime=0                                       #Return value from get_filetime
oldversion="$VERSION"
declare -i jumppoint=0                           #Percentage increment
declare -i percentpoint=0                        #Number of lines to loop through before a percentage increment is hit
declare -i dedup=0                               #Count of Deduplication

declare -A domainlist                            #Associative to store domains being blocked
declare -a sqllist                              #Array to store each list for entering into MariaDB
declare -A tldlist                               #Associative to check if TLD blocked
declare -A whitelist                             #associative array for referencing domains in White List


#######################################
# Error Exit
#
# Globals:
#   None
# Arguments:
#  $1. Error Message
#  $2. Exit Code
# Returns:
#   None
#
#######################################
function error_exit() {
  echo "Error: $1"
  echo "Aborting"
  exit "$2"
}


#######################################
# Restart service
#    with either systemd or sysvinit or runit
#
# Globals:
#   None
# Arguments:
#   None
# Returns:
#   None
#
#######################################
service_restart() {
  if [[ -n $1 ]]; then
    echo "Restarting $1"
    if [ "$(command -v systemctl)" ]; then       #systemd
      sudo systemctl restart "$1"
    elif [ "$(command -v service)" ]; then       #sysvinit
      sudo service "$1" restart
    elif [ "$(command -v sv)" ]; then            #runit
      sudo sv restart "$1"
    else
      error_exit "Unable to restart services. Unknown service supervisor" "21"
    fi
  fi
}





#######################################
# Delete Old File
#   Checks if a file exists and then deletes it
#
# Globals:
#   None
# Arguments:
#   $1. File to delete
# Returns:
#   None
#
#######################################
function delete_file() {
  if [ -e "$1" ]; then                                     #Does file exist?
    echo "Deleting file: $1"
    rm "$1"                                                #If yes then delete it
  fi
}



#######################################
# Check If Running as Root and if Script is already running
#
# Globals:
#   None
# Arguments:
#   None
# Returns:
#   None
#
#######################################
function check_root() {
  local pid=""
  pid=$(pgrep notrack | head -n 1)                         #Get PID of first notrack process

  if [[ "$(id -u)" != "0" ]]; then
    error_exit "This script must be run as root" "5"
  fi

  #Check if another copy of notrack is running
  if [[ $pid != "$$" ]] && [[ -n $pid ]] ; then  #$$ = This PID
    error_exit "NoTrack already running under pid $pid" "8"
  fi
}


#######################################
# Delete Blocklist table
#   1. Delete all rows in Table
#   2. Reset Counter
#
# Globals:
#   USER, PASSWORD, DBNAME
# Arguments:
#   None
# Returns:
#   None
#
#######################################
function delete_table() {
  echo "Clearing Blocklist Table"

  echo "DELETE FROM blocklist;" | mysql --user="$USER" --password="$PASSWORD" -D "$DBNAME"
  echo "ALTER TABLE blocklist AUTO_INCREMENT = 1;" | mysql --user="$USER" --password="$PASSWORD" -D "$DBNAME"
}


#######################################
# Download File
#   1. Download file with wget
#   2. Check return value of wget
#   3. Check if file exists
#
# Globals:
#   None
# Arguments:
#   $1. Output File
#   $2. URL
# Returns:
#   0. success
#   >=1. fail
#
#######################################
function download_file() {
  local exitstatus=0

  echo "Downloading $2"
  wget -qO "$1" "$2"                                       #Download with wget

  exitstatus="$?"

  if [ $exitstatus -eq 0 ]; then
    if [ -s "$1" ]; then                                   #Check if file has been downloaded
      return 0                                             #Success
    else
      echo "Error: download_file - File not downloaded"
      return 1
    fi
  fi

  case $exitstatus in                                      #Review exit code of wget
    "1") echo "Error: download_file - Generic error" ;;
    "2") echo "Error: download_file - Parsing error" ;;
    "3") echo "Error: download_file - File I/O error" ;;
    "4") echo error_exit "download_file - Network error" "30" ;;
    "5") echo "Error: download_file - SSL verification failure" ;;
    "6") echo "Error: download_file - Authentication failure" ;;
    "7") echo "Error: download_file - Protocol error" ;;
    "8") echo "Error: download_file - File not available on server" ;;
  esac

  return "$exitstatus"
}


#######################################
# Generate Example Black List File
#
# Globals:
#   $FILE_BLACKLIST
# Arguments:
#   None
# Returns:
#   None
#
#######################################
function generate_blacklist() {
  local -a tmp                                   #Local array to build contents of file

  echo "Creating blacklist"
  touch "$FILE_BLACKLIST"
  tmp+=("#Use this file to create your own custom block list")
  tmp+=("#Run notrack script (sudo notrack) after you make any changes to this file")
  tmp+=("#doubleclick.net")
  tmp+=("#googletagmanager.com")
  tmp+=("#googletagservices.com")
  tmp+=("#polling.bbc.co.uk #BBC Breaking News Popup")
  printf "%s\n" "${tmp[@]}" > $FILE_BLACKLIST    #Write Array to file with line seperator
}


#######################################
# Generate Example White List File
#
# Globals:
#   $FILE_WHITELIST
# Arguments:
#   None
# Returns:
#   None
#
#######################################
function generate_whitelist() {
  local -a tmp                                   #Local array to build contents of file

  echo "Creating whitelist"
  touch "$FILE_WHITELIST"
  tmp+=("#Use this file to remove sites from block list")
  tmp+=("#Run notrack script (sudo notrack) after you make any changes to this file")
  tmp+=("#doubleclick.net")
  tmp+=("#google-analytics.com")
  printf "%s\n" "${tmp[@]}" > $FILE_WHITELIST    #Write Array to file with line seperator
}


#######################################
# Get IP Address
#   Reads IP address of System or uses custom IP assigned by IPVersion
#   Note: A manual IP address can be assigned using IPVersion, e.g. if using with a VPN
#
# Globals:
#   IPAddr, IPVersion
# Arguments:
#   None
# Returns:
#   None
#
#######################################
function get_ip() {
  if [ "$IPVersion" == "IPv4" ]; then
    echo "Internet Protocol Version 4 (IPv4)"
    echo "Reading IPv4 Address from $NetDev"
    IPAddr=$(ip addr list "$NetDev" | grep inet | head -n 1 | cut -d ' ' -f6 | cut -d/ -f1)

  elif [ "$IPVersion" == "IPv6" ]; then
    echo "Internet Protocol Version 6 (IPv6)"
    echo "Reading IPv6 Address"
    IPAddr=$(ip addr list "$NetDev" | grep inet6 | head -n 1 | cut -d ' ' -f6 | cut -d/ -f1)
  else
    echo "Custom IP Address used"
    IPAddr="$IPVersion";                         #Use IPVersion to assign a manual IP Address
  fi

  echo "System IP Address: $IPAddr"
  echo
}



#######################################
# Get Blacklist
#   Get Users Custom Blacklist
#
# Globals:
#   FILE_BLACKLIST, sqllist
# Arguments:
#   None
# Returns:
#   None
#
#######################################
function get_blacklist() {
  echo "Processing Custom Black List"
  process_list "$FILE_BLACKLIST" TYPE_PLAIN

  if [ ${#sqllist[@]} -gt 0 ]; then                         #Get size of sqllist
    insert_data "custom"
  fi
  echo "Finished processing Custom Black List"
  echo
}


#######################################
# Get Custom Blocklists
#   Get the users custom blocklists from either download or local file
#
# Globals:
#   Config
# Arguments:
#   None
# Returns:
#   None
#
#######################################
function get_custom_blocklists() {
  local dlfile=""
  local dlfile_time=0                                      #Downloaded File Time
  local -i c=1                                             #For displaying count of custom list
  local filename=""
  local customurl=""
  local -a customurllist

   #Are there any custom block lists set? 
  if [[ ${Config[bl_custom]} == "0" ]] || [[ ${Config[bl_custom]} == "" ]]; then
    echo "No Custom Block Lists in use"
    echo
    for filename in /tmp/custom_*; do                      #Clean up old custom lists
      delete_file "/tmp/$filename"
    done
    return 0
  fi

  echo "Processing Custom Block Lists"
    
  #Read comma seperated values from bl_custom string into an array
  IFS=',' read -ra customurllist <<< "${Config[bl_custom]}"
  unset IFS
  
  for customurl in "${customurllist[@]}"; do               #Review each url from array
    echo "$c: $customurl"
    filename=${customurl##*/}                              #Get filename from URL
    filename=${filename%.*}                                #Remove file extension
    dlfile="/tmp/custom_$filename.txt']
   
    get_filetime "$dlfile"                                 #When was file last downloaded?
    dlfile_time="$filetime"

    #Determine whether we are dealing with a download or local file
    if [[ $customurl =~ ^(https?|ftp):// ]]; then           #Is this a URL - http(s) / ftp?
      if [ $dlfile_time -lt $((EXECTIME-CHECKTIME)) ]; then #Is list older than 4 days
        if ! download_file "$dlfile" "$customurl"; then     #Yes - Download it
          echo "Warning: get_custom_blocklists - unable to proceed without $customurl"
          continue
        fi
      else
        echo "File in date, not downloading"
      fi
    elif [ -e "$customurl" ]; then                         #Is it a file on the server?
      echo "$customurl found on system"
      dlfile="$customurl"      
    else                                                   #Don't know what to do
      echo "Warning: get_custom_blocklists - unable to find $customurl"
      echo
      continue                                             #Skip to next item
    fi

    process_custom_blocklist "$dlfile" "$filename"
    ((c++))                                                #Increase count of custom lists
  done

  
}


#######################################
# Process Custom Blocklist
#   1. Calculate percentpoint
#   2. Attempt to match against a known pattern line
#
# Globals:
#   sqllist, jumppoint, percentpoint
# Arguments:
#   $1. List file
#   $2. filename for temp csv
# Returns:
#   None
#
#######################################
function process_custom_blocklist() {
  local listfile="$1"
  local csvfile="$2"
  local i=0
  local j=0
  local line=""

  calc_percentpoint "$1"
  i=1                                                      #Progress counter
  j=$jumppoint                                             #Jump in percent

  while IFS=$'\n\r' read -r line
  do
    if [[ ! $line =~ ^# ]] && [[ -n $line ]]; then  
      if ! match_plainline "$line"; then
        if ! match_easyline "$line"; then
          if ! match_unixline "$line"; then
            match_defangedline "$line"
          fi          
        fi        
      fi
    fi

    if [ $i -ge $percentpoint ]; then                      #Display progress
      echo -ne " $j%  \r"                                  #Echo without return
      j=$((j + jumppoint))
      i=0
    fi
    ((i++))
  done < "$listfile"
  echo " 100%"

  unset IFS
  
  if [ ${#sqllist[@]} -gt 0 ]; then                       #Any domains in the block list?
    insert_data "custom_$csvfile"
    echo "Finished processing $csvfile"
  else                                                     #No domains in block list
    echo "No domains extracted from block list"
  fi
  
  echo
}


#######################################
# Insert Data into SQL Table
#   1. Save sqllist array to .csv file
#   2. Bulk write csv file into MariaDB
#   3. Delete .csv file
#
# Globals:
#   sqllist
# Arguments:
#   $1. Blocklist
# Returns:
#   None
#
#######################################
function insert_data() {
  printf "%s\n" "${sqllist[@]}" > "/tmp/$1.csv"           #Output arrays to file

  mysql --user="$USER" --password="$PASSWORD" -D "$DBNAME" -e "LOAD DATA INFILE '/tmp/$1.csv' INTO TABLE blocklist FIELDS TERMINATED BY ',' ENCLOSED BY '\"' LINES TERMINATED BY '\n' (@var1, @var2, @var3) SET id=NULL, bl_source = '$1', site = @var1, site_status=@var2, comment=@var3;"
  delete_file "/tmp/$1.csv"

  sqllist=()                                              #Zero SQL Array
}


#######################################
# Check If mysql or MariaDB is installed
#   exits if not installed
# Globals:
#   None
# Arguments:
#   None
# Returns:
#   None
#
#######################################
function is_sql_installed() {
  if [ -z "$(command -v mysql)" ]; then
    echo "NoTrack requires MySql or MariaDB to be installed"
    echo "Run install.sh -sql"
    exit 60
  fi
}


#######################################
# Check if an update is required
#   Triggers for Update being required:
#   1. -f or --forced
#   2 Block list older than 3 days
#   3 White list recently modified
#   4 Black list recently modified
#   5 Config recently modified
#   6 Domain White list recently modified
#   7 Domain Black list recently modified
#   8 Domain CSV recently modified
# Globals:
#   FORCE
#   FILE_BLACKLIST, FILE_WHITELIST, FILE_CONFIG, FILE_TLDBLACK, FILE_TLDWHITE
#   TLD_CSV
# Arguments:
#   None
# Returns:
#   None
#
#######################################
function is_update_required() {
  local ftime=0

  if [ $FORCE == 1 ]; then                                 #Force overrides
    echo "Forced Update"
    return 0
  fi

  get_filetime "$MAIN_BLOCKLIST"
  ftime="$filetime"
  if [ $ftime -lt $((EXECTIME-CHECKTIME)) ]; then
    echo "Block List out of date"
    return 0
  fi

  get_filetime "$FILE_WHITELIST"
  if [ $filetime -gt $ftime ]; then
    echo "White List recently modified"
    return 0
  fi

  get_filetime "$FILE_BLACKLIST"
  if [ $filetime -gt $ftime ]; then
    echo "Black List recently modified"
    return 0
  fi

  get_filetime "$FILE_CONFIG"
  if [ $filetime -gt $ftime ]; then
    echo "Config recently modified"
    return 0
  fi

  get_filetime "$FILE_TLDWHITE"
  if [ $filetime -gt $ftime ]; then
    echo "Domain White List recently modified"
    return 0
  fi

  get_filetime "$FILE_TLDBLACK"
  if [ $filetime -gt $ftime ]; then
    echo "Domain White List recently modified"
    return 0
  fi

  get_filetime "$TLD_CSV"
  if [ $filetime -gt $ftime ]; then
    echo "TLD Master List recently modified"
    return 0
  fi

  echo "No update required"
  exit 0
}



#######################################
# Get List
#   Downloads a blocklist and prepares it for processing
#
# Globals:
#   Config, filetime, sqllist
# Arguments:
#   $1. List Name to be Processed
#   $2. Process Method
#   $3. List file to use within zip file
# Returns:
#   0 on success
#   1 on error
#
#######################################
function get_list() {
  local list="$1"
  local dlfile="/tmp/$1.txt']
  local zipfile=false

  #Should we process this list according to the Config settings?
  if [ "${Config[bl_$list]}" == 0 ]; then
    delete_file "$dlfile"  #If not delete the old file, then leave the function
    return 0
  fi

  if [[ ${urls[$list]} =~ \.zip$ ]]; then                  #Is the download a zip file?
    dlfile="/tmp/$1.zip"
    zipfile=true
  fi

  get_filetime "$dlfile"                                   #Is the download in date?

  if [ $filetime -gt $((EXECTIME-CHECKTIME)) ]; then
    echo "$list in date. Not downloading"
  else
    if ! download_file "$dlfile" "${urls[$list]}"; then    #Download out of date list
      echo "Error: get_list - unable to proceed without ${urls[$list]}"
      return 1
    fi
  fi

  if [[ $zipfile == true ]]; then                          #Do we need to unzip?
    unzip -o "$dlfile" -d "/tmp/"                          #Unzip not quietly (-q)
    dlfile="/tmp/$3"                                       #dlfile is now the expected unziped file
    if [ ! -e "$dlfile" ]; then                            #Check if expected file is there
      echo "Warning: Can't find file $dlfile"
      return 1
    fi
  fi

  echo "Processing list $list"                             #Inform user

  case $2 in                                               #What type of processing is required?
    "csv") process_csv "$dlfile" ;;
    "notrack") process_notracklist "$dlfile" ;;
    "tldlist") process_tldlist ;;
    *) process_list "$dlfile" "$2"
  esac

  if [ ${#sqllist[@]} -gt 0 ]; then                       #Are there any domains in the block list?
    insert_data "bl_$list"                                 #Add data to SQL table
    echo "Finished processing $list"
  else                                                     #No domains in block list
    echo "No domains extracted from block list"
  fi

  echo
}


#######################################
# Process List
#   Generic processing of block lists based on supplied Process Method
#
# Arguments:
#   $1. List file
#   $2. Process Method
# Returns:
#   None
#
#######################################
function process_list() {
  local listfile="$1"
  local process_type="$2"
  local i=0
  local j=0
  local line=""

  calc_percentpoint "$1"
  i=1                                                      #Progress counter
  j=$jumppoint                                             #Jump in percent

  while IFS=$'\n\r' read -r line
  do
    if [[ ! $line =~ ^# ]] && [[ -n $line ]]; then
      eval "${process_type}" '"$line"'                     #Call appropriate match function
    fi

    if [ $i -ge $percentpoint ]; then                      #Display progress
      echo -ne " $j%  \r"                                  #Echo without return
      j=$((j + jumppoint))
      i=0
    fi
    ((i++))
  done < "$listfile"
  echo " 100%"

  unset IFS
}


#######################################
# Match Defanged Line
#
# Globals:
#   REGEX_DEFANGED
# Arguments:
#   $1. Line to match
# Returns:
#   0. Match
#   1. No Match
#
#######################################
function match_defangedline() {
  local tmpstr="${1//[\[\]]/}"                             #Remove square brackets
  
  if [[ $tmpstr =~ $REGEX_DEFANGED ]]; then
    add_domain "${BASH_REMATCH[2]}" ""
  else
    return 1
  fi
  return 0
}


#######################################
# Match Easy Line
#   EasyLists contain a mixture of Element hiding rules and third party sites to block.
#   Disregard anything with a full URL supplied
#
# Globals:
#   REGEX_EASY
# Arguments:
#   $1. Line to Match
# Returns:
#   0. Match
#   1. No Match
# Regex:
#   Group 1: Domain
#   Group 2: ^ | / | $  once
#   Group 3: $third-party | $popup | $popup,third-party
#
#######################################
function match_easyline() {
  if [[ $1 =~ $REGEX_EASY ]]; then
    add_domain "${BASH_REMATCH[1]}" ""
  else
    return 1
  fi
  return 0
}


#######################################
# Match Plain Line
#
# Globals:
#   REGEX_PLAINLINE
# Arguments:
#   $1. Line to process
# Returns:
#   0. Match
#   1. No Match
#
#######################################
function match_plainline() {
  if [[ $1 =~ $REGEX_PLAINLINE ]]; then
    add_domain "${BASH_REMATCH[1]}" "${BASH_REMATCH[2]}"
  else
    return 1
  fi
  return 0
}


#######################################
# Match Unix Line
#
# Globals:
#   REGEX_UNIX
# Arguments:
#   $1. Line to process
# Returns:
#   0. Match
#   1. No Match
# Regex:
#   Group 1: 127.0.0.1 | 0.0.0.0
#   Space  one or more (include tab)
#   Group 2: Domain
#   Group 3: Comment - any character zero or more times
#
#######################################
function match_unixline() {
  if [[ $1 =~ $REGEX_UNIX ]]; then
    add_domain "${BASH_REMATCH[2]}" "${BASH_REMATCH[3]}"
  else
    return 1
  fi

  return 0
}

#######################################
# Process CSV
#   Process CSV List Tab seperated with Col1 = site, Col2 = comments
#
# Globals:
#   jumppoint
#   percentpoint
# Arguments:
#   $1. List file to process
# Returns:
#   None
# Regex:
#   Group 1: Subdomain or Domain
#   Group 2: Domain or TLD
#
#######################################
function process_csv() {
  local csvsite=""
  local csvcomment=""
  local i=0
  local j=0

  calc_percentpoint "$1"
  i=1                                                      #Progress counter
  j=$jumppoint                                             #Jump in percent

  while IFS=$'\t\n' read -r csvsite csvcomment _
  do
    if [[ $csvsite =~ ^([A-Za-z0-9\-]+)\.([A-Za-z0-9\.\-]+)$ ]]; then
      add_domain "$csvsite" "$csvcomment"
    fi

    if [ $i -ge $percentpoint ]; then                      #Display progress
      echo -ne " $j%  \r"                                  #Echo without return
      j=$((j + jumppoint))
      i=0
    fi
    ((i++))
  done < "$1"
  echo " 100%"

  unset IFS
}


#######################################
# Process NoTrack List
#   NoTrack list is just like PlainList, but contains latest version number
#    which is used by the Admin page to inform the user an upgrade is available
# Globals:
#   jumppoint
#   percentpoint
#   Version
# Arguments:
#   $1 List file to process
# Returns:
#   None
# Regex:
#   Group 1: Subdomain or Domain
#   .
#   Group 2: Domain or TLD
#   space  optional
#   #  optional
#   Group 3: Comment  any character zero or more times
#
#######################################
function process_notracklist() {
  local i=0
  local j=0
  local latestversion=""

  calc_percentpoint "$1"
  i=1                                                      #Progress counter
  j=$jumppoint                                             #Jump in percent

  while IFS=$'\n' read -r Line
  do
    if [[ $Line =~ $REGEX_PLAINLINE ]]; then
      add_domain "${BASH_REMATCH[1]}" "${BASH_REMATCH[2]}"
    elif [[ $Line =~ ^#LatestVersion[[:space:]]([0-9\.]+)$ ]]; then #Is it version number
      latestversion="${BASH_REMATCH[1]}"         #Extract Version number
      if [[ $oldversion != "$latestversion" ]]; then
        echo "New version of NoTrack available v$latestversion"
        #Check if config line LatestVersion exists
        #If not add it in with tee
        #If it does then use sed to update it
        if [[ $(grep "LatestVersion" "$FILE_CONFIG") == "" ]]; then
          echo "LatestVersion = $latestversion" | sudo tee -a "$FILE_CONFIG"
        else
          sed -i "s/^\(LatestVersion *= *\).*/\1$latestversion/" $FILE_CONFIG
        fi
      fi
    fi

    if [ $i -ge $percentpoint ]; then                      #Display progress
      echo -ne " $j%  \r"                                  #Echo without return
      j=$((j + jumppoint))
      i=0
    fi
    ((i++))
  done < "$1"
  echo " 100%"

  unset IFS
}





#######################################
# Add Domain to List
#   Checks whether a Domain is in the Users whitelist or has previously been added
#
# Globals:
#   tldlist
#   whitelist
#   Dedup
# Arguments:
#   $1 domain to Add
#   $2 Comment
# Returns:
#   0. Success
#   1. Failed to add
#
#######################################
function add_domain() {
  local domain="$1"

  if [[ $domain =~ $REGEX_IPV4 ]]; then                    #Leave if IPv4 Address
    return 1
  fi

  if [[ $domain =~ ^www\. ]]; then                         #Drop www. from domain
    domain="${domain:4}"
  fi

  #Ignore Sub domain
  #Group 1 Domain: A-Z,a-z,0-9,-  one or more
  # .
  #Group 2 (Double-barrelled TLD's) : org. | co. | com.  optional
  #Group 3 TLD: A-Z,a-z,0-9,-  one or more

  if [[ $domain =~ ([A-Za-z0-9_\-]+)\.(org\.|co\.|com\.|gov\.)?([A-Za-z0-9\-]+)$ ]]; then
    if [ -n "${tldlist[.${BASH_REMATCH[3]}]}" ]; then      #Drop if .domain is in TLD
      #echo "Dedup TLD $domain"                            #Uncomment for debugging
      ((dedup++))
      return 1
    fi

    #Drop if sub.site.domain has been added
    if [ -n "${domainlist[${BASH_REMATCH[1]}.${BASH_REMATCH[2]}${BASH_REMATCH[3]}]}" ]; then
      #echo "Dedup Domain $domain"                         #Uncomment for debugging
      ((dedup++))
      return 1
    fi

    #Drop if sub.site.domain has been added
    if [ -n "${domainlist[$domain]}" ]; then
      #echo "Dedup Duplicate Sub $domain"                  #Uncomment for debugging
      ((dedup++))
      return 1
    fi

    #Is sub.site.domain or site.domain in whitelist?
    if [ -n "${whitelist[$domain]}" ] || [ -n "${whitelist[${BASH_REMATCH[1]}.${BASH_REMATCH[2]}${BASH_REMATCH[3]}]}" ]; then
      sqllist+=("\"$domain\",\"0\",\"$2\"")               #Add to SQL as Disabled
    else                                                   #Not found it whitelist
      sqllist+=("\"$domain\",\"1\",\"$2\"")               #Add to SQL as Active
      domainlist[$domain]=true                             #Add domain into domainlist array
    fi
  #else
    #echo "Invalid domain $domain"
  fi

  return 0
}


#######################################
# Sort List then save to file
#   1. Sort domainlist array into new array sortedlist
#   2. Go through sortedlist and check subdomains again
#   3. Copy sortedlist to domains, removing any blocked subdomains
#   4. Write list to dnsmasq folder
# Globals:
#   domainlist
# Arguments:
#   None
# Returns:
#   None
#
#######################################
function sortlist() {
  local listsize=0
  local i=0
  local j=0
  local -a sortedlist                                      #Sorted array of domainlist
  local -a domains                                         #Dnsmasq list
  local domain=""
  local tmpstr=""
  dedup=0                                                  #Reset Deduplication

  listsize=${#domainlist[@]}                               #Get number of items in Array
  if [ "$listsize" == 0 ]; then                            #Fatal error
    error_exit "No items in Block List" "8"
  fi
  if [ "$listsize" -ge 100 ]; then                         #Calculate Percentage Point
    percentpoint=$((listsize/100))
    jumppoint=1
  else
    percentpoint=1
    jumppoint=$((100/listsize))
  fi

  echo "Sorting List"
  IFS=$'\n' sortedlist=($(sort <<< "${!domainlist[*]}"))
  unset IFS

  echo "Final Deduplication"
  domains+=("#Tracker Block list last updated $(date)")
  domains+=("#Don't make any changes to this file, use $FILE_BLACKLIST and $FILE_WHITELIST instead")

  for domain in "${sortedlist[@]}"; do
    # ^ Subdomain
    #Group 1: Domain
    #Group 2: org. | co. | com.  optional
    #Group 3: TLD
    #Is there a subdomain?
    if [[ $domain =~ ^[A-Za-z0-9_\-]+\.([A-Za-z0-9_\-]+)\.(org\.|co\.|com\.|gov\.)?([A-Za-z0-9\-]+)$ ]]; then
      #Is domain.domain already in list?
      if [ -n "${domainlist[${BASH_REMATCH[1]}.${BASH_REMATCH[2]}${BASH_REMATCH[3]}]}" ]; then        
        ((dedup++))                                        #Yes, add to total of dedup
      else
        domains+=("address=/$domain/$IPAddr")              #No, add to Array
      fi
    else                                                   #No subdomain, add to Array
      domains+=("address=/$domain/$IPAddr")
    fi

    if [ $i -ge $percentpoint ]; then                      #Display progress
      echo -ne " $j%  \r"                                  #Echo without return
      j=$((j + jumppoint))
      i=0
    fi
    ((i++))
  done

  echo " 100%"
  echo
  #printf "%s\n" "${sortedlist[@]}"                        #Uncomment to debug
  echo "Further Deduplicated $dedup Domains"
  echo "Number of Domains in Block List: ${#domains[@]}"
  echo "Writing block list to $MAIN_BLOCKLIST"
  printf "%s\n" "${domains[@]}" > "$MAIN_BLOCKLIST"

  echo
}

#######################################
# Show Help
#
# Globals:
#   None
# Arguments:
#   None
# Returns:
#   None
#######################################
function show_help() {
  echo "Usage: notrack"
  echo "Downloads and Installs updated tracker lists"
  echo
  echo "The following options can be specified:"
  echo -e "  -f, --force\tForce update of Block list"
  echo -e "  -h, --help\tDisplay this help and exit"
  echo -e "  -t, --test\tConfig Test"
  echo -e "  -v, --version\tDisplay version information and exit"
  echo -e "  -u, --upgrade\tRun a full upgrade"
}


#######################################
# Show Version
#
# Globals:
#   VERSION
# Arguments:
#   None
# Returns:
#   None
#######################################
function show_version() {
  echo "NoTrack Version $VERSION"
  echo
}


#######################################
# Test
#   Display Config and version number
# Globals:
#   Config
# Arguments:
#   None
# Returns:
#   None
#######################################
function test() {
  local DnsmasqVersion=""
  local key=""

  echo "NoTrack Config Test"
  echo
  echo "NoTrack version $VERSION"

  DnsmasqVersion=$(dnsmasq --version)
  [[ $DnsmasqVersion =~ ^Dnsmasq[[:space:]]version[[:space:]]([0-9]\.[0-9]{1,2}) ]]
  local VerNo="${BASH_REMATCH[1]}"               #Extract version number from string
  if [[ -z $VerNo ]]; then                       #Was anything extracted?
    echo "Dnsmasq version Unknown"
  else
    echo "Dnsmasq version $VerNo"
    check_dnsmasq_version
    if [ $? == 53 ]; then                        #Is white listing supported?
      echo "Dnsmasq Supports White listing"
    else                                         #No, version too low
      echo "Dnsmasq Doesn't support White listing (v2.75 or above is required)"
      if [ -n "$(command -v dig)" ]; then        #Is dig available?
        echo "Fallback option using Dig is available"
      else
        echo "Dig isn't installed. Unable to White list from blocked TLD's"
      fi
    fi
  fi
  echo

  load_config                                    #Load saved variables
  get_ip                                         #Read IP Address of NetDev

  echo "Block Lists Utilised:"
  for key in "${!Config[@]}"; do                 #Read keys from Config array
    if [[ "${Config[$key]}" == 1 ]]; then        #Is block list enabled?
      echo "$key"                                #Yes, display it
    fi
  done
  echo

  if [[ ${Config[bl_custom]} != "0" ]]; then      #Any custom block lists?
    echo "Additional Custom Block Lists Utilised:"
    echo "${Config[bl_custom]}"
  fi
}



#######################################
# Main
#######################################
if [ -n "$1" ]; then                                       #Have any arguments been given
  if ! options="$(getopt -o fhvtu -l help,force,version,upgrade,test,wait -- "$@")"; then
    # something went wrong, getopt will put out an error message for us
    exit 6
  fi

  set -- $options

  while [ $# -gt 0 ]
  do
    case $1 in
      -f|--force)
        FORCE=1
      ;;
      -h|--help)
        show_help
        exit 0
      ;;
      -t|--test)
        test
        exit 0
      ;;
      -v|--version)
        show_version
        exit 0
      ;;
      -u|--upgrade)
        upgrade
        exit 0
      ;;
      --wait)
        echo "Waiting"
        sleep 240
      ;;
      (--)
        shift
        break
      ;;
      (-*)
        error_exit "$0: error - unrecognized option $1" "6"
      ;;
      (*)
        break
      ;;
    esac
    shift
  done
fi


#At this point the functionality of notrack.sh is to update Block Lists
#1. Check if user is running as root
#2. Create folder /etc/notrack
#3. Load config file (or use default values)
#4. Get IP address of system, e.g. 192.168.1.2
#5. Generate whitelist if it doesn't exist
#6. Check if Update is required
#7. Load whitelist file into whitelist associative array
#8. Process Users Custom BlackList
#9. Process Other block lists according to Config
#10. Process Custom block lists
#11. Sort list and do final deduplication

check_root                                                 #Check if Script run as Root
is_sql_installed                                           #Check if MariaDB is installed
create_sqltables                                           #Create Tables if they don't exist

if [ ! -d "/etc/notrack" ]; then                           #Check /etc/notrack folder exists
  echo "Creating notrack config folder: /etc/notrack"
  if ! mkdir "/etc/notrack"; then 
    error_exit "Unable to create folder /etc/notrack" "2"
  fi
fi

load_config                                                #Load saved variables
get_ip                                                     #Read IP Address of NetDev

if [ ! -e $FILE_WHITELIST ]; then 
  generate_whitelist
fi

if [ ! -e "$FILE_BLACKLIST" ]; then 
  generate_blacklist
fi

create_file "$FILE_TLDWHITE"                               #Create Black & White TLD lists if they don't exist
create_file "$FILE_TLDBLACK"
create_file "$MAIN_BLOCKLIST"                              #The main notrack.list

is_update_required                                         #Check if NoTrack really needs to run
delete_table

load_whitelist                                             #Load Whitelist into array
process_tldlist                                            #Load and Process TLD List
process_whitelist                                          #Process White List

get_blacklist                                              #Process Users Blacklist
get_custom_blocklists                                      #Process Custom Block lists


echo "Deduplicated $dedup Domains"
sortlist                                                   #Sort, Dedup 2nd round, Save list

service_restart dnsmasq

echo "NoTrack complete"
echo
"""