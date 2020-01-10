<?php
require('./include/global-vars.php');
require('./include/global-functions.php');
require('./include/config.php');
require('./include/menu.php');

ensure_active_session();

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <link href="./css/master.css" rel="stylesheet" type="text/css">
  <link href="./css/icons.css" rel="stylesheet" type="text/css">
  <link href="./css/tabbed.css" rel="stylesheet" type="text/css">
  <link rel="icon" type="image/png" href="./favicon.png">
  <script src="./include/menu.js"></script>
  <title>NoTrack - DHCP Config</title>
</head>

<body>
<?php

/************************************************
*Constants                                      *
************************************************/
define('DHCP_CONF', '/etc/dnsmasq.d/dhcp.conf');
define('LEASES_FILE', '/var/lib/misc/dnsmasq.leases');
define('REGEX_DEVICEICONS', '(computer|laptop|nas|phone|raspberrypi|server|tv)');

/************************************************
*Global Variables                               *
************************************************/
$view = 1;

/************************************************
*Arrays                                         *
************************************************/
$dhcpconfig = array(
  'dhcp_enabled' => false,
  'start_ip' => '',
  'end_ip' => '',
  'gateway_ip' => '',
  'lease_time' => '24h',
  'dhcp_authoritative' => false,
);

$statichosts = array();
$leases = array();                                         //Array of leases from LEASES_FILE


/********************************************************************
 *  Load Active Leases
 *    1. Load list of systems from LEASES_FILE into $leases array
 *    2. Use preg_match to make sure it is a valid line
 *    3. Write relevant $matches as an array to leases[ip]
 *    4. Active is assumed as yes
 *
 *  Params:
 *    None
 *  Return:
 *    None
 *  Regex:
 *    Group 1 - Expiry Time in Unix Time - integer
 *    Group 2 - MAC Address
 *    Group 3 - IP Allocated
 *    Group 4 - Device Name
 *    not captured - * or MAC address
 */
function load_activeleases() {
  global $leases;

  $matches = array();

  $fh= fopen(LEASES_FILE, 'r') or die('Error unable to open '.LEASES_FILE);

  while (!feof($fh)) {
    $line = fgets($fh);                                    //Read Line of LogFile

    //Extract component items from log file
    //Create new value in leases by key - IP
    //Value is an array of: mac, name, icon, active
    if (preg_match('/^(\d+) ([\da-f:]{17}) ([\d:\.]+) ([\w\*\-_\.]+)/i', $line, $matches)) {
      $leases[$matches[3]] = array('exptime' => $matches[1], 'mac' => $matches[2], 'name' => $matches[4], 'icon' => 'computer', 'active' => true);
    }
  }

  fclose($fh);                                             //Close LEASES_FILE
}



/********************************************************************
 *  Load DHCP Values from DHCP_CONF
 *    1. Open /etc/dnsmasq.d/dhcp.conf
 *    2. Match each line against certain regex combinations
 *    3. Static host names (if present) are held on the commented out previous config line
 *    4. Values are stored in $dhcpconfig and $statichosts[$ip]
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function load_dhcp() {
  global $dhcpconfig, $statichosts;

  $host = array();                                         //Temp array for each host
  $ip = '';                                                //System IP for $statichosts
  $line = '';
  $previous_line = '';

  if (file_exists(DHCP_CONF)) {                            //Does /etc/dnsmasq.d/dhcp.conf exist?
    $fh = fopen(DHCP_CONF, 'r') or die('Error unable to open '.DHCP_CONF);

    while (!feof($fh)) {
      $line = trim(fgets($fh));                            //Read Line of config

      //dhcp-host=Mac,IP (Name is on $previous_line)
      //Create new value in statichosts by key - IP
      //Value is an array of: mac, name, icon
      if (preg_match('/^dhcp\-host=([\da-f:]{17}),([\da-f:\.]+)$/i', $line,  $matches)) {
        $host = array();
        $ip = $matches[2];                                 //Hold IP value
        $host['mac'] = $matches[1];                        //Set MAC Address
        $host['name'] = '';                                //Temp System name
        $host['icon'] = 'computer';                        //Temp Icon name

        if (preg_match('/^#([\w\.]+),?'.REGEX_DEVICEICONS.'?$/', $previous_line, $matches)) {
          $host['name'] = $matches[1];                     //Set the System name
          $host['icon'] = $matches[2];                     //Add Icon name if it exists
        }

        $statichosts[$ip] = $host;                         //Add by IP
      }

      //dhcp-range=(start_ip),(end_ip),(lease_time d/h/m)
      elseif (preg_match('/^(#?)dhcp\-range=([\da-f:\.]+),([\da-f:\.]+),(\d{1,3}[DdHhMm])$/', $line, $matches) > 0) {
        $dhcpconfig['dhcp_enabled'] = is_commented($matches[1]);
        $dhcpconfig['start_ip'] = $matches[2];
        $dhcpconfig['end_ip'] = $matches[3];
        $dhcpconfig['lease_time'] = $matches[4];
      }

      //dhcp-option=3,gateway_ip (The router IP)
      elseif (preg_match('/^(#?)dhcp-option=3,([\da-f:\.]+)$/', $line, $matches) > 0) {
        $dhcpconfig['gateway_ip'] = $matches[2];
      }

      //dhcp-authoritative - commented yes or no
      elseif (preg_match('/^(#?)dhcp-authoritative$/', $line, $matches) > 0) {
        $dhcpconfig['dhcp_authoritative'] = is_commented($matches[1]);
      }

    $previous_line = $line;                                //Hold the current line in previous line
    }

    fclose($fh);                                           //Close /etc/dnsmasq.d/dhcp.conf
  }

  //Set some default values if an IP address is missing based on the Web server IP
  //gateway_ip=$(ip route | grep -oP 'default[[:space:]]via[[:space:]]\K([0-9a-f:\.]+)')

  if (($dhcpconfig['start_ip'] == '') || ($dhcpconfig['end_ip'] == '') || ($dhcpconfig['gateway_ip'] == '')) {
    //Example (192.168.0).x
    if (preg_match('/^(\d{1,3}\.\d{1,3}\.\d{1,3}\.)\d{1,3}/', $_SERVER['SERVER_ADDR'], $matches)) {

      $dhcpconfig['start_ip'] = $matches[1].'64';
      $dhcpconfig['end_ip'] = $matches[1].'254';
      $dhcpconfig['gateway_ip'] = $matches[1].'1';
    }

    // TODO No idea about IPv6
    elseif (preg_match('/^[0-9a-f:]+/i', $_SERVER['SERVER_ADDR'], $matches)) {
      $dhcpconfig['start_ip'] = $matches[0].':00FF';
      $dhcpconfig['end_ip'] = $matches[0].':FFFF';
      $dhcpconfig['gateway_ip'] = $matches[0].'';
    }

    //Not known, use the default values from Dnsmasq
    else {
      $dhcpconfig['start_ip'] = '192.168.0.50';
      $dhcpconfig['end_ip'] = '192.168.0.150';
      $dhcpconfig['gateway_ip'] = '192.168.0.1';
    }
  }
}


/********************************************************************
 *  Add Static Hosts
 *    Add Static Hosts to Leases in order to fill out the DHCP Leases view
 *     with additional information which is not present in the log file
 *
 *    1. Read each key and value of $statichost list
 *    2. Check if the IP exists in $leases
 *     2a. If it does, replace the name with the users entry in static_hosts
 *     2b. Otherwise, add system as a new entry and set it as inactive
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function add_statichosts() {
  global $statichosts, $leases;

  $hostinfo = array();
  $ip = '';

  foreach($statichosts as $ip => $hostinfo) {
    if (array_key_exists($ip, $leases)) {                  //Does this IP exist in leases array?
      $leases[$ip]['name'] = $hostinfo['name'];            //Replace the host name
      $leases[$ip]['icon'] = $hostinfo['icon'];            //Add the icon
    }
    else {                                                 //No - add host details as a new entry in leases
      $leases[$ip] = array('exptime' => 0, 'mac' => $hostinfo['mac'], 'name' => $hostinfo['name'], 'icon' => $hostinfo['icon'], 'active' => false);
    }
  }
}


/********************************************************************
 *  Get Icon Menu
 *    Build icon select menu
 *    TODO need to show pictures
 *  Params:
 *    None
 *  Return:
 *    code for a select box
 */
function get_iconmenu() {
  $menu = '';

  $menu = '<ul>';
  $menu .= '<li class="device-computer" title="Computer" onclick="setIcon(this)"></li>';
  $menu .= '<li class="device-laptop" title="Laptop" onclick="setIcon(this)"></li>';
  $menu .= '<li class="device-nas" title="NAS" onclick="setIcon(this)"></li>';
  $menu .= '<li class="device-server" title="Server" onclick="setIcon(this)"></li>';
  $menu .= '<li class="device-phone" title="Phone" onclick="setIcon(this)"></li>';
  $menu .= '<li class="device-raspberrypi" title="Raspberry Pi" onclick="setIcon(this)"></li>';
  $menu .= '<li class="device-tv" title="TV" onclick="setIcon(this)"></li>';
  $menu .= '</ul>'.PHP_EOL;

  return $menu;
}


/********************************************************************
 *  Update Static Hosts Array
 *    Validate POST data in newhosts then write to statichosts array
 *    Write the contents of statichosts array to /tmp/localhosts.list
 *    1. Check if data has been posted to newhosts
 *    2. Explode the new line seperated data from newhosts into an array
 *    3. Carry out regex check on each line
 *
 *  Params:
 *    None
 *  Return:
 *    None
 *  Regex:
 *    Group 1: IPv4 or IPv6 address
 *    Group 2: MAC Address
 *    Group 3: Surplus of MAC Address
 *    Group 4: Surplus of MAC Address
 *    Group 5: Name (optional)
 *    Group 6: Icon from REGEX_DEVICEICONS
 */
function update_statichosts() {
  global $statichosts;

  $matches = array();
  $newhosts = array();
  $host = '';
  $ip = '';

  $regex_newhost = '/^\s*([\d\.:]+)\s*,\s*(([\dA-Fa-f]{2}:){5}([\dA-Fa-f]{2}))\s*,\s*([\w\.\-_]+)?\s*,\s*'.REGEX_DEVICEICONS.'/';

  if (! isset($_POST['newhosts'])) return;                 //Leave if there is nothing in newhosts

  $newhosts = json_decode($_POST['newhosts']);             //Decode JSON data into an array

  //print_r($newhosts);
  foreach($newhosts as $host) {

    //Regex check on user data entered to ensure the it's valid and avoid XSS vulnerabilities
    if (preg_match($regex_newhost, $host, $matches)) {
      $ip = $matches[1];

      $statichosts[$ip] = array('mac' => $matches[2], 'name' => $matches[5], 'icon' => $matches[6]);
    }
  }

  if (count($statichosts) == 0) return;                   //Leave if nothing in statichosts array

  //Open a temporary file localhosts.list in /tmp
  $fh = fopen(DIR_TMP.'localhosts.list', 'w') or die('Unable to open '.DIR_TMP.'localhosts.list for writing');

  foreach($statichosts as $ip => $hostdata) {
    //Only write static hosts which have a name
    //IP (tab) hostname
    if ($hostdata['name'] != '') {
      fwrite($fh, "{$ip}\t{$hostdata['name']}".PHP_EOL);
    }
  }

  fclose($fh);                                             //Close Temp localhosts

  //Run ntrk-exec to copy Temp localhosts to /etc/localhosts.list
  //Restarting DNS server is handled by update_dhcp
  exec(NTRK_EXEC.'--write localhosts');
}


/********************************************************************
 *  Update DHCP
 *    Validate POST data from the various items in DHCP Config then write to dhcpconfig array
 *    Write the contents of dhcpconfic array to /tmp/dhcp.conf
 *    ntrk-exec will then restart the DHCP / DNS Server
 *
 *    dhcp-enabled, and dhcp-authoritative are tick boxes
 *    gateway_ip, start_ip, end_ip are all IP addresses, use filter_var to validate the IP
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function update_dhcp() {
  global $dhcpconfig, $statichosts;

  $matches = array();
  $hostdata = array();
  $ip = '';


  $dhcpconfig['enabled'] = isset($_POST['enabled']);
  $dhcpconfig['dhcp_authoritative'] = isset($_POST['authoritative']);

  if (isset($_POST['gateway_ip'])) {
    if (filter_var($_POST['gateway_ip'], FILTER_VALIDATE_IP) !== false) {
      $dhcpconfig['gateway_ip'] = $_POST['gateway_ip'];
    }
  }
  if (isset($_POST['start_ip'])) {
    if (filter_var($_POST['start_ip'], FILTER_VALIDATE_IP) !== false) {
      $dhcpconfig['start_ip'] = $_POST['start_ip'];
    }
  }
  if (isset($_POST['end_ip'])) {
    if (filter_var($_POST['end_ip'], FILTER_VALIDATE_IP) !== false) {
      $dhcpconfig['end_ip'] = $_POST['end_ip'];
    }
  }

  if (isset($_POST['lease_time'])) {
    if (preg_match('/\d\d?(h|d)/', $_POST['lease_time'])) {
      $dhcpconfig['lease_time'] = $_POST['lease_time'];
    }
  }

  $fh = fopen(DIR_TMP.'dhcp.conf', 'w') or die('Unable to open '.DIR_TMP.'dhcp.conf for writing');

  if ($dhcpconfig['enabled']) {
    fwrite($fh, 'dhcp-option=3,'.$dhcpconfig['gateway_ip'].PHP_EOL);
    fwrite($fh, 'dhcp-range='.$dhcpconfig['start_ip'].','.$dhcpconfig['end_ip'].','.$dhcpconfig['lease_time'].PHP_EOL);
  }
  else {
    fwrite($fh, '#dhcp-option=3,'.$dhcpconfig['gateway_ip'].PHP_EOL);
    fwrite($fh, '#dhcp-range='.$dhcpconfig['start_ip'].','.$dhcpconfig['end_ip'].','.$dhcpconfig['lease_time'].PHP_EOL);
  }

  if ($dhcpconfig['dhcp_authoritative']) {
    fwrite($fh, 'dhcp-authoritative'.PHP_EOL);
  }
  else {
    fwrite($fh, '#dhcp-authoritative'.PHP_EOL);
  }

  //Blank line to prevent #dhcp-authoritative being used as a host name
  fwrite($fh, PHP_EOL);

  //Static hosts are also written to dhcp.conf but in a different for to the above function
  foreach($statichosts as $ip => $hostdata) {
    if ($hostdata['name'] == '') {
      fwrite($fh, "dhcp-host='{$hostdata['mac']},{$ip}".PHP_EOL);
    }
    else {
      fwrite($fh, "#{$hostdata['name']},{$hostdata['icon']}".PHP_EOL);
      fwrite($fh, "dhcp-host={$hostdata['mac']},{$ip}".PHP_EOL);
    }
  }

  fclose($fh);                                             //Close Temp Conf

  //Run ntrk-exec to copy Temp conf to /etc/dnsmasq.d/dhcp.conf and then restart DNS server
  exec(NTRK_EXEC.'--write dhcp');
}


/********************************************************************
 *  Show Leases
 *    1. Sort the $leases array using natural sort function natsort
 *    2. Output the sorted list of $leases into a dhcp-table
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function show_leases() {
  global $leases;

  $iplist = array();
  $icon = '';
  $valid_until = '';
  $rowclass = '';
  $currenttime = 0;

  $currenttime = time();

  echo '<div id="tab-content-1">'.PHP_EOL;                 //Start Tab
  if (count($leases) == 0) {                               //Any devices in array?
    echo '<h4><img src=./svg/emoji_sad.svg>No devices found</h4>'.PHP_EOL;
    return;
  }

  $iplist = array_keys($leases);                           //Get list of IP Addresses
  natsort($iplist);                                        //Sort IP's with natural sort

  echo '<table class="dhcp-table">'.PHP_EOL;               //Start DHCP Table
  echo '<tr><th>IP Allocated</th><th></th><th>Device Name</th><th>MAC Address</th><th>Valid Until</th>'.PHP_EOL;

  foreach ($iplist as $ip) {                               //Go through sorted iplist

    //Set rowclass based on host being active
    $rowclass = $leases[$ip]['active'] ? '' : ' class="gray"';

    //Set icon class
    $icon = '<div class="device-'.$leases[$ip]['icon'].'">&nbsp;</div>';

    //Make sure expired time below current time is shown as 'Expired'
    if ($leases[$ip]['exptime'] >= $currenttime) {
      $valid_until = date("d M Y \- H:i:s", $leases[$ip]['exptime']);
    }
    else {
      $valid_until = 'Expired';
    }

    //Output the table row
    echo "<tr{$rowclass}><td>{$ip}</td><td>{$icon}</td><td>{$leases[$ip]['name']}</td><td>{$leases[$ip]['mac']}</td><td>{$valid_until}</td></tr>".PHP_EOL;
  }

  echo '</table>'.PHP_EOL;                                 //End DHCP Table
  echo '</div>'.PHP_EOL;                                   //End Tab
}


/********************************************************************
 *  Show Static Hosts
 *    Static Hosts utilises contenteditable divs to allow user to enter relevant data
 *    1. Sort the statichosts array using natural sort function natsort
 *    2. Output the sorted list of $statichosts into a dhcp-table
 *    3. Add a blank line at the end to allow users to enter a new host
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function show_statichosts()
{
  global $statichosts;

  $iplist = array();                                       //For sorting by IP
  $delbutton = '';                                         //Code for delete button
  $icon = '';                                              //Code for icon div and class
  $iconmenu = '';                                          //Code for icon menu

  $iconmenu = get_iconmenu();

  $iplist = array_keys($statichosts);                      //Get list of IP Addresses
  natsort($iplist);                                        //Sort IP's with natural sort

  echo '<div id="tab-content-2">'.PHP_EOL;                 //Start Tab 2

  echo '<table id="hostsTable" class="dhcp-table">'.PHP_EOL;
  echo '<tr><th>IP Allocated</th><th></th><th>Device Name</th><th>MAC Address</th><th></th>'.PHP_EOL;

  foreach ($iplist as $ip) {                               //Go through sorted iplist

    //Create delete button
    $delbutton = '<button class="button-grey material-icon-centre icon-delete" type="button" onclick="deleteRow(this)">&nbsp;</button>';

    //Create icon div and device class
    $icon = '<button type="button" class="device-'.$statichosts[$ip]['icon'].'">';

    //Output table row
    echo '<tr><td><div contenteditable="true" placeholder="192.168.0.2">'.$ip.'</div></td><td>'.$icon.$iconmenu.'</button></td><td><div contenteditable="true" placeholder="new.host">'.$statichosts[$ip]['name'].'</div></td><td><div contenteditable="true" placeholder="11:22:33:aa:bb:cc">'.$statichosts[$ip]['mac'].'</div></td><td>'.$delbutton.'</td></tr>'.PHP_EOL;
  }

  //Add blank row to table
  //Delete button will now be an add button
  $delbutton = '<button class="button-grey material-icon-centre icon-plus" type="button" onclick="addRow(this)">&nbsp;</button>';

  //Set icon menu to be a computer
  $icon = '<button type="button" class="device-computer">';

  //Output blank table row
  echo '<tr><td><div contenteditable="true" placeholder="192.168.0.2"></div></td><td>'.$icon.$iconmenu.'</button></td><td><div contenteditable="true" placeholder="new.host"></div></td><td><div contenteditable="true" placeholder="11:22:33:aa:bb:cc"></div></td><td>'.$delbutton.'</td></tr>'.PHP_EOL;

  //Save button
  echo '<tr><td colspan="5"><button type="button" onclick=submitForm(2)>Save Changes</button></td></tr>'.PHP_EOL;
  echo '</table>'.PHP_EOL;                                 //End contenteditable table
  echo '</div>'.PHP_EOL;                                   //End Tab
}


/********************************************************************
 *  Show DHCP
 *    Output DHCP Config
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function show_dhcpconfig() {
  global $dhcpconfig;

  echo '<div id="tab-content-3">'.PHP_EOL;                 //Start Tab 3

  echo '<table class="sys-table">';
  draw_sysrow('Enabled', '<input type="checkbox" name="enabled" id="enabledBox" '.is_checked($dhcpconfig['dhcp_enabled']).'>');

  echo '<tr id="confRow1"><td>Authoritative <div class="help-icon" title="Avoids long timeouts when a machine wakes up on a new network"></div>:</td><td><input type="checkbox" name="authoritative"'.is_checked($dhcpconfig['dhcp_authoritative']).'><p>Authoritative mode will barge in and take over the lease for any client which broadcasts on the network.</p></td></tr>'.PHP_EOL;

  echo '<tr id="confRow2"><td>Gateway IP <div class="help-icon" title="Usually the IP address of your Router"></div>:</td><td><input type="text" name="gateway_ip" value="'.$dhcpconfig['gateway_ip'].'"></td></tr>'.PHP_EOL;

  echo '<tr id="confRow3"><td>Range - Start IP:</td><td><input type="text" name="start_ip" value="'.$dhcpconfig['start_ip'].'"></td></tr>'.PHP_EOL;

  echo '<tr id="confRow4"><td>Range - End IP:</td><td><input type="text" name="end_ip" value="'.$dhcpconfig['end_ip'].'"></td></tr>'.PHP_EOL;

  echo '<tr id="confRow5"><td>Lease Time:</td><td><input type="text" name="lease_time" value="'.$dhcpconfig['lease_time'].'"></td></tr>'.PHP_EOL; //TODO Beautify

  //echo '<tr id="confRow6"><td>Static Hosts:</td><td><p class="light"><code>System.name,MAC Address,IP to allocate</code><br><code>e.g. nas.local,11:22:33:aa:bb:cc,192.168.0.5</code></p>';
  //echo '<textarea rows="10" name="static">'.$dhcpconfig['static_hosts'].'</textarea></td></tr>'.PHP_EOL;
  echo '<tr><td colspan="2"><div class="centered"><button type="button" onclick=submitForm(3)>Save Changes</button></div></td></tr>'.PHP_EOL;
  echo '</table>'.PHP_EOL;
  echo '</div>'.PHP_EOL;                                    //End Tab
}


/********************************************************************
 *  Show Basic View
 *    Just show the config page when no leases file can be found
 *    Requires displaying the dhcpForm and a sys-group div
 *    Then call show_dhcpconfig
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function show_basicview() {
  echo '<form id="dhcpForm" action="?" method="post">'.PHP_EOL;
  echo '<div class="sys-group">';
  echo '<p>DHCP is not currently handled by NoTrack.<br>'.PHP_EOL;
  echo 'Enable it in the config below:-</p>'.PHP_EOL;

  show_dhcpconfig();

  echo '</div>';                                           //End sys-group
  echo '</form>';                                          //End Form
}


/********************************************************************
 *  Draw Tabbed View
 *    Draw Tabbed View is called when a value is set for GET/POST argument "v"
 *    1. Check which tab to set as checked
 *    2. Draw the tabbed elements
 *
 *  Params:
 *    $view - Tab to View
 *  Return:
 *    None
 */
function draw_tabbedview($view) {
  $tab = filter_integer($view, 1, 3, 1);
  $checkedtabs = array('', '', '', '');
  $checkedtabs[$tab] = ' checked';

  echo '<form id="dhcpForm" action="?" method="post">'.PHP_EOL;
  echo '<input type="hidden" id="viewTab" name="view" value="'.$tab.'">'.PHP_EOL;
  echo '<input type="hidden" id="newHosts" name="newhosts" value="">'.PHP_EOL;

  echo '<div class="sys-group">';
  echo '<div id="tabbed">'.PHP_EOL;                        //Start tabbed container

  echo '<input type="radio" name="tabs" id="tab-nav-1"'.$checkedtabs[1].'><label for="tab-nav-1">DHCP Leases</label>'.PHP_EOL;
  echo '<input type="radio" name="tabs" id="tab-nav-2"'.$checkedtabs[2].'><label for="tab-nav-2">Static Hosts</label>'.PHP_EOL;
  echo '<input type="radio" name="tabs" id="tab-nav-3"'.$checkedtabs[3].'><label for="tab-nav-3">DHCP Config</label>'.PHP_EOL;

  echo '<div id="tabs">'.PHP_EOL;                          //Start Tabs

  show_leases();
  show_statichosts();
  show_dhcpconfig();

  echo '</div>'.PHP_EOL;                                   //End tabs
  echo '</div>'.PHP_EOL;                                   //End tabbed container
  echo '</div>'.PHP_EOL;                                   //End sys-group
  echo '</form>'.PHP_EOL;                                  //End form
}

/********************************************************************/

draw_topmenu('DHCP');
draw_sidemenu();
echo '<div id="main">'.PHP_EOL;

if (count($_POST) > 2) {                                   //Anything in POST array?
  //Set value for view from POST value
  if (isset($_POST['view'])) {
    $view = filter_integer($_POST['view'], 1, 3, 1);
  }

  //Update statichosts and DHCP config based on users input
  update_statichosts();
  update_dhcp();

  //Reload page making sure the last view is selected
  header('Location: ?view='.$view);
}


//Set value for view from GET value
if (isset($_GET['view'])) {
  $view = filter_integer($_GET['view'], 1, 3, 1);
}

load_dhcp();                                               //Load DHCP Config

if (file_exists(LEASES_FILE)) {                            //Is DHCP Active?
  load_activeleases();                                     //Load LEASES_FILE
  add_statichosts();                                       //Add static hosts to leases
  draw_tabbedview($view);                                  //Draw tabs and all tables
}

else {                                                     //No LEASES_FILE available
  show_basicview();                                        //Just display config
}

echo '</div>';

?>
<script>

/********************************************************************
 *  Icon Menu
 *    Create a select menu for the icons, similar to the PHP code
 *
 *  Params:
 *    None
 *  Return:
 *    code for a select menu
 */
function iconMenu() {
  menu = '';

  menu = '<button class="device-computer" type="button">';
  menu += '<ul>';
  menu += '<li class="device-computer" title="Computer" onclick="setIcon(this)"></li>';
  menu += '<li class="device-laptop" title="Laptop" onclick="setIcon(this)"></li>';
  menu += '<li class="device-nas" title="NAS" onclick="setIcon(this)"></li>';
  menu += '<li class="device-server" title="Server" onclick="setIcon(this)"></li>';
  menu += '<li class="device-phone" title="Phone" onclick="setIcon(this)"></li>';
  menu += '<li class="device-raspberrypi" title="Raspberry Pi" onclick="setIcon(this)"></li>';
  menu += '<li class="device-tv" title="TV" onclick="setIcon(this)"></li>';
  menu += '</ul>';
  menu += '</button>';

  return menu;
}


/********************************************************************
 *  Add Row
 *    1. Make the Add button a Del button
 *    2. Insert a new Table row 1 up from the end (above Save Changes button)
 *    3. Create Cells in new row
 *    4. Create a new Add button in the new row
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function addRow(btn) {
  let tbl = document.getElementById('hostsTable');

  //Make Add button a Delete button
  btn.className = 'button-grey material-icon-centre icon-delete';
  btn.setAttribute('onClick', 'deleteRow(this)');

  //Insert new row 1 up from end of the table
  let newRow = tbl.insertRow(tbl.rows.length -1);

  //Insert cells
  let c0 = newRow.insertCell(0);
  let c1 = newRow.insertCell(1);
  let c2 = newRow.insertCell(2);
  let c3 = newRow.insertCell(3);
  let c4 = newRow.insertCell(4);

  //Set contents of the cells
  c0.innerHTML = '<div contenteditable="true" placeholder="192.168.0.3"></div>';
  c1.innerHTML = iconMenu();
  c2.innerHTML = '<div contenteditable="true" placeholder="new.host"></div>';
  c3.innerHTML = '<div contenteditable="true" placeholder="11:22:33:aa:bb:cc"></div>';
  c4.innerHTML = '<button class="button-grey material-icon-centre icon-plus" type="button" onclick="addRow(this)">&nbsp;</button>';
}
/********************************************************************
 *  Delete Row
 *    1. Get the table cell for the button which was pressed
 *    2. Delete the parent of the cell (the table row)
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function deleteRow(btn) {
  let p = btn.parentNode.parentNode;
  p.parentNode.removeChild(p);

}


/********************************************************************
 *  Set Icon
 *    Change the icon div to show the image user has selected from dropdown menu
 *    1. Get the table cell for the button which was pressed
 *    2. Delete the parent of the cell (the table row)
 *    3. Use regex to check the menu value is one of the specified Device Icons
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function setIcon(item) {
  /*let regexDevice = /^$/;
  let p = menu.parentNode.parentNode;

  //Check if selected menu value is one of the Device Icons
  if (regexDevice.test(menu.value)) {
    p.children[1].innerHTML = '<div class="device-'+menu.value+'"></div>';
  }
*/
  let p = item.parentNode.parentNode;
  p.className = item.className;

  p.blur()
  //console.log(item.parentNode.parentNode);
}


/********************************************************************
 *  Submit Form
 *    Collect contenteditable data then add it as a JSON encoded value to newHosts
 *    1. tbl element (the contenteditable table) may be null if show_basicview is used
 *    2. Extract the device icon class from innerHTML
 *    3. Extract other values using innerText
 *    4. Add as an array to hostList
 *    5. JSON Encode hostlist
 *    6. Submit form
 *
 *  Params:
 *    Tab to return to
 *  Return:
 *    None
 */
function submitForm(returnTab) {
  let regexDevice = /^<button type=\"button\" class="device\-<?php echo REGEX_DEVICEICONS?>">/;
  let tbl = document.getElementById('hostsTable');

  let deviceIcon = '';
  let rowCount = 0;
  let hostList = Array()
  let host = '';

  //tbl won't exist when no leases file is not present
  if (tbl == null) {
    document.getElementById('dhcpForm').submit();
    return;
  }

  rowCount = tbl.rows.length - 1;

  for (let i = 1; i < rowCount; i++) {

    //Extract the div class from the device icon cell
    matches = regexDevice.exec(tbl.rows[i].children[1].innerHTML);
    if (matches != undefined) {
      deviceIcon = matches[1];
    }
    else {
      deviceIcon = '';
    }

    //Set the elemets for host based on the row cells
    host = tbl.rows[i].children[0].innerText + ',';        //IP
    host += tbl.rows[i].children[3].innerText + ',';       //MAC
    host += tbl.rows[i].children[2].innerText + ',';       //Name
    host += deviceIcon + '\n';                             //Device Icon
    hostList.push(host);
  }
  //console.log(tbl);
  document.getElementById('newHosts').value = JSON.stringify(hostList);
  document.getElementById('viewTab').value = returnTab;

  document.getElementById('dhcpForm').submit();
}


/********************************************************************
 *  Hide Config
 *    Hide the few rows below DHCP Enabled Tickbox
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function hideConfig() {
  document.getElementById('confRow1').style.display = 'none';
  document.getElementById('confRow2').style.display = 'none';
  document.getElementById('confRow3').style.display = 'none';
  document.getElementById('confRow4').style.display = 'none';
  document.getElementById('confRow5').style.display = 'none';
}


/********************************************************************
 *  Show Config
 *    Show the few rows below DHCP Enabled Tickbox
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function showConfig() {
  document.getElementById('confRow1').style.display = '';
  document.getElementById('confRow2').style.display = '';
  document.getElementById('confRow3').style.display = '';
  document.getElementById('confRow4').style.display = '';
  document.getElementById('confRow5').style.display = '';
}


/********************************************************************/
//Event listener to hide dhcp config when it is disabled
document.getElementById('enabledBox').addEventListener('change', (event) => {
  if (event.target.checked) {
    showConfig();
  } else {
    hideConfig();
  }
})

window.onload = function() {
  if (! document.getElementById('enabledBox').checked) {
    hideConfig();
  }
};
</script>
</body>
</html>
