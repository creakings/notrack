<?php
/*Wrapper class for mysqli for running very specific queries. Used on API page.
 *The following public functions are available:
 *  count_blocklists
 *  count_queries_today
 *  count_total_queries_today
 */

class MySqliDb {
  private $db;

  /******************************************************************
   *  Class Constructer
   *    Open connection to mysql
   *  Params:
   *    None
   *  Return:
   *    None
   */
  public function __construct() {
    $this->db = new mysqli(SERVERNAME, USERNAME, PASSWORD, DBNAME);  //Open MariaDB connection
    if ($this->db->connect_errno) {
      echo 'Error: Unable to connect to Database.<br>';
      echo 'Debugging errno: ' .$this->db->connect_errno.'<br>';
      echo 'Debugging error: ' .$this->db->connect_error.'<br>';
      unset($this->db);
    }
  }


  /******************************************************************
   *  Class Destructer
   *    Close connection to mysql
   *  Params:
   *    None
   *  Return:
   *    None
   */
  public function __destruct() {
    $this->db->close();
    unset($this->db);
  }

  
  /******************************************************************
   *  Count Table Rows
   *    Count number of rows in table
   *  Params:
   *    Table name and any additional query
   *  Return:
   *    Number of rows
   */
  private function count_table_rows($expr) {

    $rows = 0;
  
    if(!$result = $this->db->query('SELECT COUNT(*) FROM '.$expr)){
      die('count_table_rows: error running the query '.$this->db->error);
    }

    //Extract count value from array
    $rows = $result->fetch_row()[0];
    $result->free();

    return $rows;
  }

  
  /******************************************************************
   *  Blocklist Active
   *    Get list of distinct items in bl_source column of blocklist table
   *     (The active blocklists)
   *  Params:
   *    None
   *  Return:
   *    Numeric array of items
   *    False if nothing found
   */
  public function blocklist_active() {
    $data = array();
    $query = "SELECT DISTINCT bl_source FROM blocklist";

    if (!$result = $this->db->query($query)) {
      echo '<h4><img src=../svg/emoji_sad.svg>Error running query</h4>'.PHP_EOL;
      echo 'blocklist_active: '.$this->db->error;
      echo '</div>'.PHP_EOL;
      die;
    }
    
    if ($result->num_rows > 0) {
      $data = $result->fetch_all(MYSQLI_NUM);
      $result->free();
    }
    else {
      $result->free();
      return false;
    }

    return $data;
  }

  /******************************************************************
   *  Blocklist Domains
   *    Get list of domains from blocklist
   *    1. Make sure supplied parameters are valid
   *    2. Carry out SQL Query
   *  Params:
   *    blocklist - blocklist name
   *    searchstr - Search value for domain or comment
   *  Return:
   *    mysqli result class
   */
  public function blocklist_domains($blocklist, $searchstr) {
    $validblocklist = '';
    $validsearchstr = '';

    $query = "SELECT * FROM blocklist ";

    //Only validate strings shorter than x to prevent resource exhaustion
    $validblocklist = (strlen($blocklist) < 50 ? $blocklist : 'all');
    $validsearchstr = (strlen($searchstr) < 255 ? $searchstr : '');

    //Remove invalid characters
    $validblocklist = preg_replace('/[^a-z_]/', '', $validblocklist);
    $validsearchstr = preg_replace('/[^\w\.\-_]/', '', $validsearchstr);

    //if (! preg_match('/^(all|whitelist|bl_[a-z]{4, 40})$/', $validblocklist) {

    //Build up query based on supplied parameters
    if (($validblocklist != 'all') && ($validsearchstr != '')) {
      $query .= "WHERE site LIKE '%$validsearchstr%' AND bl_source = '$validblocklist' ";
    }
    elseif ($validblocklist != 'all') {
      $query .= "WHERE bl_source = '$validblocklist' ";
    }
    elseif ($validsearchstr != '') {
      $query .= "WHERE site LIKE '%$validsearchstr%' OR comment LIKE '%$validsearchstr%'";
    }

    $query .= "ORDER BY id";

    if (!$result = $this->db->query($query)) {
      echo '<h4><img src=../svg/emoji_sad.svg>Error running query</h4>'.PHP_EOL;
      echo 'blocklist_domains: '.$this->db->error;
      echo '</div>'.PHP_EOL;
      die;
    }

    return $result;
  }
  

  /******************************************************************
   *  Count Alerts
   *    Return number of alerts unresolved in analytics table
   *  Params:
   *    None
   *  Return:
   *    Number of rows
   */
  public function count_alerts() {
    return $this->count_table_rows("analytics WHERE ack = 'FALSE'");
  }


  /******************************************************************
   *  Count number of rows in Blocklist table
   *    Return the value from count_table_rows for Blocklist table
   *  Params:
   *    None
   *  Return:
   *    Number of rows
   */
  public function count_blocklists() {
    return $this->count_table_rows('blocklist');
  }


  /******************************************************************
   *  Count number of rows in a Specific Blocklist
   *    Return the value from count_table_rows for Blocklist table
   *  Params:
   *    blocklist to search for
   *  Return:
   *    Number of rows
   */
  public function count_specific_blocklist($blocklist) {
    return $this->count_table_rows("blocklist WHERE bl_source = '$blocklist'");
  }


  /******************************************************************
   *  Count queries for today
   *    Count number of Allowed, Blocked and Local queries for today
   *  Params:
   *    None
   *  Return:
   *    Array of values as queries, allowed, blocked, local
   */
  public function count_queries_today() {
    $allowed = $this->count_table_rows("dnslog WHERE log_time > CURDATE() AND dns_result = 'A'");
    $blocked = $this->count_table_rows("dnslog WHERE log_time > CURDATE() AND dns_result = 'B'");
    $local = $this->count_table_rows("dnslog WHERE log_time > CURDATE() AND dns_result = 'L'");
    $total = strval($allowed + $blocked + $local);
    return array('queries' => $total, 'allowed' => $allowed, 'blocked' => $blocked, 'local' => $local);
  }


  /******************************************************************
   *  Count total queries
   *    Count total number of queries in queries table
   *  Params:
   *    None
   *  Return:
   *    Number of queries
   */
  public function count_total_queries() {
    return $this->count_table_rows("dnslog");
  }


  /******************************************************************
   *  Count total queries for today
   *    Count total number of queries for today
   *  Params:
   *    None
   *  Return:
   *    Number of queries
   */
  public function count_total_queries_today() {
    return $this->count_table_rows("dnslog WHERE log_time > CURDATE()");
  }


  /******************************************************************
   *  Get Status
   *    Return Config Status
   *  Params:
   *    None
   *  Return:
   *    Config Status
   */
  public function get_status() {
    global $config;
    return $config->status;
  }


  /******************************************************************
   *  Recent Queries
   *    Return recent DNS queries in an array
   *    Increase interval time by 4 minutes as the cron job to collect DNS data
   *     only runs every 4 minutes by default
   *  Params:
   *    Interval to look back in minutes
   *  Return:
   *    Config Status
   */
  public function recent_queries($interval) {
    $queries = array();

    $starttime = $interval + 4;
    $cmd = "SELECT * FROM dnslog WHERE log_time >= DATE_SUB(NOW(), INTERVAL $starttime MINUTE) AND log_time <= DATE_SUB(NOW(), INTERVAL 4 MINUTE) ORDER BY UNIX_TIMESTAMP(log_time) ASC";

    

    if(!$result = $this->db->query($cmd)) {
      http_response_code(400);                             //Bad Request
      return array('error_code' => 'invalid_input', 'error_message' => $this->db->error);
    }

    if ($result->num_rows == 0) {
      //Valid query, but no data found
      //http_response_code(204);                             //200 = No Content
      $queries = array('error_code' => 'no_data_found', 'error_message' => 'No recent queries found');
    }
    else {
      //Valid query with data found
      $queries = $result->fetch_all();
    }

    $result->free();

    return $queries;
  }


  /******************************************************************
   *  Queries Count Hourly
   *    Return Array of DNS Query count per rounded 30 min period for last 24 hours
   *
   *  Params:
   *    Current Time to start from
   *  Return:
   *    False when nothing found
   *    Array of single dns_results with round_time
   */
  public function queries_count_hourly($currenttime) {
    $values = array();                                     //Array of values to be returned
    $starttime = 0;
    $endtime = 0;

    $starttime = date('Y-m-d H:00:00', $currenttime - 84600); //Start Minus 24 Hours from Current Time
    $endtime = date('Y-m-d H:59:59');                         //End at this hour

    //Only taking rounded time (to 30 min block) and each dns_result
    $query = "SELECT SEC_TO_TIME((TIME_TO_SEC(log_time) DIV 1800) * 1800) AS round_time, dns_result FROM dnslog WHERE log_time >= '{$starttime}' AND log_time <= '{$endtime}'";

    if (!$result = $this->db->query($query)){
      echo '<h4><img src=./svg/emoji_sad.svg>Error running query</h4>'.PHP_EOL;
      echo 'count_queries: '.$this->db->error;
      echo '</div>'.PHP_EOL;
      die();
    }

    //Leave if nothing found and return false
    if ($result->num_rows == 0) {
      $result->free();
      return false;
    }

    $values = $result->fetch_all(MYSQLI_ASSOC);            //Get associative array of values from MariaDB result
    $result->free();

    return $values;
  }
  /******************************************************************
   *  Queries Historical Days
   *    Return recent DNS queries in an array
   *    Increase interval time by 4 minutes as the cron job to collect DNS data
   *     only runs every 4 minutes by default
   *  Params:
   *    Interval to look back in minutes
   *  Return:
   *    Config Status
   */
  public function queries_historical_days() {
    $rows = 0;

    if(!$result = $this->db->query('SELECT COUNT(DISTINCT(DATE(log_time))) FROM dnslog')){
      die('queries_historical_days: error running the query '.$this->db->error);
    }

    //Extract count value from array
    $rows = $result->fetch_row()[0];
    $result->free();

    return $rows;
  }
}
