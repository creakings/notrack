<?php
/********************************************************************
config.php handles setting of Global variables, GET, and POST requests
It also houses the functions for POST requests.

All other config functions are in ./include/config-functions.php

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
//$dbwrapper = new MySqliDb;
$page = 1;
$searchbox = '';
$showblradio = false;
$blradio = 'all';
$db = new mysqli(SERVERNAME, USERNAME, PASSWORD, DBNAME);


/************************************************
*Arrays                                         *
************************************************/
$list = array();                                 //Global array for all the Block Lists


/********************************************************************
 *  Add Search Box String to SQL Search
 *
 *  Params:
 *    None
 *  Return:
 *    SQL Query string
 */
function add_searches() {
  global $blradio, $searchbox;
  $searchstr = '';
  
  if (($blradio != 'all') && ($searchbox != '')) {
    $searchstr = ' WHERE site LIKE \'%'.$searchbox.'%\' AND bl_source = \''.$blradio.'\' ';
  }
  elseif ($blradio != 'all') {
    $searchstr = ' WHERE bl_source = \''.$blradio.'\' ';
  }
  elseif ($searchbox != '') {
    $searchstr = ' WHERE site LIKE \'%'.$searchbox.'%\' ';
  }
  
  return $searchstr;
}

/********************************************************************
 *  Draw Blocklist Radio Form
 *    Radio list is made up of the items in config::BLOCKLISTNAMES array
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function draw_blradioform() {
  global $config, $showblradio, $blradio, $page, $searchbox;
  
  if ($showblradio) {                            //Are we drawing Form or Show button?
    echo '<form name = "blradform" method="GET">'.PHP_EOL;   //Form for Radio List
    echo '<input type="hidden" name="v" value="full">'.PHP_EOL;
    echo '<input type="hidden" name="page" value="'.$page.'">'.PHP_EOL;
    if ($searchbox != '') {
      echo '<input type="hidden" name="s" value="'.$searchbox.'">'.PHP_EOL;
    }    
  
    if ($blradio == 'all') {
      echo '<span class="blradiolist"><input type="radio" name="blrad" value="all" checked="checked" onclick="document.blradform.submit()">All</span>'.PHP_EOL;
    }
    else {
      echo '<span class="blradiolist"><input type="radio" name="blrad" value="all" onclick="document.blradform.submit()">All</span>'.PHP_EOL;
    }
  
    foreach ($config::BLOCKLISTNAMES as $key => $value) { //Use BLOCKLISTNAMES for Radio items
      if ($key == $blradio) {                    //Should current item be checked?
        echo '<span class="blradiolist"><input type="radio" name="blrad" value="'.$key.'" checked="checked" onclick="document.blradform.submit()">'.$value.'</span>'.PHP_EOL;
      }
      else {
        echo '<span class="blradiolist"><input type="radio" name="blrad" value="'.$key.'" onclick="document.blradform.submit()">'.$value.'</span>'.PHP_EOL;
      }
    }
  }  
  else {                                         //Draw Show button instead
    echo '<form action="?v=full&amp;page='.$page.'" method="POST">'.PHP_EOL;
    echo '<input type="hidden" name="showblradio" value="1">'.PHP_EOL;
    echo '<input type="submit" value="Select Block List">'.PHP_EOL;
  }
  
  echo '</form>'.PHP_EOL;                        //End of either form above
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
  global $config, $db, $page, $searchbox, $blradio, $showblradio;

  $i = 0;
  $k = 1;                                                  //Count within ROWSPERPAGE
  $key = '';
  $value ='';
  $rows = 0;
  $row_class = '';
  $bl_source = '';
  $linkstr = '';
    
  echo '<div class="sys-group">'.PHP_EOL;
  echo '<h5>Sites Blocked</h5>'.PHP_EOL;
      
  $query = 'SELECT * FROM blocklist '.add_searches().'ORDER BY id';

  if(!$result = $db->query($query)) {
    echo '<h4><img src=./svg/emoji_sad.svg>Error running query</h4>'.PHP_EOL;
    echo 'show_full_blocklist: '.$db->error;
    echo '</div>'.PHP_EOL;
    return;
  }
    
  draw_blradioform();                                      //Block List selector form
  
  echo '<form method="GET">'.PHP_EOL;                      //Form for Text Search
  echo '<input type="hidden" name="page" value="'.$page.'">'.PHP_EOL;
  echo '<input type="hidden" name="v" value="full">'.PHP_EOL;
  echo '<input type="hidden" name="blrad" value="'.$blradio.'">'.PHP_EOL;
  echo '<input type="text" name="s" id="search" value="'.$searchbox.'">&nbsp;&nbsp;';
  echo '<input type="Submit" value="Search">'.PHP_EOL;
  echo '</form></div>'.PHP_EOL;                            //End form for Text Search
  
  if ($result->num_rows == 0) {                            //Leave if nothing found
    $result->free();
    echo '<h4><img src=../svg/emoji_sad.svg>No domains found in Block List</h4>'.PHP_EOL;
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

  if ($showblradio) {                                      //Add selected blocklist to pagination link string
    $linkstr .= 'blrad='.$blradio;
  }  
  
  echo '<div class="sys-group">';                          //Now for the results
  pagination($result->num_rows, $linkstr);
    
  echo '<table id="block-table">'.PHP_EOL;
  echo '<tr><th>#</th><th>Block List</th><th>Site</th><th>Comment</th></tr>'.PHP_EOL;
   
  while($row = $result->fetch_assoc()) {                   //Read each row of results
    if ($row['site_status'] == 0) {                        //Is site enabled or disabled?
      $row_class = ' class="dark"';
    }
    else {
      $row_class = '';
    }
    
    if (array_key_exists($row['bl_source'], $config::BLOCKLISTNAMES)) { //Convert bl_name to Actual Name
      $bl_source = $config::BLOCKLISTNAMES[$row['bl_source']];
    }
    else {
      $bl_source = $row['bl_source'];
    }
    echo '<tr'.$row_class.'><td>'.$i.'</td><td>'.$bl_source.'</td><td>'.$row['site'].'</td><td>'.$row['comment'].'</td></tr>'.PHP_EOL;
    
    $i++;
    $k++;
    if ($k > ROWSPERPAGE) break;
  }
  echo '</table>'.PHP_EOL;                                 //End of table
  
  echo '<br>'.PHP_EOL;
    pagination($result->num_rows, $linkstr);               //Draw second Pagination box
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
  <link href="../css/tabbed.css" rel="stylesheet" type="text/css">
  <link rel="icon" type="image/png" href="../favicon.png">
  <script src="../include/menu.js"></script>
  <meta name="viewport" content="width=device-width, initial-scale=0.9">
  <title>NoTrack - Domains Blocked</title>
</head>

<body>
<?php
draw_topmenu('Block Lists');
draw_sidemenu();

if (isset($_GET['s'])) {                         //Search box
  //Allow only characters a-z A-Z 0-9 ( ) . _ - and \whitespace
  $searchbox = preg_replace('/[^a-zA-Z0-9\(\)\.\s\_\-]/', '', $_GET['s']);
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
  if ($_GET['blrad'] == 'all') {
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
$db->close();
?>

</div>
</body>
</html>
