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
  
    if (!$result = $this->db->query('SELECT COUNT(*) FROM '.$expr)){
      //$this->display_error('count_table_rows');
      return 0;
    }

    //Extract count value from array
    $rows = $result->fetch_row()[0];
    $result->free();

    return $rows;
  }


  /******************************************************************
   *  Display Error
   *    Sad Emoji with db error message
   *
   *  Params:
   *    None
   *  Return:
   *    None
   */
  private function display_error($parentfunction) {
    echo '<h4><img src=./svg/emoji_sad.svg>Error running query</h4>'.PHP_EOL;
    echo $parentfunction.': '.$this->db->error;
    echo '</div>'.PHP_EOL;
    //die();
  }


  /******************************************************************
   *  Count Alerts
   *    Return number of alerts unresolved in analytics table
   *  Params:
   *    None
   *  Return:
   *    Number of rows
   */
  public function analytics_count() {
    return $this->count_table_rows("analytics WHERE ack = 'FALSE'");
  }


  /******************************************************************
   *  Analytics Get Data
   *    Return Array of results from analytics table
   *    Behaviour of status search is to search for ACK = FALSE when status = 0
   *
   *  Params:
   *    status - Whether to look for open or resolved (ACK false or true)
   *  Return:
   *    False when nothing found
   *    Associative Array of results
   */
  public function analytics_get_data($severity, $status) {
    $values = array();                                     //Array of values to be returned

    $query = '';
    $search_status = false;

    //Work out if any extra searches are required for severity or status
    if ($status < 3) {                                     //STATUS_OPEN + STATUS_RESOLVED
      $search_status = true;
    }

    $query = 'SELECT * FROM analytics ';

    //Any searching to do?
    if (($search_status) || ($search_severity)) {
      $query .= 'WHERE ';
    }

    //Status uses Bitwise operators. OPEN + RESOLVED doesn't require a search input
    switch($status) {
      case 0:
      case STATUS_OPEN:
        $query .= "ack = '0' ";
        break;
      case STATUS_RESOLVED:
        $query .= "ack = '1' ";
        break;
    }

    //AND will need adding if status and a severity search is being done
    if (($search_status) && ($severity > 0) && ($severity < 7)) {
      $query .= 'AND ';
    }

    switch($severity) {
      case SEVERITY_LOW:
        $query .= "severity = '1' ";
        break;
      case SEVERITY_MED:
        $query .= "severity = '2' ";
        break;
      case SEVERITY_HIGH:
        $query .= "severity = '3' ";
        break;
      case SEVERITY_LOW + SEVERITY_MED:
        $query .= "severity IN ('1','2') ";
        break;
      case SEVERITY_LOW + SEVERITY_HIGH:
        $query .= "severity IN ('1','3') ";
        break;
      case SEVERITY_MED + SEVERITY_HIGH:
        $query .= "severity IN ('2','3') ";
        break;
    }

    $query .= 'ORDER BY log_time DESC';

    if (!$result = $this->db->query($query)) {             //Run the query
      $this->display_error('analytics_get_data');
      return false;
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


  /********************************************************************
   *  Update Value
   *    Update value in analytics table based on action
   *    Prevent malicious changes by checking time and id matches
   *    1. Search for value based on id and log_time
   *    2. Zero results means malicious change, so drop out silently
   *    3. Carry out update action
   *    4. Decrease alert_count value in memcache
   *
   *  Params:
   *    id, logdate, logtime, action
   *  Return:
   *    False on Failure or if inputs don't match an entry in analytics table
   *    True on Success
   */
  function analytics_update_value($id, $logdate, $logtime, $action) {
    global $mem;

    $cmd = '';

    //There should only be one matching record
    if ($this->count_table_rows("analytics WHERE id = '{$id}' AND log_time = '{$logdate} {$logtime}'") != 1) {
      return false;
    }

    if ($action == 'resolve') {
      $cmd = "UPDATE analytics SET ack = TRUE WHERE id = '{$id}'";
    }
    elseif ($action == 'delete') {
      $cmd = "DELETE FROM analytics WHERE id = '{$id}'";
    }

    if ($this->db->query($cmd) === false) {
      echo 'Error updating record '.$this->db->error;
      return false;
    }

    //Decrease alert_count (count value from this table) in memcache
    $mem->decrement('alert_count', 1);
    return true;
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
      $query .= "WHERE site LIKE '%$validsearchstr%' OR comment LIKE '%$validsearchstr%' ";
    }

    $query .= "ORDER BY bl_source, site";

    if (!$result = $this->db->query($query)) {
      echo '<h4><img src=../svg/emoji_sad.svg>Error running query</h4>'.PHP_EOL;
      echo 'blocklist_domains: '.$this->db->error;
      echo '</div>'.PHP_EOL;
      die;
    }

    return $result;
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
    $allowed = $this->count_table_rows("dnslog WHERE log_time > CURDATE() AND severity = '1'");
    $blocked = $this->count_table_rows("dnslog WHERE log_time > CURDATE() AND severity = '2'");
    $total = strval($allowed + $blocked);
    return array('queries' => $total, 'allowed' => $allowed, 'blocked' => $blocked);
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
   *    Array of single severities with round_time
   */
  public function queries_count_hourly($currenttime) {
    $values = array();                                     //Array of values to be returned
    $starttime = 0;
    $endtime = 0;

    $starttime = date('Y-m-d H:00:00', $currenttime - 84600); //Start Minus 24 Hours from Current Time
    $endtime = date('Y-m-d H:59:59');                         //End at this hour

    //Only taking rounded time (to 30 min block) and each severity
    $query = "SELECT SEC_TO_TIME((TIME_TO_SEC(log_time) DIV 1800) * 1800) AS round_time, severity FROM dnslog WHERE log_time >= '{$starttime}' AND log_time <= '{$endtime}'";

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
