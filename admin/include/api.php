<?php
require('./global-vars.php');
require('./global-functions.php');
require('./mysqlidb.php');
load_config();
ensure_active_session();

header('Content-Type: application/json; charset=UTF-8');

/************************************************
*Global Variables                               *
************************************************/
$response = array();
$readonly = true;

/********************************************************************
 *  Enable NoTrack
 *    Enable or Disable NoTrack Blocking
 *    Calls NTRK_EXEC with Play or Stop based on current status
 *    Race condition isn't being prevented, however we are assuming the user can't get config to load quicker than ntrk-pause can change the file
 *     
 *  Params:
 *    None
 *  Return:
 *    None
 */
function api_enable_notrack() {
  global $Config, $mem, $response;

  if ($Config['status'] & STATUS_ENABLED) {
    exec(NTRK_EXEC.'-s');
    $Config['status'] -= STATUS_ENABLED;
    $Config['status'] += STATUS_DISABLED;
  }
  elseif ($Config['status'] & STATUS_PAUSED) {
    exec(NTRK_EXEC.'-p');
    $Config['status'] -= STATUS_PAUSED;
    $Config['status'] += STATUS_ENABLED;
  }
  elseif ($Config['status'] & STATUS_DISABLED) {
    exec(NTRK_EXEC.'-p');
    $Config['status'] -= STATUS_DISABLED;
    $Config['status'] += STATUS_ENABLED;
  }
  //sleep(1);                                  //Prevent race condition
  $mem->delete('Config');                      //Force reload of config
  //load_config();
  $response['status'] = $Config['status'];
}


/********************************************************************
 *  Pause NoTrack
 *    Pause NoTrack with time parsed in POST mins
 *
 *  Params:
 *    None
 *  Return:
 *    false on error
 *    true on success
 */
function api_pause_notrack() {
  global $Config, $mem, $response;
  
  $mins = 0;

  if (! isset($_POST['mins'])) {
    $response['error'] = 'api_pause_notrack: Mins not specified';
    return false;
  }
  
  $mins = filter_integer($_POST['mins'], 1, 1440, 5);      //1440 = 24 hours in mins
  
  exec(NTRK_EXEC.'--pause '.$mins);
  
  if ($Config['status'] & STATUS_INCOGNITO) {
    $Config['status'] = STATUS_INCOGNITO + STATUS_PAUSED;
  }
  else {
    $Config['status'] = STATUS_PAUSED;    
  }
  //sleep(1);
  $mem->delete('Config');                      //Force reload of config
  //load_config();
  $response['status'] = $Config['status'];
  $response['unpausetime'] = date('H:i', (time() + ($mins * 60)));
  
  return true;
}


/********************************************************************
 *  API Incognito
 *    Switch incognito status based on bitwise value of Config[status]
 *  Params:
 *    None
 *  Return:
 *    None
 */
function api_incognito() {
  global $Config, $response;
  
  if ($Config['status'] & STATUS_INCOGNITO) $Config['status'] -= STATUS_INCOGNITO;
  else $Config['status'] += STATUS_INCOGNITO;
  $response['status'] = $Config['status'];
  
  save_config();
}


/********************************************************************
 *  API Restart
 *    Restart the system
 *    Delay execution of the command for a couple of seconds to finish off any disk writes
 *  Params:
 *    None
 *  Return:
 *    None
 */
function api_restart() {
  sleep(2);
  exec(NTRK_EXEC.'--restart');
  exit(0);
}


/********************************************************************
 *  API Shutdown
 *    Shutdown the system
 *    Delay execution of the command for a couple of seconds to finish off any disk writes
 *  Params:
 *    None
 *  Return:
 *    None
 */
function api_shutdown() {
  sleep(2);
  exec(NTRK_EXEC.'--shutdown');
  exit(0);
}

/********************************************************************
 *  API Load DNS
 *    Load DNS Log file
 *  Params:
 *    None
 *  Return:
 *    None
 */
function api_load_dns() {
  global $response;
  
  $line = '';
  $linenum = 1;
  

  if (! file_exists(DNS_LOG)) {
    $response['error'] = DNS_LOG. 'not found';
    return;
  }

  $fh = fopen(DNS_LOG, 'r') or die('Error unable to open '.DNS_LOG);
  while (!feof($fh)) {
    $line = trim(fgets($fh));                            //Read and trim line of file

    $response[$linenum] = $line;
    $linenum++;
  }
  fclose($fh);                                           //Close file
  
}


/********************************************************************
 *  Is Key Valid
 *    
 *  Params:
 *    None
 *  Return:
 *    None
 */
function is_key_valid() {
  global $Config, $readonly;

  $key = '';

  if ($Config['api_key'] == '') return false;
  
  $key = $_GET['api_key'] ?? '';
  
  if (preg_match(REGEX_VALIDAPI, $key)) {
    if ($key == $Config['api_key']) {
      $readonly = false;
      return true;
    }
    elseif ($key == $Config['api_readonly']) {
      $readonly = true;
      return true;
    }
  }

  return false;
}


/********************************************************************
 *  Do GET Action
 *    Review the specified action on GET parameter
 *    Create new sqli wrapper class
 *    Carry out the action specified by user
 *  Params:
 *    None
 *  Return:
 *    None
 */
function do_action() {
  global $Config, $response;
  
  $dbwrapper = new MySqliDb;
  
  $action = $_GET['action'] ?? '';
  
  switch ($action) {
    case 'count_blocklists':
      $response['blocklists'] = $dbwrapper->count_blocklists();
      break;
    case 'count_dnsqueries_today':
      $response = $dbwrapper->count_queries_today();
      break;
    case 'count_total_dnsqueries_today':
      $response['queries'] = $dbwrapper->count_total_queries_today();
      break;
    default:
      $response['error'] = 'Unknown Request';
  }
}
//Main---------------------------------------------------------------

/************************************************
*POST REQUESTS                                  *
************************************************/

if (isset($_POST['operation'])) {
  switch ($_POST['operation']) {
      case 'force-notrack':
        exec(NTRK_EXEC.'--force');
        sleep(3);                                //Prevent race condition
        header("Location: ?");
        break;
      case 'disable': api_enable_notrack(); break;
      case 'enable': api_enable_notrack(); break;
      case 'pause': api_pause_notrack(); break;
      case 'incognito': api_incognito(); break;
      case 'restart': api_restart(); break;
      case 'shutdown': api_shutdown(); break;
      case 'updateblocklist': exec(NTRK_EXEC.'--run-notrack'); break;
  }
}

elseif (isset($_POST['livedns'])) {
  api_load_dns();
}

elseif (sizeof($_GET) > 0) {
  if (is_key_valid()) {
    do_action();
  }
  else {
    $response['message'] = 'Invalid API Key';
  }
}

else {
  $response['error'] = 'Nothing specified';
}
echo json_encode($response);
?>
