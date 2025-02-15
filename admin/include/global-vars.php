<?php
define('STATUS_ENABLED', 1);
define('STATUS_DISABLED', 2);
define('STATUS_PAUSED', 4);
define('STATUS_INCOGNITO', 8);
define('STATUS_NOTRACKRUNNING', 64);
define('STATUS_ERROR', 128);

define('VERSION', '21.03');
define('SERVERNAME', 'localhost');
define('USERNAME', 'ntrk');
define('PASSWORD', 'ntrkpass');
define('DBNAME', 'ntrkdb');

define('ROWSPERPAGE', 200);

define('DIR_TMP', '/tmp/');
define('CONFIGFILE', '/etc/notrack/notrack.conf');
define('DNS_LOG', '/var/log/notrack.log');
define('TLD_CSV', '../include/tld.csv'); //To be DEPRECATED
define('NOTRACK_LIST', '/etc/dnsmasq.d/notrack.list');

//Status values for Analytics ACK (acknowledged)
define('STATUS_OPEN', 1);
define('STATUS_RESOLVED', 2);

//Severity values for Analytics, DNS Queries
define('SEVERITY_LOW', 1);
define('SEVERITY_MED', 2);
define('SEVERITY_HIGH', 4);

//Regular Expressions:
define('REGEX_DATETIME', '/^2\d\d\d\-[0-1][0-9]\-(0[1-9]|[1-2][0-9]|3[01])\s([0-1][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/');

//VALIDAPI is any length of hexadecimal lowercase from start to end
define('REGEX_VALIDAPI', '/^[a-f0-9]*$/');

//IPCIDR = Check for valid IP/CIDR - e.g. 192.168.0.1/24
//         Reject leading zeros - e.g. 10.00.00.009/02
//Group 1 - First three octets with following zero - (222.) * 3 - 250-255, 200-249, 100-199, 10-99, 0-9
//Group 2 - Fourth octets
//Forward slash /
//Group 3 - CIDR notation 30-32, 10/20-19/29, 0-9
define('REGEX_IPCIDR', '/^((25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9][0-9]|[0-9])\.){3}(25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9][0-9]|[0-9])\/(3[0-2]|[1-2][0-9]|[0-9])$/');

define('REGEX_URLSEARCH', '/[^\w\.\-]/');        //Valid leters for URL search

if (!extension_loaded('memcache')) {
  die('NoTrack requires memcached and php-memcached to be installed');
}

$mem = new Memcache;                             //Initiate Memcache
$mem->connect('localhost', 11211);

?>
