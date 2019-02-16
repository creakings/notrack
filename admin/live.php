<?php
//1. Draw initial page with PHP. Most of the functionality onwards is done clientside with javascript
//2. Draw blank table cells up to MAX_LINES
//3. Run a timer
//4. Send POST request to API asking for contents of DNS_LOG file
//5. Parse DNS_LOG (in a similar method to ntrk-parse bash script) into requestBuffer map
//5a. Key to requestBuffer is time+dnsquery in order to track progress of DNS_LOG
//5b. timePoint is also used to track progress
//6. Move requestBuffer to requestReady on a number in a limited number of items based on size of requestBuffer
//7. displayRequests shows contents of requestReady in table with cells coloured depending on if
//   DNS request is allowed / blocked / local


require('./include/global-vars.php');
require('./include/global-functions.php');
require('./include/menu.php');

load_config();
ensure_active_session();

//-------------------------------------------------------------------
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <link href="./css/master.css" rel="stylesheet" type="text/css">
  <link rel="icon" type="image/png" href="./favicon.png">
  <script src="./include/config.js"></script>
  <script src="./include/menu.js"></script>
  <meta name="viewport" content="width=device-width, initial-scale=0.8">
  <!--TODO Sort mobile view out    -->
  <title>NoTrack - Live</title>
</head>

<body>
<?php
draw_topmenu('Live');
draw_sidemenu();
echo '<div id="main">'.PHP_EOL;
echo '<div id="menu-lower">'.PHP_EOL;
echo '<input type="text" id="ipaddressbox" value="" placeholder="127.0.0.1">';
echo '<img src="./svg/lmenu_pause.svg" id="pausequeueimg" class="pointer" onclick="pauseQueue()">';
echo '<img src="./svg/lmenu_clear.svg" id="clearqueueimg" class="pointer" onclick="clearQueue()">';
echo '</div>'.PHP_EOL;

echo '<div class="sys-group">'.PHP_EOL;
echo '<table id="livetable">'.PHP_EOL;
echo '</table>'.PHP_EOL;
echo '</div>'.PHP_EOL;
echo '</div>'.PHP_EOL;
?>

<script>

const MAX_LINES = 27;
var paused = false;
var throttleApiRequest = 0;                                //Int used to reduce number of requests for DNS_LOG to be read
var timePoint = 0;                                         //Unix time position in log file
var requestReady = [];                                     //Requests displayed on livetable
var requestBuffer = new Map();                             //Requests read from DNS_LOG waiting to be moved to requestReady

drawTable();
loadApi();
moveBuffer();
displayRequests();
/********************************************************************
 *  Draw Table
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function drawTable() {
  let liveTable = document.getElementById('livetable');
  for (let i = 0; i < MAX_LINES - 1; i++) {
    let row = liveTable.insertRow(i);
    row.insertCell(0);
    row.insertCell(1);
    row.insertCell(2);
    row.insertCell(3);
    liveTable.rows[i].cells[0].innerHTML = '&nbsp;';
  }
}
/********************************************************************
 *  Get Time
 *    Return formatted time: hh:mm:ss
 *  Params:
 *    UNIX Time
 *  Return:
 *    Formatted string of time
 */
function getTime(t)
{
  let dt = new Date(parseInt(t));
  let hr = dt.getHours();
  let m = '0' + dt.getMinutes();
  let s = '0' + dt.getSeconds();
  return hr+ ':' + m.substr(-2) + ':' + s.substr(-2);
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
 *  Move requestBuffer to requestReady
 *    1. This function is called when requestBuffer is ready to be moved to requestReady
 *       (i.e. window visible and not paused by user)
 *    2. Calculate how much of the requestBuffer to move to requestReady
 *    3. Split Time-Request from requestBuffer key
 *    4. Split System-Result from requestBuffer value
 *    5. Add the 4 items as an array to requestReady
 *    6. Advance on the timePoint
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function moveBuffer() {
  let addEntry = true;
  let dnsRequest = "";
  let i = 0;
  let requestBufferSize = requestBuffer.size;
  let target = 0;
  let sys = "";
  let searchIP = '';
  let validSearchIP = false;
  let matches = [];

  var regexpKey = /^(\d+)\-(.+)$/                          //Regex to split Key to Time - Request

  if (paused) return;                                      //Don't move the queue if user has paused

  //Check if user has entered a valid IPv4 or IPv6 in ipaddressbox text box
  searchIP = document.getElementById("ipaddressbox").value.trim();
  validSearchIP = validIP(searchIP);

  if (requestBufferSize == 0) return;                      //Prevent div by zero

  //Target is based on a percentage of the requestBufferSize with no more than 75% of the buffer to be moved
  target = Math.ceil((requestBufferSize / MAX_LINES) * 3.5);
  if (target > MAX_LINES * 0.75) target = Math.ceil(MAX_LINES * 0.75);
  if (requestReady.length < 10) target = 10;               //If nothing much in requestReady then add more requests

  for (let [key, value] of requestBuffer.entries()) {
    matches = regexpKey.exec(key);
    //if (matches != null) { DEPRECATED not needed
      
      //Extract sys and dnsResult from value (127.0.0.1A / 127.0.0.1B)
      sys = value.substr(0, value.length-1);
      dnsResult = value.substr(-1);

      if (validSearchIP) {                                 //Are we looking for a specific IP?
        (sys == searchIP) ? addEntry = true : addEntry = false;
      }

      if (addEntry) {
        requestReady.push([matches[1], matches[2], sys, dnsResult]);
        i++;
      }
      /*else {
        console.log(matches[2] + sys);                     //Uncomment for debugging search
      }*/
      requestBuffer.delete(key);                           //Delete key from requestBuffer
      timePoint = matches[1];                              //Advance position of DNS_LOG on
      if (requestReady.length > MAX_LINES) requestReady.shift(); //Delete trailing lines
      if (i >= target) break;
    //}
    addEntry = true;
  }
}


/********************************************************************
 * Clear Queue
 *    Activated when user clicks Clear button
 *    1. Delete all values from requestReady and requestBuffer
 *    2. Clear all values from liveTable
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function clearQueue() {
  let liveTable = document.getElementById('livetable');

  requestReady.splice(0,requestReady.length);
  requestBuffer.clear();

  //Remove all values from the table
  for (let i = 0; i < MAX_LINES - 1; i++) {
    liveTable.rows[i].className = '';
    liveTable.rows[i].cells[0].innerHTML = '&nbsp;';
    liveTable.rows[i].cells[1].innerHTML = '&nbsp;';
    liveTable.rows[i].cells[2].innerHTML = '&nbsp;';
    liveTable.rows[i].cells[3].innerHTML = '&nbsp;';
  }
}


/********************************************************************
 * Pause Queue
 *    Activated when user clicks Pause / Play button
 *  Params:
 *    None
 *  Return:
 *    None
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
 *  Read Log Data
 *    Parse JSON output from DNS_LOG into requestBuffer
 *    DNS_LOG gets new data added to the end of file, but is flushed after ntrk-parse is run
 *    Track progress point with timePoint
 *
 *    regexp:
 *      Group 1. Month DD HH:MM:SS
 *      Non.     dnsmasq[pid]:
 *      Group 2. query|reply|config|\etc\localhosts.list
 *      Group 3. A or AAAA
 *      Group 4. is|to|from
 *      Group 5. IP
 *
 *  Params:
 *    JSON Data
 *  Return:
 *    None
 */
function readLogData(data) {
  let currentYear = new Date().getFullYear();
  let dedupAnswer = '';
  let dnsRequest = '';
  let dnsResult = '';
  let line = '';
  let logTime = 0;
  let matches = [];
  let queryList = new Map();
  let systemList = new Map();

  var regexp = /(\w{3}\s\s?\d{1,2}\s\d{2}\:\d{2}\:\d{2})\sdnsmasq\[\d{1,6}\]\:\s(query|reply|cached|config|\/etc\/localhosts\.list)(\[[A]{1,4}\])?\s([A-Za-z0-9\.\-]+)\s(is|to|from)\s(.*)$/;

  //TODO hasOwnProperty error condition in data for file not found

  for (var key in data) {
    line = data[key];                                      //Get log line
    matches = regexp.exec(line);                           //Run regexp to get matches

    if (matches != null) {
      dnsRequest = matches[4];
      logTime = Date.parse(currentYear + ' ' + matches[1]);//Get UNIX time of log entry
      if ((matches[2] == 'query') && (logTime > timePoint)) {
        if (matches[3] == '[A]') {                         //Only IPv4 to prevent double query entries
          queryList.set(dnsRequest, logTime);              //Log DNS Reqest
          systemList.set(dnsRequest, matches[6]);          //Add Corresponding IP to systemList
        }
      }
      else if ((dnsRequest != dedupAnswer) && (logTime > timePoint)) {
        dedupAnswer = dnsRequest;                          //Prevent repeat processing of Answer
        if (queryList.has(dnsRequest)) {                   //Does Answer match a Query?
          if (matches[2] == 'reply') dnsResult='A';        //Allowed
          else if (matches[2] == 'config') dnsResult='B';  //Blocked
          else if (matches[2] == 'cached') dnsResult='C';  //Cached
          else if (matches[2] == '/etc/localhosts.list') dnsResult='L'; //Local

          //Check if entry exists for Time + Request (assume same request is not made more than once per second)
          if (! requestBuffer.has(logTime+'-'+dnsRequest)) {
            //Key = Time + Request, Value = System + Result
            requestBuffer.set(logTime+'-'+dnsRequest, systemList.get(dnsRequest) + dnsResult);
          }

          queryList.delete(dnsRequest);                    //Delete value from queryList
          systemList.delete(dnsRequest);                   //Delete value from system list
        }
      }
    }
  }
}


/********************************************************************
 *  Simplfy Domain
 *    Drop www. from beginning of DNS Request
 *
 *  Params:
 *    DNS Reqest
 *  Return:
 *    Formatted DNS Reqest
 */
function simplifyDomain(site) {
  if (/^(www\.)/.test(site)) return site.substr(4);
  return site;
}


/********************************************************************
 *  Display Queue
 *    Show the results from requestReady array in livetable
 *    Array is displayed from end-to-start (latest-to-earliest)
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function displayRequests() {
  let queuesize = requestReady.length;
  let liveTable = document.getElementById('livetable');
  let currentRow = 0;

  for (let i = queuesize - 1; i > 0; i--) {                //Start with latest first
    if (requestReady[i][3] == 'A') {
      liveTable.rows[currentRow].className = '';
      liveTable.rows[currentRow].cells[2].innerHTML = 'Ok (Forwarded)';
    }
    else if (requestReady[i][3] == 'B') {
      liveTable.rows[currentRow].className = 'blocked';
      liveTable.rows[currentRow].cells[2].innerHTML = 'Blocked';
    }
    else if (requestReady[i][3] == 'C') {
      liveTable.rows[currentRow].className = '';
      liveTable.rows[currentRow].cells[2].innerHTML = 'Ok (Cached)';
    }
    else if (requestReady[i][3] == 'L') {
      liveTable.rows[currentRow].className = 'local';
      liveTable.rows[currentRow].cells[2].innerHTML = 'Local';
    }

    liveTable.rows[currentRow].cells[0].innerHTML = getTime(requestReady[i][0]);
    liveTable.rows[currentRow].cells[1].innerHTML = simplifyDomain(requestReady[i][1]);
    liveTable.rows[currentRow].cells[3].innerHTML = requestReady[i][2];
    currentRow++;
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
  /*xmlhttp.onload = function () {
    // do something to response
    console.log(this.responseText);
  };*/
  xmlhttp.onreadystatechange = function() {
    if(this.readyState == 4 && this.status == 200) {
      let apiResponse = JSON.parse(this.responseText);
      readLogData(apiResponse);
    }
  }
  xmlhttp.send(params);
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
  if (throttleApiRequest >= 4) {                           //Throttle loading of DNS_LOG
    loadApi();
    throttleApiRequest = 0;
  }

  if (document.visibilityState == 'visible') {             //Move queue if window is visible
    moveBuffer();
    displayRequests();
  }
  throttleApiRequest++

}, 1800);


</script>
</body>
</html>
