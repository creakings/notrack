#!/bin/bash
#Title : NoTrack Analytics
#Description : Analyse dns_logs for suspect lookups to malicious or unknown tracking sites
#Author : QuidsUp
#Date : 2019-03-17
#Usage : bash ntrk-analytics.sh

#######################################
# Constants
#######################################
readonly USER="ntrk"
readonly PASSWORD="ntrkpass"
readonly DBNAME="ntrkdb"


#######################################
# Global Variables
#######################################
declare -a results
declare -A blocklists
declare -A whitelist


#######################################
# Check Running
#
# Globals:
#   None
# Arguments:
#   None
# Returns:
#   None
#
#######################################
function check_running() {
  local pid=""
  pid=$(pgrep ntrk-analytics | head -n 1)                  #Get PID of first process

  #Check if another copy is running
  if [[ $pid != "$$" ]] && [[ -n $pid ]] ; then            #$$ = This PID
    error_exit "Ntrk-Analytics already running under pid $pid" "8"
  fi
}


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
# Create SQL Tables
#   Create SQL tables for analytics, in case it has been deleted
#
# Globals:
#   USER, PASSWORD, DBNAME
# Arguments:
#   None
# Returns:
#   None
#
#######################################
function create_sqltables() {
  mysql --user="$USER" --password="$PASSWORD" -D "$DBNAME" -e "CREATE TABLE IF NOT EXISTS analytics (id BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT, log_time DATETIME, sys TINYTEXT, dns_request TINYTEXT, dns_result CHAR(1), issue TINYTEXT, ack BOOLEAN);"
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

  #echo "DELETE FROM blocklist;" | mysql --user="$USER" --password="$PASSWORD" -D "$DBNAME"
  #echo "ALTER TABLE blocklist AUTO_INCREMENT = 1;" | mysql --user="$USER" --password="$PASSWORD" -D "$DBNAME"
}


#######################################
# Get Available Block Lists
#   1. Find the distinct blocklists the user has selected in blocklist table
#   2. Add them to associative array blocklists
#
# Globals:
#   DBNAME, PASSWORD, USER, blocklists
# Arguments:
#   1. Blocklist Code
# Returns:
#   None
#
#######################################
function get_blocklists() {
  local -a templist
  local str=""

  echo "Checking which blocklists are in use"

  mapfile templist < <(mysql --user="$USER" --password="$PASSWORD" -D "$DBNAME" -N --batch -e "SELECT DISTINCT bl_source FROM blocklist;")

  for str in "${templist[@]}"; do
    str="${str//[[:space:]]/}"                             #Remove spaces and tabs
    blocklists[$str]=true                                  #Add key to blocklists
  done
}


#######################################
# Get Whitelist
#   1. Load list of sites from whitelist bl_source in blocklist
#   2. Add them to associative array whitelist
#
# Globals:
#   DBNAME, PASSWORD, USER, whitelist
# Arguments:
#   1. Blocklist Code
# Returns:
#   None
#
#######################################
function get_whitelist() {
  local -a templist
  local str=""

  echo "Loading whitelist"

  mapfile templist < <(mysql --user="$USER" --password="$PASSWORD" -D "$DBNAME" -N --batch -e "SELECT site from blocklist WHERE bl_source = 'whitelist';")

  for str in "${templist[@]}"; do
    str="${str//[[:space:]]/}"                             #Remove spaces and tabs
    whitelist[$str]=true                                   #Add key to whitelist
  done
}


#######################################
# Check Malware
#   Do a search of dnslog for any requests for sites that appear in a blocklist
#
# Globals:
#   DBNAME, PASSWORD, USER
# Arguments:
#   1. Blocklist Code
# Returns:
#   None
#
#######################################
function check_malware() {
  local bl="$1"                                            #Blocklist
  results=()                                               #Clear results array

  echo "Searching for domains from $bl"
  mapfile results < <(mysql --user="$USER" --password="$PASSWORD" -D "$DBNAME" -N --batch -e "SELECT * FROM dnslog WHERE log_time >= DATE_SUB(NOW(), INTERVAL 1 HOUR) AND dns_request IN (SELECT site FROM blocklist WHERE bl_source = '$bl') GROUP BY(dns_request) ORDER BY id asc;")

  if [ ${#results[@]} -gt 0 ]; then
    review_results "Malware-$bl"
  fi
}


#######################################
# Check Tracking
#   Do a search of dnslog for any requests for sites starting with known tracker names
#
# Globals:
#   DBNAME, PASSWORD, USER
# Arguments:
#   1. regular expression
# Returns:
#   None
#
#######################################
function check_tracking() {
  local pattern="$1"
  results=()                                               #Clear results array

  echo "Searching for trackers with regular expression $pattern"
  mapfile results < <(mysql --user="$USER" --password="$PASSWORD" -D "$DBNAME" -N --batch -e "SELECT * FROM dnslog WHERE log_time >= DATE_SUB(NOW(), INTERVAL 1 HOUR) AND dns_request REGEXP '$pattern' AND dns_result='A' GROUP BY(dns_request) ORDER BY id asc;")

  if [ ${#results[@]} -gt 0 ]; then
    review_results "Tracker"
  fi
}


#######################################
# Review Results
#   Split each tabbed seperated array item of results and then
#    send those arrays (minus id) to insert_data
#
# Globals:
#   results
# Arguments:
#   1. What the result is from
# Returns:
#   None
#
#######################################
function review_results() {
  local issue="$1"
  local -i results_size=0
  local -i i=0

  #Group 1: id
  #Group 2: log_time
  #Group 3: sys
  #Group 4: dns_request
  #Group 5: dns_result

  results_size=${#results[@]}
  echo "Found $results_size domains"

  while [ $i -lt "$results_size" ]
  do
    if [[ ${results[$i]} =~ ^([0-9]+)[[:blank:]]([0-9\-]+[[:blank:]][0-9:]+)[[:blank:]]([^[:blank:]]+)[[:blank:]]([^[:blank:]]+)[[:blank:]]([ABCL]) ]]; then
      insert_data "${BASH_REMATCH[2]}" "${BASH_REMATCH[3]}" "${BASH_REMATCH[4]}" "${BASH_REMATCH[5]}" "$issue"
    fi
    ((i++))
  done

  echo
}


#######################################
# Insert Data into SQL Table
#   Disreagard any dns_request that appears in whitelist
#
# Globals:
#   DBNAME, PASSWORD, USER
# Arguments:
#   $1. log_time
#   $2. system
#   $3. dns_request
#   $4. dns_result
#   $5. issue
# Returns:
#   None
#
#######################################

function insert_data() {
  #echo "$1,$2,$3,$4,$5"                                   #Uncomment for debugging
  if [ -n "${whitelist[$3]}" ]; then                       #Does dns_request appear in whitelist?
    echo "skipping $3"
    return 0
  fi

mysql --user="$USER" --password="$PASSWORD" -D "$DBNAME" << EOF
INSERT INTO analytics (id,log_time,sys,dns_request,dns_result,issue,ack) VALUES (NULL,'$1','$2','$3','$4','$5',FALSE);
EOF

  if [ $? -gt 0 ]; then
    error_exit "Unable to add data to analytics table" "36"
  fi
}

#######################################
# Main
#
#######################################
echo "NoTrack Analytics"

check_running
create_sqltables

get_blocklists                                             #Check which blocklists are in use
get_whitelist
#Check if any sites from the following blocklists have been accessed
[ -n "${blocklists['bl_notrack_malware']}" ] && check_malware "bl_notrack_malware"
[ -n "${blocklists['bl_hexxium']}" ] && check_malware "bl_hexxium"
[ -n "${blocklists['bl_cedia']}" ] && check_malware "bl_cedia"
[ -n "${blocklists['bl_cedia_immortal']}" ] && check_malware "bl_cedia_immortal"
[ -n "${blocklists['bl_malwaredomainlist']}" ] && check_malware "bl_malwaredomainlist"
[ -n "${blocklists['bl_malwaredomains']}" ] && check_malware "bl_malwaredomains"
[ -n "${blocklists['bl_swissransom']}" ] && check_malware "bl_swissransom"

#Regular expression checks for past hour of domains accessed
#Note: Two backslashes are required for MariaDB and a third backslash is required for bash

#Checks for Pixels, Telemetry, and Trackers
check_tracking "^log\\\."                        #log as a subdomain (exclude login.)
check_tracking "^pxl?\\\."                       #px, optional l, as a subdomain
check_tracking "pixel[^\\\.]{0,8}\\\."           #pixel, followed by 0 to 8 non-dot chars anywhere
check_tracking "telemetry"                       #telemetry anywhere
check_tracking "trk[^\\\.]{0,3}\\\."             #trk, followed by 0 to 3 non-dot chars anywhere
check_tracking "track(ing|\\\-[a-z]{2,8})?\\\."  #track, tracking, track-eu as a subdomain / domain.
#Have to exclude tracker. (bittorent), security-tracker (Debian), and tracking-protection (Mozilla)

#Checks for Advertising
check_tracking "^ads\\\."
check_tracking "^adserver"
check_tracking "^advert"

