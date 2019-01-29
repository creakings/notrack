<?php
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
  <meta name="viewport" content="width=device-width, initial-scale=0.5">
  <!--TODO Sort mobile view out    -->
  <title>NoTrack - Live</title>
</head>

<body>
<?php
draw_topmenu('Live');
draw_sidemenu();
echo '<div id="main">'.PHP_EOL;
echo '<div id="menu-lower">'.PHP_EOL;
echo '<img src="./svg/lmenu_pause.svg" id="pausequeueimg" class="pointer" onclick="pauseQueue()">';
echo '<img src="./svg/lmenu_clear.svg" id="clearqueueimg" class="pointer" onclick="clearQueue()">';
echo '<div id="temp"></div>'.PHP_EOL;
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
var displayList = [];                                      //Requests displayed on livetable
var mainQueue = new Map();                                 //Requests read from DNS_LOG waiting to be moved to displayList

drawTable();
loadApi();
moveMainQueue();
displayQueue();
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
 *  Move Main Queue to Display List
 *    1. Calculate how much of the mainQueue to move to displayList
 *    2. Split Time-Request from mainQueue key
 *    3. Split System-Result from mainQueue value
 *    4. Add the 4 items as an array to displayList
 *    5. Advance on the timePoint
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function moveMainQueue() {
  let i = 0;
  let mainQueueSize = mainQueue.size;
  let target = 0;
  let matches = [];

  var regexpKey = /^(\d+)\-(.+)$/                          //Regex to split Key to Time - Request

  if (paused) return;

  if (mainQueueSize == 0) return;
  target = Math.ceil((mainQueueSize / MAX_LINES) * 3.5);   //Target is based on a percentage of the mainQueueSize
  if (target > MAX_LINES * 0.75) target = Math.ceil(MAX_LINES * 0.75);  //No more than 75% to be moved
  if (displayList.length < 10) target = 10;                //If nothing much in displayList then add more requests

  //console.log(target);                                   //Uncomment for debugging

  for (let [key, value] of mainQueue.entries()) {
    matches = regexpKey.exec(key);
    if (matches != null) {
      //Add key, value, system, result to displayList
      displayList.push([matches[1], matches[2], value.substr(0, value.length-1), value.substr(-1)]);

      mainQueue.delete(key);                               //Delete key from mainQueue
      timePoint = matches[1];                              //Advance position of DNS_LOG on
      i++;
      if (displayList.length > MAX_LINES) displayList.shift();
      if (i >= target) break;
    }
  }
}


/********************************************************************
 * Clear Queue
 *    Activated when user clicks Clear button
 *    1. Delete all values from displayList and mainQueue
 *    2. Clear all values from liveTable
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function clearQueue() {
  let liveTable = document.getElementById('livetable');

  displayList.splice(0,displayList.length);
  mainQueue.clear();

  //Remove all values from the table
  for (let i = 0; i < MAX_LINES - 1; i++) {
    liveTable.rows[i].cells[0].innerHTML = '&nbsp;';
    liveTable.rows[i].cells[1].innerHTML = '&nbsp;';
    liveTable.rows[i].cells[2].innerHTML = '&nbsp;';
    liveTable.rows[i].cells[1].className = '';
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
 *    Parse JSON output from DNS_LOG into mainQueue
 *    DNS_LOG gets new data added to the end of file, but is flushed after ntrk-parse is run
 *    Track progress point with timePoint
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

  var regexp = /(\w{3}\s\s?\d{1,2}\s\d{2}\:\d{2}\:\d{2})\sdnsmasq\[\d{1,6}\]\:\s(query|reply|config|\/etc\/localhosts\.list)(\[[A]{1,4}\])?\s([A-Za-z0-9\.\-]+)\s(is|to|from)\s(.*)$/;

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
          else if (matches[2] == '/etc/localhosts.list') dnsResult='L'; //Local

          //Check if entry exists for Time + Request (assume same request is not made more than once per second)
          if (! mainQueue.has(logTime+'-'+dnsRequest)) {
            //Key = Time + Request, Value = System + Result
            mainQueue.set(logTime+'-'+dnsRequest, systemList.get(dnsRequest) + dnsResult);
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
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function displayQueue() {
  let queuesize = displayList.length;
  let div = document.getElementById('temp');
  let liveTable = document.getElementById('livetable');
  let currentRow = 0;

  div.innerHTML = 'backlog:' + mainQueue.size + '<br>';
  for (let i = queuesize - 1; i > 0; i--) {                //Start with latest first
    if (displayList[i][3] == 'A') {
      liveTable.rows[currentRow].cells[1].className = '';
    }
    else if (displayList[i][3] == 'B') {
      liveTable.rows[currentRow].cells[1].className = 'blocked';
    }
    else if (displayList[i][3] == 'L') {
      liveTable.rows[currentRow].cells[1].className = 'local';
    }

    liveTable.rows[currentRow].cells[0].innerHTML = getTime(displayList[i][0]);
    liveTable.rows[currentRow].cells[1].innerHTML = simplifyDomain(displayList[i][1]);
    liveTable.rows[currentRow].cells[2].innerHTML = displayList[i][2];
    currentRow++;
  }
}


/********************************************************************
 *  Load API
 *    Send requst to NoTrack API requesting the contents of DNS_LOG
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

  if (document.visibilityState == 'visible') {             //Only display if window is visible
    moveMainQueue();
    displayQueue();
  }
  throttleApiRequest++

}, 2000);


</script>
</body>
</html>
