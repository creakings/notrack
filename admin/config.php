<?php
/********************************************************************
config.php handles setting of Global variables, GET, and POST requests
It also houses the functions for POST requests.

All other config functions are in ./include/config-functions.php

********************************************************************/

require('./include/global-vars.php');
require('./include/global-functions.php');
require('./include/config.php');
require('./include/menu.php');
require('./include/config-functions.php');

ensure_active_session();

/************************************************
*Constants                                      *
************************************************/


/************************************************
*Global Variables                               *
************************************************/
$page = 1;
$searchbox = '';
$showblradio = false;
$blradio = 'all';
$db = new mysqli(SERVERNAME, USERNAME, PASSWORD, DBNAME);

/************************************************
*Arrays                                         *
************************************************/
$DHCPConfig = array();
$list = array();                                 //Global array for all the Block Lists

/************************************************
*POST REQUESTS                                  *
************************************************/
//Deal with POST actions first, that way we can reload the page and remove POST requests from browser history.
if (isset($_POST['action'])) {
  switch($_POST['action']) {
    case 'advanced':
      if (update_advanced()) {                   //Are users settings valid?
        $config->save();                         //If ok, then save the Config file        
        sleep(1);                                //Short pause to prevent race condition
        exec(NTRK_EXEC.'--parsing');             //Update ParsingTime value in Cron job
      }      
      header('Location: ?v=advanced');           //Reload page
      break;
    case 'dhcp':
      update_dhcp();
      header('Location: ?v=dhcp');               //Reload to DHCP
      break;
    case 'webserver':
      update_webserver_config();
      $config->save();
      header('Location: ?');
      break;
    case 'stats':
      if (update_stats_config()) {
        $config->save();
        sleep(1);                                //Short pause to prevent race condition
        header('Location: ?v=general');
      }
      break;
    /*case 'tld':
      load_csv(TLD_FILE, 'CSVTld');              //Load tld.csv
      update_domain_list();      
      sleep(1);                                  //Prevent race condition
      header('Location: ?v=tld');                //Reload page
      break;*/
    default:
      die('Unknown POST action');
  }
}
//-------------------------------------------------------------------
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <link href="./css/master.css" rel="stylesheet" type="text/css">
  <link href="./css/icons.css" rel="stylesheet" type="text/css">
  <link rel="icon" type="image/png" href="./favicon.png">
  <script src="./include/config.js"></script>
  <script src="./include/menu.js"></script>
  <meta name="viewport" content="width=device-width, initial-scale=0.9">
  <title>NoTrack - Config</title>
</head>

<body>
<?php
draw_topmenu('Config');
draw_sidemenu();
echo '<div id="main">';


/********************************************************************
 *  Update Advanced Config
 *    1. Make sure Suppress list is valid
 *    1a. Replace new line and space with commas
 *    1b. If string too short, set to '' then leave
 *    1c. Copy Valid URL's to a ValidList array
 *    1d. Write valid URL's to Config Suppress string seperated by commas 
 *  Params:
 *    None
 *  Return:
 *    None
 */
function update_advanced() {
  
  global $config;
  
  $suppress = '';
  $suppresslist = array();
  $validlist = array();
  
  if (isset($_POST['parsing'])) {
    $config->settings['ParsingTime'] = filter_integer($_POST['parsing'], 1, 60, 7);
  }
  
  if (isset($_POST['suppress'])) {
    $suppress = preg_replace('#\s+#',',',trim($_POST['suppress'])); //Split array
    if (strlen($suppress) <= 2) {                //Is string too short?
      $config->settings['Suppress'] = '';
      return true;
    }
    
    $suppresslist = explode(',', $suppress);     //Split string into array
    foreach ($suppresslist as $site) {           //Check if each item is a valid URL
      if (filter_url($site)) {  //TODO FIX THIS!
        $validlist[] = strip_tags($site);
      }
    }
    if (sizeof($validlist) == 0) $config->settings['Suppress'] = '';
    else $config->settings['Suppress'] = implode(',', $validlist);
  }
  
  return true;
}


/********************************************************************
 *  Update Stats Config
 *
 *  Params:
 *    None
 *  Return:
 *    True if change has been made or False if nothing changed
 */
function update_stats_config() {
  global $config;
  
  $updated = false;

  if (isset($_POST['search'])) {
    if (array_key_exists($_POST['search'], $config::SEARCHENGINELIST)) {      
      $config->settings['Search'] = $_POST['search'];
      $config->settings['SearchUrl'] = $config::SEARCHENGINELIST[$_POST['search']];
      $updated = true;
    }
  }
  
  if (isset($_POST['whois'])) {    
    if (array_key_exists($_POST['whois'], $config::WHOISLIST)) {
      $config->settings['WhoIs'] = $_POST['whois'];
      $config->settings['WhoIsUrl'] = $config::WHOISLIST[$_POST['whois']];
      $updated = true;
    }
  }
  
  if (isset($_POST['whoisapi'])) {                         //Validate whoisapi
    if (strlen($_POST['whoisapi']) < 50) {                 //Limit input length
      if (ctype_xdigit($_POST['whoisapi'])) {              //Is input hexadecimal?
        $config->settings['whoisapi'] = $_POST['whoisapi'];
        $updated = true;
      }
      else {
        $config->settings['whoisapi'] = '';
      }
    }
  }  
  
  return $updated;
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
 *  Update Webserver Config
 *    Run ntrk-exec with appropriate change to Webserver setting
 *    Onward process is save_config function
 *  Params:
 *    None
 *  Return:
 *    None
 */
function update_webserver_config() {
  global $config;  
  
  if (isset($_POST['block'])) {
    switch ($_POST['block']) {
      case 'message':
        $config->settings['blockmessage'] = 'message';
        exec(NTRK_EXEC.'--bm-msg');
        break;
      case 'pixel':
        $config->settings['blockmessage'] = 'pixel';
        exec(NTRK_EXEC.'--bm-pxl');
        break;      
    }
  }
}

//Main---------------------------------------------------------------

/************************************************
*GET REQUESTS                                   *
************************************************/
if (isset($_GET['s'])) {                         //Search box
  //Allow only characters a-z A-Z 0-9 ( ) . _ - and \whitespace
  $searchbox = preg_replace('/[^a-zA-Z0-9\(\)\.\s\_\-]/', '', $_GET['s']);
  $searchbox = strtolower($searchbox);  
}

if (isset($_GET['page'])) {
  $page = filter_integer($_GET['page'], 1, PHP_INT_MAX, 1);
}

if (isset($_POST['showblradio'])) {
  if ($_POST['showblradio'] == 1) {
    $showblradio = true;
  }
}

if (isset($_GET['blrad'])) {
  if ($_GET['blrad'] == 'all') {
    $blradio = 'all';
    $showblradio = true;
  }
  elseif (array_key_exists($_GET['blrad'], $config::BLOCKLISTNAMES)) {
    $blradio = $_GET['blrad'];
    $showblradio = true;
  }
}

if (isset($_GET['action'])) {
  switch($_GET['action']) {
    case 'delete-history':
      exec(NTRK_EXEC.'--delete-history');
      show_general();
      break;
  }
}

if (isset($_GET['v'])) {                         //What view to show?
  switch($_GET['v']) {
    case 'config':
      show_general();
      break;
    case 'advanced':
      show_advanced();
      break;
    case 'status':
      show_status();
      break;
    /*case 'tld':
      load_csv(TLD_FILE, 'csv_tld');
      show_domain_list();     
      break;*/
    default:
      show_general();
      break;
  }
}
else {                                           //No View set
  show_menu();
}

$db->close();
?> 
</div>
<script>
function confirmLogDelete() {
  if (confirm("Are you sure you want to delete all History?")) window.open("?action=delete-history", "_self");
}
</script>
</body>
</html>
