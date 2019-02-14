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
  <link href="./css/home.css" rel="stylesheet" type="text/css">
  <link href="./css/chart.css" rel="stylesheet" type="text/css">
  <link rel="icon" type="image/png" href="./favicon.png">
  <script src="./include/menu.js"></script>
  <title>NoTrack Admin</title>
</head>

<body>
<?php
draw_topmenu();
draw_sidemenu();

/************************************************
*Constants                                      *
************************************************/
define('QRY_BLOCKLIST', 'SELECT COUNT(*) FROM blocklist');

$CHARTCOLOURS = array('#008CD1', '#B1244A', '#00AA00');

/************************************************
*Global Variables                               *
************************************************/
$db = new mysqli(SERVERNAME, USERNAME, PASSWORD, DBNAME);

$day_allowed = 0;
$day_blocked = 0;
$day_local = 0;
$allowed_queries = array();
$blocked_queries = array();
$chart_labels = array();

/********************************************************************
 *  Block List Box
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function home_blocklist() {
  $rows = 0;

  exec('pgrep notrack', $pids);
  if(empty($pids)) {
    $rows = count_rows(QRY_BLOCKLIST); 
    echo '<a class="home-nav-item" href="./config.php?v=full"><span><h2>Block List</h2>'.number_format(floatval($rows)).'<br>Domains</span><div class="icon-box"><img src="./svg/home_trackers.svg" alt=""></div></a>'.PHP_EOL;
  }
  else {
    echo '<a href="./config.php?v=full"><span><h2>Block List</h2>Processing</span><div class="icon-box"><img src="./svg/home_trackers.svg" alt=""></div></a>'.PHP_EOL;
  }
}


/********************************************************************
 *  DHCP Network Box
 *    Read number of lines from dnsmasq.leases using wc
 *    Split into columns using cut with delimiter of space
 *    Take 1st field
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function home_network() {
  if (file_exists('/var/lib/misc/dnsmasq.leases')) {       //DHCP Active
    echo '<a href="./dhcp.php"><span><h2>Network</h2>'.number_format(floatval(exec('wc -l /var/lib/misc/dnsmasq.leases | cut -d\  -f 1'))).'<br>Systems</span><div class="icon-box"><img src="./svg/home_dhcp.svg" alt=""></div></a>'.PHP_EOL;
  }
  else {                                                   //DHCP Disabled
    echo '<a class="home-bgred" href="./dhcp.php"><span><h2>Network</h2>DHCP Disabled</span><div class="icon-box"><img class="full" src="./svg/home_dhcp.svg" alt=""></div></a>'.PHP_EOL;
  }
}


/********************************************************************
 *  DNS Queries Box
 *    1. Query dnslog table for Total, Blocked, and Local results
 *    2. Calculate allowed queries
 *    3. Show icon if no DNS queries have been made
 *    4. Otherwise draw a piechart of the results
 *  Params:
 *    None
 *  Return:
 *    None
 */
function home_queries() {
  global $CHARTCOLOURS, $day_allowed, $day_blocked, $day_local;

  $total = 0;
  $chartdata = array();
  $lables = array('Allowed', 'Blocked', 'Local');
  
  $total = $day_allowed + $day_blocked + $day_local;

  if ($day_local == 0) {                                   //Build array of chartdata, we may not need to include $local
    $chartdata = array($day_allowed, $day_blocked);
  }
  else {                                                   //Local is necessary
    $chartdata = array($day_allowed, $day_blocked, $day_local);
  }

  //Start Drawing Queries Box
  echo '<a href="./queries.php"><span><h2>DNS Queries</h2>' . number_format(floatval($total)) . '<br>Today</span>'.PHP_EOL;

  if ($total == 0) {                                       //Alternative if no DNS queries have been made
    echo '<div class="icon-box"><img src="./svg/home_queries.svg" alt=""></div>'.PHP_EOL;
    echo '</a>'.PHP_EOL;
    return null;
  }
  
  //1 or more queries made, draw a piechart
  echo '<div class="chart-box">'.PHP_EOL;                  //Start Pie Chart
  echo '<svg width="100%" height="90%" viewbox="0 0 200 200">'.PHP_EOL;
  piechart($lables, $chartdata, 100, 100, 98, $CHARTCOLOURS);
  echo '<circle cx="100" cy="100" r="26" stroke="#202020" stroke-width="2" fill="#eaf1f1" />'.PHP_EOL;  //Small overlay circle
  echo '</svg>'.PHP_EOL;
  echo '</div>'.PHP_EOL;                                   //End Pie Chart

  echo '</a>'.PHP_EOL;                               //End Queries Box
}


/********************************************************************
 *  Status Box
 *    Look at the file modified time for NoTrack file under /etc/dnsmasq
 *    Override with Upgrade message if upgrade is available
 *  Params:
 *    None
 *  Return:
 *    None
 */
function home_status() {
  global $Config;

  $currenttime = time();
  $date_bgcolour = '';
  $date_msg = '';
  $date_submsg = '<p>Block list is in date</p>';
  $filemtime = 0;
  
  if (file_exists(NOTRACK_LIST)) {               //Does the notrack.list file exist?
    $filemtime = filemtime(NOTRACK_LIST);        //Get last modified time
    if ($filemtime > $currenttime - 86400) $date_msg = '<h3 class="green">Today</h3>';
    elseif ($filemtime > $currenttime - 172800) $date_msg = '<h3 class="green">Yesterday</h3>';
    elseif ($filemtime > $currenttime - 259200) $date_msg = '<h3 class="green">3 Days ago</h3>';
    elseif ($filemtime > $currenttime - 345600) $date_msg = '<h3 class="green">4 Days ago</h3>';
    elseif ($filemtime > $currenttime - 432000) {  //5 days onwards is getting stale
      $date_bgcolour = 'home-bgyellow';
      $date_msg = '<h3 class="darkgray">5 Days ago</h3>';
      $date_submsg = '<p>Block list is old</p>';
    }
    elseif ($filemtime > $currenttime - 518400) {
      $date_bgcolour = 'home-bgyellow';
      $date_msg = '<h3 class="darkgray">6 Days ago</h3>';
      $date_submsg = '<p>Block list is old</p>';
    }
    elseif ($filemtime > $currenttime - 1209600) {
      $date_bgcolour = 'home-bgred';
      $date_msg = '<h3 class="darkgray">Last Week</h3>';
      $date_submsg = '<p>Block list is old</p>';
    }
    else {                                       //Beyond 2 weeks is too old
      $date_bgcolour = 'home-bgred';
      $date_msg = '<h3 class="darkgray">'.date('d M', $filemtime).'</h3>';
      $date_submsg = '<h3 class="red">Out of date</h3>';
    }
  }  
  else {
    if ($Config['status'] & STATUS_ENABLED) {
      $status_msg = '<h3 class="darkgray">Block List Missing</h3>';
      $date_msg = '<h3 class="darkgray">Unknown</h3>';
      $date_bgcolour = 'home-bgred';
    }
  }

  if ((VERSION != $Config['LatestVersion']) && check_version($Config['LatestVersion'])) {
    $date_msg = '<h3 class="darkgray">Upgrade</h3>';
    $date_submsg = '<p>New version available: v'.$Config['LatestVersion'].'</p>';
    
    echo '<a class="home-bggreen" href="./upgrade.php"><span><h2>Status</h2>'.$date_msg.$date_submsg.'</span></a>'.PHP_EOL;
    //TODO Image for upgrade
  }
  else {
    echo '<div><h2>Last Updated</h2>'.$date_msg.$date_submsg.'</div>'.PHP_EOL;
  }

  return null;
}


/********************************************************************
 *  Count Queries
 *    1. Query time, system, dns_result for all results from passed 24 hours from dnslog
 *    2. Use SQL rounding to round time to nearest 30 mins
 *    3. Count by 30 min time blocks into associative array
 *    4. Move values from associative array to daily count indexed array
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function count_queries() {
  global $db, $allowed_queries, $blocked_queries, $chart_labels;
  global $day_allowed, $day_blocked, $day_local;
  
  $allowed_arr = array();
  $blocked_arr = array();
  $currenttime = 0;
  $datestr = '';

  $currenttime = intval(time() - (time() % 1800)) + 3600;
  /*if ($currenttime < time()+1800) {
    echo intval($currenttime - time()).'offset<br>';
    $currenttime += 1800;
  }
  else echo intval($currenttime - time())."not used<br>";*/

  $starttime = date('Y-m-d H:00:00', $currenttime - 84600);
  $endtime = date('Y-m-d H:59:59');
  
  $query = "SELECT SEC_TO_TIME((TIME_TO_SEC(log_time) DIV 1800) * 1800) AS round_time, sys, dns_result FROM dnslog WHERE log_time >= '$starttime' AND log_time <= '$endtime'";
  
  for ($i = $currenttime - 84600; $i <= $currenttime; $i+=1800) {
    $datestr = date('H:i:00', $i);
    $allowed_arr[$datestr] = 0;
    $blocked_arr[$datestr] = 0;
    $chart_labels[] = date('H:i', $i);
  }
  //print_r($allowed_arr);
  
  if(!$result = $db->query($query)){
    echo '<h4><img src=./svg/emoji_sad.svg>Error running query</h4>'.PHP_EOL;
    echo 'show_time_view: '.$db->error;
    echo '</div>'.PHP_EOL;
    die();
  }
  
  if ($result->num_rows == 0) {                  //Leave if nothing found
    $result->free();
    //echo '<h4><img src=./svg/emoji_sad.svg>No results found</h4>'.PHP_EOL;
    return false;
  }
  
  while($row = $result->fetch_assoc()) {         //Read each row of results
    if ($row['dns_result'] == 'A') {
      $allowed_arr[$row['round_time']]++;
      $day_allowed++;
    }
    elseif ($row['dns_result'] == 'B') {
      $blocked_arr[$row['round_time']]++;
      $day_blocked++;
    }
    elseif ($row['dns_result'] == 'L') {
      $day_local++;
    }
  }

  $result->free();
  
  $allowed_queries = array_values($allowed_arr);
  $blocked_queries = array_values($blocked_arr);
  
  return null;
}



//Main---------------------------------------------------------------


echo '<div id="main">';
count_queries();
echo '<div class="home-nav-container">';
home_status();
home_blocklist();
home_queries();
home_network();

//home_sitesblocked();

echo '</div>'.PHP_EOL;                                     //End home-nav-container
if ($day_allowed + $day_blocked > 0) {
  linechart($allowed_queries, $blocked_queries, $chart_labels);
}



$db->close();
?>
</div>
</body>
</html>
