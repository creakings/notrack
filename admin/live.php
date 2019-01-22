<?php
/*
In progres live view of DNS blocklist
*/
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
  <title>NoTrack - Live</title>
</head>

<body>
<?php
draw_topmenu('Development of Live');
draw_sidemenu();
echo '<div id="main">'.PHP_EOL;

/************************************************
*Constants                                      *
************************************************/

echo '</div>';
?>

<script>
const MAX_LINES = 30;
var displayList = [];
var mainQueue = new Map();
var timePoint = 0;


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
  var dt = new Date(parseInt(t));
  var hr = dt.getHours();
  var m = "0" + dt.getMinutes();
  var s = "0" + dt.getSeconds();
  return hr+ ':' + m.substr(-2) + ':' + s.substr(-2);
}


/********************************************************************
 *  Move Main Queue to Display List
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function moveMainQueue() {
  var i = 0;
  var mainQueueSize = mainQueue.size;
  var target = MAX_LINES;
  var matches = [];

  var regexpkey = /^(\d+)\-(.+)$/                          //Regex to split Key to Time - Request

  if (mainQueueSize == 0) return;
  else if (mainQueueSize < 5) target = 1;                  //Throttle adding of new requests to displayList
  else if (mainQueueSize < 10) target = 2;
  else if (mainQueueSize < 15) target = 3;
  else if (mainQueueSize < 20) target = 4;
  else if (mainQueueSize < 30) target = 8;
  else if (mainQueueSize < 40) target = 12;
  
  if (displayList.length < 10) target = 10;                //If nothingmuch in displayList then add more requests
  
  for (var [key, value] of mainQueue.entries()) {
    matches = regexpkey.exec(key);
    if (matches != null) {
      displayList.push([matches[1], matches[2], value]);   //Add key, value to displayList
      mainQueue.delete(key);                               //Delete key from mainQueue
      timePoint = matches[1];                              //Advance position of DNS_LOG on
      i++;
      if (displayList.length > MAX_LINES) displayList.shift();
      if (i > target) break;
    }
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
  var currentYear = new Date().getFullYear();
  var dedupAnswer = "";
  var dnsRequest = "";
  var dnsResult = "";
  var line = "";
  var logTime = 0;
  var matches = [];
  var queryList = new Map();
  var systemList = new Map();

  var regexp = /(\w{3}\s\s?\d{1,2}\s\d{2}\:\d{2}\:\d{2})\sdnsmasq\[\d{1,6}\]\:\s(query|reply|config|\/etc\/localhosts\.list)(\[[A]{1,4}\])?\s([A-Za-z0-9\.\-]+)\s(is|to|from)\s(.*)$/;

  //TODO hasOwnProperty error condition in data for file not found
  
  for (var key in data) {
    line = data[key];                                      //Get log line
    matches = regexp.exec(line);                           //Run regexp to get matches

    if (matches != null) {
      dnsRequest = matches[4];
      logTime = Date.parse(currentYear + " " + matches[1]);//Get UNIX time of log entry
      if ((matches[2] == "query") && (logTime > timePoint)) {
        if (matches[3] == "[A]") {                         //Only IPv4 to prevent double query entries
          queryList.set(dnsRequest, logTime);              //Log DNS Reqest
          systemList.set(dnsRequest, matches[6]);          //Add Corresponding IP to systemList
        }
      }
      else if ((dnsRequest != dedupAnswer) && (logTime > timePoint)) { 
        dedupAnswer = dnsRequest;                          //Prevent repeat processing of Answer
        if (queryList.has(dnsRequest)) {                   //Does Answer match a Query?
          if (matches[2] == "reply") dnsResult="A";        //Allowed
          else if (matches[2] == "config") dnsResult="B";  //Blocked
          else if (matches[2] == "/etc/localhosts.list") dnsResult="L"; //Local

          //Check if entry exists for Time + Request (assume same request is not made more than once per second)
          if (! mainQueue.has(logTime+"-"+dnsRequest)) {
            //Key = Time + Request, Value = System + Result
            mainQueue.set(logTime+"-"+dnsRequest, systemList.get(dnsRequest) + dnsResult);
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
function simplfyDomain(site) {
  if (/^(www\.)/.test(site)) return site.substr(4);
  return site;
}


/********************************************************************
 *  Display Queue
 *    TODO Beautify
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function displayQueue() {
  var queuesize = displayList.length;
  var div = document.getElementById("main");

  div.innerHTML = "backlog:"+mainQueue.size+"<br>";
  for (i = queuesize - 1; i > 0; i--) {                    //Start with latest first
    div.innerHTML = div.innerHTML + getTime(displayList[i][0]) + "-" + simplfyDomain(displayList[i][1]) + "-" + displayList[i][2] + "<br>";
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
  var xmlhttp = new XMLHttpRequest();
  var url = "./include/api.php";
  var params = "livedns=1";

  xmlhttp.open("POST", url, true);
  xmlhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
  /*xmlhttp.onload = function () {
    // do something to response
    console.log(this.responseText);
  };*/
  xmlhttp.onreadystatechange = function() {
    if(this.readyState == 4 && this.status == 200) {
      var apiResponse = JSON.parse(this.responseText);
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
  var throttleApiRequest = 0;

  if (throttleApiRequest == 0) loadApi();
  moveMainQueue();
  displayQueue();

  throttleApiRequest++
  if (throttleApiRequest > 4) throttleApiRequest = 0;      //Throttle loading of DNS_LOG

}, 2000);

</script>
</body>
</html>
