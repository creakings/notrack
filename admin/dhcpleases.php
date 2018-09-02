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
  <title>NoTrack - DHCP Leases</title>
</head>

<body>
<?php

/************************************************
*Global Variables                               *
************************************************/
$db = new mysqli(SERVERNAME, USERNAME, PASSWORD, DBNAME);

/********************************************************************
 *  Load DHCP Values from SQL
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function load_dhcp() {
  global $DHCPConfig, $db;
  
  $query = "SELECT * FROM config WHERE config_type = 'dhcp'";
  $DHCPConfig['static_hosts'] = '';
  
  if (table_exists('config')) {
    if (count_rows("SELECT COUNT(*) FROM config WHERE config_type = 'dhcp'") == 0) {
      exec(NTRK_EXEC.'--read dhcp');
    }
  }
  else {
    exec(NTRK_EXEC.'--read dhcp');
    exec(NTRK_EXEC.'--read dnsmasq');
  }
  
  if(!$result = $db->query($query)) {            //Run the Query
    die('There was an error running the query'.$db->error);
  }
  
  while($row = $result->fetch_assoc()) {         //Read each row of results
    switch($row['option_name']) {
      case 'dhcp-host':
        $DHCPConfig['static_hosts'] .= $row['option_value'].PHP_EOL;
        break;
      case 'dhcp_enabled':
      case 'dhcp-authoritative':
      case 'log-dhcp':
        $DHCPConfig[$row['option_name']] = $row['option_enabled'];
        break;
      default:
        $DHCPConfig[$row['option_name']] = $row['option_value'];
        break;
    }    
  }
  
  $result->free();
}
/********************************************************************
 *  Show Full Block List
 *    1: DHCPConfig has been loaded from SQL table into Array
 *    2: Draw form
 * 
 *  Params:
 *    None
 *  Return:
 *    None
 */
function show_dhcp() {
  global $DHCPConfig;
    
  echo '<form method="POST">'.PHP_EOL;
  echo '<input type="hidden" name="action" value="dhcp">';
  
  draw_systable('<strike>DHCP</strike> Work in progress');
  draw_sysrow('Enabled', '<input type="checkbox" name="enabled" '.is_checked($DHCPConfig['dhcp_enabled']).'>');
  draw_sysrow('Gateway IP', '<input type="text" name="gateway_ip" value="'.$DHCPConfig['gateway_ip'].'"><p>Usually the IP address of your Router</p>');
  draw_sysrow('Range - Start IP', '<input type="text" name="start_ip" value="'.$DHCPConfig['start_ip'].'">');
  draw_sysrow('Range - End IP', '<input type="text" name="end_ip" value="'.$DHCPConfig['end_ip'].'">');
  draw_sysrow('Authoritative', '<input type="checkbox" name="authoritative"'.is_checked($DHCPConfig['dhcp-authoritative']).'><p>Set the DHCP server to authoritative mode. In this mode it will barge in and take over the lease for any client which broadcasts on the network. This avoids long timeouts
  when a machine wakes up on a new network. http://www.isc.org/files/auth.html</p>');
  echo '<tr><td>Static Hosts:</td><td><p><code>System.name,MAC Address,IP to allocate</code><br>e.g. <code>nas.local,11:22:33:aa:bb:cc,192.168.0.5</code></p>';
  echo '<textarea rows="10" name="static">'.$DHCPConfig['static_hosts'].'</textarea></td></tr>'.PHP_EOL;
  echo '<tr><td colspan="2"><div class="centered"><input type="submit" class="button-blue" value="Save Changes">&nbsp;<input type="reset" class="button-blue" value="Reset"></div></td></tr>'.PHP_EOL;
  echo '</table></div>'.PHP_EOL;
  echo '</div></form>'.PHP_EOL;
}


/********************************************************************
 *  Add Config Record
 *    Add new record to config table
 *  Params:
 *    type, name, value, enabled
 *  Return:
 *    None
 */
function add_config_record($config_type, $option_name, $option_value, $option_enabled) {
  global $db;
  
  $query = "INSERT INTO config (config_id, config_type, option_name, option_value, option_enabled) VALUES(null, '$config_type', '$option_name', '$option_value', '$option_enabled')";
    
  if (! $db->query($query)) {
    die('add_config_record Error: '.$db->error);
  }
  
  return null;
}

/********************************************************************
 *  Delete Config Record
 *    Delete records from config table
 *  Params:
 *    type, name
 *  Return:
 *    None
 */
function delete_config_record($config_type, $option_name) {
  global $db;
  
  $query = "DELETE FROM config WHERE config_type = '$config_type' AND option_name = '$option_name'";
    
  if (! $db->query($query)) {
    die('delete_config_record Error: '.$db->error);
  }
    
  return null;
}


/********************************************************************
 *  Update Config Record
 *    1: Search for the ID of option_name
 *    2: If record can't be found then set query to add value
 *  Params:
 *    type, name, value, enabled
 *  Return:
 *    None
 */
function update_config_record($config_type, $option_name, $option_value, $option_enabled) {
  global $db;
  
  $config_id = 0;
  $query = '';
  
  $result = $db->query("SELECT * FROM config WHERE config_type = '$config_type' AND option_name = '$option_name'");
  
  if ($result->num_rows > 0) {                       #Has anything been found?
    $config_id = $result->fetch_object()->config_id; #Get the ID number
  }
  
  if ($config_id > 0) {                          #ID > 0 means an existing record was found
    $query = "UPDATE config SET option_value = '$option_value', option_enabled = '$option_enabled' WHERE config_id = '$config_id'";
  }
  else {                                         #Nothing found, add new record
    $query = "INSERT INTO config (config_id, config_type, option_name, option_value, option_enabled) VALUES(null, '$config_type', '$option_name', '$option_value', '$option_enabled')";
  }
  
  if (! $db->query($query)) {
    die('update_config_record Error: '.$db->error);
  }
  
  $result->free();
  return null;
}
/********************************************************************
 *  Update DHCP
 *    dhcp-enabled, and dhcp-authoritative are tick boxes
 *    gateway_ip, start_ip, end_ip are all IP addresses, use filter_var to validate
 *    Its not easy to update the dhcp-host's, so we delete them and then re-add
 *
 *  Params:
 *    None
 *  Return:
 *    None
 *  Regex:
 *    Group 1: anthing up to first comma ,
 *    Group 2: MAC Address
 *    Group 3: IPv4 or IPv6 address
 */
function update_dhcp() {
  global $db;
  
  
  $hosts = array();
  $matches = array();
  $host = '';
  
  update_config_record('dhcp', 'dhcp_enabled', '', isset($_POST['enabled']));
  update_config_record('dhcp', 'dhcp-authoritative', '', isset($_POST['authoritative']));
  
  if (isset($_POST['gateway_ip'])) {
    if (filter_var($_POST['gateway_ip'], FILTER_VALIDATE_IP) !== false) {
      update_config_record('dhcp', 'gateway_ip', $_POST['gateway_ip'], true);
    }    
  }
  if (isset($_POST['start_ip'])) {
    if (filter_var($_POST['start_ip'], FILTER_VALIDATE_IP) !== false) {
      update_config_record('dhcp', 'start_ip', $_POST['start_ip'], true);
    }    
  }
  if (isset($_POST['end_ip'])) {
    if (filter_var($_POST['end_ip'], FILTER_VALIDATE_IP) !== false) {
      update_config_record('dhcp', 'end_ip', $_POST['end_ip'], true);
    }    
  }
  
  delete_config_record('dhcp', 'dhcp-host');
  if (isset($_POST['static'])) {                 //Need to split textbox into seperate lines
    $hosts = explode(PHP_EOL, strip_tags($_POST['static'])); #Prevent XSS
    
    foreach($hosts as $host) {                   //Read each line
      //Check for Name,MAC,IP or MAC,IP
      //Add record if it is valid
      if (preg_match('/^([^,]+),([a-f\d]{2}:[a-f\d]{2}:[a-f\d]{2}:[a-f\d]{2}:[a-f\d]{2}:[a-f\d]{2}),([a-f\d:\.]+)/', $host, $matches) > 0) {
        add_config_record('dhcp', 'dhcp-host', $matches[0], true);
      }
      elseif (preg_match('/([a-f\d]{2}:[a-f\d]{2}:[a-f\d]{2}:[a-f\d]{2}:[a-f\d]{2}:[a-f\d]{2}),([a-f\d:\.]+)/', $host, $matches) > 0) {
        add_config_record('dhcp', 'dhcp-host', $matches[0], true);
      }
    }
  }
  
  return null;
}



draw_topmenu('Network');
draw_sidemenu();
echo '<div id="main">'.PHP_EOL;

echo '<div class="sys-group"><div class="sys-title">'.PHP_EOL;
echo '<h5>DHCP Leases</h5>'.PHP_EOL;
echo '</div>'.PHP_EOL;
echo '<div class="sys-items">'.PHP_EOL;

//Is DHCP Active?
if (file_exists('/var/lib/misc/dnsmasq.leases')) {
  $FileHandle= fopen('/var/lib/misc/dnsmasq.leases', 'r') or die('Error unable to open /var/lib/misc/dnsmasq.leases');

  echo '<table id="dhcp-table">'.PHP_EOL;
  echo '<tr><th>IP Allocated</th><th>Device Name</th><th>MAC Address</th><th>Valid Until</th>'.PHP_EOL;
  
  while (!feof($FileHandle)) {
    $Line = trim(fgets($FileHandle));            //Read Line of LogFile
    if ($Line != '') {                           //Sometimes a blank line appears in log file
      $Seg = explode(' ', $Line);
      //0 - Time Requested in Unix Time
      //1 - MAC Address
      //2 - IP Allocated
      //3 - Device Name
      //4 - '*' or MAC address
      echo '<tr><td>'.$Seg[2].'</td><td>'.$Seg[3].'</td><td>'.$Seg[1].'</td><td>'.date("d M Y \- H:i:s", $Seg[0]).'</td></tr>'.PHP_EOL;
    }    
  }
  echo '</table>'.PHP_EOL;
}

//No, display tutorial on how to set it up.
else {
  echo '<p>DHCP is not currently being handled by NoTrack.</p>'.PHP_EOL;
  echo '<p>In order to enable it, you need to edit Dnsmasq config file.<br>See this video tutorial: <a href="https://www.youtube.com/watch?v=a5dUJ0SlGP0">DHCP Server Setup with Dnsmasq</a></p><br>'.PHP_EOL;
  echo '<iframe width="640" height="360" src="https://www.youtube.com/embed/a5dUJ0SlGP0" frameborder="0" allowfullscreen></iframe>'.PHP_EOL;  
}

echo '</div></div>';

load_dhcp();
show_dhcp();
$db->close();
?>
</div>
</body>
</html>
