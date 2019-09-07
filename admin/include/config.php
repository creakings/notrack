<?php
/*Class for NoTrack config
 */

class Config {
  public $status = STATUS_ENABLED;
  public $unpausetime = 0;

  public $DEFAULTCONFIG = array(
    'NetDev' => 'eth0',
    'IPVersion' => 'IPv4',
    'BlockMessage' => 'pixel',
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
  
  public $blocklists = array(
    'bl_notrack' => 1,
    'bl_notrack_malware' => 1,
    'bl_tld' => 1,    
    'bl_hexxium' => 1,
    'bl_cbl_all' => 0,
    'bl_cbl_browser' => 0,
    'bl_cbl_opt' => 0,
    'bl_cedia' => 0,
    'bl_cedia_immortal' => 1,
    'bl_disconnectmalvertising' => 0,
    'bl_easylist' => 0,
    'bl_easyprivacy' => 0,
    'bl_fbannoyance' => 0,
    'bl_fbenhanced' => 0,
    'bl_fbsocial' => 0,
    'bl_hphosts' => 0,
    'bl_malwaredomainlist' => 0,
    'bl_malwaredomains' => 0,    
    'bl_pglyoyo' => 0,    
    'bl_someonewhocares' => 0,
    'bl_spam404' => 0,
    'bl_swissransom' => 0,
    'bl_winhelp2002' => 0,
    'bl_windowsspyblocker' => 0,
    //Region Specific BlockLists
    'bl_areasy' => 0,
    'bl_chneasy' => 0,
    'bl_deueasy' => 0,
    'bl_dnkeasy' => 0,
    'bl_fraeasy' => 0,
    'bl_grceasy' => 0,
    'bl_huneasy' => 0,
    'bl_idneasy' => 0,
    'bl_isleasy' => 0,
    'bl_itaeasy' => 0,
    'bl_jpneasy' => 0,
    'bl_koreasy' => 0,
    'bl_korfb' => 0,
    'bl_koryous' => 0,
    'bl_ltueasy' => 0,
    'bl_lvaeasy' => 0,
    'bl_nldeasy' => 0,
    'bl_poleasy' => 0,
    'bl_ruseasy' => 0,
    'bl_spaeasy' => 0,
    'bl_svneasy' => 0,
    'bl_sweeasy' => 0,
    'bl_viefb' => 0,
    'bl_fblatin' => 0,
    'bl_yhosts' => 0,
  );

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
  /********************************************************************
   *  Load Config File
   *    1. Attempt to load Config from Memcache
   *    2. Write DefaultConfig to Config, incase any variables are missing
   *    3. Read Config File
   *    4. Split Line between: (Var = Value)
   *    5. Certain values need filtering to prevent XSS
   *    6. For other values, check if key exists, then replace with new value
   *    7. Setup SearchUrl
   *    8. Write Config to Memcache
   *  Params:
   *    Description, Value
   *  Return:
   *    None
   */
  public function load() {
    global $mem;
    $line = '';
  
    /*$Config=$mem->get('Config');                   //Load Config array from Memcache
    if (! empty($Config)) {
      return null;                                 //Did it load from memory?
    }*/
  
    $this->settings = $this->DEFAULTCONFIG;                      //Firstly Set Default Config
  
    if (file_exists(CONFIGFILE)) {                 //Check file exists
      $fh= fopen(CONFIGFILE, 'r');
      while (!feof($fh)) {
        $line = fgets($fh);                  //Read Line of LogFile
        if (preg_match('/(\w+)\s+=\s+([\S]+)/', $line, $matches)) {
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
              elseif (array_key_exists($matches[1], $this->blocklists)) {
                $this->blocklists[$matches[1]] = filter_bool($matches[2]);
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
    
    //$mem->set('conf-settings', $this->settings, 0, 1200);
    //$mem->set('conf-blocklists', $this->blocklists, 0, 1200);
    
    return null;
  }
    
  /********************************************************************
   *  Save Config
   *    1. Check if Latest Version is less than Current Version
   *    2. Open Temp Config file for writing
   *    3. Loop through Config Array
   *    4. Write all values, except for "Status = Enabled"
   *    5. Close Config File
   *    6. Delete Config Array out of Memcache, in order to force reload
   *    7. Onward process is to Display appropriate config view
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
      fwrite($fh, $key.' = '.$value.PHP_EOL);
    }
    
    //Write other non-array items to temp config
    fwrite($fh, 'status = '.$this->status.PHP_EOL);
    fwrite($fh, 'unpausetime = '.$this->unpausetime.PHP_EOL);
    fclose($fh);                                   //Close file
  
    $mem->delete('conf-settings');                        //Delete config from Memcache
  
    exec(NTRK_EXEC.'--save-conf');
}
  
  public function __construct() {
    $this->load();
  }
}

$config = new Config;
