<?php
/********************************************************************
tld.csv stores list of country / generic domains, as well as the table layout used here
tldlist.txt stores the users settings
If the users settings are missing, then fallback to enabling risk score 1 TLD's by default

Flags taken from:
https://github.com/lipis/flag-icon-css
https://github.com/hjnilsson/country-flags
https://www.iso.org/obp/ui/#search
********************************************************************/
require('../include/global-vars.php');
require('../include/global-functions.php');
require('../include/config.php');
require('../include/menu.php');

ensure_active_session();

/*************************************************
*Constants                                       *
*************************************************/
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
 *  Get Digram
 *    Two letters to use in flag-icon when picture is missing
 *
 *  Params:
 *    Word (str):
 *  Return:
 *    Digram
 */
function get_digram($word) {
  $wordlen = 0;

  $wordlen = strlen($word);

  //Four or more crop to 1st two letters
  if ($wordlen > 3) {
    return substr(ucfirst($word), 0, 2);
  }
  //Two letters is passthru
  elseif ($wordlen == 2) {
    return ucfirst($word);
  }
  //1 or 3 use just the first letter
  else {
    return substr(strtoupper($word), 0, 1);
  }
}
/********************************************************************
 *  Load TLD CSV List
 *    1. Attempt to load $tldlist from memcache
 *    2. Load TLD List CSV file into $tldlist
 *    3. Save $tldlist to memcache
 *  Params:
 *    None
 *  Return:
 *    None
 */
function load_tldcsv() {
  global $mem, $tldlist, $usersbl;

  $line = array();

  if ($mem->get('tldlist') !== false) {
    $tldlist = $mem->get('tldlist');
    return;
  }

  $fh = fopen(TLD_CSV, 'r') or die('Error unable to open '.TLD_CSV);
  fgetcsv($fh);                                            //Ditch column headers

  while(! feof($fh) && ($line = fgetcsv($fh)) !== false) {
    if (sizeof($line) >= 6) {                              //Check array length is valid
      $tldlist[] = $line;                                  //Add line of CSV to $tldlist
    }
  }
  fclose($fh);                                             //Close tld.csv

  $mem->add('tldlist', $tldlist, 0, 600);                  //Save to memcache for 10 mins
}


/********************************************************************
 *  Load Users TLD Blocklist
 *    Load tldlist.txt into $usersbl
 *    Regex Groups:
 *      1. Optional # (if # is present then line commented out, therefore TLD disabled
 *      2. .tld (2 to 63 characters)
 *      Ignore trailing comment, its not relevent for $usersbl
 *  Params:
 *    None
 *  Return:
 *    None
 */
function load_bl() {
  global $usersbl;

  $enabled = true;
  $line = '';
  $matches = array();

  //Not necessary for file to exist, NoTrack can use default values
  if (! file_exists(TLD_BL)) {
    return;
  }

  $fh = fopen(TLD_BL, 'r') or die('Error unable to open '.TLD_BL);
  while(! feof($fh) && ($line = fgets($fh)) !== false) {
    if (preg_match('/^(#)?(\.\w{2,63})/', $line, $matches)) {
      $enabled = ($matches[1] == '#') ? false : true;
      $usersbl[$matches[2]] = $enabled;
    }
  }

  fclose($fh);
}


/********************************************************************
 *  Update Users TLD Block List
 *    1. Loop through $tldlist, which will have already been loaded from file / memcache
 *    2a. Check if TLD name is POST requests
 *    2b. Or if missing assume unticked and therefore false
 *    3. Add fileline based on the above
 *    4. Save filelines to tldlist.txt
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function save_bl() {
  global $tldlist, $usersbl;

  $enabled = false;
  $risk = 0;
  $title = '';
  $tld = '';

  $filelines = array();
  $line = array();

  foreach ($tldlist as $line) {
    $tld = $line[0];
    $name = $line[1];
    $title = $line[2];
    $risk = $line[4];

    if (isset($_POST[$line[1]])) {                       //Does name feature in POST (tld domain without preceding .)
      $usersbl[$tld] = true;
      $filelines[] = "{$tld} #{$title}".PHP_EOL;
    }
    else {
      $usersbl[$tld] = false;
      $filelines[] = "#{$tld} #{$title}".PHP_EOL;
    }
  }

  if (file_put_contents(TLD_BL, $filelines) === false) {
    die('Unable to save settings to '.TLD_BL);
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
  echo '<div>'.PHP_EOL;                                    //Start tab 5 div
  echo '<h5>Domain Blocking</h5>'.PHP_EOL;
  echo '<p>NoTrack has the ability to block certain top-level domains, this comes in useful against certain domains which are abused by malicious actors. Sites can be created very quickly with the purpose of hosting malware and phishing sites, which can inflict a significant amount of damage before the security community can identify and block them.</p>'.PHP_EOL;
  echo '<p>Domains are categorised by a risk level: High, Medium, Low, and Negligible. The risk level has been taken from <u><a href="https://www.spamhaus.org/statistics/tlds/">Spamhaus</a></u>, <u><a href="https://krebsonsecurity.com/tag/top-20-shady-top-level-domains/">Krebs on Security</a></u>, <u><a href="https://www.symantec.com/blogs/feature-stories/top-20-shady-top-level-domains">Symantec</a></u>, and my own experience of dealing with Malware and Phishing campaigns in an Enterprise environment</p>'.PHP_EOL;
  echo '<br>'.PHP_EOL;
  echo '<span class="key flag-icon-red">High</span>'.PHP_EOL;
  echo '<p>High risk domains are home to a high percentage of malicious sites compared to legitimate sites. Often websites within these domains are cheap or even free, and the domains are not well policed.<br>'.PHP_EOL;
  echo 'High risk domains are automatically blocked, unless you specifically untick them.</p>'.PHP_EOL;
  echo '<br>'.PHP_EOL;

  echo '<span class="key flag-icon-orange">Medium</span>'.PHP_EOL;
  echo '<p>Medium risk domains are home to a significant number of malicious sites, but are outnumbered by legitimate sites. You may want to consider blocking these, unless you live in, or utilise the websites of the affected country.</p>'.PHP_EOL;
  echo '<br>'.PHP_EOL;

  echo '<span class="key flag-icon-yellow">Low</span>'.PHP_EOL;
  echo '<p>Low risk may still house some malicious sites, but they are vastly outnumbered by legitimate sites.</p>'.PHP_EOL;
  echo '<br>'.PHP_EOL;

  echo '<span class="key flag-icon-green">Negligible</span>'.PHP_EOL;
  echo '<p>These domains are not open to the public, and therefore extremely unlikely to contain malicious sites.</p>'.PHP_EOL;
  echo '<br>'.PHP_EOL;

  echo '</div>'.PHP_EOL;                                   //End tab 5 div

}

/********************************************************************
 *  Show Domain List
 *    1. Loop through $tldlist
 *    2. Get enabled status from $usersbl
 *    3. Format flag-icon-background based on risk number
 *    4. Set checked value from $usersbl, fallback to risk number with 1 enabled by default
 *    5. Draw table row
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function show_domain_list() {
  global $tldlist, $usersbl;

  $cell1 = '';                                             //Flag icon
  $cell2 = '';                                             //Checkbox input
  $checked = '';                                           //Checkbox status
  $countrycode = '';
  $description = '';
  $enabled = false;                                        //TLD enabled or disabled by user
  $flag_background = '';                                   //Colour of flag-icon
  $flag_image = '';                                        //Contents of flag-icon
  $name = '';                                              //TLD Name without preceding .
  $tld = '';
  $title = '';

  $risk = 0;
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
    $tld = $line[0];
    $name = $line[1];
    $title = $line[2];
    $countrycode = $line[3];
    $risk = $line[4];
    $description = $line[5];

    //Risk score of zero means draw new table
    if ($risk == 0) {
      echo '<tr><td colspan="5"><button type="submit" name="v" value="'.$tabview.'">Save Changes</button></td></tr>'.PHP_EOL;
      echo '</table>'.PHP_EOL;                             //End current tld-table
      echo '</div>'.PHP_EOL;                               //End Tab

      $tabview++;
      echo '<div>'.PHP_EOL;                                //Start new Tab
      echo "<h5>{$title}</h5>".PHP_EOL;                    //Title
      echo '<table class="tld-table">'.PHP_EOL;            //Start new tld-table
      continue;                                            //Jump to end of loop
    }

    //Get Enabled status
    if (array_key_exists($tld, $usersbl)) {                //Check users settings
      $enabled = $usersbl[$tld];
    }
    elseif ($risk == 1) {                                  //Fallback if missing
      $enabled = true;                                     //Risk 1 enabled by default
    }
    else {
      $enabled = false;                                    //Risk 2-3 disabled by default
    }

    switch ($risk) {                                       //Cell colour based on risk
      case 1: $flag_background = 'flag-icon-red'; break;
      case 2: $flag_background = 'flag-icon-orange'; break;
      case 3: $flag_background = 'flag-icon-yellow'; break;
      case 5: $flag_background = 'flag-icon-green'; break;
    }

    if ($countrycode != '') {
      $flag_image = "<img src=\"../flags/$countrycode.png\" alt=\"{$countrycode}\">";
    }
    else {
      $flag_image = get_digram($name);                     //Two-letter digram instead of picture
    }

    $checked = ($enabled) ? ' checked="checked"' : '';     //Set checkbox ticked value

    $cell1 = "<div class=\"flag-icon {$flag_background}\">{$flag_image}</div>";
    $cell2 = "<input type=\"checkbox\" name=\"{$name}\"{$checked}>";

    echo "<tr><td>{$cell1}</td><td>{$cell2}</td><td>{$tld}</td><td>{$title}</td><td>{$description}</td></tr>".PHP_EOL;
  }

  //Closing save and table
  echo '<tr><td colspan="5"><button type="submit" name="v" value="'.$tabview.'">Save Changes</button></td></tr>'.PHP_EOL;
  echo '</table>'.PHP_EOL;                                 //End final table
  echo '</div>'.PHP_EOL;                                   //End Tab
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
  $tab = filter_integer($view, 1, 5, 2);
  $checkedtabs = array('', '', '', '', '', '');
  $checkedtabs[$tab] = ' checked';

  echo '<form name="tld" action="?" method="post">'.PHP_EOL;
  echo '<input type="hidden" name="action" value="tld">'.PHP_EOL;

  echo '<div class="sys-group">'.PHP_EOL;
  echo '<div id="tabbed">'.PHP_EOL;                        //Start tabbed container

  echo '<input type="radio" name="tabs" id="tab-nav-1"'.$checkedtabs[1].'><label for="tab-nav-1">Old Generic</label>'.PHP_EOL;
  echo '<input type="radio" name="tabs" id="tab-nav-2"'.$checkedtabs[2].'><label for="tab-nav-2">New Generic</label>'.PHP_EOL;
  echo '<input type="radio" name="tabs" id="tab-nav-3"'.$checkedtabs[3].'><label for="tab-nav-3">Country</label>'.PHP_EOL;
  echo '<input type="radio" name="tabs" id="tab-nav-4"'.$checkedtabs[4].'><label for="tab-nav-4">New Country</label>'.PHP_EOL;
  echo '<input type="radio" name="tabs" id="tab-nav-5"'.$checkedtabs[5].'><label for="tab-nav-5">Help</label>'.PHP_EOL;

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

//-------------------------------------------------------------------
load_tldcsv();                                             //Load CSV file

//Deal with POST actions first, that way we can reload the page and remove POST requests from browser history.
if ((isset($_POST['action'])) && (isset($_POST['v']))) {
  save_bl();
  usleep(250000);                                          //Prevent race condition
  header('Location: ?v='.$_POST['v']);                     //Reload page
}


?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <link href="../css/master.css" rel="stylesheet" type="text/css">
  <link href="../css/flags.css" rel="stylesheet" type="text/css">
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
load_bl();                                                 //Load users block list

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
