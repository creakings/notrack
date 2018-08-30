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
  <link rel="icon" type="image/png" href="./favicon.png">
  <script src="./include/menu.js"></script>
  <script src="./include/queries.js"></script>
  <title>NoTrack - DNS Queries</title>
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
DEFINE('DEF_SDATE', date("Y-m-d", time() - 172800));  //Start Date of Historic -2d
DEFINE('DEF_EDATE', date("Y-m-d", time() - 86400));   //End Date of Historic   -1d

$FILTERLIST = array('all' => 'All Requests',
                    'a' => 'Allowed Only',
                    'b' => 'Blocked Only',
                    'l' => 'Local Only');

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
$groupby = 'name';
$searchbox = '';
$searchtime = '1 DAY';
$sort = 'DESC';
$sys = DEF_SYSTEM;

$datestart = DEF_SDATE;
$dateend = DEF_EDATE;


/************************************************
*Arrays                                         *
************************************************/
$syslist = array();
$TLDBlockList = array();


/********************************************************************
 *  Add Filter Vars to SQL Search
 *
 *  Params:
 *    None
 *  Return:
 *    SQL Query string
 */
function add_filterstr() {
  global $searchbox, $searchtime, $filter, $sys;
  
  $searchstr = " WHERE ";
  
  $searchstr .= "log_time >= DATE_SUB(NOW(), INTERVAL $searchtime) ";
  
  if ($searchbox != '') {
    $searchstr .= "AND dns_request LIKE '$searchbox%' ";
  }

  if ($sys != DEF_SYSTEM) {
    $searchstr .= "AND sys = '$sys'";
  }
  if ($filter != DEF_FILTER) {
    $searchstr .= " AND dns_result = '$filter'";
  }

  return $searchstr;
}


/********************************************************************
 *  Count rows in table and save result to memcache
 *  
 *  1. Attempt to load value from Memcache
 *  2. Check if same query is being run
 *  3. If that fails then run query
 *
 *  Params:
 *    Query String
 *  Return:
 *    Number of Rows
 */
function count_rows_save($query) {
  global $db, $mem;
  
  $rows = 0;
  
  if ($mem->get('rows')) {                       //Does rows exist in memcache?
    if ($query == $mem->get('oldquery')) {       //Is this query same as old query?
      $rows = $mem->get('rows');                 //Use stored value      
      return $rows;
    }
  }
  
  if(!$result = $db->query($query)){
    die('There was an error running the query '.$db->error);
  }
  
  $rows = $result->fetch_row()[0];               //Extract value from array
  $result->free();    
  $mem->set('oldquery', $query, 0, 600);         //Save for 10 Mins
      
  return $rows;
}


/********************************************************************
 *  Draw Filter Box
 *  
 *  Params:
 *    None
 *  Return:
 *    None
 */
function draw_filterbox() {
  global $FILTERLIST, $syslist, $filter, $page, $searchbox, $searchtime, $sort, $sys, $groupby;
  global $GROUPLIST, $TIMELIST;
  global $datestart, $dateend;
  
  $line = '';
  
  echo '<div class="sys-group">'.PHP_EOL;                    //Start Div Group
  echo '<h5>DNS Queries</h5>'.PHP_EOL;
  echo '<form method="get">'.PHP_EOL;
  echo '<input type="hidden" name="page" value="'.$page.'">'.PHP_EOL;
  echo '<input type="hidden" name="sort" value="'.$sort.'">'.PHP_EOL;
  
  echo '<div class="row">'.PHP_EOL;                        //Start Row TODO mobile view
  echo '<div class="dnsqueries-filterlarge">'.PHP_EOL;     //Start Search Box
  if ($searchbox != '') {
    echo '<input type="text" class="full" name="searchbox" value="'.$searchbox.'">'.PHP_EOL;
  }
  else {
    echo '<input type="text" class="full" name="searchbox" placeholder="search">'.PHP_EOL;
  }
  echo '</div>'.PHP_EOL;                                   //End Search Box
  
  echo '<div class="dnsqueries-filtermedium">'.PHP_EOL;    //Start Search Time
  echo '<span class="filter">Time:</span><select name="searchtime" onchange="submit()">';
  echo '<option value="'.$searchtime.'">'.$TIMELIST[$searchtime].'</option>'.PHP_EOL;
  foreach ($TIMELIST as $key => $line) {
    if ($key != $searchtime) echo '<option value="'.$key.'">'.$line.'</option>'.PHP_EOL;
  }
  echo '</select></div>'.PHP_EOL;                          //End Search Time
  
  echo '<div class="dnsqueries-filtermedium">'.PHP_EOL;    //Start System List
  echo '<span class="filter">System:</span><select name="sys" onchange="submit()">';
    
  if ($sys == DEF_SYSTEM) {
    echo '<option value="all">All</option>'.PHP_EOL;
  }
  else {
    echo '<option value="1">'.$sys.'</option>'.PHP_EOL;
    echo '<option value="all">All</option>'.PHP_EOL;
  }
  foreach ($syslist as $line) {
    if ($line != $sys) echo '<option value="'.$line.'">'.$line.'</option>'.PHP_EOL;
  }
  echo '</select></div>'.PHP_EOL;                          //End System List
  
  echo '<div class="dnsqueries-filtermedium">'.PHP_EOL;    //Start Filter List
  echo '<span class="filter">Filter:</span><select name="filter" onchange="submit()">';
  echo '<option value="'.$filter.'">'.$FILTERLIST[$filter].'</option>'.PHP_EOL;
  foreach ($FILTERLIST as $key => $line) {
    if ($key != $filter) echo '<option value="'.$key.'">'.$line.'</option>'.PHP_EOL;
  }
  echo '</select></div>'.PHP_EOL;                          //End Filter List
  
  echo '<div class="dnsqueries-filtermedium">'.PHP_EOL;    //Start Group List
  echo '<span class="groupby">Group By:</span><select name="groupby" onchange="submit()">';
  echo '<option value="'.$groupby.'">'.$GROUPLIST[$groupby].'</option>'.PHP_EOL;
  foreach ($GROUPLIST as $key => $line) {
    if ($key != $groupby) echo '<option value="'.$key.'">'.$line.'</option>'.PHP_EOL;
  }
  echo '</select></div>'.PHP_EOL;                          //End Group List
  
  echo '</div>'.PHP_EOL;                                   //End Row
  echo '</form>'.PHP_EOL;
  
  echo '</div>'.PHP_EOL;                                   //End Div Group

}


/********************************************************************
 *  Get Block List Name
 *    Returns the name of block list if it exists in the names array
 *  Params:
 *    $bl - bl_name
 *  Return:
 *    Full block list name
 */

function get_blocklistname($bl) {
  global $BLOCKLISTNAMES;
  
  if (array_key_exists($bl, $BLOCKLISTNAMES)) {
    return $BLOCKLISTNAMES[$bl];
  }
  
  return $bl;
}
/********************************************************************
 *  Search Block Reason
 *    1. Search $site in bl_source for Blocklist name
 *    2. Use regex match to extract (site).(tld)
 *    3. Search site.tld in bl_source
 *    4. On fail search for .tld in bl_source
 *    5. On fail return ''
 *
 *  Params:
 *    $site - Site to search
 *  Return:
 *    blocklist name
 */
function search_blockreason($site) {
  global $db;
  
  $result = $db->query('SELECT bl_source site FROM blocklist WHERE site = \''.$site.'\'');
  if ($result->num_rows > 0) {
    return $result->fetch_row()[0];
  }
  
    
  //Try to find LIKE site ending with site.tld
  if (preg_match('/([\w\d\-\_]+)\.([\w\d\-\_]+)$/', $site,  $matches) > 0) {
    $result = $db->query('SELECT bl_source site FROM blocklist WHERE site LIKE \'%'.$matches[1].'.'.$matches[2].'\'');

    if ($result->num_rows > 0) {
      return $result->fetch_row()[0];
    }    
    else {                                      //On fail try for site = .tld
      $result = $db->query('SELECT bl_source site FROM blocklist WHERE site = \'.'.$matches[2].'\'');
      if ($result->num_rows > 0) {
        return $result->fetch_row()[0];
      }
    }
  }
  
  return '';                                     //Don't know at this point    
}

//Need to ammend for historic view TODO
/********************************************************************
 *  Search Systems
 * TODO limit results for past day or search time
 *  1. Find unique sys values in table
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function search_systems() {
  global $db, $mem, $syslist;
  
  $syslist = $mem->get('syslist');
  
  if (empty($syslist)) {
    if (! $result = $db->query("SELECT DISTINCT sys FROM dnslog ORDER BY sys")) {
      die('There was an error running the query'.$db->error);
    }
    while($row = $result->fetch_assoc()) {       //Read each row of results
      $syslist[] = $row['sys'];                  //Add row value to $syslist
    }
    $result->free();
    $mem->set('syslist', $syslist, 0, 600);      //Save for 10 Mins
  }    
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
  global $page, $sort, $filter, $sys, $groupby, $searchbox, $searchtime;
  
  $i = 0;
  $rows = 0;
  $row_class = '';
  $action = '';
  $blockreason = '';
  $query = '';
  $site_cell = '';
  
  $sortlink = "?page=$page&amp;searchbox=$searchbox&amp;searchtime=$searchtime&amp;sys=$sys&amp;filter=$filter&amp;groupby=$groupby&amp;";
  
  $paginationlink = "&amp;sort=$sort&amp;searchbox=$searchbox&amp;searchtime=$searchtime&amp;sys=$sys&amp;filter=$filter&amp;groupby=$groupby";

  $rows = count_rows_save("SELECT COUNT(DISTINCT dns_request) FROM dnslog ".add_filterstr());
  
  if ((($page-1) * ROWSPERPAGE) > $rows) {
    $page = 1;
  }
  $i = (($page - 1) * ROWSPERPAGE) + 1;
  
  $query = "SELECT sys, dns_request, dns_result, COUNT(*) AS count FROM dnslog" .add_filterstr()." GROUP BY dns_request ORDER BY count $sort LIMIT ".ROWSPERPAGE." OFFSET ".(($page-1) * ROWSPERPAGE);
  
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
  
  if ((($page-1) * ROWSPERPAGE) > $rows) $page = 1;
  
  pagination($rows, $paginationlink);
  
  echo '<table id="query-group-table">'.PHP_EOL;
  
  echo '<tr><th>#</th><th>Site</th><th>Action</th><th>Requests<a class="blue" href="'.$sortlink.'sort=DESC">&#x25BE;</a><a class="blue" href="'.$sortlink.'sort=ASC">&#x25B4;</a></th></tr>'.PHP_EOL;
  
  while($row = $result->fetch_assoc()) {         //Read each row of results
    $action = '<a target="_blank" href="'.$Config['SearchUrl'].$row['dns_request'].'"><img class="icon" src="./images/search_icon.png" alt="G" title="Search"></a>&nbsp;<a target="_blank" href="'.$Config['WhoIsUrl'].$row['dns_request'].'"><img class="icon" src="./images/whois_icon.png" alt="W" title="Whois"></a>&nbsp;';
    
    if ($row['dns_result'] == 'A') {             //Row colouring
      $row_class='';
      $action .= '<span class="pointer"><img src="./images/report_icon.png" alt="Rep" title="Report Site" onclick="reportSite(\''.$row['dns_request'].'\', false, true)"></span>';
    }
    elseif ($row['dns_result'] == 'B') {         //Blocked
      $row_class = ' class="blocked"';
      $blockreason = search_blockreason($row['dns_request']);
      if ($blockreason == 'bl_notrack') {        //Show Report icon on NoTrack list
        $action .= '<span class="pointer"><img src="./images/report_icon.png" alt="Rep" title="Report Site" onclick="reportSite(\''.$row['dns_request'].'\', true, true)"></span>';
        $blockreason = '<p class="small">Blocked by NoTrack list</p>';
      }
      elseif ($blockreason == 'custom') {        //Users blacklist, show report icon
        $action .= '<span class="pointer"><img src="./images/report_icon.png" alt="Rep" title="Report Site" onclick="reportSite(\''.$row['dns_request'].'\', true, true)"></span>';
        $blockreason = '<p class="small">Blocked by Black list</p>';
      }
      elseif ($blockreason == '') {              //No reason is probably IP or Search request
        $row_class = ' class="invalid"';
        $blockreason = '<p class="small">Invalid request</p>';
      }
      else {
        $blockreason = '<p class="small">Blocked by '.get_blocklistname($blockreason).'</p>';
        $action .= '<span class="pointer"><img src="./images/report_icon.png" alt="Rep" title="Report Site" onclick="reportSite(\''.$row['dns_request'].'\', true, false)"></span>';
      }
    }
    elseif ($row['dns_result'] == 'L') {
      $row_class = ' class="local"';
      $action = '&nbsp;';
    }
    
    //Make entire site cell clickable with link going to Investigate
    $site_cell = '<td class="pointer" onclick="window.open(\'./investigate.php?site='.$row['dns_request'].'\', \'_blank\')"><a href="./investigate.php?site='.$row['dns_request'].'" class="black" target="_blank">'.$row['dns_request'].$blockreason.'</a></td>';
        
    echo '<tr'.$row_class.'><td>'.$i.'</td>'.$site_cell.'<td>'.$action.'</td><td>'.$row['count'].'</td></tr>'.PHP_EOL;
    $blockreason = '';
    $i++;
  }
  
  echo '</table>'.PHP_EOL;
  echo '<br>'.PHP_EOL;
  pagination($rows, $paginationlink);
  
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
  global $page, $sort, $filter, $sys, $groupby, $searchbox, $searchtime;
  
  $rows = 0;
  $row_class = '';
  
  $query = '';
  $action = '';
  $blockreason = '';
  $investigate = '';
  $site_cell = '';
  
  $sortlink = "?page=$page&amp;searchbox=$searchbox&amp;searchtime=$searchtime&amp;sys=$sys&amp;filter=$filter&amp;groupby=$groupby&amp;";
  
  $paginationlink = "&amp;sort=$sort&amp;searchbox=$searchbox&amp;searchtime=$searchtime&amp;sys=$sys&amp;filter=$filter&amp;groupby=$groupby";
  
  $rows = count_rows_save('SELECT COUNT(*) FROM dnslog'.add_filterstr());
  if ((($page-1) * ROWSPERPAGE) > $rows) {
    $page = 1;
  }
  
  $query = "SELECT *, DATE_FORMAT(log_time, '%Y-%m-%d %H:%i:%s') AS formatted_time FROM dnslog ".add_filterstr(). " ORDER BY UNIX_TIMESTAMP(log_time) $sort LIMIT ".ROWSPERPAGE." OFFSET ".(($page-1) * ROWSPERPAGE);
    
  
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
  
  pagination($rows, $paginationlink);
    
  echo '<table id="query-time-table">'.PHP_EOL;
  echo '<tr><th>Time<a class="blue" href="'.$sortlink.'sort=DESC">&#x25BE;</a><a class="blue" href="'.$sortlink.'sort=ASC">&#x25B4;</a></th><th>System</th><th>Site</th><th>Action</th></tr>'.PHP_EOL;
  
  while($row = $result->fetch_assoc()) {         //Read each row of results
    $action = '<a target="_blank" href="'.$Config['SearchUrl'].$row['dns_request'].'"><img class="icon" src="./images/search_icon.png" alt="G" title="Search"></a>&nbsp;<a target="_blank" href="'.$Config['WhoIsUrl'].$row['dns_request'].'"><img class="icon" src="./images/whois_icon.png" alt="W" title="Whois"></a>&nbsp;';
    
    if ($row['dns_result'] == 'A') {             //Allowed
      $row_class='';
      $action .= '<span class="pointer"><img src="./images/report_icon.png" alt="Rep" title="Report Site" onclick="reportSite(\''.$row['dns_request'].'\', false, true)"></span>';
    }
    elseif ($row['dns_result'] == 'B') {         //Blocked
      $row_class = ' class="blocked"';
      $blockreason = search_blockreason($row['dns_request']);
      if ($blockreason == 'bl_notrack') {        //Show Report icon on NoTrack list
        $action .= '<span class="pointer"><img src="./images/report_icon.png" alt="Rep" title="Report Site" onclick="reportSite(\''.$row['dns_request'].'\', true, true)"></span>';
        $blockreason = '<p class="small">Blocked by NoTrack list</p>';
      }
      elseif ($blockreason == 'custom') {        //Users blacklist, show report icon
        $action .= '<span class="pointer"><img src="./images/report_icon.png" alt="Rep" title="Report Site" onclick="reportSite(\''.$row['dns_request'].'\', true, true)"></span>';
        $blockreason = '<p class="small">Blocked by Black list</p>';
      }
      elseif ($blockreason == '') {              //No reason is probably IP or Search request
        $row_class = ' class="invalid"';
        $blockreason = '<p class="small">Invalid request</p>';
      }
      else {
        $blockreason = '<p class="small">Blocked by '.get_blocklistname($blockreason).'</p>';
        $action .= '<span class="pointer"><img src="./images/report_icon.png" alt="Rep" title="Report Site" onclick="reportSite(\''.$row['dns_request'].'\', true, false)"></span>';
      }    
    }
    elseif ($row['dns_result'] == 'L') {         //Local
      $row_class = ' class="local"';
      $action = '&nbsp;';
    }
    
        
    //Make entire site cell clickable with link going to Investigate
    //Add in datetime and system into investigate link
    $site_cell = '<td class="pointer" onclick="window.open(\'./investigate.php?datetime='.$row['formatted_time'].'&amp;site='.$row['dns_request'].'&amp;sys='.$row['sys'].'\', \'_blank\')"><a href="./investigate.php?datetime='.$row['formatted_time'].'&amp;site='.$row['dns_request'].'&amp;sys='.$row['sys'].'" class="black" target="_blank">'.$row['dns_request'].$blockreason.'</a></td>';
        
    echo '<tr'.$row_class.'><td>'.$row['formatted_time'].'</td><td>'.$row['sys'].'</td>'.$site_cell.'<td>'.$action.$investigate.'</td></tr>'.PHP_EOL;
    $blockreason = '';
  }
  
  echo '</table>'.PHP_EOL;
  echo '<br>'.PHP_EOL;
  pagination($rows,  $paginationlink);  
  
  $result->free();
  return true;
}

//Main---------------------------------------------------------------

$db = new mysqli(SERVERNAME, USERNAME, PASSWORD, DBNAME);

search_systems();                                          //Need to find out systems on live table

if (isset($_GET['page'])) {
  $page = filter_integer($_GET['page'], 1, PHP_INT_MAX, 1);
}

if (isset($_GET['filter'])) {
  if (array_key_exists($_GET['filter'], $FILTERLIST)) $filter = $_GET['filter'];
}

if (isset($_GET['sort'])) {
  if ($_GET['sort'] == 'ASC') $sort = 'ASC';
}

if (isset($_GET['sys'])) {
  if (in_array($_GET['sys'], $syslist)) $sys = $_GET['sys'];
}

if (isset($_GET['groupby'])) {
  if (array_key_exists($_GET['groupby'], $GROUPLIST)) $groupby = $_GET['groupby'];
}

if (isset($_GET['searchtime'])) {
  if (array_key_exists($_GET['searchtime'], $TIMELIST)) $searchtime = $_GET['searchtime'];
}

if (isset($_GET['searchbox'])) {
  $searchbox = $_GET['searchbox']; //TODO Sanitize
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

<div id="scrollup" class="button-scroll" onclick="ScrollToTop()"><img src="./svg/arrow-up.svg" alt="up"></div>
<div id="scrolldown" class="button-scroll" onclick="ScrollToBottom()"><img src="./svg/arrow-down.svg" alt="down"></div>

<div id="stats-box">
<div class="dialog-bar">Report</div>
<span id="sitename">site</span>
<span id="statsmsg">something</span>
<span id="statsblock1"><a class="button-blue" href="#">Block Whole</a> Block whole domain</span>
<span id="statsblock2"><a class="button-blue" href="#">Block Sub</a> Block just the subdomain</span>
<form name="reportform" action="https://quidsup.net/notrack/report.php" method="post" target="_blank">
<input type="hidden" name="site" id="siterep" value="none">
<span id="statsreport"><input type="submit" class="button-blue" value="Report">&nbsp;<input type="text" name="comment" class="textbox-small" placeholder="Optional comment"></span>
</form>

<br>
<div class="centered"><h6 class="button-grey" onclick="HideStatsBox()">Cancel</h6></div>
<div class="close-button" onclick="HideStatsBox()"><img src="./svg/button_close.svg" onmouseover="this.src='./svg/button_close_over.svg'" onmouseout="this.src='./svg/button_close.svg'" alt="close"></div>
</div>

</body>
</html>
