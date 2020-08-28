<?php
require('../include/global-vars.php');
require('../include/global-functions.php');
require('../include/config.php');
require('../include/menu.php');
require('../include/mysqlidb.php');

ensure_active_session();

/************************************************
*Constants                                      *
************************************************/
define('DNSLIST', ['dnsmasq', 'bind']);
define('OPTIONSLIST', ['Custom', 'Cloudflare', 'FreeDNS', 'OpenDNS', 'Verisign', 'Yandex']);

/************************************************
*Global Variables                               *
************************************************/
$dbwrapper = new MySqliDb();

/************************************************
*Arrays                                         *
************************************************/


/********************************************************************
 *  Show DNS Server Section
 *    1. Find running dns server from DNSLIST using ps
 *    2. Split result of ps into an $pidarray using delimiter of one or more spaces
 *    3. $pidarray elements:
 *       0 - Process
 *       1 - PID
 *       2 - Date Opened
 *       3 - Memory Usage
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function dns_status() {
  global $dbwrapper;

  $pidstr = '';
  $pidarray = array();

  foreach (DNSLIST AS $app) {
    $pidstr = exec("ps -eo fname,pid,stime,pmem | grep $app");

    //Has valid process been found?
    if ($pidstr != '') {
      $pidarray = preg_split('/\s+/', $pidstr);            //Explode into array
      $pidarray[0] = ucfirst($pidarray[0]).' is Active';   //Prettify process name
      break;
    }
  }

  //Fallback if no process hasn't been found
  if ($pidstr == '') {
    $pidarray = array('<span class="red">Inactive</span>', '-', '-', '-');
  }

  echo '<section id="dns">'.PHP_EOL;
  draw_systable('DNS Server');
  draw_sysrow('Status', $pidarray[0]);
  draw_sysrow('Pid', $pidarray[1]);
  draw_sysrow('Started On', $pidarray[2]);
  draw_sysrow('Memory Used', $pidarray[3].' MB');
  draw_sysrow('Historical Logs', $dbwrapper->queries_historical_days().' Days');
  draw_sysrow('DNS Queries', number_format($dbwrapper->count_total_queries()));
  draw_sysrow('Delete All History', '<button class="button-danger" type="button" onclick="confirmLogDelete();">Purge</button>');
  echo '</table></div>'.PHP_EOL;
  echo '</section>'.PHP_EOL;
}

/********************************************************************
 *  Draw Form
 *    Form items
 *  Params:
 *    None
 *  Return:
 *    None
 */
function draw_form() {
  global $config;

  $ipbox1 = '';
  $ipbox2 = '';
  $ipoptions = '';

  $ipoptions = menu_ipoptions($config->dns_server);
  $ipbox1 = '<input type="text" name="serverIP1" id="serverIP1" value="'.$config->dns_serverip1.'" placeholder="208.67.222.222" pattern="[\da-fA-F:\.]+">';
  $ipbox2 = '<input type="text" name="serverIP2" id="serverIP2" value="'.$config->dns_serverip2.'" placeholder="208.67.220.220" pattern="[\da-fA-F:\.]+">';

  echo '<form method="POST">'.PHP_EOL;
  
  echo '<div class="sys-group">'.PHP_EOL;                  //Start sys-group box
  echo '<h5>Configuration</h5>'.PHP_EOL;
  echo '<table class="sys-table">'.PHP_EOL;
  draw_sysrow('DNS Server', "{$ipoptions}<br>{$ipbox1}<br> {$ipbox2}");
  draw_sysrow('Listening Interface', '<input type="text" name="listenInterface" id="listenInterface" value="'.$config->dns_interface.'" placeholder="eth0" pattern="\w+">');
  draw_sysrow('Listening IP', '<input type="text" name="listenIP" id="listenIP" value="'.$config->dns_listenip.'" placeholder="127.0.0.1" pattern="[\da-fA-F:\.]+">');
  draw_sysrow('Listening Port', '<input type="number" name="listenPort" id="listenPort" value="'.$config->dns_listenport.'" placeholder="53" min="1" max="65535">');
  draw_sysrow('Block Page IP', '<input type="text" name="blockIP" id="blockIP" value="'.$config->dns_blockip.'" placeholder="192.168.0.2" pattern="[\da-fA-F:\.]+">');
  draw_sysrow('Log Retention', '<input type="number" name="logRetention" id="logRetention" value="'.$config->dns_logretention.'" placeholder="60" min="0" max="366" title="Log retention in days (zero to retain forever)">');

  
  echo '<tr><td colspan="2"><div class="centered"><button class="icon-tick" type="submit">Save Changes</button></div></td></tr>'.PHP_EOL;
  
  echo '</table>'.PHP_EOL;
  
  echo '</div>'.PHP_EOL;                                   //End sys-group box
  echo '</form>'.PHP_EOL;
}


function menu_ipoptions($current) {
  $str = '';

  $str = '<select id="server">';
  if (in_array($current, OPTIONSLIST)) {
    $str .= "<option value=\"$current\">$current</option>";
  }
  else {
    $str .= "<option value=\"Custom\">Custom</option>";
  }

  foreach (OPTIONSLIST as $item) {
    $str .= "<option value=\"$item\">$item</option>";
  }

  $str .= '</select>';

  return $str;
}

/********************************************************************
 *  Save Changes
 *    1. Check POST vars have been set
 *    2. Carry out input validation using regex
 *    3. Failed input validation results in Config settings remaining unchanged
 *    4. Save Config array to file
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function save_changes() {
  global $config;

  $apikey = '';
  $apireadonly = '';

  //Use null coalescing operator in PHP 7 to check if POST vars have been set
  $apikey = $_POST['apikey'] ?? '';
  $apireadonly = $_POST['apireadonly'] ?? '';

  //Carry out input validation of apikey
  if (preg_match(REGEX_VALIDAPI, $apikey)) {
    $config->settings['api_key'] = $apikey;
  }

  //Carry out input validation of apireadonly
  if (preg_match(REGEX_VALIDAPI, $apireadonly)) {
    $config->settings['api_readonly'] = $apireadonly;
  }

  $config->save();
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <link href="../css/master.css" rel="stylesheet" type="text/css">
  <link href="../css/icons.css" rel="stylesheet" type="text/css">
  <link rel="icon" type="image/png" href="../favicon.png">
  <script src="../include/menu.js"></script>
  <script src="../include/customblocklist.js"></script>
  <meta name="viewport" content="width=device-width, initial-scale=0.9">
  <title>NoTrack - DNS Setup</title>
</head>
<?php

/********************************************************************
 Main
*/
draw_topmenu('DNS Server');
draw_sidemenu();

echo '<div id="main">'.PHP_EOL;

/*if (sizeof($_POST) > 0) {                                  //Anything in POST to process?
  save_changes();
}
*/
//dns_status();
draw_form();

echo '</div>'.PHP_EOL;
?>
</body>
</html>
