<?php
require('../include/global-vars.php');
require('../include/global-functions.php');
require('../include/config.php');
require('../include/menu.php');

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
$list = array();                                //Global array for all the Block Lists


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
function draw_table_toolbar($view) {
  global $searchbox;

  $blackactive = ($view == 'black') ? 'checked="checked"' : '';
  $whiteactive = ($view == 'white') ? 'checked="checked"' : '';

  echo '<div class="table-toolbar">'.PHP_EOL;
  echo '<button class="float-left" onclick="window.location.href=\'?v=bulk'.$view.'\'">Bulk Upload</button>'.PHP_EOL;
  echo '<div class="table-toolbar-options">'.PHP_EOL;      //Start Table Toolbar Export
  echo '<button class="button-grey material-icon-centre icon-export" title="Export" onclick="window.location.href=\'../include/downloadlist.php?v='.$view.'\'">&nbsp;</button>';
  echo '</div>'.PHP_EOL;                                   //End Table Toolbar Export

  echo '<form method="get">';                              //Groupby box
  echo '<input type="hidden" name="s" value="'.$searchbox.'">';
  echo '<div id="groupby-container">'.PHP_EOL;
  echo '<input id="gbtab1" type="radio" name="v" value="black" onchange="submit()" '.$blackactive.'><label for="gbtab1">Black List</label>'.PHP_EOL;
  echo '<input id="gbtab2" type="radio" name="v" value="white" onchange="submit()" '.$whiteactive.'><label for="gbtab2">White List</label>'.PHP_EOL;
  echo '</div>'.PHP_EOL;                                   //End Groupby box
  echo '</form>'.PHP_EOL;
  echo '</div>'.PHP_EOL;                                   //End Table Toolbar
}


/********************************************************************
 *  Show Custom List
 *    Display list to user in table form
 *    1. Show search button
 *    2. Display table with items from $list
 *    3. Add a new table row with text boxes for new site
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

  echo '<form method="get">';                              //Filter Toolbar
  echo '<div class="filter-toolbar custombl-filter-toolbar">'.PHP_EOL;

  //Column Headers
  echo '<div><h3>Search Domain</h3></div>'.PHP_EOL;
  echo '<div></div>'.PHP_EOL;

  echo '<input type="hidden" name="v" value="'.$view.'">';
  echo '<div><input type="text" name="s" id="searchbox" placeholder="site.com" value="'.$searchbox.'"></div>';
  echo '<div><button type="submit">Search</button></div>'.PHP_EOL;
  echo '</div>'.PHP_EOL;
  echo '</form>'.PHP_EOL;

  draw_table_toolbar($view);

  echo '<table id="custombl-table">'.PHP_EOL;            //Start custom list table

  if (is_array($list)) {
    foreach ($list as $listitem) {
      $delete = '<button class="icon-delete button-grey" onclick="deleteSite(\''.$listitem[0].'\')" title="Delete">Del</button>';
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
  echo '</div>'.PHP_EOL;                                   //End sys-group
}


/********************************************************************
 *  Process Bulk List
 *    Validate POST items and then run function write_temp_list to save $list
 *    1. Carry out input validation on POST items
 *    2. Create an array from $_POST[site], which are seperated by a comma
 *    3. Check if each item of array is valid, then add
 *    4. Run write_temp_list()
 *
 *  Params:
 *    Actual Name, List name
 *  Return:
 *    True when action carried out
 */
function process_bulk_list($actualname, $listname) {
  global $list;

  $domainlist = array();
  $domain = '';

  if (isset($_POST['site'])) {
    if (strlen($_POST['site']) < 4) {                      //Reject if below minimum domain len
      return false;
    }
  }
  else {
    return false;
  }

  //Remove tags and trim before exploding by comma
  $domainlist = explode(',', strip_tags(trim($_POST['site'])));
  if (! is_array($domainlist)) return false;

  foreach ($domainlist as $domain) {
    if (filter_domain($domain)) {                          //Is domain valid?
      $list[] = array(strtolower($domain), '', true);      //Add item to whichever list
      //echo "$domain<br>";
    }
  }

  write_temp_list($actualname, $listname);

  return true;
}


/********************************************************************
 *  Update Custom List
 *    Validate POST items and then run function write_temp_list to save $list
 *    1. Carry out input validation on POST items
 *    2. Find site in $list[x][0]
 *    3. Carry out action specified in status
 *    4. Save $list to temporary file
 *    5. Run write_temp_list()
 *
 *  Params:
 *    Actual Name, List name
 *  Return:
 *    True when action carried out
 */
function update_custom_list($actualname, $listname) {
  global $list;

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
    if (filter_domain(trim($_POST['site']))) {
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
  if ($status != 'add') {
    if (! is_array($list)) {                               //Prevent error finding item in empty array
      return false;
    }
    //Find position of $site in array
    $arraypos = array_search($site, array_column($list, 0));

    if ($arraypos === false) {
      return false;
    }
  }

  //Carry out action
  if ($status == 'add') {
    $list[] = array($site, $comment, true);                //Add item to whichever list
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

  write_temp_list($actualname, $listname);
}


/********************************************************************
 *  Write Temp List
 *    Save $list to a temporary file, then run ntrk-exec to copy temp file to /etc/notrack
 *    Run notrack in wait mode, which gives user 4 mins before blocklists are processed
 *
 *  Params:
 *    Actual Name, List name
 *  Return:
 *    True when action carried out
 */
function write_temp_list($actualname, $listname) {
  global $list, $mem;

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

  exec(NTRK_EXEC.'--copy '.$listname);

  $mem->delete($listname);
  $mem->set($listname, $list, 0, 120);

  return true;
}


/********************************************************************
 *  Show Bulkupload
 *
 *  Params:
 *    $view - black or white
 *  Return:
 *    None
 */
function show_bulkupload($view) {
  echo '<pre id="bulkBox" contenteditable="true">'.PHP_EOL;
  echo '#paste a list of sites here, and then click the "Check" Button'.PHP_EOL;
  echo '</pre>'.PHP_EOL;
  echo '<div class="sys-group">'.PHP_EOL;
  echo '<div class="centered">'.PHP_EOL;
  echo '<button id="bulkCheck" class="button-yellow" onclick="evaluateBulkBox()">Check</button>&nbsp;&nbsp;';
  echo '<button id="bulkSubmit" class="button-danger" onclick="submitBulkBox()">Submit</button>'.PHP_EOL;
  echo '</div>'.PHP_EOL;
  echo '</div>'.PHP_EOL;
}

/********************************************************************
 Main
*/

//Carry out any post actions and then reload
if (isset($_POST['action'])) {
  switch($_POST['action']) {
    case 'black':
      load_customlist('black', BLACKLIST_FILE);
      update_custom_list('BlackList', 'black');
      header('Location: ?v=black');
      break;
    case 'white':
      load_customlist('white', WHITELIST_FILE);
      update_custom_list('WhiteList', 'white');
      header('Location: ?v=white');
      break;
    case 'bulkblack':
      load_customlist('black', BLACKLIST_FILE);
      process_bulk_list('BlackList', 'black');
      header('Location: ?v=black');
      break;
    case 'bulkwhite':
      load_customlist('white', WHITELIST_FILE);
      process_bulk_list('WhiteList', 'white');
      header('Location: ?v=white');
      break;
  }
  exit;
}


?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <link href="../css/master.css" rel="stylesheet" type="text/css">
  <link href="../css/icons.css" rel="stylesheet" type="text/css">
  <link rel="icon" type="image/png" href="../favicon.png">
  <script src="../include/menu.js"></script>
  <script src="../include/customblocklist.js"></script>
  <meta name="viewport" content="width=device-width, initial-scale=0.9">
  <title>NoTrack - Custom Blocklists</title>
</head>

<body>
<?php

if (isset($_GET['s'])) {                                   //Search box
  //Limit characters to alphanumeric, . - _
  $searchbox = preg_replace('/[^\w\.\-_]/', '', $_GET['s']);
  $searchbox = strtolower($searchbox);
}

if (isset($_GET['v'])) {                                   //What view to show?
  switch($_GET['v']) {
    case 'white':
      draw_topmenu('White List');
      draw_sidemenu();
      echo '<div id="main">'.PHP_EOL;

      load_customlist('white', WHITELIST_FILE);
      show_custom_list('white');
      $listtype = 'white';
      break;
    case 'bulkblack':
      draw_topmenu('Black List');
      draw_sidemenu();
      echo '<div id="main">'.PHP_EOL;

      load_customlist('black', BLACKLIST_FILE);
      show_bulkupload('black');
      $listtype = 'bulkblack';
      break;
    case 'bulkwhite':
      draw_topmenu('White List');
      draw_sidemenu();
      echo '<div id="main">'.PHP_EOL;

      load_customlist('white', WHITELIST_FILE);
      show_bulkupload('white');
      $listtype = 'bulkwhite';
      break;
    default:
      draw_topmenu('Black List');
      draw_sidemenu();
      echo '<div id="main">'.PHP_EOL;

      load_customlist('black', BLACKLIST_FILE);
      show_custom_list('black');
      $listtype = 'black';
      break;
  }
}
else {
  draw_topmenu('Black List');
  draw_sidemenu();
  echo '<div id="main">'.PHP_EOL;

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
</body>
</html>
