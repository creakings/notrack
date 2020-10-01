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
define('OPTIONSLIST', [
  'Custom' => array('', '', '', ''),
  'Cloudflare' => array('1.1.1.1', '1.0.0.1', '2606:4700:4700::1111', '2606:4700:4700::1001'),
  'Google' => array('8.8.8.8', '8.8.4.4', '2001:4860:4860::8888', '2001:4860:4860::8844'),
  'OpenDNS' => array('208.67.222.222', '208.67.220.220', '2620:0:ccc::2', '2620:0:ccd::2'),
  'Quad9' => array('9.9.9.9', '9.9.9.9', '2620:fe::fe', '2620:fe::fe'),
  'Verisign' => array('64.6.64.6', '64.6.65.6', '2620:74:1b::1:1', '2620:74:1c::2:2'),
  'Yandex' => array('77.88.8.88', '77.88.8.2', '2a02:6b8::feed:bad', '2a02:6b8:0:1::feed:bad')
  ]);

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
  draw_systable('Server Status');
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
  $ipversion = '';

  $ipoptions = menu_serveroptions($config->dns_server);
  $ipversion = menu_ipoptions($config->dns_serverip1);

  $ipbox1 = '<input type="text" name="serverIP1" id="serverIP1" value="'.$config->dns_serverip1.'" placeholder="208.67.222.222" pattern="[\da-fA-F:\.]+"><p class="light">Primary Server</p>';
  $ipbox2 = '<input type="text" name="serverIP2" id="serverIP2" value="'.$config->dns_serverip2.'" placeholder="208.67.220.220" pattern="[\da-fA-F:\.]+"><p class="light">Secondary Server</p>';

  echo '<form method="POST">'.PHP_EOL;
  
  echo '<div class="sys-group">'.PHP_EOL;                  //Start sys-group box
  echo '<h5>Configuration</h5>'.PHP_EOL;
  echo '<table class="sys-table">'.PHP_EOL;

  draw_sysrow('NoTrack Server Name', '<input type="text" name="dnsName" id="dnsName" value="'.$config->dns_name.'" placeholder="notrack.local" pattern="[\w\-]{1,63}\.[\w\-\.]{2,253}">');

  draw_sysrow('DNS Server', "{$ipoptions}<br>{$ipbox1} {$ipbox2}");
  draw_sysrow('IP Version', $ipversion);

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


/********************************************************************
 *  Menu IP Version Options
 *    HTML code for radio buttons for IP Version based on the selected IP
 *    1. Check if IPv6 or IPv4
 *    2. Generate radio options
 *
 *  Params:
 *    None
 *  Return:
 *    HTML code for radio buttons
 */
function menu_ipoptions($ip) {
  $ipv4checked = '';
  $ipv6checked = '';
  $str = '';

  if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
    $ipv6checked = ' checked="checked"';
  }
  else {
    $ipv4checked = ' checked="checked"';
  }

  $str .= '<label for="ipv4">IPv4<input type="radio" id="ipv4" name="ipVersion" value="ipv4" onclick="setIP()"'.$ipv4checked.'></label>';
  $str .= '<label for="ipv6">IPv6<input type="radio" id="ipv6" name="ipVersion" value="ipv6" onclick="setIP()"'.$ipv6checked.'></label>';

  return $str;
}


/********************************************************************
 *  Menu Server Options
 *    HTML code for select options box for DNS Servers
 *    1. Check current selection is valid
 *    2. Generate menu options from OPTIONSLIST
 *
 *  Params:
 *    dnsserver (str): $config->dns_server
 *  Return:
 *    HTML code for select and options list
 */
function menu_serveroptions($dnsserver) {
  $current = '';
  $selcted = '';
  $str = '';

  //Make sure the current selection is in OPTIONSLIST, set to Custom if it isn't
  $current = (array_key_exists($dnsserver, OPTIONSLIST)) ? $dnsserver : 'Custom';

  $str = '<select id="serverName" name="serverName" onchange="setIP()">';

  foreach (OPTIONSLIST as $key => $value) {
    $selected = ($key == $current) ? ' selected' : '';
    $str .= "<option value=\"{$key}\"{$selected}>{$key}</option>";
  }

  $str .= '</select>';

  return $str;
}


/********************************************************************
 *  Save Changes
 *    Get values from POST
 *    Assign values to config (input validation is carried out by config)
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function save_changes() {
  global $config;

  $blockip = $_POST['blockIP'] ?? '';
  $dnsname = $_POST['dnsName'] ?? '';
  $listeninterface = $_POST['listenInterface'] ?? '';
  $listenip = $_POST['listenIP'] ?? '';
  $listenport = $_POST['listenPort'] ?? 53;
  $logretention = $_POST['logRetention'] ?? 60;
  $serverip1 = $_POST['serverIP1'] ?? '';
  $serverip2 = $_POST['serverIP2'] ?? '';
  $server = $_POST['serverName'] ?? '';

  $config->dns_blockip = $blockip;
  $config->dns_interface = $listeninterface;
  $config->dns_listenip = $listenip;
  $config->dns_listenport = $listenport;
  $config->dns_logretention = $logretention;
  $config->dns_name = $dnsname;
  $config->dns_server = $server;
  $config->dns_serverip1 = $serverip1;
  $config->dns_serverip2 = $serverip2;

  $config->save_serversettings();
}

/********************************************************************
 Main
*/

load_serversettings();

if (sizeof($_POST) > 0) {                                  //Anything in POST to process?
  save_changes();
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

draw_topmenu('DNS Server');
draw_sidemenu();

echo '<div id="main">'.PHP_EOL;

draw_form();
dns_status();

echo '</div>'.PHP_EOL;
?>
<script>
var serverList = new Object();
<?php
//Fill out properties of serverList from contents of OPTIONSLIST
foreach (OPTIONSLIST as $key => $value) {
  echo "serverList.{$key} = ['{$value[0]}', '{$value[1]}', '{$value[2]}', '{$value[3]}'];".PHP_EOL;
}
?>

/********************************************************************
 *  Set IP
 *    Set IP addresses in serverIP1 and serverIP2 text boxes based on serverName and IP ver
 *
 */
function setIP() {
  selectedIPv4 = false;
  selectedIPv6 = false;
  selectedServer = '';

  //Get user selections
  selectedServer = document.getElementById('serverName').value;
  selectedIPv4 = document.getElementById('ipv4').checked;
  selectedIPv6 = document.getElementById('ipv6').checked;

  //Check if serverName is in serverList object
  if (! selectedServer in serverList) {
    console.warn('Invalid server selected');
    return;
  }

  if (selectedIPv4) {
    document.getElementById('serverIP1').value = serverList[selectedServer][0];
    document.getElementById('serverIP2').value = serverList[selectedServer][1];
  }
  else if (selectedIPv6) {
    document.getElementById('serverIP1').value = serverList[selectedServer][2];
    document.getElementById('serverIP2').value = serverList[selectedServer][3];
  }
  else {
    console.warn('Invalid IP version selected');
    return;
  }
}
</script>
</body>
</html>
