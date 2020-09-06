<?php
require('./include/global-vars.php');
require('./include/global-functions.php');
require('./include/config.php');
require('./include/menu.php');

ensure_active_session();

/************************************************
*Constants                                      *
************************************************/
define('LEASES_FILE', '/var/lib/misc/dnsmasq.leases');
define('REGEX_DEVICEICONS', 'device\-(computer|laptop|nas|phone|raspberrypi|server|tv)');

/************************************************
*Global Variables                               *
************************************************/
$view = 1;

/************************************************
*Arrays                                         *
************************************************/
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
      $leases[$matches[3]] = array('exptime' => $matches[1], 'mac' => $matches[2], 'sysname' => $matches[4], 'sysicon' => 'computer', 'active' => true);
    }
  }

  fclose($fh);                                             //Close LEASES_FILE
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
  global $config, $leases;

  $hostinfo = array();
  $ip = '';


  foreach($config->dhcp_hosts as $ip => $hostinfo) {
    if (array_key_exists($ip, $leases)) {                  //Does this IP exist in leases array?
      $leases[$ip]['sysname'] = $hostinfo['sysname'];      //Replace the host name
      $leases[$ip]['sysicon'] = $hostinfo['sysicon'];      //Add the icon
    }
    else {                                                 //No - add host details as a new entry in leases
      $leases[$ip] = array('exptime' => 0, 'mac' => $hostinfo['mac'], 'sysname' => $hostinfo['sysname'], 'sysicon' => $hostinfo['sysicon'], 'active' => false);
    }
  }
}


/********************************************************************
 *  Draw Icon Menu
 *    Build icon select menu
 *  Params:
 *    None
 *  Return:
 *    HTML code for a select box
 */
function draw_iconmenu() {
  $menu = '';

  $menu = '<ul>';
  $menu .= '<li class="device-computer" title="Computer" onclick="setIcon(this)"></li>';
  $menu .= '<li class="device-laptop" title="Laptop" onclick="setIcon(this)"></li>';
  $menu .= '<li class="device-nas" title="NAS" onclick="setIcon(this)"></li>';
  $menu .= '<li class="device-server" title="Server" onclick="setIcon(this)"></li>';
  $menu .= '<li class="device-phone" title="Phone" onclick="setIcon(this)"></li>';
  $menu .= '<li class="device-raspberrypi" title="Raspberry Pi" onclick="setIcon(this)"></li>';
  $menu .= '<li class="device-tv" title="TV" onclick="setIcon(this)"></li>';
  $menu .= '</ul>';

  return $menu;
}


/********************************************************************
 *  Set Default Network Values
 *   Set some default values if an IP address is missing based on the Web server IP
 *
 */
function set_default_network() {
  global $config;

  //Example (192.168.0).x
  if (preg_match('/^(\d{1,3}\.\d{1,3}\.\d{1,3}\.)\d{1,3}/', $_SERVER['SERVER_ADDR'], $matches)) {
    $config->dhcp_gateway    = $matches[1].'1';
    $config->dhcp_rangestart = $matches[1].'64';
    $config->dhcp_rangeend   = $matches[1].'254';
  }

  // TODO No idea about IPv6
  elseif (preg_match('/^[0-9a-f:]+/i', $_SERVER['SERVER_ADDR'], $matches)) {
    $config->dhcp_gateway    = $matches[0].'';
    $config->dhcp_rangestart = $matches[0].':00FF';
    $config->dhcp_rangeend   = $matches[0].':FFFF';
  }

  //Not known, use the default values from Dnsmasq
  else {
    $config->dhcp_gateway    = '192.168.0.1';
    $config->dhcp_rangestart = '192.168.0.50';
    $config->dhcp_rangeend   = '192.168.0.150';
  }
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
    $icon = '<div class="device-'.$leases[$ip]['sysicon'].'">&nbsp;</div>';

    //Make sure expired time below current time is shown as 'Expired'
    if ($leases[$ip]['exptime'] > $currenttime) {
      $valid_until = date("d M \- H:i:s", $leases[$ip]['exptime']);
    }
    else {
      $valid_until = 'Expired';
    }

    //Output the table row
    echo "<tr{$rowclass}><td>{$ip}</td><td>{$icon}</td><td>{$leases[$ip]['sysname']}</td><td>{$leases[$ip]['mac']}</td><td>{$valid_until}</td></tr>".PHP_EOL;
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
  global $config;

  $iplist = array();                                       //For sorting by IP
  $delbutton = '';                                         //Code for delete button
  $icon = '';                                              //Code for icon div and class
  $iconmenu = '';                                          //Code for icon menu

  $iconmenu = draw_iconmenu();

  $statichosts = $config->dhcp_hosts;
  $iplist = array_keys($statichosts);                      //Get list of IP Addresses
  natsort($iplist);                                        //Sort IP's with natural sort

  echo '<div id="tab-content-2">'.PHP_EOL;                 //Start Tab 2

  echo '<table id="hostsTable" class="dhcp-table">'.PHP_EOL;
  echo '<tr><th>IP Allocated</th><th></th><th>Device Name</th><th>MAC Address</th><th></th>'.PHP_EOL;

  foreach ($iplist as $ip) {                               //Go through sorted iplist

    //Create delete button
    $delbutton = '<button class="button-grey material-icon-centre icon-delete" type="button" onclick="deleteRow(this)">&nbsp;</button>';

    //Create icon div and device class
    $icon = '<button type="button" class="device-'.$statichosts[$ip]['sysicon'].'">';

    //Output table row
    echo '<tr><td><div contenteditable="true" placeholder="192.168.0.2">'.$ip.'</div></td><td>'.$icon.$iconmenu.'</button></td><td><div contenteditable="true" placeholder="new.host">'.$statichosts[$ip]['sysname'].'</div></td><td><div contenteditable="true" placeholder="11:22:33:aa:bb:cc">'.$statichosts[$ip]['mac'].'</div></td><td>'.$delbutton.'</td></tr>'.PHP_EOL;
  }

  //Add blank row to table
  //Delete button will now be an add button
  $delbutton = '<button class="button-grey material-icon-centre icon-plus" type="button" onclick="addRow(this)">&nbsp;</button>';

  //Set icon menu to be a computer
  $icon = '<button type="button" class="device-computer">';

  //Output blank table row
  echo '<tr><td><div contenteditable="true" placeholder="192.168.0.2"></div></td><td>'.$icon.$iconmenu.'</button></td><td><div contenteditable="true" placeholder="new.host"></div></td><td><div contenteditable="true" placeholder="11:22:33:aa:bb:cc"></div></td><td>'.$delbutton.'</td></tr>'.PHP_EOL;

  //Save button
  echo '<tr><td colspan="5"><button type="button" onclick="submitForm(2)">Save Changes</button></td></tr>'.PHP_EOL;
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
  global $config;

  echo '<div id="tab-content-3">'.PHP_EOL;                 //Start Tab 3

  echo '<table class="sys-table">';
  draw_sysrow('Enabled', '<input type="checkbox" name="enabled" id="enabledBox" '.is_checked($config->dhcp_enabled).'>');

  echo '<tr id="confRow1"><td>Authoritative <div class="help-icon" title="Avoids long timeouts when a machine wakes up on a new network"></div>:</td><td><input type="checkbox" name="authoritative"'.is_checked($config->dhcp_authoritative).'><p>Authoritative mode will barge in and take over the lease for any client which broadcasts on the network.</p></td></tr>'.PHP_EOL;

  echo '<tr id="confRow2"><td>Gateway IP <div class="help-icon" title="Usually the IP address of your Router"></div>:</td><td><input type="text" name="gateway_ip" value="'.$config->dhcp_gateway.'"></td></tr>'.PHP_EOL;

  echo '<tr id="confRow3"><td>Range - Start IP:</td><td><input type="text" name="start_ip" value="'.$config->dhcp_rangestart.'"></td></tr>'.PHP_EOL;

  echo '<tr id="confRow4"><td>Range - End IP:</td><td><input type="text" name="end_ip" value="'.$config->dhcp_rangeend.'"></td></tr>'.PHP_EOL;

  echo '<tr id="confRow5"><td>Lease Time:</td><td><input type="text" name="lease_time" value="'.$config->dhcp_leasetime.'"></td></tr>'.PHP_EOL; //TODO Beautify

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


/********************************************************************
 *  Update Static Hosts Array
 *    Process newhosts POST data which was built from JS function submitForm
 *    1. Check if data has been posted to newhosts
 *    2. Explode the new line seperated data from newhosts into an array
 *    3. Carry out basic regex check on each line to avoid XSS vulnerabilities
 *       Further filtering of add_host is done in config
 *
 *  Regex:
 *    Group 1: IPv4 or IPv6 address
 *    Group 2: MAC Address
 *    Group 3: Name (optional)
 *    Group 4: Icon from REGEX_DEVICEICONS
 */
function update_statichosts() {
  global $config;

  $matches = array();
  $newhosts = array();
  $host = '';
  $ip = '';

  $regex_newhost = '/^\s*([\d\.:]+)\s*,\s*((?:[\dA-Fa-f]{2}:){5}[\dA-Fa-f]{2})\s*,\s*([\w\.\-_]+)?\s*,\s*'.REGEX_DEVICEICONS.'/';

  $config->dhcp_clearhosts();

  if (! isset($_POST['newhosts'])) return;                 //Leave if there is nothing in newhosts

  $newhosts = json_decode($_POST['newhosts']);             //Decode JSON data into an array

  foreach($newhosts as $host) {
    if (preg_match($regex_newhost, $host, $matches)) {     //Ensure line is valid
      //Further filtering is done in $config
      $config->dhcp_addhost($matches[1], $matches[2], $matches[3], $matches[4]);
    }
  }

}


/********************************************************************
 *  Update DHCP
 *    Assign POST items to $config->dhcp values
 *    Validation is carried out by $config
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function update_dhcp() {
  global $config;

  $config->dhcp_enabled = isset($_POST['enabled']);
  $config->dhcp_authoritative = isset($_POST['authoritative']);

  if (isset($_POST['gateway_ip'])) {
    $config->dhcp_gateway = $_POST['gateway_ip'];
  }

  if (isset($_POST['start_ip'])) {
    $config->dhcp_rangestart = $_POST['start_ip'];
  }

  if (isset($_POST['end_ip'])) {
    $config->dhcp_rangeend = $_POST['end_ip'];
  }

  if (isset($_POST['lease_time'])) {
    $config->dhcp_leasetime = $_POST['lease_time'];
  }
}


/********************************************************************/

load_serversettings();                                     //Load DHCP Settings

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
  $config->save_serversettings();
}

//Set value for view from GET value
if (isset($_GET['view'])) {
  $view = filter_integer($_GET['view'], 1, 3, 1);
}

//Check if any IP settings are blank
if (($config->dhcp_gateway == '') || ($config->dhcp_rangestart == '') || ($config->dhcp_rangeend == '')) {
  set_default_network();
}

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
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function setIcon(item) {
  let p = item.parentNode.parentNode;
  p.className = item.className;

  p.blur()
}


/********************************************************************
 *  Submit Form
 *    Collect contenteditable data then add it as a JSON encoded value to newHosts
 *    1. tbl element (the contenteditable table) may be null if show_basicview is used
 *    2. Extract the device icon class from button className
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
    //TD > Button > Button Class Name
    deviceIcon = tbl.rows[i].children[1].children[0].className;

    //Set the elemets for host based on the row cells
    host = tbl.rows[i].children[0].innerText + ',';        //IP
    host += tbl.rows[i].children[3].innerText + ',';       //MAC
    host += tbl.rows[i].children[2].innerText + ',';       //Name
    host += deviceIcon + '\n';                             //Device Icon
    hostList.push(host);
  }

  //console.log(hostList);
  document.getElementById('newHosts').value = JSON.stringify(hostList);
  document.getElementById('viewTab').value = returnTab;

  document.getElementById('dhcpForm').submit();            //Submit the form data
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
