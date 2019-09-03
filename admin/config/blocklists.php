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
$db = new mysqli(SERVERNAME, USERNAME, PASSWORD, DBNAME);

/************************************************
*Arrays                                         *
************************************************/
$list = array();                                 //Global array for all the Block Lists


/********************************************************************
 *  Draw Blocklist Row
 *
 *  Params:
 *    Block list, bl_name, Message
 *  Return:
 *    None
 */
function draw_blocklist_row($bl, $bl_name, $msg, $url) {
  global $Config;
  //Txt File = Origniating download file
  //TLD Is a special case, and the Txt file used is TLD_CSV


  $txtfilename = '';
  $txtlines = 0;
  $filename = '';
  $stats = '';

  if ($Config[$bl] == 0) {
    echo '<tr><td><input type="checkbox" name="'.$bl.'"></td><td>'.$bl_name.':</td><td>'.$msg.' <a href="'.$url.'" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a></td></tr>'.PHP_EOL;
  }
  else {
    $filename = strtolower(substr($bl, 3));
    if ($bl == 'bl_tld') {
      $txtfilename = TLD_CSV;
    }
    else {
      $txtfilename = DIR_TMP.$filename.'.txt';
    }

    $rows = count_rows("SELECT COUNT(*) FROM blocklist WHERE bl_source = '$bl'");

    if (($rows > 0) && (file_exists($txtfilename))) {

      //Try and get the number of lines in the file
      try {
        $txtlines = count(file($txtfilename));
      }
      catch(Exception $e) {
        $txtlines = 0;
      }

      //Prevent stupid result of lines being higher than the site count
      if ($rows > $txtlines) {
        $rows = $txtlines;
      }

      $stats = '<p class="light">'.$rows.' used of '.$txtlines.'</p>';
    }
    else {
      $stats = '<p class="light">'.$rows.' used of ?</p>';
    }

    echo '<tr><td><input type="checkbox" name="'.$bl.'" checked="checked"></td><td>'.$bl_name.':</td><td>'.$msg.' <a href="'.$url.'" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>'.$stats.'</td></tr>'.PHP_EOL;
  }

  return null;
}


/********************************************************************
 *  Tracking Block Lists
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function tracking_blocklists() {
  echo '<div>'.PHP_EOL;                                    //Start Tab
  echo '<div class="sys-group">'.PHP_EOL;
  echo '<h5>Tracker Block Lists</h5>'.PHP_EOL;

  echo '<table class="bl-table">'.PHP_EOL;
  draw_blocklist_row('bl_notrack', 'NoTrack List', 'NoTrack Block List contains mixture of Tracking and Advertising sites', 'https://gitlab.com/quidsup/notrack-blocklists');

  draw_blocklist_row('bl_tld', 'Top Level Domains', 'Whole country and generic top level domains', './tld.php');

  draw_blocklist_row('bl_easyprivacy', 'EasyPrivacy', 'Supplementary list from AdBlock Plus', 'https://forums.lanik.us/');

  draw_blocklist_row('bl_fbenhanced', 'Fanboy&rsquo;s Enhanced Tracking List', 'Blocks common tracking scripts', 'https://www.fanboy.co.nz/');

  echo '<tr><td colspan="3"><button type="submit" name="v" value="1">Save Changes</button></td></tr>'.PHP_EOL;
  echo '</table>'.PHP_EOL;                                 //End bl table
  echo '</div>'.PHP_EOL;                                   //End sys-group
  echo '</div>'.PHP_EOL;                                   //End Tab

  return null;
}


/********************************************************************
 *  Advertising Block Lists
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function advertising_blocklists() {
  echo '<div>'.PHP_EOL;                                    //Start Tab
  echo '<div class="sys-group">'.PHP_EOL;
  echo '<h5>Advertising Block Lists</h5>'.PHP_EOL;

  echo '<table class="bl-table">'.PHP_EOL;
  draw_blocklist_row('bl_easylist', 'EasyList', 'EasyList without element hiding rules‎', 'https://forums.lanik.us/');

  draw_blocklist_row('bl_pglyoyo', 'Peter Lowe&rsquo;s Ad server list‎', 'Some of this list is already in NoTrack', 'https://pgl.yoyo.org/adservers/');

  echo '<tr><td colspan="3"><button type="submit" name="v" value="2">Save Changes</button></td></tr>'.PHP_EOL;
  echo '</table>'.PHP_EOL;                                 //End bl table
  echo '</div>'.PHP_EOL;                                   //End sys-group
  echo '</div>'.PHP_EOL;                                   //End Tab

  return null;
}


/********************************************************************
 *  Malware Block Lists
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function malware_blocklists() {
  echo '<div>'.PHP_EOL;                                    //Start Tab
  echo '<div class="sys-group">'.PHP_EOL;
  echo '<h5>Malware Block Lists</h5>'.PHP_EOL;

  echo '<table class="bl-table">'.PHP_EOL;
  draw_blocklist_row('bl_notrack_malware', 'NoTrack Malware', 'NoTrack Malware List contains malicious and dodgy sites that aren&rsquo;t really considered tracking or advertising', 'https://gitlab.com/quidsup/notrack-blocklists');

  draw_blocklist_row('bl_hexxium', 'Hexxium Creations Threat List', 'Hexxium Creations are a small independent team running a community based malware and scam domain database', 'https://www.hexxiumcreations.com/projects/malicious-domain-blocking');

  draw_blocklist_row('bl_cedia', 'CEDIA Malware List', 'National network investigation and education of Ecuador Malware List', 'https://cedia.org.ec/');

  draw_blocklist_row('bl_cedia_immortal', 'CEDIA Immortal Malware List', 'CEDIA Long-lived &#8220;immortal&#8221; Malware sites', 'https://cedia.org.ec/');

  draw_blocklist_row('bl_disconnectmalvertising', 'Malvertising list by Disconnect', '', 'https://disconnect.me/');

  draw_blocklist_row('bl_malwaredomainlist', 'Malware Domain List', '', 'http://www.malwaredomainlist.com/');

  draw_blocklist_row('bl_malwaredomains', 'Malware Domains', 'A good list to add', 'http://www.malwaredomains.com/');

  draw_blocklist_row('bl_spam404', 'Spam404', '', 'http://www.spam404.com/');

  draw_blocklist_row('bl_swissransom', 'Swiss Security - Ransomware Tracker', 'Protects against downloads of several variants of Ransomware, including Cryptowall and TeslaCrypt', 'https://ransomwaretracker.abuse.ch/');

  echo '<tr><td colspan="3"><button type="submit" name="v" value="3">Save Changes</button></td></tr>'.PHP_EOL;
  echo '</table>'.PHP_EOL;                                 //End bl table
  echo '</div>'.PHP_EOL;                                   //End sys-group
  echo '</div>'.PHP_EOL;                                   //End Tab

  return null;
}

/********************************************************************
 *  Cryptocoin Block Lists
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function cryptocoin_blocklists() {
  echo '<div>'.PHP_EOL;                                    //Start Tab
  echo '<div class="sys-group">'.PHP_EOL;
  echo '<h5>Crypto Coin Block Lists</h5>'.PHP_EOL;

  echo '<table class="bl-table">'.PHP_EOL;
  draw_blocklist_row('bl_cbl_all', 'Coin Blocker Lists - All', 'This list contains all crypto mining domains - A list for administrators to prevent mining in networks.', 'https://gitlab.com/ZeroDot1/CoinBlockerLists');

  draw_blocklist_row('bl_cbl_opt', 'Coin Blocker Lists - Optional', 'This list contains all optional mining domains - An additional list for administrators.', 'https://gitlab.com/ZeroDot1/CoinBlockerLists');

  draw_blocklist_row('bl_cbl_browser', 'Coin Blocker Lists - Browser', 'This list contains all browser mining domains - A list to prevent browser mining only.', 'https://gitlab.com/ZeroDot1/CoinBlockerLists');

  echo '<tr><td colspan="3"><button type="submit" name="v" value="4">Save Changes</button></td></tr>'.PHP_EOL;
  echo '</table>'.PHP_EOL;                                 //End bl table
  echo '</div>'.PHP_EOL;                                   //End sys-group
  echo '</div>'.PHP_EOL;                                   //End Tab

  return null;
}


/********************************************************************
 *  Social Block Lists
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function social_blocklists() {
  echo '<div>'.PHP_EOL;                                    //Start Tab
  echo '<div class="sys-group">'.PHP_EOL;
  echo '<h5>Social Block Lists</h5>'.PHP_EOL;

  echo '<table class="bl-table">'.PHP_EOL;

  draw_blocklist_row('bl_fbannoyance', 'Fanboy&rsquo;s Annoyance List', 'Block Pop-Ups and other annoyances.', 'https://www.fanboy.co.nz/');
  draw_blocklist_row('bl_fbsocial', 'Fanboy&rsquo;s Social Blocking List', 'Block social content, widgets, scripts and icons.', 'https://www.fanboy.co.nz/');

  echo '<tr><td colspan="3"><button type="submit" name="v" value="5">Save Changes</button></td></tr>'.PHP_EOL;
  echo '</table>'.PHP_EOL;                                 //End bl table
  echo '</div>'.PHP_EOL;                                   //End sys-group
  echo '</div>'.PHP_EOL;                                   //End Tab

  return null;
}


/********************************************************************
 *  Multipurpose Block Lists
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function multipurpose_blocklists() {
  echo '<div>'.PHP_EOL;                                    //Start Tab
  echo '<div class="sys-group">'.PHP_EOL;
  echo '<h5>Multipurpose Block Lists</h5>'.PHP_EOL;

  echo '<table class="bl-table">'.PHP_EOL;

  draw_blocklist_row('bl_someonewhocares', 'Dan Pollock&rsquo;s hosts file', 'Mixture of Shock and Ad sites.', 'http://someonewhocares.org/hosts');

  draw_blocklist_row('bl_hphosts', 'hpHosts', 'Inefficient list', 'http://hosts-file.net');

  //draw_blocklist_row('bl_securemecca', 'Secure Mecca', 'Mixture of Adult, Gambling and Advertising sites <a href="http://securemecca.com/" target="_blank">(securemecca.com)</a>');

  draw_blocklist_row('bl_winhelp2002', 'MVPS Hosts‎', 'Very inefficient list', 'http://winhelp2002.mvps.org/');

  echo '<tr><td colspan="3"><button type="submit" name="v" value="6">Save Changes</button></td></tr>'.PHP_EOL;
  echo '</table>'.PHP_EOL;                                 //End bl table
  echo '</div>'.PHP_EOL;                                   //End sys-group
  echo '</div>'.PHP_EOL;                                   //End Tab

  return null;
}


/********************************************************************
 *  Regional Block Lists
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function regional_blocklists() {
  echo '<div>'.PHP_EOL;                                    //Start Tab
  echo '<div class="sys-group">'.PHP_EOL;
  echo '<h5>Regional Block Lists</h5>'.PHP_EOL;

  echo '<table class="bl-table">'.PHP_EOL;

  draw_blocklist_row('bl_fblatin', 'Latin EasyList', 'Spanish/Portuguese Adblock List', 'https://www.fanboy.co.nz/regional.html');

  draw_blocklist_row('bl_areasy', 'AR EasyList', 'عربي EasyList (Arab) ‎', 'https://forums.lanik.us/viewforum.php?f=98');

  draw_blocklist_row('bl_chneasy', 'CHN EasyList', '中文 EasyList (China)‎', 'http://abpchina.org/forum/forum.php');

  draw_blocklist_row('bl_yhosts', 'CHN Yhosts', 'YHosts 中文‎ focused on Chinese advert sites (China)', 'https://github.com/vokins/yhosts');

  draw_blocklist_row('bl_deueasy', 'DEU EasyList', 'Deutschland EasyList (Germany)', 'https://forums.lanik.us/viewforum.php?f=90');
  draw_blocklist_row('bl_dnkeasy', 'DNK EasyList', 'Danmark Schacks Adblock Plus liste‎ (Denmark)', 'https://henrik.schack.dk/adblock/');

  draw_blocklist_row('bl_fraeasy', 'FRA EasyList', 'France EasyList', 'https://forums.lanik.us/viewforum.php?f=91');

  draw_blocklist_row('bl_grceasy', 'GRC EasyList', 'Ελλάδα EasyList (Greece)', 'https://github.com/kargig/greek-adblockplus-filter');

  draw_blocklist_row('bl_huneasy', 'HUN hufilter', 'Magyar Adblock szűrőlista (Hungary)', 'https://github.com/szpeter80/hufilter');

  draw_blocklist_row('bl_idneasy', 'IDN EasyList', 'ABPindo (Indonesia)', 'https://github.com/ABPindo/indonesianadblockrules');

  draw_blocklist_row('bl_isleasy', 'ISL EasyList', 'Adblock Plus listi fyrir íslenskar vefsíður (Iceland)', 'https://adblock.gardar.net');

  draw_blocklist_row('bl_itaeasy', 'ITA EasyList', 'Italia EasyList (Italy)', 'https://forums.lanik.us/viewforum.php?f=96');

  draw_blocklist_row('bl_jpneasy', 'JPN EasyList', '日本用フィルタ (Japan)', 'https://github.com/k2jp/abp-japanese-filters');

  draw_blocklist_row('bl_koreasy', 'KOR EasyList', '대한민국 EasyList (Korea)', 'https://github.com/gfmaster/adblock-korea-contrib');

  draw_blocklist_row('bl_korfb', 'KOR Fanboy', '대한민국 Fanboy&rsquo;s list (Korea)', 'https://forums.lanik.us/');

  draw_blocklist_row('bl_koryous', 'KOR YousList', '대한민국 YousList (Korea)', 'https://github.com/yous/YousList');

  draw_blocklist_row('bl_ltueasy', 'LTU EasyList', 'Lietuva EasyList (Lithuania)', 'http://margevicius.lt/easylist_lithuania');

  draw_blocklist_row('bl_lvaeasy', 'LVA EasyList', 'Latvija List (Latvia)', 'https://notabug.org/latvian-list/adblock-latvian');

  draw_blocklist_row('bl_nldeasy', 'NLD EasyList', 'Nederland EasyList (Dutch)', 'https://forums.lanik.us/viewforum.php?f=100');

  draw_blocklist_row('bl_poleasy', 'POL EasyList', 'Polskie filtry do Adblocka (Poland)', 'https://www.certyficate.it/adblock-ublock-polish-filters/');

  draw_blocklist_row('bl_ruseasy', 'RUS EasyList', 'Россия RuAdList+EasyList (Russia)', 'https://forums.lanik.us/viewforum.php?f=102');

  draw_blocklist_row('bl_spaeasy', 'SPA EasyList', 'España EasyList (Spain)', 'https://forums.lanik.us/viewforum.php?f=103');

  draw_blocklist_row('bl_svneasy', 'SVN EasyList', 'Slovenska lista (Slovenia)', 'https://github.com/betterwebleon/slovenian-list');

  echo '<tr><td colspan="3"><button type="submit" name="v" value="7">Save Changes</button></td></tr>'.PHP_EOL;
  echo '</table>'.PHP_EOL;                                 //End bl table
  echo '</div>'.PHP_EOL;                                   //End sys-group
  echo '</div>'.PHP_EOL;                                   //End Tab

  return null;
}


/********************************************************************
 *  Custom Block Lists
 *    Replace comma seperated values of bl_custom with new lines
 *  Params:
 *    None
 *  Return:
 *    None
 */
function custom_blocklists() {
  global $Config;

  echo '<div>'.PHP_EOL;                                    //Start Tab
  echo '<div class="sys-group">'.PHP_EOL;
  echo '<h5>Custom Block Lists</h5>'.PHP_EOL;

  echo '<table class="sys-table">'.PHP_EOL;
  echo '<tr><td>&nbsp;</td><td><p>Use either Downloadable or Localy stored Block Lists</p><textarea rows="5" name="bl_custom">'.str_replace(',', PHP_EOL, $Config['bl_custom']).'</textarea></td></tr>';
  echo '</table>'.PHP_EOL;

  echo '<table class="bl-table">'.PHP_EOL;
  echo '<tr><td colspan="3"><button type="submit" name="v" value="8">Save Changes</button></td></tr>'.PHP_EOL;
  echo '</table>'.PHP_EOL;                                 //End bl table
  echo '</div>'.PHP_EOL;                                   //End sys-group
  echo '</div>'.PHP_EOL;                                   //End Tab

  return null;
}


/********************************************************************
 *  Update Blocks
 *    1: Search through Config array for bl_? (excluding bl_custom)
 *    2: Check if bl_? appears in POST[bl_?]
 *    3: Set bl_custom by splitting and filtering values from POST[bl_custom]
 *    4: After this function save_config is run
 *  Params:
 *    None
 *  Return:
 *    None
 */
function update_blocklists() {
  global $Config;

  $customstr = '';
  $customlist = array();
  $validlist = array();
  $key = '';
  $value = '';

  //Look for block list items in Config
  foreach($Config as $key => $value) {                     //Read entire Config array
    if (preg_match('/^bl\_(?!custom)/', $key) > 0) {       //Look for values starting bl_
      if (isset($_POST[$key])) {                           //Is there an equivilent POST value?
        if ($_POST[$key] == 'on') {                        //Is it set to on (ticked)?
          $Config[$key] = 1;                               //Yes - enable block list
        }
      }
      else {                                               //No POST value
        $Config[$key] = 0;                                 //Block list is unticked
      }
    }
  }

  //bl_custom requires extra processing
  if (filter_string('bl_custom', 'POST', 2000)) {
    //Split array
    $customstr = preg_replace('#\s+#',',',trim(strip_tags($_POST['bl_custom'])));
    $customlist = explode(',', $customstr);                //Split string into array

    //Check if each item is a valid URL or file location?
    foreach ($customlist as $site) {
      if (filter_url($site)) {
        $validlist[] = strip_tags($site);
      }
      elseif (preg_match('/\/\w{3,4}\/[\w\/\.]/', $site) > 0) {
        $validlist[] = strip_tags($site);
      }
    }

    //Are there any items in the valid list?
    if (sizeof($validlist) == 0) {                         //No - blank out bl_custom
      $Config['bl_custom'] = '';
    }
    else {                                                 //Yes - Implode the validlist
      $Config['bl_custom'] = implode(',', $validlist);
    }
  }
  else {
    $Config['bl_custom'] = '';
  }

  return null;
}


/********************************************************************
 *  Draw Tabbed View
 *    Draw Tabbed View is called when a value is set for GET/POST argument "v"
 *    1. Check which tab to set as checked
 *    2. Draw the tabbed elements
 *    3. Draw each block list table
 *  Params:
 *    $view - Tab to View
 *  Return:
 *    None
 */
function draw_tabbedview($view) {
  $tab = filter_integer($view, 1, 8, 1);
  $checkedtabs = array('', '', '', '', '', '', '', '', '');
  $checkedtabs[$tab] = ' checked';

  echo '<form name="blocklists" action="?" method="post">'.PHP_EOL;
  echo '<input type="hidden" name="action" value="blocklists">'.PHP_EOL;
  echo '<input type="hidden" name="v" value="'.$tab.'">'.PHP_EOL;

  echo '<div id="tabbed">'.PHP_EOL;                        //Start tabbed container

  echo '<input type="radio" name="tabs" id="tab-nav-1"'.$checkedtabs[1].'><label for="tab-nav-1">Tracking</label>'.PHP_EOL;
  echo '<input type="radio" name="tabs" id="tab-nav-2"'.$checkedtabs[2].'><label for="tab-nav-2">Advertising</label>'.PHP_EOL;
  echo '<input type="radio" name="tabs" id="tab-nav-3"'.$checkedtabs[3].'><label for="tab-nav-3">Malware</label>'.PHP_EOL;
  echo '<input type="radio" name="tabs" id="tab-nav-4"'.$checkedtabs[4].'><label for="tab-nav-4">Crypto Coin</label>'.PHP_EOL;
  echo '<input type="radio" name="tabs" id="tab-nav-5"'.$checkedtabs[5].'><label for="tab-nav-5">Social</label>'.PHP_EOL;
  echo '<input type="radio" name="tabs" id="tab-nav-6"'.$checkedtabs[6].'><label for="tab-nav-6">Multipurpose</label>'.PHP_EOL;
  echo '<input type="radio" name="tabs" id="tab-nav-7"'.$checkedtabs[7].'><label for="tab-nav-7">Regional</label>'.PHP_EOL;
  echo '<input type="radio" name="tabs" id="tab-nav-8"'.$checkedtabs[8].'><label for="tab-nav-8">Custom</label>'.PHP_EOL;

  echo '<div id="tabs">'.PHP_EOL;                          //Start Tabs

  tracking_blocklists();
  advertising_blocklists();
  malware_blocklists();
  cryptocoin_blocklists();
  social_blocklists();
  multipurpose_blocklists();
  regional_blocklists();
  custom_blocklists();

  echo '</div>'.PHP_EOL;                                   //End tabs
  echo '</div>'.PHP_EOL;                                   //End tabbed container
  echo '</form>'.PHP_EOL;                                  //End form
}


/********************************************************************
 *  Draw Welcome
 *    Draw Welcome is called when no value has been set for GET/POST argument "v"
 *  Params:
 *    None
 *  Return:
 *    None
 */
function draw_welcome() {
  echo '<div class="sys-group">'.PHP_EOL;
  echo '<div class="bl-flex-container">'.PHP_EOL;
  echo '<div><a href="?v=1">Tracking</a></div>'.PHP_EOL;
  echo '<div><a href="?v=2">Advertising</a></div>'.PHP_EOL;
  echo '<div><a href="?v=3">Malware</a></div>'.PHP_EOL;
  echo '<div><a href="?v=4">Crypto Coin</a></div>'.PHP_EOL;
  echo '<div><a href="?v=5">Social</a></div>'.PHP_EOL;
  echo '<div><a href="?v=6">Multipurpose</a></div>'.PHP_EOL;
  echo '<div><a href="?v=7">Regional</a></div>'.PHP_EOL;
  echo '<div><a href="?v=8">Custom</a></div>'.PHP_EOL;
  echo '</div>'.PHP_EOL;
  echo '</div>'.PHP_EOL;
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
  <title>NoTrack - Block Lists</title>
</head>

<body>
<?php
draw_topmenu('Block Lists');
draw_sidemenu();

echo '<div id="main">'.PHP_EOL;

//Has the Save Changes button been clicked?
if (isset($_POST['action'])) {                             //Yes - Update block list conf
  update_blocklists();
  save_config();
  //Might need a sleep to prevent race condition
  exec(NTRK_EXEC.'--run-notrack');
}

if (isset($_GET['v'])) {
  draw_tabbedview($_GET['v']);
}
elseif (isset($_POST['v'])) {
  draw_tabbedview($_POST['v']);
}
else {
  draw_welcome();
}


$db->close();
?>

</div>
</body>
</html>
