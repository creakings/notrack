<?php
/********************************************************************
config.php handles setting of Global variables, GET, and POST requests
It also houses the functions for POST requests.

All other config functions are in ./include/config-functions.php

********************************************************************/

require('../include/global-vars.php');
require('../include/global-functions.php');
require('../include/menu.php');

load_config();
ensure_active_session();

/************************************************
*Constants                                      *
************************************************/
define('TLD_CSV', '../include/tld.csv');
define('DOMAIN_BLACKLIST', '/etc/notrack/domain-blacklist.txt');
define('DOMAIN_WHITELIST', '/etc/notrack/domain-whitelist.txt');
/************************************************
*Global Variables                               *
************************************************/


/************************************************
*Arrays                                         *
************************************************/
$list = array();                                 //Global array for all the Block Lists


/********************************************************************
 *  Load CSV List
 *    Load TLD List CSV file into $list
 *  Params:
 *    listname - blacklist or whitelist, filename
 *  Return:
 *    true on completion
 */
function load_csv($filename, $listname) {
  global $list, $mem;
    
  $list = $mem->get($listname);
  if (empty($list)) {
    $fh = fopen($filename, 'r') or die('Error unable to open '.$filename);
    while (!feof($fh)) {
      $list[] = fgetcsv($fh);
    }
    
    fclose($fh);
    if (count($list) > 50) {                               //Only store decent size list in Memcache
      $mem->set($listname, $list, 0, 120);                 //2 Minutes
    }
  }
  
  return true;
}


/********************************************************************
 *  Load List
 *    Loads a a List from File and returns it in Array form
 *    Saves $list into respective Memcache array  
 *  Params:
 *    listname - blacklist or whitelist, filename
 *  Return:
 *    array of file
 */
function load_list($filename, $listname) {
  global $mem;
  
  $filearray = array();
  
  $filearray = $mem->get($listname);
  if (empty($filearray)) {
    if (file_exists($filename)) {
      $fh = fopen($filename, 'r') or die('Error unable to open '.$filename);
      while (!feof($fh)) {
        $filearray[] = trim(fgets($fh));
      }

      fclose($fh);
      $mem->set($listname, $filearray, 0, 300);
    }
  }

  return $filearray;
}

/********************************************************************
 *  Draw Help Page
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function tld_help() {
  echo '<div>'.PHP_EOL;                                    //Start tab 4 div
  echo '<div class="sys-group">'.PHP_EOL;
  echo '<h5>Domain Blocking</h5>'.PHP_EOL;
  echo '<span class="key key-red">High</span>'.PHP_EOL;
  echo '<p>High risk domains are home to a high percentage of malicious sites compared to legitimate sites. Often they are cheap / free to buy and are not well policed.<br>'.PHP_EOL;
  echo 'High risk domains are automatically blocked, unless you specifically untick them.</p>'.PHP_EOL;
  echo '<br>'.PHP_EOL;

  echo '<span class="key key-orange">Medium</span>'.PHP_EOL;
  echo '<p>Medium risk domains are home to a significant number of malicious sites, but are outnumbered by legitimate sites. You may want to consider blocking these, unless you live in, or utilise the websites of the affected country.</p>'.PHP_EOL;  
  echo '<br>'.PHP_EOL;

  echo '<span class="key">Low</span>'.PHP_EOL;
  echo '<p>Low risk may still house some malicious sites, but they are vastly outnumbered by legitimate sites.</p>'.PHP_EOL;
  echo '<br>'.PHP_EOL;

  echo '<span class="key key-green">Negligible</span>'.PHP_EOL;
  echo '<p>These domains are not open to the public, therefore extremely unlikely to contain malicious sites.</p>'.PHP_EOL;
  echo '<br>'.PHP_EOL;

  echo '</div>'.PHP_EOL;
  echo '</div>'.PHP_EOL;                                   //End tab 4 div

}

/********************************************************************
 *  Show Domain List
 *    1. Load Users Domain Black list and convert into associative array
 *    2. Load Users Domain White list and convert into associative array
 *    3. Display list
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function show_domain_list() {
  global $list;
  
  $domain_cell = '';
  $flag_image = '';
  $flag_filename = '';
  $black = array();
  $white = array();

  $black = array_flip(load_list(DOMAIN_BLACKLIST, 'tldblacklist'));
  $white = array_flip(load_list(DOMAIN_WHITELIST, 'tldwhitelist'));
  $listsize = count($list);

  if ($list[$listsize-1][0] == '') {                       //Last line is sometimes blank
    array_splice($list, $listsize-1);                      //Cut last line out
  }

  
  echo '<div>'.PHP_EOL;  //Start Tabs
  //Tables
  echo '<div class="sys-group">'.PHP_EOL;
  if ($listsize == 0) {                                    //Is List blank?
    echo '<h4><img src=./svg/emoji_sad.svg>No sites found in Block List</h4>'.PHP_EOL;
    echo '</div>';
    return;
  }

  

  echo '<p><b>Old Generic Domains</b></p>'.PHP_EOL;
  echo '<table class="tld-table">'.PHP_EOL;                //Start TLD Table

  foreach ($list as $site) {
    if ($site[2] == 0) {                                   //Zero means draw new table
      echo '</table>'.PHP_EOL;                             //End current TLD table
      echo '<input type="submit" class="button-blue" value="Save Changes">'.PHP_EOL;
      echo '</div>'.PHP_EOL;
      echo '</div>'.PHP_EOL;
      echo '<div>'.PHP_EOL;
      echo '<div class="sys-group">'.PHP_EOL;
      echo '<p><b>'.$site[1].'</b></p>'.PHP_EOL;           //Title of new TLD Table
      echo '<table class="tld-table">'.PHP_EOL;            //Start new TLD Table
      continue;                                            //Jump to end of loop
    }

    echo '<tr>';                                           //Start Row
    switch ($site[2]) {                                    //Row colour based on risk      
      case 1: $domain_cell = '<td class="red">'; break;
      case 2: $domain_cell = '<td class="orange">'; break;
      case 3: $domain_cell = '<td>'; break;                //Use default colour for low risk
      case 5: $domain_cell = '<td class="green">'; break;
    }

    //Flag names are seperated by underscore and converted to ASCII, dropping any UTF-8 Characters
    $flag_filename = iconv('UTF-8', 'ASCII//IGNORE', str_replace(' ', '_', $site[1])); 

    //Does a Flag image exist?
    if (file_exists('./images/flags/Flag_of_'.$flag_filename.'.png')) {
      $flag_image = '<img src="./images/flags/Flag_of_'.$flag_filename.'.png" alt=""> ';
    }
    else {
      $flag_image = '';
    }

    //(Risk 1 & NOT in White List) OR (in Black List)
    if ((($site[2] == 1) && (! array_key_exists($site[0], $white))) || (array_key_exists($site[0], $black))) {
      echo $domain_cell.'<b>'.$site[0].'</b></td><td><b>'.$flag_image.$site[1].'</b></td><td>'.$site[3].'</td><td><input type="checkbox" name="'.substr($site[0], 1).'" checked="checked"></td></tr>'.PHP_EOL;
    }
    else {
      echo $domain_cell.$site[0].'</td><td>'.$flag_image.$site[1].'</td><td>'.$site[3].'</td><td><input type="checkbox" name="'.substr($site[0], 1).'"></td></tr>'.PHP_EOL;
    }
  }

  echo '</table>'.PHP_EOL;
    
  echo '<input type="submit" class="button-blue" value="Save Changes">'.PHP_EOL;
  
  echo '</div>'.PHP_EOL;
  echo '</div>'.PHP_EOL;
  

  return null;
}


/********************************************************************
 *  Update Domian List
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function update_domain_list() {
  global $list, $mem;
  
  //Start with White List
  $fh = fopen(DIR_TMP.'domain-whitelist.txt', 'w') or die('Unable to open '.DIR_TMP.'domain-whitelist.txt for writing');
  
  fwrite($fh, '#Domain White list generated by tld.php'.PHP_EOL);
  fwrite($fh, '#Do not make any changes to this file'.PHP_EOL);
  
  foreach ($list as $site) {                               //Generate White list based on unticked Risk 1 items
    if ($site[2] == 1) {
      if (! isset($_POST[substr($site[0], 1)])) {          //Check POST for domain minus preceding .
        fwrite($fh, $site[0].PHP_EOL);                     //Add domain to White list
      }
    }
  }
  fclose($fh);                                             //Close White List


  //Write Black List
  $fh = fopen(DIR_TMP.'domain-blacklist.txt', 'w') or die('Unable to open '.DIR_TMP.'domain-blacklist.txt for writing');
    
  fwrite($fh, '#Domain Block list generated by tld.php'.PHP_EOL);
  fwrite($fh, '#Do not make any changes to this file'.PHP_EOL);
  
  foreach ($_POST as $key => $value) {                     //Generate Black list based on ticked items in $_POST
    if ($key != 'tabs') {
      if ($value == 'on') fwrite($fh, '.'.$key.PHP_EOL);   //Add each item of POST if value is 'on' (checked)
    }
  }
  fclose($fh);                                             //Close Black List

  exec(NTRK_EXEC.'--copy tld');

  $mem->delete('tldblacklist');                            //Delete Black List from Memcache
  $mem->delete('tldwhitelist');                            //Delete White List from Memcache
  
  return null;
}

/************************************************
*POST REQUESTS                                  *
************************************************/
//Deal with POST actions first, that way we can reload the page and remove POST requests from browser history.
if (isset($_POST['action'])) {
  switch($_POST['action']) {
    case 'tld':
      load_csv(TLD_CSV, 'csvtld');              //Load tld.csv
      update_domain_list();
      sleep(1);                                  //Prevent race condition
      header('Location: ../config/tld.php');                //Reload page
      break;
    default:
      die('Unknown POST action');
  }
}


load_csv(TLD_CSV, 'csvtld');

//-------------------------------------------------------------------
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <link href="../css/master.css" rel="stylesheet" type="text/css">
  <link href="../css/tabbed.css" rel="stylesheet" type="text/css">
  <link rel="icon" type="image/png" href="../favicon.png">
  <script src="../include/config.js"></script>
  <script src="../include/menu.js"></script>
  <meta name="viewport" content="width=device-width, initial-scale=0.9">
  <title>NoTrack - Config</title>
</head>

<body>
<?php
draw_topmenu('Config');
draw_sidemenu();

echo '<div id="main">'.PHP_EOL;
echo '<form name="tld" action="?" method="post">'.PHP_EOL;
echo '<input type="hidden" name="action" value="tld">'.PHP_EOL;
echo '<div id="tabbed">'.PHP_EOL;                          //Start tabbed container

echo '<input type="radio" name="tabs" id="tab-nav-1" checked><label for="tab-nav-1">Old Generic</label>'.PHP_EOL;
echo '<input type="radio" name="tabs" id="tab-nav-2"><label for="tab-nav-2">New Generic</label>'.PHP_EOL;
echo '<input type="radio" name="tabs" id="tab-nav-3"><label for="tab-nav-3">Country</label>'.PHP_EOL;
echo '<input type="radio" name="tabs" id="tab-nav-4"><label for="tab-nav-4">Help</label>'.PHP_EOL;

echo '<div id="tabs">'.PHP_EOL;

show_domain_list();
tld_help();
echo '</div>'.PHP_EOL;                                     //End tabs
echo '</div>'.PHP_EOL;                                     //End tabbed container
echo '</form>'.PHP_EOL;                                    //End form
?>

</div>
</body>
</html>
