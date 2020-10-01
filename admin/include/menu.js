/********************************************************************
 *  Change Status
 *    Update pause-button and menu-side-blocking based on new status value
 *    TODO Home-Nav box to update
 *
 *  Params:
 *    New Status (output from API $config->status)
 *  Return:
 *    None
 */
function changeStatus(newstatus) {
  const STATUS_ENABLED = 1;
  const STATUS_DISABLED = 2;
  const STATUS_PAUSED = 4;

  if (newstatus & STATUS_ENABLED) {
    document.getElementById("pause-button").src = '/admin/svg/tmenu_pause.svg';
    document.getElementById("pause-button").title = "Disable Blocking";
    document.getElementById("menu-side-blocking").innerHTML = "<img src='/admin/svg/status_green.svg' alt=''>Blocking: Enabled";
  }

  else if (newstatus & STATUS_DISABLED) {
    document.getElementById("pause-button").src = '/admin/svg/tmenu_play.svg';
    document.getElementById("pause-button").title = "Enable Blocking";
    document.getElementById("menu-side-blocking").innerHTML = "<img src='/admin/svg/status_red.svg' alt=''>Blocking: Disabled";
  }

  else if (newstatus & STATUS_PAUSED) {
    document.getElementById("pause-button").src = '/admin/svg/tmenu_play.svg';
    document.getElementById("pause-button").title = "Enable Blocking";
  }
  document.getElementById("dropbutton").blur();
}

/********************************************************************
 *  Menu incognito
 *    POST Incognito operation to API
 *    API returns new status value
 *    Set incognito elements to red colour
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */

function menuIncognito() {
  var oReq = new XMLHttpRequest();
  var url = "/admin/include/api.php";
  var params = "operation=incognito";

  oReq.open("POST", url, true);
  oReq.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

  oReq.onreadystatechange = function() {
    if(this.readyState == 4 && this.status == 200) {
      //console.log(this.responseText);
      var apiResponse = JSON.parse(this.responseText);
      if (apiResponse["status"] & 8) {                     //Bitwise check for STATUS_INCOGNITO
        //Change incognito picture to red colour
        document.getElementById("incognito-button").src = "/admin/svg/menu_incognito_active.svg";
      }
      else {
        //Turning incognito off, change picture back to grey
        document.getElementById("incognito-button").src = "/admin/svg/menu_incognito.svg";
      }
    }
  }
  oReq.send(params);
}


/********************************************************************
 *  Enable NoTrack
 *    POST enable operation to API
 *    API returns new status value
 *    This is the same function for Enable and Disable.
 *    Let the API work out what the request is meant to be.
 *  Params:
 *    None
 *  Return:
 *    None
 */

function enableNoTrack() {
  var oReq = new XMLHttpRequest();
  var url = "/admin/include/api.php";
  var params = "operation=enable";

  oReq.open("POST", url, true);
  oReq.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

  oReq.onreadystatechange = function() {
    if(this.readyState == 4 && this.status == 200) {
      //console.log(this.responseText);
      var apiResponse = JSON.parse(this.responseText);
      changeStatus(apiResponse["status"]);
    }
  }
  oReq.send(params);
}


/********************************************************************
 *  Enable NoTrack
 *    POST enable operation to API
 *    API returns new status value
 *
 *  Params:
 *    mins to pause for
 *  Return:
 *    None
 */

function pauseNoTrack(mins) {
  var oReq = new XMLHttpRequest();
  var url = "/admin/include/api.php";
  var params = "operation=pause&mins="+mins;

  oReq.open("POST", url, true);
  oReq.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

  oReq.onreadystatechange = function() {
    if(this.readyState == 4 && this.status == 200) {
      var apiResponse = JSON.parse(this.responseText);
      changeStatus(apiResponse["status"]);
      document.getElementById("menu-side-blocking").innerHTML = "<img src='/admin/svg/status_yellow.svg' alt=''>Blocking: Paused - " + apiResponse["unpausetime"];
    }
  }
  oReq.send(params);
}


/********************************************************************
 *  Update Blocklists
 *    DEPRECATED
 *  Params:
 *    None
 *  Return:
 *    None
 */

/*function updateBlocklist() {
  var oReq = new XMLHttpRequest();
  var url = "/admin/include/api.php";
  var params = "operation=updateblocklist";

  oReq.open("POST", url, true);
  oReq.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

  oReq.onreadystatechange = function() {
    if(this.readyState == 4 && this.status == 200) {
      var apiResponse = JSON.parse(this.responseText);
      document.getElementById("menu-top-logo").textContent = "Updating Blocklists";
    }
  }
  oReq.send(params);

  hideOptions();
}
*/


/********************************************************************
 *  Open Side Menu
 *    Check width of #menu-side
 *    Expand to 14rem if zero
 *    Reduce to zero for any other size
 *  Params:
 *    None
 *  Return:
 *    None
 */

function openNav() {
  if (document.getElementById("menu-side").style.width == "0rem" || document.getElementById("menu-side").style.width == "") {
    document.getElementById("menu-side").style.width = "14rem";
    document.getElementById("main").style.marginLeft = "14rem";
  }
  else {
    document.getElementById("menu-side").style.width = "0rem";
    document.getElementById("main").style.marginLeft= "0rem";
  }
}
