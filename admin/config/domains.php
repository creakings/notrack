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
$showblradio = false;
$blradio = 'all';

/************************************************
*Arrays                                         *
************************************************/


/********************************************************************
 *  Draw Blocklist Radio Form
 *    There are two views to choose from:
 *      1: Button to "Select Block List"
 *      2: Radio list of blocklists identified by blocklist_active
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function draw_blradioform() {
  global $config, $dbwrapper, $showblradio, $blradio, $page, $searchbox;
  
  $checked = '';                                           //Display checked="checked" or nothing
  $activelist = $dbwrapper->blocklist_active();
  
  //A value of false from blocklist_active means no blocklists are in use
  if ($activelist === false) {
    return;
  }
  
  //Just draw the Select Block List button
  if (! $showblradio) {
    echo '<form action="?page='.$page.'&amp;s='.$searchbox.'" method="POST">'.PHP_EOL;
    echo '<input type="hidden" name="showblradio" value="1">'.PHP_EOL;
    echo '<input type="submit" value="Select Block List">'.PHP_EOL;
    echo '</form>'.PHP_EOL;
    echo '<br>'.PHP_EOL;
    return;
  }
  
  //At this point we are drawing the radio list
  echo '<form name = "blradform" method="GET">'.PHP_EOL;   //Form for Radio List
  echo '<input type="hidden" name="page" value="'.$page.'">'.PHP_EOL;
  echo '<input type="hidden" name="s" value="'.$searchbox.'">'.PHP_EOL;
  
  //Start with 'All' radio item
  $checked = ($blradio == 'all' ? 'checked="checked" ' : '');
  echo '<span class="blradiolist"><input type="radio" name="blrad" value="all"'.$checked.' onclick="document.blradform.submit()">All</span>'.PHP_EOL;

  //List of active items for radio list
  foreach ($activelist as $item) {
    //Should current item be checked?
    $checked = ($item[0] == $blradio ? 'checked="checked" ' : '');

    echo '<span class="blradiolist"><input type="radio" name="blrad" value="'.$item[0].'" '.$checked. 'onclick="document.blradform.submit()">'.$config->get_blocklistname($item[0]).'</span>'.PHP_EOL;
  }

  echo '</form>'.PHP_EOL;                                  //End of form
  echo '<br>'.PHP_EOL;
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
  global $config, $dbwrapper, $page, $searchbox, $blradio, $showblradio;

  $i = 0;                                                  //Friendly table position
  $k = 1;                                                  //Count within ROWSPERPAGE
  $clipboard = '';                                         //Div for Clipboard
  $domain = '';
  $row_class = '';
  $bl_source = '';
  $linkstr = '';
    
  echo '<div class="sys-group">'.PHP_EOL;
  echo '<h5>Domains Blocked</h5>'.PHP_EOL;
      
  $result = $dbwrapper->blocklist_domains($blradio, $searchbox);
   
  draw_blradioform();                                      //Block List selector form
  
  echo '<form method="GET">'.PHP_EOL;                      //Form for Text Search
  echo '<input type="hidden" name="page" value="'.$page.'">'.PHP_EOL;
  if ($showblradio) {
    echo '<input type="hidden" name="blrad" value="'.$blradio.'">'.PHP_EOL;
  }
  echo '<input type="text" name="s" id="search" placeholder="site.com" value="'.$searchbox.'">&nbsp;&nbsp;';
  echo '<input type="Submit" value="Search">'.PHP_EOL;
  echo '</form></div>'.PHP_EOL;                            //End form for Text Search
  
  echo '<div class="sys-group">';                          //Now for the results

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
  $linkstr = ($searchbox == '' ? '' : "s=$searchbox&amp;");
  $linkstr .= ($showblradio ? "blrad=$blradio" : '');
  
  pagination($result->num_rows, $linkstr);
    
  echo '<table id="block-table">'.PHP_EOL;
  echo '<tr><th>#</th><th>Block List</th><th>Domain</th><th>Comment</th></tr>'.PHP_EOL;
   
  while($row = $result->fetch_assoc()) {                   //Read each row of results
    $domain = $row['site'];

    //Create clipboard image and text
    $clipboard = '<div class="icon-clipboard" onclick="setClipboard(\''.$domain.'\')" title="Copy domain">&nbsp;</div>';

    if ($row['site_status'] == 0) {                        //Is site enabled or disabled?
      $row_class = ' class="dark"';
    }
    else {
      $row_class = '';
    }
    
    //Convert abbreviated bl_name to friendly name
    if (array_key_exists($row['bl_source'], $config::BLOCKLISTNAMES)) {
      $bl_source = $config::BLOCKLISTNAMES[$row['bl_source']];
    }
    else {
      $bl_source = $row['bl_source'];
    }

    //Output table row
    echo "<tr{$row_class}><td>{$i}</td><td>{$bl_source}</td><td>{$domain}{$clipboard}</td><td>{$row['comment']}</td></tr>".PHP_EOL;
    
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
//-------------------------------------------------------------------

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

if (isset($_GET['s'])) {                                   //Search box
  //Allow only alphanumeric . - _
  $searchbox = preg_replace('/[^\w\.\-_]/', '', $_GET['s']);
  $searchbox = strtolower($searchbox);
}

if (isset($_GET['page'])) {
  $page = filter_integer($_GET['page'], 1, PHP_INT_MAX, 1);
}

if (isset($_POST['showblradio'])) {
  if ($_POST['showblradio'] == 1) {
    $showblradio = true;
  }
}

if (isset($_GET['blrad'])) {
  if ($_GET['blrad'] == 'all') {                           //All isn't actually a blocklist name
    $blradio = 'all';
    $showblradio = true;
  }
  elseif (array_key_exists($_GET['blrad'], $config::BLOCKLISTNAMES)) {
    $blradio = $_GET['blrad'];
    $showblradio = true;
  }
}

echo '<div id="main">'.PHP_EOL;

show_full_blocklist();
draw_copymsg();
?>

</div>
</body>
</html>
