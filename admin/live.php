<?php
//1. Draw initial page with PHP. Most of the functionality onwards is done clientside with javascript
//2. Draw blank table cells up to MAX_LINES
//3. Run a timer
//4. Send POST request to API asking for contents of DNS_LOG file
//5. Parse DNS_LOG (in a similar method to logparser.py) into requestBuffer map
//6. Move limited number of items from requestBuffer to readyBuffer based on size of requestBuffer
//7. displayRequests shows contents of readyBuffer

require('./include/global-vars.php');
require('./include/global-functions.php');
require('./include/config.php');
require('./include/menu.php');

ensure_active_session();
//-------------------------------------------------------------------
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <link href="./css/master.css" rel="stylesheet" type="text/css">
  <link href="./css/icons.css" rel="stylesheet" type="text/css">
  <link rel="icon" type="image/png" href="./favicon.png">
  <script src="./include/menu.js"></script>
  <script src="./include/queries.js"></script>
  <meta name="viewport" content="width=device-width, initial-scale=0.8">
  <title>NoTrack - Live</title>
</head>

<body>
<?php
draw_topmenu('Live');
draw_sidemenu();
draw_copymsg();
echo '<div id="main">'.PHP_EOL;

echo '<div class="sys-group">'.PHP_EOL;
echo '<div class="filter-toolbar live-filter-toolbar">'.PHP_EOL;

echo '<div><h3>IP</h3></div>'.PHP_EOL;                     //Column Headers
echo '<div><h3>Severity</h3></div>'.PHP_EOL;
echo '<div></div>'.PHP_EOL;

echo '<div><input type="text" id="ipaddressbox" value="" placeholder="192.168.0.1"></div>';

echo '<div class="filter-nav-group">'.PHP_EOL;             //Start Group 2 - Severity
echo '<span class="filter-nav-button" title="Low - Connection Allowed" onclick="toggleNavButton(this, \''.SEVERITY_LOW.'\')"><img src="./svg/filters/severity_low.svg" alt=""></span>'.PHP_EOL;
echo '<span class="filter-nav-button" title="Medium - Connection Blocked" onclick="toggleNavButton(this, \''.SEVERITY_MED.'\')"><img src="./svg/filters/severity_med.svg" alt=""></span>'.PHP_EOL;
echo '<span class="filter-nav-button" title="High - Malware or Tracker Accessed" onclick="toggleNavButton(this, \''.SEVERITY_HIGH.'\')"><img src="./svg/filters/severity_high.svg" alt=""></span>'.PHP_EOL;
echo '</div>'.PHP_EOL;                                     //End Group 2 - Severity

echo '<div class="filter-nav-group">'.PHP_EOL;             //Start Group 3 -
echo '<span class="filter-nav-button active" onclick="pauseQueue()"><img src="./svg/lmenu_pause.svg" id="pausequeueimg" alt=""></span>';
echo '<span class="filter-nav-button" class="button-grey" onclick="clearQueue()"><img src="./svg/lmenu_clear.svg" id="clearqueueimg" alt=""></span>';
echo '</div>'.PHP_EOL;                                     //End Group 3
echo '</div>'.PHP_EOL;                                     //End Filter toolbar

echo '<table id="livetable">'.PHP_EOL;                     //Fill in table using javascript
echo '</table>'.PHP_EOL;
echo '</div>'.PHP_EOL;
echo '</div>'.PHP_EOL;
?>

<div id="scrollup" class="button-scroll" onclick="scrollToTop()"><img src="./svg/arrow-up.svg" alt="up"></div>
<div id="scrolldown" class="button-scroll" onclick="scrollToBottom()"><img src="./svg/arrow-down.svg" alt="down"></div>

<form name="customBlockForm" id="customBlockForm" method="POST" target="_blank" action="./config/customblocklist.php">
<input type="hidden" name="v" value="" id="viewItem">
<input type="hidden" name="action" value="" id="actionItem">
<input type="hidden" name="site" value="" id="domainItem">
<input type="hidden" name="comment" value="" id="commentItem">
<input type="hidden" name="status" value="add" id="statusItem">
</form>

<script>
const SEARCH = <?php echo json_encode($config->search_engine)?>;
const SEARCHURL = <?php echo json_encode($config->search_url)?>;
<?php
if ($config->whois_api == '') {                            //Setup Investigate / Whois for popupmenu
  echo 'const INVESTIGATE = "'.$config->whois_provider.'";'.PHP_EOL;
  echo 'const INVESTIGATEURL = "'.$config->whois_url.'";'.PHP_EOL;
}
else {
  echo 'const INVESTIGATE = "Investigate";'.PHP_EOL;
  echo 'const INVESTIGATEURL = "./investigate.php?subdomain=";'.PHP_EOL;
}
?>
const MAX_LINES = 27;
const SEVERITY_HIGH = <?php echo SEVERITY_HIGH?>;
const SEVERITY_MED = <?php echo SEVERITY_MED?>;
const SEVERITY_LOW = <?php echo SEVERITY_LOW?>;
var paused = false;
var throttleApiRequest = 0;                 //Throttle read requests for DNS_LOG
var throttleClean = 0;                      //Throttle how oftern requestBuffer is cleaned
var readyBuffer = new Array();              //Requests displayed on livetable
var requestBuffer = new Map();              //Requests read from DNS_LOG waiting to be moved to readyBuffer
var searchSeverity = 0;

drawTable();
loadApi();
moveBuffer();
displayRequests();


/********************************************************************
 *  Add item to requestBuffer
 *    Check if item has already been added based on serial number
 *    Displayed parameter is set to false
 *
 *  Params:
 *    serial, log_date, sys, dns_request, severity, bl_source
 *  Return:
 *    True - item added
 *    False - item already in requestBuffer
 */
function addBuffer(serial, log_date, sys, dns_request, severity, bl_source) {
  if (requestBuffer.has(serial)) {
    return false;
  }
  else {
    requestBuffer.set(serial, {log_date: parseInt(log_date), sys: sys, dns_request: dns_request, severity: severity, bl_source: bl_source, displayed: false});
  }
  return true;
}


/********************************************************************
 *  Beautify Domain
 *    Drop www. from beginning of DNS Request
 *
 *  Params:
 *    domain (str): domain to beautify
 *  Return:
 *    beautified domain string
 */
function beautifyDomain(domain) {
  if (/^(www\.)/.test(domain)) {
    return domain.substr(4);
  }
  return domain;
}


/********************************************************************
 *  Beautify Time
 *    Return formatted time: hh:mm:ss
 *  Params:
 *    UNIX Time
 *  Return:
 *    Formatted string of time
 */
function beautifyTime(log_date) {
  let dt = new Date(log_date);
  let hr = dt.getHours();
  let m = '0' + dt.getMinutes();
  let s = '0' + dt.getSeconds();
  return hr + ':' + m.substr(-2) + ':' + s.substr(-2);
}


/********************************************************************
 *  Check Severity for Search
 *    Called from moveBuffer to determine if an item should be added if a severity
 *     has been selected
 *  Params:
 *    addEntry (bool): the current status of addEntry
 *    severity (int): Severity value from requestBuffer
 *  Return:
 *    Bool confirming if entry should be added to requestBuffer
 */
function checkSeverity(addEntry, severity) {
  if (! addEntry) return false;                            //Item should already be dropped

  if (searchSeverity == 0) return true;                    //No search active

  //Severity search is active, check whether the item should be added
  if ((severity == 1) && (searchSeverity & SEVERITY_LOW)) return true;
  if ((severity == 2) && (searchSeverity & SEVERITY_MED)) return true;
  if ((severity == 3) && (searchSeverity & SEVERITY_HIGH)) return true;

  return false;
}


/********************************************************************
 *  Draw Table
 *
 */
function drawTable() {
  let liveTable = document.getElementById('livetable');
  for (let i = 0; i < MAX_LINES - 1; i++) {
    let row = liveTable.insertRow(i);
    row.insertCell(0);
    row.insertCell(1);
    row.insertCell(2);
    row.insertCell(3);
    row.insertCell(4);
    liveTable.rows[i].cells[0].innerHTML = '<img src="./svg/events/blank.svg" alt="">';
  }
}


/********************************************************************
 *  Clean Buffer
 *    Limit memory utilisation of requestBuffer by removing items that have been displayed
 *    Keep displayed items in for 15 Mins
 *    Keep non-displayed items in for 60 Mins
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function cleanBuffer() {
  let clearTime = 0;

  clearTime = Date.now() - 900000;                         //15 Mins for displayed items
  purgeTime = Date.now() - 3600000;                        //60 Mins for non-disabled items

  for (let [key, values] of requestBuffer.entries()) {
    if (values.displayed) {
      if (values.log_date < clearTime) {                   //Under clearTime?
        requestBuffer.delete(key);
      }
    }
    else if (values.log_date < purgeTime) {                //Under purgeTime?
      requestBuffer.delete(key);
    }
  }
}


/********************************************************************
 *  Count Buffer Size
 *    Count the number of undisplayed items in requestBuffer
 *
 *  Params:
 *    None
 *  Return:
 *    Number of items undisplayed
 */
function countBufferSize() {
  let bufferSize = 0;

  for (let [key, values] of requestBuffer.entries()) {
    if (values.displayed == false) {
      bufferSize++;
    }
  }

  return bufferSize;
}


/********************************************************************
 * Clear Queue
 *    Activated when user clicks Clear button
 *    1. Delete all values from readyBuffer and requestBuffer
 *    2. Clear all values from liveTable
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function clearQueue() {
  let liveTable = document.getElementById('livetable');

  readyBuffer = [];
  requestBuffer.clear();

  //Remove all values from the table
  for (let i = 0; i < MAX_LINES - 1; i++) {
    //liveTable.rows[i].className = '';
    liveTable.rows[i].cells[0].innerHTML = '<img src="./svg/events/blank.svg" alt="">';
    liveTable.rows[i].cells[1].innerHTML = '&nbsp;';
    liveTable.rows[i].cells[2].innerHTML = '&nbsp;';
    liveTable.rows[i].cells[3].innerHTML = '&nbsp;';
    liveTable.rows[i].cells[4].innerHTML = '&nbsp;';
  }
}


/********************************************************************
 *  Display Queue
 *    Show the results from readyBuffer array in livetable
 *    Array is displayed from end-to-start (latest-to-earliest)
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function displayRequests() {
  let queuesize = readyBuffer.length;
  let liveTable = document.getElementById('livetable');
  let clipboard = '';                                      //Div for Clipboard
  let domain = '';
  let currentRow = 0;

  if (paused) return;                                      //No updates when paused

  for (let i = queuesize - 1; i > 0; i--) {                //Start with latest first
    bl_source = readyBuffer[i].bl_source;
    domain = readyBuffer[i].dns_request;
    severity = readyBuffer[i].severity;

    clipboard = '<div class="icon-clipboard" onclick="setClipboard(\''+domain+'\')" title="Copy domain">&nbsp;</div>';

    imgSrc = './svg/events/' + bl_source + severity + '.svg';
    if (bl_source == 'allowed') imgTitle = 'Ok (Forwarded)';
    else if (bl_source == 'cached') imgTitle = 'Ok (Cached)';
    else if (bl_source == 'cname') imgTitle = 'Ok (CNAME)';
    else if (bl_source == 'local') imgTitle = 'Local';
    else if (severity == 2) imgTitle = 'Blocked';

    liveTable.rows[currentRow].cells[0].innerHTML = '<img src="' + imgSrc + '" alt="" title="' + imgTitle + '">';
    liveTable.rows[currentRow].cells[1].innerHTML = beautifyTime(readyBuffer[i].log_date);
    liveTable.rows[currentRow].cells[2].innerHTML = domain + clipboard;
    liveTable.rows[currentRow].cells[3].innerHTML = readyBuffer[i].sys
    liveTable.rows[currentRow].cells[4].innerHTML = popupMenu(domain, severity, bl_source);
    currentRow++;
  }
}


/********************************************************************
 *  Move requestBuffer to readyBuffer
 *    1. This function is called when requestBuffer is ready to be moved to readyBuffer
 *       (i.e. window visible and not paused by user)
 *    2. Calculate how much of the requestBuffer to move to readyBuffer
 *    3. Carry out any searches - IP / Severity
 *    4. Add object from requestBuffer into readyBuffer
 *    5. Mark requestBuffer object as displayed (set displayed to true)
 *
 */
function moveBuffer() {
  let addEntry = true;
  let i = 0;
  let bufferSize = 0;
  let target = 0;                                //Number of items to add into readyBuffer
  let searchIP = '';
  let validSearchIP = false;

  //Don't move the queue if user has paused
  if (paused) return;

  bufferSize = countBufferSize();                //Count number of non-displayed items

  //Check if user has entered a valid IPv4 or IPv6 in ipaddressbox text box
  searchIP = document.getElementById("ipaddressbox").value.trim();
  validSearchIP = validIP(searchIP);

  if (bufferSize == 0) return;                   //Prevent div by zero

  //Target is based on a percentage of the bufferSize with no more than 75% of the buffer to be moved
  target = Math.ceil((bufferSize / MAX_LINES) * 3.5);
  if (target > MAX_LINES * 0.75) target = Math.ceil(MAX_LINES * 0.75);
  //If nothing much in readyBuffer, add ten requests
  if (readyBuffer.size < 10) target = 10;

  for (let [key, values] of requestBuffer.entries()) {
    if (values.displayed) continue;

    if (validSearchIP) {                                   //Searching for a specific IP?
      (values.sys == searchIP) ? addEntry = true : addEntry = false;
    }
    addEntry = checkSeverity(addEntry, values.severity);   //Searching for a severity?

    if (addEntry) {
      readyBuffer.push(values);                            //Add object to readyBuffer
      requestBuffer.get(key).displayed = true;
      i++;
    }

    if (readyBuffer.length > MAX_LINES) readyBuffer.shift(); //Delete trailing lines
    if (i >= target) break;

    addEntry = true;
  }
}


/********************************************************************
 * Pause Queue
 *    Activated when user clicks Pause / Play button
 *
 */
function pauseQueue() {
  let pauseQueueImg = document.getElementById('pausequeueimg');
  paused = !paused;

  if (paused) {
    pauseQueueImg.src = './svg/lmenu_play.svg';
  }
  else {
    pauseQueueImg.src = './svg/lmenu_pause.svg';
  }
}


/********************************************************************
 *  Popup Menu
 *    HTML Code for popup menu using dropdown-container
 *    Paused should be activated when using the popup menu, this is done using onmouseover / onmouseout
 *
 *  Params:
 *    domain (str): Domain name
 *    severity (int): Severity - 1,2,3
 *    bl_source: Block List Source
 *  Return:
 *    HTML code for popup menu
 */
function popupMenu(domain, severity, bl_source) {
  let str = '';

  str = '<div class="dropdown-container" onmouseover="pauseQueue()" onmouseout="pauseQueue()"><span class="dropbtn"></span><div class="dropdown">';

  if ((severity == 1) && (bl_source != 'local')) {         //Allowed, exclude Local
    str += '<span onclick="submitCustomBlock(\''+domain+'\', \'black\')">Block</span>';
  }
  else if (severity == 2) {                               //Blocked
    str += '<span onclick="submitCustomBlock(\''+domain+'\', \'white\')">Allow</span>';
  }

  str += '<a href="'+INVESTIGATEURL+domain+'" target="_blank">'+INVESTIGATE+'</a>';
  str += '<a href="'+SEARCHURL+domain+'" target="_blank">'+SEARCH+'</a>';
  str += '<a href="https://www.virustotal.com/en/domain/'+domain+'/information/" target="_blank">VirusTotal</a>';
  str += '</div></div>';                                   //End dropdown-container

  return str;
}


/********************************************************************
 *  Read Log Data
 *    Parse JSON output from DNS_LOG into requestBuffer
 *    DNS_LOG will be flushed by logparser.py
 *
 *  Params:
 *    JSON Data
 *  Return:
 *    None
 */
function readLogData(data) {
  let currentYear = new Date().getFullYear();
  let line = '';
  let domain = '';
  let serial = '';
  let sys = '';
  let matches = new Array();
  let tempQueries = new Map();

  var regexp = /^(?<log_date>\w{3}  ?\d{1,2} \d{2}:\d{2}:\d{2}) dnsmasq\[\d{1,7}\]: (?<serial>\d+) (?<sys>[\d\.:]+)\/\d+ (?<action>query|reply|config|cached|\/etc\/localhosts\.list)(?:\[A{1,4}\])? (?<domain>[\w\.\-]{2,254}) (?:is|to|from) (?<res>[\w\.:<>]*)$/

  for (key in data) {
    line = data[key];                                      //Get log line
    matches = regexp.exec(line);                           //Run regexp to get matches
    if (matches == null) continue;

    domain = matches.groups.domain;
    serial = Number(matches.groups.serial);
    sys = matches.groups.sys;

    if (matches.groups.action != 'query') {                //Beautify domains on a query response
      domain = beautifyDomain(domain);
    }

    if (matches.groups.action == 'query') {                //Domain Query
      log_date = Date.parse(currentYear + ' ' + matches.groups.log_date);
      //Query contains the fewest records, there so we calculate the ISO formatted date now
      tempQueries.set(serial, {log_date: log_date});
    }

    else if (matches.groups.action == 'reply') {           //Domain Allowed (new response)
      if (tempQueries.has(serial)) {
        if (matches.groups.res == '<CNAME>') {             //CNAME results in another query against the serial number
          addBuffer(serial + 'c', tempQueries.get(serial).log_date, sys, domain, 1, 'cname');
        }
        else {                                             //Answer found, drop the serial number
          addBuffer(serial, tempQueries.get(serial).log_date, sys, domain, 1, 'allowed');
          tempQueries.delete(serial);
        }
      }
    }

    else if (matches.groups.action == 'cached') {          //Domain Allowed (cached)
      if (tempQueries.has(serial)) {
        if (matches.groups.res == '<CNAME>') {             //CNAME results in another query against the serial number
          addBuffer(serial + 'c', tempQueries.get(serial).log_date, sys, domain, 1, 'cname');
        }
        else {                                             //Answer found, drop the serial number
          addBuffer(serial, tempQueries.get(serial).log_date, sys, domain, 1, 'cached');
          tempQueries.delete(serial);
        }
      }
    }

    else if (matches.groups.action == 'config') {          //Domain Blocked by NoTrack
      if (tempQueries.has(serial)) {
        //TODO What blocklist prevented the DNS lookup?
        addBuffer(serial, tempQueries.get(serial).log_date, sys, domain, 2, 'invalid');
        tempQueries.delete(serial);
      }
    }

    else if (matches.groups.action == '/etc/localhosts.list') { //LAN Query
      if (tempQueries.has(serial)) {
        addBuffer(serial, tempQueries.get(serial).log_date, sys, domain, 1, 'local');
        tempQueries.delete(serial);
      }
    }
  }
}


/********************************************************************
 *  Submit Custom Block Form
 *    Fill out hidden items in customBlockForm
 *    Submit the form
 *
 */
function submitCustomBlock(domain, action) {
  document.getElementById('domainItem').value = domain;
  document.getElementById('actionItem').value = action;
  document.getElementById('viewItem').value = action;
  document.getElementById('customBlockForm').submit();
}


/********************************************************************
 *  Toggle Nav Button
 *    Toggle active state of severity button
 *
 *  Params:
 *    Button, Value to increase or decrease severity by
 *  Return:
 *    None
 */
function toggleNavButton(item, value) {
  if (item.classList.contains('active')) {
    searchSeverity -= Number(value);
    item.classList.remove('active');
  }
  else {
    searchSeverity += Number(value);
    item.classList.add('active');
  }
}

/********************************************************************
 *  Load API
 *    Send POST requst to NoTrack API asking for the contents of DNS_LOG
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function loadApi() {
  let xmlhttp = new XMLHttpRequest();
  let url = './include/api.php';
  let params = 'livedns=1';

  xmlhttp.open('POST', url, true);
  xmlhttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
  xmlhttp.onreadystatechange = function() {
    if(this.readyState == 4 && this.status == 200) {
      let apiResponse = JSON.parse(this.responseText);
      readLogData(apiResponse);
    }
  }
  xmlhttp.send(params);
}


/********************************************************************
 *  Valid IP
 *    Regex to check for Valid IPv4 or IPv6
 *  Params:
 *    String to check
 *  Return:
 *    True Valid IP
 *    False Invalid IP
 */
function validIP(str) {
  return /^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$|^\s*((([0-9A-Fa-f]{1,4}:){7}([0-9A-Fa-f]{1,4}|:))|(([0-9A-Fa-f]{1,4}:){6}(:[0-9A-Fa-f]{1,4}|((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){5}(((:[0-9A-Fa-f]{1,4}){1,2})|:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){4}(((:[0-9A-Fa-f]{1,4}){1,3})|((:[0-9A-Fa-f]{1,4})?:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){3}(((:[0-9A-Fa-f]{1,4}){1,4})|((:[0-9A-Fa-f]{1,4}){0,2}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){2}(((:[0-9A-Fa-f]{1,4}){1,5})|((:[0-9A-Fa-f]{1,4}){0,3}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){1}(((:[0-9A-Fa-f]{1,4}){1,6})|((:[0-9A-Fa-f]{1,4}){0,4}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(:(((:[0-9A-Fa-f]{1,4}){1,7})|((:[0-9A-Fa-f]{1,4}){0,5}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:)))(%.+)?$/.test(str);
}


/********************************************************************
 *  Timer
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
setInterval(function() {
  if (throttleApiRequest >= 5) {                           //Throttle loading of DNS_LOG
    loadApi();
    throttleApiRequest = 0;
  }
  if (throttleClean >= 30) {                               //Throttle requestBuffer clean
    cleanBuffer();
    throttleClean = 0;
  }

  if (document.visibilityState == 'visible') {             //Move queue if window is visible
    moveBuffer();
    displayRequests();
  }
  throttleApiRequest++;
  throttleClean++;

}, 2000);


</script>
</body>
</html>
