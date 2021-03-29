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
 *  Simplify Domain
 *    Limit the length of a domain in dialog boxes
 *    1. Check if length is under MAXDOMAINLEN with a bit of extra leeway (don't want to dot if not much of a gain)
 *    2. Extract domain and get length
 *    3. Check if the focus should be at reducing the overall domain or just subdomain
 *
 *  Params:
 *    Subdomain or domain
 *  Return:
 *    Simplified domain
 */
function simplifyDomain(subDomain) {
  const MAXDOMAINLEN = 34;
  let subDomainLen = subDomain.length;
  let availChars = 0

  if (subDomainLen <= (MAXDOMAINLEN + 2)) {                          //Some extra leeway
    return subDomain+'<wbr>';
  }

  let domainName = subDomain.match(/[\w\-]+\.(org\.|co\.|com\.|gov\.)?[\w\-]+$/)[0];
  let domainLen = domainName.length;

  if (domainLen < (MAXDOMAINLEN - 2)) {
    availChars = Math.round((MAXDOMAINLEN - domainLen) / 2);
    return subDomain.substring(0, availChars)+'…'+subDomain.substring(subDomainLen - (domainLen + availChars))+'<wbr>';
  }
  else {
    availChars = MAXDOMAINLEN / 2;
    return subDomain.substring(0, (availChars - 3))+'…'+subDomain.substring((subDomainLen - 3) - availChars+'<wbr>');
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
 *  Block Domain
 *    Fill in elements in for block-dialog
 *
 *  Params:
 *    domain name, if blocked (true = blocked, false = allowed)
 *  Return:
 *    None
 */
function blockDomain(subDomain, blocked) {
  let action = '';                                         //Black or White
  let domainName = '';
  let firstItemButton = '';
  let firstItemText = '';
  let secondItemButton = '';
  let secondItemText = '';
  let simplifiedSubDomain = '';
  let simplifiedDomain = '';

  if (isIPaddress(subDomain)) {                            //Is it an IP Address
    firstItemText = '<p>Unable to Block IP addresses.<br>Perhaps you could add it to your Firewall instead?</p>';
  }

  else {                                                   //Valid subDomain to block / allow
    //Check if there are any sub domains?
    if (/^[\w\-]+\.(org\.|co\.|com\.|gov\.)?[\w\-]+$/.test(subDomain)) {
      //Regex for no sub domains but allowing for double-barrelled TLD
      simplifiedDomain = simplifyDomain(subDomain);

      if (blocked) {
        action = 'white';
        firstItemText = '<p>Add <b>'+simplifiedDomain+'</b> to White List</p>';
        firstItemButton = '<button name="site" value='+subDomain+' type="submit">Allow Entire Domain</button>';

      }
      else {
        action = 'black';
        firstItemText = '<p>Add <b>'+simplifiedDomain+'</b> to Black List</p>';
        firstItemButton = '<button name="site" value='+subDomain+' type="submit">Block Entire Domain</button>';
      }
    }
    else {
      //One or more sub domains
      //Extract subDomain with optional double-barrelled tld
      domainName = subDomain.match(/[\w\-]+\.(org\.|co\.|com\.|gov\.)?[\w\-]+$/)[0];
      simplifiedDomain = simplifyDomain(domainName);
      simplifiedSubDomain = simplifyDomain(subDomain);

      if (blocked) {
        action = 'white';
        firstItemText = '<p>Add <b>'+simplifiedDomain+'</b> to White List</p>';
        firstItemButton = '<button name="site" value='+domainName+' type="submit">Allow Entire Domain</button>';
        secondItemText = '<p>Add <b>'+simplifiedSubDomain+'</b> to White List</p>';
        secondItemButton = '<button name="site" value='+subDomain+' type="submit">Allow Sub-Domain</button>';
      }
      else {
        action = 'black';
        firstItemText = '<p>Add <b>'+simplifiedDomain+'</b> to Black List</p>';
        firstItemButton = '<button type="submit" name="site" value='+domainName+' type="submit">Block Entire Domain</button>';
        secondItemText = '<p>Add <b>'+simplifiedSubDomain+'</b> to Black List</p>';
        secondItemButton = '<button type="submit" name="site" value='+subDomain+' type="submit">Block Sub-Domain</button>';
      }
    }
  }

  document.getElementById('blockitem1').innerHTML = firstItemText + firstItemButton;

  if (secondItemText == '') {
    document.getElementById('blockitem2').style.display = 'none';
  }
  else {
    document.getElementById('blockitem2').innerHTML = secondItemText + secondItemButton;
    document.getElementById('blockitem2').style.display = 'block';
  }

  document.getElementById('reportv').value = action;
  document.getElementById('blockAction').value = action;

  //Display fade and queries-box
  document.getElementById('fade').style.display = 'block';
  document.getElementById('queries-dialog').style.display = 'block';
}


/********************************************************************
 *  Report Domain
 *    Fill in elements for report-dialog
 *
 *  Params:
 *    domain name, if blocked (true = blocked, false = allowed)
 *  Return:
 *    None
 */
function reportDomain(domain, blocked) {
  let domainName = '';

  if (blocked) {                                           //Report incorrectly blocked
    domainName = 'remove--' + domain;
  }
  else {                                                   //Report for blocking
    domainName = domain;
  }

  document.getElementById('reportTitle').innerHTML = simplifyDomain(domain);
  document.getElementById('reportInput').value = domainName;

  //Display fade and report-dialog
  document.getElementById('fade').style.display = 'block';
  document.getElementById('report-dialog').style.display = 'block';
}


/********************************************************************
 *  Hide Dialogs
 *
 */
function hideDialogs() {
  document.getElementById('queries-dialog').style.display = 'none';
  document.getElementById('report-dialog').style.display = 'none';
  document.getElementById('fade').style.display = 'none';
}


/********************************************************************
 *  Hide Report Dialog
 *
 */
function hideReportDialog() {
  document.getElementById('report-dialog').style.display = 'none';
  document.getElementById('fade').style.display = 'none';
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

  document.getElementById('timepicker-dropdown').blur();   //Move focus to the submit button
  document.getElementById('timepicker-group').blur();
  document.getElementById('submit-button').focus();
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

  document.getElementById('timepicker-dropdown').blur();   //Move focus to the submit button
  document.getElementById('timepicker-group').blur();
  document.getElementById('submit-button').focus();
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

  document.getElementById('timepicker-dropdown').blur();   //Move focus to the submit button
  document.getElementById('timepicker-group').blur();
  document.getElementById('submit-button').focus();
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

  if (item.classList.contains('active')) {
    severityInt -= Number(value);
    item.classList.remove('active');
  }
  else {
    severityInt += Number(value);
    item.classList.add('active');
  }

  severity.value = severityInt.toString();
}
