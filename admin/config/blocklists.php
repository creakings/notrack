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
function draw_blocklist_row($bl, $bl_name, $msg) {
  global $Config;
  //Txt File = Origniating download file
  //TLD Is a special case, and the Txt file used is TLD_CSV
  
  $txtfile = false;
  $txtfilename = '';
  $txtlines = 0;
  $filename = '';
  $totalmsg = '';  
  
  if ($Config[$bl] == 0) {
    echo '<tr><td><input type="checkbox" name="'.$bl.'"></td><td>'.$bl_name.':</td><td>'.$msg.'</td></tr>'.PHP_EOL;
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
        
    $txtfile = file_exists($txtfilename);
    
    if (($rows > 0) && ($txtfile)) {
      $txtlines = intval(exec('wc -l '.$txtfilename));
      if ($rows > $txtlines) $rows = $txtlines;  //Prevent stupid result
      $totalmsg = '<p class="light">'.$rows.' used of '.$txtlines.'</p>';
    }
    else {
      $totalmsg = '<p class="light">'.$rows.' used of ?</p>';
    }
    
   
    echo '<tr><td><input type="checkbox" name="'.$bl.'" checked="checked"></td><td>'.$bl_name.':</td><td>'.$msg.' '.$totalmsg.'</td></tr>'.PHP_EOL;    
  }
    
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
  echo '<h5>Advert Blocking</h5>'.PHP_EOL;

  echo '<table class="bl-table">'.PHP_EOL;
  draw_blocklist_row('bl_easylist', 'EasyList', 'EasyList without element hiding rules‎ <a href="https://forums.lanik.us/" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  draw_blocklist_row('bl_pglyoyo', 'Peter Lowe&rsquo;s Ad server list‎', 'Some of this list is already in NoTrack <a href="https://pgl.yoyo.org/adservers/" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  echo '</table>'.PHP_EOL;                                 //End bl table

  echo '<input type="submit" value="Save Changes">'.PHP_EOL;
  echo '</div>'.PHP_EOL;                                   //End sys-group
  echo '</div>'.PHP_EOL;                                   //End Tab

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
  echo '<h5>Tracker Blocking</h5>'.PHP_EOL;

  echo '<table class="bl-table">'.PHP_EOL;
  draw_blocklist_row('bl_notrack', 'NoTrack List', 'NoTrack Block List contains mixture of Tracking and Advertising sites');
  draw_blocklist_row('bl_tld', 'Top Level Domains', 'Whole country and generic top level domains');
  draw_blocklist_row('bl_easyprivacy', 'EasyPrivacy', 'Supplementary list from AdBlock Plus <a href="https://forums.lanik.us/" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  draw_blocklist_row('bl_fbenhanced', 'Fanboy&rsquo;s Enhanced Tracking List', 'Blocks common tracking scripts <a href="https://www.fanboy.co.nz/" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  echo '</table>'.PHP_EOL;                                 //End bl table

  echo '<input type="submit" value="Save Changes">'.PHP_EOL;
  echo '</div>'.PHP_EOL;                                   //End sys-group
  echo '</div>'.PHP_EOL;                                   //End Tab

  return null;
}

/********************************************************************
 *  Show Block List Page
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function show_blocklists() {
  global $Config;

  draw_blocklist_row('bl_notrack_malware', 'NoTrack Malware', 'NoTrack Malware List contains malicious and dodgy sites that aren&rsquo;t really considered tracking or advertising');

  

  //Malware
  draw_systable('Malware');
  draw_blocklist_row('bl_hexxium', 'Hexxium Creations Threat List', 'Hexxium Creations are a small independent team running a community based malware and scam domain database <a href="https://www.hexxiumcreations.com/projects/malicious-domain-blocking" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  draw_blocklist_row('bl_cedia', 'CEDIA Malware List', 'National network investigation and education of Ecuador - Malware List <a href="https://cedia.org.ec/" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  draw_blocklist_row('bl_cedia_immortal', 'CEDIA Immortal Malware List', 'CEDIA Long-lived &#8220;immortal&#8221; Malware sites <a href="https://cedia.org.ec/" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  draw_blocklist_row('bl_disconnectmalvertising', 'Malvertising list by Disconnect', '<a href="https://disconnect.me/" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  draw_blocklist_row('bl_malwaredomainlist', 'Malware Domain List', '<a href="http://www.malwaredomainlist.com/" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  draw_blocklist_row('bl_malwaredomains', 'Malware Domains', 'A good list to add <a href="http://www.malwaredomains.com/" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  draw_blocklist_row('bl_spam404', 'Spam404', '<a href="http://www.spam404.com/" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  draw_blocklist_row('bl_swissransom', 'Swiss Security - Ransomware Tracker', 'Protects against downloads of several variants of Ransomware, including Cryptowall and TeslaCrypt <a href="https://ransomwaretracker.abuse.ch/" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  draw_blocklist_row('bl_swisszeus', 'Swiss Security - ZeuS Tracker', 'Protects systems infected with ZeuS malware from accessing Command & Control servers <a href="https://zeustracker.abuse.ch/" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  echo '</table></div>'.PHP_EOL;
  
  draw_systable('Crypto Coin Mining');                     //Start Crypto Coin
    
  draw_blocklist_row('bl_cbl_all', 'Coin Blocker Lists - All', 'This list contains all crypto mining domains - A list for administrators to prevent mining in networks. <a href="https://gitlab.com/ZeroDot1/CoinBlockerLists" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  
  draw_blocklist_row('bl_cbl_opt', 'Coin Blocker Lists - Optional', 'This list contains all optional mining domains - An additional list for administrators. <a href="https://gitlab.com/ZeroDot1/CoinBlockerLists" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  
  draw_blocklist_row('bl_cbl_browser', 'Coin Blocker Lists - Browser', 'This list contains all browser mining domains - A list to prevent browser mining only. <a href="https://gitlab.com/ZeroDot1/CoinBlockerLists" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');    
  
  echo '</table></div>'.PHP_EOL;                           //End Crypto Coin
  
  //Social
  draw_systable('Social');
  draw_blocklist_row('bl_fbannoyance', 'Fanboy&rsquo;s Annoyance List', 'Block Pop-Ups and other annoyances. <a href="https://www.fanboy.co.nz/" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  draw_blocklist_row('bl_fbsocial', 'Fanboy&rsquo;s Social Blocking List', 'Block social content, widgets, scripts and icons. <a href="https://www.fanboy.co.nz" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  echo '</table></div>'.PHP_EOL;
  
  //Multipurpose
  draw_systable('Multipurpose');
  draw_blocklist_row('bl_someonewhocares', 'Dan Pollock&rsquo;s hosts file', 'Mixture of Shock and Ad sites. <a href="http://someonewhocares.org/hosts" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  draw_blocklist_row('bl_hphosts', 'hpHosts', 'Inefficient list <a href="http://hosts-file.net" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  //draw_blocklist_row('bl_securemecca', 'Secure Mecca', 'Mixture of Adult, Gambling and Advertising sites <a href="http://securemecca.com/" target="_blank">(securemecca.com)</a>');
  draw_blocklist_row('bl_winhelp2002', 'MVPS Hosts‎', 'Very inefficient list <a href="http://winhelp2002.mvps.org/" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  echo '</table></div>'.PHP_EOL;
  
  //Region Specific
  draw_systable('Region Specific');
  draw_blocklist_row('bl_fblatin', 'Latin EasyList', 'Spanish/Portuguese Adblock List <a href="https://www.fanboy.co.nz/regional.html" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  draw_blocklist_row('bl_areasy', 'AR EasyList', 'عربي EasyList (Arab) ‎ <a href="https://forums.lanik.us/viewforum.php?f=98" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  draw_blocklist_row('bl_chneasy', 'CHN EasyList', '中文 EasyList (China)‎ <a href="http://abpchina.org/forum/forum.php" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  draw_blocklist_row('bl_yhosts', 'CHN Yhosts', 'YHosts 中文‎ focused on Chinese advert sites (China) <a href="https://github.com/vokins/yhosts" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');

  draw_blocklist_row('bl_deueasy', 'DEU EasyList', 'Deutschland EasyList (Germany) <a href="https://forums.lanik.us/viewforum.php?f=90" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  draw_blocklist_row('bl_dnkeasy', 'DNK EasyList', 'Danmark Schacks Adblock Plus liste‎ (Denmark) <a href="https://henrik.schack.dk/adblock/" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');  
  draw_blocklist_row('bl_fraeasy', 'FRA EasyList', 'France EasyList <a href="https://forums.lanik.us/viewforum.php?f=91" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  draw_blocklist_row('bl_grceasy', 'GRC EasyList', 'Ελλάδα EasyList (Greece) <a href="https://github.com/kargig/greek-adblockplus-filter" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  draw_blocklist_row('bl_huneasy', 'HUN hufilter', 'Magyar Adblock szűrőlista (Hungary) <a href="https://github.com/szpeter80/hufilter" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  draw_blocklist_row('bl_idneasy', 'IDN EasyList', 'ABPindo (Indonesia) <a href="https://github.com/ABPindo/indonesianadblockrules" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  draw_blocklist_row('bl_isleasy', 'ISL EasyList', 'Adblock Plus listi fyrir íslenskar vefsíður (Iceland) <a href="https://adblock.gardar.net" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  draw_blocklist_row('bl_itaeasy', 'ITA EasyList', 'Italia EasyList (Italy) <a href="https://forums.lanik.us/viewforum.php?f=96" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  draw_blocklist_row('bl_jpneasy', 'JPN EasyList', '日本用フィルタ (Japan) <a href="https://github.com/k2jp/abp-japanese-filters" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  draw_blocklist_row('bl_koreasy', 'KOR EasyList', '대한민국 EasyList (Korea) <a href="https://github.com/gfmaster/adblock-korea-contrib" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  draw_blocklist_row('bl_korfb', 'KOR Fanboy', '대한민국 Fanboy&rsquo;s list (Korea) <a href="https://forums.lanik.us/" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  draw_blocklist_row('bl_koryous', 'KOR YousList', '대한민국 YousList (Korea) <a href="https://github.com/yous/YousList" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  draw_blocklist_row('bl_ltueasy', 'LTU EasyList', 'Lietuva EasyList (Lithuania) <a href="http://margevicius.lt/easylist_lithuania" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  draw_blocklist_row('bl_lvaeasy', 'LVA EasyList', 'Latvija List (Latvia) <a href="https://notabug.org/latvian-list/adblock-latvian" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  draw_blocklist_row('bl_nldeasy', 'NLD EasyList', 'Nederland EasyList (Dutch) <a href="https://forums.lanik.us/viewforum.php?f=100" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  draw_blocklist_row('bl_poleasy', 'POL EasyList', 'Polskie filtry do Adblocka (Poland) <a href="https://www.certyficate.it/adblock-ublock-polish-filters/" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  draw_blocklist_row('bl_ruseasy', 'RUS EasyList', 'Россия RuAdList+EasyList (Russia) <a href="https://forums.lanik.us/viewforum.php?f=102" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  draw_blocklist_row('bl_spaeasy', 'SPA EasyList', 'España EasyList (Spain) <a href="https://forums.lanik.us/viewforum.php?f=103" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  draw_blocklist_row('bl_svneasy', 'SVN EasyList', 'Slovenska lista (Slovenia) <a href="https://github.com/betterwebleon/slovenian-list" target="_blank"><img alt="Link" src="../svg/icon_home.svg"></a>');
  
  echo '</table></div>'.PHP_EOL;
  
  draw_systable('Custom Block Lists');
  draw_sysrow('Custom', '<p>Use either Downloadable or Localy stored Block Lists</p><textarea rows="5" name="bl_custom">'.str_replace(',', PHP_EOL,$Config['bl_custom']).'</textarea>');
  
  echo '<tr><td>&nbsp;</td><td><input type="submit" value="Save Changes"></td></tr>'.PHP_EOL;
  echo '</table><br>'.PHP_EOL;
  
  
  echo '</div></form>'.PHP_EOL;
  
  return null;
}

/*
case 'blocklists':
      update_blocklist_config();
      save_config();
      exec(NTRK_EXEC.'--run-notrack');
      $mem->delete('SiteList');                  //Delete Site Blocked from Memcache
      sleep(1);                                  //Short pause to prevent race condition
      header('Location: ?v=blocks');             //Reload page
      break;
*/

/********************************************************************
 *  Update Block List Config
 *    1: Search through Config array for bl_? (excluding bl_custom)
 *    2: Check if bl_? appears in POST[bl_?]
 *    3: Set bl_custom by splitting and filtering values from POST[bl_custom]
 *    4: After this function save_config is run
 *  Params:
 *    None
 *  Return:
 *    None
 */
function update_blocklist_config() {  
  global $Config;
  $customstr = '';
  $customlist = array();
  $validlist = array();
  $key = '';
  $value = '';
  
  print_r($_POST);
  exit;
  /*
  foreach($Config as $key => $value) {           //Read entire Config array
    if (preg_match('/^bl\_(?!custom)/', $key) > 0) { //Look for values starting bl_
      if (isset($_POST[$key])) {                 //Is there an equivilent POST value?
        if ($_POST[$key] == 'on') {              //Is it set to on (ticked)?
          $Config[$key] = 1;                     //Yes - enable block list
        }
      }
      else {                                     //No POST value
        $Config[$key] = 0;                       //Block list is unticked
      }
    }
  }
  
  if (filter_string('bl_custom', 'POST', 2000)) {          //bl_custom requires extra processing
    $customstr = preg_replace('#\s+#',',',trim(strip_tags($_POST['bl_custom']))); //Split array
    $customlist = explode(',', $customstr);      //Split string into array
    foreach ($customlist as $site) {             //Check if each item is a valid URL
      if (filter_url($site)) {
        $validlist[] = strip_tags($site);
      }
      elseif (preg_match('/\/\w{3,4}\/[\w\/\.]/', $site) > 0) { #Or file location?
        $validlist[] = strip_tags($site);
      }
    }
    if (sizeof($validlist) == 0) $Config['bl_custom'] = '';
    else $Config['bl_custom'] = implode(',', $validlist);
  }
  else {
    $Config['bl_custom'] = "";
  }
    */
  return null;
}

function draw_tabbedview($view) {
  $tab = filter_integer($view, 1, 7, 1);
  $checkedtabs = array('', '', '', '', '', '', '', '');
  $checkedtabs[$tab] = ' checked';
  
  echo '<form name="blocklists" action="?" method="post">'.PHP_EOL;
  echo '<input type="hidden" name="action" value="blocklists">'.PHP_EOL;
  echo '<input type="hidden" name="v" value="'.$tab.'">'.PHP_EOL;
  
  echo '<div id="tabbed">'.PHP_EOL;                          //Start tabbed container

  echo '<input type="radio" name="tabs" id="tab-nav-1"'.$checkedtabs[1].'><label for="tab-nav-1">Tracking</label>'.PHP_EOL;
  echo '<input type="radio" name="tabs" id="tab-nav-2"'.$checkedtabs[2].'><label for="tab-nav-2">Advertising</label>'.PHP_EOL;
  echo '<input type="radio" name="tabs" id="tab-nav-3"'.$checkedtabs[3].'><label for="tab-nav-3">Malware</label>'.PHP_EOL;
  echo '<input type="radio" name="tabs" id="tab-nav-4"'.$checkedtabs[4].'><label for="tab-nav-4">Crypto Coin</label>'.PHP_EOL;
  echo '<input type="radio" name="tabs" id="tab-nav-5"'.$checkedtabs[5].'><label for="tab-nav-5">Social</label>'.PHP_EOL;
  echo '<input type="radio" name="tabs" id="tab-nav-6"'.$checkedtabs[6].'><label for="tab-nav-6">Multipurpose</label>'.PHP_EOL;
  echo '<input type="radio" name="tabs" id="tab-nav-7"'.$checkedtabs[7].'><label for="tab-nav-7">Regional</label>'.PHP_EOL;
  //echo '<input type="radio" name="tabs" id="tab-nav-8"'.$checkedtabs[8].'><label for="tab-nav-8">Regional</label>'.PHP_EOL;
  

  echo '<div id="tabs">'.PHP_EOL;

  tracking_blocklists();
  advertising_blocklists();
  //show_blocklists();

  echo '</div>'.PHP_EOL;                                     //End tabs
  echo '</div>'.PHP_EOL;                                     //End tabbed container
  echo '</form>'.PHP_EOL;                                    //End form
}

function draw_welcome() {
  echo '<div class="sys-group">'.PHP_EOL;
  echo '<a href="?v=1">Number 1</a>'.PHP_EOL;
  echo '<a href="?v=2">Number 2</a>'.PHP_EOL;
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
//echo '<form name="blocklists" action="?" method="post">'.PHP_EOL;
//echo '<input type="hidden" name="action" value="blocklists">'.PHP_EOL;
if (isset($_POST['action'])) {
  update_blocklist_config();
}

if (isset($_GET['v'])) {
  draw_tabbedview($_GET['v']);
}
else {
  draw_welcome();
}

/*echo '</div>'.PHP_EOL;                                     //End tabs
echo '</div>'.PHP_EOL;                                     //End tabbed container
echo '</form>'.PHP_EOL;                                    //End form*/

$db->close();
?>

</div>
</body>
</html>
