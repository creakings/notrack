/********************************************************************
 *  Reset queries form
 *    Reset elements in queries form to their default values
 *  Params:
 *    None
 *  Return:
 *    None
 */
function resetQueriesForm() {
  document.getElementById('filtersearch').value = '';
  document.getElementById('filtersys').value = '';
  document.getElementById('filtertime').value = '1 DAY';
  document.getElementById('filtertype').value = 'all';
  document.getElementById('filtergroup').value = 'name';
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
 *  Report Site
 *
 *  Params:
 *    site name, if blocked (true = blocked, false = allowed), show report button
 *  Return:
 *    None
 */
function reportSite(site, blocked, showreport) {
  var msg = '';                                            //Message to show user
  var inv = '';                                            //Investigate Item
  var item1 = '';                                          //Block button and message
  var item2 = '';                                          //Block button and message with subdomain
  var report = '';                                         //report button and message
  var domain = '';
  var action = '';

  if (isCommonDomain(site)) {                              //Is it a *common site?
    msg = '<p>Domains starting with * are known to utilise a large number of subdomains</p>';
  }
  else if (! isValidDomain(site)) {
    msg = '<p>Invalid site</p>';
  }
  else if (/(\.akamai\.net|akamaiedge\.net)$/.test(site)) { //Is it an Akami site?
    msg = '<p>Akami is a Content Delivery Network (CDN) providing media delivery for a wide range of websites.</p><p>It is more efficient to block the originating website, rather than an Akami subdomain.</p>';
  }
  else if (isIPaddress(site)) {            //Is it an IP Address
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

    inv = '<button name="site" value='+site+' type="submit">View Details</button><span>View domain details in NoTrack Investigate</span>';

    if (showreport) {                                      //Show report button (for NoTrack blocked sites)
      if (blocked) {                                       //Remove blocked site
        report = 'remove--'+site;
    }
      else {                                               //Report site for blocking
        report = site;
      }
    }
  }


  //Modify DOM elements depending on whether a string has been set.
  document.getElementById('sitename').innerText = site;

  if (msg == '') {
    document.getElementById('reportmsg').style.display = 'none';
    document.getElementById('reportmsg').innerHTML = '';
  }
  else {
    document.getElementById('reportmsg').style.display = 'block';
    document.getElementById('reportmsg').innerHTML = msg;
  }

  if (item1 == '') {
    document.getElementById('reportitem1').style.display = 'none';
    document.getElementById('reportitem1').innerHTML = '';
    document.getElementById('invitem').style.display = 'none';
    document.getElementById('invitem').innerHTML = '';
  }
  else {
    document.getElementById('reportitem1').style.display = 'block';
    document.getElementById('reportitem1').innerHTML = item1;
    document.getElementById('invitem').style.display = 'block';
    document.getElementById('invitem').innerHTML = inv;
  }

  if (item2 == '') {
    document.getElementById('reportitem2').style.display = 'none';
    document.getElementById('reportitem2').innerHTML = '';
  }
  else {
    document.getElementById('reportitem2').style.display = 'block';
    document.getElementById('reportitem2').innerHTML = item2;
  }

  document.getElementById('reportv').value = action;
  document.getElementById('reportaction').value = action;

  if (report == '') {
    document.getElementById('statsreport').style.display = 'none';
  }
  else {
    document.getElementById('statsreport').style.display = 'block';
    document.getElementById('siterep').value = report;
  }

  //Position Fade and Stats box
  document.getElementById('fade').style.top = window.pageYOffset+'px';
  document.getElementById('fade').style.display = 'block';

  document.getElementById('queries-box').style.top = (window.pageYOffset + (window.innerHeight / 2))+'px';
  document.getElementById('queries-box').style.left = (window.innerWidth / 2)+'px';
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

  //Lock Stats box and Fade in place if visible
  if (document.getElementById('queries-box').style.display == 'block') {
    document.getElementById('fade').style.top=window.pageYOffset+'px';

    document.getElementById('queries-box').style.top = (window.pageYOffset + (window.innerHeight / 2))+'px';
    document.getElementById('queries-box').style.left = (window.innerWidth / 2)+'px';
  }

  //Lock Options box in place if visible
  if (document.getElementById('options-box').style.display == 'block') {
    document.getElementById('fade').style.top=window.pageYOffset+'px';

    document.getElementById('options-box').style.top = (window.pageYOffset + (window.innerHeight / 2))+'px';
    document.getElementById('options-box').style.left = (window.innerWidth / 2)+'px';
  }
}

