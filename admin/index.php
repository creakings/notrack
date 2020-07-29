<?php
require('./include/global-vars.php');
require('./include/global-functions.php');
require('./include/mysqlidb.php');
require('./include/config.php');
require('./include/menu.php');

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
  <meta name="viewport" content="width=device-width, initial-scale=0.9">
  <title>NoTrack Admin</title>
</head>

<body>
<?php
/************************************************
*Constants                                      *
************************************************/
$CHARTCOLOURS = array('#00c2c8', '#b1244a', '#93a0ff');

/************************************************
*Global Variables                               *
************************************************/
$dbwrapper = new MySqliDb;

$day_allowed = 0;
$day_blocked = 0;
$allowed_queries = array();
$blocked_queries = array();
$chart_labels = array();
$link_labels = array();

/********************************************************************
 *  Block List Box
 *    1. Check if notrack.sh is currently running
 *    2. If it is, show message that blocklists are processing
 *    3. Otherwise show the blocklist count
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function home_blocklist() {
  global $dbwrapper;

  $rows = 0;

  exec('pgrep notrack', $pids);
  if(empty($pids)) {
    $rows = $dbwrapper->count_blocklists();
    echo '<a class="home-nav-item" href="./config/blocklists.php"><span><h2>Block List</h2>'.number_format(floatval($rows)).'<br>Domains</span><div class="icon-box"><img src="./svg/home_trackers.svg" alt=""></div></a>'.PHP_EOL;
  }
  else {
    echo '<a href="./config/blocklists.php"><span><h2>Block List</h2>Processing</span><div class="icon-box"><img src="./svg/home_trackers.svg" alt=""></div></a>'.PHP_EOL;
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
 *    1. Query dnslog table for Total, Blocked results
 *    2. Calculate allowed queries
 *    3. Show icon if no DNS queries have been made
 *    4. Otherwise draw a piechart of the results
 *  Params:
 *    None
 *  Return:
 *    None
 */
function home_queries() {
  global $CHARTCOLOURS, $day_allowed, $day_blocked;

  $total = 0;
  $chartdata = array();
  $lables = array('Allowed', 'Blocked');
  
  $total = $day_allowed + $day_blocked;

  $chartdata = array($day_allowed, $day_blocked);


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
  echo '<circle cx="100" cy="100" r="26" stroke="#262626" stroke-width="2" fill="#f7f7f7" />'.PHP_EOL;  //Small overlay circle
  echo '</svg>'.PHP_EOL;
  echo '</div>'.PHP_EOL;                                   //End Pie Chart

  echo '</a>'.PHP_EOL;                                     //End Queries Box
  
  return null;
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
  global $config;

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
    if ($config->status & STATUS_ENABLED) {
      $status_msg = '<h3 class="darkgray">Block List Missing</h3>';
      $date_msg = '<h3 class="darkgray">Unknown</h3>';
      $date_bgcolour = 'home-bgred';
    }
  }

  echo '<div><h2>Last Updated</h2>'.$date_msg.$date_submsg.'</div>'.PHP_EOL;
  /* DEPRECATED
  if ((VERSION != $config->settings['LatestVersion']) && check_version($config->settings['LatestVersion'])) {
    $date_msg = '<h3 class="darkgray">Upgrade</h3>';
    $date_submsg = '<p>New version available: v'.$config->settings['LatestVersion'].'</p>';
    
    echo '<a class="home-bggreen" href="./upgrade.php"><span><h2>Status</h2>'.$date_msg.$date_submsg.'</span></a>'.PHP_EOL;
    //TODO Image for upgrade
  }
  else {
  */
}


/********************************************************************
 *  Count Queries
 *    1. Create chart labels
 *    2. Query time, system, severity for all results from passed 24 hours from dnslog
 *    3. Use SQL rounding to round time to nearest 30 mins
 *    4. Count by 30 min time blocks into associative array
 *    5. Move values from associative array to daily count indexed array
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function count_queries() {
  global $dbwrapper;
  global $allowed_queries, $blocked_queries, $chart_labels, $link_labels;
  global $day_allowed, $day_blocked;
  
  $allowed_arr = array();
  $blocked_arr = array();
  $currenttime = 0;
  $datestr = '';

  $currenttime = intval(time() - (time() % 1800)) + 3600;  //Round current time up to nearest 30 min period

  //Create labels, Allowed array, and Blocked array values
  for ($i = $currenttime - 84600; $i <= $currenttime; $i+=1800) {
    $datestr = date('H:i:00', $i);
    $allowed_arr[$datestr] = 0;
    $blocked_arr[$datestr] = 0;
    $chart_labels[] = date('H:i', $i);
    $link_labels[] = date('Y-m-d\TH:i:00', $i);
  }

  $hourlyvalues = $dbwrapper->queries_count_hourly($currenttime);
  
  if ($hourlyvalues === false) {                           //Leave if nothing found
    return false;
  }

  //Read each row of results totalling up a count per 30 min period depending whether the query was allowed / blocked
  //Also include count per day for the piechart
  foreach ($hourlyvalues as $row) {
    if ($row['severity'] == '1') {
      $allowed_arr[$row['round_time']]++;
      $day_allowed++;
    }
    elseif ($row['severity'] == '2') {
      $blocked_arr[$row['round_time']]++;
      $day_blocked++;
    }
  }

  $allowed_queries = array_values($allowed_arr);
  $blocked_queries = array_values($blocked_arr);
}


/********************************************************************
 *  Show Latest version alert if a newer version of NoTrack is available
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function show_latestversion() {
  global $config;

  if (($config->get_latestversion() != VERSION) && (compare_version($config->get_latestversion()))) {
    echo '<div class="alertupgrade">'.PHP_EOL;
    echo 'There is a newer version available - <a href="./upgrade.php">Update Now</a>'.PHP_EOL;
    echo '</div>';
  }
}

/********************************************************************
 */

draw_topmenu();
draw_sidemenu();
echo '<div id="main">';

count_queries();
load_latestversion();
show_latestversion();

echo '<div class="home-nav-container">';
home_status();
home_blocklist();
home_queries();
home_network();

//home_sitesblocked();

echo '</div>'.PHP_EOL;                                     //End home-nav-container
if ($day_allowed + $day_blocked > 0) {
  linechart($allowed_queries, $blocked_queries, $chart_labels, $link_labels, '/PT1H', 'DNS Queries over past 24 hours');
}

?>
</div>
</body>
</html>
