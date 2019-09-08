<?php
/*Class for NoTrack config
 *  Holds the users settings from /etc/notrack/notrack.conf
 *  Values are split between two arrays: settings and blocklists
 *  All blocklists, except for bl_custom are held in the blocklists array.
 *  (Most blocklists are either enabled - true or disabled - false, with the exception of bl_custom which can be a list of addresses / files)
 *
 *  settings and blocklists are stored in Memcache to improve performance.
 *
 *  New blocklists should be added to DEFAULTBLOCKLISTS, BLOCKLISTNAMES, and BLOCKLISTEVENT
 *
 *
 */

class Config {
  public $status = STATUS_ENABLED;
  public $unpausetime = 0;

  public $DEFAULTCONFIG = array(
    'NetDev' => 'eth0',
    'IPVersion' => 'IPv4',
    'blockmessage' => 'pixel',
    'Search' => 'DuckDuckGo',
    'SearchUrl' => '',
    'WhoIs' => 'Who.is',
    'WhoIsUrl' => '',
    'whoisapi' => '',
    'Username' => '',
    'Password' => '',
    'Delay' => 30,
    'Suppress' => '',
    'ParsingTime' => 4,
    'api_key' => '',
    'api_readonly' => '',
    'LatestVersion' => VERSION,
    'bl_custom' => '',
  );

  //Single word code of blocklists
  private $DEFAULTBLOCKLISTS = array(
    'bl_notrack' => true,
    'bl_notrack_malware' => true,
    'bl_tld' => true,
    'bl_hexxium' => true,
    'bl_cbl_all' => false,
    'bl_cbl_browser' => false,
    'bl_cbl_opt' => false,
    'bl_cedia' => false,
    'bl_cedia_immortal' => true,
    'bl_disconnectmalvertising' => false,
    'bl_easylist' => false,
    'bl_easyprivacy' => false,
    'bl_fbannoyance' => false,
    'bl_fbenhanced' => false,
    'bl_fbsocial' => false,
    'bl_hphosts' => false,
    'bl_malwaredomainlist' => false,
    'bl_malwaredomains' => false,
    'bl_pglyoyo' => false,
    'bl_someonewhocares' => false,
    'bl_spam404' => false,
    'bl_swissransom' => false,
    'bl_winhelp2002' => false,
    'bl_windowsspyblocker' => false,
    //Region Specific BlockLists
    'bl_areasy' => false,
    'bl_chneasy' => false,
    'bl_deueasy' => false,
    'bl_dnkeasy' => false,
    'bl_fraeasy' => false,
    'bl_grceasy' => false,
    'bl_huneasy' => false,
    'bl_idneasy' => false,
    'bl_isleasy' => false,
    'bl_itaeasy' => false,
    'bl_jpneasy' => false,
    'bl_koreasy' => false,
    'bl_korfb' => false,
    'bl_koryous' => false,
    'bl_ltueasy' => false,
    'bl_lvaeasy' => false,
    'bl_nldeasy' => false,
    'bl_poleasy' => false,
    'bl_ruseasy' => false,
    'bl_spaeasy' => false,
    'bl_svneasy' => false,
    'bl_sweeasy' => false,
    'bl_viefb' => false,
    'bl_fblatin' => false,
    'bl_yhosts' => false,
  );

  //Legible names of each blocklist code
  const BLOCKLISTNAMES = array(
    'custom' => 'Custom',
    'bl_tld' => 'Top Level Domain',
    'bl_notrack' => 'NoTrack Block List',
    'bl_notrack_malware' => 'NoTrack Malware',
    'bl_cbl_all' => 'Coin Block List - All',
    'bl_cbl_browser' => 'Coin Block List - Browser',
    'bl_cbl_opt' => 'Coin Block List - Optional',
    'bl_cedia' => 'CEDIA Malware',
    'bl_cedia_immortal' => 'CEDIA Immortal Malware',
    'bl_someonewhocares' => 'Dan Pollocks&rsquo;s hosts',
    'bl_disconnectmalvertising' => 'Malvertising by Disconnect',
    'bl_easylist' => 'Easy List',
    'bl_easyprivacy' => 'Easy Privacy',
    'bl_fbannoyance' => 'Fanboy&rsquo;s Annoyance',
    'bl_fbenhanced' => 'Fanboy&rsquo;s Enhanced',
    'bl_fbsocial' => 'Fanboy&rsquo;s Social',
    'bl_hexxium' => 'Hexxium',
    'bl_hphosts' => 'hpHosts',
    'bl_malwaredomainlist' => 'Malware Domain List',
    'bl_malwaredomains' => 'Malware Domains',
    'bl_winhelp2002' => 'MVPS Hosts',
    'bl_pglyoyo' => 'Peter Lowe&rsquo;s Ad List',
    'bl_spam404'=> 'Spam 404',
    'bl_swissransom' => 'Swiss Security Ransomware',
    'bl_windowsspyblocker' => 'Windows Spy Blocker',
    'bl_areasy' => 'AR Easy List',
    'bl_chneasy' => 'CHN Easy List',
    'bl_yhosts' => 'CHN Yhosts',
    'bl_deueasy' => 'DEU Easy List',
    'bl_dnkeasy' => 'DNK Easy List',
    'bl_fraeasy' => 'FRA Easy List',
    'bl_grceasy' => 'GRC Easy List',
    'bl_huneasy' => 'HUN Easy List',
    'bl_idneasy' => 'IDN Easy List',
    'bl_itaeasy' => 'ITA Easy List',
    'bl_jpneasy' => 'JPN Easy List',
    'bl_koreasy' => 'KOR Easy List',
    'bl_korfb' => 'KOR Fanboy',
    'bl_koryous' => 'KOR Yous List',
    'bl_ltueasy' => 'LTU Easy List',
    'bl_nldeasy' => 'NLD Easy List',
    'bl_ruseasy' => 'RUS Easy List',
    'bl_spaeasy' => 'SPA Easy List',
    'bl_svneasy' => 'SVN Easy List',
    'bl_sweeasy' => 'SWE Easy List',
    'bl_viefb' => 'VIE Fanboy',
    'bl_fblatin' => 'Latin Easy List',
  );

  //What type of data is in each blocklist
  const BLOCKLISTEVENT = array(
    'custom' => 'custom',
    'bl_tld' => 'tld',
    'bl_notrack' => 'notrack',
    'bl_notrack_malware' => 'malware',
    'bl_cbl_all' => 'cryptocoin',
    'bl_cbl_browser' => 'cryptocoin',
    'bl_cbl_opt' => 'cryptocoin',
    'bl_cedia' => 'malware',
    'bl_cedia_immortal' => 'malware',
    'bl_someonewhocares' => 'misc',
    'bl_disconnectmalvertising' => 'malware',
    'bl_easylist' => 'advert',
    'bl_easyprivacy' => 'tracker',
    'bl_fbannoyance' => 'misc',
    'bl_fbenhanced' => 'tracker',
    'bl_fbsocial' => 'misc',
    'bl_hexxium' => 'malware',
    'bl_hphosts' => 'advert',
    'bl_malwaredomainlist' => 'malware',
    'bl_malwaredomains' => 'malware',
    'bl_winhelp2002' => 'advert',
    'bl_pglyoyo' => 'advert',
    'bl_spam404'=> 'misc',
    'bl_swissransom' => 'malware',
    'bl_windowsspyblocker' => 'tracker',
    'bl_areasy' => 'advert',
    'bl_chneasy' => 'advert',
    'bl_yhosts' => 'advert',
    'bl_deueasy' => 'advert',
    'bl_dnkeasy' => 'advert',
    'bl_fraeasy' => 'advert',
    'bl_grceasy' => 'advert',
    'bl_huneasy' => 'advert',
    'bl_idneasy' => 'advert',
    'bl_itaeasy' => 'advert',
    'bl_jpneasy' => 'advert',
    'bl_koreasy' => 'advert',
    'bl_korfb' => 'advert',
    'bl_koryous' => 'advert',
    'bl_ltueasy' => 'advert',
    'bl_nldeasy' => 'advert',
    'bl_ruseasy' => 'advert',
    'bl_spaeasy' => 'advert',
    'bl_svneasy' => 'advert',
    'bl_sweeasy' => 'advert',
    'bl_viefb' => 'advert',
    'bl_fblatin' => 'advert',
  );

  const SEARCHENGINELIST = array(
    'Baidu' => 'https://www.baidu.com/s?wd=',
    'Bing' => 'https://www.bing.com/search?q=',
    'DuckDuckGo' => 'https://duckduckgo.com/?q=',
    'Ecosia' =>'https://www.ecosia.org/search?q=',
    'Exalead' => 'https://www.exalead.com/search/web/results/?q=',
    'Gigablast' => 'https://www.gigablast.com/search?q=',
    'Google' => 'https://www.google.com/search?q=',
    'Qwant' => 'https://www.qwant.com/?q=',
    'StartPage' => 'https://startpage.com/do/search?q=',
    'WolframAlpha' => 'https://www.wolframalpha.com/input/?i=',
    'Yahoo' => 'https://search.yahoo.com/search?p=',
    'Yandex' => 'https://www.yandex.com/search/?text='
  );

  const WHOISLIST = array(
    'DomainTools' => 'http://whois.domaintools.com/',
    'Icann' => 'https://whois.icann.org/lookup?name=',
    'Who.is' => 'https://who.is/whois/'
  );

  public $settings = array();
  public $blocklists = array();


  /********************************************************************
   *  Load Config File
   *    1. Attempt to load settings and blocklist arrays from Memcache
   *    2. Write default values to settings and blocklist arrays
   *    3. Read Config File
   *    4. Split Line into "key" = "value" using regex
   *       matches[1] = key, matches[2] = value
   *    5. Certain values need filtering to prevent XSS
   *    6. For other values, check if key exists, then replace with new value
   *    7. Setup SearchUrl
   *    8. Write Config to Memcache
   *
   *  Params:
   *    None
   *  Return:
   *    None
   */
  public function load() {
    global $mem;

    $line = '';
    $matches = array();

    //Attempt to load settings and blocklists arrays from Memcache
    $this->settings = $mem->get('conf-settings');
    $this->blocklists = $mem->get('conf-blocklists');
    if ((! empty($this->settings)) && (! empty($this->blocklists))) {
      return null;
    }

    //Nothing loaded from Memcache
    //Firstly Set settings and blocklists arrays to their default values
    $this->settings = $this->DEFAULTCONFIG;
    $this->blocklists = $this->DEFAULTBLOCKLISTS;

    if (file_exists(CONFIGFILE)) {                         //Check config file exists
      $fh= fopen(CONFIGFILE, 'r');                         //Open config
      while (!feof($fh)) {
        $line = fgets($fh);                                //Read Line of LogFile

        //Check if the line matches a blocklist (excluding bl_custom)
        if (preg_match('/^(bl_(?!custom)\[a-z_]{5,25}) = (0|1)/', $line, $matches)) {
          if (array_key_exists($matches[1], $this->blocklists)) {
            $this->blocklists[$matches[1]] = (bool)$matches[2];
          }
        }

        //Match any other config line. #Comments are ignored
        elseif (preg_match('/(\w+)\s+=\s+([\S]+)/', $line, $matches)) {
          switch ($matches[1]) {
            case 'Delay':
              $this->settings['Delay'] = filter_integer($matches[2], 0, 3600, 30);
              break;
            case 'ParsingTime':
              $this->settings['ParsingTime'] = filter_integer($matches[2], 1, 60, 7);
              break;
            case 'status':
              $this->status = filter_integer($matches[2], 1, PHP_INT_MAX, 0);
              break;
            case 'unpausetime':
              $this->unpausetime = filter_integer($matches[2], 1, PHP_INT_MAX, 0);
              break;
            default:
              if (array_key_exists($matches[1], $this->settings)) {
                $this->settings[$matches[1]] = strip_tags($matches[2]);
              }
              break;
          }
        }
      }

      fclose($fh);
    }

    //Set SearchUrl if User hasn't configured a custom string via notrack.conf
    if ($this->settings['SearchUrl'] == '') {
      if (array_key_exists($this->settings['Search'], self::SEARCHENGINELIST)) {
        $this->settings['SearchUrl'] = self::SEARCHENGINELIST[self::settings['Search']];
      }
      else {
        $this->settings['SearchUrl'] = self::SEARCHENGINELIST['DuckDuckGo'];
      }
    }

    //Set WhoIsUrl if User hasn't configured a custom string via notrack.conf
    if ($this->settings['WhoIsUrl'] == '') {
      if (array_key_exists($this->settings['WhoIs'], self::WHOISLIST)) {
        $this->settings['WhoIsUrl'] = self::WHOISLIST[$this->settings['WhoIs']];
      }
      else {
        $this->settings['WhoIsUrl'] = self::WHOISLIST['Who.is'];
      }
    }

    $mem->set('conf-settings', $this->settings, 0, 1200);
    $mem->set('conf-blocklists', $this->blocklists, 0, 1200);
  }


  /********************************************************************
   *  Save Config
   *    1. Check if Latest Version is less than Current Version
   *    2. Open Temp Config file for writing
   *    3. Loop through settings and blocklist arrays
   *    4. Write other non-array values
   *    5. Close Config File
   *    6. Delete Config Array out of Memcache, in order to force reload
   *    7. Call ntrk-exec to replace old /etc/notrack/notrack.conf with temp config
   *
   *  Params:
   *    None
   *  Return:
   *    SQL Query string
   */
  public function save() {
    global $mem;

    $key = '';
    $value = '';

    //Prevent wrong version being written to config file if user has just upgraded and old LatestVersion is still stored in Memcache
    if (check_version($this->settings['LatestVersion'])) {
        $this->settings['LatestVersion'] = VERSION;
    }

    $fh = fopen(CONFIGTEMP, 'w');                          //Open temp config for writing

    //Write each value of settings array to temp config
    foreach ($this->settings as $key => $value) {
      fwrite($fh, $key.' = '.$value.PHP_EOL);
    }

    //Write each value of blocklists array to temp config
    foreach ($this->blocklists as $key => $value) {
      if ($value) {
        fwrite($fh, $key.' = 1'.PHP_EOL);
      }
      else {
        fwrite($fh, $key.' = 0'.PHP_EOL);
      }
    }

    //Write other non-array items to temp config
    fwrite($fh, 'status = '.$this->status.PHP_EOL);
    fwrite($fh, 'unpausetime = '.$this->unpausetime.PHP_EOL);
    fclose($fh);                                           //Close temp file

    $mem->delete('conf-settings');                         //Delete config from Memcache
    $mem->delete('conf-blocklists');                       //Delete config from Memcache

    exec(NTRK_EXEC.'--save-conf');
  }


   /********************************************************************
   *  Get Block List Name
   *    Returns the name of block list if it exists in the names array
   *
   *  Params:
   *    $bl - bl_name
   *  Return:
   *    Full block list name
   *    Or what it has been named as
   */
  public function get_blocklistname($bl) {

    if (array_key_exists($bl, self::BLOCKLISTNAMES)) {
      return self::BLOCKLISTNAMES[$bl];
    }

    return $bl;
  }


  /********************************************************************
   *  Get Block List Event
   *    Returns the name of block list event if it exists in the event array
   *
   *  Params:
   *    $bl - bl_name
   *  Return:
   *    event value
   */
  function get_blocklistevent($bl) {

    if (array_key_exists($bl, self::BLOCKLISTEVENT)) {
      return self::BLOCKLISTEVENT[$bl];
    }
    elseif (substr($bl, 0, 6) == 'custom') {               //Could be a custom_x list
      return 'custom';
    }

    return $bl;                                            //Shouldn't get to here
  }


  /********************************************************************
   *  Constructor
   *
   *  Params:
   *    None
   *  Return:
   *    None
   */
  public function __construct() {
    $this->load();
  }
}

$config = new Config;
