<?php
/********************************************************************
*  1. Deal with POST action requests first
*     1a. Carry out necessary input validation
*     1b. Save config
*     1c. Sort pause of 0.25 seconds to prevent race condition of loading wrong conf
*     1b. Reload the page to updated section
*  2. Draw various sections with form including hidden action,
*     so we know where to return the user to
********************************************************************/
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
define('WEBLIST', ['lighttpd', 'apache', 'nginx']);


/************************************************
*Global Variables                               *
************************************************/
$dbwrapper = new MySqliDb();


/************************************************
*Arrays                                         *
************************************************/


/************************************************
*POST REQUESTS                                  *
************************************************/
//Deal with POST actions first, that way we can reload the page and remove POST requests from browser history.
if (isset($_POST['action'])) {
  switch($_POST['action']) {
    case 'dnsqueries':
      update_dnsqueries();
      $config->save();
      usleep(25000);                             //Short pause to prevent race condition
      exec(NTRK_EXEC.'--parsing');               //Update ParsingTime value in Cron job
      header('Location: #dnsqueries');
      break;
    case 'webserver':
      update_webserver_config();
      $config->save();
      header('Location: #web');
      break;
    case 'server':
      update_server_config();
      $config->save();
      usleep(25000);                             //Short pause to prevent race condition
      header('Location: #server');
      break;
    default:
      die('Unknown POST action');
  }
}

if (isset($_GET['action'])) {
  if ($_GET['action'] == 'delete-history') {
    exec(NTRK_EXEC.'--delete-history');
    usleep(25000);                               //Short pause to prevent race condition
    header('Location: ?#dns');
  }
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
  <meta name="viewport" content="width=device-width, initial-scale=0.9">
  <title>NoTrack - General Config</title>
</head>
<?php


/********************************************************************
 *  Show Server Section
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function server_section() {
  global $config;

  $key = '';
  $value = '';

  $freemem = preg_split('/\s+/', exec('free -m | grep Mem'));
  $uptime = exec('uptime -p');

  echo '<section id="server">'.PHP_EOL;
  echo '<form name="server" method="post">'.PHP_EOL;
  echo '<input type="hidden" name="action" value="server">'.PHP_EOL;
  draw_systable('Server');
  draw_sysrow('Name', gethostname());
  draw_sysrow('Network Device', $config->settings['NetDev']);
  if (($config->settings['IPVersion'] == 'IPv4') || ($config->settings['IPVersion'] == 'IPv6')) {
    draw_sysrow('Internet Protocol', $config->settings['IPVersion']);
    draw_sysrow('IP Address', $_SERVER['SERVER_ADDR']);
  }
  else {
    draw_sysrow('IP Address', $config->settings['IPVersion']);
  }

  draw_sysrow('Memory Used', $freemem[2].' MB');
  draw_sysrow('Free Memory', $freemem[3].' MB');
  draw_sysrow('Uptime', $uptime);
  draw_sysrow('NoTrack Version', VERSION);

  //Search Engine select box
  echo '<tr><td>Search Engine: </td>'.PHP_EOL;
  echo '<td><select name="search" class="input-conf" onchange="submit()">'.PHP_EOL;
  echo '<option value="'.$config->settings['Search'].'">'.$config->settings['Search'].'</option>'.PHP_EOL;
  foreach ($config::SEARCHENGINELIST as $key => $value) {
    if ($key != $config->settings['Search']) {
      echo '<option value="'.$key.'">'.$key.'</option>'.PHP_EOL;
    }
  }
  echo '</select></td></tr>'.PHP_EOL;

  //Whois select box
  echo '<tr><td>Who Is Lookup: </td>'.PHP_EOL;
  echo '<td><select name="whois" class="input-conf" onchange="submit()">'.PHP_EOL;
  echo '<option value="'.$config->settings['WhoIs'].'">'.$config->settings['WhoIs'].'</option>'.PHP_EOL;
  foreach ($config::WHOISLIST as $key => $value) {
    if ($key != $config->settings['WhoIs']) {
      echo '<option value="'.$key.'">'.$key.'</option>'.PHP_EOL;
    }
  }
  echo '</select></td></tr>'.PHP_EOL;

  draw_sysrow('JsonWhois API <a href="https://jsonwhois.com/"><div class="help-icon"></div></a>', '<input type="text" name="whoisapi" class="input-conf" value="'.$config->settings['whoisapi'].'">');

  echo '</table></div>'.PHP_EOL;
  echo '</form>'.PHP_EOL;
  echo '</section>'.PHP_EOL;
}


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
function dns_section() {
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
  draw_sysrow('Delete All History', '<button class="button-danger" onclick="confirmLogDelete();">Purge</button>');
  echo '</table></div>'.PHP_EOL;
  echo '</section>'.PHP_EOL;
}


/********************************************************************
 *  Show Web Server Section
 *    1. Find running web server from WEBLIST using ps
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
function web_section() {
  global $config, $dbwrapper;

  $pidstr = '';
  $pidarray = array();

  foreach (WEBLIST AS $app) {
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

  echo '<section id="web">'.PHP_EOL;
  echo '<form name="blockmsg" action="?" method="post">'.PHP_EOL;
  echo '<input type="hidden" name="action" value="webserver">'.PHP_EOL;
  draw_systable('Web Server');
  draw_sysrow('Status', $pidarray[0]);
  draw_sysrow('Pid', $pidarray[1]);
  draw_sysrow('Started On', $pidarray[2]);
  draw_sysrow('Memory Used', $pidarray[3].' MB');

  if ($config->settings['blockmessage'] == 'pixel') draw_sysrow('Block Message', '<input type="radio" name="block" value="pixel" checked onclick="document.blockmsg.submit()">1x1 Blank Pixel (default)<br><input type="radio" name="block" value="message" onclick="document.blockmsg.submit()">Message - Blocked by NoTrack<br>');
  else draw_sysrow('Block Message', '<input type="radio" name="block" value="pixel" onclick="document.blockmsg.submit()">1x1 Blank Pixel (default)<br><input type="radio" name="block" value="messge" checked onclick="document.blockmsg.submit()">Message - Blocked by NoTrack<br>');
  echo '</table></div></form>'.PHP_EOL;
  echo '</section>'.PHP_EOL;
}


/********************************************************************
 *  DNS Queries
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function dnsqueries_section() {
  global $config;

  echo '<section id="dnsqueries">'.PHP_EOL;
  echo '<form action="?" method="post">'.PHP_EOL;
  echo '<input type="hidden" name="action" value="dnsqueries">'.PHP_EOL;
  draw_systable('DNS Queries');
  draw_sysrow('DNS Log Parsing Interval', '<input type="number" class="fixed10" name="parsing" min="1" max="60" value="'.$config->settings['ParsingTime'].'" title="Time between updates in Minutes" onchange="submit()">');
  draw_sysrow('Suppress Domains <div class="help-icon" title="Group together certain domains on the Stats page"></div>', '<textarea rows="5" name="suppress">'.str_replace(',', PHP_EOL, $config->settings['Suppress']).'</textarea>');
  echo '<tr><td>&nbsp;</td><td><input type="submit" value="Save Changes"></td></tr>'.PHP_EOL;
  echo '</table>'.PHP_EOL;
  echo '</div>'.PHP_EOL;
  echo '</form>'.PHP_EOL;
  echo '</section>'.PHP_EOL;
}

/********************************************************************
 *  Update Server Config
 *    1. Check for each value in POST array
 *    2. Carry out string length validation
 *    3. Check if value exists in SEARCHENGINELIST / WHOISLIST
 *    4. Only except hexadecimal values for whoisapi
 *    5. Change values in config
 *    6. Onward function is config->save()
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function update_server_config() {
  global $config;

  if (filter_string('search', 'POST', 16)) {
    if (array_key_exists($_POST['search'], $config::SEARCHENGINELIST)) {
      $config->settings['Search'] = $_POST['search'];
      $config->settings['SearchUrl'] = $config::SEARCHENGINELIST[$_POST['search']];
    }
  }

  if (filter_string('whois', 'POST', 16)) {
    if (array_key_exists($_POST['whois'], $config::WHOISLIST)) {
      $config->settings['WhoIs'] = $_POST['whois'];
      $config->settings['WhoIsUrl'] = $config::WHOISLIST[$_POST['whois']];
    }
  }

  if (filter_string('whoisapi', 'POST', 48)) {
    if (ctype_xdigit($_POST['whoisapi'])) {              //Is input hexadecimal?
      $config->settings['whoisapi'] = $_POST['whoisapi'];
    }
    elseif($_POST['whoisapi'] == '') {
      $config->settings['whoisapi'] = '';
    }
  }
}


/********************************************************************
 *  Update Webserver Config
 *    1. Check POST value block is valid
 *    2. Run ntrk-exec with appropriate change to Webserver setting
 *    3. Onward function is config->save()
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function update_webserver_config() {
  global $config;

  if (filter_string('block', 'POST', 16)) {
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


/********************************************************************
 *  Update DNS Queries
 *    1. Update parsing time if valid
 *    2. Make sure Suppress list is valid
 *    3. Replace new line and space with commas (since list maybe in any format)
 *    4. Copy Valid domains to a validarray
 *    5. Write valid domains to Config suppress string seperated by commas
 *    6. Onward function is config->save()
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function update_dnsqueries() {
  global $config;

  $domain = '';                                            //Each domain identified
  $suppress = '';                                          //POST value suppress
  $suppressarray = array();                                //Array of items in suppress
  $validarray = array();                                   //Array of valid domains

  if (isset($_POST['parsing'])) {
    $config->settings['ParsingTime'] = filter_integer($_POST['parsing'], 1, 60, 4);
  }

  if (filter_string('suppress', 'POST', 4096)) {
    $suppress = strip_tags($_POST['suppress']);

    //Replace spaces / newlines with commas for processing below
    $suppress = preg_replace('/\s+/',',', $suppress);

    if (strlen($suppress) <= 2) {                //Is string too short?
      $config->settings['Suppress'] = '';
    }

    $suppressarray = explode(',', $suppress);              //Split string into array
    foreach ($suppressarray as $domain) {                  //Check if each item is a valid domain
      if (filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
        $validarray[] = $domain;                           //Add valid domain to array
      }
    }

    //Use a blank string for suppress if nothing is in validarray
    if (sizeof($validarray) == 0) {
      $config->settings['Suppress'] = '';
    }
    //Or implode validarray into comma seperated values
    else {
      $config->settings['Suppress'] = implode(',', $validarray);
    }
  }
}

/********************************************************************
 Main
*/
draw_topmenu('Status');
draw_sidemenu();

echo '<div id="main">'.PHP_EOL;

//Draw all the sections
server_section();
dns_section();
web_section();
dnsqueries_section();

echo '</div>'.PHP_EOL;                                     //End main
?>

<script>
function confirmLogDelete() {
  if (confirm("Are you sure you want to delete all History?")) window.open("?action=delete-history", "_self");
}
</script>
</body>
</html>
