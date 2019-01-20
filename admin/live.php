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
var displaylist = new Map();
var timepoint = 0;

function readLogArray(data) {
  var line = "";
  var dedup_answer = "";
  var url = "";
  
  var matches = [];
  var querylist = new Map();
  var systemlist = new Map();
  var dns_result = "";
  var currentYear = new Date().getFullYear();
  var log_time = 0;
  
  var regexp = /(\w{3}\s\s?\d{1,2}\s\d{2}\:\d{2}\:\d{2})\sdnsmasq\[\d{1,6}\]\:\s(query|reply|config|\/etc\/localhosts\.list)(\[[A]{1,4}\])?\s([A-Za-z0-9\.\-]+)\s(is|to|from)\s(.*)$/;
  
  //TODO hasOwnProperty error condition in data for file not found
  for (var key in data) {
    line = data[key];
    
    
    //div.innerHTML = data[key] + "<br>" + div.innerHTML;
    matches = regexp.exec(line);
    if (matches != null) {
      url = matches[4];
      //div.innerHTML = matches[3] + "<br>" + div.innerHTML;      
      console.log(matches[1]);
      log_time = Date.parse(currentYear + " " + matches[1]);
      if ((matches[2] == "query") && (log_time > timepoint)) {
        if (matches[3] == "[A]") { //             #Only IPv4 to prevent double query entries
          querylist.set(url, log_time);
          systemlist.set(url, matches[6]);                 //Add IP to system array
        }
      }
      else if ((url != dedup_answer) && (log_time > timepoint)) { //             #Simplify processing of multiple IP addresses returned
        dedup_answer = url;                                 //Deduplicate answer
        if (querylist.has(url)) {                 //#Does answer match a query?
          if (matches[2] == "reply") dns_result="A";    //#Allowed
          else if (matches[2] == "config") dns_result="B"; //#Blocked
          else if (matches[2] == "/etc/localhosts.list") dns_result="L";
          
          if (! displaylist.has(log_time+"-"+url)) {
            displaylist.set(log_time+"-"+url, systemlist.get(url) + dns_result);
          }
          //simplify_url "$url"                              #Simplify with commonsites
          //TODO simpleurl
          //div.innerHTML = url + " - " + dns_result + "<br>" + div.innerHTML;
          //if [[ $simpleurl != "" ]]; then                  #Add row into SQL Table
//            echo "INSERT INTO dnslog (id,log_time,sys,dns_request,dns_result) VALUES ('null','${querylist[$url]}', '${systemlist[$url]}', '$simpleurl', '$dns_result')" | mysql --user="$USER" --password="$PASSWORD" -D "$DBNAME"
          //fi

          querylist.delete(url); //                            #Delete value from querylist
          systemlist.delete(url); //                          #Delete value from system list
        }
      }    
    }
    
  }
  
}

function displayLogArray() {
  var matches = [];

  var regexpkey = /^(\d+)\-(.+)$/
  var div = document.getElementById("main");
  
  for (var [key, value] of displaylist.entries()) {
    matches = regexpkey.exec(key);
    if (matches != null) {
      timepoint = matches[1];
      div.innerHTML = key + value + "<br>" + div.innerHTML;
      displaylist.delete(key);
    }
  }
  
}


setInterval(function(){
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
      readLogArray(apiResponse);
      displayLogArray();
      //console.log(this.responseText);
    }
  }
  xmlhttp.send(params);
}, 3000);

</script>
</body>
</html>
