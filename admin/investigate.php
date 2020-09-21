<?php
/*A different view is displayed depending how user enters this page
* 1. No arguments set - Show searchbox
* 2. Domain set - display whois, and graph
* 3. Domain and time set - display surrounding queries, whois, and graph
*/
require('./include/global-vars.php');
require('./include/global-functions.php');
require('./include/config.php');
require('./include/menu.php');
require('./include/whoisapi.php');
ensure_active_session();

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <link href="./css/master.css" rel="stylesheet" type="text/css">
  <link href="./css/chart.css" rel="stylesheet" type="text/css">
  <link href="./css/icons.css" rel="stylesheet" type="text/css">
  <link rel="icon" type="image/png" href="./favicon.png">
  <script src="./include/menu.js"></script>
  <script src="./include/queries.js"></script>
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
$domain = '';                                              //site.com
$subdomain = '';                                           //subdomain.site.com
$forceupdate = false;                                      //Get latest info about a domain
$showraw = false;                                          //Raw view or fancy view
$sys = '';

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
 *  Draw Filter Toolbar
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function draw_filter_toolbar() {
  global $subdomain;

  echo '<div class="filter-toolbar single-filter-toolbar">'.PHP_EOL;
  echo '<form method="GET">'.PHP_EOL;
  echo '<input type="text" name="site" class="input-conf" placeholder="Search domain" value="'.$subdomain.'">'.PHP_EOL;
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
  global $subdomain;

  echo '<div id="search-box"><div>'.PHP_EOL;
  echo '<form method="GET">'.PHP_EOL;
  echo '<input type="text" name="site" placeholder="Search domain" value="'.$subdomain.'">&nbsp;'.PHP_EOL;
  echo '<input type="submit" value="Investigate">'.PHP_EOL;
  echo '</form>'.PHP_EOL;
  echo '</div></div>'.PHP_EOL;
}


/******************************************************************
 *  Extract Emails from Raw $whois_record
 *    Regex match for Registrant, Abuse, and Tech emails
 *    Don't add Tech email if it matches Registrant
 *
 *  Params:
 *    Jsondata['raw'] string
 *  Return:
 *    Array of emails found on success
 *    or false if nothing found
 */
function extract_emails($raw) {
  $abuse = '';
  $registrant = '';
  $tech = '';
  $emails = array();


  if (preg_match('/Registrant\sEmail:\s([\w\.\-\_\+]+@[\w\.\-\_\+]+)/', $raw, $matches) > 0) {
    $registrant = strtolower($matches[1]);
    $emails['Registrant Email'] = $registrant;
  }

  if (preg_match('/Abuse\sContact\sEmail:\s([\w\.\-\_\+]+@[\w\.\-\_\+]+)/', $raw, $matches) > 0) {
    $abuse = strtolower($matches[1]);
    $emails['Abuse Contact'] = $abuse;
  }

  if (preg_match('/Tech\sEmail:\s([\w\.\-\_\+]+@[\w\.\-\_\+]+)/', $raw, $matches) > 0) {
    $tech = strtolower($matches[1]);
    if ($tech != $registrant) $emails['Tech Email'] = $tech;
  }

  if (count($emails) == 0) return false;

  return $emails;
}


/********************************************************************
 *  Search Block Reason
 *    1. Search site.com in blocklist table
 *    2. Search .tld in blocklist table
 *    3. Search for like site.com in blocklist table
 *    4. On fail return ''
 *
 *  Params:
 *    $domain - Domain to search
 *  Return:
 *    blocklist name
 */
function search_blockreason($domain) {
  global $db;
  $res = '';

  preg_match('/[\w\-_]+(\.co|\.com|\.org|\.gov)?\.([\w\-]+)$/', $domain, $matches);

  //Search for site.com
  //Negate whitelist to prevent stupid results
  $result = $db->query("SELECT bl_source FROM blocklist WHERE site = '".$matches[0]."' AND bl_source != 'whitelist'");
  if ($result->num_rows > 0) {
    $res = $result->fetch_row()[0];
  }
  else {
    $result->free();
    //Search for .tld
    $result = $db->query("SELECT bl_source FROM blocklist WHERE site = '.".$matches[2]."' AND bl_source = 'bl_tld'");
    if ($result->num_rows > 0) {
      $res = $result->fetch_row()[0];
    }
    else {
      $result->free();
      //Search for like site.com (possibly prone to misidentifying bl_source)
      $result = $db->query("SELECT bl_source FROM blocklist WHERE site LIKE '%.".$matches[0]."' AND bl_source != 'whitelist'");
      if ($result->num_rows > 0) {
        $res = $result->fetch_row()[0];
      }
    }
  }

  $result->free();
  return $res;
}

/********************************************************************
 *  Format Row
 *    Returns the action, blockreason, event, and severity in an array
 *
 *  Params:
 *    domain, dns_result(allowed, blocked, local)
 *
 *  Return:
 *    Array of variables to be taken using list()
 */
function format_row($domain, $dns_result) {
  global $config;

  $blocklist = '';
  $blockreason = '';
  $event = '';
  $severity = '1';
  $popupmenu = '';


  $popupmenu = '<div class="dropdown-container"><span class="dropbtn"></span><div class="dropdown">';

  if ($dns_result == 'A') {
    $event = 'allowed1';
    $popupmenu .= '<span onclick="reportSite(\''.$domain.'\', false, true)">Block</span>';
  }
  elseif ($dns_result == 'B') {                            //Blocked
    $blocklist = search_blockreason($domain);
    $rowseverity = '2';

    if ($blocklist == 'bl_notrack') {                      //Show Report on NoTrack list
      $blockreason = '<p class="small grey">Blocked by NoTrack list</p>';
      $event = 'tracker2'; //TODO change image
      $popupmenu .= '<span onclick="reportSite(\''.$domain.'\', true, true)">Allow</span>';
    }
    elseif ($blocklist == 'custom') {                      //Users blacklist
      $blockreason = '<p class="small grey">Blocked by Custom Black list</p>';
      $event = 'custom2';
      $popupmenu .= '<span onclick="reportSite(\''.$domain.'\', true, false)">Allow</span>';
    }
    elseif ($blocklist != '') {                            //Other blocklist
      $blockreason = '<p class="small grey">Blocked by '.$config->get_blocklistname($blocklist).'</p>';
      $event = $config->get_blocklistevent($blocklist);
      $event .= $rowseverity;
      $popupmenu .= '<span onclick="reportSite(\''.$domain.'\', true, false)">Allow</span>';
    }
    else {  //No reason is probably IP or Search request
      $blockreason = '<p class="small">Invalid request</p>';
      $event = 'invalid2';
    }
  }
  elseif ($dns_result == 'M') {                            //Malware Accessed
    $blockreason = '<p class="small grey">Malware Accessed</p>';
    $event = 'malware3';
    $rowseverity = '3';
    $popupmenu .= '<span onclick="reportSite(\''.$domain.'\', false, true)">Block</span>';
  }
  elseif ($dns_result == 'T') {                            //Tracker Accessed
    $blockreason = '<p class="small grey">Tracker Accessed</p>';
    $event = 'tracker3';
    $rowseverity = '3';
    $popupmenu .= '<span onclick="reportSite(\''.$domain.'\', false, true)">Block</span>';
  }
  elseif ($dns_result == 'L') {
    $event = 'local1';
  }

  $popupmenu .= "<a href=\"{$config->search_url}{$domain}\" target=\"_blank\">{$config->search_engine}</a>";
  $popupmenu .= '<a href="https://www.virustotal.com/en/domain/'.$domain.'/information/" target="_blank">VirusTotal</a>';
  $popupmenu .= '</div></div>';                                  //End dropdown-container

  return array($blockreason, $event, $severity, $popupmenu);
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
  global $config, $db, $datetime, $subdomain, $sys;

  $action = '';
  $blockreason = '';
  $clipboard = '';                                         //Div for Clipboard
  $dns_request = '';
  $event = '';                                             //Image event
  $popupmenu = '';                                         //Div for popup menu
  $row_class = '';                                         //Optional row highlighting
  $severity = 1;
  $query = '';
  $domain_cell = '';

  $query = "SELECT *, DATE_FORMAT(log_time, '%H:%i:%s') AS formatted_time FROM dnslog WHERE sys = '$sys' AND log_time > SUBTIME('$datetime', '00:00:04') AND log_time < ADDTIME('$datetime', '00:00:03') ORDER BY UNIX_TIMESTAMP(log_time)";


  if (!$result = $db->query($query)){
    echo '<h4><img src=./svg/emoji_sad.svg>Error running query</h4>'.PHP_EOL;
    echo 'show_time_view: '.$db->error;
    echo '</div>'.PHP_EOL;
    return false;
  }

  if ($result->num_rows == 0) {                  //Leave if nothing found
    $result->free();
    echo '<h4><img src=./svg/emoji_sad.svg>No results found for selected time</h4>'.PHP_EOL;
    echo '</div>'.PHP_EOL;
    return false;
  }

  echo '<table id="query-time-table">'.PHP_EOL;
  echo '<tr><th>&nbsp</th><th>Time</th><th>System</th><th>Site</th><th></th></tr>'.PHP_EOL;

  while($row = $result->fetch_assoc()) {         //Read each row of results
    $dns_request = $row['dns_request'];
    list($blockreason, $event, $severity, $popupmenu) = format_row($dns_request, $row['dns_result']);

    //Create clipboard image and text
    $clipboard = '<div class="icon-clipboard" onclick="setClipboard(\''.$dns_request.'\')" title="Copy domain">&nbsp;</div>';

    //Contents of domain cell with more specific url for investigate
    $domain_cell = "<a href=\"./investigate.php?datetime={$row['log_time']}&amp;site={$dns_request}&amp;sys={$row['sys']}\" target=\"_blank\">{$dns_request}</a>{$clipboard}{$blockreason}";

    if ($subdomain == $row['dns_request']) {               //Highlight row if it matches the subdomain requested
      $row_class = ' class="cyan"';
    }
    else {
      $row_class = '';
    }

    //Output table row
    echo "<tr{$row_class}><td><img src=\"./svg/events/{$event}.svg\" alt=\"\"><td>{$row['formatted_time']}</td><td>{$row['sys']}</td><td>{$domain_cell}</td><td>{$popupmenu}</td></tr>".PHP_EOL;
    $blockreason = '';
  }

  echo '</table>'.PHP_EOL;
  echo '</div>'.PHP_EOL;

  $result->free();
  return true;
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
function show_whoisdata($whois_date, $whois_record) {
  global $config, $subdomain;
  
  $blockreason = '';
  $notrack_row = '';

  if ($whois_record == null) return null;                  //Any data in the array?

  if (isset($whois_record['error'])) {
    //echo '<div class="sys-group">'.PHP_EOL;
    echo '<h5>Domain Information</h5>'.PHP_EOL;
    echo $whois_record['error'].PHP_EOL;
    echo '</div>'.PHP_EOL;
    return null;
  }

  $emails = extract_emails($whois_record['raw']);
  $blockreason = search_blockreason($subdomain);
  if ($blockreason != '') {
    $notrack_row = 'Blocked by '.$config->get_blocklistname($blockreason);
  }
  else {
    $notrack_row = 'Allowed';
  }

  //draw_systable('Domain Information');
  echo '<h5>Domain Information</h5>'.PHP_EOL;
  echo '<table class="sys-table">'.PHP_EOL;
  draw_sysrow('Domain Name', $whois_record['domain'].'<span class="investigatelink"><a href="?site='.$subdomain.'&amp;v=raw">View Raw</a></span>');
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
  draw_sysrow('Last Retrieved', $whois_date.'<span class="investigatelink"><a href="?site='.$subdomain.'&amp;update">Get Latest</a></span>');
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
function show_rawwhoisdata($whois_record) {
  if ($whois_record == null) return null;                  //Any data in the array?

  if (isset($whois_record['error'])) {
    echo '<div class="sys-group">'.PHP_EOL;
    echo '<h5>Domain Information</h5>'.PHP_EOL;
    echo $whois_record['error'].PHP_EOL;
    echo '</div>'.PHP_EOL;
    return null;
  }
  
  echo '</div>'.PHP_EOL;                                   //End sys-group from filter-toolbar
  echo '<pre>'.PHP_EOL;
  echo $whois_record['raw'];
  echo '</pre>'.PHP_EOL;
}


/********************************************************************
 *  Show Who Is Error when no API has been set
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function show_whoiserror() {
  echo '<div class="sys-group">'.PHP_EOL;
  echo '<h5>Domain Information</h5>'.PHP_EOL;
  echo '<p>In order to use this feature you will need to add a valid JsonWhois API key to NoTrack config</p>'.PHP_EOL;
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
  global $db, $domain, $subdomain;

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

  if ($domain != $subdomain) {
    $query = "SELECT date_format(log_time, '%m-%d') as log_date, dns_result, COUNT(1) as count FROM dnslog WHERE dns_request LIKE '%$domain' GROUP BY dns_result, log_date";
  }
  else {
    $query = "SELECT date_format(log_time, '%m-%d') as log_date, dns_result, COUNT(1) as count FROM dnslog WHERE dns_request LIKE '%$subdomain' GROUP BY dns_result, log_date";
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
    $link_labels[] = date('Y-m-d\T00:00:00', $i);
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

  linechart(array_values($allowed_arr), array_values($blocked_arr), $chart_labels, $link_labels, '/P1D&amp;searchbox=*'.$domain, 'Queries over past 30 days');   //Draw the line chart
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
    $subdomain = trim($_GET['site']);
    $domain = extract_domain($subdomain);
  }
}

if (isset($_GET['update'])) {
  $forceupdate = true;
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

if ($config->whois_api == '') {                            //Has user set an API key?
  show_whoiserror();                                       //No - Don't go any further
  $db->close();
  exit;
}


if ($domain == '') {                                       //No domain set, just show searchbox
  draw_searchbox();
}
else {                                                     //Load whois data?
  echo '<div class="sys-group">'.PHP_EOL;
  draw_filter_toolbar();

  $whois = new WhoisApi($config->whois_api, $domain);
  if ($datetime != '') {                                   //Show time view if datetime in parameters
    show_time_view();
    echo '<div class="sys-group">'.PHP_EOL;
  }

  if ($forceupdate) {                                      //Are we deleting the old record?
    $whois->delete_whoisrecord();
  }
  if (! $whois->search_whoisrecord()) {                    //Attempt to search whois table
    $whois->get_whoisdata(); //No record found - download it from JsonWhois
  }


  if ($showraw) {                                          //Show basic raw view?
    show_rawwhoisdata($whois->jsondata);
  }
  else {                                                   //Display fancy whois data
    show_whoisdata($whois->download_date, $whois->jsondata);
    count_queries();                                       //Show log data for last 30 days
  }
}

draw_copymsg();
$db->close();

?>
</div>
<div id="queries-box">
<h2 id="sitename">site</h2>
<span id="reportmsg">something</span>
<form action="./investigate.php" method="get" target="_blank">
<span id="searchitem"></span>
<span id="invitem"></span>
</form>
<form action="./config/customblocklist.php" method="POST" target="_blank">
<input type="hidden" name="v" id="reportv" value="none">
<input type="hidden" name="action" id="reportaction" value="none">
<input type="hidden" name="status" value="add">
<input type="hidden" name="comment" value="">
<span id="reportitem1"></span>
<span id="reportitem2"></span>
</form>
<form name="reportform" action="https://quidsup.net/notrack/report.php" method="post" target="_blank">
<input type="hidden" name="site" id="siterep" value="none">
<span id="reportitem3"><input type="submit" value="Report">&nbsp;<input type="text" name="comment" class="textbox-small" placeholder="Optional comment"></span>
</form>

<br>
<div class="centered"><button class="button-grey" onclick="hideQueriesBox()">Cancel</button></div>
<div class="close-button" onclick="hideQueriesBox()"><img src="./svg/button_close.svg" onmouseover="this.src='./svg/button_close_over.svg'" onmouseout="this.src='./svg/button_close.svg'" alt="close"></div>
</div>
<script>
const SEARCHNAME = <?php echo json_encode($config->search_engine)?>;
const SEARCHURL = <?php echo json_encode($config->search_url)?>;
const WHOISNAME = <?php echo json_encode($config->whois_provider)?>;
const WHOISURL = <?php echo json_encode($config->whois_url)?>;
const WHOISAPI = <?php echo ($config->whois_api == '') ? 0 : 1;?>;
</script>
</body>
</html>
