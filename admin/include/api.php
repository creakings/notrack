<?php
require('./global-vars.php');
require('./global-functions.php');
require('./config.php');
require('./mysqlidb.php');

header('content-Type: application/json; charset=UTF-8');

/************************************************
*Global Variables                               *
************************************************/
$response = array();
$readonly = true;

/********************************************************************
 *  Enable NoTrack
 *    Enable or Disable NoTrack Blocking
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function api_enable_notrack() {
  global $config, $mem, $response;

  $newstatus = 0;

  if ($config->status & STATUS_ENABLED) {
    $newstatus = $config->status - STATUS_ENABLED;
    $newstatus += STATUS_DISABLED;
  }
  elseif ($config->status & STATUS_PAUSED) {
    $newstatus = $config->status - STATUS_PAUSED;
    $newstatus += STATUS_ENABLED;
  }
  elseif ($config->status & STATUS_DISABLED) {
    $newstatus = $config->status - STATUS_DISABLED;
    $newstatus += STATUS_ENABLED;
  }
  else {                                                   //Fallback in case of error
    $newstatus = STATUS_ENABLED;
  }

  $config->save_status($newstatus, 0);
  $response['status'] = $newstatus;
}


/********************************************************************
 *  Pause NoTrack
 *    Pause NoTrack with time parsed in POST mins
 *    Delete conf-settings from memcache so we force a load of config next page user views
 *
 *  Params:
 *    None
 *  Return:
 *    false on error
 *    true on success
 */
function api_pause_notrack() {
  global $config, $mem, $response;

  $mins = 0;
  $newstatus = 0;
  $unpausetime = 0;

  if (! isset($_POST['mins'])) {
    $response['error'] = 'api_pause_notrack: Mins not specified';
    return false;
  }

  $mins = filter_integer($_POST['mins'], 1, 1440, 5);      //1440 = 24 hours in mins
  $unpausetime = time() + ($mins * 60);

  if ($config->status & STATUS_INCOGNITO) {
    $newstatus = STATUS_INCOGNITO + STATUS_PAUSED;
  }
  else {
    $newstatus = STATUS_PAUSED;
  }

  $config->save_status($newstatus, $unpausetime);
  $response['status'] = $newstatus;
  $response['unpausetime'] = date('H:i', $unpausetime);

  return true;
}


/********************************************************************
 *  API Incognito
 *    Switch incognito status based on bitwise value of config->status
 *  Params:
 *    None
 *  Return:
 *    None
 */
function api_incognito() {
  global $config, $response;
  $newstatus = 0;

  if ($config->status & STATUS_INCOGNITO) {
    $newstatus = $config->status - STATUS_INCOGNITO;
  }
  else {
    $newstatus = $config->status + STATUS_INCOGNITO;
  }

  $config->save_status($newstatus, $config->unpausetime);
  $response['status'] = $newstatus;
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
    http_response_code(410);                               //File Gone
    $response['error_code'] = 'file_not_found';
    $response['error_message'] = DNS_LOG.' not found';
    return;
  }

  $fh = fopen(DNS_LOG, 'r') or die('Error unable to open '.DNS_LOG);
  while (!feof($fh)) {
    $line = trim(fgets($fh));                              //Read and trim line of file

    $response[$linenum] = $line;
    $linenum++;
  }
  fclose($fh);                                             //Close file

}


/********************************************************************
 *  API Recent Queries
 *    Get recent DNS queries
 *    Optional value of interval (in minutes) can be specified
 *  Params:
 *    MySqliDb class
 *  Return:
 *    None
 */
function api_recent_queries($dbwrapper) {
  global $response;

  $interval = 4;                                           //Assume 4 mins (default log collecting interval)

  //Check that interval if specified is within a valid range of one hour
  if (isset($_GET['interval'])) {
    $interval = filter_integer($_GET['interval'], 1, 60, 4);
  }

  $response = $dbwrapper->recent_queries($interval);
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
  global $config, $readonly;

  $key = '';

  if ($config->settings['api_key'] == '') return false;

  $key = $_GET['api_key'] ?? '';

  if (preg_match(REGEX_VALIDAPI, $key)) {
    if ($key == $config->settings['api_key']) {
      $readonly = false;
      return true;
    }
    elseif ($key == $config->settings['api_readonly']) {
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
  global $response;

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
    case 'get_status':
      $response['status'] = $dbwrapper->get_status();
      break;
    case 'recent_queries':
      api_recent_queries($dbwrapper);
      break;
    default:
      http_response_code(400);
      $response['error_code'] = 'missing_required_parameter';
      $response['error_message'] = 'Your request was missing an action parameter';
  }
}
//Main---------------------------------------------------------------

/************************************************
*POST REQUESTS                                  *
************************************************/

if (isset($_POST['operation'])) {
  ensure_active_session();
  switch ($_POST['operation']) {
      case 'disable': api_enable_notrack(); break;
      case 'enable': api_enable_notrack(); break;
      case 'pause': api_pause_notrack(); break;
      case 'incognito': api_incognito(); break;
      default:
        http_response_code(400);
        $response['error_code'] = 'missing_required_parameter';
        $response['error_message'] = 'Your request was missing an operation parameter';
        break;
  }
}

elseif (isset($_POST['livedns'])) {
  ensure_active_session();
  api_load_dns();
}

elseif (sizeof($_GET) > 0) {
  if (is_key_valid()) {
    do_action();
  }
  else {
    //Unauthorised
    http_response_code(401);
    $response['error_code'] = 'invalid_request';
    $response['error_message'] = 'Your client ID is invalid';
  }
}

else {
  //Bad Request
  http_response_code(400);
  $response['error_code'] = 'missing_required_parameter';
  $response['error_message'] = 'Your request was missing an action parameter';
}
echo json_encode($response);
?>
