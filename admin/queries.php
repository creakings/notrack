<?php
require('./include/global-vars.php');
require('./include/global-functions.php');
require('./include/menu.php');

load_config();
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
  <meta name="viewport" content="width=device-width, initial-scale=0.7">
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


/************************************************
*Global Variables                               *
************************************************/
$page = 1;
$filter = DEF_FILTER;
$datetime = '';
$groupby = 'name';
$dtrange = 0;
$searchbox = '';
$searchtime = '1 DAY';
$sort = 'DESC';
$sysip = DEF_SYSTEM;

/************************************************
*Arrays                                         *
************************************************/
$TLDBlockList = array();


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
 *    sysip GET paramater
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
 *
 *  Params:
 *    Users search
 *  Return:
 *    SQL Search string for DNS Request
 */
function get_dnssearch($urlsearch) {
  $sqlsearch = '';
  $url = '';

  $url = preg_replace('/\*/', '', $urlsearch);

  if (preg_match('/^\*[\w\d\.\-_]+\*$/', $urlsearch) > 0) {
    $sqlsearch = "AND dns_request LIKE '%$url%' ";
    //echo '1';
  }
  elseif (preg_match('/^\*[\w\d\.\-_]+\.[\w\d\-]+$/', $urlsearch) > 0) {
    $sqlsearch = "AND dns_request LIKE '%$url' ";
    //echo '2';
  }
  elseif (preg_match('/^[\w\d\.\-_]+\*$/', $urlsearch) > 0) {
    $sqlsearch = "AND dns_request LIKE '$url%' ";
    //echo '3';
  }
  elseif (preg_match('/^[\w\d\.\-_]+\.[\w\d\-]+$/', $urlsearch) > 0) {
    $sqlsearch = "AND dns_request = '$url' ";
    //echo '4';
  }
  elseif (preg_match('/^\*$/', $urlsearch) > 0) {          //* Only = all grouped requests
    $sqlsearch = "AND dns_request LIKE '*%' ";
    //echo '5';
  }
  else {
    $sqlsearch = "AND dns_request LIKE '%$url%' ";
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
 *  Draw Filter Box
 *    Reset form is dealt with by queries.js function resetQueriesForm()
 *    Show current value first in <select>, and then read through respective array to output values
 *  Params:
 *    None
 *  Return:
 *    None
 */
function draw_filterbox() {
  global $sysiplist, $filter, $page, $searchbox, $searchtime, $sort, $sysip, $groupby, $datetime, $dtrange;
  global $FILTERLIST, $TIMELIST;

  $line = '';

  echo '<form method="get">'.PHP_EOL;
  echo '<div id="dnsfilter-container">'.PHP_EOL;                    //Start Div Group

  echo '<input type="hidden" name="page" value="'.$page.'">'.PHP_EOL;
  echo '<input type="hidden" name="sort" value="'.$sort.'">'.PHP_EOL;
  echo '<input type="hidden" name="groupby" value="'.$groupby.'">'.PHP_EOL;
  if ($datetime != '') {
    echo '<input type="hidden" name="datetime" value="'.$datetime.'">'.PHP_EOL;
  }
  if ($dtrange != 0) {
    echo '<input type="hidden" name="dtrange" value="'.$dtrange.'">'.PHP_EOL;
  }

  echo '<div><input type="text" name="searchbox" id="filtersearch" value="'.$searchbox.'" placeholder="site.com"></div>'.PHP_EOL;

  if ($sysip == DEF_SYSTEM) {
    echo '<div><input type="text" name="sysip" id="filtersys" placeholder="192.168.0.1/24"></div>'.PHP_EOL;
  }
  else {
    echo '<div><input type="text" name="sysip" id="filtersys" value="'.$sysip.'" placeholder="192.168.0.1/24"></div>'.PHP_EOL;
  }

  echo '<div><select name="searchtime" id="filtertime" onchange="submit()">';
  echo '<option value="'.$searchtime.'">'.$TIMELIST[$searchtime].'</option>'.PHP_EOL;
  foreach ($TIMELIST as $key => $line) {
    if ($key != $searchtime) echo '<option value="'.$key.'">'.$line.'</option>'.PHP_EOL;
  }
  echo '</select></div>'.PHP_EOL;                          //End Search Time

  echo '<div><select name="filter" id="filtertype" onchange="submit()">';
  echo '<option value="'.$filter.'">'.$FILTERLIST[$filter].'</option>'.PHP_EOL;
  foreach ($FILTERLIST as $key => $line) {
    if ($key != $filter) echo '<option value="'.$key.'">'.$line.'</option>'.PHP_EOL;
  }
  echo '</select></div>'.PHP_EOL;                          //End Filter List

  echo '<input type="submit" value="Search">&nbsp;&nbsp;';
  echo '<button type="button" class="button-grey mobile-hide" onclick="resetQueriesForm()">Reset</button>';

  echo '</div>'.PHP_EOL;                                   //End Div Group
  echo '</form>'.PHP_EOL;

}


/********************************************************************
 *  Draw Group By Buttons
 *    groupby is a form which contains hidden elements from draw_filterbox
 *    Selection between Site / Time is made using radio box, which is missing the input box
 *    Radio box labels are styled to look like pag-nav
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function draw_groupby() {
  global $filter, $page, $searchbox, $searchtime, $sort, $sysip, $groupby, $datetime, $dtrange;

  $domainactive = '';
  $timeactive = '';

  $domainactive = ($groupby == 'name') ? 'checked="checked"' : '';
  $timeactive = ($groupby == 'time') ? 'checked="checked"' : '';

  echo '<form method="get">';
  echo '<input type="hidden" name="page" value="'.$page.'">'.PHP_EOL;
  echo '<input type="hidden" name="sort" value="'.$sort.'">'.PHP_EOL;
  echo '<input type="hidden" name="searchbox" value="'.$searchbox.'">'.PHP_EOL;
  if ($datetime != '') {
    echo '<input type="hidden" name="datetime" value="'.$datetime.'">'.PHP_EOL;
  }
  else {
    echo '<input type="hidden" name="searchtime" value="'.$searchtime.'">'.PHP_EOL;
  }
  if ($dtrange > 0) {
    echo '<input type="hidden" name="dtrange" value="'.$dtrange.'">'.PHP_EOL;
  }
  echo '<input type="hidden" name="sys" value="'.$sysip.'">'.PHP_EOL;
  echo '<input type="hidden" name="filter" value="'.$filter.'">'.PHP_EOL;
  echo '<div id="groupby-container">'.PHP_EOL;
  echo '<input type="radio" id="gbtab1" name="groupby" value="name" onchange="submit()" '.$domainactive.'><label for="gbtab1">Site</label>'.PHP_EOL;
  echo '<input type="radio" id="gbtab2" name="groupby" value="time" onchange="submit()" '.$timeactive.'><label for="gbtab2">Time</label>'.PHP_EOL;
  echo '</div></form>';
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
  global $datetime, $dtrange, $searchbox, $searchtime, $filter, $sysip;

  if (($datetime != '') && ($dtrange == 0)) {
    $searchstr = " WHERE log_time > SUBTIME('$datetime', '00:01:00') AND log_time < ADDTIME('$datetime', '00:03:00')"; //ORDER BY UNIX_TIMESTAMP(log_time)
  }
  elseif (($datetime != '') && ($dtrange > 0)) {
    $datetimerange = $dtrange * 60;
    $searchstr = " WHERE log_time > SUBTIME('$datetime', '00:00:01') AND log_time < ADDTIME('$datetime', $datetimerange)"; //ORDER BY UNIX_TIMESTAMP(log_time)
  }
  else {
    $searchstr = " WHERE log_time >= DATE_SUB(NOW(), INTERVAL $searchtime) ";
  }

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

  //echo $searchstr;                                       //Uncomment to debug sql query
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
 *  Get Block List Name
 *    Returns the name of block list if it exists in the names array
 *
 *  Params:
 *    $bl - bl_name
 *  Return:
 *    Full block list name
 *    Or what it has been named as
 */
function get_blocklistname($bl) {
  global $BLOCKLISTNAMES;

  if (array_key_exists($bl, $BLOCKLISTNAMES)) {
    return $BLOCKLISTNAMES[$bl];
  }

  return $bl;
}


/********************************************************************
 *  Get Block List Event
 *    Returns the name of block list event if it exists in the event array
 *
 *  Params:
 *    $bl - bl_name
 *  Return:
 *    event value
 */
function get_blocklistevent($bl) {
  global $BLOCKLISTEVENT;

  if (array_key_exists($bl, $BLOCKLISTEVENT)) {
    return $BLOCKLISTEVENT[$bl];
  }
  elseif (substr($bl, 0, 6) == 'custom') {                 //Could be a custom_x list
    return 'custom';
  }

  return $bl;                                              //Shouldn't get to here
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
  $action = '';
  $blocklist = '';
  $blockreason = '';
  $event = '';
  $severity = '1';
  
  if ($dns_result == 'A') {
    $action = '<button class="icon-boot button-grey" onclick="reportSite(\''.$domain.'\', false, true)">Block</button>';
    $event = 'allowed1';
  }
  elseif ($dns_result == 'B') {         //Blocked
    $blocklist = search_blockreason($domain);
    $severity = '2';
      
    if ($blocklist == 'bl_notrack') {        //Show Report icon on NoTrack list
      $action = '<button class="icon-tick button-grey" onclick="reportSite(\''.$domain.'\', true, true)">Allow</button>';
      $blockreason = '<p class="small grey">Blocked by NoTrack list</p>';
      $event = 'tracker2'; //TODO change image
    }
    elseif ($blocklist == 'custom') {        //Users blacklist
      $action = '<button class="icon-tick button-grey" onclick="reportSite(\''.$domain.'\', true, false)">Allow</button>';
      $blockreason = '<p class="small grey">Blocked by Custom Black list</p>';
      $event = 'custom2';
    }
    elseif ($blocklist != '') {
      $blockreason = '<p class="small grey">Blocked by '.get_blocklistname($blocklist).'</p>';
      $action = '<button class="icon-tick button-grey" onclick="reportSite(\''.$domain.'\', true, false)">Allow</button>';

      $event = get_blocklistevent($blocklist);

      if ($event == 'malware') {
        $severity = '3';
      }
      $event .= $severity;

    }
    else {  //No reason is probably IP or Search request
      $blockreason = '<p class="small">Invalid request</p>';
      $event = 'invalid2';
    }
  }
  elseif ($dns_result == 'L') {
    $event = 'local1';
  }

  return array($action, $blockreason, $event, $severity);
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
  global $db, $Config, $TLDBlockList;
  global $page, $sort, $filter, $sysip, $groupby, $searchbox, $searchtime;

  $i = 0;
  $k = 1;                                                  //Count within ROWSPERPAGE
  $action = '';
  $blockreason = '';
  $event = '';
  $severity = 1;
  $query = '';
  $domain = '';
  $site_cell = '';

  $sortlink = "?page=$page&amp;searchbox=$searchbox&amp;searchtime=$searchtime&amp;sys=$sysip&amp;filter=$filter&amp;groupby=$groupby&amp;";

  $paginationlink = "&amp;sort=$sort&amp;searchbox=$searchbox&amp;searchtime=$searchtime&amp;sys=$sysip&amp;filter=$filter&amp;groupby=$groupby";

  $query = "SELECT sys, dns_request, dns_result, COUNT(*) AS count FROM dnslog".add_filterstr()." GROUP BY dns_request ORDER BY count $sort";

  if(!$result = $db->query($query)){
    echo '<h4><img src=./svg/emoji_sad.svg>Error running query</h4>'.PHP_EOL;
    echo 'show_group_view: '.$db->error;
    echo '</div>'.PHP_EOL;
    die();
  }

  if ($result->num_rows == 0) {                 //Leave if nothing found
    $result->free();
    echo '<h4><img src=./svg/emoji_sad.svg>No results found</h4>'.PHP_EOL;
    return false;
  }

  if ((($page-1) * ROWSPERPAGE) > $result->num_rows) {
    $page = 1;
  }
  $i = (($page - 1) * ROWSPERPAGE) + 1;
  
  if ($page > 1) {
    $result->data_seek($page * ROWSPERPAGE);
  }

  pagination($result->num_rows, $paginationlink);
  draw_groupby();

  echo '<table id="query-group-table">'.PHP_EOL;

  echo '<tr><th>&nbsp;</th><th>#</th><th>Site</th><th>Action</th><th>Requests<a class="primarydark" href="'.$sortlink.'sort=DESC">&#x25BE;</a><a class="primarydark" href="'.$sortlink.'sort=ASC">&#x25B4;</a></th></tr>'.PHP_EOL;

  while($row = $result->fetch_assoc()) {         //Read each row of results
    $domain = $row['dns_request'];
    list($action, $blockreason, $event, $severity) = format_row($domain, $row['dns_result']);

    //Make entire site cell clickable with link going to Investigate
    $site_cell = '<td class="pointer" onclick="window.open(\'./investigate.php?site='.$domain.'\', \'_blank\')"><a href="./investigate.php?site='.$domain.'" class="black" target="_blank">'.$domain.$blockreason.'</a></td>';

    echo '<tr><td><img src="./svg/events/'.$event.'.svg" alt=""></td><td>'.$i.'</td>'.$site_cell.'<td>'.$action.'</td><td>'.$row['count'].'</td></tr>'.PHP_EOL;
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
  global $db, $Config, $TLDBlockList;
  global $page, $sort, $filter, $sysip, $groupby, $searchbox, $searchtime;

  $i = 0;
  $k = 1;                                                  //Count within ROWSPERPAGE
  $action = '';
  $blockreason = '';
  $event = '';
  $severity = 1;
  $query = '';
  $domain = '';
  $site_cell = '';

  $sortlink = "?page=$page&amp;searchbox=$searchbox&amp;searchtime=$searchtime&amp;sys=$sysip&amp;filter=$filter&amp;groupby=$groupby&amp;";

  $paginationlink = "&amp;sort=$sort&amp;searchbox=$searchbox&amp;searchtime=$searchtime&amp;sys=$sysip&amp;filter=$filter&amp;groupby=$groupby";

  $query = "SELECT *, DATE_FORMAT(log_time, '%Y-%m-%d %H:%i:%s') AS formatted_time FROM dnslog ".add_filterstr(). " ORDER BY UNIX_TIMESTAMP(log_time) $sort";

  if(!$result = $db->query($query)){
    echo '<h4><img src=./svg/emoji_sad.svg>Error running query</h4>'.PHP_EOL;
    echo 'show_time_view: '.$db->error;
    echo '</div>'.PHP_EOL;
    die();
  }

  if ($result->num_rows == 0) {                  //Leave if nothing found
    $result->free();
    echo '<h4><img src=./svg/emoji_sad.svg>No results found</h4>'.PHP_EOL;
    return false;
  }

  if ((($page-1) * ROWSPERPAGE) > $result->num_rows) {
    $page = 1;
  }
  $i = (($page - 1) * ROWSPERPAGE) + 1;
  
  if ($page > 1) {
    $result->data_seek($page * ROWSPERPAGE);
  }
  
  pagination($result->num_rows, $paginationlink);
  draw_groupby();

  echo '<table id="query-time-table">'.PHP_EOL;
  echo '<tr><th>&nbsp</th><th>Time<a class="primarydark" href="'.$sortlink.'sort=DESC">&#x25BE;</a><a class="primarydark" href="'.$sortlink.'sort=ASC">&#x25B4;</a></th><th>System</th><th>Site</th><th>Action</th></tr>'.PHP_EOL;

  while($row = $result->fetch_assoc()) {         //Read each row of results
    $domain = $row['dns_request'];
    list($action, $blockreason, $event, $severity) = format_row($domain, $row['dns_result']);

    //Make entire site cell clickable with link going to Investigate
    //Add in datetime and system into investigate link
    $site_cell = '<td class="pointer" onclick="window.open(\'./investigate.php?datetime='.$row['formatted_time'].'&amp;site='.$domain.'&amp;sys='.$row['sys'].'\', \'_blank\')"><a href="./investigate.php?datetime='.$row['formatted_time'].'&amp;site='.$domain.'&amp;sys='.$row['sys'].'" class="black" target="_blank">'.$domain.$blockreason.'</a></td>';

    echo '<tr><td><img src="./svg/events/'.$event.'.svg" alt=""><td>'.$row['formatted_time'].'</td><td>'.$row['sys'].'</td>'.$site_cell.'<td>'.$action.'</td></tr>'.PHP_EOL;
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

if (isset($_GET['searchtime'])) {
  if (array_key_exists($_GET['searchtime'], $TIMELIST)) $searchtime = $_GET['searchtime'];
}

if (isset($_GET['searchbox'])) {                           //searchbox uses preg_replace to remove invalid characters
  $searchbox = preg_replace(REGEX_URLSEARCH, '', $_GET['searchbox']);
}

if (isset($_GET['datetime'])) {
  if (preg_match(REGEX_DATETIME, $_GET['datetime'])) {
    $datetime = $_GET['datetime'];
  }
}
if (isset($_GET['dtrange'])) {
  $dtrange = filter_integer($_GET['dtrange'], 0, 1440, 0); //1440 = 24 Hours in Minutes
}

draw_filterbox();                                          //Draw filters

echo '<div class="sys-group">'.PHP_EOL;                    //Start Div Group
if ($groupby == 'time') {
  show_time_view();
}
elseif ($groupby == 'name') {
  show_group_view();
}

echo '</div>'.PHP_EOL;                                     //End Div Group
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
const SEARCHNAME = <?php echo json_encode($Config['Search'])?>;
const SEARCHURL = <?php echo json_encode($Config['SearchUrl'])?>;
const WHOISNAME = <?php echo json_encode($Config['WhoIs'])?>;
const WHOISURL = <?php echo json_encode($Config['WhoIsUrl'])?>;
const WHOISAPI = <?php echo ($Config['whoisapi'] == '') ? 0 : 1;?>;
</script>
</body>
</html>
