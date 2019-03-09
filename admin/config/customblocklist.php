<?php
require('../include/global-vars.php');
require('../include/global-functions.php');
require('../include/menu.php');

load_config();
ensure_active_session();

/************************************************
*Constants                                      *
************************************************/

/************************************************
*Global Variables                               *
************************************************/
$searchbox = '';
$listtype = '';

/************************************************
*Arrays                                         *
************************************************/
$list = array();                                           //Global array for all the Block Lists


/********************************************************************
 *  Load Custom Block List
 *    Loads a Black or White List from File into $list Array
 *    1. Try and load list from Memcache
 *    2. Load blocklist and parse with regex saving into $list array
 *    3. Save $list array to Memcache
 *
 *  Regex:
 *    Group 1. # Optional Comment (showing current item is disabled
 *    Group 2. site.com
 *    Group 3. # Optional Comment
 *    Group 4. Comment
 *
 *  Params:
 *    listname - blacklist or whitelist, filename
 *  Return:
 *    true on completion
 */
function load_customlist($listname, $filename) {
  global $list, $mem;

  $line = '';
  $matches = array();
  $list = $mem->get($listname);                            //Attempt to load list from Memcache

  if (empty($list)) {                                      //If nothing, then read appropriate file
    $fh = fopen($filename, 'r') or die('Error unable to open '.$filename);
    while (!feof($fh)) {
      $line = trim(fgets($fh));
      if (preg_match('/(#)?\s?([\w\-_]+\.[\w\-_\.]+)\s?(#)?(.*)/', $line, $matches) > 0) {
        if ($matches[1] == '#') {
          $list[] = array($matches[2], $matches[4], false);
        }
        else {
          $list[] = array($matches[2], $matches[4], true);
        }
      }
    }

    fclose($fh);                                           //Close file
    $mem->set($listname, $list, 0, 120);                   //Save array to Memcache
  }

  return true;
}


/********************************************************************
 *  Draw Switcher
 *    Buttons to switch between black or white list

 *  Params:
 *    $view - black or white
 *  Return:
 *    None
 */
function draw_switcher($view) {
  global $searchbox;

  $blackactive = ($view == 'black') ? 'checked="checked"' : '';
  $whiteactive = ($view == 'white') ? 'checked="checked"' : '';

  echo '<form method="get">';                              //Groupby box
  echo '<input type="hidden" name="s" value="'.$searchbox.'">';
  echo '<div id="groupby-container">'.PHP_EOL;
  echo '<input id="gbtab1" type="radio" name="v" value="black" onchange="submit()" '.$blackactive.'><label for="gbtab1">Black List</label>'.PHP_EOL;
  echo '<input id="gbtab2" type="radio" name="v" value="white" onchange="submit()" '.$whiteactive.'><label for="gbtab2">White List</label>'.PHP_EOL;
  echo '</div>'.PHP_EOL;
  echo '</form>'.PHP_EOL;
}

/********************************************************************
 *  Show Custom List
 *    Display list to user in table form
 *    1. Show search button
 *    2. Display table with items from $list
 *    3. Add a new table row with text boxes for new site
 *    4. Add download list button
 *
 *  Params:
 *    $view - black or white
 *  Return:
 *    None
 */
function show_custom_list($view) {
  global $list, $searchbox;

  $checkbox = '';
  $delete = '';
  $rowclass = '';
  $i = 1;

  echo '<div class="sys-group">'.PHP_EOL;
  echo '<h5>'.ucfirst($view).' List</h5>'.PHP_EOL;

  echo '<div class="float-left">'.PHP_EOL;
  echo '<form method="get">';                              //Search box
  echo '<input type="hidden" name="v" value="'.$view.'">';
  echo '<input type="text" name="s" id="searchbox" value="'.$searchbox.'">&nbsp;&nbsp;';
  echo '<input type="submit" value="Search">'.PHP_EOL;
  echo '</form>'.PHP_EOL;
  echo '</div>'.PHP_EOL;
  draw_switcher($view);
  echo '<br>'.PHP_EOL;

  echo '<table id="cfg-custom-table">'.PHP_EOL;            //Start custom list table

  if (is_array($list)) {
    foreach ($list as $listitem) {
      $delete = '<button class="icon-delete button-grey" onclick="deleteSite(\''.$listitem[0].'\')">Del</button>';
      if ($listitem[2] == true) {
        $checkbox = '<input type="checkbox" name="'.$listitem[0].'" onclick="changeSite(this)" checked="checked">';
        $rowclass = '<tr>';
      }
      else {
        $checkbox = '<input type="checkbox" name="'.$listitem[0].'" onclick="changeSite(this)">';
        $rowclass = '<tr class="dark">';
      }
      if ($searchbox != '') {
        if (strpos($listitem[0], $searchbox) !== false) {
          echo $rowclass.'<td>'.$i.'</td><td>'.$listitem[0].'</td><td>'.$listitem[1].'<td>'.$checkbox.$delete.'</td></tr>'.PHP_EOL;
        }
      }
      else {
        echo $rowclass.'<td>'.$i.'</td><td>'.$listitem[0].'</td><td>'.$listitem[1].'<td>'.$checkbox.$delete.'</td></tr>'.PHP_EOL;
      }
      $i++;
    }
  }

  //New domain row
  echo '<tr><td>'.$i.'</td><td><input type="text" id="newSite" placeholder="site.com"></td><td>';
  echo '<input type="text" id="newComment" placeholder="comment"></td>';
  echo '<td><button class="icon-save button-grey" onclick="addSite()">Save</button></td></tr>';

  echo '</table>'.PHP_EOL;                                 //End custom list table

  echo '<div class="centered"><br>'.PHP_EOL;
  echo '<form method="get">'.PHP_EOL;
  echo '<input type="hidden" name="v" value="'.$view.'">'.PHP_EOL;
  //echo '<button type="submit">Update Blocklists</button>&nbsp;'.PHP_EOL;
  echo '<button type="submit" formaction="../include/downloadlist.php" class="button-grey">Download List</button></form>';
  echo '</div>'.PHP_EOL;
  echo '</div>'.PHP_EOL;                                   //End sys-group
}


/********************************************************************
 *  Update Custom List
 *    Save $list to a temporary file, then run ntrk-exec to copy temp file to /etc/notrack
 *    Run notrack in wait mode, which gives user 4 mins before blocklists are processed
 *    1. Carry out input validation on POST items
 *    2. Find site in $list[x][0]
 *    3. Carry out action specified in status
 *    4. Save $list to temporary file
 *    5. Run notrack in wait mode
 *
 *  Params:
 *    Actual Name, List name
 *  Return:
 *    True when action carried out
 */
function update_custom_list($actualname, $listname) {
  global $list, $mem;

  $comment = '';
  $status = '';
  $site = '';

  //Input validation
  if (isset($_POST['status'])) {
    switch($_POST['status']) {
      case 'add': $status = 'add'; break;
      case 'del': $status = 'del'; break;
      case 'enable': $status = 'enable'; break;
      case 'disable': $status = 'disable'; break;
    }
  }

  if (isset($_POST['site'])) {
    if (filter_url(trim($_POST['site']))) {
      $site = trim(strtolower($_POST['site']));            //New sites should be lowercase
    }
    else {
      return false;
    }
  }
  else {
    return false;
  }

  if (isset($_POST['comment'])) {
    $comment = strip_tags($_POST['comment']);
  }

  //Find position of site in array, unless we are adding a site
  if ((! is_array($list)) && ($status != 'add')) {         //Prevent error finding item in empty array
    return false;
  }

  $arraypos = array_search($site, array_column($list, 0)); //Find position of $site in array
  if (($arraypos === false) && ($status != 'add')) {
    return false;
  }

  //Carry out action
  if ($status == 'add') {
    $list[] = array($site, $comment, true);                //Add item to array
  }
  elseif ($status == 'del') {
    array_splice($list, $arraypos, 1);                     //Remove 1 item from array
  }
  elseif ($status == 'disable') {
    $list[$arraypos][2] = false;
  }
  elseif ($status == 'enable') {
    $list[$arraypos][2] = true;
  }


  //Open file /tmp/listname.txt for writing
  $fh = fopen(DIR_TMP.strtolower($actualname).'.txt', 'w') or die('Unable to open '.DIR_TMP.$actualname.'.txt for writing');

  //Write Usage Instructions to top of File
  fwrite($fh, '#Use this file to create your own custom '.$actualname.PHP_EOL);
  fwrite($fh, '#Run sudo notrack after you make any changes to this file'.PHP_EOL);

  foreach ($list as $listitem) {                           //Write list array to temp
    if ($listitem[2] == true) {                            //Is site enabled?
      fwrite($fh, $listitem[0].' #'.$listitem[1].PHP_EOL);
    }
    else {                                                 //Site disabled, comment it out by preceding Line with #
      fwrite($fh, '# '.$listitem[0].' #'.$listitem[1].PHP_EOL);
    }
  }
  fclose($fh);                                             //Close file

  $mem->delete($listname);
  $mem->set($listname, $list, 0, 60);

  exec(NTRK_EXEC.'--copy '.$listname);

  return true;
}

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <link href="../css/master.css" rel="stylesheet" type="text/css">
  <link href="../css/icons.css" rel="stylesheet" type="text/css">
  <link rel="icon" type="image/png" href="../favicon.png">
  <script src="../include/config.js"></script>
  <script src="../include/menu.js"></script>
  <meta name="viewport" content="width=device-width, initial-scale=0.9">
  <title>NoTrack - Custom Blocklists</title>
</head>

<body>
<?php
/********************************************************************
 Main
*/
draw_topmenu('Blocklists');
draw_sidemenu();

echo '<div id="main">'.PHP_EOL;

if (isset($_GET['s'])) {                         //Search box
  //Limit characters to alphanumeric, . - _
  $searchbox = preg_replace('/[^\w\.\-_]/', '', $_GET['s']);
  $searchbox = strtolower($searchbox);
}

if (isset($_POST['action'])) {
  switch($_POST['action']) {
    case 'black':
      load_customlist('black', BLACKLIST_FILE);
      update_custom_list('BlackList', 'black');
      $listtype = 'black';
      break;
    case 'white':
      load_customlist('white', WHITELIST_FILE);
      update_custom_list('WhiteList', 'white');
      $listtype = 'white';
      break;
  }
}

if (isset($_GET['v'])) {                                   //What view to show?
  switch($_GET['v']) {
    case 'white':
      load_customlist('white', WHITELIST_FILE);
      show_custom_list('white');
      $listtype = 'white';
      break;
    default:
      load_customlist('black', BLACKLIST_FILE);
      show_custom_list('black');
      $listtype = 'black';
      break;
  }
}
else {
  load_customlist('black', BLACKLIST_FILE);
  show_custom_list('black');
  $listtype = 'black';
}

echo '</div>'.PHP_EOL;                                     //End Main
echo '<form name="customblocklist" id="blocklistform" method="POST">'.PHP_EOL;
echo '<input type="hidden" name="v" value="'.$listtype.'">'.PHP_EOL;
echo '<input type="hidden" name="action" value="'.$listtype.'">'.PHP_EOL;
echo '<input type="hidden" name="site" value="" id="siteItem">'.PHP_EOL;
echo '<input type="hidden" name="comment" value="" id="commentItem">'.PHP_EOL;
echo '<input type="hidden" name="status" value="" id="statusItem">'.PHP_EOL;
echo '</form>';
?>


<script>
function addSite() {
  document.getElementById('siteItem').value = document.getElementById('newSite').value;
  document.getElementById('commentItem').value = document.getElementById('newComment').value;
  document.getElementById('statusItem').value = 'add';
  document.getElementById('blocklistform').submit();
}
function deleteSite(site) {
  document.getElementById('siteItem').value = site;
  document.getElementById('statusItem').value = 'del';
  document.getElementById('blocklistform').submit();
}
function changeSite(box) {
  let statusValue = '';
  statusValue = (box.checked) ? 'enable' : 'disable';

  document.getElementById('siteItem').value = box.name;
  document.getElementById('statusItem').value = statusValue;
  document.getElementById('blocklistform').submit();
}
</script>
</body>
</html>
