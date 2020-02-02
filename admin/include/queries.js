/********************************************************************
 *  Reset queries form DEPRECATED
 *    Reset elements in queries form to their default values
 *  Params:
 *    None
 *  Return:
 *    None
 */
/*function resetQueriesForm() {
  document.getElementById('filtersearch').value = '';
  document.getElementById('filtersys').value = '';
  document.getElementById('filtertime').value = '1 DAY';
  document.getElementById('filtertype').value = 'all';
  document.getElementById('filtergroup').value = 'name';
}*/


/********************************************************************
 *  Get Search Image
 *    Returns a link string of apropriate button based on users search engine choice
 *  Params:
 *    domain
 *  Return:
 *    Link string
 */
function getSearchImage(domain) {
  let imageName = '';

  switch(SEARCHNAME) {
    case 'DuckDuckGo':
      imageName = 'duckduckgo';
      break;
    case 'Google':
      imageName = 'google';
      break;
    default:
      imageName = 'search';
  }

  return '<a href="'+SEARCHURL+domain+'" target="_blank"><img src="./svg/search/'+imageName+'.svg" onmouseover="this.src=\'./svg/search/'+imageName+'_over.svg\'" onmouseout="this.src=\'./svg/search/'+imageName+'.svg\'"></a>';
}


/********************************************************************
 *  Is IP Address
 *    Checks for IPv4 address
 *  Params:
 *    ipaddress
 *  Return:
 *    true if IPv4 address
 *    false if not
 */
function isIPaddress(ipaddress) {
  return /^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/.test(ipaddress);
}

/********************************************************************
 *  Is Common Domain
 *    Checks to see if Domain starts with asterisk
 *  Params:
 *    Domain
 *  Return:
 *    true when domain starts with *.
 *    false if it doesn't
 */
function isCommonDomain(domain) {
  return /^\*\./.test(domain);
}

/********************************************************************
 *  Is Valid Domain
 *    Checks to see if Domain contains format of site.com
 *  Params:
 *    Domain
 *  Return:
 *    true when domain is valid
 *    false invalid domain
 */
function isValidDomain(domain) {
  return /^[\w\-_\.]+[\w\-_]+$/.test(domain);
}


/********************************************************************
 *  Simplify Domain Title
 *    Limit the length of a domain in queries-box title
 *    1. Check if length is under MAXDOMAINLEN with a bit of extra leeway (don't want to dot if not much of a gain)
 *    2. Extract domain and get length
 *    3. Check if the focus should be at reducing the overall domain or just subdomain
 *
 *  Params:
 *    Domain
 *  Return:
 *    Simplified domian for queries-box title
 */
function simplfyDomainTitle(site) {
  const MAXDOMAINLEN = 34;
  let sitelen = site.length;
  let availchars = 0
  let subdomain = '';

  if (sitelen <= (MAXDOMAINLEN + 2)) return site;          //Some extra leeway

  let domain = site.match(/[\w\-_]+\.(org\.|co\.|com\.|gov\.)?[\w\-_]+$/)[0];
  let domainlen = domain.length;

  if (domainlen < (MAXDOMAINLEN - 2)) {
    subdomain = site.substring(1, (sitelen - domainlen));
    availchars = Math.round((MAXDOMAINLEN - domainlen) / 2);
    return site.substring(0, availchars)+'…'+site.substring(sitelen - (domainlen + availchars));
  }
  else {
    availchars = MAXDOMAINLEN / 2;
    return site.substring(0, (availchars - 3))+'…'+site.substring((sitelen - 3) - availchars);
  }
}

/********************************************************************
 *  Set Clipboard Data
 *    Fills in supplied data into clipboard
 *    Show popup copymsg for 3 seconds
 *    Hide copymsg
 *
 *  Params:
 *    Domain
 *  Return:
 *    None
 */

function setClipboard(domain) {
  function handler (event){
    event.clipboardData.setData('text/plain', domain);
    event.preventDefault();
    document.removeEventListener('copy', handler, true);
  }

  document.addEventListener('copy', handler, true);
  document.execCommand('copy');

  //Show copymsg element
  document.getElementById('copymsg').style.display = 'block';

  //Delay for 3 seconds, then Hide  copymsg element
  setTimeout(function(){
    document.getElementById('copymsg').style.display = 'none';
  },3000);
}

/********************************************************************
 *  Set Span Contents
 *    Shorthand method of writing a Value to Element, and then set Display to Block
 *    If Value is blank, then set Element Display to None
 *
 *  Params:
 *    Element ID, Value
 *  Return:
 *    None
 */
function setSpanContents(elementId, value) {
  if (value == '') {
    document.getElementById(elementId).style.display = 'none';
    document.getElementById(elementId).innerHTML = '';
  }
  else {
    document.getElementById(elementId).style.display = 'block';
    document.getElementById(elementId).innerHTML = value;
  }
}

/********************************************************************
 *  Report Site
 *    Fill in elements in queries-box
 *
 *  Params:
 *    site name, if blocked (true = blocked, false = allowed), show report button
 *  Return:
 *    None
 */
function reportSite(site, blocked, showreport) {
  let action = '';                                         //Black or White
  let domain = '';
  let msg = '';                                            //Message to show user
  let investigate = '';                                    //Investigate Button
  let item1 = '';                                          //Block button and message
  let item2 = '';                                          //Block button and message with subdomain
  let report = '';                                         //Report to quidsup.net
  let search = '';                                         //Search Button

  //Set investigate link now and replace with whois link later
  investigate = '<button name="site" value='+site+' type="submit">View Details</button><span>View domain details in NoTrack Investigate</span>';

  if (isCommonDomain(site)) {                              //Is it a *common site?
    msg = '<p>Domains starting with * are known to utilise a large number of subdomains</p>';
    site = site.substring(2);                              //Drop *. from the string
  }
  else if (! isValidDomain(site)) {
    msg = '<p>Invalid site</p>';
  }
  else if (/(\.akamai\.net|akamaiedge\.net)$/.test(site)) { //Is it an Akami site?
    msg = '<p>Akami is a Content Delivery Network (CDN) providing media delivery for a wide range of websites.</p><p>It is more efficient to block the originating website, rather than an Akami subdomain.</p>';
  }
  else if (isIPaddress(site)) {                            //Is it an IP Address
    msg = '<p>Unable to Block IP addresses.<br>You could add it to your Firewall instead</p>';
  }

  else {                                                   //Valid site to block / allow
    //Is domain a single domain with optional double-barrelled tld?
    if (/^[\w\-_]+\.(org\.|co\.|com\.|gov\.)?[\w\-_]+$/.test(site)) {
      if (blocked) {
        item1 = '<button name="site" value='+site+' type="submit">Whitelist Domain</button><span>Add domain to your White List</span>';
        action = 'white';
      }
      else {
        item1 = '<button name="site" value='+site+' type="submit">Block Domain</button><span>Add domain to your Black List</span>';
        action = 'black';
      }
    }
    else {                                                 //No, it has one or more sub-domains
      domain = site.match(/[\w\-_]+\.(org\.|co\.|com\.|gov\.)?[\w\-_]+$/)[0];     //Extract domain with optional double-barrelled tld
      if (blocked) {
        item1 = '<button name="site" value='+domain+' type="submit">Whitelist Domain</button><span>Whitelist entire domain</span>';
        item2 = '<button name="site" value='+site+' type="submit">Whitelist  Subdomain</button><span>Add subdomain to your White List</span>';
        action = 'white';
      }
      else {
        item1 = '<button name="site" value='+domain+' type="submit">Block Domain</button><span>Block entire domain</span>';
        item2 = '<button name="site" value='+site+' type="submit">Block Subdomain</button><span>Add subdomain to your Black List</span>';
        action = 'black';
      }
    }

    if (showreport) {                                      //Show report button (for NoTrack blocked sites)
      if (blocked) {                                       //Remove blocked site
        report = 'remove--'+site;
      }
      else {                                               //Report site for blocking
        report = site;
      }
    }
  }
  
  search = getSearchImage(site);
  search += '<a href="https://www.virustotal.com/en/domain/'+site+'/information/" target="_blank"><img src="./svg/search/virustotal.svg" onmouseover="this.src=\'./svg/search/virustotal_over.svg\'" onmouseout="this.src=\'./svg/search/virustotal.svg\'"></a>';

  //Whois or NoTrack Investigate button depending on whether user has an API
  //Some whois providers require the domain to be provided as an argument
  //The 1st regex test checks if the WHOISURL ends in ?argument=
  //The 2nd regex splits WHOISURL into (whois.com)?(argument)=
  //These groups can then be used on the name, value of investigate button
  //Alternate option is whois.com/site, this can be added to formaction without name, value
  if (WHOISAPI == 0) {
    if (/\?\w+=$/.test(WHOISURL)) {
      let matches = WHOISURL.match(/^([^\?]+)\?([\w]+)=$/);
      investigate = '<button name="'+matches[2]+'" value='+site+' type="submit" formaction="'+matches[1]+'">Whois Details</button><span>View whois details on '+WHOISNAME+'</span>';
    }
    else {
      investigate = '<button type="submit" formaction="'+WHOISURL+site+'">Whois Details</button><span>View whois details on '+WHOISNAME+'</span>';
    }
  }

  //Modify DOM elements depending on whether a string has been set.
  document.getElementById('sitename').innerText = simplfyDomainTitle(site);
  setSpanContents('reportmsg', msg);
  setSpanContents('searchitem', search)
  setSpanContents('invitem', investigate)
  setSpanContents('reportitem1', item1);
  setSpanContents('reportitem2', item2);


  document.getElementById('reportv').value = action;
  document.getElementById('reportaction').value = action;

  if (report == '') {
    document.getElementById('reportitem3').style.display = 'none';
  }
  else {
    document.getElementById('reportitem3').style.display = 'block';
    document.getElementById('siterep').value = report;
  }

  //Display fade and queries-box
  document.getElementById('fade').style.display = 'block';
  document.getElementById('queries-box').style.display = 'block';
}


/********************************************************************
 *  Hide Queries Box
 *    Hides queries-box, and fade elements
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function hideQueriesBox() {
  document.getElementById('queries-box').style.display = 'none';
  document.getElementById('fade').style.display = 'none';
}


/********************************************************************
 *  Scroll To Bottom
 *    Moves scrollbar when user clicks button-scroll
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function scrollToBottom() {
  window.scrollTo(0, document.body.scrollHeight);
  //Animated http://jsfiddle.net/forestrf/tPQSv/2/
}


/********************************************************************
 *  Scroll To Top
 *    Moves scrollbar when user clicks button-scroll
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function scrollToTop() {
  window.scrollTo(0, 0);
}


/********************************************************************
 *  Scroll Function
 *    Show Scroll button depending on certain conditions:
 *    1: Under 100 pixels from Top - None
 *    2: Over 100 pixels from Top and Under 60% - Scroll Down
 *    3: Over 60% - Scroll Up
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
window.onscroll = function() {                             //OnScroll Event
  let y = document.body.scrollHeight / 10;

  if (window.pageYOffset > 100 && window.pageYOffset < y * 6) {
    document.getElementById('scrollup').style.display = 'none';
    document.getElementById('scrolldown').style.display = 'block';
  }
  else if (window.pageYOffset >= (y * 6)) {
    document.getElementById('scrollup').style.display = 'block';
    document.getElementById('scrolldown').style.display = 'none';
  }
  else {
    document.getElementById('scrollup').style.display = 'none';
    document.getElementById('scrolldown').style.display = 'none';
  }
}


/********************************************************************
 *  Format Date
 *    Return three character month name and day from a timedate string:
 *     YYYY-MM-DDThh:mm
 *    Although hh:mm is not necessarily required
 *
 *  Params:
 *    timedate string
 *  Return:
 *    Formatted date string
 */
function formatDate(dateStr) {
  let shortDay = '';
  let shortMonth = '';

  let options = {
    month: 'short'
  };

  let tempDate= new Date(dateStr);

  //Get 3 letter month from Intl DateTimeFormat
  shortMonth = new Intl.DateTimeFormat('default', options).format(tempDate);

  //Extract the first group match out of regex YYYY-MM-(DD)
  shortDay = dateStr.match(/\d{4}\-\d{2}\-(\d{2})/)[1];

  return shortDay + " " + shortMonth;
}


/********************************************************************
 *  Select Time Preset from timepicker menu
 *    Fills in timepicker-text from li text value
 *    Fills in dateTime value from supplied timeValue
 *
 *  Params:
 *    item - The li which called this function
 *    timeValue - string to supply for dateTime value
 *  Return:
 *    None
 */
function selectTime(item, timeValue) {
  document.getElementById('timepicker-text').value = item.innerText;
  document.getElementById('dateTime').value = timeValue;

  document.getElementById('timepicker-dropdown').blur();
  document.getElementById('timepicker-group').blur();
}


/********************************************************************
 *  Select Date from timepicker menu
 *    Fills in timepicker-text with formatDate value of start and end dates
 *    Fills in dateTime value with selected dates and  with added time string 00:00:00 to 23:59:59
 *    1. Check if startDate is greater than endDate - Swap around if necessary
 *    2. Fill in timepicker-text with formatDate
 *    3. Fill in dateTime with startDateT00:00:00/endDateT23:59:59
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function selectDate() {
  let startDate = document.getElementById('timepicker-date-start').value;
  let endDate = document.getElementById('timepicker-date-end').value;

  if (startDate == '') return;
  if (endDate == '') return;

  //Check if startDate in Unix time is greater than endDate in Unix time
  if (Date.parse(startDate) > Date.parse(endDate)) {
     [startDate, endDate] = [endDate, startDate]           //Swap the values
  }

  document.getElementById('timepicker-text').value = formatDate(startDate) + ' to ' + formatDate(endDate);
  document.getElementById('dateTime').value = startDate + 'T00:00:00/' + endDate + 'T23:59:59';

  document.getElementById('timepicker-dropdown').blur();
  document.getElementById('timepicker-group').blur();

}


/********************************************************************
 *  Select Time & Date from timepicker menu
 *    Fills in timepicker-text with formatDate value of start and end dates & times
 *    Fills in dateTime value with selected dates and  with added seconds 00 to 59
 *    1. Check if startDate + startTime is greater than endDate + endTime - Swap around if necessary
 *    2. Fill in timepicker-text with formatDate
 *    3. Fill in dateTime with startDateTstartTime:00/endDateTendTime:59
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function selectTimeDate() {
  let startDate = document.getElementById('timepicker-tddate-start').value;
  let endDate = document.getElementById('timepicker-tddate-end').value;
  let startTime = document.getElementById('timepicker-tdtime-start').value;
  let endTime = document.getElementById('timepicker-tdtime-end').value;

  if (startDate == '') return;
  if (endDate == '') return;
  if (startTime == '') return;
  if (endTime == '') return;

  startDate += "T" + startTime;
  endDate += "T" + endTime;

  //Check if startDate in Unix time is greater than endDate in Unix time
  if (Date.parse(startDate) > Date.parse(endDate)) {
     [startDate, endDate] = [endDate, startDate]           //Swap the values
  }

  document.getElementById('timepicker-text').value = formatDate(startDate) + ' ' + startTime + ' to ' + formatDate(endDate) + ' ' + endTime;

  //Need to add the seconds
  document.getElementById('dateTime').value = startDate + ':00/' + endDate + ':00';

  document.getElementById('timepicker-dropdown').blur();
  document.getElementById('timepicker-group').blur();
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
  let severity = document.getElementById('severity');
  let severityInt = Number(severity.value);

  //console.log("Before" + item.classList);
  //console.log(severity.value);
  if (item.classList.contains('active')) {
    severityInt -= Number(value);
    item.classList.remove('active');
  }
  else {
    severityInt += Number(value);
    item.classList.add('active');
  }

  severity.value = severityInt.toString();

  //console.log("After" + item.classList);
  //console.log(severity.value);
}