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
*Constants                                      *
************************************************/
DEFINE('DHCP_CONF', '/etc/dnsmasq.d/dhcp.conf');


/************************************************
*Global Variables                               *
************************************************/

/************************************************
*Arrays                                         *
************************************************/
$DHCPConfig = array(
  'dhcp_enabled' => false,
  'start_ip' => '',
  'end_ip' => '',
  'gateway_ip' => '',
  'lease_time' => '24h',
  'dhcp_authoritative' => false,
  'static_hosts' => '',
);

/********************************************************************
 *  Load DHCP Values from SQL
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function load_dhcp() {
  global $DHCPConfig;

  $previous_line = "";
  $line = "";

  if (file_exists(DHCP_CONF)) {                            //Does /etc/dnsmasq.d/dhcp.conf exist?
    $fh = fopen(DHCP_CONF, 'r') or die('Error unable to open '.DHCP_CONF);
  
    while (!feof($fh)) {
      $line = trim(fgets($fh));                            //Read Line of config
      
      //dhcp-host=Mac,IP (Name is on $previous_line)
      if (preg_match('/^dhcp-host=(.+)/', $line,  $matches) > 0) {
        if (! is_commented(substr($previous_line, 0, 1))) {
          $DHCPConfig['static_hosts'] .= substr($previous_line, 1).','.$matches[1].PHP_EOL;
        }
        else {
          $DHCPConfig['static_hosts'] .= $matches[1].PHP_EOL;
        }
      }
      
      //dhcp-range=(start_ip),(end_ip),(lease_time)
      //TODO input validation
      elseif (preg_match('/^(#?)dhcp\-range\=([^,]+),([^,]+),(.+)$/', $line, $matches) > 0) {
        $DHCPConfig['dhcp_enabled'] = is_commented($matches[1]);
        $DHCPConfig['start_ip'] = $matches[2];
        $DHCPConfig['end_ip'] = $matches[3];
        $DHCPConfig['lease_time'] = $matches[4];
      }
      //dhcp-option=3,(gateway_ip)
      elseif (preg_match('/(#?)^dhcp-option\=3,(.+)$/', $line, $matches) > 0) {
        $DHCPConfig['gateway_ip'] = $matches[2];
      }
      //dhcp-authoritative
      elseif (preg_match('/^(#?)dhcp-authoritative/', $line, $matches) > 0) {
        $DHCPConfig['dhcp_authoritative'] = is_commented($matches[1]);
      }
      
    
    $previous_line = $line;
    }
    
    fclose($fh);                                           //Close /etc/dnsmasq.d/dhcp.conf
  }
  
  //Set some default values if an IP address is missing based on the Web server IP
  //gateway_ip=$(ip route | grep -oP 'default[[:space:]]via[[:space:]]\K([0-9a-f:\.]+)')
  
  if (($DHCPConfig['start_ip'] == '') || ($DHCPConfig['end_ip'] == '') || ($DHCPConfig['gateway_ip'] == '')) {
    //Example (192.168.0).x
    if (preg_match('/^(\d{1,3}\.\d{1,3}\.\d{1,3}\.)\d{1,3}/', $_SERVER['SERVER_ADDR'], $matches) > 0) {
    
      $DHCPConfig['start_ip'] = $matches[1].'64';
      $DHCPConfig['end_ip'] = $matches[1].'254';
      $DHCPConfig['gateway_ip'] = $matches[1].'1';
    }
    
    // TODO No idea about IPv6
    elseif (preg_match('/^[0-9a-f:]+/i', $_SERVER['SERVER_ADDR'], $matches) > 0) {
      $DHCPConfig['start_ip'] = $matches[0].':00FF';
      $DHCPConfig['end_ip'] = $matches[0].':FFFF';
      $DHCPConfig['gateway_ip'] = $matches[0].'';
    }
    
    //Not known, use the default values from Dnsmasq
    else {
      $DHCPConfig['start_ip'] = '192.168.0.50';
      $DHCPConfig['end_ip'] = '192.168.0.150';
      $DHCPConfig['gateway_ip'] = '192.168.0.1';
    }
  }
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
  echo '<input type="hidden" name="update" value="1">';
  
  draw_systable('DHCP Config');
  draw_sysrow('Enabled', '<input type="checkbox" name="enabled" id="enabledBox" '.is_checked($DHCPConfig['dhcp_enabled']).'>');
  echo '<tr id="confRow1"><td>Authoritative:</td><td><input type="checkbox" name="authoritative"'.is_checked($DHCPConfig['dhcp_authoritative']).'>Authoritative mode will barge in and take over the lease for any client which broadcasts on the network. Avoids long timeouts when a machine wakes up on a new network.</td></tr>'.PHP_EOL;
  echo '<tr id="confRow2"><td>Gateway IP: <img class="btn" src="./svg/button_help.svg" alt="help" title="Usually the IP address of your Router"></td><td><input type="text" name="gateway_ip" value="'.$DHCPConfig['gateway_ip'].'"></td></tr>'.PHP_EOL;
  echo '<tr id="confRow3"><td>Range - Start IP:</td><td><input type="text" name="start_ip" value="'.$DHCPConfig['start_ip'].'"></td></tr>'.PHP_EOL;
  echo '<tr id="confRow4"><td>Range - End IP:</td><td><input type="text" name="end_ip" value="'.$DHCPConfig['end_ip'].'"></td></tr>'.PHP_EOL;
  echo '<tr id="confRow5"><td>Lease Time:</td><td><input type="text" name="lease_time" value="'.$DHCPConfig['lease_time'].'"></td></tr>'.PHP_EOL; //TODO Beautify
  echo '<tr id="confRow6"><td>Static Hosts:</td><td><p class="light"><code>System.name,MAC Address,IP to allocate</code><br><code>e.g. nas.local,11:22:33:aa:bb:cc,192.168.0.5</code></p>';
  echo '<textarea rows="10" name="static">'.$DHCPConfig['static_hosts'].'</textarea></td></tr>'.PHP_EOL;
  echo '<tr><td colspan="2"><div class="centered"><input type="submit" class="button-blue" value="Save Changes">&nbsp;<input type="reset" class="button-blue" value="Reset"></div></td></tr>'.PHP_EOL;
  echo '</table></div>'.PHP_EOL;
  echo '</form>'.PHP_EOL;
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
  global $DHCPConfig;
  
  $hosts = array();
  $matches = array();
  $host = '';
  
  $DHCPConfig['enabled'] = isset($_POST['enabled']);
  $DHCPConfig['dhcp_authoritative'] = isset($_POST['authoritative']);
  
  if (isset($_POST['gateway_ip'])) {
    if (filter_var($_POST['gateway_ip'], FILTER_VALIDATE_IP) !== false) {
      $DHCPConfig['gateway_ip'] = $_POST['gateway_ip'];
    }
  }
  if (isset($_POST['start_ip'])) {
    if (filter_var($_POST['start_ip'], FILTER_VALIDATE_IP) !== false) {
      $DHCPConfig['start_ip'] = $_POST['start_ip'];
    }
  }
  if (isset($_POST['end_ip'])) {
    if (filter_var($_POST['end_ip'], FILTER_VALIDATE_IP) !== false) {
      $DHCPConfig['end_ip'] = $_POST['end_ip'];
    }
  }

  if (isset($_POST['lease_time'])) {
    if (preg_match('/\d\d?(h|d)/', $_POST['lease_time']) > 0) {
      $DHCPConfig['lease_time'] = $_POST['lease_time'];
    }
  }
  
  $fh = fopen(DIR_TMP.'dhcp.conf', 'w') or die('Unable to open '.DIR_TMP.'dhcp.conf for writing');
  
  if ($DHCPConfig['enabled']) {
    fwrite($fh, 'dhcp-option=3,'.$DHCPConfig['gateway_ip'].PHP_EOL);
    fwrite($fh, 'dhcp-range='.$DHCPConfig['start_ip'].','.$DHCPConfig['end_ip'].','.$DHCPConfig['lease_time'].PHP_EOL);
  }
  else {
    fwrite($fh, '#dhcp-option=3,'.$DHCPConfig['gateway_ip'].PHP_EOL);
    fwrite($fh, '#dhcp-range='.$DHCPConfig['start_ip'].','.$DHCPConfig['end_ip'].','.$DHCPConfig['lease_time'].PHP_EOL);
  }

  if ($DHCPConfig['dhcp_authoritative']) {
    fwrite($fh, 'dhcp-authoritative'.PHP_EOL);
  }
  else {
    fwrite($fh, '#dhcp-authoritative'.PHP_EOL);
  }
  
  fwrite($fh, PHP_EOL);                                    //Bug fix to prevent #dhcp-authoritative being used as a host name
  
  if (isset($_POST['static'])) {                           //Need to split textbox into seperate lines
    $hosts = explode(PHP_EOL, strip_tags($_POST['static'])); //Prevent XSS and write into an array
    
    foreach($hosts as $host) {                             //Read each line of $hosts array
      //Check for Name,MAC,IP or MAC,IP
      //Add record if it is valid
      if (preg_match('/^([^,]+),([a-f\d]{2}:[a-f\d]{2}:[a-f\d]{2}:[a-f\d]{2}:[a-f\d]{2}:[a-f\d]{2}),([a-f\d:\.]+)/', $host, $matches) > 0) {
        fwrite($fh, '#'.$matches[1].PHP_EOL);
        fwrite($fh, 'dhcp-host='.$matches[2].','.$matches[3].PHP_EOL);
      }
      elseif (preg_match('/([a-f\d]{2}:[a-f\d]{2}:[a-f\d]{2}:[a-f\d]{2}:[a-f\d]{2}:[a-f\d]{2}),([a-f\d:\.]+)/', $host, $matches) > 0) {
        fwrite($fh, 'dhcp-host='.$matches[1].','.$matches[2].PHP_EOL);
      }
    }
  }
  fclose($fh);                                             //Close Temp Conf
  exec(NTRK_EXEC.'--write dhcp');                          //Run ntrk-exec to copy Temp conf to /etc/dnsmasq.d/dhcp.conf then restart dnsmasq
}

/********************************************************************/
draw_topmenu('Network');
draw_sidemenu();
echo '<div id="main">'.PHP_EOL;

echo '<div class="sys-group">'.PHP_EOL;
echo '<h5>DHCP Leases</h5>'.PHP_EOL;

//Is DHCP Active?
if (file_exists('/var/lib/misc/dnsmasq.leases')) {
  $fh= fopen('/var/lib/misc/dnsmasq.leases', 'r') or die('Error unable to open /var/lib/misc/dnsmasq.leases');

  echo '<table id="dhcp-table">'.PHP_EOL;
  echo '<tr><th>IP Allocated</th><th>Device Name</th><th>MAC Address</th><th>Valid Until</th>'.PHP_EOL;
  
  while (!feof($fh)) {
    $line = trim(fgets($fh));            //Read Line of LogFile
    if ($line != '') {                           //Sometimes a blank line appears in log file
      $Seg = explode(' ', $line);
      //0 - Time Requested in Unix Time
      //1 - MAC Address
      //2 - IP Allocated
      //3 - Device Name
      //4 - '*' or MAC address
      echo '<tr><td>'.$Seg[2].'</td><td>'.$Seg[3].'</td><td>'.$Seg[1].'</td><td>'.date("d M Y \- H:i:s", $Seg[0]).'</td></tr>'.PHP_EOL;
    }
  }
  echo '</table>'.PHP_EOL;
  
  fclose($fh);
}

//No, display tutorial on how to set it up.
else {
  echo '<p>DHCP is not currently handled by NoTrack.<br>'.PHP_EOL;
  echo '<p>Enable it in the Config below:-'.PHP_EOL;
}

echo '</div>';

load_dhcp();

if (isset($_POST['update'])) {
  update_dhcp();
  $DHCPConfig['static_hosts'] = '';
  load_dhcp();
}

show_dhcp();

?>
</div>
<script>
//-------------------------------------------------------------------
function hideRows() {
  document.getElementById('confRow1').style.display = 'none';
  document.getElementById('confRow2').style.display = 'none';
  document.getElementById('confRow3').style.display = 'none';
  document.getElementById('confRow4').style.display = 'none';
  document.getElementById('confRow5').style.display = 'none';
  document.getElementById('confRow6').style.display = 'none';
}
//-------------------------------------------------------------------
function showRows() {
  document.getElementById('confRow1').style.display = '';
  document.getElementById('confRow2').style.display = '';
  document.getElementById('confRow3').style.display = '';
  document.getElementById('confRow4').style.display = '';
  document.getElementById('confRow5').style.display = '';
  document.getElementById('confRow6').style.display = '';
}
//-------------------------------------------------------------------
const checkbox = document.getElementById('enabledBox')

checkbox.addEventListener('change', (event) => {
  if (event.target.checked) {
    showRows();
  } else {
    hideRows();
  }
})

window.onload = function() {
  if (! enabledBox.checked) {
    hideRows();
  }
};
</script>
</body>
</html>
