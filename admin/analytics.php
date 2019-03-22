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
  <title>NoTrack - Analytics</title>
  <meta name="viewport" content="width=device-width, initial-scale=0.7">
</head>

<body>
<?php
draw_topmenu('Analytics');
draw_sidemenu();
echo '<div id="main">'.PHP_EOL;

/************************************************
*Constants                                      *
************************************************/


/************************************************
*Global Variables                               *
************************************************/

/************************************************
*Arrays                                         *
************************************************/


/********************************************************************
 *  Show Group View
 *    Show results grouped by name
 *
 *  Params:
 *    None
 *  Return:
 *    false when nothing found, true on success
 */
function show_analytics() {
  global $db;
  $action = '';
  $log_time = '';
  $sys = '';
  $dns_request = '';
  $dns_result = '';
  $issue = '';
  $ack = '';
  $list = '';
  $query = '';

  $query = "SELECT * FROM analytics ORDER BY log_time DESC";

  echo '<div class="sys-group">'.PHP_EOL;

  if(!$result = $db->query($query)){
    echo '<h4><img src=./svg/emoji_sad.svg>Error running query</h4>'.PHP_EOL;
    echo 'show_analytics: '.$db->error;
    echo '</div>'.PHP_EOL;
    die();
  }

  if ($result->num_rows == 0) {                 //Leave if nothing found
    $result->free();
    echo '<h4><img src=./svg/emoji_sad.svg>No results found</h4>'.PHP_EOL;
    return false;
  }

  echo '<table id="analytics-table">'.PHP_EOL;
  echo '<tr><th>Time</th><th>System</th><th>Site</th><th>Action</th><th>Review</th></tr>'.PHP_EOL;
  
  while($row = $result->fetch_assoc()) {         //Read each row of results
    $log_time = $row['log_time'];
    $sys = $row['sys'];
    $dns_request = $row['dns_request'];
    $dns_result = $row['dns_result'];
    $ack = ($row['ack'] == 0) ? '' : ' checked="checked"';
    
    if ($dns_result == 'B') {                              //Setup Action Button
      $action = '<button class="icon-tick button-grey" onclick="reportSite(\''.$dns_request.'\', true, true)">Allow</button>';
    }
    else {
      $action = '<button class="icon-boot button-grey" onclick="reportSite(\''.$dns_request.'\', false, true)">Block</button>';
    }
    

    if (($row['issue'] == 'Tracker') || ($row['issue'] == 'Advert')) {
      $issue = $row['issue'].' Accessed';
    }
    else {                                                 //Setup Malware Alert
      $list = ucwords(str_replace('_', ' ', substr($row['issue'], 11)));
      $issue = ($dns_result == 'B') ? '<span title="Blocked by '.$list.'">Malware Blocked</span>' : '<span class="red" title="Blocklist '.$list.'">Malware Accessed</span>';
    }
    
    echo "<tr><td>$log_time</td><td>$sys</td><td>$issue - $dns_request</td><td>$action</td><td><input type=\"checkbox\"$ack></td></tr>".PHP_EOL;
    //print_r($row);
    
  }
  
  echo '</table>'.PHP_EOL;
  echo '</div>'.PHP_EOL;                                   //End sys-group
  $result->free();

  return true;
}

/********************************************************************
 *Main
 */
$db = new mysqli(SERVERNAME, USERNAME, PASSWORD, DBNAME);
show_analytics();

//echo '</div>'.PHP_EOL;                                     //End Div Group
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
