<?php
/********************************************************************
https://github.com/lipis/flag-icon-css
********************************************************************/

require('../include/global-vars.php');
require('../include/global-functions.php');
require('../include/config.php');
require('../include/menu.php');

ensure_active_session();

/*************************************************
*Constants                                       *
*************************************************/
//define('DOMAIN_BLACKLIST', '/etc/notrack/domain-blacklist.txt');
//define('DOMAIN_WHITELIST', '/etc/notrack/domain-whitelist.txt');
//define('TLD_CSV', '../include/tld.csv');
define('TLD_BL', '../settings/tldlist.txt');
/*************************************************
*Global Variables                                *
*************************************************/
$view = 0;

/*************************************************
*Arrays                                          *
*************************************************/
$tldlist = array();                              //Contents of tld.csv
$usersbl = array();


/********************************************************************
 *  Load TLD CSV List
 *    Load TLD List CSV file into $tldlist
 *  Params:
 *    None
 *  Return:
 *    None
 */
function load_tldcsv() {
  global $tldlist, $usersbl;

  $line = array();

  $fh = fopen(TLD_CSV, 'r') or die('Error unable to open '.TLD_CSV);
  while(!feof($fh) && ($line = fgetcsv($fh)) !== false) {
    if (sizeof($line) == 4) {                              //Check array length is valid
      if (array_key_exists($line[0], $usersbl)) {          //Check for user-set value
        $line[] = $usersbl[$line[0]];
      }
      elseif ($line[2] == '1') {                           //Enable priority one by default
        $line[] = true;
      }
      else {                                               //Unset, leave disabled
        $line[] = false;
      }
      $tldlist[] = $line;                                  //Add line of CSV to $tldlist
    }
  }

  fclose($fh);
}


/********************************************************************
 *  Load Users TLD Blocklist
 *    Load tldlist.txt into $
 *  Params:
 *
 *  Return:
 *    None
 */
function load_bl() {
  global $usersbl;

  if (file_exists(TLD_BL)) {
    $fh = fopen(TLD_BL, 'r') or die('Error unable to open '.TLD_BL);
    while (!feof($fh)) {
      $line = trim(fgets($fh));

    }

    fclose($fh);
  }
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

  echo '</div>'.PHP_EOL;                                   //End tab 4 div

}

/********************************************************************
 *  Show Domain List
 *
 *    3. Display list
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function show_domain_list() {
  global $tldlist;

  $cell1 = '';                                             //Tickbox cell
  $cell2 = '';                                             //TLD Name cell
  $cell3 = '';                                             //Description cell
  $checked = '';                                           //Tickbox status
  $domain_name = '';
  $flag_image = '';                                        //HTML Code for flag
  $flag_filename = '';                                     //Filename of flag

  $tabview = 1;                                            //Current Tab view


  $listsize = count($tldlist);

  echo '<div>'.PHP_EOL;                                    //Start Tab

  if ($listsize == 0) {                                    //Is List blank?
    echo '<h4><img src=../svg/emoji_sad.svg>No sites found in Block List</h4>'.PHP_EOL;
    echo '</div>';
    return;
  }

  echo '<h5>Old Generic Domains</h5>'.PHP_EOL;
  echo '<input type="hidden" name="action" value="tld">'.PHP_EOL;
  echo '<table class="tld-table">'.PHP_EOL;                //Start tld-table

  foreach ($tldlist as $line) {
    //1. Domain
    //2. Domain Name
    //3. Risk
    //4. Comment

    //Risk score of zero means draw new table
    if ($line[2] == 0) {
      echo '<tr><td colspan="3"><button type="submit" name="v" value="'.$tabview.'">Save Changes</button></td></tr>'.PHP_EOL;
      echo '</table>'.PHP_EOL;                             //End current tld-table
      echo '</div>'.PHP_EOL;                               //End Tab

      $tabview++;
      echo '<div>'.PHP_EOL;                                //Start new Tab
      echo '<h5>'.$line[1].'</h5>'.PHP_EOL;                //Title
      echo '<table class="tld-table">'.PHP_EOL;            //Start new tld-table
      continue;                                            //Jump to end of loop
    }

    $domain_name = substr($line[0], 1);

    switch ($line[2]) {                                    //Cell colour based on risk
      case 1: $cell2 = '<td class="red">'; break;
      case 2: $cell2 = '<td class="orange">'; break;
      case 3: $cell2 = '<td>'; break;                      //Default colour for low risk
      case 5: $cell2 = '<td class="green">'; break;
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

    //Set tickbox checked: Condition (Risk 1 & NOT in White List) OR (in Black List)
    $checked = $line[4] ? ' checked="checked"' : '';

    $cell1 = '<input type="checkbox" name="'.$domain_name.'"'.$checked.'>';
    $cell2 .= '<div class="centered">'.$line[0].'</div>';
    $cell3 = $flag_image.$line[1].'<br>'.$line[3];

    echo "<tr><td>$cell1</td>$cell2</td><td>$cell3</td></tr>".PHP_EOL;

  }

  echo '<tr><td colspan="3"><button type="submit" name="v" value="'.$tabview.'">Save Changes</button></td></tr>'.PHP_EOL;
  echo '</table>'.PHP_EOL;                                 //End final table

  echo '</div>'.PHP_EOL;                                   //End Tab
}


/********************************************************************
 *  Update Domian List
 *    1. Write domain-whitelist.txt to /tmp
 *    2. Write domain-blacklist.txt to /tmp
 *    3. Run ntrk-exec to copy domain lists over
 *    4. Delete Memcache items to force reload
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function update_domain_list() {
  global $tldlist, $mem;

  //Start with White List
  $fh = fopen(DIR_TMP.'domain-whitelist.txt', 'w') or die('Unable to open '.DIR_TMP.'domain-whitelist.txt for writing');

  fwrite($fh, '#Domain White list generated by tld.php'.PHP_EOL);
  fwrite($fh, '#Do not make any changes to this file'.PHP_EOL);

  foreach ($tldlist as $site) {                               //Generate White list based on unticked Risk 1 items
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

  exec(NTRK_EXEC.'--copy tld'); // DEPRECATED
  exec(NTRK_EXEC.'--save tld');

  $mem->delete('tldblacklist');                            //Delete Black List from Memcache
  $mem->delete('tldwhitelist');                            //Delete White List from Memcache

  return null;
}


/********************************************************************
 *  Draw Tabbed View
 *    Draw Tabbed View is called when a value is set for GET/POST argument "v"
 *    1. Check which tab to set as checked
 *    2. Draw the tabbed elements
 *    3. Draw the Domain List
 *    4. Draw Help page
 *  Params:
 *    $view - Tab to View
 *  Return:
 *    None
 */
function draw_tabbedview($view) {
  $tab = filter_integer($view, 1, 4, 2);
  $checkedtabs = array('', '', '', '', '');
  $checkedtabs[$tab] = ' checked';

  echo '<form name="tld" action="?" method="post">'.PHP_EOL;
  echo '<input type="hidden" name="action" value="tld">'.PHP_EOL;

  echo '<div class="sys-group">'.PHP_EOL;
  echo '<div id="tabbed">'.PHP_EOL;                        //Start tabbed container

  echo '<input type="radio" name="tabs" id="tab-nav-1"'.$checkedtabs[1].'><label for="tab-nav-1">Old Generic</label>'.PHP_EOL;
  echo '<input type="radio" name="tabs" id="tab-nav-2"'.$checkedtabs[2].'><label for="tab-nav-2">New Generic</label>'.PHP_EOL;
  echo '<input type="radio" name="tabs" id="tab-nav-3"'.$checkedtabs[3].'><label for="tab-nav-3">Country</label>'.PHP_EOL;
  echo '<input type="radio" name="tabs" id="tab-nav-4"'.$checkedtabs[4].'><label for="tab-nav-4">Help</label>'.PHP_EOL;

  echo '<div id="tabs">'.PHP_EOL;

  show_domain_list();
  tld_help();
  echo '</div>'.PHP_EOL;                                   //End tabs
  echo '</div>'.PHP_EOL;                                   //End tabbed container
  echo '</div>'.PHP_EOL;                                   //End sys-group
  echo '</form>'.PHP_EOL;                                   //End form
}


/********************************************************************
 *  Draw Welcome
 *    Draw Welcome is called when no value has been set for GET/POST argument "v"
 *    Word Cloud images generated from https://www.jasondavies.com/wordcloud/
 *  Params:
 *    None
 *  Return:
 *    None
 */
function draw_welcome() {
  echo '<div class="sys-group">'.PHP_EOL;
  echo '<div class="bl-flex-container">'.PHP_EOL;
  echo '<a href="?v=1"><img src="../svg/wordclouds/classic_wordcloud.svg" alt=""><h6>Old Generic</h6></a>'.PHP_EOL;
  echo '<a href="?v=2"><img src="../svg/wordclouds/gtld_wordcloud.svg" alt=""><h6>New Generic</h6></a>'.PHP_EOL;
  echo '<a href="?v=3"><img src="../svg/wordclouds/country_wordcloud.svg" alt=""><h6>Country</h6></a>'.PHP_EOL;
  echo '<a href="?v=4"><img src="../svg/wordclouds/help_wordcloud.svg" alt=""><h6>Help</h6></a>'.PHP_EOL;
  echo '</div>'.PHP_EOL;
  echo '</div>'.PHP_EOL;
}

/************************************************
*POST REQUESTS                                  *
************************************************/
//Deal with POST actions first, that way we can reload the page and remove POST requests from browser history.
if ((isset($_POST['action'])) && (isset($_POST['v']))) {
  load_tldcsv();                                           //Load tld.csv
  update_domain_list();
  usleep(250000);                                          //Prevent race condition
  header('Location: ?v='.$_POST['v']);                     //Reload page
}



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
load_tldcsv();

if (isset($_GET['v'])) {
  draw_tabbedview($_GET['v']);
}
else {
  draw_welcome();
}




?>

</div>
</body>
</html>
