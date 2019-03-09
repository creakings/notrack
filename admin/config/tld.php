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

    $mem->set($listname, $list, 0, 120);                   //2 Minutes
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
  echo '<p>NoTrack has the ability to block certain top-level domains, this comes in useful against certain domains which are abused by malicious actors. Sites can be created very quickly with the purpose of hosting malware and phishing sites, which can inflict a significant amount of damage before the security community can identify and block them.</p>'.PHP_EOL;
  echo '<p>Domains are categorised by a risk level: High, Medium, Low, and Negligible. The risk level has been taken from <u><a href="https://www.spamhaus.org/statistics/tlds/">Spamhaus</a></u>, <u><a href="https://krebsonsecurity.com/tag/top-20-shady-top-level-domains/">Krebs on Security</a></u>, <u><a href="https://www.symantec.com/blogs/feature-stories/top-20-shady-top-level-domains">Symantec</a></u>, and my own experience of dealing with Malware and Phishing campaigns in an Enterprise environment</p>'.PHP_EOL;
  echo '<br>'.PHP_EOL;
  echo '<span class="key key-red">High</span>'.PHP_EOL;
  echo '<p>High risk domains are home to a high percentage of malicious sites compared to legitimate sites. Often websites within these domains are cheap or even free, and the domains are not well policed.<br>'.PHP_EOL;
  echo 'High risk domains are automatically blocked, unless you specifically untick them.</p>'.PHP_EOL;
  echo '<br>'.PHP_EOL;

  echo '<span class="key key-orange">Medium</span>'.PHP_EOL;
  echo '<p>Medium risk domains are home to a significant number of malicious sites, but are outnumbered by legitimate sites. You may want to consider blocking these, unless you live in, or utilise the websites of the affected country.</p>'.PHP_EOL;
  echo '<br>'.PHP_EOL;

  echo '<span class="key">Low</span>'.PHP_EOL;
  echo '<p>Low risk may still house some malicious sites, but they are vastly outnumbered by legitimate sites.</p>'.PHP_EOL;
  echo '<br>'.PHP_EOL;

  echo '<span class="key key-green">Negligible</span>'.PHP_EOL;
  echo '<p>These domains are not open to the public, and therefore extremely unlikely to contain malicious sites.</p>'.PHP_EOL;
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
  $domain_name = '';
  $flag_image = '';
  $flag_filename = '';
  $black = array();
  $white = array();

  $black = array_flip(load_list(DOMAIN_BLACKLIST, 'tldblacklist'));
  $white = array_flip(load_list(DOMAIN_WHITELIST, 'tldwhitelist'));
  $listsize = count($list);

  if ($list[$listsize-1][0] == '') {                       //Last line is sometimes blank
    array_splice($list, $listsize-1);                      //Cut last blank line out
  }

  echo '<div>'.PHP_EOL;                                    //Start Tab
  echo '<div class="sys-group">'.PHP_EOL;
  if ($listsize == 0) {                                    //Is List blank?
    echo '<h4><img src=./svg/emoji_sad.svg>No sites found in Block List</h4>'.PHP_EOL;
    echo '</div>';
    return;
  }

  echo '<h5>Old Generic Domains</h5>'.PHP_EOL;
  echo '<table class="tld-table">'.PHP_EOL;                //Start TLD Table

  foreach ($list as $line) {
    //1. Domain
    //2. Domain Name
    //3. Risk
    //4. Comment

    if ($line[2] == 0) {                                   //Risk zero means draw new table
      echo '</table>'.PHP_EOL;                             //End current TLD table
      echo '<input type="submit" value="Save Changes">'.PHP_EOL;
      echo '</div>'.PHP_EOL;                               //End sys-group
      echo '</div>'.PHP_EOL;                               //End Tab

      echo '<div>'.PHP_EOL;                                //Start new Tab
      echo '<div class="sys-group">'.PHP_EOL;              //Start new sys-group
      echo '<h5>'.$line[1].'</h5>'.PHP_EOL;                //Title of new TLD Table
      echo '<table class="tld-table">'.PHP_EOL;            //Start new TLD Table
      continue;                                            //Jump to end of loop
    }

    $domain_name = substr($line[0], 1);

    echo '<tr>';                                           //Start Row
    switch ($line[2]) {                                    //Cell colour based on risk
      case 1: $domain_cell = '<td class="red">'; break;
      case 2: $domain_cell = '<td class="orange">'; break;
      case 3: $domain_cell = '<td>'; break;                //Use default colour for low risk
      case 5: $domain_cell = '<td class="green">'; break;
    }

    //Flag names are seperated by underscore and converted to ASCII, dropping any UTF-8 Characters
    $flag_filename = iconv('UTF-8', 'ASCII//IGNORE', str_replace(' ', '_', $line[1]));

    //Does a Flag image exist?
    if (file_exists('../images/flags/Flag_of_'.$flag_filename.'.png')) {
      $flag_image = '<img src="../images/flags/Flag_of_'.$flag_filename.'.png" alt=""> ';
    }
    //TODO: Rename flags to this format
    elseif (file_exists('../images/flags/flag_of_'.$domain_name.'.png')) {
      $flag_image = '<img src="../images/flags/flag_of_'.$domain_name.'.png" alt=""> ';
    }
    else {
      $flag_image = '';
    }

    //Embolden Domain and Domain Name, and check checkbox of blocked domains
    //Condition for not blocking - (Risk 1 & NOT in White List) OR (in Black List)
    if ((($line[2] == 1) && (! array_key_exists($line[0], $white))) || (array_key_exists($line[0], $black))) {
      echo $domain_cell.'<b>'.$line[0].'</b></td><td><b>'.$flag_image.$line[1].'</b></td><td>'.$line[3].'</td><td><input type="checkbox" name="'.$domain_name.'" checked="checked"></td></tr>'.PHP_EOL;
    }
    else {
      echo $domain_cell.$line[0].'</td><td>'.$flag_image.$line[1].'</td><td>'.$line[3].'</td><td><input type="checkbox" name="'.$domain_name.'"></td></tr>'.PHP_EOL;
    }
  }

  echo '</table>'.PHP_EOL;
  echo '<input type="submit" value="Save Changes">'.PHP_EOL;

  echo '</div>'.PHP_EOL;                                   //End last sys-group
  echo '</div>'.PHP_EOL;                                   //End Tab

  return null;
}


/********************************************************************
 *  Update Domian List
 *    1. Write domain-whitelist.txt to /tmp
 *    2. Write domain-blacklist.txt to /tmp
 *    3. Call NTRK_EXEC to copy domain lists over
 *    4. Delete Memcache items to force reload
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
  load_csv(TLD_CSV, 'csvtld');                             //Load tld.csv
  update_domain_list();
  sleep(1);                                                //Prevent race condition
  header('Location: ../config/tld.php');                   //Reload page
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
  <script src="../include/menu.js"></script>
  <meta name="viewport" content="width=device-width, initial-scale=0.9">
  <title>NoTrack - Domains</title>
</head>

<body>
<?php
draw_topmenu('Domains');
draw_sidemenu();

echo '<div id="main">'.PHP_EOL;
echo '<form name="tld" action="?" method="post">'.PHP_EOL;
echo '<input type="hidden" name="action" value="tld">'.PHP_EOL;
echo '<div id="tabbed">'.PHP_EOL;                          //Start tabbed container

echo '<input type="radio" name="tabs" id="tab-nav-1"><label for="tab-nav-1">Old Generic</label>'.PHP_EOL;
echo '<input type="radio" name="tabs" id="tab-nav-2" checked><label for="tab-nav-2">New Generic</label>'.PHP_EOL;
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
