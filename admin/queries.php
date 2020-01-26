<?php
require('./include/global-vars.php');
require('./include/global-functions.php');
require('./include/config.php');
require('./include/menu.php');

ensure_active_session();

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <link href="./css/master.css" rel="stylesheet" type="text/css">
  <link href="./css/icons.css" rel="stylesheet" type="text/css">
  <link rel="icon" type="image/png" href="./favicon.png">
  <script src="./include/menu.js"></script>
  <script src="./include/queries.js"></script>
  <title>NoTrack - DNS Queries</title>
  <meta name="viewport" content="width=device-width, initial-scale=0.8">
</head>

<body>
<?php
draw_topmenu('DNS Queries');
draw_sidemenu();
echo '<div id="main">'.PHP_EOL;

/************************************************
*Constants                                      *
************************************************/
DEFINE('DEF_FILTER', 'all');
DEFINE('DEF_SYSTEM', 'all');

$FILTERLIST = array('all' => 'All Requests',
                    'A' => 'Allowed Only',
                    'B' => 'Blocked Only',
                    'L' => 'Local Only');

$GROUPLIST = array('name' => 'Site Name',
                   'time' => 'Time');

$TIMELIST = array('1 HOUR' => '1 Hour',
                  '4 HOUR' => '4 Hours',
                  '12 HOUR' => '12 Hours',
                  '1 DAY' => '1 Day',
                  '7 DAY' => '7 Days',
                  '30 DAY' => '30 Days');

$VIEWLIST = array('name', 'time');

$INVESTIGATE = '';
$INVESTIGATEURL = '';

/************************************************
*Global Variables                               *
************************************************/
$page = 1;
$filter = DEF_FILTER;
$datetime = 'P1D';                                         //Default range = Past 1 Day
$datetime_text = '';
$datetime_search = '';
$groupby = 'name';
$searchbox = '';
$sort = 'DESC';
$sysip = DEF_SYSTEM;

//Date Time Duration from ISO 8601
//Note: PHP allows the values to be higher than the normal time period e.g 90S instead of 1M30S
//Start with P
//Lookahead to see if next letter is T and a number or a number
//Group 1: 1-9 Years (optional)
//Group 2: 1-99 Months (optional)
//Group 3: 1-999 Days (optional)
//Non-Capture Group for optional T (Time component)
//Group 4: 1-999 Hours (optional)
//Group 5: 1-999 Minutes (optional)
//Group 6: 1-999 Seconds (optional)
define('REGEX_DTDURATION', '/^P(?=T\d|\d)(\dY)?(\d{1,2}M)?(\d{1,3}D)?(?:T(\d{1,3}H)?(\d{1,3}M)?(\d{1,3}S)?)?$/');
define('REGEX_DTSINGLE', '/^([0-9]{4})\-(1[0-2]|0[1-9])\-(3[01]|0[1-9]|[12][0-9])T(2[0-3]|[01][0-9]):([0-5][0-9]):([0-5][0-9])$/');
define('REGEX_DTRANGE', '/^([0-9]{4})\-(1[0-2]|0[1-9])\-(3[01]|0[1-9]|[12][0-9])T(2[0-3]|[01][0-9]):([0-5][0-9]):([0-5][0-9])\/([0-9]{4})\-(1[0-2]|0[1-9])\-(3[01]|0[1-9]|[12][0-9])T(2[0-3]|[01][0-9]):([0-5][0-9]):([0-5][0-9])$/');

//Date Time Start with Duration
//Combined date time with a Duration
//Group 1 - Date Time e.g. 2020-01-26T17:10:00
//Non-Capture Group 0000-9999 Years
//Non-Capture Group 10-12 or 0-9 Months
//Non-Capture Group 30-31 or 0 1-9 or 1-2 0-9 Days
//T
//Non-Capture Group 2 0-3 or 0-1 0-9 Hours
//Non-Capture Group 0-5 0-9 Minutes
//Non-Capture Group 0-5 0-9 Seconds
// / (split)
//Group 2 Duration (see REGEX_DTDURATION)
define('REGEX_DTSTARTDURATION', '/^((?:[0-9]{4})\-(?:1[0-2]|0[1-9])\-(?:3[01]|0[1-9]|[12][0-9])T(?:2[0-3]|[01][0-9]):(?:[0-5][0-9]):(?:[0-5][0-9]))\/(P(?=T\d|\d)(?:\dY)?(?:1[0-2]M|[0-9]M)?(?:[1-2][0-9]D|3[0-1]D|[0-9]D)?T?(?:[1-2][0-4]H|[0-9]H)?(?:[0-5]?[0-9]M)?(?:[0-5]?[0-9]S)?)$/');

define('REGEX_DTNAMED', '/^(last week midnight|first day of (last|this) (week|month) midnight)\/(now|last week midnight|first day of (last|this) (week|month) midnight)$/');

/************************************************
*Arrays                                         *
************************************************/
$TLDBlockList = array();


/********************************************************************
 *  Build Link Text
 *    Returns a HTML link containing parameters used on the page
 *    Used to create the links for sort and pagination
 *
 *  Params:
 *    None
 *  Return:
 *    String of parameters
 */
function buildlink() {
  global $datetime, $filter, $groupby, $searchbox, $sysip;

  $link = "groupby=$groupby";

  $link .= ($datetime != '') ? '&amp;datetime='.rawurlencode($datetime) : '';
  $link .= ($filter != DEF_FILTER) ? "&amp;filter={$filter}" : '';
  $link .= ($sysip != DEF_SYSTEM) ? "&amp;sysip={$sysip}" : '';
  $link .= ($searchbox != '') ? "&amp;searchbox={$searchbox}" : '';

  return $link;
}


/********************************************************************
 *  Get CIDR Range
 *    Returns Start and End IP of a given IP/CIDR range
 *
 *  Params:
 *    IP/CIDR - e.g. 192.168.0.0/24
 *  Return:
 *    Array of Start and End IP range
 */
function cidr($cidr) {
  list($ip, $mask) = explode('/', $cidr);

  $maskBinStr =str_repeat("1", $mask ) . str_repeat("0", 32-$mask );          //net mask binary string
  $inverseMaskBinStr = str_repeat("0", $mask ) . str_repeat("1",  32-$mask ); //inverse mask

  $ipLong = ip2long( $ip );
  $ipMaskLong = bindec( $maskBinStr );
  $inverseIpMaskLong = bindec( $inverseMaskBinStr );
  $netWork = $ipLong & $ipMaskLong;

  $start = long2ip($netWork);
  $end = long2ip(($netWork | $inverseIpMaskLong));

  //echo "start $start end $end";                          //Uncomment to Debug
  return array($start, $end);
}


/********************************************************************
 *  Get DNS Search
 *    Checks for various combinations of allowable searches for IP address
 *    1. IP/CIDR
 *    2. IPv4 / IPv6
 *
 *  Params:
 *    sysip GET parameter
 *  Return:
 *    Trimmed input if valid
 *    Otherwise return DEF_SYSTEM
 */
function filter_ipaddress($value) {
  $userinput = trim($value);

  if (preg_match(REGEX_IPCIDR, $userinput) > 0) {          //Check if valid IP/CIDR
    return $userinput;
  }

  else {
    if (filter_var($userinput, FILTER_VALIDATE_IP)) {      //Or single IPv4 / IPv6
      return $userinput;
    }
  }

  return DEF_SYSTEM;
}

/********************************************************************
 *  Get DNS Search
 *    Returns formatted SQL query for searching dns_request from dnsqueries table based on various search inputs
 *    *domain* = domain anywhere
 *    *domain = ends with domain
 *    domain* = begins with domain
 *    * = all grouped domains
 *    domain = domain anywhere
 *
 *  Params:
 *    Users search
 *  Return:
 *    SQL Search string for DNS Request
 */
function get_dnssearch($domainsearch) {
  $sqlsearch = '';
  $domain = '';

  $domain = preg_replace('/\*/', '', $domainsearch);

  if (preg_match('/^\*[\w\d\.\-_]+\*$/', $domainsearch) > 0) {
    $sqlsearch = "AND dns_request LIKE '%{$domain}%' ";
    //echo '1';
  }
  elseif (preg_match('/^\*[\w\d\.\-_]+\.[\w\d\-]+$/', $domainsearch) > 0) {
    $sqlsearch = "AND dns_request LIKE '%{$domain}' ";
    //echo '2';
  }
  elseif (preg_match('/^[\w\d\.\-_]+\*$/', $domainsearch) > 0) {
    $sqlsearch = "AND dns_request LIKE '{$domain}%' ";
    //echo '3';
  }
  elseif (preg_match('/^[\w\d\.\-_]+\.[\w\d\-]+$/', $domainsearch) > 0) {
    $sqlsearch = "AND dns_request = '{$domain}' ";
    //echo '4';
  }
  elseif (preg_match('/^\*$/', $domainsearch) > 0) {          //* Only = all grouped requests
    $sqlsearch = "AND dns_request LIKE '*%' ";
    //echo '5';
  }
  else {
    $sqlsearch = "AND dns_request LIKE '%{$domain}%' ";
    //echo '6';
  }


  return $sqlsearch;
}


/********************************************************************
 *  Get IP Search
 *    Returns formatted SQL query for searching sys (IP) from dnsqueries table based on various search inputs
 *
 *  Params:
 *    User search string
 *  Return:
 *    SQL Search string
 */
function get_ipsearch($ipsearch) {
  $sqlsearch = '';
  $ipstart = 0;
  $ipend = 0;

  if (preg_match(REGEX_IPCIDR, $ipsearch) > 0) {
    list($ipstart, $ipend) = cidr($ipsearch);
    $sqlsearch = " AND INET_ATON(sys) BETWEEN INET_ATON('$ipstart') AND INET_ATON('$ipend') ";
  }
  else {
    $sqlsearch = " AND sys = '$ipsearch' ";
  }
  return $sqlsearch;
}


/********************************************************************
 *  Draw Filter Toolbar
 *    Populates filter bar with Search Boxes, Search Button
 *    Reset form is dealt with by queries.js function resetQueriesForm()
 *    Show current value first in <select>, and then read through respective array to output values
 *  Params:
 *    None
 *  Return:
 *    None
 */
function draw_filter_toolbar() {
  global $sysiplist, $filter, $page, $searchbox, $sort, $sysip, $groupby, $datetime, $datetime_text;
  global $FILTERLIST, $TIMELIST;

  $line = '';

  echo '<form method="get">'.PHP_EOL;
  echo '<div class="filter-toolbar queries-filter-toolbar">'.PHP_EOL;

  echo '<input type="hidden" name="page" value="'.$page.'">'.PHP_EOL;
  echo '<input type="hidden" name="sort" value="'.$sort.'">'.PHP_EOL;
  echo '<input type="hidden" name="groupby" value="'.$groupby.'">'.PHP_EOL;
  echo '<input type="hidden" name="datetime" id="dateTime" value="'.$datetime.'">'.PHP_EOL;

  //Column Headers
  echo '<div><h3>Domain</h3></div>'.PHP_EOL;
  echo '<div><h3>IP</h3></div>'.PHP_EOL;
  echo '<div><h3>Time</h3></div>'.PHP_EOL;
  echo '<div><h3><span class="mobile-hide">Request </span>Type</h3></div>'.PHP_EOL;
  echo '<div></div>'.PHP_EOL;

  echo '<div><input type="text" name="searchbox" id="filtersearch" value="'.$searchbox.'" placeholder="site.com"></div>'.PHP_EOL;

  //Start Group 2 - IP
  if ($sysip == DEF_SYSTEM) {
    echo '<div><input type="text" name="sysip" id="filtersys" placeholder="192.168.0.1/24"></div>'.PHP_EOL;
  }
  else {
    echo '<div><input type="text" name="sysip" id="filtersys" value="'.$sysip.'" placeholder="192.168.0.1/24"></div>'.PHP_EOL;
  }
  //End Group 2 - IP



  //Start Group 3
  echo '<div id="timepicker-dropdown" tabindex="0">'.PHP_EOL;
  echo '<input type="text" id="timepicker-text" value="'.$datetime_text.'">'.PHP_EOL;
  echo '<div id="timepicker-group">'.PHP_EOL;              //Start timepicker-group

  echo '<div class="timepicker-item" tabindex="0">'.PHP_EOL;
  echo '<h3>Presets</h3>'.PHP_EOL;
  echo '<div class="timepicker-grid timepicker-grid-half">'.PHP_EOL;

  //Column headers
  echo '<div><h4>Relative</h4></div>'.PHP_EOL;
  echo '<div><h4>Or</h4></div>'.PHP_EOL;

  //Dates
  echo '<ul>'.PHP_EOL;
  echo '<li onclick="selectTime(this, \'PT15M\')">15 Minutes</li>'.PHP_EOL;
  echo '<li onclick="selectTime(this, \'PT30M\')">30 Minutes</li>'.PHP_EOL;
  echo '<li onclick="selectTime(this, \'PT1H\')">1 Hour</li>'.PHP_EOL;
  echo '<li onclick="selectTime(this, \'PT4H\')">4 Hours</li>'.PHP_EOL;
  echo '<li onclick="selectTime(this, \'PT12H\')">12 Hours</li>'.PHP_EOL;
  echo '<li onclick="selectTime(this, \'P1D\')">1 Day</li>'.PHP_EOL;
  echo '<li onclick="selectTime(this, \'P7D\')">7 Days</li>'.PHP_EOL;
  echo '<li onclick="selectTime(this, \'P30D\')">30 Days</li>'.PHP_EOL;
  echo '</ul>'.PHP_EOL;
  /*echo '<ul>'.PHP_EOL;
  echo '<li onclick="selectTime(this)">Yesterday</li>'.PHP_EOL;
  echo '<li>Item2</li>'.PHP_EOL;
  echo '</ul>'.PHP_EOL;*/

  echo '</div>'.PHP_EOL;                                   //End timepicker-grid
  echo '</div>'.PHP_EOL;                                   //End timepicker-item 1

  echo '<div class="timepicker-item" tabindex="0">'.PHP_EOL;
  echo '<h3>Date</h3>'.PHP_EOL;
  echo '<div class="timepicker-grid timepicker-grid-half">'.PHP_EOL;

  echo '<div>'.PHP_EOL;
  echo '<input type="date" id="timepicker-date-start" value="2020-01-20">'.PHP_EOL;
  echo '</div>'.PHP_EOL;

  echo '<div>'.PHP_EOL;
  echo '<input type="date" id="timepicker-date-end" value="2020-01-20">'.PHP_EOL;
  echo '<button type="button" onclick="selectDate()">Apply</button>'.PHP_EOL;
  echo '</div>'.PHP_EOL;

  echo '</div>'.PHP_EOL;                                   //End timepicker-grid
  echo '</div>'.PHP_EOL;                                   //End timepicker-item 2
  /*echo '<div class="timepicker-item" tabindex="0">'.PHP_EOL;
  echo '<h3>Advanced</h3>'.PHP_EOL;
  echo '</div>'.PHP_EOL;*/
  echo '</div>'.PHP_EOL;                                   //End timepicker-group
  echo '</div>'.PHP_EOL;                                   //End timepicker-dropdown

  echo '<div><select name="filter" id="filtertype" onchange="submit()">';
  echo '<option value="'.$filter.'">'.$FILTERLIST[$filter].'</option>'.PHP_EOL;
  foreach ($FILTERLIST as $key => $line) {
    if ($key != $filter) echo '<option value="'.$key.'">'.$line.'</option>'.PHP_EOL;
  }
  echo '</select></div>'.PHP_EOL;                          //End Filter List

  echo '<div><button type="submit">Search</button>';
  echo '<button type="button" class="button-grey mobile-hide" onclick="resetQueriesForm()">Reset</button></div>';

  echo '</div>'.PHP_EOL;                                   //End Div Group
  echo '</form>'.PHP_EOL;

}


/********************************************************************
 *  Draw Group By Buttons
 *    groupby is a form which contains hidden elements from draw_filter_toolbar
 *    Selection between Domain / Time is made using radio box, which is missing the input box
 *    Radio box labels are styled to look like pag-nav
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function draw_groupby() {
  global $filter, $page, $searchbox, $sort, $sysip, $groupby, $datetime;

  $domainactive = '';
  $timeactive = '';

  $domainactive = ($groupby == 'name') ? 'checked="checked"' : '';
  $timeactive = ($groupby == 'time') ? 'checked="checked"' : '';

  echo '<form method="get">';
  echo '<input type="hidden" name="page" value="'.$page.'">'.PHP_EOL;
  echo '<input type="hidden" name="sort" value="'.$sort.'">'.PHP_EOL;
  echo '<input type="hidden" name="searchbox" value="'.$searchbox.'">'.PHP_EOL;
  echo '<input type="hidden" name="datetime" value="'.$datetime.'">'.PHP_EOL;
  echo '<input type="hidden" name="sys" value="'.$sysip.'">'.PHP_EOL;
  echo '<input type="hidden" name="filter" value="'.$filter.'">'.PHP_EOL;
  echo '<div id="groupby-container">'.PHP_EOL;
  echo '<input type="radio" id="gbtab1" name="groupby" value="name" onchange="submit()" '.$domainactive.'><label for="gbtab1">Domain</label>'.PHP_EOL;
  echo '<input type="radio" id="gbtab2" name="groupby" value="time" onchange="submit()" '.$timeactive.'><label for="gbtab2">Time</label>'.PHP_EOL;
  echo '</div></form>';
}


/********************************************************************
 *  Get DT Duration Text
 *    Converts ISO 8601 Abbrevated Duration to a Human readable form
 *    e.g. P1M2DT15H22M8S = 1 Month 2 Days 15 Hours 22 Minutes 8 Seconds
 *
 *    1. Use REGEX_DTDURATION to get grouped matches
 *    2. Loop through array of matches
 *    3. If match is set, get pluralised value from corresponding dtnames array
 *
 *  Params:
 *    Duration
 *  Return:
 *    None
 */
function get_dtduration_text($duration) {
  $str = '';
  $i = 0;

  $dtnames = array('', 'Year', 'Month', 'Day', 'Hour', 'Minute', 'Second');

  preg_match(REGEX_DTDURATION, $duration, $matches);

  for ($i = 1; $i < 7; $i++) {
    if (isset($matches[$i])) {
      if ($matches[$i] != '') {
        $str .= pluralise((int)substr($matches[$i], 0, -1), $dtnames[$i]);
        $str .= ' ';
      }
    }
  }

  return $str;
}


/********************************************************************
 *  Format Date Time Search
 *    Fills in $datetime_search (SQL search) and $datetime_text (Human readable)
 *     based on the type of search from $datetime
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function format_datetime_search() {
  global $datetime, $datetime_search, $datetime_text;
  $matches = array();
  $SQLFORMAT = 'Y-m-d H:i:s';

  //Duration from ISO 8601
  //Usually last x time, so no end time required
  if (preg_match(REGEX_DTDURATION, $datetime)) {
    $startdate = new DateTime('now');
    $startdate->sub(new DateInterval($datetime));

    $datetime_search = "log_time > '".$startdate->format($SQLFORMAT)."'";
    $datetime_text = 'Last '.get_dtduration_text($datetime);
  }

  //Start Duration
  //(2020-01-26T15:00:00)/(PT1H)
  //Group 1 Date Time
  //Group 2 Range to add
  elseif (preg_match(REGEX_DTSTARTDURATION, $datetime, $matches)) {
    $startdate = new DateTime($matches[1]);
    $enddate = new DateTime($matches[1]);
    $enddate->add(new DateInterval($matches[2]));

    $datetime_search = "log_time > '".$startdate->format($SQLFORMAT)."' AND log_time < '".$enddate->format($SQLFORMAT)."'";

    //Searching for one day, so drop the time
    if ($matches[2] == 'P1D') {
      $datetime_text = $startdate->format('d M').' for '.get_dtduration_text($matches[2]);
    }
    else {
      $datetime_text = $startdate->format('d M H:i').' for '.get_dtduration_text($matches[2]);
    }
  }
  /*

define('REGEX_DTSINGLE', '/^([0-9]{4})\-(1[0-2]|0[1-9])\-(3[01]|0[1-9]|[12][0-9])T(2[0-3]|[01][0-9]):([0-5][0-9]):([0-5][0-9])$/');
define('REGEX_DTRANGE', '/^([0-9]{4})\-(1[0-2]|0[1-9])\-(3[01]|0[1-9]|[12][0-9])T(2[0-3]|[01][0-9]):([0-5][0-9]):([0-5][0-9])\/([0-9]{4})\-(1[0-2]|0[1-9])\-(3[01]|0[1-9]|[12][0-9])T(2[0-3]|[01][0-9]):([0-5][0-9]):([0-5][0-9])$/');

define('REGEX_DTNAMED', '/^(last week midnight|first day of (last|this) (week|month) midnight)\/(now|last week midnight|first day of (last|this) (week|month) midnight)$/');
*/
}

/********************************************************************
 *  Add Filter Vars to SQL Search
 *
 *  Params:
 *    None
 *  Return:
 *    SQL Query string
 */
function add_filterstr() {
  global $datetime, $datetime_search, $searchbox, $filter, $sysip;

  $searchstr = '';

  $searchstr = ' WHERE ';

  $searchstr .= $datetime_search;


  if ($searchbox != '') {
    $searchstr .= get_dnssearch($searchbox);
  }

  if ($sysip != DEF_SYSTEM) {
    //$searchstr .= "AND sys = '$sysip'";
    $searchstr .= get_ipsearch($sysip);
  }
  if ($filter != DEF_FILTER) {
    $searchstr .= " AND dns_result = '$filter'";
  }

  echo $searchstr;                                       //Uncomment to debug sql query
  return $searchstr;
}


/********************************************************************
 *  Search Block Reason
 *    1. Search site.com in blocklist table
 *    2. Search .tld in blocklist table
 *    3. Search for like site.com in blocklist table
 *    4. On fail return ''
 *
 *  Params:
 *    $domain - Domain to search
 *  Return:
 *    blocklist name
 */
function search_blockreason($domain) {
  global $db;
  $res = '';

  preg_match('/[\w\-_]+(\.co|\.com|\.org|\.gov)?\.([\w\-]+)$/', $domain, $matches);

  //Search for site.com
  //Negate whitelist to prevent stupid results
  $result = $db->query("SELECT bl_source FROM blocklist WHERE site = '".$matches[0]."' AND bl_source != 'whitelist'");
  if ($result->num_rows > 0) {
    $res = $result->fetch_row()[0];
  }
  else {
    $result->free();
    //Search for .tld
    $result = $db->query("SELECT bl_source FROM blocklist WHERE site = '.".$matches[2]."' AND bl_source = 'bl_tld'");
    if ($result->num_rows > 0) {
      $res = $result->fetch_row()[0];
    }
    else {
      $result->free();
      //Search for like site.com (possibly prone to misidentifying bl_source)
      $result = $db->query("SELECT bl_source FROM blocklist WHERE site LIKE '%.".$matches[0]."' AND bl_source != 'whitelist'");
      if ($result->num_rows > 0) {
        $res = $result->fetch_row()[0];
      }
    }
  }

  $result->free();
  return $res;
}


/********************************************************************
 *  Format Row
 *    Returns the action, blockreason, event, and severity in an array
 *
 *  Params:
 *    domain, dns_result(allowed, blocked, local)
 *
 *  Return:
 *    Array of variables to be taken using list()
 */
function format_row($domain, $dns_result) {
  global $config, $INVESTIGATE, $INVESTIGATEURL;

  $blocklist = '';
  $blockreason = '';
  $event = '';
  $severity = '1';
  $popupmenu = '';


  $popupmenu = '<div class="dropdown-container"><span class="dropbtn"></span><div class="dropdown">';

  if ($dns_result == 'A') {
    $event = 'allowed1';
    $popupmenu .= '<span onclick="reportSite(\''.$domain.'\', false, true)">Block</span>';
  }
  elseif ($dns_result == 'B') {         //Blocked
    $blocklist = search_blockreason($domain);
    $severity = '2';
      
    if ($blocklist == 'bl_notrack') {        //Show Report icon on NoTrack list
      $blockreason = '<p class="small grey">Blocked by NoTrack list</p>';
      $event = 'tracker2'; //TODO change image
      $popupmenu .= '<span onclick="reportSite(\''.$domain.'\', true, true)">Allow</span>';
    }
    elseif ($blocklist == 'custom') {        //Users blacklist
      $blockreason = '<p class="small grey">Blocked by Custom Black list</p>';
      $event = 'custom2';
      $popupmenu .= '<span onclick="reportSite(\''.$domain.'\', true, false)">Allow</span>';
    }
    elseif ($blocklist != '') {
      $blockreason = '<p class="small grey">Blocked by '.$config->get_blocklistname($blocklist).'</p>';
      $event = $config->get_blocklistevent($blocklist);

      $popupmenu .= '<span onclick="reportSite(\''.$domain.'\', true, false)">Allow</span>';

      if ($event == 'malware') {
        $severity = '3';
      }
      $event .= $severity;

    }
    else {  //No reason is probably IP or Search request
      $blockreason = '<p class="small">Invalid request</p>';
      $event = 'invalid2';
      $popupmenu .= '<span onclick="reportSite(\''.$domain.'\', true, false)">Allow</span>';
    }
  }
  elseif ($dns_result == 'L') {
    $event = 'local1';
  }

  $popupmenu .= '<a href="'.$INVESTIGATEURL.$domain.'">'.$INVESTIGATE.'</a>';
  $popupmenu .= '<a href="'.$config->settings['SearchUrl'].$domain.'" target="_blank">'.$config->settings['Search'].'</a>';
  $popupmenu .= '<a href="https://www.virustotal.com/en/domain/'.$domain.'/information/" target="_blank">VirusTotal</a>';
  $popupmenu .= '</div></div>';                                  //End dropdown-container

  return array($blockreason, $event, $severity, $popupmenu);
}


/********************************************************************
 *  Show Group View
 *    Show results grouped by name
 *
 *  Params:
 *    None
 *  Return:
 *    false when nothing found, true on success
 */
function show_group_view() {
  global $db, $TLDBlockList;
  global $page, $sort, $filter, $sysip, $groupby, $searchbox;

  $i = 0;
  $k = 1;                                                  //Count within ROWSPERPAGE
  $blockreason = '';
  $clipboard = '';                                         //Div for Clipboard
  $event = '';                                             //Image event
  $popupmenu = '';                                         //Div for popup menu
  $severity = 1;
  $query = '';
  $dns_request = '';
  $site_cell = '';

  $sortlink = "?page=$page&amp;".buildlink();
  $paginationlink = buildlink()."&amp;sort=$sort";

  $query = "SELECT sys, dns_request, dns_result, COUNT(*) AS count FROM dnslog".add_filterstr()." GROUP BY dns_request ORDER BY count $sort";

  if(!$result = $db->query($query)){
    echo '<h4><img src=./svg/emoji_sad.svg>Error running query</h4>'.PHP_EOL;
    echo 'show_group_view: '.$db->error;
    echo '</div>'.PHP_EOL;
    die();
  }

  if ($result->num_rows == 0) {                            //Leave if nothing found
    $result->free();
    echo '<h4><img src=./svg/emoji_sad.svg>No results found</h4>'.PHP_EOL;
    return false;
  }

  //Page needs to be reduced by one to account for array position starting at zero vs human readable starting page at one
  if ((($page-1) * ROWSPERPAGE) > $result->num_rows) {     //Prevent page being greater than number of rows
    $page = 1;
  }

  if ($page > 1) {                                         //Move seek point if currrent page is greater than one
    $result->data_seek(($page - 1) * ROWSPERPAGE);
  }
  $i = (($page - 1) * ROWSPERPAGE) + 1;                    //Friendly table position

  echo '<div class="table-toolbar">'.PHP_EOL;              //Start table-toolbar
  pagination($result->num_rows, $paginationlink);
  draw_groupby();
  echo '</div>'.PHP_EOL;                                   //End table-toolbar

  echo '<table id="query-group-table">'.PHP_EOL;

  echo '<tr><th>&nbsp;</th><th>#</th><th>Domain</th><th>Requests<a class="primarydark" href="'.$sortlink.'&amp;sort=DESC">&#x25BE;</a><a class="primarydark" href="'.$sortlink.'&amp;sort=ASC">&#x25B4;</a></th><th></th></tr>'.PHP_EOL;

  while($row = $result->fetch_assoc()) {                   //Read each row of results
    $dns_request = $row['dns_request'];
    list($blockreason, $event, $severity, $popupmenu) = format_row($dns_request, $row['dns_result']);

    //Create clipboard image and text
    $clipboard = '<div class="icon-clipboard" onclick="setClipboard(\''.$dns_request.'\')" title="Copy domain">&nbsp;</div>';

    //Contents of domain cell
    $domain_cell = '<a href="./investigate.php?site='.$dns_request.'" target="_blank">'.$dns_request.'</a>'.$clipboard.$blockreason;

    //Output table row
    echo "<tr><td><img src=\"./svg/events/{$event}.svg\" alt=\"\"></td><td>{$i}</td><td>{$domain_cell}</td><td>{$row['count']}</td><td>{$popupmenu}</td></tr>".PHP_EOL;
    $blockreason = '';

    $i++;
    $k++;
    if ($k > ROWSPERPAGE) break;
  }

  echo '</table>'.PHP_EOL;
  echo '<br>'.PHP_EOL;
  pagination($result->num_rows, $paginationlink);

  $result->free();

  return true;
}


/********************************************************************
 *  Show Time View
 *    Show results in Time order
 *
 *  Params:
 *    None
 *  Return:
 *    false when nothing found, true on success
 */
function show_time_view() {
  global $db, $TLDBlockList;
  global $page, $sort, $filter, $sysip, $groupby, $searchbox;

  $i = 0;
  $k = 1;                                                  //Count within ROWSPERPAGE
  $blockreason = '';
  $clipboard = '';                                         //Div for Clipboard
  $event = '';                                             //Image event
  $popupmenu = '';                                         //Div for popup menu
  $severity = 1;
  $query = '';
  $dns_request = '';
  $domain_cell = '';

  $sortlink = "?page=$page&amp;".buildlink();
  $paginationlink = buildlink()."&amp;sort=$sort";

  $query = "SELECT *, DATE_FORMAT(log_time, '%Y-%m-%d %H:%i:%s') AS formatted_time FROM dnslog ".add_filterstr(). " ORDER BY UNIX_TIMESTAMP(log_time) $sort";

  if(!$result = $db->query($query)){
    echo '<h4><img src=./svg/emoji_sad.svg>Error running query</h4>'.PHP_EOL;
    echo 'show_time_view: '.$db->error;
    echo '</div>'.PHP_EOL;
    die();
  }

  if ($result->num_rows == 0) {                            //Leave if nothing found
    $result->free();
    echo '<h4><img src=./svg/emoji_sad.svg>No results found</h4>'.PHP_EOL;
    return false;
  }

  //Page needs to be reduced by one to account for array position starting at zero vs human readable starting page at one
  if ((($page-1) * ROWSPERPAGE) > $result->num_rows) {     //Prevent page being greater than number of rows
    $page = 1;
  }

  if ($page > 1) {                                         //Move seek point if currrent page is greater than one
    $result->data_seek(($page - 1) * ROWSPERPAGE);
  }
  $i = (($page - 1) * ROWSPERPAGE) + 1;                    //Friendly table position

  echo '<div class="table-toolbar">'.PHP_EOL;              //Start table-toolbar
  pagination($result->num_rows, $paginationlink);
  draw_groupby();
  echo '</div>'.PHP_EOL;                                   //End table-toolbar

  echo '<table id="query-time-table">'.PHP_EOL;
  echo '<tr><th>&nbsp</th><th>Time<a class="primarydark" href="'.$sortlink.'&amp;sort=DESC">&#x25BE;</a><a class="primarydark" href="'.$sortlink.'&amp;sort=ASC">&#x25B4;</a></th><th>System</th><th>Domain</th><th></th></tr>'.PHP_EOL;

  while($row = $result->fetch_assoc()) {         //Read each row of results
    $dns_request = $row['dns_request'];
    list($blockreason, $event, $severity, $popupmenu) = format_row($dns_request, $row['dns_result']);

    //Create clipboard image and text
    $clipboard = '<div class="icon-clipboard" onclick="setClipboard(\''.$dns_request.'\')" title="Copy domain">&nbsp;</div>';

    //Contents of domain cell with more specific url for investigate
    $domain_cell = "<a href=\"./investigate.php?datetime={$row['log_time']}&amp;site={$dns_request}&amp;sys={$row['sys']}\" target=\"_blank\">{$dns_request}</a>{$clipboard}{$blockreason}";

    //Output table row
    echo "<tr><td><img src=\"./svg/events/{$event}.svg\" alt=\"\"><td>{$row['formatted_time']}</td><td>{$row['sys']}</td><td>{$domain_cell}</td><td>{$popupmenu}</td></tr>".PHP_EOL;
    $blockreason = '';

    $k++;
    if ($k > ROWSPERPAGE) break;
  }

  echo '</table>'.PHP_EOL;
  echo '<br>'.PHP_EOL;
  pagination($result->num_rows,  $paginationlink);

  $result->free();
  return true;
}

//Main---------------------------------------------------------------
$db = new mysqli(SERVERNAME, USERNAME, PASSWORD, DBNAME);

if (isset($_GET['page'])) {
  $page = filter_integer($_GET['page'], 1, PHP_INT_MAX, 1);
}

if (isset($_GET['filter'])) {
  if (array_key_exists($_GET['filter'], $FILTERLIST)) $filter = $_GET['filter'];
}

if (isset($_GET['sort'])) {
  if ($_GET['sort'] == 'ASC') $sort = 'ASC';
}

if (isset($_GET['sysip'])) {                               //sysip uses custom function to verify valid search
  $sysip = filter_ipaddress($_GET['sysip']);
}

if (isset($_GET['groupby'])) {
  if (array_key_exists($_GET['groupby'], $GROUPLIST)) $groupby = $_GET['groupby'];
}


if (isset($_GET['searchbox'])) {                           //searchbox uses preg_replace to remove invalid characters
  $searchbox = preg_replace(REGEX_URLSEARCH, '', $_GET['searchbox']);
}

if (isset($_GET['datetime'])) {
  if ((preg_match(REGEX_DTDURATION, $_GET['datetime'])) ||
      (preg_match(REGEX_DTSINGLE, $_GET['datetime'])) ||
      (preg_match(REGEX_DTRANGE, $_GET['datetime'])) ||
      (preg_match(REGEX_DTSTARTDURATION, $_GET['datetime'])) ||
      (preg_match(REGEX_DTNAMED, $_GET['datetime']))) {
    $datetime = $_GET['datetime'];
  }
}

format_datetime_search();

if ($config->settings['whoisapi'] == '') {                 //Setup Investigate / Whois for popupmenu
  $INVESTIGATE = $config->settings['WhoIs'];
  $INVESTIGATEURL = $config->settings['WhoIsUrl'];
}
else {
  $INVESTIGATE = 'Investigate';
  $INVESTIGATEURL = './investigate.php?site=';
}

echo '<div class="sys-group">'.PHP_EOL;                    //Start Div Group
draw_filter_toolbar();                                     //Draw filter-toolbar
if ($groupby == 'time') {
  show_time_view();
}
elseif ($groupby == 'name') {
  show_group_view();
}

echo '</div>'.PHP_EOL;                                     //End Div Group
draw_copymsg();

$db->close();
?>
</div>

<div id="scrollup" class="button-scroll" onclick="scrollToTop()"><img src="./svg/arrow-up.svg" alt="up"></div>
<div id="scrolldown" class="button-scroll" onclick="scrollToBottom()"><img src="./svg/arrow-down.svg" alt="down"></div>

<div id="queries-box">
<h2 id="sitename">site</h2>
<span id="reportmsg">something</span>
<form action="./investigate.php" method="get" target="_blank">
<span id="searchitem"></span>
<span id="invitem"></span>
</form>
<form action="./config/customblocklist.php" method="POST" target="_blank">
<input type="hidden" name="v" id="reportv" value="none">
<input type="hidden" name="action" id="reportaction" value="none">
<input type="hidden" name="status" value="add">
<input type="hidden" name="comment" value="">
<span id="reportitem1"></span>
<span id="reportitem2"></span>
</form>
<form name="reportform" action="https://quidsup.net/notrack/report.php" method="post" target="_blank">
<input type="hidden" name="site" id="siterep" value="none">
<span id="reportitem3"><input type="submit" value="Report">&nbsp;<input type="text" name="comment" class="textbox-small" placeholder="Optional comment"></span>
</form>

<br>
<div class="centered"><button class="button-grey" onclick="hideQueriesBox()">Cancel</button></div>
<div class="close-button" onclick="hideQueriesBox()"><img src="./svg/button_close.svg" onmouseover="this.src='./svg/button_close_over.svg'" onmouseout="this.src='./svg/button_close.svg'" alt="close"></div>
</div>
<script>
const SEARCHNAME = <?php echo json_encode($config->settings['Search'])?>;
const SEARCHURL = <?php echo json_encode($config->settings['SearchUrl'])?>;
const WHOISNAME = <?php echo json_encode($config->settings['WhoIs'])?>;
const WHOISURL = <?php echo json_encode($config->settings['WhoIsUrl'])?>;
const WHOISAPI = <?php echo ($config->settings['whoisapi'] == '') ? 0 : 1;?>;
</script>
</body>
</html>
