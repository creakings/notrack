<?php
/*TODO: Add Resolve and delete to popup menu*/
require('./include/global-vars.php');
require('./include/global-functions.php');
require('./include/config.php');
require('./include/menu.php');
require('./include/mysqlidb.php');

ensure_active_session();

/************************************************
*Constants                                      *
************************************************/
$INVESTIGATE = '';
$INVESTIGATEURL = '';

/************************************************
*Global Variables                               *
************************************************/
$dbwrapper = new MySqliDb;

$searchseverity = 0;
$status = 0;

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
  global $dbwrapper;

  $boxstr = '';
  $boxes = array();
  $box = '';

  $boxstr = $_POST['selectedCheckboxes'] ?? '';

  if (filter_string($boxstr, 4800) === false) return false;//Invalid string

  if ($boxstr == '') return false;                         //Nothing to do

  $boxes = explode(',', $boxstr);

  foreach($boxes as $box) {
    if (preg_match('/(\d+)_(\d{4}\-\d\d-\d\d)_(\d\d:\d\d:\d\d)/', $box, $matches) > 0) {
      $dbwrapper->analytics_update_value($matches[1], $matches[2], $matches[3], $action);
    }
  }
}


/********************************************************************
 *  Draw Filter Toolbar
 *    $searchseverity and $status utise bitwise values to allow multiple buttons to be pressed
 *    if status & value then button active, link is status - value
 *    else button inactive, link is status + value
 *  Params:
 *    None
 *  Return:
 *    None
 */

function draw_filter_toolbar() {
  global $searchseverity, $status;

  $severitylink = '';
  $statuslink = '';

  $severitylink = "severity={$searchseverity}";
  $statuslink = "&amp;status={$status}";

  echo '<div class="filter-toolbar analytics-filter-toolbar">'.PHP_EOL;

  //Column Headers
  echo '<div><h3>Status</h3></div>'.PHP_EOL;
  echo '<div><h3>Severity</h3></div>'.PHP_EOL;


  echo '<div class="filter-nav-group">'.PHP_EOL;           //Start Group 1 - Status
  if ($status & STATUS_OPEN) {
    echo '<a class="filter-nav-button active" href="?'.$severitylink.'&amp;status='.($status - STATUS_OPEN).'"><span>Open</span></a>'.PHP_EOL;
  }
  else {
    echo '<a class="filter-nav-button" href="?'.$severitylink.'&amp;status='.($status + STATUS_OPEN).'"><span>Open</span></a>'.PHP_EOL;
  }

  if ($status & STATUS_RESOLVED) {
    echo '<a class="filter-nav-button active" href="?'.$severitylink.'&amp;status='.($status - STATUS_RESOLVED).'"><span>Resolved</span></a>'.PHP_EOL;
  }
  else {
    echo '<a class="filter-nav-button" href="?'.$severitylink.'&amp;status='.($status + STATUS_RESOLVED).'"><span>Resolved</span></a>'.PHP_EOL;
  }
  echo '</div>'.PHP_EOL;                                   //End Group 1 - Status

  echo '<div class="filter-nav-group">'.PHP_EOL;           //Start Group 2 - Severity

  if ($searchseverity & SEVERITY_LOW) {
    echo '<a class="filter-nav-button active" title="Tracker Accessed"  href="?severity='.($searchseverity - SEVERITY_LOW).$statuslink.'"><img src="./svg/filters/severity_lowalt.svg" alt=""></a>'.PHP_EOL;
  }
  else {
    echo '<a class="filter-nav-button" title="Tracker Accessed" href="?severity='.($searchseverity + SEVERITY_LOW).$statuslink.'"><img src="./svg/filters/severity_lowalt.svg" alt=""></a>'.PHP_EOL;
  }

  if ($searchseverity & SEVERITY_MED) {
    echo '<a class="filter-nav-button active" title="Malware Blocked"  href="?severity='.($searchseverity - SEVERITY_MED).$statuslink.'"><img src="./svg/filters/severity_med.svg" alt=""></a>'.PHP_EOL;
  }
  else {
    echo '<a class="filter-nav-button" title="Malware Blocked" href="?severity='.($searchseverity + SEVERITY_MED).$statuslink.'"><img src="./svg/filters/severity_med.svg" alt=""></a>'.PHP_EOL;
  }

  if ($searchseverity & SEVERITY_HIGH) {
    echo '<a class="filter-nav-button active" title="Malware Accessed"  href="?severity='.($searchseverity - SEVERITY_HIGH).$statuslink.'"><img src="./svg/filters/severity_high.svg" alt=""></a>'.PHP_EOL;
  }
  else {
    echo '<a class="filter-nav-button" title="Malware Accessed" href="?severity='.($searchseverity + SEVERITY_HIGH).$statuslink.'"><img src="./svg/filters/severity_high.svg" alt=""></a>'.PHP_EOL;
  }
  echo '</div>'.PHP_EOL;                                   //End Group 2 - Severity
  echo '</div>'.PHP_EOL;                                   //End filter-toolbar
}


/********************************************************************
 *  Draw Table Toolbar
 *    Populates filter bar with Mark resolved
 *  Params:
 *    None
 *  Return:
 *    None
 */
function draw_table_toolbar() {
  echo '<div class="table-toolbar">'.PHP_EOL;
  echo '<input type="hidden" id="selectedCheckboxes" name="selectedCheckboxes" value="">'.PHP_EOL;
  echo '<input type="checkbox" id="topCheckbox" onClick="checkAll(this)">'.PHP_EOL;
  echo '<button type="submit" name="action" value="resolve" onClick="submitForm()">Mark Resolved</button>&nbsp;'.PHP_EOL;
  echo '<button type="submit" class="button-grey" name="action" value="delete" onClick="submitForm()">Delete</button>'.PHP_EOL;

  echo '<div class="table-toolbar-options">'.PHP_EOL;      //Start Table Toolbar Export
  echo '<button type="submit" name="action" value="export" class="button-grey material-icon-centre icon-export" title="Export">&nbsp;</button>';
  echo '</div>'.PHP_EOL;                                   //End Table Toolbar Export
  echo '</div>'.PHP_EOL;                                   //End filter-toolbar
}

/********************************************************************
 *  Popup Menu
 *    Prepare popup menu and contents
 *
 *  Params:
 *    Domain (str)
 *    Severity (int)
 *  Return:
 *    HTML code for popup menu
 */
function popupmenu($domain, $severity) {
  global $config, $INVESTIGATE, $INVESTIGATEURL;

  $str = '';
  $str .= '<div class="dropdown-container"><span class="dropbtn"></span><div class="dropdown">';
  
  if ($severity == 1) {
    $str .= "<span onclick=\"reportSite('{$domain}', false, true)\">Block</span>";
  }
  else {
    $str .= "<span onclick=\"reportSite('{$domain}', true, false)\">Allow</span>";
  }
  $str .= '<a href="'.$INVESTIGATEURL.$domain.'">'.$INVESTIGATE.'</a>';
  $str .= "<a href=\"{$config->search_url}{$domain}\" target=\"_blank\">{$config->search_engine}</a>";
  $str .= '<a href="https://www.virustotal.com/en/domain/'.$domain.'/information/" target="_blank">VirusTotal</a>';
  $str .= '</div></div>';                                  //End dropdown-container

  return $str;
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
  global $dbwrapper, $searchseverity, $status;

  $action = '';
  $bl_name = '';
  $checkboxid = '';                                        //Made up of id and time
  $clipboard = '';                                         //Div for Clipboard
  $domaincell = '';
  $dns_request = '';
  $investigateurl = '';                                    //URL to investigate.php
  $issue = '';                                             //What problem identified?
  $log_time = '';
  $row_colour = '';
  $severity = 0;
  $sys = '';

  echo '<div class="sys-group">'.PHP_EOL;
  echo '<form method="POST" name="analyticsForm">'.PHP_EOL;
  echo '<input type="hidden" name="severity" value="'.$searchseverity.'">'.PHP_EOL;
  echo '<input type="hidden" name="status" value="'.$status.'">'.PHP_EOL;

  draw_filter_toolbar();
  draw_table_toolbar();

  $analyticsdata = $dbwrapper->analytics_get_data($searchseverity, $status);

  if ($analyticsdata === false) {                         //Leave if nothing found
    echo '<h4><img src=./svg/emoji_sad.svg>No results found</h4>'.PHP_EOL;
    return false;
  }

  echo '<table id="analytics-table">'.PHP_EOL;             //Start table
  echo '<tr><th>&nbsp;</th><th>&nbsp;</th><th>Domain</th><th>System</th><th>Time</th><th>&nbsp;</th></tr>'.PHP_EOL;

  foreach ($analyticsdata as $row) {                       //Read each row of results
    $log_time = $row['log_time'];
    $sys = $row['sys'];
    $dns_request = $row['dns_request'];
    $severity = $row['severity'];
    $issue = $row['issue'];
    $row_colour = ($row['ack'] == 0) ? '' : ' class="dark"';

    $checkboxid = $row['id'].'_'.str_replace(' ', '_', $log_time);

    $investigateurl = "./investigate.php?datetime=".rawurlencode($log_time)."&amp;site={$dns_request}&amp;sys={$sys}";

    //Create clipboard image and text
    $clipboard = '<div class="icon-clipboard" onclick="setClipboard(\''.$dns_request.'\')" title="Copy domain">&nbsp;</div>';

    //Setup Popup menu Button for blocked malware site
    $action = popupmenu($dns_request, $severity);

    //Setup Domain Cell for Tracker or Advert accessed
    if (($issue == 'tracker') or ($issue == 'advert')) {
      $domaincell = '<a href="'.$investigateurl.'">'.ucfirst($issue).' Accessed - '.$dns_request.'</a>'.$clipboard;
    }

    //Setup Domain Cell for Malware blocked or allowed
    else {
      //Drop "Malware-", Replace underscore with space and uc first letter of each word
      $bl_name = ucwords(str_replace('_', ' ', substr($issue, 11)));
      $issue = 'malware';
      
      if ($severity == 2) {                            //Malware Blocked
        $domaincell = '<a href="'.$investigateurl.'">Malware Blocked - '.$dns_request.'</a>'.$clipboard.'<p class="small grey">Blocked by '.$bl_name.'</p>';
      }
      else {                                               //Malware Accessed
        $domaincell = '<a href="'.$investigateurl.'"><span class="red">Malware Accessed</span> - '.$dns_request.'</a>'.$clipboard.'<p class="small grey">Identified by '.$bl_name.'</p>';
      }
    }

    //Output table row
    echo '<tr'.$row_colour.'><td><img src="./svg/events/'.$issue.$severity.'.svg" alt=""></td><td><input type="checkbox" name="resolve" id="'.$checkboxid.'" onclick="setIndeterminate()"></td>';
    echo '<td>'.$domaincell.'</td><td>'.$sys.'</td><td>'.simplified_time($log_time).'</td><td>'.$action.'</td></tr>'.PHP_EOL;
  }

  echo '</table>'.PHP_EOL;
  echo '</form>'.PHP_EOL;
  echo '</div>'.PHP_EOL;                                   //End sys-group

  return true;
}


/********************************************************************
 *  Show Export
 *    Output a CSV file of
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function show_export() {
  global $dbwrapper, $searchseverity, $status;

  header('Content-type: text/csv');
  header('Content-Disposition: attachment; filename="notrack_alerts.csv"');

  $analyticsdata = $dbwrapper->analytics_get_data($searchseverity, $status);

  if ($analyticsdata === false) {                         //Leave if nothing found
    return;
  }

  echo 'Time,IP,Domain,Severity,Issue,Acknowledged'.PHP_EOL;

  foreach ($analyticsdata as $row) {                       //Read each row of results
    echo "\"{$row['log_time']}\",{$row['sys']},{$row['dns_request']},{$row['severity']},{$row['issue']},{$row['ack']}".PHP_EOL;
  }
}

/********************************************************************
 *Main
 */


//Review POST values
if (isset($_POST['severity'])) {                           //Severity to carry value through on reload
  $searchseverity = filter_integer($_POST['severity'], 0, 7, 1);
}

if (isset($_POST['status'])) {                             //ACK (acknowledge) carry value through on reload
  $status = filter_integer($_POST['status'], 0, 3, 1);
}

if (isset($_POST['action'])) {                             //Any POST actions to carry out?
  switch($_POST['action']) {
    case 'resolve':
      do_action('resolve');
      break;
    case 'delete':
      do_action('delete');
      break;
    case 'export':
      show_export();
      exit;
      break;
  }
  //Reload page to prevent repeat action browser alert
  header("Location: analytics.php?severity={$searchseverity}&status={$status}");
  exit;
}

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
  <title>NoTrack - Alerts</title>
  <meta name="viewport" content="width=device-width, initial-scale=0.7">
</head>

<body>
<?php

//Review GET values

if (isset($_GET['severity'])) {                            //Severity
  $searchseverity = filter_integer($_GET['severity'], 0, 7, 1);
}

if (isset($_GET['status'])) {                              //ACK (acknowledge)
  $status = filter_integer($_GET['status'], 0, 3, 1);
}

draw_topmenu('Alerts');
draw_sidemenu();
echo '<div id="main">'.PHP_EOL;

if ($config->whois_api == '') {                            //Setup Investigate / Whois for popupmenu
  $INVESTIGATE = $config->whois_provider;
  $INVESTIGATEURL = $config->whois_url;
}
else {
  $INVESTIGATE = 'Investigate';
  $INVESTIGATEURL = './investigate.php?site=';
}

show_analytics();
draw_copymsg();
//echo '</div>'.PHP_EOL;                                   //End Div Group

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

<div id="fade" onclick="hideQueriesBox()"></div>

<script>
const SEARCHNAME = <?php echo json_encode($config->search_engine)?>;
const SEARCHURL = <?php echo json_encode($config->search_url)?>;
const WHOISNAME = <?php echo json_encode($config->whois_provider)?>;
const WHOISURL = <?php echo json_encode($config->whois_url)?>;
const WHOISAPI = <?php echo ($config->whois_api == '') ? 0 : 1;?>;


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
