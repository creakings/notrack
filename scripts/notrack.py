#!/usr/bin/env python3
#Title : NoTrack
#Description : This script will download latest block lists from various sources, then parse them into Dnsmasq.
#Author : QuidsUp
#Date : 2015-01-14
#Version : 0.9.5
#Usage : sudo bash notrack.sh

from time import time, sleep
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

dnsserver_whitelist = ''
dnsserver_blacklist = ''

#######################################
# Global Variables
#######################################
dedupcount = 0
domaincount = 0
totaldedupcount = 0


#######################################
# Regular Expressions
#######################################
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
#If an IP has been supplied then use that instead of trying to find the system IP
#Usecase is for when NoTrack is used on a VPN
class Host:
    name = ''
    ip = ''

    def __init__(self, conf_ip):
        import socket

        self.name = socket.gethostname()                   #Host Name is easy to get

        #Has a non-loopback config - ipaddress been supplied?
        if conf_ip == '127.0.0.1' or conf_ip == '::1' or conf_ip == '':
            #Make a network connection to unroutable 169.x IP in order to get system IP
            #Connect to an unroutable 169.254.0.0/16 address on port 1
            try:
                s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
                s.connect(("169.254.0.255", 1))
            except OSError as e:
                print('Host Init: Error - Unable to open network connection')
                sys.exit(1)
            else:
                self.ip = s.getsockname()[0]
            finally:
                s.close()
        else:
            self.ip = conf_ip


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
        print()


    """ Get DNS Template String
        Gets the necessary string for creating DNS Block list files based on DNS server
    Args:
        Host Name, Host IP
    Returns:
        list of blacklist and whitelist string templates
    """
    def get_dnstemplatestr(self, hostname, hostip):
        blacklist = ''
        whitelist = ''

        if self.__dnsserver == 'dnsmasq':
            blacklist = 'address=/%s/' + hostip + '\n'
            whitelist = 'server=/%s/#\n'

        return tuple([blacklist, whitelist])


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


""" String to Bool
    Convert string to boolean value
Args:
    String
Returns:
    True or False
"""
def str2bool(v):
    return v.lower() in ('1', 'true', 'yes')


""" Check Root
    Check script is being run as root
Args:
    None
Returns:
    None
"""
def check_root():
    if os.geteuid() != 0:
        print('Error - This script must be run as root')
        print('NoTrack must be run as root', file=sys.stderr)
        sys.exit(2)


""" Is Running
    1. Get current pid and script name
    2. Run pgrep -a python3 - look for instances of python3 running
    3. Look through results of above command
    4. Check for my script name not equalling my pid
Args:
    None
Returns:
    0 - No other instances running
    > 0 - First match of another instance
"""
def is_running():
    Regex_Pid = re.compile('^(\d+)\spython3?\s([\w\.]+)')
    mypid = os.getpid()                                    #Current PID
    myname = os.path.basename(__file__)                    #Current Script Name
    cmd = 'pgrep -a python3'

    try:
        res = subprocess.check_output(cmd,shell=True, universal_newlines=True)
    except subprocess.CalledProcessError as e:
        print('error', e.output, e.returncode)
    else:
        for line in res.splitlines():
            matches = Regex_Pid.search(line)
            if matches is not None:
                if matches.group(2) == myname and int(matches.group(1)) != mypid:
                    print('%s is already running on pid %s' % (myname, matches.group(1)))
                    sys.exit(8)
                    #return int(matches.group(1))


    return 0



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

    print('Loading Config')
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

    print()


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


""" Clear Table
    Clear blocklist table and reset serial increment
Args:
    None
Returns:
    None
"""
def clear_table():
    cursor = db.cursor()

    cursor.execute('DELETE FROM blocklist')
    cursor.execute('ALTER TABLE blocklist AUTO_INCREMENT = 1')
    cursor.close()


""" Insert data into SQL table blocklist
    Bulk insert a list into MariaDB
Args:
    List of data
Returns:
    None
"""
def insert_data(sqldata):
    cmd = ''
    cursor = db.cursor()

    cmd = 'INSERT INTO blocklist (id, bl_source, site, site_status, comment) VALUES (NULL, %s, %s, %s, %s)'

    cursor.executemany(cmd, sqldata)
    db.commit()
    cursor.close()


""" Delete File
    1. Check file exists
    2. Delete file
Args:
    File to delete
Returns:
    True on success
    False on failure or not needed
"""
def delete_file(filename):
    if os.path.isfile(filename):
        os.remove(filename)
        return True

    return False




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

    #print('\tReading data from %s' % filename)
    f = open(filename, 'r')
    filelines = f.readlines()
    f.close()

    return filelines


""" Save Blob
    Save a binary blob to a file
Args:
    None
Returns:
    None
"""
def save_blob(data, filename):
    try:
        f = open(filename, 'wb')                           #Open file for binary writing
    except IOError as e:
        print('Error writing to %s' % filename)
        print(e)
        return False
    except OSError as e:
        print('Error writing to %s' % filename)
        print(e)
        return False
    else:
        f.write(data)
        f.close()




""" Save File
    Save a list into a file
Args:
    None
Returns:
    True on success
    False on error
"""
def save_list(domains, filename):
    try:
        f = open(filename, 'w')                            #Open file for ascii writing
    except IOError as e:
        print('Error writing to %s' % filename)
        print(e)
        return False
    except OSError as e:
        print('Error writing to %s' % filename)
        print(e)
        return False
    else:
        f.writelines(domains)
        f.close()
    return True


""" Extract Zip
    Unzip a file to destination
Args:
    Zip file, Output destination
Returns:
    None
"""
def extract_zip(inputfile, destination):
    from zipfile import ZipFile

    with ZipFile(inputfile) as zipobj:
        for compressedfile in zipobj.namelist():
            if compressedfile.endswith('.txt'):
                zipobj.extract(compressedfile, folders.temp)
                print('\tExtracting %s' % compressedfile)
                move_file(folders.temp + compressedfile, destination)


""" Add Domain
    Process supplied domain and add it to blocklist:
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
    if matches == None:                                    #Shouldn't happen
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
    #An Extra dot is required to match other subdomains and avoid similar spellings
    reverse = subdomain[::-1] + '.'

    blocklist.append(tuple([reverse, subdomain, comment, source]))
    domaincount += 1

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


""" Process Custom List
    We don't know what type of list this is, so try regex match against different types
    1. Reset Dedup and Domain counters
    2. Read list of lines
    3. Try different regex matches

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
        if match_plainline(line, listname):                #Try against Plain line
            continue
        if match_easyline(line, listname):                 #Try agaisnt Easy List
            continue
        if match_unixline(line, listname):                 #Try against Unix List
            continue
        match_defanged(line, listname)                     #Finally try against Defanged

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


""" Process Top Level Domain List
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
        save_list(dns_whitelist, folders.dnslists + 'whitelist.txt')
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


""" Check File Age
    1. Has FORCE been set?
    2. Does file exist?
    3. Check last modified time is within MAX_AGE (2 days)
Args:
    File
Returns:
    True - Update list
    False - List within MAX_AGE
"""
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


""" Download File
    1. Make 3 attempts at downloading a file
    2. Save File
    3. Request file is unzipped (if necessary)
Args:
    URL, List Name, File Destination
Returns:
    True - Success
    False - Failed download
"""
def download_file(url, listname, destination):
    extension = ''
    outputfile = ''

    print('\tDownloading %s' % url)

    for i in range(1, 4):
        try:
            response = urlopen(url)
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
            print('\t%s' % url)
        except URLError as e:
            if hasattr(e, 'reason'):
                print('\tError downloading %s' % url)
                print('\tReason: %s' % e.reason)
                return False
            elif hasattr(e, 'code'):
                print('\t%s' % url)
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
                print('\t%s' % url)
                print('\tHTTP Response %d' % res_code)

        sleep(i * 2)                                       #Throttle repeat attemps

    #Prepare for writing downloaded file to temp folder
    if url.endswith('zip'):                                #Check file extension
        extension = 'zip'
        outputfile = '%s%s.zip' % (folders.temp, listname)
    else:                                                  #Other - Assume txt for output
        extension = 'txt'
        outputfile = destination

    save_blob(response.read(), outputfile)                 #Write file to temp folder

    if extension == 'zip':                                 #Extract zip file?
        extract_zip(outputfile, destination)

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
    prev = '\0'                                            #Previous has to be something (e.g. a null byte)

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

    save_list(dns_blacklist, folders.dnslists + 'notrack.list')
    insert_data(sqldata)


""" Generate Example Black List File

Args:
    None
Returns:
    None
"""
def generate_blacklist():
    tmp = []                                               #List to build contents of file

    if os.path.isfile(folders.blacklist):                  #Check if black list exists
        return False

    print('Creating Black List')
    tmp.append('#Use this file to create your own custom block list\n')
    tmp.append('#Run notrack script (sudo notrack) after you make any changes to this file\n')
    tmp.append('#doubleclick.net\n')
    tmp.append('#googletagmanager.com\n')
    tmp.append('#googletagservices.com\n')
    tmp.append('#polling.bbc.co.uk #BBC Breaking News Popup\n')
    save_list(tmp, folders.blacklist)
    print()


""" Generate Example White List File

Args:
    None
Returns:
    None
"""
def generate_whitelist():
    tmp = []                                               #List to build contents of file

    if os.path.isfile(folders.whitelist):                  #Check if white list exists
        return False

    print('Creating White List')
    tmp.append('#Use this file to create your own custom block list\n')
    tmp.append('#Run notrack script (sudo notrack) after you make any changes to this file\n')
    tmp.append('#doubleclick.net\n')
    tmp.append('#googletagmanager.com\n')
    tmp.append('#googletagservices.com\n')
    save_list(tmp, folders.whitelist)
    print()


""" Show Version
    Show version number and exit
Args:
    None
Returns:
    None
"""
def show_version():
    print('NoTrack Version %s' % VERSION)
    print()
    sys.exit(0)


""" Test
    Display Config of Block list choices
Args:
    None
Returns:
    None
"""
def test():
    print()
    print('Block Lists Utilised:')

    for bl in blocklistconf.items():
        if bl[1][0]:
            print(bl[0])

    print()
    if config['bl_custom'] == '':
        print('No additional custom block lists utilised')
    else:
        print('Additional custom block lists utilised:')
        print(config['bl_custom'].replace(',', '\n'))

    sys.exit(0)


#Main----------------------------------------------------------------
parser = argparse.ArgumentParser(description = 'NoTrack')
#parser.add_argument('-p', "--play", help='Start Blocking', action='store_true')
#parser.add_argument('-s', "--stop", help='Stop Blocking', action='store_true')
#parser.add_argument('--pause', help='Pause Blocking', type=int)
parser.add_argument('--force', help='Force update block lists', action='store_true')
parser.add_argument('--wait', help='Delay start', action='store_true')
parser.add_argument('--test', help='Show current configuration', action='store_true')
parser.add_argument('-v', '--version', help='Get version number', action='store_true')
#parser.add_argument('--run', help='Run NoTrack', choices=['now', 'wait', 'force'])

args = parser.parse_args()

if args.version:                                           #Showing version?
    show_version()

#Add any OS specific folder locations
blocklistconf['bl_usersblacklist'] = tuple([True, folders.blacklist, TYPE_PLAIN])

services = Services()                                      #Declare service class

load_config()
host = Host(config['ipaddress'])

#Setup the template strings for writing out to black/white list files
[dnsserver_blacklist, dnsserver_whitelist] = services.get_dnstemplatestr(host.name, host.ip)

print('NoTrack version %s' % VERSION)
print('Hostname: %s' % host.name)
print('IP Address: %s' % host.ip)

#Process Arguments
if args.force:                                             #Download blocklists
    FORCE = True
if args.test:
    test()
if args.wait:
    sleep(1000)

check_root()
is_running()                                               #Already running?


print('Opening connection to MariaDB')
db = mariadb.connect(user=dbconf.user, password=dbconf.password, database=dbconf.database)

create_sqltables()                                         #Create SQL Tables
clear_table()                                              #Clear SQL Tables

generate_blacklist()
generate_whitelist()

process_whitelist()                                        #Need whitelist first
action_lists()                                             #Action default lists
action_customlists()                                       #Action users custom lists

print('Finished processing all block lists')
print('Total number of domains added: %d' % len(blocklist))
print('Total number of domains deduplicated: %d' % totaldedupcount)
dedup_lists()                                              #Dedup then insert domains

print('Closing connection to MariaDB')
db.close()


#TODO Check for updates
#TODO Pause