<?php
require('./include/global-vars.php');
require('./include/global-functions.php');
require('./include/menu.php');

load_config();
ensure_active_session();

?>
<!DOCTYPE html>
<html lang="en">
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
$view = false;

/************************************************
*Arrays                                         *
************************************************/


/********************************************************************
 *  Do Action
 *    Carry out POST action
 *    1. Explode comma seperated values from selectedCheckboxes
 *    2. Check each item complies with valid regex pattern
 *    3. call update_value for each matched string
 *
 *  Params:
 *    action parse through to update_value
 *  Return:
 *    None
 */
function do_action($action) {
  $boxstr = '';
  $boxes = array();
  $box = '';

  if (isset($_POST['selectedCheckboxes'])) {
    $boxstr = $_POST['selectedCheckboxes'];
  }
  else {
    return false;
  }

  $boxes = explode(',', $boxstr);

  foreach($boxes as $box) {
    if (preg_match('/(\d+)_(\d{4}\-\d\d-\d\d)_(\d\d:\d\d:\d\d)/', $box, $matches) > 0) {
      update_value($matches[1], $matches[2], $matches[3], $action);
    }
  }
}


/********************************************************************
 *  Update Value
 *    Update value in analytics table based on action
 *    Prevent malicious changes by checking time and id matches
 *    1. Search for value based on id and log_time
 *    2. Zero results means malicious change, so drop out silently
 *    3. Carry out update action
 *
 *  Params:
 *    id, logdate, logtime, action
 *  Return:
 *    None
 */
function update_value($id, $logdate, $logtime, $action) {
  global $db;
  $cmd = '';

  $cmd = "SELECT * FROM analytics WHERE id = '$id' AND log_time = '$logdate $logtime'";

  if(!$result = $db->query($cmd)){
    return false;
  }
  if ($result->num_rows == 0) {
    $result->free();
    return false;
  }
  $result->free();

  if ($action == 'resolve') {
    $cmd = "UPDATE analytics SET ack = TRUE WHERE id = '$id'";
  }
  elseif ($action == 'delete') {
    $cmd = "DELETE FROM analytics WHERE id = '$id'";
  }

  $db->query($cmd);
}


/********************************************************************
 *  Show Analytics
 *    1. Query results
 *    2. Draw Checkbox and Buttons
 *    3. Output data from query in a table
 *
 *  Params:
 *    None
 *  Return:
 *    false when nothing found, true on success
 */
function show_analytics() {
  global $db, $view;
  $action = '';
  $log_time = '';
  $sys = '';
  $dns_request = '';
  $dns_result = '';
  $issue = '';
  $row_colour = '';
  $list = '';
  $query = '';
  $checkboxid = '';
  $queryurl = '';                                          //URL to queries.php

  $query = "SELECT * FROM analytics WHERE ack = '$view' ORDER BY log_time DESC";

  echo '<div class="sys-group">'.PHP_EOL;

  if(!$result = $db->query($query)){
    echo '<h4><img src=./svg/emoji_sad.svg>Error running query</h4>'.PHP_EOL;
    echo 'show_analytics: '.$db->error;
    echo '</div>'.PHP_EOL;
    die();
  }

  if ($result->num_rows == 0) {                            //Leave if nothing found
    $result->free();
    echo '<h4><img src=./svg/emoji_sad.svg>No results found</h4>'.PHP_EOL;
    return false;
  }

  //Draw form and buttons
  echo '<form method="POST" name="analyticsForm">'.PHP_EOL;
  echo '<input type="hidden" id="selectedCheckboxes" name="selectedCheckboxes" value="">'.PHP_EOL;
  echo '<input type="checkbox" id="topCheckbox" onClick="checkAll(this)">'.PHP_EOL;
  echo '<button type="submit" name="action" value="resolve" onClick="submitForm()">Mark Resolved</button>'.PHP_EOL;
  echo '<button type="submit" class="button-grey" name="action" value="delete" onClick="submitForm()">Delete</button>'.PHP_EOL;
  echo '<p></p>'.PHP_EOL;

  echo '<table id="analytics-table">'.PHP_EOL;             //Start table
  echo '<tr><th>&nbsp;</th><th>Time</th><th>System</th><th>Site</th><th>Action</th></tr>'.PHP_EOL;

  while($row = $result->fetch_assoc()) {                   //Read each row of results
    $log_time = $row['log_time'];
    $sys = $row['sys'];
    $dns_request = $row['dns_request'];
    $dns_result = $row['dns_result'];
    $row_colour = ($row['ack'] == 0) ? '' : ' class="dark"';

    $checkboxid = $row['id'].'_'.str_replace(' ', '_', $log_time);
    if ($dns_result != 'B') {                              //Setup Action Button
      $action = '<button type="button" class="icon-boot button-grey" onclick="reportSite(\''.$dns_request.'\', false, true)">Block</button>';
    }

    if (($row['issue'] == 'Tracker') || ($row['issue'] == 'Advert')) {
      $issue = $row['issue'].' Accessed - '.$dns_request;
    }
    else {                                                 //Setup Malware Alert
      $list = ucwords(str_replace('_', ' ', substr($row['issue'], 11)));

      if ($dns_result == 'B') {
        $issue = 'Malware Blocked - '.$dns_request.'<p class="small grey">Blocked by '.$list.'</p>';
        $action = ($list == 'Notrack Malware') ? '<button type="button" class="icon-tick button-grey" onclick="reportSite(\''.$dns_request.'\', true, true)">Allow</button>' : '<button type="button" class="icon-tick button-grey" onclick="reportSite(\''.$dns_request.'\', true, false)">Allow</button>';
      }
      else {
        $issue = '<span class="red">Malware Accessed</span> - '.$dns_request.'<p class="small grey">Identified by '.$list.'</p>';
        $action = '';
      }
    }

    $queryurl = './queries.php?groupby=time&amp;sysip='.$sys.'&amp;datetime='.$log_time;

    echo '<tr'.$row_colour.'><td><input type="checkbox" name="resolve" id="'.$checkboxid.'" onclick="setIndeterminate()"></td>';
    echo '<td>'.$log_time.'</td><td>'.$sys.'</td><td class="pointer" onclick="window.open(\''.$queryurl.'\')">'.$issue.'</td><td>'.$action.'</td></tr>'.PHP_EOL;
  }

  echo '</table>'.PHP_EOL;
  echo '</form>'.PHP_EOL;
  echo '</div>'.PHP_EOL;                                   //End sys-group
  $result->free();

  return true;
}

/********************************************************************
 *Main
 */
$db = new mysqli(SERVERNAME, USERNAME, PASSWORD, DBNAME);

if (isset($_POST['action'])) {                             //Any POST actions to carry out?
  switch($_POST['action']) {
    case 'resolve':
      do_action('resolve');
      break;
    case 'delete':
      do_action('delete');
      break;
  }
}
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


/********************************************************************
 *  Check All
 *    Set checked value of all checkboxes in resolve group to topCheckbox
 *
 *  Params:
 *    source checkbox (topCheckbox)
 *  Return:
 *    None
 */
function checkAll(source) {
  let i = 0;
  let numCheckboxes = 0;
  let checkboxes = document.getElementsByName('resolve');

  numCheckboxes = checkboxes.length
  for (i = 0; i < numCheckboxes; i++)  {
    checkboxes[i].checked = source.checked;
  }

  document.getElementById('topCheckbox').indeterminate = false;
}


/********************************************************************
 *  Set Indeterminate
 *    Function is called after any of the resolve checkbox group is clicked.
 *    A count of boxes checked is taken.
 *    If all or none, then set topCheckbox as checked or unchecked respectively
 *    Else, set topCheckbox as Indeterminate
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function setIndeterminate() {
  let i = 0;
  let checkedCount = 0;
  let numCheckboxes = 0;
  let checkboxes = document.getElementsByName('resolve');

  numCheckboxes = checkboxes.length
  for (i = 0; i < numCheckboxes; i++)  {
    if (checkboxes[i].checked) {
      checkedCount++;
    }
  }

  if (checkedCount == 0) {
    document.getElementById('topCheckbox').checked = false;
    document.getElementById('topCheckbox').indeterminate = false;
  }
  else if (checkedCount == numCheckboxes) {
    document.getElementById('topCheckbox').checked = true;
    document.getElementById('topCheckbox').indeterminate = false;
  }
  else {
    document.getElementById('topCheckbox').checked = false;
    document.getElementById('topCheckbox').indeterminate = true;
  }
}


/********************************************************************
 *  Submit Form
 *    Collapse all checkbox ID's down into comma seperated values and place in
 *     selectedCheckboxes hidden value
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function submitForm() {
  let itemsChecked = '';
  let i = 0;
  let numCheckboxes = 0;
  let checkboxes = document.getElementsByName('resolve');

  numCheckboxes = checkboxes.length
  for (i = 0; i < numCheckboxes; i++)  {
    if (checkboxes[i].checked) {
      itemsChecked += checkboxes[i].id + ",";
    }
  }

  document.getElementById('selectedCheckboxes').value = itemsChecked;
}
</script>
</body>
</html>
