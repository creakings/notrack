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
                    'allowed' => 'Allowed Only',
                    'blocked' => 'Blocked Only',
                    'local' => 'Local Only');

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
$CommonSites = array();                          //Merge Common sites list with Users Suppress list


/********************************************************************
 *  Add Date Vars to SQL Search
 *
 *  Params:
 *    None
 *  Return:
 *    SQL Query string
 */
/*function add_datestr() {
  global $filter, $sys, $datestart, $dateend;
  
  if ( == 'dnslog') return '';
  
  $searchstr = ' WHERE ';
  if (($filter != DEF_FILTER) || ($sys != DEF_SYSTEM)) $searchstr = ' AND ';
  
  $searchstr .= 'log_time BETWEEN \''.$datestart.'\' AND \''.$dateend.' 23:59\'';
  
  return $searchstr;
}
*/

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
    $searchstr .= "AND dns_request LIKE '%$searchbox%' ";
  }
  return $searchstr;
  
  if (($filter == DEF_FILTER) && ($sys == DEF_SYSTEM)) {   //Nothing to add
    return '';
  }
  
  if ($sys != DEF_SYSTEM) {
    $searchstr .= "sys = '$sys'";
  }
  if ($filter != DEF_FILTER) {
    if ($sys != DEF_SYSTEM) {
      $searchstr .= " AND dns_result=";
    }    
    else {
      $searchstr .= " dns_result=";
    }    
    
    switch($filter) {
      case 'allowed':
        $searchstr .= "'a'";
        break;
      case 'blocked':
        $searchstr .= "'b'";
        break;
      case 'local':
        $searchstr .= "'l'";
        break;
    }
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
  
  echo '<div class="sys-group">'.PHP_EOL;
  echo '<h5>DNS Queries</h5>'.PHP_EOL;
  echo '<form method="post">'.PHP_EOL;
  
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
  
  echo '</div>'.PHP_EOL;
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
  global $db, $page, $sort, $filter, $sys, $groupby, $Config, $TLDBlockList;
  
  
  $i = (($page - 1) * ROWSPERPAGE) + 1;
  $rows = 0;
  $row_class = '';
  $action = '';
  $blockreason = '';
  $query = '';
  $site_cell = '';
  
  $linkstr = "&amp;filter=$filter&amp;sys=$sys"; //Default link string
  
  /*if ( == 'historic') {                 //Add date search to link in histroic view
    $linkstr .= "&amp;datestart=$datestart&amp;dateend=$dateend";
  }*/
  
  $rows = count_rows_save("SELECT COUNT(DISTINCT dns_request) FROM dnslog ".add_filterstr());
  $query = "SELECT sys, dns_request, dns_result, COUNT(*) AS count FROM dnslog" .add_filterstr()." GROUP BY dns_request ORDER BY count $sort LIMIT ".ROWSPERPAGE." OFFSET ".(($page-1) * ROWSPERPAGE);
  
  if(!$result = $db->query($query)){
    die('There was an error running the query'.$db->error);
  } 
  
  if ($result->num_rows == 0) {                 //Leave if nothing found
    $result->free();
    echo 'Nothing Found';
    return false;
  }
  
  if ((($page-1) * ROWSPERPAGE) > $rows) $page = 1;
  
  echo '<div class="sys-group">'.PHP_EOL;
  pagination($rows, 'view='.$groupby.'&amp;sort='.strtolower($sort).$linkstr);  
  
  echo '<table id="query-group-table">'.PHP_EOL;
  
  echo '<tr><th>#</th><th>Site</th><th>Action</th><th>Requests<a class="blue" href="?page='.$page.'&amp;view='.$groupby.'&amp;sort=desc'.$linkstr.'">&#x25BE;</a><a class="blue" href="?page='.$page.'&amp;view='.$groupby.'&amp;sort=asc'.$linkstr.'">&#x25B4;</a></th></tr>'.PHP_EOL;  
  
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
  pagination($rows, 'view='.$groupby.'&amp;sort='.strtolower($sort).$linkstr);
  
  echo '</div>'.PHP_EOL;
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
  global $db, $page, $sort, $filter, $sys, $groupby, $datestart, $dateend, $Config, $TLDBlockList;
  global $datestart, $dateend;
  
  $rows = 0;
  $row_class = '';
  $pagination_link = '';
  $query = '';
  $action = '';
  $blockreason = '';
  $investigate = '';
  $site_cell = '';
  
  if ($groupby == 'livetime') {
    $rows = count_rows_save('SELECT COUNT(*) FROM dnslog'.add_filterstr());
    if ((($page-1) * ROWSPERPAGE) > $rows) {
      $page = 1;    
    }
    $query = "SELECT *, DATE_FORMAT(log_time, '%H:%i:%s') AS formatted_time FROM dnslog ".add_filterstr(). " ORDER BY UNIX_TIMESTAMP(log_time) $sort LIMIT ".ROWSPERPAGE." OFFSET ".(($page-1) * ROWSPERPAGE);
    $pagination_link = "view=$groupby&amp;sort=".strtolower($sort)."&amp;filter=$filter&amp;sys=$sys";
  }
  else {    
    $rows = count_rows_save("SELECT COUNT(*) FROM dnslog".add_filterstr());
    if ((($page-1) * ROWSPERPAGE) > $rows) {
      $page = 1;
    }
    $query = "SELECT *, DATE_FORMAT(log_time, '%Y-%m-%d %H:%i:%s') AS formatted_time FROM dnslog".add_filterstr(). " ORDER BY UNIX_TIMESTAMP(log_time) $sort LIMIT ".ROWSPERPAGE." OFFSET ".(($page-1) * ROWSPERPAGE);
    $pagination_link = "view=$groupby&amp;sort=".strtolower($sort)."&amp;filter=$filter&amp;sys=$sys&amp;datestart=$datestart&amp;dateend=$dateend";
  }
  
  if(!$result = $db->query($query)){
    die('There was an error running the query'.$db->error);
  }
  
  if ($result->num_rows == 0) {                  //Leave if nothing found
    $result->free();
    echo "Nothing found for the selected dates";
    return false;
  }
  
  echo '<div class="sys-group">'.PHP_EOL;
  pagination($rows, $pagination_link);
    
  echo '<table id="query-time-table">'.PHP_EOL;
  echo '<tr><th>Time<a class="blue" href="?'.htmlspecialchars('page='.$page.'&view='.$groupby.'&sort=desc&filter='.$filter.'&sys='.$sys.'&datestart='.$datestart.'&dateend='.$dateend).'">&#x25BE;</a><a class="blue" href="?'.htmlspecialchars('page='.$page.'&view='.$groupby.'&sort=asc&filter='.$filter.'&sys='.$sys.'&datestart='.$datestart.'&dateend='.$dateend).'">&#x25B4;</a></th><th>System</th><th>Site</th><th>Action</th></tr>'.PHP_EOL;
  
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
  pagination($rows,  $pagination_link);
  echo '</div>'.PHP_EOL;
  
  $result->free();
  return true;
}

//Main---------------------------------------------------------------

$db = new mysqli(SERVERNAME, USERNAME, PASSWORD, DBNAME);

search_systems();                                //Need to find out systems on live table

if (isset($_GET['page'])) {
  $page = filter_integer($_GET['page'], 1, PHP_INT_MAX, 1);
}

if (isset($_POST['filter'])) {
  if (array_key_exists($_POST['filter'], $FILTERLIST)) $filter = $_POST['filter'];
}

if (isset($_GET['sort'])) {
  if ($_GET['sort'] == 'asc') $sort = 'ASC';
}

if (isset($_POST['sys'])) {
  if (in_array($_POST['sys'], $syslist)) $sys = $_POST['sys'];
}

if (isset($_POST['groupby'])) {
  if (array_key_exists($_POST['groupby'], $GROUPLIST)) $groupby = $_POST['groupby'];
}

if (isset($_POST['searchtime'])) {
  if (array_key_exists($_POST['searchtime'], $TIMELIST)) $searchtime = $_POST['searchtime'];
}

if (isset($_POST['searchbox'])) {  
  $searchbox = $_POST['searchbox']; //TODO Sanitize
}

/*if (isset($_GET['datestart'])) {                 //Filter for yyyy-mm-dd
  if (preg_match(REGEX_DATE, $_GET['datestart']) > 0) $datestart = $_GET['datestart'];
}
if (isset($_GET['dateend'])) {                   //Filter for yyyy-mm-dd
  if (preg_match(REGEX_DATE, $_GET['dateend']) > 0) $dateend = $_GET['dateend'];  
}*/

/*if ($== 'historic') {                   //Check to see if dates are valid
  if (strtotime($dateend) > time()) $dateend = DEF_EDATE;
  if (strtotime($datestart) > strtotime($dateend)) {
    $datestart = DEF_SDATE;
    $dateend = DEF_EDATE;
  }
}
*/

draw_filterbox();                                //Draw filters

if ($groupby == 'time') {
  show_time_view();
}
elseif ($groupby == 'name') {
  show_group_view();
}


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
