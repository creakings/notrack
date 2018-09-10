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
define('QRY_DNSLOG', 'SELECT COUNT(*) FROM dnslog WHERE log_time BETWEEN (CURDATE() - INTERVAL 2 DAY) AND NOW()');
define('QRY_WEBLOG', 'SELECT COUNT(*) FROM weblog WHERE log_time BETWEEN (CURDATE() - INTERVAL 7 DAY) AND NOW()');

$CHARTCOLOURS = array('#008CD1', '#B1244A', '#00AA00');

/************************************************
*Global Variables                               *
************************************************/
$db = new mysqli(SERVERNAME, USERNAME, PASSWORD, DBNAME);


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
    echo '<a href="./config.php?v=full"><div class="home-nav"><h2>Block List</h2><hr><span>'.number_format(floatval($rows)).'<br>Domains</span><div class="icon-box"><img src="./svg/home_trackers.svg" alt=""></div></div></a>'.PHP_EOL;
  }
  else {
    echo '<a href="./config.php?v=full"><div class="home-nav"><h2>Block List</h2><hr><span>Processing</span><div class="icon-box"><img src="./svg/home_trackers.svg" alt=""></div></div></a>'.PHP_EOL;
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
    echo '<a href="./dhcp.php"><div class="home-nav"><h2>Network</h2><hr><span>'.number_format(floatval(exec('wc -l /var/lib/misc/dnsmasq.leases | cut -d\  -f 1'))).'<br>Systems</span><div class="icon-box"><img src="./svg/home_dhcp.svg" alt=""></div></div></a>'.PHP_EOL;
  }
  else {                                                   //DHCP Disabled
    echo '<a href="./dhcp.php"><div class="home-nav"><h2>Network</h2><hr><span>DHCP Disabled</span><div class="icon-box"><img class="full" src="./svg/home_dhcp.svg" alt=""></div></div></a>'.PHP_EOL;
  }  
}


/********************************************************************
 *  DNS Queries Box
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function home_queries() {
  global $CHARTCOLOURS;

  $total = 0;
  $allowed = 0;
  $blocked = 0;
  $local = 0;
  $chartdata = array();
  
  $total = count_rows(QRY_DNSLOG);
  $local = count_rows(QRY_DNSLOG." AND dns_result = 'l'");
  $blocked = count_rows(QRY_DNSLOG." AND dns_result = 'b'");
  $allowed = $total - $blocked - $local;
  
  if ($local == 0) {
    $chartdata = array($allowed, $blocked);
  }
  else {
    $chartdata = array($allowed, $blocked, $local);
  }
  if ($allowed > 0) {
    $allowed = floatval(($allowed/$total)*100);
  }

  if ($blocked > 0) {
    $blocked = floatval(($blocked/$total)*100);
  }  

  echo '<a href="./queries.php"><div class="home-nav"><h2>DNS Queries</h2><hr><span>' . number_format(floatval($total)) . '<br>Today'.PHP_EOL;
  //echo '<span class="mobile-hide">'.PHP_EOL;
  echo '<svg width="20em" height="3em" overflow="visible">'.PHP_EOL;
  echo '<text x="0" y="2em" style="font-family: Arial; font-size: 0.58em; fill:'.$CHARTCOLOURS[0].'">'.number_format($allowed).'% Allowed</text>'.PHP_EOL;
  echo '<text x="6.4em" y="2em" style="font-family: Arial; font-size: 0.58em; fill:'.$CHARTCOLOURS[1].'">'.number_format($blocked).'% Blocked</text>'.PHP_EOL;
  if ($local > 0) {
    $local = floatval(($local/$total)*100);
    echo '<text x="0" y="3.3em" style="font-family: Arial; font-size: 0.58em; fill:'.$CHARTCOLOURS[2].'">'.number_format($local).'% Local</text>'.PHP_EOL;
  }
  echo '</svg>'.PHP_EOL;
  //echo '</span>'.PHP_EOL;
  echo '</span>'.PHP_EOL;
  
  
  echo '<div class="chart-box">'.PHP_EOL;
  echo '<svg width="100%" height="90%" viewbox="0 0 200 200">'.PHP_EOL;
  echo piechart($chartdata, 100, 100, 98, $CHARTCOLOURS);
  echo '<circle cx="100" cy="100" r="30" stroke="#202020" stroke-width="2" fill="#eaf1f1" />'.PHP_EOL;  //Small overlay circle
  echo '</svg>'.PHP_EOL;
  //<img src="./svg/home_queries.svg" srcset="./svg/home_queries.svg" alt="">
  echo '</div></div></a>'.PHP_EOL;
}


/********************************************************************
 *  Status Box
 *    Check $Config for status
 *    Look at the file modified time for NoTrack file under /etc/dnsmasq
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
  $date_submsg = '<h2>Block list is in date</h2>';
  $filemtime = 0;
  $status_bgcolour = '';
  $status_msg = '';
  $status_submsg = '';
  $upgrade_available = false;
  
  if ($Config['status'] & STATUS_PAUSED) {
    $status_msg = '<h3 class="darkgray">Paused</h3>';
    $status_bgcolour = ' home-bgyellow';
    $date_msg = '<h2>---</h2>';
    $date_submsg = '';
  }
  elseif ($Config['status'] & STATUS_DISABLED) {
    $status_msg = '<h3 class="darkgray">Disabled</h3>';
    $status_bgcolour = ' home-bgred';
    $date_msg = '<h2>---</h2>';
    $date_submsg = '';
  }
  else {
    $status_msg = '<h3 class="green">Active</h3>';
    
    //Is an upgrade Needed?
    if ((VERSION != $Config['LatestVersion']) && check_version($Config['LatestVersion'])) {
      $upgrade_available = true;
      $status_bgcolour = ' home-bggreen';
      $status_msg = '<h3 class="darkgray">Upgrade</h3>';
      $status_submsg = '<h2>New version available: v'.$Config['LatestVersion'].'</h2>';  
    }    
  }
  
  if (file_exists(NOTRACK_LIST)) {               //Does the notrack.list file exist?
    $filemtime = filemtime(NOTRACK_LIST);        //Get last modified time
    if ($filemtime > $currenttime - 86400) $date_msg = '<h3 class="green">Today</h3>';
    elseif ($filemtime > $currenttime - 172800) $date_msg = '<h3 class="green">Yesterday</h3>';
    elseif ($filemtime > $currenttime - 259200) $date_msg = '<h3 class="green">3 Days ago</h3>';
    elseif ($filemtime > $currenttime - 345600) $date_msg = '<h3 class="green">4 Days ago</h3>';
    elseif ($filemtime > $currenttime - 432000) {  //5 days onwards is getting stale
      $date_bgcolour = 'home-bgyellow';
      $date_msg = '<h3 class="darkgray">5 Days ago</h3>';
      $date_submsg = '<h2>Block list is old</h2>';
    }
    elseif ($filemtime > $currenttime - 518400) {
      $date_bgcolour = 'home-bgyellow';
      $date_msg = '<h3 class="darkgray">6 Days ago</h3>';
      $date_submsg = '<h2>Block list is old</h2>';
    }
    elseif ($filemtime > $currenttime - 1209600) {
      $date_bgcolour = 'home-bgred';
      $date_msg = '<h3 class="darkgray">Last Week</h3>';
      $date_submsg = '<h2>Block list is old</h2>';
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
      $status_bgcolour = 'home-bgred';
    }
  }

  if ($upgrade_available) {
    echo '<a href="./upgrade.php"><div class="home-nav'.$status_bgcolour.'"><h2>Status</h2><hr><br>'.$status_msg.$status_submsg.'</div></a>'.PHP_EOL;
  }
  else {
    echo '<div class="home-nav'.$status_bgcolour.'"><h2>Status</h2><hr><br>'.$status_msg.'</div>'.PHP_EOL;
  }
  echo '<div class="home-nav"><h2>Last Updated</h2><hr><br>'.$date_msg.$date_submsg.'</div>'.PHP_EOL;
}


/********************************************************************
 *  Sites Blocked Box
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function home_sitesblocked() {
  $rows = 0;
  
  $rows = count_rows(QRY_WEBLOG);

  echo '<a href="./blocked.php"><div class="home-nav"><h2>Sites Blocked</h2><hr><span>'.number_format(floatval($rows)).'<br>This Week</span><div class="icon-box"><img src="./svg/home_blocked.svg" alt=""></div></div></a>'.PHP_EOL;
}


/********************************************************************
 *  Traffic Graph
 *    1. Calculate what Unix time was 23 hours ago
 *    2. Build xlabels using just the hour component of date()
 *    3. Load allowed 'a' results from dnslog table for values per hour using 00 mins to 59 mins of each hour
 *    4. Load blocked 'b' results from dnslog table for values per hour
 *    5. Send data to linechart() function to draw the chart
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function trafficgraph() {
  $allowed_values = array();
  $blocked_values = array();
  $xlabels = array();
  $timestr1 = '';
  $timestr2 = '';

  $starttime = time() - 82800;                             //-23 Hours

  for ($i = 0; $i < 24; $i++) {                            //Loop forward to +23 Hours
    $xlabels[] = date('H:00', $starttime + ($i * 3600));
    $timestr1 = date('Y-m-d H:00:00', $starttime + ($i * 3600));
    $timestr2 = date('Y-m-d H:59:59', $starttime + ($i * 3600));

    $allowed_values[] = count_rows("SELECT COUNT(*) FROM dnslog WHERE dns_result = 'a' AND log_time >= '$timestr1' AND log_time <= '$timestr2'");
    $blocked_values[] = count_rows("SELECT COUNT(*) FROM dnslog WHERE dns_result = 'b' AND log_time >= '$timestr1' AND log_time <= '$timestr2'");
  }

  /*print_r($allowed_values);                              //For debugging
  echo '<br>';
  print_r($blocked_values);*/
  linechart($allowed_values, $blocked_values, $xlabels);   //Draw the line chart
}  


//Main---------------------------------------------------------------
echo '<div id="main">';
echo '<div class="home-nav-container">';

home_status();
home_blocklist();
home_sitesblocked();
home_queries();
home_network();

trafficgraph();

echo '</div>'.PHP_EOL;

$db->close();
?>
</div>
</body>
</html>
