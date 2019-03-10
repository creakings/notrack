/*var bulkBox = document.getElementById('bulkBox')
bulkBox.addEventListener('input', function() {
  evaluateBulkBox();
});
*/
var domainlist = new Array();

const DEFANGED_REGEX = /^(fxp|ftp|hxxps?|https?):\/\/([\w\-_]+\[?\.\]?[\w\-_\[\]\.]+)(\/?.*)/i;
const EASY_REGEX = /^\|\|([\w\-_]+\.[\w\-_\.]+)(\^|\^\$third\-party|\^\$popup|\^\$popup,third\-party)$/;
const PLAIN_REGEX = /^([\w\-_]+\.[\w\-_\.]+)\s?(#[^#]*|)$/
const UNIX_REGEX = /^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\s+)([\w\-_]+\.[\w\-_\.]+)\s?#?([^#]*)$/;


/********************************************************************
 *  Is Defanged Line
 *    Checks supplied line against DEFANGED_REGEX
 *
 *  Params:
 *    line
 *  Return:
 *    True if matches DEFANGED_REGEX
 *    False if no match
 */
function isDefangedLine(line) {
  return DEFANGED_REGEX.test(line);
}


/********************************************************************
 *  Match Defanged Line
 *   Extracts matches against DEFANGED_REGEX and returns formatted string
 *
 *  Params:
 *    line
 *  Return:
 *    Formatted line
 */
function matchDefangedLine(line) {
  let matches = DEFANGED_REGEX.exec(line)

  if (matches !== null) {
    domainlist.push(matches[2].replace('[.]', '.'));
    return matches[1]+"://<span>"+matches[2]+"</span>"+matches[3];
  }
  return null;
}

/********************************************************************
 *  Is Easy Line
 *    Checks supplied line against EASY_REGEX
 *
 *  Params:
 *    line
 *  Return:
 *    True if matches EASY_REGEX
 *    False if no match
 */
function isEasyLine(line) {
  return EASY_REGEX.test(line);
}


/********************************************************************
 *  Match Easy Line
 *   Extracts matches against EASY_REGEX and returns formatted string
 *
 *  Params:
 *    line
 *  Return:
 *    Formatted line
 */
function matchEasyLine(line) {
  let matches = EASY_REGEX.exec(line)

  if (matches !== null) {
    domainlist.push(matches[1]);
    return "||<span>"+matches[1]+"</span>"+matches[2];
  }
  return null;
}


/********************************************************************
 *  Is Plain Line
 *    Checks supplied line against PLAIN_REGEX
 *
 *  Params:
 *    line
 *  Return:
 *    True if matches PLAIN_REGEX
 *    False if no match
 */
function isPlainLine(line) {
  return PLAIN_REGEX.test(line);
}


/********************************************************************
 *  Match Plain Line
 *   Extracts matches against EASY_REGEX and returns formatted string
 *
 *  Params:
 *    line
 *  Return:
 *    Formatted line
 */
function matchPlainLine(line) {
  let matches = PLAIN_REGEX.exec(line)

  if (matches !== null) {
    domainlist.push(matches[1]);
    if (matches[2] !== undefined) {                        //Comment may not be filled in
      return "<span>"+matches[1]+"</span> "+matches[2];
    }
    else {
      return "<span>"+matches[1]+"</span>";
    }
  }
  return null;
}

/********************************************************************
 *  Is Unix Line
 *    Checks supplied line against UNIX_REGEX
 *
 *  Params:
 *    line
 *  Return:
 *    True if matches UNIX_REGEX
 *    False if no match
 */
function isUnixLine(line) {
  return UNIX_REGEX.test(line);
}


/********************************************************************
 *  Match Unix Line
 *   Extracts matches against UNIX_REGEX and returns formatted string
 *
 *  Params:
 *    line
 *  Return:
 *    Formatted line
 */
function matchUnixLine(line) {
  let matches = UNIX_REGEX.exec(line);

  if (matches !== null) {
    domainlist.push(matches[2]);
    return matches[1]+"<span>"+matches[2]+"</span> #"+matches[3];
  }
  return null;
}


/********************************************************************
 *  Evaluate Bulk Box
 *   1. Take plaintext lines from bulkBox and put them in lines array
 *   2. Evaluate each line against known regex's
 *   3. Add matched lines to a new array newlines with formatting
 *   4. Add unmatched lines without formatting
 *   5. Replace bulkBox HTML text with newlines
 *
 *  Params:
 *    none
 *  Return:
 *    none
 */
function evaluateBulkBox() {
  var bulkBox = document.getElementById('bulkBox');
  let lines = bulkBox.innerText.split('\n');
  let line = '';
  let lineslen = lines.length;
  let newlines = new Array();

  domainlist.length = 0;

  for (let i = 0; i < lineslen; i++) {
    line = lines[i];
    //if (/^#/.test(line)) continue;

    if (/<(?!\/?span>)/.test(line)) continue;              //Prevent XSS by stripping non-span tags

    //Attmept to match against known regex's
    if (isPlainLine(line)) {
      newlines.push(matchPlainLine(line));
    }
    else if (isEasyLine(line)) {
      newlines.push(matchEasyLine(line));
    }
    else if (isUnixLine(line)) {
      newlines.push(matchUnixLine(line));
    }
    else if (isDefangedLine(line)) {
      newlines.push(matchDefangedLine(line));
    }
    else {
      newlines.push(line);
    }
  }

  bulkBox.innerHTML = newlines.join('<br>');               //Write formatted data to bulkBox

  if (domainlist.length == 0) {                            //If nothing found leave submit red coloured
    document.getElementById('bulkSubmit').className = 'button-danger';
  }
  else {                                                   //Or recolour to teal
    document.getElementById('bulkSubmit').className = '';
  }
}

/********************************************************************
 *  Submit Bulk Box
 *    1. evaluateBulkBox
 *    2. Check there is something in domainlist
 *    3. Submit blocklistform
 *
 *  Params:
 *    none
 *  Return:
 *    none
 */
function submitBulkBox() {
  evaluateBulkBox();
  if (domainlist.length == 0) {
    return false;
  }
  document.getElementById('siteItem').value = domainlist;
  document.getElementById('statusItem').value = 'bulk';
  document.getElementById('blocklistform').submit();
}


/********************************************************************
 *  Add Site
 *    Add single site and submit blocklistform
 *
 *  Params:
 *    none
 *  Return:
 *    none
 */
function addSite() {
  document.getElementById('siteItem').value = document.getElementById('newSite').value;
  document.getElementById('commentItem').value = document.getElementById('newComment').value;
  document.getElementById('statusItem').value = 'add';
  document.getElementById('blocklistform').submit();
}


/********************************************************************
 *  Delete Site
 *    Delete single site and submit blocklistform
 *
 *  Params:
 *    none
 *  Return:
 *    none
 */
function deleteSite(site) {
  document.getElementById('siteItem').value = site;
  document.getElementById('statusItem').value = 'del';
  document.getElementById('blocklistform').submit();
}


/********************************************************************
 *  Change Site
 *    Change site depending on checkbox value and submit blocklistform
 *
 *  Params:
 *    none
 *  Return:
 *    none
 */
function changeSite(box) {
  let statusValue = '';
  statusValue = (box.checked) ? 'enable' : 'disable';

  document.getElementById('siteItem').value = box.name;
  document.getElementById('statusItem').value = statusValue;
  document.getElementById('blocklistform').submit();
}
