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

ensure_active_session();

/************************************************
*Constants                                      *
************************************************/
define('WEBLIST', ['lighttpd', 'apache', 'nginx']);


/************************************************
*Global Variables                               *
************************************************/


/************************************************
*Arrays                                         *
************************************************/


/************************************************
*POST REQUESTS                                  *
************************************************/
//Deal with POST actions first, that way we can reload the page and remove POST requests from browser history.
if (isset($_POST['action'])) {
  update_server_config();
  usleep(15000);                             //Short pause to prevent race condition
  $config->save();

  //Refresh page to anchor point if action is valid
  if (($_POST['action'] == 'dnsqueries') || ($_POST['action'] == 'webserver') || ($_POST['action'] == 'server')) {
    header('Location: #'.$_POST['action']);
    exit;
  }
  else {
    header('Location: #');
    exit;
  }
}

if (isset($_GET['action'])) {
  if ($_GET['action'] == 'delete-history') {
    exec(NTRK_EXEC.'--delete-history');  //DEPRECATED
    echo '<pre>';
    passthru(NTRK_EXEC.'--deletehistory');
    echo '</pre>'.PHP_EOL;
    exit;
    //usleep(15000);                               //Short pause to prevent race condition
    //header('Location: #dns');
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
  draw_systable('Server');
  draw_sysrow('Memory Used', $freemem[2].' MB');
  draw_sysrow('Free Memory', $freemem[3].' MB');
  draw_sysrow('Uptime', $uptime);
  draw_sysrow('NoTrack Version', VERSION);

  //Search Engine select box
  echo '<tr><td>Search Engine: </td>'.PHP_EOL;
  echo '<td><select name="search" class="input-conf" onchange="submitForm(\'server\')">'.PHP_EOL;
  echo '<option value="'.$config->settings['Search'].'">'.$config->settings['Search'].'</option>'.PHP_EOL;
  foreach ($config::SEARCHENGINELIST as $key => $value) {
    if ($key != $config->settings['Search']) {
      echo '<option value="'.$key.'">'.$key.'</option>'.PHP_EOL;
    }
  }
  echo '</select></td></tr>'.PHP_EOL;

  //Whois select box
  echo '<tr><td>Who Is Lookup: </td>'.PHP_EOL;
  echo '<td><select name="whois" class="input-conf" onchange="submitForm(\'server\')">'.PHP_EOL;
  echo '<option value="'.$config->settings['WhoIs'].'">'.$config->settings['WhoIs'].'</option>'.PHP_EOL;
  foreach ($config::WHOISLIST as $key => $value) {
    if ($key != $config->settings['WhoIs']) {
      echo '<option value="'.$key.'">'.$key.'</option>'.PHP_EOL;
    }
  }
  echo '</select></td></tr>'.PHP_EOL;

  draw_sysrow('JsonWhois API <a href="https://jsonwhois.com/"><div class="help-icon"></div></a>', '<input type="text" name="whoisapi" class="input-conf" value="'.$config->settings['whoisapi'].'" onkeydown="if (event.keyCode == 13) { submitForm(\'server\'); return false; }">');

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

  echo '<section id="webserver">'.PHP_EOL;
  //echo '<form name="blockmsg" action="?" method="post">'.PHP_EOL;
  //echo '<input type="hidden" name="action" value="webserver">'.PHP_EOL;
  draw_systable('Web Server');
  draw_sysrow('Status', $pidarray[0]);
  draw_sysrow('Pid', $pidarray[1]);
  draw_sysrow('Started On', $pidarray[2]);
  draw_sysrow('Memory Used', $pidarray[3].' MB');

  echo '</table></div>'.PHP_EOL;
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
 Main
*/
draw_topmenu('Status');
draw_sidemenu();

echo '<div id="main">'.PHP_EOL;

//Draw all the sections
echo '<form name="generalform" method="post">'.PHP_EOL;   //Form is to encapsulate all sections
echo '<input type="hidden" id="action" name="action" value="">'.PHP_EOL;
server_section();
web_section();
echo '</form>'.PHP_EOL;                                    //End Form
echo '</div>'.PHP_EOL;                                     //End main
?>

<script>
function confirmLogDelete() {
  if (confirm("Are you sure you want to delete all History?")) window.open("?action=delete-history", "_self");
}

function submitForm(toAction) {
  document.getElementById('action').value = toAction;
  document.generalform.submit()
}
</script>
</body>
</html>
