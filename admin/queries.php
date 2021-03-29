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
DEFINE('DEF_SYSTEM', 'all');

$GROUPLIST = array('name' => 'Site Name', 'time' => 'Time');

$VIEWLIST = array('name', 'time');

$INVESTIGATE = '';
$INVESTIGATEURL = '';

/************************************************
*Global Variables                               *
************************************************/
$page = 1;
$datetime = 'P1D';                                         //Default range = Past 1 Day
$datetime_text = '';
$datetime_search = '';
$groupby = 'name';
$searchbox = '';
$sort = 'DESC';
$sysip = DEF_SYSTEM;
$searchseverity = 0;

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


//Date Time Single Y-M-DTh:m:s
//0000-9999 Years
//Non-Capture Group 10-12 or 0-9 Months
//Non-Capture Group 30-31 or 0 1-9 or 1-2 0-9 Days
//T
//Non-Capture Group 2 0-3 or 0-1 0-9 Hours
//0-5 0-9 Minutes
//0-5 0-9 Seconds
define('REGEX_DTSINGLE', '/^[0-9]{4}\-(?:1[0-2]|0[1-9])\-(?:3[01]|0[1-9]|[12][0-9])T(?:2[0-3]|[01][0-9]):[0-5][0-9]:[0-5][0-9]$/');

//Date Time Range - Two ranges sepeareted by a /
define('REGEX_DTRANGE', '/^([0-9]{4}\-(?:1[0-2]|0[1-9])\-(?:3[01]|0[1-9]|[12][0-9])T(?:2[0-3]|[01][0-9]):[0-5][0-9]:[0-5][0-9])\/([0-9]{4}\-(?:1[0-2]|0[1-9])\-(?:3[01]|0[1-9]|[12][0-9])T(?:2[0-3]|[01][0-9]):[0-5][0-9]:[0-5][0-9])/');

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

//Date Time Named - Two word Duration points that PHP can understand
//Group 1 - From
//Non Capture Group yesterday|last week
// / (split)
define('REGEX_DTFIXED', '/^(yesterday|(?:this|previous) (?:week|month))/');

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
  global $datetime, $groupby, $searchbox, $searchseverity, $sysip;

  $link = "groupby=$groupby";

  $link .= ($datetime != '') ? '&amp;datetime='.rawurlencode($datetime) : '';
  $link .= "&amp;severity={$searchseverity}";
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

  if (preg_match('/^\*[\w\.\-_]+\*$/', $domainsearch) > 0) {
    $sqlsearch = "AND dns_request LIKE '%{$domain}%' ";
    //echo '1';
  }
  elseif (preg_match('/^\*[\w\.\-_]+\.[\w\d\-]+$/', $domainsearch) > 0) {
    $sqlsearch = "AND dns_request LIKE '%{$domain}' ";
    //echo '2';
  }
  elseif (preg_match('/^[\w\.\-_]+\*$/', $domainsearch) > 0) {
    $sqlsearch = "AND dns_request LIKE '{$domain}%' ";
    //echo '3';
  }
  elseif (preg_match('/^[\w\.\-_]+\.[\w\d\-]+$/', $domainsearch) > 0) {
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
 *    Show current value first in <select>, and then read through respective array to output values
 *  Params:
 *    None
 *  Return:
 *    None
 */
function draw_filter_toolbar() {
  global $sysiplist, $page, $searchbox, $searchseverity, $sort, $sysip, $groupby, $datetime, $datetime_text;

  $isactive = '';

  echo '<form method="get">'.PHP_EOL;
  echo '<input type="hidden" name="page" value="'.$page.'">'.PHP_EOL;
  echo '<input type="hidden" name="sort" value="'.$sort.'">'.PHP_EOL;
  echo '<input type="hidden" name="groupby" value="'.$groupby.'">'.PHP_EOL;
  echo '<input type="hidden" name="datetime" id="dateTime" value="'.$datetime.'">'.PHP_EOL;
  echo '<input type="hidden" name="severity" id="severity" value="'.$searchseverity.'">'.PHP_EOL;

  echo '<div class="filter-toolbar queries-filter-toolbar">'.PHP_EOL;

  //Column Headers and submit button
  echo '<div><h3>Domain</h3></div>'.PHP_EOL;
  echo '<div><h3>IP</h3></div>'.PHP_EOL;
  echo '<div><h3>Time</h3></div>'.PHP_EOL;
  echo '<div><h3>Severity</h3></div>'.PHP_EOL;
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
  echo '<div><h4>Fixed</h4></div>'.PHP_EOL;

  //Dates
  echo '<ul>'.PHP_EOL;
  echo '<li onclick="selectTime(this, \'PT15M\')">Last 15 Minutes</li>'.PHP_EOL;
  echo '<li onclick="selectTime(this, \'PT30M\')">Last 30 Minutes</li>'.PHP_EOL;
  echo '<li onclick="selectTime(this, \'PT1H\')">Last 1 Hour</li>'.PHP_EOL;
  echo '<li onclick="selectTime(this, \'PT4H\')">Last 4 Hours</li>'.PHP_EOL;
  echo '<li onclick="selectTime(this, \'PT12H\')">Last 12 Hours</li>'.PHP_EOL;
  echo '<li onclick="selectTime(this, \'P1D\')">Last 1 Day</li>'.PHP_EOL;
  echo '<li onclick="selectTime(this, \'P7D\')">Last 7 Days</li>'.PHP_EOL;
  echo '<li onclick="selectTime(this, \'P30D\')">Last 30 Days</li>'.PHP_EOL;
  echo '</ul>'.PHP_EOL;

  echo '<ul>'.PHP_EOL;
  echo '<li onclick="selectTime(this, \'yesterday\')">Yesterday</li>'.PHP_EOL;
  echo '<li onclick="selectTime(this, \'this week\')">Week-To-Date</li>'.PHP_EOL;
  echo '<li onclick="selectTime(this, \'previous week\')">Previous Week</li>'.PHP_EOL;
  echo '<li onclick="selectTime(this, \'this month\')">Month-To-Date</li>'.PHP_EOL;
  echo '<li onclick="selectTime(this, \'previous month\')">Previous Month</li>'.PHP_EOL;
  echo '</ul>'.PHP_EOL;

  echo '</div>'.PHP_EOL;                                   //End timepicker-grid
  echo '</div>'.PHP_EOL;                                   //End timepicker-item 1

  echo '<div class="timepicker-item" tabindex="0">'.PHP_EOL;
  echo '<h3>Date</h3>'.PHP_EOL;
  echo '<div class="timepicker-grid timepicker-grid-half">'.PHP_EOL;

  //Column headers for Date
  echo '<div><h4>From</h4></div>'.PHP_EOL;
  echo '<div><h4>To</h4></div>'.PHP_EOL;

  echo '<div>'.PHP_EOL;                                    //Start date-start
  echo '<input type="date" id="timepicker-date-start" value="'.date('Y-m-d', strtotime('yesterday')).'">'.PHP_EOL;
  echo '<p class="light">00:00</p>'.PHP_EOL;
  echo '</div>'.PHP_EOL;                                   //End date-start

  echo '<div>'.PHP_EOL;                                    //Start date-end
  echo '<input type="date" id="timepicker-date-end" value="'.date('Y-m-d').'">'.PHP_EOL;
  echo '<p class="light">23:59</p>'.PHP_EOL;
  echo '<button type="button" onclick="selectDate()">Apply</button>'.PHP_EOL;
  echo '</div>'.PHP_EOL;                                   //End date-end

  echo '</div>'.PHP_EOL;                                   //End timepicker-grid
  echo '</div>'.PHP_EOL;                                   //End timepicker-item 2

  //Time & Date
  echo '<div class="timepicker-item" tabindex="0">'.PHP_EOL;
  echo '<h3>Time & Date</h3>'.PHP_EOL;
  echo '<div class="timepicker-grid timepicker-grid-td">'.PHP_EOL;

  //Column headers for Date
  echo '<div><h4>From</h4></div>'.PHP_EOL;
  echo '<div><h4>To</h4></div>'.PHP_EOL;

  echo '<div><input type="date" id="timepicker-tddate-start" value="'.date('Y-m-d', strtotime('yesterday')).'"></div>'.PHP_EOL;
  echo '<div><input type="time" id="timepicker-tdtime-start" value="00:00"></div>'.PHP_EOL;

  echo '<div><input type="date" id="timepicker-tddate-end" value="'.date('Y-m-d').'"></div>'.PHP_EOL;
  echo '<div><input type="time" id="timepicker-tdtime-end" value="00:00"></div>'.PHP_EOL;

  echo '<div><button type="button" onclick="selectTimeDate()">Apply</button></div>'.PHP_EOL;
  echo '</div>'.PHP_EOL;                                   //End timepicker-grid
  echo '</div>'.PHP_EOL;                                   //End timepicker-item 2

  /*TODO echo '<div class="timepicker-item" tabindex="0">'.PHP_EOL;
  echo '<h3>Advanced</h3>'.PHP_EOL;
  echo '</div>'.PHP_EOL;*/
  echo '</div>'.PHP_EOL;                                   //End timepicker-group
  echo '</div>'.PHP_EOL;                                   //End timepicker-dropdown


  //Group 4 - Severity
  echo '<div class="filter-nav-group">'.PHP_EOL;

  $isactive = ($searchseverity & SEVERITY_LOW) ? ' active' : '';
  echo '<span class="filter-nav-button'.$isactive.'" title="Low - Connection Allowed" onclick="toggleNavButton(this, \''.SEVERITY_LOW.'\')"><img src="./svg/filters/severity_low.svg" alt=""></span>'.PHP_EOL;

  $isactive = ($searchseverity & SEVERITY_MED) ? ' active' : '';
  echo '<span class="filter-nav-button'.$isactive.'" title="Medium - Connection Blocked" onclick="toggleNavButton(this, \''.SEVERITY_MED.'\')"><img src="./svg/filters/severity_med.svg" alt=""></span>'.PHP_EOL;

  $isactive = ($searchseverity & SEVERITY_HIGH) ? ' active' : '';
  echo '<span class="filter-nav-button'.$isactive.'" title="High - Malware or Tracker Accessed" onclick="toggleNavButton(this, \''.SEVERITY_HIGH.'\')"><img src="./svg/filters/severity_high.svg" alt=""></span>'.PHP_EOL;
  echo '</div>'.PHP_EOL;                                   //End Group 4 - Severity

  echo '<div><button id="submit-button" type="submit">Search</button></div>'.PHP_EOL;

  //echo '<button type="button" class="button-grey mobile-hide" onclick="resetQueriesForm()">Reset</button></div>';

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
  global $page, $searchbox, $searchseverity, $sort, $sysip, $groupby, $datetime;

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
  echo '<input type="hidden" name="severity" value="'.$searchseverity.'">'.PHP_EOL;
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

  $fixedstart = '';
  $fixedend = '';

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

  elseif (preg_match(REGEX_DTRANGE, $datetime, $matches)) {
    $startdate = new DateTime($matches[1]);
    $enddate = new DateTime($matches[2]);

    if ($startdate->getTimestamp() > $enddate->getTimestamp()) {
      unset($startdate);
      unset($enddate);
      $startdate = new DateTime($matches[2]);
      $enddate = new DateTime($matches[1]);
    }

    $datetime_search = "log_time > '".$startdate->format($SQLFORMAT)."' AND log_time < '".$enddate->format($SQLFORMAT)."'";

    if (($startdate->format('H:i:s') == '00:00:00') && ($enddate->format('H:i:s') == '23:59:59')) {
      $datetime_text = $startdate->format('d M').' to '.$enddate->format('d M');
    }
    else {
      $datetime_text = $startdate->format('d M H:i').' to '.$enddate->format('d M H:i');
    }
  }

  elseif (preg_match(REGEX_DTFIXED, $datetime)) {

    switch($datetime) {
      case 'previous week':
        $fixedstart = 'last week midnight';
        $fixedend = 'this week midnight';
        $datetime_text = 'Previous Week';
        break;
      case 'this week':
        $fixedstart = 'this week midnight';
        $fixedend = 'now';
        $datetime_text = 'Week-To-Date';
        break;
      case 'previous month':
        $fixedstart = 'last month midnight';
        $fixedend = 'this month midnight';
        $datetime_text = 'Previous Month';
        break;
      case 'this month':
        $fixedstart = 'this month midnight';
        $fixedend = 'now';
        $datetime_text = 'Month-To-Date';
        break;
      default:                                             //Default to yesterday
        $fixedstart = 'yesterday midnight';
        $fixedend = 'today midnight';
        $datetime_text = 'Yesterday';
        break;
    }

    $startdate = new DateTime($fixedstart);
    $enddate = new DateTime($fixedend);
    $datetime_search = "log_time > '".$startdate->format($SQLFORMAT)."' AND log_time < '".$enddate->format($SQLFORMAT)."'";
  }


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
  global $datetime, $datetime_search, $searchbox, $searchseverity, $sysip;

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

  //Severity uses Bitwise operators
  switch($searchseverity) {
    case SEVERITY_LOW:
      $searchstr .= " AND severity = '1' ";                //Allowed, Cached, Local
      break;
    case SEVERITY_MED:
      $searchstr .= " AND severity = '2' ";                //Blocked
      break;
    case SEVERITY_HIGH:
      $searchstr .= " AND severity = '3' ";                //Malware, Tracker
      break;
    case SEVERITY_LOW + SEVERITY_MED:
      $searchstr .= " AND severity IN ('1','2') ";         //Allowed, Blocked
      break;
    case SEVERITY_LOW + SEVERITY_HIGH:
      $searchstr .= " AND severity IN ('1','3') ";         //Allowed, Malware, Tracker
      break;
    case SEVERITY_MED + SEVERITY_HIGH:
      $searchstr .= " AND severity IN ('2','3') ";         //Blocked, Malware, Tracker
      break;
  }

  //echo $searchstr;                                       //Uncomment to debug sql query
  return $searchstr;
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
 *   ($bl_name, $event, $popupmenu)
 */
function format_row($dns_request, $severity, $bl_source) {
  global $config, $INVESTIGATE, $INVESTIGATEURL;

  $bl_name = '';
  $event = '';
  $popupmenu = '';

  $popupmenu = '<div class="dropdown-container"><span class="dropbtn"></span><div class="dropdown">';

  if ($severity == 1) {
    $event = "{$bl_source}1";
    if ($bl_source != 'local') {
      $popupmenu .= "<span onclick=\"reportDomain('{$dns_request}', false)\">Report Domain</span>";
      $popupmenu .= "<span onclick=\"blockDomain('{$dns_request}', false)\">Block Domain</span>";
    }
  }
  elseif ($severity == 2) {                                //Blocked
    $event = $config->get_blocklisttype($bl_source).'2';

    if ($bl_source == 'bl_notrack') {                      //Show Report on NoTrack list
      $bl_name = '<p class="small grey">Blocked by NoTrack list</p>';
      $popupmenu .= "<span onclick=\"reportDomain('{$dns_request}', true)\">Report Domain</span>";
      $popupmenu .= "<span onclick=\"blockDomain('{$dns_request}', true)\">Allow Domain</span>";
    }
    elseif ($bl_source == 'invalid') {                     //Other blocklist
      $bl_name = '<p class="small">Invalid request</p>';
      $event = 'invalid2';
    }
    else {
      $bl_name = '<p class="small grey">Blocked by '.$config->get_blocklistname($bl_source).'</p>';
      $popupmenu .= "<span onclick=\"reportDomain('{$dns_request}', true)\">Allow Domain</span>";
    }
  }
  elseif ($severity == 3) {
    if (($bl_source == 'advert') or ($bl_source == 'tracker')) {
      $event = "{$bl_source}3";
    }
    else {
      $event = $config->get_blocklisttype($bl_source).'3';
    }
    $blockreason = '<p class="small grey">'.ucfirst($bl_source).'Accessed</p>';
    $popupmenu .= "<span onclick=\"reportDomain('{$dns_request}', false)\">Report Domain</span>";
    $popupmenu .= "<span onclick=\"blockDomain('{$dns_request}', false)\">Block Domain</span>";
  }

  $popupmenu .= '<a href="'.$INVESTIGATEURL.$dns_request.'">'.$INVESTIGATE.'</a>';
  $popupmenu .= "<a href=\"{$config->search_url}{$dns_request}\" target=\"_blank\">{$config->search_engine}</a>";
  $popupmenu .= '<a href="https://www.virustotal.com/en/domain/'.$dns_request.'/information/" target="_blank">VirusTotal</a>';
  $popupmenu .= '</div></div>';                                  //End dropdown-container

  return array($bl_name, $event, $popupmenu);
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
  global $page, $sort;

  $i = 0;
  $k = 1;                                                  //Count within ROWSPERPAGE
  $bl_name = '';
  $bl_source = '';
  $clipboard = '';                                         //Div for Clipboard
  $event = '';                                             //Image event
  $popupmenu = '';                                         //Div for popup menu
  $severity = 0;
  $query = '';
  $dns_request = '';
  $site_cell = '';

  $sortlink = "?page=$page&amp;".buildlink();
  $paginationlink = buildlink()."&amp;sort=$sort";

  $query = "SELECT sys, dns_request, severity, bl_source, COUNT(*) AS count FROM dnslog".add_filterstr()." GROUP BY dns_request ORDER BY count $sort";

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
    $bl_source = $row['bl_source'];
    $severity = $row['severity'];

    list($bl_name, $event, $popupmenu) = format_row($dns_request, $severity, $bl_source);

    //Create clipboard image and text
    $clipboard = '<div class="icon-clipboard" onclick="setClipboard(\''.$dns_request.'\')" title="Copy domain">&nbsp;</div>';

    //Contents of domain cell
    $domain_cell = '<a href="./investigate.php?subdomain='.$dns_request.'" target="_blank">'.$dns_request.'</a>'.$clipboard.$bl_name;

    //Output table row
    echo "<tr><td><img src=\"./svg/events/{$event}.svg\" alt=\"\"></td><td>{$i}</td><td>{$domain_cell}</td><td>{$row['count']}</td><td>{$popupmenu}</td></tr>".PHP_EOL;


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
  global $page, $sort;

  $i = 0;
  $k = 1;                                                  //Count within ROWSPERPAGE
  $bl_name = '';
  $bl_source = '';
  $clipboard = '';                                         //Div for Clipboard
  $event = '';                                             //Image event
  $popupmenu = '';                                         //Div for popup menu
  $severity = 0;
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
    $bl_source = $row['bl_source'];
    $severity = $row['severity'];

    list($bl_name, $event, $popupmenu) = format_row($dns_request, $severity, $bl_source);

    //Create clipboard image and text
    $clipboard = '<div class="icon-clipboard" onclick="setClipboard(\''.$dns_request.'\')" title="Copy domain">&nbsp;</div>';

    //Contents of domain cell with more specific url for investigate
    $domain_cell = "<a href=\"./investigate.php?datetime={$row['log_time']}&amp;subdomain={$dns_request}&amp;sys={$row['sys']}\" target=\"_blank\">{$dns_request}</a>{$clipboard}{$bl_name}";

    //Output table row
    echo "<tr><td><img src=\"./svg/events/{$event}.svg\" alt=\"\"><td>{$row['formatted_time']}</td><td>{$row['sys']}</td><td>{$domain_cell}</td><td>{$popupmenu}</td></tr>".PHP_EOL;

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

if (isset($_GET['severity'])) {                            //Severity
  $searchseverity = filter_integer($_GET['severity'], 0, 7, 1);
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
  //die($_GET['datetime']);
  if ((preg_match(REGEX_DTDURATION, $_GET['datetime'])) ||
      (preg_match(REGEX_DTRANGE, $_GET['datetime'])) ||
      (preg_match(REGEX_DTSTARTDURATION, $_GET['datetime'])) ||
      (preg_match(REGEX_DTFIXED, $_GET['datetime']))) {
    $datetime = $_GET['datetime'];
  }
}

format_datetime_search();

if ($config->whois_api == '') {                            //Setup Investigate / Whois for popupmenu
  $INVESTIGATE = $config->whois_provider;
  $INVESTIGATEURL = $config->whois_url;
}
else {
  $INVESTIGATE = 'Investigate';
  $INVESTIGATEURL = './investigate.php?subdomain=';
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

<div id="report-dialog">
<h2>Report Domain</h2>
<h3 id="reportTitle">domain</h3>
<form action="https://quidsup.net/notrack/report.php" method="post" target="_blank">
<input type="hidden" name="site" id="reportInput" value="none">
<div><input type="text" name="comment" class="textbox-small" placeholder="Optional comment"></div>
<menu>
<button type="submit">Confirm</button>
<button type="button" class="button-grey" onclick="hideDialogs()">Cancel</button>
</menu>
</form>
</div>

<div id="queries-dialog">
<h2>Block Domain</h2>
<form action="./config/customblocklist.php" method="POST" target="_blank">
<input type="hidden" name="v" id="reportv" value="none">
<input type="hidden" name="action" id="blockAction" value="none">
<input type="hidden" name="status" value="add">
<input type="hidden" name="comment" value="">
<div id="blockitem1"></div>
<div id="blockitem2"></div>
</form>
<menu>
<button type="button" class="button-grey" onclick="hideDialogs()">Cancel</button>
</menu>
</div>

<div id="fade" onclick="hideDialogs()"></div>

</body>
</html>
