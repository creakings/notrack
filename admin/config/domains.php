<?php
/********************************************************************

********************************************************************/
require('../include/global-vars.php');
require('../include/global-functions.php');
require('../include/config.php');
require('../include/menu.php');
require('../include/mysqlidb.php');

ensure_active_session();

/************************************************
*Constants                                      *
************************************************/
define('DOMAIN_BLACKLIST', '/etc/notrack/domain-blacklist.txt');
define('DOMAIN_WHITELIST', '/etc/notrack/domain-whitelist.txt');
/************************************************
*Global Variables                               *
************************************************/
$dbwrapper = new MySqliDb;
$page = 1;
$searchbox = '';
$selectedbl = 'all';

/************************************************
*Arrays                                         *
************************************************/


/********************************************************************
 *  Draw Domain Filter Bar
 *    Populates filter bar with Search Box, Search Button, and Block List selector
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function draw_filter_toolbar() {
  global $config, $dbwrapper;
  global $page, $searchbox, $selectedbl;

  $activelist = array();                                   //Array of active block lists

  $activelist = $dbwrapper->blocklist_active();            //Get active block lists

  echo '<form method="GET">'.PHP_EOL;
  echo '<input type="hidden" name="page" value="'.$page.'">'.PHP_EOL;
  echo '<div class="filter-toolbar domains-filter-toolbar">'.PHP_EOL;

  //1st Row - Column headers
  echo '<div><h3>Search Domain</h3></div>';
  echo '<div></div>';
  echo '<div><h3>Block List</h3></div>';

  //2nd Row - Form inputs
  echo '<div><input type="text" name="s" id="search" placeholder="site.com" value="'.$searchbox.'"></div>';
  echo '<div><input type="Submit" value="Search"></div>'.PHP_EOL;

  echo '<div><select name="selectedbl" onchange="submit()">';

  //Fill in the first value which is the current selected block list (default is all)
  if ($selectedbl == 'all') echo '<option value="all">All</option>'.PHP_EOL;
  else {
    echo '<option value="'.$selectedbl.'">'.$config->get_blocklistname($selectedbl).'</option>'.PHP_EOL;
    echo '<option value="all">All</option>'.PHP_EOL;       //Still need to write all as its not part of the block lists
  }

  foreach ($activelist as $item) {                         //Go through active block lists
    if ($item[0] != $selectedbl) echo '<option value="'.$item[0].'">'.$config->get_blocklistname($item[0]).'</option>'.PHP_EOL;
  }

  echo '</select></div>'.PHP_EOL;                          //End block list select

  echo '</div>'.PHP_EOL;                                   //End domains-filter-toolbar
  echo '</form>'.PHP_EOL;                                  //End of form
}


/********************************************************************
 *  Show Export
 *    Plaintext version of the full blocklist
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function show_export() {
  global $config, $dbwrapper, $page, $searchbox, $selectedbl;

  $result = $dbwrapper->blocklist_domains($selectedbl, $searchbox);

  header('Content-type: text/dns');
  header('Content-Disposition: attachment; filename="notrack_blocklist_'.$selectedbl.'.txt"');
  echo '#Title: NoTrack Blocklist'.PHP_EOL;

  echo "#Selected Block List: {$selectedbl}".PHP_EOL;

  if ($result->num_rows == 0) {                            //Leave if nothing found
    $result->free();
    echo 'No results found in Block List'.PHP_EOL;
    return false;
  }

  while($row = $result->fetch_assoc()) {                   //Read each row of results
    echo $row['site'].PHP_EOL;
  }

  $result->free();
}


/********************************************************************
 *  Show Full Block List
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function show_full_blocklist() {
  global $config, $dbwrapper, $page, $searchbox, $selectedbl;

  $i = 0;                                                  //Friendly table position
  $k = 1;                                                  //Count within ROWSPERPAGE
  $clipboard = '';                                         //Div for Clipboard
  $domain = '';
  $row_class = '';
  $bl_friendlyname = '';
  $linkstr = '';

  $result = $dbwrapper->blocklist_domains($selectedbl, $searchbox);

  echo '<div class="sys-group">';                          //Now for the results

  draw_filter_toolbar();                                    //Block List selector form

  if ($result->num_rows == 0) {                            //Leave if nothing found
    $result->free();
    echo '<h4><img src=../svg/emoji_sad.svg>No results found in Block List</h4>'.PHP_EOL;
    echo '</div>'.PHP_EOL;
    return false;
  }

  //Page needs to be reduced by one to account for array position starting at zero vs human readable starting page at one

  //Prevent page being greater than number of rows
  if ((($page-1) * ROWSPERPAGE) > $result->num_rows) {
    $page = 1;
  }

  //Move seek point if currrent page is greater than one
  if ($page > 1) {
    $result->data_seek(($page - 1) * ROWSPERPAGE);
  }

  $i = (($page - 1) * ROWSPERPAGE) + 1;                    //Friendly table position

  //Setup link string with contents of search box and selected blocklist
  $linkstr = ($searchbox == '' ? '' : "s={$searchbox}&amp;");
  $linkstr .= ($selectedbl == 'all' ? '' : "selectedbl={$selectedbl}");

  echo '<div class="table-toolbar">'.PHP_EOL;              //Start Table Toolbar
  pagination($result->num_rows, $linkstr);

  //Table Toolbar
  echo '<div class="table-toolbar-options">'.PHP_EOL;      //Start Table Toolbar Export
  echo '<button class="button-grey material-icon-centre icon-export" title="Export" onclick="window.location.href=\'?'.$linkstr.'&amp;export\'">&nbsp;</button>';
  echo '</div>'.PHP_EOL;                                   //End Table Toolbar Export
  echo '</div>'.PHP_EOL;                                   //End Table Toolbar

  //Start of Table
  echo '<table id="block-table">'.PHP_EOL;
  echo '<tr><th>#</th><th>Block List</th><th>Domain</th><th>Comment</th></tr>'.PHP_EOL;

  while($row = $result->fetch_assoc()) {                   //Read each row of results
    $domain = $row['site'];

    //Create clipboard image and text
    $clipboard = '<div class="icon-clipboard" onclick="setClipboard(\'./'.$domain.'\')" title="Copy domain">&nbsp;</div>';

    if ($row['site_status'] == 0) {                        //Is site enabled or disabled?
      $row_class = ' class="dark"';
    }
    else {
      $row_class = '';
    }

    //Convert abbreviated bl_name to friendly name
    $bl_friendlyname = $config->get_blocklistname($row['bl_source']);

    //Output table row
    echo "<tr{$row_class}><td>{$i}</td><td>{$bl_friendlyname}</td><td>{$domain}{$clipboard}</td><td>{$row['comment']}</td></tr>".PHP_EOL;

    $i++;
    $k++;
    if ($k > ROWSPERPAGE) break;
  }
  echo '</table>'.PHP_EOL;                                 //End of table

  echo '<br>'.PHP_EOL;
  pagination($result->num_rows, $linkstr);                 //Draw second Pagination box
  echo '</div>'.PHP_EOL;

  $result->free();

  return true;
}
/********************************************************************
 Main
 */

//Process GET Requests
if (filter_string('s', 'GET', 255)) {                      //Search box
  //Allow only alphanumeric . - _
  $searchbox = preg_replace('/[^\w\.\-_]/', '', $_GET['s']);
  $searchbox = strtolower($searchbox);
}

if (isset($_GET['page'])) {                                //Page Number
  $page = filter_integer($_GET['page'], 1, PHP_INT_MAX, 1);
}

if (isset($_GET['selectedbl'])) {                          //Selected Blocklist
  //Filtering to check if the selected blocklist exists
  //If it doesn't, keep with the default blocklist option - all
  if (array_key_exists($_GET['selectedbl'], $config->blocklists)) {
    $selectedbl = $_GET['selectedbl'];
  }
}

if (isset($_GET['export'])) {                              //Export file has a different content type
  show_export();
  exit;
}

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <link href="../css/master.css" rel="stylesheet" type="text/css">
  <link href="../css/icons.css" rel="stylesheet" type="text/css">
  <link href="../css/tabbed.css" rel="stylesheet" type="text/css">
  <link rel="icon" type="image/png" href="../favicon.png">
  <script src="../include/menu.js"></script>
  <script src="../include/queries.js"></script>
  <meta name="viewport" content="width=device-width, initial-scale=0.9">
  <title>NoTrack - Domains Blocked</title>
</head>

<body>
<?php
draw_topmenu('Domains Blocked');
draw_sidemenu();

echo '<div id="main">'.PHP_EOL;

show_full_blocklist();
draw_copymsg();
?>

</div>
</body>
</html>
