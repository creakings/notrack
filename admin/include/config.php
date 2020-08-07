<?php
/*Class for NoTrack config
 *  Holds the users settings from /etc/notrack/notrack.conf
 *  Values are split between two arrays: settings and blocklists
 *  All blocklists, except for bl_custom are held in the blocklists array.
 *  (Most blocklists are either enabled - true or disabled - false, with the exception of bl_custom which can be a list of addresses / files)
 *
 *  settings and blocklists are stored in Memcache to improve performance.
 *
 *  New blocklists should be added to DEFAULTBLOCKLISTS, BLOCKLISTNAMES, and BLOCKLISTTYPE
 *
 *
 */

class Config {
  private $latestversion = VERSION;

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
    'LatestVersion' => VERSION, //DEPRECATED
    'bl_custom' => '',
  );

  public $blocklists = array(
    'bl_blacklist' => array(true, 'custom', 'Custom List'),
    'bl_tld' => array(true, 'tld', 'Top Level Domain'),
    'bl_notrack' => array(true, 'tracker', 'NoTrack Block List'),
    'bl_notrack_malware' => array(true, 'malware', 'NoTrack Malware'),
    'bl_cbl_all' => array(false, 'cryptocoin', 'Coin Block List - All'),
    'bl_cbl_browser' => array(false, 'cryptocoin', 'Coin Block List - Browser'),
    'bl_cbl_opt' => array(false, 'cryptocoin', 'Coin Block List - Optional'),
    'bl_cedia' => array(false, 'malware', 'CEDIA Malware'),
    'bl_cedia_immortal' => array(true, 'malware', 'CEDIA Immortal Malware'),
    'bl_ddg_confirmed' => array(false, 'tracker', 'DuckDuckGo - Confirmed'),
    'bl_ddg_high' => array(false, 'tracker', 'DuckDuckGo - High'),
    'bl_ddg_medium' => array(false, 'tracker', 'DuckDuckGo - Medium'),
    'bl_ddg_low' => array(false, 'tracker', 'DuckDuckGo - Low'),
    'bl_ddg_unknown' => array(false, 'tracker', 'DuckDuckGo - Unknown'),
    'bl_disconnectmalvertising' => array(false, 'malware', 'Malvertising by Disconnect'),
    'bl_easylist' => array(false, 'advert', 'Easy List'),
    'bl_easyprivacy' => array(false, 'tracker', 'Easy Privacy'),
    'bl_fbannoyance' => array(false, 'misc', 'Fanboy&rsquo;s Annoyance'),
    'bl_fbenhanced' => array(false, 'tracker', 'Fanboy&rsquo;s Enhanced'),
    'bl_fbsocial' => array(false, 'misc', 'Fanboy&rsquo;s Social'),
    'bl_hexxium' => array(true, 'malware', 'Hexxium'),
    'bl_hphosts' => array(false, 'advert', 'hpHosts'),
    'bl_malwaredomainlist' => array(false, 'malware', 'Malware Domains List'),
    'bl_malwaredomains' => array(false, 'malware', 'Malware Domain'),
    'bl_pglyoyo' => array(false, 'advert', 'Peter Lowe&rsquo;s Ad List'),
    'bl_someonewhocares' => array(false, 'misc', 'Dan Pollocks&rsquo;s hosts'),
    'bl_spam404' => array(false, 'misc', 'Spam 404'),
    'bl_swissransom' => array(false, 'malware', 'Swiss Security Ransomware'),
    'bl_winhelp2002' => array(false, 'advert', 'MVPS Hosts'),
    'bl_windowsspyblocker' => array(false, 'tracker', 'Windows Spy Blocker'),
    //Region Specific BlockLists
    'bl_areasy' => array(false, 'advert', 'AR Easy List'),
    'bl_chneasy' => array(false, 'advert', 'CHN Easy List'),
    'bl_deueasy' => array(false, 'advert', 'DEU Easy List'),
    'bl_dnkeasy' => array(false, 'advert', 'DNK Easy List'),
    'bl_fraeasy' => array(false, 'advert', 'FRA Easy List'),
    'bl_grceasy' => array(false, 'advert', 'GRC Easy List'),
    'bl_huneasy' => array(false, 'advert', 'HUN Easy List'),
    'bl_idneasy' => array(false, 'advert', 'IDN Easy List'),
    'bl_isleasy' => array(false, 'advert', 'ISL Easy List'),
    'bl_itaeasy' => array(false, 'advert', 'ITA Easy List'),
    'bl_jpneasy' => array(false, 'advert', 'JPN Easy List'),
    'bl_koreasy' => array(false, 'advert', 'KOR Easy List'),
    'bl_korfb' => array(false, 'advert', 'KOR Fanboy'),
    'bl_koryous' => array(false, 'advert', 'KOR Yous List'),
    'bl_ltueasy' => array(false, 'advert', 'LTU Easy List'),
    'bl_lvaeasy' => array(false, 'advert', 'NLD Easy List'),
    'bl_nldeasy' => array(false, 'advert', 'RUS Easy List'),
    'bl_poleasy' => array(false, 'advert', 'POL Easy List'),
    'bl_ruseasy' => array(false, 'advert', 'RUS Easy List'),
    'bl_spaeasy' => array(false, 'advert', 'SPA Easy List'),
    'bl_svneasy' => array(false, 'advert', 'SVN Easy List'),
    'bl_sweeasy' => array(false, 'advert', 'SWE Easy List'),
    'bl_viefb' => array(false, 'advert', 'VIE Fanboy'),
    'bl_fblatin' => array(false, 'advert', 'Latin Easy List'),
    'bl_yhosts' => array(false, 'advert', 'CHN Yhosts'),
    'custom' => array(true, 'custom', 'Custom'), #DEPRECATED
    'invalid' => array(false, 'invalid', 'Invalid'),
    'whitelist' => array(false, 'custom', 'Whitelist'),
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
  //public $blocklists = array();


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
    $line = '';
    $matches = array();

    //Firstly Set settings and blocklists arrays to their default values
    $this->settings = $this->DEFAULTCONFIG;

    if (file_exists(CONFIGFILE)) {                         //Check config file exists
      $fh= fopen(CONFIGFILE, 'r');                         //Open config
      while (!feof($fh)) {
        $line = fgets($fh);                                //Read Line of LogFile

        //Check if the line matches a blocklist (excluding bl_custom)
        if (preg_match('/^(bl_(?!custom)[a-z_]{5,25}) = (0|1)/', $line, $matches)) {
          if (array_key_exists($matches[1], $this->blocklists)) {
            $this->blocklists[$matches[1]][0] = (bool)$matches[2];
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
        $this->settings['SearchUrl'] = self::SEARCHENGINELIST[$this->settings['Search']];
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

    //DEPRECATED
    //Prevent wrong version being written to config file if user has just upgraded and old LatestVersion is still stored in Memcache
    /*if (check_version($this->settings['LatestVersion'])) {
        $this->settings['LatestVersion'] = VERSION;
    }*/

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

    exec(NTRK_EXEC.'--save-conf'); // DEPRECATED
    exec(NTRK_EXEC.'--save conf');
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

    if (array_key_exists($bl, $this->blocklists)) {
      return $this->blocklists[$bl][2];
    }

    return $bl;
  }


  /********************************************************************
   *  Get Block List Type
   *    Returns the type of blocklist based on bl_source
   *
   *  Params:
   *    $bl - bl_name
   *  Return:
   *    event value
   */
  function get_blocklisttype($bl_source) {

    if (array_key_exists($bl_source, $this->blocklists)) {
      return $this->blocklists[$bl_source][1];
    }
    /*elseif (substr($bl, 0, 6) == 'custom') {               //Could be a custom_x list
      return 'custom';
    }*/

    return 'custom';                                       //Shouldn't get to here
  }

  /********************************************************************
   *  Return value of latestversion
   */
  function get_latestversion() {
    return $this->latestversion;
  }

  /********************************************************************
   *  Set value of latestversion
   */
  function set_latestversion($newversion) {
    $this->latestversion = $newversion;
  }



  /********************************************************************
   *  Is Blocklist Active
   *    Returns status of specified blocklist
   *
   *  Params:
   *    $bl (str): Blocklist name
   *  Return:
   *    True - Blocklist active
   *    False - Blocklist disabled
   */
  function is_blocklist_active($bl) {
    return $this->blocklists[$bl];
  }

  private function load_status() {
    if (file_exists(DIR_SETTINGS.'status.php')) {
      include DIR_SETTINGS.'status.php';
    }

    //Check unpause time hasn't been exceeded
    if ($this->status & STATUS_PAUSED) {
      if ($this->unpausetime < time()) {                   //Unpause needs to happen
        $this->status -= STATUS_PAUSED;                    //Remove Pause status
        $this->status += STATUS_ENABLED;                   //Add Enable status
        $this->unpausetime = 0;
        $this->save_status($this->status, 0);              //Update the status.php settings file
      }
    }
  }

  public function set_status($newstatus, $newunpausetime) {
    $this->status = $newstatus;
    $this->unpausetime = $newunpausetime;
  }

  public function save_status($newstatus, $newunpausetime) {
    $filelines = array(
      '<?php'.PHP_EOL,
      "\$this->set_status({$newstatus}, {$newunpausetime});".PHP_EOL,
      '?>'.PHP_EOL,
    );

    file_put_contents(DIR_SETTINGS.'status.php', $filelines);
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

    $this->load_status();
  }
}

$config = new Config;
