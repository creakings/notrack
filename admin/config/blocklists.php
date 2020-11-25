<?php
/********************************************************************
config.php handles setting of Global variables, GET, and POST requests
It also houses the functions for POST requests.

All other config functions are in ./include/config-functions.php

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
define('DOMAIN_BLACKLIST', '/etc/notrack/domain-blacklist.txt');
define('DOMAIN_WHITELIST', '/etc/notrack/domain-whitelist.txt');
/************************************************
*Global Variables                               *
************************************************/
$dbwrapper = new MySqliDb;

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
  global $config, $dbwrapper;

  $filelines = 0;
  $filename = '';
  $stats = '';

  if ($config->is_blocklist_active($bl)) {
    if ($bl == 'bl_tld') {                                 //Set the filename
      $filename = TLD_CSV;
    }
    else {
      $filename = DIR_TMP.$bl.'.txt';                      //Temp + Abbreviated blocklist name + .txt
    }

    $rows = $dbwrapper->count_specific_blocklist($bl);     //Count number of entries for this blocklist in blocklist MariaDB table

    if (($rows > 0) && (file_exists($filename))) {
      try {                                                //Try and count the number of lines in the file
        $filelines = count(file($filename));
      }
      catch(Exception $e) {                                //Something wrong, default count to zero
        $filelines = 0;
      }

      //Prevent stupid result of lines being higher than the site count
      if ($rows > $filelines) {
        $rows = $filelines;
      }

      $stats = '<p class="light">'.$rows.' used of '.$filelines.'</p>';
    }
    else {                                                 //Temp file missing, default to unknown count value
      $stats = '<p class="light">'.$rows.' used of ?</p>';
    }

    echo '<tr><td><input type="checkbox" name="'.$bl.'" checked="checked" onChange="setBlocklist(this)"></td><td>'.$bl_name.':</td><td>'.$msg.' <a href="'.$url.'" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>'.$stats.'</td></tr>'.PHP_EOL;
  }
  else {
    echo '<tr><td><input type="checkbox" name="'.$bl.'" onChange="setBlocklist(this)"></td><td>'.$bl_name.':</td><td>'.$msg.' <a href="'.$url.'" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a></td></tr>'.PHP_EOL;
  }
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
  echo '<div id="tab-content-1">'.PHP_EOL;                                    //Start Tab

  echo '<h5>Tracker Block Lists</h5>'.PHP_EOL;

  echo '<table class="bl-table">'.PHP_EOL;
  draw_blocklist_row('bl_notrack', 'NoTrack List', 'NoTrack Block List contains mixture of Tracking and Advertising domains', 'https://gitlab.com/quidsup/notrack-blocklists');

  draw_blocklist_row('bl_tld', 'Top Level Domains', 'Whole country and generic top level domains', './tld.php');

  draw_blocklist_row('bl_easyprivacy', 'EasyPrivacy', 'Supplementary list from AdBlock Plus', 'https://forums.lanik.us/');

  draw_blocklist_row('bl_fbenhanced', 'Fanboy&rsquo;s Enhanced Tracking List', 'Blocks common tracking scripts', 'https://www.fanboy.co.nz/');

  draw_blocklist_row('bl_windowsspyblocker', 'Windows Spy Blocker', 'Windows Spy Blocker provides a block list to prevent spying and tracking on Windows Systems', 'https://github.com/crazy-max/WindowsSpyBlocker');

  draw_blocklist_row('bl_ddg_confirmed', 'DuckDuckGo Confirmed', 'DuckDuckGo Tracker Radar blocklist domains which have been categorised as Confirmed trackers', 'https://gitlab.com/quidsup/ntrk-tracker-radar');

  draw_blocklist_row('bl_ddg_high', 'DuckDuckGo High Certainty', 'DuckDuckGo Tracker Radar blocklist domains which are excessively using browser APIs associated with tracking', 'https://gitlab.com/quidsup/ntrk-tracker-radar');

  draw_blocklist_row('bl_ddg_medium', 'DuckDuckGo Medium Certainty', 'DuckDuckGo Tracker Radar blocklist domains which are using many browser APIs, possibly for tracking purposes. This may contain some legitimate websites.', 'https://gitlab.com/quidsup/ntrk-tracker-radar');

  draw_blocklist_row('bl_ddg_low', 'DuckDuckGo Low Certainty', 'DuckDuckGo Tracker Radar blocklist domains which are using some browser APIs, but not obvoiusly for tracking purposes. This will contain some legitimate websites that are not associated with tracking.', 'https://gitlab.com/quidsup/ntrk-tracker-radar');

  draw_blocklist_row('bl_ddg_unknown', 'DuckDuckGo Unknown', 'Domains identified by DuckDuckGo Tracker Radar which have little to no usage of browser APIs associate with tracking. This list contains a mixture of legitimate and suspect websites.', 'https://gitlab.com/quidsup/ntrk-tracker-radar');

  draw_blocklist_row('bl_quantum_privacy', 'Quantum Privacy only list‎', 'Uses AI to track and analyse every website to find and identify ads.<br>This list contains over 265,000 domains for tracking domains', 'https://gitlab.com/The_Quantum_Alpha/the-quantum-ad-list');

  echo '</table>'.PHP_EOL;                                 //End bl table
  echo '</div>'.PHP_EOL;                                   //End Tab
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
  echo '<h5>Advertising Block Lists</h5>'.PHP_EOL;

  echo '<table class="bl-table">'.PHP_EOL;
  draw_blocklist_row('bl_easylist', 'EasyList', 'EasyList without element hiding rules‎', 'https://forums.lanik.us/');

  draw_blocklist_row('bl_pglyoyo', 'Peter Lowe&rsquo;s Ad server list‎', 'Some of this list is already in NoTrack', 'https://pgl.yoyo.org/adservers/');

  draw_blocklist_row('bl_quantum_full', 'Quantum Full list‎', 'Uses AI to track and analyse every website to find and identify ads.<br>This list contains over 1.3 Million domains used by ads, trackers, miners, malwares, and much more', 'https://gitlab.com/The_Quantum_Alpha/the-quantum-ad-list');

  draw_blocklist_row('bl_quantum_ads', 'Quantum Adverts only list‎', 'Uses AI to track and analyse every website to find and identify ads.<br>This list contains over 360,000 domains used by advertising companies', 'https://gitlab.com/The_Quantum_Alpha/the-quantum-ad-list');

  draw_blocklist_row('bl_quantum_youtube', 'Quantum YouTube Adverts only list‎', 'Uses AI to track and analyse every website to find and identify ads.<br>The list contains over 32,000 domains used by Google to place adverts on YouTube videos', 'https://gitlab.com/The_Quantum_Alpha/the-quantum-ad-list');

  echo '</table>'.PHP_EOL;                                 //End bl table
  echo '</div>'.PHP_EOL;                                   //End Tab
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
  echo '<h5>Malware Block Lists</h5>'.PHP_EOL;

  echo '<table class="bl-table">'.PHP_EOL;
  draw_blocklist_row('bl_notrack_malware', 'NoTrack Malware', 'NoTrack Malware List contains long-lived domains associated with malware, adware and phishing', 'https://gitlab.com/quidsup/notrack-blocklists');

  draw_blocklist_row('bl_hexxium', 'Hexxium Creations Threat List', 'Hexxium Creations are a small independent team running a community based malware and scam domain database', 'https://www.hexxiumcreations.com/projects/malicious-domain-blocking');

  draw_blocklist_row('bl_cedia', 'CEDIA Malware List', 'National network investigation and education of Ecuador Malware List', 'https://cedia.org.ec/');

  draw_blocklist_row('bl_cedia_immortal', 'CEDIA Immortal Malware List', 'CEDIA Long-lived &#8220;immortal&#8221; Malware sites', 'https://cedia.org.ec/');

  draw_blocklist_row('bl_disconnectmalvertising', 'Malvertising list by Disconnect', '', 'https://disconnect.me/');

  draw_blocklist_row('bl_malwaredomainlist', 'Malware Domain List', '', 'http://www.malwaredomainlist.com/');

  draw_blocklist_row('bl_malwaredomains', 'Malware Domains', 'A good list to add', 'http://www.malwaredomains.com/');

  draw_blocklist_row('bl_quantum_abuse', 'Quantum Abuse only list‎', 'Uses AI to track and analyse every website to find and identify ads.<br>This list contains over 67,000 domains for malware and other dodgy sites', 'https://gitlab.com/The_Quantum_Alpha/the-quantum-ad-list');

  draw_blocklist_row('bl_spam404', 'Spam404', '', 'http://www.spam404.com/');

  draw_blocklist_row('bl_swissransom', 'Swiss Security - Ransomware Tracker', 'Protects against downloads of several variants of Ransomware, including Cryptowall and TeslaCrypt', 'https://ransomwaretracker.abuse.ch/');

  draw_blocklist_row('bl_ublock_badware', 'uBlockOrigin Badware List', 'Block sites documented to put users at risk of installing adware/crapware etc', 'https://github.com/uBlockOrigin/uAssets/blob/master/filters/badware.txt');

  echo '</table>'.PHP_EOL;                                 //End bl table
  echo '</div>'.PHP_EOL;                                   //End Tab
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
  echo '<h5>Crypto Coin Block Lists</h5>'.PHP_EOL;

  echo '<table class="bl-table">'.PHP_EOL;
  draw_blocklist_row('bl_cbl_all', 'Coin Blocker Lists - All', 'This list contains all crypto mining domains - A list for administrators to prevent mining in networks.', 'https://gitlab.com/ZeroDot1/CoinBlockerLists');

  draw_blocklist_row('bl_cbl_opt', 'Coin Blocker Lists - Optional', 'This list contains all optional mining domains - An additional list for administrators.', 'https://gitlab.com/ZeroDot1/CoinBlockerLists');

  draw_blocklist_row('bl_cbl_browser', 'Coin Blocker Lists - Browser', 'This list contains all browser mining domains - A list to prevent browser mining only.', 'https://gitlab.com/ZeroDot1/CoinBlockerLists');

  echo '</table>'.PHP_EOL;                                 //End bl table
  echo '</div>'.PHP_EOL;                                   //End Tab
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
  echo '<h5>Social Block Lists</h5>'.PHP_EOL;

  echo '<table class="bl-table">'.PHP_EOL;

  draw_blocklist_row('bl_fbannoyance', 'Fanboy&rsquo;s Annoyance List', 'Block Pop-Ups and other annoyances.', 'https://www.fanboy.co.nz/');
  draw_blocklist_row('bl_fbsocial', 'Fanboy&rsquo;s Social Blocking List', 'Block social content, widgets, scripts and icons.', 'https://www.fanboy.co.nz/');
  draw_blocklist_row('bl_ublock_annoyance', 'uBlockOrigin Annoyance List', 'Mostly for element blocking with uBlockOrigin, but contains a few domains NoTrack can use for blocking', 'https://github.com/uBlockOrigin/uAssets/blob/master/filters/annoyances.txt');

  echo '</table>'.PHP_EOL;                                 //End bl table
  echo '</div>'.PHP_EOL;                                   //End Tab
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
  echo '<h5>Multipurpose Block Lists</h5>'.PHP_EOL;

  echo '<table class="bl-table">'.PHP_EOL;

  draw_blocklist_row('bl_someonewhocares', 'Dan Pollock&rsquo;s hosts file', 'Mixture of Shock and Ad sites.', 'http://someonewhocares.org/hosts');

  draw_blocklist_row('bl_hphosts', 'hpHosts', 'Inefficient list', 'http://hosts-file.net');

  draw_blocklist_row('bl_winhelp2002', 'MVPS Hosts‎', 'Very inefficient list', 'http://winhelp2002.mvps.org/');

  echo '</table>'.PHP_EOL;                                 //End bl table
  echo '</div>'.PHP_EOL;                                   //End Tab
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

  echo '</table>'.PHP_EOL;                                 //End bl table
  echo '</div>'.PHP_EOL;                                   //End Tab
}


/********************************************************************
 *  Custom Block Lists
 *    Save button required here
 *  Params:
 *    None
 *  Return:
 *    None
 */
function custom_blocklists() {
  global $config;

  echo '<div>'.PHP_EOL;                                    //Start Tab
  echo '<h5>Custom Block Lists</h5>'.PHP_EOL;

  echo '<p>Use either Downloadable or Localy stored Block Lists</p><textarea rows="18" name="bl_custom">'.$config->get_blocklist_custom().'</textarea>';

  echo '<table class="bl-table">'.PHP_EOL;
  echo '<tr><td colspan="3"><button type="submit" name="v" value="8">Save Changes</button></td></tr>'.PHP_EOL;
  echo '</table>'.PHP_EOL;                                 //End bl table
  echo '</div>'.PHP_EOL;                                   //End Tab
}


/********************************************************************
 *  Update Blocks
 *    1: Set bl_custom by splitting and filtering values from POST[bl_custom]
 *    2: Save the config file
 *  Params:
 *    None
 *  Return:
 *    None
 */
function update_blocklists() {
  global $config;

  $customstr = '';                                         //Filtered string of POST bl_custom
  $customlist = array();                                   //Array of items from customstr
  $validlist = array();                                    //Valid items from customlist

  $customstr = $_POST['bl_custom'] ?? '';

  if (filter_string($customstr, 2000)) {
    //Split array
    $customstr = preg_replace('#\s+#',',',trim(strip_tags($customstr)));
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
      $config->set_blocklist_custom('');
    }
    else {                                                 //Yes - save as comma seperated values
      $config->set_blocklist_custom(implode(',', $validlist));
    }
  }
  else {
    $config->set_blocklist_custom('');                     //Nothing set - blank bl_custom
  }

  $config->save_blocklists();
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

  echo '<div class="sys-group">';
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
  echo '</div>'.PHP_EOL;                                   //End sys-group
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
  echo '<a href="?v=1"><img src="../svg/homebl_tracking.svg" alt=""><h6>Tracking</h6></a>'.PHP_EOL;
  echo '<a href="?v=2"><img src="../svg/homebl_advertising.svg" alt=""><h6>Advertising</h6></a>'.PHP_EOL;
  echo '<a href="?v=3"><img src="../svg/homebl_malware.svg" alt=""><h6>Malware</h6></a>'.PHP_EOL;
  echo '<a href="?v=4"><img src="../svg/homebl_cryptocoin.svg" alt=""><h6>Crypto Coin</h6></a>'.PHP_EOL;
  echo '<a href="?v=5"><img src="../svg/homebl_social.svg" alt=""><h6>Social</h6></a>'.PHP_EOL;
  echo '<a href="?v=6"><img src="../svg/homebl_multipurpose.svg" alt=""><h6>Multipurpose</h6></a>'.PHP_EOL;
  echo '<a href="?v=7"><img src="../svg/homebl_regional.svg" alt=""><h6>Regional</h6></a>'.PHP_EOL;
  echo '<a href="?v=8"><img src="../svg/homebl_custom.svg" alt=""><h6>Custom</h6></a>'.PHP_EOL;
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

$config->load_blocklists();

echo '<div id="main">'.PHP_EOL;

//Has the Save Changes button been clicked?
if (isset($_POST['action'])) {                             //Yes - Update block list conf
  update_blocklists();
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

draw_copymsg();

?>

</div>
<script>
function setBlocklist(box) {
  var xhr = new XMLHttpRequest();
  var url = '/admin/include/api.php';
  var params = 'operation=blocklist_status&blname=' + box.name + '&blstatus=' + box.checked;

  xhr.open('POST', url, true);
  xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');

  xhr.onreadystatechange = function() {
    if (this.readyState == 4 && this.status == 200) {
      //console.log(this.responseText);
      var apiResponse = JSON.parse(this.responseText);

      document.getElementById('copymsg').innerText = apiResponse['message'];
      document.getElementById('copymsg').style.display = 'block';

      //Delay for 3 seconds, then Hide copymsg element
      setTimeout(function(){
        document.getElementById('copymsg').style.display = 'none';
      },3000);
    }
  }
  xhr.send(params);
}

</script>
</body>
</html>
