<?php
/*Class for Whois API interaction
 *  Supply domain to search and API Token on class creation
 *  JSON Whois has a limit to the number of api queries a free user can make.
 *  In order to reduce the number of api queries we utilise whois table to save each result
 *  The data can get stale, so there is the option to delete a record to force an api query
 *
 */
class WhoisApi {
  private $domain = '';                                    //Supplied Domain
  private $token = '';                                     //Supplied API Token
  public $download_date = '';                              //Date when record was downloaded
  public $jsondata = array();                              //JSON Formatted record

  /******************************************************************
   *  Class Constructer
   *    Set initial variables
   *  Params:
   *    API Token, Domain
   *  Return:
   *    None
   */
  public function __construct($tokenkey, $domainsearch) {
    $this->token = $tokenkey;
    $this->domain = $domainsearch;
  }


  /********************************************************************
   *  Get Who Is Data
   *    Downloads whois data from jsonwhois.com
   *    Checks cURL return value
   *    If there is a problem exit function before record is saved
   *    Save data to whois table
   *
   *  Params:
   *    None
   *  Return:
   *    True on success
   *    False on lookup failed or other HTTP error
   */
  public function get_whoisdata() {
    global $db;

    $headers = array();                                    //cURL Headers
    $rawdata = '';                                         //cURL Return String
    $status = 0;                                           //cURL Return Status

    $headers[] = 'Accept: application/json';
    $headers[] = 'Content-Type: application/json';
    $headers[] = 'Authorization: Token token='.$this->token;
    $url = 'https://jsonwhois.com/api/v1/whois/?domain='.$this->domain;
    $this->download_date = date('Y-m-d H:i:s');

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $rawdata = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);       //Get HTTP Error Value
    curl_close($ch);

    if ($status == 400) {                                  //400 = Domain doesn't exist
      echo '<div class="sys-group">'.PHP_EOL;
      echo '<h5>Domain Information</h5>'.PHP_EOL;
      echo '<div class="sys-items">'.PHP_EOL;
      echo $this->domain.' does not exist'.PHP_EOL;
      echo '</div>'.PHP_EOL;
      return false;
    }

    if ($status >= 300) {                                  //Other HTTP Error
      //echo '<div class="sys-group">'.PHP_EOL;
      echo '<h4><img src=./svg/emoji_sad.svg>Error running Whois lookup</h4>'.PHP_EOL;
      echo 'Server returned status: '.$status.', response '.$rawdata;
      echo '</div>'.PHP_EOL;
      return false;
    }

    //Save whois record into whois table
    $cmd = "INSERT INTO whois (id, save_time, site, record) VALUES ('NULL', '{$this->download_date}', '{$this->domain}', '".$db->real_escape_string($rawdata)."')";
    if ($db->query($cmd) === false) {                      //Any errors running the query?
      echo 'get_whoisdata() Error adding data to whois table: '.$db->error;
    }

    $this->jsondata = json_decode($rawdata, true);

    return true;
  }


  /********************************************************************
   *  Delete Record from Whois Table
   *    Find record from whois table using the same process as search_whoisrecord
   *    Take the id number from the found record
   *    Run query to delete record
   *
   *  Params:
   *    None
   *  Return:
   *    True record delete
   *    False record not found or not deleted
   */
  public function delete_whoisrecord() {
    global $db;

    $query = "SELECT * FROM whois WHERE site = '{$this->domain}'";

    if (!$result = $db->query($query)){
      die('delete_whoisrecord() There was an error running the query: '.$db->error);
    }

    if ($result->num_rows == 0) {                          //Leave if nothing found
      $result->free();
      return false;
    }

    $row = $result->fetch_assoc();                         //Read one row of results
    $result->free();

    //Now to delete the record
    $query = "DELETE FROM whois WHERE id = '{$row['id']}'";
    if ($db->query($query) === false) {
      echo '<div class="sys-group">'.PHP_EOL;
      echo '<h4><img src=./svg/emoji_sad.svg>Unable to delete old record</h4>'.PHP_EOL;
      echo $db->error;
      echo '</div>'.PHP_EOL;
      return false;
    }

    return true;
  }

  /********************************************************************
   *  Search Whois Table
   *    Attempts to find domain from whois table in order to prevent overuse of API
   *
   *  Params:
   *    None
   *  Return:
   *    True on record found
   *    False if no record found
   */
  public function search_whoisrecord() {
    global $db;

    $query = "SELECT * FROM whois WHERE site = '{$this->domain}'";

    if (!$result = $db->query($query)){
      die('search_whoisrecord() There was an error running the query: '.$db->error);
    }

    if ($result->num_rows == 0) {                          //Leave if nothing found
      $result->free();
      return false;
    }

    $row = $result->fetch_assoc();                         //Read one row of results

    $this->download_date = $row['save_time'];
    $this->jsondata = json_decode($row['record'], true);

    $result->free();

    return true;
  }
}