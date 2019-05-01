#!/bin/bash
#Title : NoTrack
#Description : This script will download latest block lists from various sources, then parse them into Dnsmasq.
#Author : QuidsUp
#Date : 2015-01-14
#Usage : sudo bash notrack.sh

#######################################
# User Configerable Settings
#######################################
#Set NetDev to the name of network device e.g. "eth0" IF you have multiple network cards
NetDev=$(ip -o link show | awk '{print $2,$9}' | grep ": UP" | cut -d ":" -f 1)

#If NetDev fails to recognise a Local Area Network IP Address, then you can use IPVersion to assign a custom IP Address in /etc/notrack/notrack.conf
#e.g. IPVersion = 192.168.1.2
IPVersion="IPv4"

declare -A Config                                #Config array for Block Lists
Config[bl_custom]="0"
Config[bl_notrack]=1
Config[bl_tld]=1
Config[bl_notrack_malware]=1
Config[bl_cbl_all]=0
Config[bl_cbl_browser]=0
Config[bl_cbl_opt]=0
Config[bl_cedia]=0
Config[bl_cedia_immortal]=1
Config[bl_hexxium]=1
Config[bl_disconnectmalvertising]=0
Config[bl_easylist]=0
Config[bl_easyprivacy]=0
Config[bl_fbannoyance]=0
Config[bl_fbenhanced]=0
Config[bl_fbsocial]=0
Config[bl_hphosts]=0
Config[bl_malwaredomainlist]=0
Config[bl_malwaredomains]=0
Config[bl_pglyoyo]=0
Config[bl_someonewhocares]=0
Config[bl_spam404]=0
Config[bl_swissransom]=0
Config[bl_swisszeus]=0
Config[bl_winhelp2002]=0
Config[bl_areasy]=0                              #Arab
Config[bl_chneasy]=0                             #China
Config[bl_deueasy]=0                             #Germany
Config[bl_dnkeasy]=0                             #Denmark
Config[bl_fraeasy]=0                             #France
Config[bl_grceasy]=0                             #Greece
Config[bl_huneasy]=0                             #Hungary
Config[bl_idneasy]=0                             #Indonesia
Config[bl_isleasy]=0                             #Iceland
Config[bl_itaeasy]=0                             #Italy
Config[bl_jpneasy]=0                             #Japan
Config[bl_koreasy]=0                             #Korea Easy List
Config[bl_korfb]=0                               #Korea Fanboy
Config[bl_koryous]=0                             #Korea Yous
Config[bl_ltueasy]=0                             #Lithuania
Config[bl_lvaeasy]=0                             #Latvia
Config[bl_nldeasy]=0                             #Netherlands
Config[bl_poleasy]=0                             #Polish
Config[bl_ruseasy]=0                             #Russia
Config[bl_spaeasy]=0                             #Spain
Config[bl_svneasy]=0                             #Slovenian
Config[bl_sweeasy]=0                             #Sweden
Config[bl_viefb]=0                               #Vietnam Fanboy
Config[bl_fblatin]=0                             #Portugal/Spain (Latin Countries)
Config[bl_yhosts]=0                              #China yhosts

#######################################
# Constants
#######################################
readonly VERSION="0.9.0"
readonly MAIN_BLOCKLIST="/etc/dnsmasq.d/notrack.list"
readonly FILE_BLACKLIST="/etc/notrack/blacklist.txt"
readonly FILE_WHITELIST="/etc/notrack/whitelist.txt"
readonly FILE_TLDBLACK="/etc/notrack/domain-blacklist.txt"
readonly FILE_TLDWHITE="/etc/notrack/domain-whitelist.txt"
readonly TLD_CSV="/var/www/html/admin/include/tld.csv"
readonly FILE_CONFIG="/etc/notrack/notrack.conf"
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
urls[notrack]="https://gitlab.com/quidsup/notrack-blocklists/raw/master/notrack-blocklist.txt"
urls[notrack_malware]="https://gitlab.com/quidsup/notrack-blocklists/raw/master/notrack-malware.txt"
urls[cbl_all]="https://zerodot1.gitlab.io/CoinBlockerLists/list.txt"
urls[cbl_browser]="https://zerodot1.gitlab.io/CoinBlockerLists/list_browser.txt"
urls[cbl_opt]="https://zerodot1.gitlab.io/CoinBlockerLists/list_optional.txt"
urls[cedia]="http://mirror.cedia.org.ec/malwaredomains/domains.zip"
urls[cedia_immortal]="http://mirror.cedia.org.ec/malwaredomains/immortal_domains.zip"
urls[hexxium]="https://hexxiumcreations.github.io/threat-list/hexxiumthreatlist.txt"
urls[disconnectmalvertising]="https://s3.amazonaws.com/lists.disconnect.me/simple_malvertising.txt"
urls[easylist]="https://easylist-downloads.adblockplus.org/easylist_noelemhide.txt"
urls[easyprivacy]="https://easylist-downloads.adblockplus.org/easyprivacy.txt"
urls[fbannoyance]="https://easylist-downloads.adblockplus.org/fanboy-annoyance.txt"
urls[fbenhanced]="https://www.fanboy.co.nz/enhancedstats.txt"
urls[fbsocial]="https://secure.fanboy.co.nz/fanboy-social.txt"
urls[hphosts]="http://hosts-file.net/ad_servers.txt"
urls[malwaredomainlist]="http://www.malwaredomainlist.com/hostslist/hosts.txt"
urls[malwaredomains]="http://mirror1.malwaredomains.com/files/justdomains"
urls[spam404]="https://raw.githubusercontent.com/Dawsey21/Lists/master/adblock-list.txt"
urls[swissransom]="https://ransomwaretracker.abuse.ch/downloads/RW_DOMBL.txt"
urls[swisszeus]="https://zeustracker.abuse.ch/blocklist.php?download=domainblocklist"
urls[pglyoyo]="http://pgl.yoyo.org/adservers/serverlist.php?hostformat=;mimetype=plaintext"
urls[someonewhocares]="http://someonewhocares.org/hosts/hosts"
urls[winhelp2002]="http://winhelp2002.mvps.org/hosts.txt"
urls[areasy]="https://easylist-downloads.adblockplus.org/Liste_AR.txt"
urls[chneasy]="https://easylist-downloads.adblockplus.org/easylistchina.txt"
urls[deueasy]="https://easylist-downloads.adblockplus.org/easylistgermany.txt"
urls[dnkeasy]="https://adblock.dk/block.csv"
urls[fblatin]="https://www.fanboy.co.nz/fanboy-espanol.txt"
urls[fineasy]="http://adb.juvander.net/Finland_adb.txt"
urls[fraeasy]="https://easylist-downloads.adblockplus.org/liste_fr.txt"
urls[grceasy]="https://www.void.gr/kargig/void-gr-filters.txt"
urls[huneasy]="https://raw.githubusercontent.com/szpeter80/hufilter/master/hufilter.txt"
urls[idneasy]="https://raw.githubusercontent.com/ABPindo/indonesianadblockrules/master/subscriptions/abpindo.txt"
urls[isleasy]="http://adblock.gardar.net/is.abp.txt"
urls[itaeasy]="https://easylist-downloads.adblockplus.org/easylistitaly.txt"
urls[jpneasy]="https://raw.githubusercontent.com/k2jp/abp-japanese-filters/master/abpjf.txt"
urls[koreasy]="https://raw.githubusercontent.com/gfmaster/adblock-korea-contrib/master/filter.txt"
urls[korfb]="https://www.fanboy.co.nz/fanboy-korean.txt"
urls[koryous]="https://raw.githubusercontent.com/yous/YousList/master/youslist.txt"
urls[ltueasy]="http://margevicius.lt/easylistlithuania.txt"
urls[lvaeasy]="https://notabug.org/latvian-list/adblock-latvian/raw/master/lists/latvian-list.txt"
urls[nldeasy]="https://easylist-downloads.adblockplus.org/easylistdutch.txt"
urls[poleasy]="https://raw.githubusercontent.com/MajkiIT/polish-ads-filter/master/polish-adblock-filters/adblock.txt"
urls[ruseasy]="https://easylist-downloads.adblockplus.org/ruadlist+easylist.txt"
urls[spaeasy]="https://easylist-downloads.adblockplus.org/easylistspanish.txt"
urls[svneasy]="https://raw.githubusercontent.com/betterwebleon/slovenian-list/master/filters.txt"
urls[sweeasy]="https://www.fanboy.co.nz/fanboy-swedish.txt"
urls[viefb]="https://www.fanboy.co.nz/fanboy-vietnam.txt"
urls[yhosts]="https://raw.githubusercontent.com/vokins/yhosts/master/hosts"


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
declare -a sql_list                              #Array to store each list for entering into MariaDB
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
# Create File
#   Checks if a file exists and creates it
#
# Globals:
#   None
# Arguments:
#   $1. File to create
# Returns:
#   None
#
#######################################
function create_file() {
  if [ ! -e "$1" ]; then                                   #Does file already exist?
    echo "Creating file: $1"
    touch "$1"
  fi
}


#######################################
# Create SQL Tables
#   Create SQL tables for blocklist, in case it has been deleted
#
# Globals:
#   USER, PASSWORD, DBNAME
# Arguments:
#   None
# Returns:
#   None
#
#######################################
function create_sqltables {
  mysql --user="$USER" --password="$PASSWORD" -D "$DBNAME" -e "CREATE TABLE IF NOT EXISTS blocklist (id BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT, bl_source TINYTEXT, site TINYTEXT, site_status BOOLEAN, comment TEXT);"

  if [ -e "/var/log/lighttpd/access.log" ]; then
    #Not SQL related, but my system was causing ntrk-parse to fail because of permissions
    sudo chmod 775 /var/log/lighttpd/access.log
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
# Calculate Percent Point in list files
#   1. Count number of lines in file with "wc"
#   2. Calculate Percentage Point (number of for loop passes for 1%)
#   3. Calculate Jump Point (increment of 1 percent point on for loop)
#   E.g.1 20 lines = 1 for loop pass to increment percentage by 5%
#   E.g.2 200 lines = 2 for loop passes to increment percentage by 1%
#
# Globals:
#   percentpoint
#   jumppoint
# Arguments:
#   $1. File to Calculate
# Returns:
#   None - via percentpoint and jumppoint
#
#######################################
function calc_percentpoint() {
  local linecount=0

  linecount=$(wc -l "$1" | cut -d " " -f 1)                #Count number of lines
  if [ "$linecount" -ge 100 ]; then                        #lines >= 100
    percentpoint=$((linecount/100))
    jumppoint=1
  else
    percentpoint=1
    jumppoint=$((100/linecount))
  fi
}


#######################################
# Check Version of Dnsmasq
#
# Globals:
#   None
# Arguments:
#   None
# Returns:
#   50. Dnsmasq Missing
#   51. Dnsmasq Version Unknown
#   52. Dnsmasq doesn't support whitelisting (below 2.75)
#   53. Dnsmasq supports whitelisting (2.75 and above)
#
#######################################
function check_dnsmasq_version() {
  local verstr=""

  if [ -z "$(command -v dnsmasq)" ]; then
    return 50
  fi

  verstr="$(dnsmasq --version)"                            #Get version from dnsmasq

  #The return is very wordy, so we need to extract the relevent info
  [[ $verstr =~ ^Dnsmasq[[:space:]]version[[:space:]]([0-9]\.[0-9]{1,2}) ]]

  local VerNo="${BASH_REMATCH[1]}"                         #Extract version number from string
  if [[ -z $VerNo ]]; then                                 #Was anything extracted?
    return 51
  else
    [[ $VerNo =~ ([0-9])\.([0-9]{1,2}) ]]
    if [ "${BASH_REMATCH[1]}" -eq 2 ] && [ "${BASH_REMATCH[2]}" -ge 75 ]; then  #Version 2.75 onwards
      return 53
    elif [ "${BASH_REMATCH[1]}" -ge 3 ]; then              #Version 3 onwards
      return 53
    else                                                   #2.74 or below
      return 52
    fi
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
# Get File Time
#   Gets file time of a file if it exists
#
# Globals:
#   filetime
# Arguments:
#   $1. File to be checked
# Returns:
#   Via filetime
#
#######################################
function get_filetime() {
  if [ -e "$1" ]; then                                     #Does file exist?
    #Get last data modification in secs since Epoch
    filetime=$(stat -c %Y "$1")
  else
    filetime=0                                             #Otherwise default to zero
  fi
}


#######################################
# Get Blacklist
#   Get Users Custom Blacklist
#
# Globals:
#   FILE_BLACKLIST, sql_list
# Arguments:
#   None
# Returns:
#   None
#
#######################################
function get_blacklist() {
  echo "Processing Custom Black List"
  process_list "$FILE_BLACKLIST" "match_plainline"

  if [ ${#sql_list[@]} -gt 0 ]; then                         #Get size of sql_list
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
    dlfile="/tmp/custom_$filename.txt"
   
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
#   sql_list, jumppoint, percentpoint
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
  
  if [ ${#sql_list[@]} -gt 0 ]; then                       #Any domains in the block list?
    insert_data "custom_$csvfile"
    echo "Finished processing $csvfile"
  else                                                     #No domains in block list
    echo "No domains extracted from block list"
  fi
  
  echo
}


#######################################
# Insert Data into SQL Table
#   1. Save sql_list array to .csv file
#   2. Bulk write csv file into MariaDB
#   3. Delete .csv file
#
# Globals:
#   sql_list
# Arguments:
#   $1. Blocklist
# Returns:
#   None
#
#######################################
function insert_data() {
  printf "%s\n" "${sql_list[@]}" > "/tmp/$1.csv"           #Output arrays to file

  mysql --user="$USER" --password="$PASSWORD" -D "$DBNAME" -e "LOAD DATA INFILE '/tmp/$1.csv' INTO TABLE blocklist FIELDS TERMINATED BY ',' ENCLOSED BY '\"' LINES TERMINATED BY '\n' (@var1, @var2, @var3) SET id=NULL, bl_source = '$1', site = @var1, site_status=@var2, comment=@var3;"
  delete_file "/tmp/$1.csv"

  sql_list=()                                              #Zero SQL Array
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
# Load Config File
#   Default values are set at top of this script
#   Config File contains Key & Value on each line for some/none/or all items
#   If the Key is found in the case, then we write the value to the Variable
#
# Globals:
#   Config
# Arguments:
#   None
# Returns:
#   None
#
#######################################
function load_config() {
  local key=""
  local value=""

  if [ ! -e "$FILE_CONFIG" ]; then
    echo "Config $FILE_CONFIG missing"
    return
  fi

  echo "Reading Config File"
  while IFS='= ' read -r key value                         #Seperator '= '
  do
    if [[ ! $key =~ ^[[:space:]]*# ]] && [[ -n $key ]]; then
      value="${value%%\#*}"                                #Del in line right comments
      value="${value%%*( )}"                               #Del trailing spaces
      value="${value%\"*}"                                 #Del opening string quotes
      value="${value#\"*}"                                 #Del closing string quotes

      if [ -n "${Config[$key]}" ]; then                       #Does key exist in Config array?      
        Config[$key]="$value"                              #Yes - replace value
      else
        case "$key" in
          IPVersion) IPVersion="$value";;
          NetDev) NetDev="$value";;
          LatestVersion) oldversion="$value";;
        esac
      fi
    fi
  done < $FILE_CONFIG

  unset IFS
}


#######################################
# Load White List
#   Load items from whitelist file into whitelist array
#   Add to SQL table as well
#
# Globals:
#   FILE_WHITELIST, sql_list, whitelist, REGEX_PLAINLINE
# Arguments:
#   None
# Returns:
#   None
#
#######################################
function load_whitelist() {
  echo "Loading whitelist"

  while IFS=$'\n' read -r line
  do
    if [[ $line =~ $REGEX_PLAINLINE ]]; then
      whitelist["${BASH_REMATCH[1]}"]=true                 #Add site to associative array
      sql_list+=("\"${BASH_REMATCH[1]}\",\"1\",\"${BASH_REMATCH[2]}\"")
    fi
  done < $FILE_WHITELIST

  unset IFS

  if [ ${#sql_list[@]} -gt 0 ]; then                       #Any items in sql_list
    insert_data "whitelist"
  fi
}


#######################################
# Get List
#   Downloads a blocklist and prepares it for processing
#
# Globals:
#   Config, filetime, sql_list
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
  local dlfile="/tmp/$1.txt"
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

  if [ ${#sql_list[@]} -gt 0 ]; then                       #Are there any domains in the block list?
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
# Process TLD List
#   1. Load Domain whitelist into associative array
#   2. Read downloaded TLD list, and compare with Domain whitelist
#   3. Read users custom TLD list, and compare with Domain whitelist
#   4. Results are stored in sql_list, and domainlist These arrays are sent back to get_list() for writing to file.
#   The Downloaded & Custom lists are handled seperately to reduce number of disk writes in say cat'ting the files together
# Globals:
#   FILE_TLDBLACK, FILE_TLDWHITE
#   TLD_CSV
# Arguments:
#   $1. List file to process
# Returns:
#   None
#
#######################################
function process_tldlist() {
  local line=""
  local name=""
  local risk=""
  local tld=""
  local -A tld_black
  local -A tld_white

  #Should we process this list according to the Config settings?
  if [ "${Config[bl_tld]}" == 0 ]; then
    echo "Not processing Top Level Domain list"
    echo
    return 1                                               #If not then leave function
  fi

  echo "Processing Top Level Domain list"

  while IFS=$'\n' read -r line                             #Load TLD White into array
  do
    if [[ $line =~ ^\.([A-Za-z0-9\-]+)[[:space:]]?#?(.*)$ ]]; then
      tld_white[".${BASH_REMATCH[1]}"]=true
    fi
  done < "$FILE_TLDWHITE"

  while IFS=$'\n' read -r line                             #Load TLD Black into array
  do
    if [[ $line =~ ^\.([A-Za-z0-9\-]+)[[:space:]]?#?(.*)$ ]]; then
      tld_black[".${BASH_REMATCH[1]}"]=true
    fi
  done < "$FILE_TLDBLACK"

  while IFS=$',\n' read -r tld name risk _; do             #Load the TLD CSV file
    if [[ $risk == 1 ]]; then                              #Risk 1 - High Risk
      if [ -z "${tld_white[$tld]}" ]; then                 #Is site not in whitelist?
        domainlist[$tld]=true                              #Add high risk unless told
        sql_list+=("\"$tld\",\"1\",\"$name\"")
        tldlist[$tld]=true
      fi
    else
      if [ -n "${tld_black[$tld]}" ]; then
        domainlist[$tld]=true
        sql_list+=("\"$tld\",\"1\",\"$name\"")
        tldlist[$tld]=true
      fi
    fi
  done < "$TLD_CSV"

  insert_data "bl_tld"

  echo "Finished processing Top Level Domain List"
  echo

  unset IFS
}



#######################################
# Process White Listed sites from Blocked TLD List
#   Depending on the version of dnsmasq, we can either tell dnsmasq to override the blacklist
#    or resolve the domain now and leave the IP in the whitelist file
#
# Globals:
#   whitelist
#   tldlist
# Arguments:
#   None
# Returns:
#   0. Success
#   55. Failed
#
#######################################
function process_whitelist() {
  local method=0                                           #1: White list from Dnsmasq, 2: Dig
  local line=""
  local domain=""
  local -a domains
  domains=()                                               #Zero Array

  echo "Processing whitelist"

  check_dnsmasq_version                                    #What version is Dnsmasq?
  if [ $? == 53 ]; then                                    #v2.75 or above can whitelist
    method=1
    echo "White listing from blocked Top Level Domains with Dnsmasq"
  elif [ -n "$(command -v dig)" ]; then                    #Fallback - Is dig available?
    method=2
    echo "White listing using resolved IP's from Dig"
  else                                                     #Old version and no fallback
    echo "Unable to White list from blocked Top Level Domains"
    echo
    return 55
  fi

  for domain in "${!whitelist[@]}"; do                     #Read entire White List associative array
    if [[ $domain =~ \.[A-Za-z0-9\-]+$ ]]; then            #Extract the TLD
      if [ -n "${tldlist[${BASH_REMATCH[0]}]}" ]; then     #Is TLD present in Domain List?
        if [ "$method" == 1 ]; then                        #What method to unblock domain?
          domains+=("server=/$domain/#")                   #Add unblocked domain to domains Array
        elif [ "$method" == 2 ]; then                      #Or use Dig
          while IFS=$'\n' read -r line                     #Read each line of Dig output
          do
            #Match A or AAAA IPv4/IPv6
            if [[ $line =~ (A|AAAA)[[:space:]]+([a-f0-9\.\:]+)$ ]]; then
              domains+=("host-record=$domain,${BASH_REMATCH[2]}")
            fi
            if [[ $line =~ TXT[[:space:]]+(.+)$ ]]; then   #Match TXT "comment"
              domains+=("txt-record=$domain,${BASH_REMATCH[1]}")
            fi
          done <<< "$(dig "$domain" @8.8.8.8 ANY +noall +answer)"
        fi
      fi
    fi
  done

  unset IFS                                                #Reset IFS

  if [ "${#domains[@]}" -gt 0 ]; then                      #How many items in domains array?
    echo "Finished processing white listed domains from blocked TLD's"
    echo "${#domains[@]} domains white listed"
    echo "Writing white list to /etc/dnsmasq.d/whitelist.list"
    printf "%s\n" "${domains[@]}" > "/etc/dnsmasq.d/whitelist.list"   #Output array to file
  else                                                     #No domains, delete old list file
    echo "No domains to white list from blocked TLD's"
    delete_file "/etc/dnsmasq.d/whitelist.list"
  fi
  echo
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
      sql_list+=("\"$domain\",\"0\",\"$2\"")               #Add to SQL as Disabled
    else                                                   #Not found it whitelist
      sql_list+=("\"$domain\",\"1\",\"$2\"")               #Add to SQL as Active
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
# Upgrade NoTrack
#   As of v0.7.9 Upgrading is now handled by ntrk-upgrade.sh
# Globals:
#   None
# Arguments:
#   None
# Returns:
#   None
#######################################
function upgrade() {
  if [ -e /usr/local/sbin/ntrk-upgrade ]; then
    echo "Running ntrk-upgrade"
    /usr/local/sbin/ntrk-upgrade
    exit 0
  fi

  error_exit "Unable to find ntrk-upgrade.sh" "20"
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

get_list "notrack" "notrack"
get_list "notrack_malware" "match_plainline"
get_list "cedia" "csv" "domains.txt"
get_list "cedia_immortal" "match_plainline" "immortal_domains.txt"
get_list "hexxium" "match_easyline"
get_list "cbl_all" "match_plainline"
get_list "cbl_browser" "match_plainline"
get_list "cbl_opt" "match_plainline"
get_list "disconnectmalvertising" "match_plainline"
get_list "easylist" "match_easyline"
get_list "easyprivacy" "match_easyline"
get_list "fbannoyance" "match_easyline"
get_list "fbenhanced" "match_easyline"
get_list "fbsocial" "match_easyline"
get_list "hphosts" "match_unixline"
get_list "malwaredomainlist" "match_unixline"
get_list "malwaredomains" "match_plainline"
get_list "pglyoyo" "match_plainline"
get_list "someonewhocares" "match_unixline"
get_list "spam404" "match_easyline"
get_list "swissransom" "match_plainline"
get_list "swisszeus" "match_plainline"
get_list "winhelp2002" "match_unixline"
get_list "fblatin" "match_easyline"
get_list "areasy" "match_easyline"
get_list "chneasy" "match_easyline"
get_list "deueasy" "match_easyline"
get_list "dnkeasy" "match_easyline"
get_list "fraeasy" "match_easyline"
get_list "grceasy" "match_easyline"
get_list "huneasy" "match_easyline"
get_list "idneasy" "match_easyline"
get_list "isleasy" "match_easyline"
get_list "itaeasy" "match_easyline"
get_list "jpneasy" "match_easyline"
get_list "koreasy" "match_easyline"
get_list "korfb" "match_easyline"
get_list "koryous" "match_easyline"
get_list "ltueasy" "match_easyline"
get_list "lvaeasy" "match_easyline"
get_list "nldeasy" "match_easyline"
get_list "poleasy" "match_easyline"
get_list "ruseasy" "match_easyline"
get_list "spaeasy" "match_easyline"
get_list "svneasy" "match_easyline"
get_list "sweeasy" "match_easyline"
get_list "viefb" "match_easyline"
get_list "yhosts" "match_unixline"


echo "Deduplicated $dedup Domains"
sortlist                                                   #Sort, Dedup 2nd round, Save list

service_restart dnsmasq

echo "NoTrack complete"
echo
