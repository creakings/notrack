#!/bin/bash
#Title : NoTrack Analytics
#Description : Analyse dns_logs for suspect lookups to malicious or unknown tracking sites
#Author : QuidsUp
#Date : 2019-03-17
#Usage : 


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
# Check Malware
#   
#
# Globals:
#   DBNAME, PASSWORD, USER
# Arguments:
#   None
# Returns:
#   None
#
#######################################
function check_malware() {
  results=()
  mapfile results < <(mysql --user="$USER" --password="$PASSWORD" -D "$DBNAME" --batch -e "SELECT * FROM dnslog WHERE log_time >= DATE_SUB(NOW(), INTERVAL 2 HOUR) AND dns_request IN (SELECT site FROM blocklist WHERE bl_source = 'bl_notrack_malware');")
  
  if [ ${#results[@]} -gt 1 ]; then
    review_results "Malware-bl_notrack_malware"
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
  local -i i=1
  
  #Group 1: id
  #Group 2: log_time
  #Group 3: sys
  #Group 4: dns_request
  #Group 5: dns_result
  
  results_size=${#results[@]}
  
  while [ $i -lt "$results_size" ]
  do    
    if [[ ${results[$i]} =~ ^([0-9]+)[[:blank:]]([0-9\-]+[[:blank:]][0-9:]+)[[:blank:]]([^[:blank:]]+)[[:blank:]]([^[:blank:]]+)[[:blank:]]([ABCL]) ]]; then
      insert_data "${BASH_REMATCH[2]}" "${BASH_REMATCH[3]}" "${BASH_REMATCH[4]}" "${BASH_REMATCH[5]}" "$issue"
    fi
    ((i++))
  done  
  
}


#######################################
# Insert Data into SQL Table
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
#echo "$1,$2,$3,$4,$5"
 
mysql --user="$USER" --password="$PASSWORD" -D "$DBNAME" << EOF
INSERT INTO anlytics (id,log_time,sys,dns_request,dns_result,issue,ack) VALUES (NULL,'$1','$2','$3','$4','$5',FALSE);
EOF

#TODO error checking with #?
}


#SELECT DISTINCT bl_source FROM blocklist;
create_sqltables

check_malware
