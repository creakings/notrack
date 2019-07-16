<?php
/*A different view is displayed depending how user enters this page
* 1. No arguments set - Show searchbox
* 2. Domain set - display whois, and graph
* 3. Domain and time set - display surrounding queries, whois, and graph
*/
require('./include/global-vars.php');
require('./include/global-functions.php');
require('./include/menu.php');

load_config();
ensure_active_session();

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <link href="./css/master.css" rel="stylesheet" type="text/css">
  <link href="./css/chart.css" rel="stylesheet" type="text/css">
  <link rel="icon" type="image/png" href="./favicon.png">
  <script src="./include/menu.js"></script>
  <meta name="viewport" content="width=device-width, initial-scale=0.9">
  <title>NoTrack - Investigate</title>
</head>

<body>
<?php
draw_topmenu('Investigate');
draw_sidemenu();
echo '<div id="main">'.PHP_EOL;

/************************************************
*Constants                                      *
************************************************/


/************************************************
*Global Variables                               *
************************************************/
$datetime = '';
$domain = '';
$site = '';
$showraw = false;

$whois_date = '';
$whois_record = '';

/************************************************
*Arrays                                         *
************************************************/

/********************************************************************
 *  Create Who Is Table
 *    Run sql query to create whois table
 *  Params:
 *    None
 *  Return:
 *    None
 */
function create_whoistable() {
  global $db;

  $query = "CREATE TABLE whois (id BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT, save_time DATETIME, site TINYTEXT, record MEDIUMTEXT)";

  $db->query($query);
}


/********************************************************************
 *  Draw Search Bar
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function draw_searchbar() {
  global $site;

  echo '<div id="menu-lower">'.PHP_EOL;
  echo '<form method="GET">'.PHP_EOL;
  echo '<input type="text" name="site" class="input-conf" placeholder="Search domain" value="'.$site.'">'.PHP_EOL;
  echo '<button type="submit">Investigate</button>'.PHP_EOL;
  echo '</form>'.PHP_EOL;
  echo '</div>'.PHP_EOL;
}


/********************************************************************
 *  Draw Search Box
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function draw_searchbox() {
  global $site;

  echo '<div id="search-box"><div>'.PHP_EOL;
  echo '<form method="GET">'.PHP_EOL;
  echo '<input type="text" name="site" placeholder="Search domain" value="'.$site.'">&nbsp;'.PHP_EOL;
  echo '<input type="submit" value="Investigate">'.PHP_EOL;
  echo '</form>'.PHP_EOL;
  echo '</div></div>'.PHP_EOL;
}


/********************************************************************
 *  Get Block List Name
 *    Returns the name of block list if it exists in the names array
 *  Params:
 *    $bl - bl_name
 *  Return:
 *    Full block list name
 */

function get_blocklistname($bl) {
  global $BLOCKLISTNAMES;

  if (array_key_exists($bl, $BLOCKLISTNAMES)) {
    return $BLOCKLISTNAMES[$bl];
  }

  return $bl;
}
/********************************************************************
 *  Search Block Reason
 *    Searches for $site in bl_source to get blocklist name
 *    1. Try for $site
 *    2. Try for .tld
 *    3. Try for site.co.uk
 *    4. Try for subdomain.site.co.uk
 *    5. Don't go any further through subdomains as there aren't many / any in the blocklists
 *
 *  Params:
 *    $site - Site to search
 *  Return:
 *    blocklist name
 */
function search_blockreason($site) {
  global $db;

  $result = $db->query("SELECT bl_source site FROM blocklist WHERE site = '.$site.'");
  if ($result->num_rows > 0) return $result->fetch_row()[0];

  //Use regex to get domain and tld (site.co.uk)
  if (preg_match('/([\w\-_]+)(\.co|\.com|\.org|\.gov)?\.([\w\-]+)$/', $site,  $matches) > 0) {
    $result = $db->query("SELECT bl_source site FROM blocklist WHERE site = '.$matches[3]'");

    if ($result->num_rows > 0) return $result->fetch_row()[0];

    $result = $db->query("SELECT bl_source site FROM blocklist WHERE site = '$matches[0]'");
    if ($result->num_rows > 0) return $result->fetch_row()[0];
  }

  //Try for subdomain.site.co.uk
  if (preg_match('/([\w\-_]+)\.([\w\-_]+)(\.co|\.com|\.org|\.gov)?\.([\w\-]+)$/', $site,  $matches) > 0) {
    $result = $db->query("SELECT bl_source site FROM blocklist WHERE site = '$matches[0]'");
    if ($result->num_rows > 0) return $result->fetch_row()[0];
  }

  return '';                                               //Don't know at this point
}


/********************************************************************
 *  Show Time View
 *    Show results in Time order
 *
 *  Params:
 *    None
 *  Return:
 *    false when nothing found, true on success
 */
function show_time_view() {
  global $db, $datetime, $site, $sys, $Config;

  $rows = 0;
  $row_class = '';
  $query = '';
  $action = '';
  $blockreason = '';

  $query = "SELECT *, DATE_FORMAT(log_time, '%H:%i:%s') AS formatted_time FROM dnslog WHERE sys = '$sys' AND log_time > SUBTIME('$datetime', '00:00:05') AND log_time < ADDTIME('$datetime', '00:00:03') ORDER BY UNIX_TIMESTAMP(log_time)";

  echo '<div class="sys-group">'.PHP_EOL;

  if(!$result = $db->query($query)){
    echo '<h4><img src=./svg/emoji_sad.svg>Error running query</h4>'.PHP_EOL;
    echo 'show_time_view: '.$db->error;
    echo '</div>'.PHP_EOL;
    return false;
  }

  if ($result->num_rows == 0) {                  //Leave if nothing found
    echo '<h4><img src=./svg/emoji_sad.svg>No results found for selected time</h4>'.PHP_EOL;
    echo '</div>'.PHP_EOL;
    $result->free();
    return false;
  }

  echo '<table id="query-time-table">'.PHP_EOL;
  echo '<tr><th>Time</th><th>System</th><th>Site</th></tr>'.PHP_EOL;

  while($row = $result->fetch_assoc()) {         //Read each row of results
    if ($row['dns_result'] == 'A') {             //Allowed
      $row_class='';
    }
    elseif ($row['dns_result'] == 'B') {         //Blocked
      $row_class = ' class="blocked"';
      $blockreason = search_blockreason($row['dns_request']);
      if ($blockreason == 'bl_notrack') {        //Show Report icon on NoTrack list
        $blockreason = '<p class="small">Blocked by NoTrack list</p>';
      }
      elseif ($blockreason == 'custom') {        //Users blacklist, show report icon
        $blockreason = '<p class="small">Blocked by Black list</p>';
      }
      elseif ($blockreason == '') {              //No reason is probably IP or Search request
        $row_class = ' class="invalid"';
        $blockreason = '<p class="small">Invalid request</p>';
      }
      else {
        $blockreason = '<p class="small">Blocked by '.get_blocklistname($blockreason).'</p>';
      }
    }
    elseif ($row['dns_result'] == 'L') {         //Local
      $row_class = ' class="local"';
    }

    if ($site == $row['dns_request']) {
      $row_class = ' class="cyan"';
    }

    echo '<tr'.$row_class.'><td>'.$row['formatted_time'].'</td><td>'.$row['sys'].'</td><td>'.$row['dns_request'].$blockreason.'</td></tr>'.PHP_EOL;
    $blockreason = '';
  }

  echo '</table>'.PHP_EOL;
  echo '<br>'.PHP_EOL;
  echo '</div>'.PHP_EOL;

  $result->free();
  return true;
}


/********************************************************************
 *  Get Who Is Data
 *    Downloads whois data from jsonwhois.com
 *    Checks cURL return value
 *    Save data to whois table
 *
 *  Params:
 *    URL to Query, Users API Key to jsonwhois
 *  Return:
 *    True on success
 *    False on lookup failed or other HTTP error
 */
function get_whoisdata($site, $apikey) {
  global $db, $whois_date, $whois_record;

  $headers[] = 'Accept: application/json';
  $headers[] = 'Content-Type: application/json';
  $headers[] = 'Authorization: Token token='.$apikey;
  $url = 'https://jsonwhois.com/api/v1/whois/?domain='.$site;
  $whois_date = date('Y-m-d H:i:s');

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $json_response = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

  if ($status == 400) {                                    //Bad request domain doesn't exist
    echo '<div class="sys-group">'.PHP_EOL;
    echo '<h5>Domain Information</h5>'.PHP_EOL;
    echo '<div class="sys-items">'.PHP_EOL;
    echo $site.' does not exist'.PHP_EOL;
    echo '</div>'.PHP_EOL;
    curl_close($ch);
    return false;
  }

  if ($status >= 300) {                                    //Other HTTP Error
    echo '<div class="sys-group">'.PHP_EOL;
    echo '<h4><img src=./svg/emoji_sad.svg>Error running query</h4>'.PHP_EOL;
    echo "Call to URL $url failed with status $status, response $json_response";
    echo '</div>'.PHP_EOL;
    curl_close($ch);
    return false;
  }

  curl_close($ch);


  //Save whois record into whois table
  $cmd = "INSERT INTO whois (id, save_time, site, record) VALUES ('NULL', '$whois_date', '$site', '".$db->real_escape_string($json_response)."')";
  if ($db->query($cmd) === false) {
    echo 'get_whoisdata() Error adding data to whois table: '.$db->error;
  }

  $whois_record = json_decode($json_response, true);

  return true;
}


/********************************************************************
 *  Search Who Is Record
 *    Attempts to find $site from whois table in order to prevent overuse of JsonWhois API
 *
 *  Params:
 *    URL to search
 *  Return:
 *    True on record found
 *    False if no record found
 */
function search_whois($site) {
  global $db, $whois_date, $whois_record;

  $query = "SELECT * FROM whois WHERE site = '$site'";

  if(!$result = $db->query($query)){
    die('search_whois() There was an error running the query: '.$db->error);
  }

  if ($result->num_rows == 0) {                            //Leave if nothing found
    $result->free();
    return false;
  }

  $row = $result->fetch_assoc();                           //Read one row of results

  $whois_date = $row['save_time'];
  $whois_record = json_decode($row['record'], true);

  $result->free();

  return true;
}


/********************************************************************
 *  Extract Emails from Raw $whois_record
 *    Regex match for Registrant, Abuse, and Tech emails
 *    Don't add Tech email if it matches Registrant
 *
 *  Params:
 *    $whois_data['raw']
 *  Return:
 *    Array of emails found on success
 *    or false if nothing found
 */
function extractemails($rawdata) {
  $abuse = '';
  $registrant = '';
  $tech = '';
  $emails = array();
  
   if (preg_match('/Registrant\sEmail:\s([\w\.\-\_\+]+@[\w\.\-\_\+]+)/', $rawdata, $matches) > 0) {
    $registrant = strtolower($matches[1]);
    $emails['Registrant Email'] = $registrant;
  }

  if (preg_match('/Abuse\sContact\sEmail:\s([\w\.\-\_\+]+@[\w\.\-\_\+]+)/', $rawdata, $matches) > 0) {
    $abuse = strtolower($matches[1]);
    $emails['Abuse Contact'] = $abuse;
  }

  if (preg_match('/Tech\sEmail:\s([\w\.\-\_\+]+@[\w\.\-\_\+]+)/', $rawdata, $matches) > 0) {
    $tech = strtolower($matches[1]);
    if ($tech != $registrant) $emails['Tech Email'] = $tech;
  }
 
  if (count($emails) == 0) return false;
  
  return $emails;
}



/********************************************************************
 *  Show Who Is Data
 *    Displays data from $whois_record
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function show_whoisdata() {
  global $site, $whois_date, $whois_record;
  
  $blockreason = '';
  $notrack_row = '';

  if ($whois_record == null) return null;                  //Any data in the array?

  //TODO give user a chance to reload data
  if (isset($whois_record['error'])) {
    echo '<div class="sys-group">'.PHP_EOL;
    echo '<h5>Domain Information</h5>'.PHP_EOL;
    echo $whois_record['error'].PHP_EOL;
    echo '</div>'.PHP_EOL;
    return null;
  }

  $emails = extractemails($whois_record['raw']);
  $blockreason = search_blockreason($site);
  if ($blockreason != '') {
    $notrack_row = 'Blocked by '.get_blocklistname($blockreason);
  }
  else {
    $notrack_row = 'Allowed';
  }

  draw_systable('Domain Information');
  draw_sysrow('Domain Name', $whois_record['domain'].'<span class="investigatelink"><a href="?site='.$site.'&amp;v=raw">View Raw</a></span>');
  draw_sysrow('Status on NoTrack', $notrack_row);
  draw_sysrow('Created On', substr($whois_record['created_on'], 0, 10));
  draw_sysrow('Updated On', substr($whois_record['updated_on'], 0, 10));
  draw_sysrow('Expires On', substr($whois_record['expires_on'], 0, 10));
  draw_sysrow('Status', ucfirst($whois_record['status']));
  draw_sysrow('Registrar', $whois_record['registrar']['name']);
  
  if ($emails !== false) {
    foreach ($emails as $key => $value) {
      draw_sysrow($key, $value);
    }
  }
  
  if (isset($whois_record['nameservers'][0])) draw_sysrow('Name Servers', $whois_record['nameservers']['0']['name']);
  if (isset($whois_record['nameservers'][1])) draw_sysrow('', $whois_record['nameservers']['1']['name']);
  if (isset($whois_record['nameservers'][2])) draw_sysrow('', $whois_record['nameservers']['2']['name']);
  if (isset($whois_record['nameservers'][3])) draw_sysrow('', $whois_record['nameservers']['3']['name']);
  draw_sysrow('Last Retrieved', $whois_date);
  echo '</table></div>'.PHP_EOL;

  /*if (isset($whois_record['registrant_contacts'][0])) {
    draw_systable('Registrant Contact');
    draw_sysrow('Name', $whois_record['registrant_contacts']['0']['name']);
    draw_sysrow('Organisation', $whois_record['registrant_contacts']['0']['organization']);
    draw_sysrow('Address', $whois_record['registrant_contacts']['0']['address']);
    draw_sysrow('City', $whois_record['registrant_contacts']['0']['city']);
    draw_sysrow('Postcode', $whois_record['registrant_contacts']['0']['zip']);
    if (isset($whois_record['registrant_contacts'][0]['state'])) draw_sysrow('State', $whois_record['registrant_contacts']['0']['state']);
    draw_sysrow('Country', $whois_record['registrant_contacts']['0']['country']);
    if (isset($whois_record['registrant_contacts'][0]['phone'])) draw_sysrow('Phone', $whois_record['registrant_contacts']['0']['phone']);
    if (isset($whois_record['registrant_contacts'][0]['fax'])) draw_sysrow('Fax', $whois_record['registrant_contacts']['0']['fax']);
    if (isset($whois_record['registrant_contacts'][0]['email'])) draw_sysrow('Email', strtolower($whois_record['registrant_contacts']['0']['email']));
    echo '</table></div>'.PHP_EOL;
  }*/

}

/********************************************************************
 *  Show Raw Who Is Data
 *    Displays contents of raw item in $whois_record
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function show_rawwhoisdata() {
  global $whois_date, $whois_record;

  if ($whois_record == null) return null;                  //Any data in the array?

  if (isset($whois_record['error'])) {
    echo '<div class="sys-group">'.PHP_EOL;
    echo '<h5>Domain Information</h5>'.PHP_EOL;
    echo $whois_record['error'].PHP_EOL;
    echo '</div>'.PHP_EOL;
    return null;
  }
  
  echo '<pre>'.PHP_EOL;
  echo $whois_record['raw'];
  echo '</pre>'.PHP_EOL;
}
/********************************************************************
 *  Show Who Is Error when no API is set
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function show_whoiserror() {
  echo '<div class="sys-group">'.PHP_EOL;
  echo '<h5>Domain Information</h5>'.PHP_EOL;
  echo '<p>Error: No WhoIs API key set. In order to use this feature you will need to add a valid JsonWhois API key to NoTrack config</p>'.PHP_EOL;
  echo '<p>Instructions:</p>'.PHP_EOL;
  echo '<ol>'.PHP_EOL;
  echo '<li>Sign up to <a href="https://jsonwhois.com/">JsonWhois.com</a></li>'.PHP_EOL;
  echo '<li> Add your API key to NoTrack <a href="./config.php?v=general">Config</a></li>'.PHP_EOL;
  echo '</ol>'.PHP_EOL;
  echo '</div>'.PHP_EOL;
}


/********************************************************************
 *  Count Queries
 *    1. log_date by day, dns_result for site for past 30 days count and grouped by day
 *    2. Create associative array for each day (to account for days when no queries are made
 *    3. Copy known count values into associative array
 *    4. Move associative values into index array
 *    5. Draw line chart
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function count_queries() {
  global $db, $domain, $site;

  $allowed_arr = array();
  $blocked_arr = array();
  $chart_labels = array();
  $link_labels = array();
  $currenttime = 0;
  $datestr = '';
  $query = '';

  $currenttime = time();

  $starttime = strtotime('-30 days');
  $endtime = strtotime('+1 days');

  if ($domain != $site) {
    $query = "SELECT date_format(log_time, '%m-%d') as log_date, dns_result, COUNT(1) as count FROM dnslog WHERE dns_request LIKE '%$domain' GROUP BY dns_result, log_date";
  }
  else {
    $query = "SELECT date_format(log_time, '%m-%d') as log_date, dns_result, COUNT(1) as count FROM dnslog WHERE dns_request LIKE '%$site' GROUP BY dns_result, log_date";
  }

  if(!$result = $db->query($query)){
    echo '<h4><img src=./svg/emoji_sad.svg>Error running query</h4>'.PHP_EOL;
    echo 'count_queries: '.$db->error;
    echo '</div>'.PHP_EOL;
    die();
  }

  for ($i = $starttime; $i < $endtime; $i += 86400) {      //Increase by 1 day from -30 days to today
    $datestr = date('m-d', $i);
    $allowed_arr[$datestr] = 0;
    $blocked_arr[$datestr] = 0;
    $chart_labels[] = $datestr;
    $link_labels[] = date('Y-m-d 00:00:00', $i);
  }

  if ($result->num_rows == 0) {                            //Leave if nothing found
    $result->free();
    //echo '<h4><img src=./svg/emoji_sad.svg>No results found</h4>'.PHP_EOL;
    return false;
  }

  while($row = $result->fetch_assoc()) {                   //Read each row of results

    if (! array_key_exists($row['log_date'], $allowed_arr)) continue;

    if ($row['dns_result'] == 'A') {
      $allowed_arr[$row['log_date']] = $row['count'];
    }
    elseif ($row['dns_result'] == 'B') {
      $blocked_arr[$row['log_date']] = $row['count'];
    }
  }

  $result->free();

  linechart(array_values($allowed_arr), array_values($blocked_arr), $chart_labels, $link_labels, 'dtrange=1440&amp;searchbox='.$domain, 'Queries over past 30 days');   //Draw the line chart
  return null;
}

/********************************************************************
 *Main
 */

$db = new mysqli(SERVERNAME, USERNAME, PASSWORD, DBNAME);  //Open MariaDB connection

if (isset($_GET['sys'])) {                                 //Any system set?
  if (filter_var($_GET['sys'], FILTER_VALIDATE_IP)) {
    $sys = $_GET['sys'];                                   //Just check for valid IP rather than if system is in dnslog
  }
}

if (isset($_GET['datetime'])) {                            //Filter for hh:mm:ss
  if (preg_match(REGEX_DATETIME, $_GET['datetime']) > 0) {
    $datetime = $_GET['datetime'];
  }
}

if (isset($_GET['site'])) {
  if (filter_domain(trim($_GET['site']))) {
    $site = trim($_GET['site']);
    $domain = extract_domain($site);
  }
}

if (isset($_GET['v'])) {
  if ($_GET['v'] == 'raw') {
    $showraw = true;
  }
}

if (!table_exists('whois')) {                              //Does whois sql table exist?
  create_whoistable();                                     //If not then create it
  sleep(1);                                                //Delay to wait for MariaDB to create the table
}

if ($Config['whoisapi'] == '') {                           //Has user set an API key?
  show_whoiserror();                                       //No - Don't go any further
  $db->close();
  exit;
}



if ($domain != '') {                                       //Load whois data?
  draw_searchbar();
  if ($datetime != '') show_time_view();                   //Show time view if datetime in parameters

  if (! search_whois($domain)) {                           //Attempt to search whois table
    get_whoisdata($domain, $Config['whoisapi']);           //No record found - download it from JsonWhois
  }
  
  if ($showraw) {
    show_rawwhoisdata();
  }
  else {
    show_whoisdata();                                      //Display fancy whois data
    count_queries();                                       //Show log data for last 30 days
  }
}
else {
  draw_searchbox();
}

$db->close();

?>
</div>
</body>
</html>
