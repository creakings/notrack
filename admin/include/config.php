<?php
/*Class for NoTrack config
 *
 *
 *
 */
define('SETTINGS_BL', $_SERVER['DOCUMENT_ROOT'].'/admin/settings/bl.php');
define('SETTINGS_FRONT',  $_SERVER['DOCUMENT_ROOT'].'/admin/settings/front.php');
define('SETTINGS_SERVER', $_SERVER['DOCUMENT_ROOT'].'/admin/settings/server.php');
define('SETTINGS_STATUS', $_SERVER['DOCUMENT_ROOT'].'/admin/settings/status.php');

class Config {
  //Front-End PHP Settings
  private $api_key = '';
  private $api_readonly = '';
  private $password = '';
  private $username = '';
  private $search_engine = 'DuckDuckGo';
  private $search_url = 'https://duckduckgo.com/?q=';
  private $whois_api = '';
  private $whois_provider = 'who.is';
  private $whois_url = 'https://who.is/whois/';
  //Version Info
  private $latestversion = VERSION;
  //DHCP Settings
  private $dhcp_authoritative = false;
  private $dhcp_enabled = false;
  private $dhcp_leasetime = '24h';
  private $dhcp_gateway = '';
  private $dhcp_rangestart = '';
  private $dhcp_rangeend = '';
  private $dhcp_hosts = array();

  //DNS Settings
  private $dns_blockip = '127.0.0.1';
  private $dns_interface = 'eth0';
  private $dns_listenip = '127.0.0.1';
  private $dns_listenport = 53;
  private $dns_logretention = 60;
  private $dns_name = 'notrack.local';
  private $dns_server = 'Cloudflare';
  private $dns_serverip1 = '1.1.1.1';
  private $dns_serverip2 = '1.0.0.1';

  private $bl_custom = '';

  //Filters used in filter_var to validate user input
  private $setfilters = array(
    //Front-End PHP Settings
    'api_key' => FILTER_SANITIZE_STRING,
    'api_readonly' => FILTER_SANITIZE_STRING,
    'latestversion' => FILTER_SANITIZE_STRING,
    'password' => FILTER_SANITIZE_STRING,
    'username' => FILTER_SANITIZE_STRING,
    'search_engine' => FILTER_SANITIZE_STRING,
    'search_url' => FILTER_SANITIZE_STRING,
    'whois_api' => FILTER_SANITIZE_STRING,
    'whois_provider' => FILTER_SANITIZE_STRING,
    'whois_url' => FILTER_SANITIZE_STRING,
    //DHCP Settings
    'dhcp_authoritative' => FILTER_VALIDATE_BOOLEAN,
    'dhcp_enabled' => FILTER_VALIDATE_BOOLEAN,
    'dhcp_leasetime' => FILTER_SANITIZE_STRING,
    'dhcp_gateway' => FILTER_VALIDATE_IP,
    'dhcp_rangestart' => FILTER_VALIDATE_IP,
    'dhcp_rangeend' => FILTER_VALIDATE_IP,
    //DNS Settings
    'dns_blockip' => FILTER_VALIDATE_IP,
    'dns_interface' => FILTER_SANITIZE_STRING,
    'dns_listenip' => FILTER_VALIDATE_IP,
    'dns_listenport' => FILTER_VALIDATE_INT,
    'dns_logretention' => FILTER_VALIDATE_INT,
    'dns_name' => FILTER_SANITIZE_URL,
    'dns_server' => FILTER_SANITIZE_STRING,
    'dns_serverip1' => FILTER_VALIDATE_IP,
    'dns_serverip2' => FILTER_VALIDATE_IP,
  );
  public $status = STATUS_ENABLED;
  public $unpausetime = 0;

  //0 - Enabled / Disabled, 1 - List Type, 2 - List Name
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
    'custom' => array(true, 'custom', 'Custom'), //DEPRECATED
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


  /********************************************************************
   *  Constructor
   *
   *  Params:
   *    None
   *  Return:
   *    None
   */
  public function __construct() {
    $this->load_status();
  }


  /********************************************************************
   *  Get Value
   *    Checks private variable exists, then returns it
   *
   *  Params:
   *    Name (str): variable name to get
   *  Return:
   *    Specified private variable
   */
  public function __get($name) {
    if (property_exists($this, $name)) {
      return $this->{$name};
    }
    else {
      trigger_error("Undefined variable {$name}", E_USER_WARNING);
    }
  }


  /********************************************************************
   *  Set Value
   *    1. Check value exists in $setfilters array
   *    2. Carry out filter_var using the filter set in $setfilters
   *    3. If filter was FILTER_VALIDATE_INT then carry out range checks
   *    4. Assign value
   *
   *  Params:
   *    name (str)
   *    value (mixed)
   *  Return:
   *    Value on success
   *    False on failure
   */
  public function __set($name, $value) {
    //Does specified name exist in $setfilters array?
    if (! array_key_exists($name, $this->setfilters)) {
      trigger_error("Undefined variable {$name}", E_USER_WARNING);
      return false;
    }

    //Filter the user input based on setfilters value
    $newvalue = filter_var($value, $this->setfilters[$name]);

    //User input failed, exit this function
    if ($newvalue === false) {
      return false;
    }

    //Carry out range checks for integer values
    if ($this->setfilters[$name] == FILTER_VALIDATE_INT) {
      if ($name == 'dns_logretention') {
        $this->dns_logretention = filter_integer($value, 0, 365, 60);
      }
      elseif ($name == 'dns_listenport') {
        $this->dns_listenport = filter_integer($value, 1, 65536, 53);
      }
    }

    $this->{$name} = $newvalue;                            //User input valid

    //echo "setting {$name} {$value}";
    return $value;
  }


  /********************************************************************
   *  DHCP Add Host
   *    TODO Complete input validation
   *
   */
  public function dhcp_addhost($ip, $mac, $sysname, $sysicon)  {
    if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
      trigger_error("Invalid IP Address {$ip}", E_USER_WARNING);
      return false;
    }

    $this->dhcp_hosts[$ip] = array('mac' => $mac, 'sysname' => $sysname, 'sysicon' => $sysicon);
  }


  /********************************************************************
   *  DHCP Clear Hosts
   *    Prevent duplication of values when saving from dhcp.php
   *
   */
  public function dhcp_clearhosts() {
    $this->dhcp_hosts = array();
  }


  /********************************************************************
   *  Getter for DHCP Hosts Array
   *
   */
  public function dhcp_gethosts() {
    return $this->dhcp_hosts;
  }


  /********************************************************************
   *  Save DHCP and DNS config to server.php
   *
   */
  public function save_serversettings() {
    $filelines = array();

    $filelines[] = '<?php'.PHP_EOL;

    //Write basic DHCP variables first, convert boolean values to integer
    $filelines[] = "\$config->dhcp_authoritative = ".(int)$this->dhcp_authoritative.";".PHP_EOL;
    $filelines[] = "\$config->dhcp_enabled = ".(int)$this->dhcp_enabled.";".PHP_EOL;
    $filelines[] = "\$config->dhcp_leasetime = '{$this->dhcp_leasetime}';".PHP_EOL;
    $filelines[] = "\$config->dhcp_gateway = '{$this->dhcp_gateway}';".PHP_EOL;
    $filelines[] = "\$config->dhcp_rangestart = '{$this->dhcp_rangestart}';".PHP_EOL;
    $filelines[] = "\$config->dhcp_rangeend = '{$this->dhcp_rangeend}';".PHP_EOL;

    $filelines[] = "\$config->dns_blockip = '{$this->dns_blockip}';".PHP_EOL;
    $filelines[] = "\$config->dns_interface = '{$this->dns_interface}';".PHP_EOL;
    $filelines[] = "\$config->dns_listenip = '{$this->dns_listenip}';".PHP_EOL;
    $filelines[] = "\$config->dns_listenport = '{$this->dns_listenport}';".PHP_EOL;
    $filelines[] = "\$config->dns_logretention = '{$this->dns_logretention}';".PHP_EOL;
    $filelines[] = "\$config->dns_name = '{$this->dns_name}';".PHP_EOL;
    $filelines[] = "\$config->dns_server = '{$this->dns_server}';".PHP_EOL;
    $filelines[] = "\$config->dns_serverip1 = '{$this->dns_serverip1}';".PHP_EOL;
    $filelines[] = "\$config->dns_serverip2 = '{$this->dns_serverip2}';".PHP_EOL;


    //Then write all the DHCP hosts
    foreach($this->dhcp_hosts as $key => $value) {        //Go through all hosts
      $filelines[] = "\$config->dhcp_addhost('{$key}', '{$value['mac']}', '{$value['sysname']}', '{$value['sysicon']}');".PHP_EOL;
    }

    //Final line closing PHP tag
    $filelines[] = '?>'.PHP_EOL;

    if (file_put_contents(SETTINGS_SERVER, $filelines) === false) {
      die('Unable to save settings to '.SETTINGS_SERVER);
    }
  }


  /********************************************************************
   *  Save Config
   *
   *  Params:
   *    None
   *  Return:
   *    None
   */
  public function save() {
    $filelines = array();

    $filelines[] = '<?php'.PHP_EOL;

    $filelines[] = "\$config->api_key = '{$this->api_key}';".PHP_EOL;
    $filelines[] = "\$config->api_readonly = '{$this->api_readonly}';".PHP_EOL;
    $filelines[] = "\$config->password = '{$this->password}';".PHP_EOL;
    $filelines[] = "\$config->username = '{$this->username}';".PHP_EOL;
    $filelines[] = "\$config->search_engine = '{$this->search_engine}';".PHP_EOL;
    $filelines[] = "\$config->search_url = '{$this->search_url}';".PHP_EOL;
    $filelines[] = "\$config->whois_api = '{$this->whois_api}';".PHP_EOL;
    $filelines[] = "\$config->whois_provider = '{$this->whois_provider}';".PHP_EOL;
    $filelines[] = "\$config->whois_url = '{$this->whois_url}';".PHP_EOL;

    //Final line closing PHP tag
    $filelines[] = '?>'.PHP_EOL;

    if (file_put_contents(SETTINGS_FRONT, $filelines) === false) {
      die('Unable to save settings to '.SETTINGS_FRONT);
    }
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
   *  Load Status File
   */
  private function load_status() {
    if (file_exists(SETTINGS_STATUS)) {
      include SETTINGS_STATUS;
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


  /********************************************************************
   *  Is Password Protection Enabled
   *
   *  Params:
   *    None
   *  Return:
   *    True - Password Enabled
   *    False - Password Disabled
   */
  public function is_password_protection_enabled() {
    if ($this->password != '') return true;
    return false;
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
  public function is_blocklist_active($blname) {
    return $this->blocklists[$blname][0];
  }

  /********************************************************************
   *  Get Blocklist Custom
   *    Replace comma seperated values of bl_custom with new lines
   *
   */
  public function get_blocklist_custom() {
    if ($this->bl_custom == '') {
      return '';
    }
    else {
      return str_replace(',', PHP_EOL, $this->bl_custom);
    }
  }

  /********************************************************************
   *  Load Blocklists from Settings
   *    Include bl.php from settings folder if exists
   *
   */
  public function load_blocklists() {
    if (file_exists(SETTINGS_BL)) {
      include SETTINGS_BL;
    }
  }

  /********************************************************************
   *  Save Blocklists to Settings
   *    Write set_blocklist_status and set_blocklist_custom instructions to bl.php
   *
   */
  public function save_blocklists() {
    $filelines = array();

    $filelines[] = '<?php'.PHP_EOL;

    foreach($this->blocklists as $key => $value) {         //Go through all blocklists
      if ($value[0]) {                                     //Enabled?
        $filelines[] = "\$this->set_blocklist_status('{$key}', true);".PHP_EOL;
      }
      else {                                               //Or Disabled
        $filelines[] = "\$this->set_blocklist_status('{$key}', false);".PHP_EOL;
      }
    }
    $filelines[] = "\$this->set_blocklist_custom('{$this->bl_custom}');".PHP_EOL;
    $filelines[] = '?>'.PHP_EOL;

    if (file_put_contents(SETTINGS_BL, $filelines) === false) {
      die('Unable to save blocklist settings to '.SETTINGS_BL);
    }
  }


  /********************************************************************
   *  Set Blocklist Custom
   *
   *  Params:
   *    $custom (str): new line for blocklist custom
   *  Return:
   *    None
   */
  public function set_blocklist_custom($custom) {
    $this->bl_custom = strip_tags($custom);
  }


  /********************************************************************
   *  Set Blocklist Status
   *
   *  Params:
   *    $blname (str): Blocklist name
   *    $status (bool): Blocklist status
   *  Return:
   *    True when blname exists
   *    False on failure
   */
  public function set_blocklist_status($blname, $status) {
    if (array_key_exists($blname, $this->blocklists)) {
      $this->blocklists[$blname][0] = $status;
      return true;
    }
    return false;
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

    file_put_contents(SETTINGS_STATUS, $filelines);
  }
}

/********************************************************************
 *  Load DNS and DHCP Values from server.php
 *    1. Check server.php exists in settings folder
 *    2. Execute server.php
 *
 */
function load_serversettings() {
  global $config;

  if (file_exists(SETTINGS_SERVER)) {
    include SETTINGS_SERVER;
  }
}


/********************************************************************
 *  Load Front End Config Settings from front.php
 *    1. Check front.php exists in settings folder
 *    2. Execute front.php
 *
 */
function load_frontsettings() {
  global $config;

  if (file_exists(SETTINGS_FRONT)) {
    include SETTINGS_FRONT;
  }
}




$config = new Config;
load_frontsettings();


