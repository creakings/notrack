<?php
require('./include/global-vars.php');
require('./include/global-functions.php');
require('./include/menu.php');
load_config();
ensure_active_session();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <link href="./css/master.css" rel="stylesheet" type="text/css">
  <link href="./css/chart.css" rel="stylesheet" type="text/css">
  <link rel="icon" type="image/png" href="./favicon.png">
  <script src="./include/menu.js"></script>
  <script src="./include/queries.js"></script>
  <title>NoTrack - Sites Blocked</title>
</head>

<body>
<?php
draw_topmenu('Sites Blocked');
draw_sidemenu();

/************************************************
*Constants                                      *
************************************************/
//Chart colours from: http://godsnotwheregodsnot.blogspot.co.uk/2013/11/kmeans-color-quantization-seeding.html
$CHARTCOLOURS = array('#FFFF00', '#1CE6FF', '#FF34FF', '#FF4A46', '#008941', '#006FA6', '#A30059', '#FFDBE5', '#7A4900', '#0000A6', '#63FFAC', '#B79762', '#004D43', '#8FB0FF', '#997D87', '#5A0007', '#809693', '#FEFFE6', '#1B4400', '#4FC601', '#3B5DFF', '#4A3B53',  '#DDEFFF', '#000035', '#7B4F4B', '#A1C299', '#300018', '#0AA6D8', '#013349', '#00846F');


/************************************************
*Global Variables                               *
************************************************/
$page = 1;
$view = 'group';
$sort = 'DESC';
$last = 1;                                       //SQL Interval Time
$unit = 'DAY';                                   //SQL Interval Unit

$db = new mysqli(SERVERNAME, USERNAME, PASSWORD, DBNAME);


/************************************************
*Arrays                                         *
************************************************/



/********************************************************************
 *  Add Date Vars to SQL Search
 *    Draw Sub Navigation menu
 *  Params:
 *    None
 *  Return:
 *    None
 */
function draw_subnav() {
  global $view;
  
  echo '<div class="sys-group">'.PHP_EOL;
  echo '<h5>Sites Blocked</h5>'.PHP_EOL;
  echo '<div class="pag-nav">'.PHP_EOL;
  echo '<ul>'.PHP_EOL;
  echo '<li'.is_active_class($view, 'group').'><a class="pag-exwide" href="?view=group">Group</a></li>'.PHP_EOL;
  echo '<li'.is_active_class($view, 'time').'><a class="pag-exwide" href="?view=time">Time</a></li>'.PHP_EOL;
  //echo '<li><a'.is_active_class($view, 'ref').' href="?view=ref">Referrer</a></li>'.PHP_EOL;
  echo '<li'.is_active_class($view, 'visualisation').'><a class="pag-exwide" href="?view=vis">Visualisation</a></li>'.PHP_EOL;
  echo '</ul>'.PHP_EOL;
  echo '</div>'.PHP_EOL;
  echo '</div>'.PHP_EOL;
}

/********************************************************************
 *  Get User Agent 
 *    Identifies OS and Browser
 *    1. Find OS using various regex matches
 *    2. Find Browser string using regex to match with corresponding image file
 *  Params:
 *    UserAgent String
 *  Return:
 *    OS and Browser
 */
function get_useragent($user_agent) {
  $matches = array();
  $ua = array('unknown', 'unknown');
  
  //Find OS
  
  //Android----------------------------
  //  •Group 1 - Mozilla or Dalvik
  //  •Group 2 - U (optional)
  if (preg_match('/^(Mozilla|Dalvik)\/\d\.\d\.?\d?\s\(Linux;\s(U;\s)?Android/', $user_agent, $matches) > 0) {
    $ua[0] = 'android';
  }
  //Windows----------------------------
  //  •Group 1 - compatible;
  //  •Group 2 - MSIE version
  //  •Group 3 - NT Kernel version
  elseif (preg_match('/^Mozilla\/\d\.\d\s\((compatible;\s)?(MSIE\s\d\.\d;\s)?Windows\sNT\s(\d\d?)\./', $user_agent, $matches) > 0) {
    $ua[0] = 'windows';
    if (isset($matches[2])) {                              //Internet Explorer old versions are in Group 2
      if (substr($matches[2], 0, 4) == 'MSIE') {
        $ua[1] = 'internet-explorer';
        return $ua;
      }
    }
    if (strpos($user_agent, 'Edge') !== false) {           //No group 2 will mean Edge or IE 11
      $ua[1] = 'edge';
      return $ua;
    }
    elseif (strpos($user_agent, 'Trident') !== false) {    //Trident = IE unless Edge is specifically mentioned
      $ua[1] = 'internet-explorer';
      return $ua;
    }
  }
  //Apple------------------------------
  elseif (preg_match('/^Mozilla\/\d\.\d\s\(iPad|iPhone|Macintosh/', $user_agent, $matches) > 0) {
    $ua[0] = 'apple';
    if (strpos($user_agent, 'Safari') !== false) {         //TODO Confirm this
      $ua[1] = 'Safari';
      return $ua;
    }
  }
  //Linux------------------------------
  //  •Group1 - X11 or Wayland
  elseif (preg_match('/^Mozilla\/\d\.\d\s\((X11|Wayland);/', $user_agent, $matches) > 0) {
    $ua[0] = 'linux';
  }
  //Microsoft Metadata retrieval-------
  elseif (preg_match('/^MICROSOFT_DEVICE_METADATA_RETRIEVAL_CLIENT$/', $user_agent, $matches) > 0) {
    $ua = array('windows', 'microsoft');
    return $ua;
  }
  //Microsoft Windows Update-----------
  elseif (preg_match('/^Windows\-Update\-Agent\/\d/', $user_agent, $matches) > 0) {
    $ua = array('windows', 'upgrade');
    return $ua;
  }
  //Python-----------------------------
  elseif(preg_match('/^Python\-urllib\/\d\.\d\d?/', $user_agent, $matches) > 0) {
    $ua = array('unknown', 'python');
  }
  //Avast Antivirus--------------------
  elseif (preg_match('/^avast!\s/', $user_agent, $matches) > 0) {
    $ua = array('windows', 'avast');
    return $ua;
  }
  
  //Try and find agent-----------------
  if (preg_match('/(Firefox|Chromium|OPR|Epiphany|Brave|Colibri|Midori|Min|Vivaldi)\/(\d[\d\.]\d?)/', $user_agent, $matches) > 0) {
    $ua[1] = strtolower($matches[1]);
  }
  //Many browsers contain Chrome as a user agent, so we need to search for Chrome after the above regex
  elseif (preg_match('/(Chrome)\/(\d\d\d?)/', $user_agent, $matches) > 0) {
    $ua[1] = strtolower($matches[1]);
  }

  //TODO SeaMonkey, Palemoon, and Waterfox are included with Firefox in UA string
  //TODO add Spotify Spotify/105800573 Win32/0 (PC laptop) Spotify/8.4.9 Android/25 (Nexus 5X)
  return $ua;
}

/********************************************************************
 *  Hightlight URL
 *    Highlight site, similar to browser behaviour
 *    Full Group 1: http / https / ftp
 *    Non-capture group to remove www.
 *    Full Group 2: Domain
 *    Full Group 3: URI Path
 *    Domain Group 1: Site
 *    Domain Group 2: Optional .gov, .org, .co, .com
 *    Domain Group 3: Top Level Domain
 *
 *    Merge final string together with Full Group 1, Full Group 2 - Length Domain, Domain (highlighted black), Full Group 3
 *  Params:
 *    URL
 *  Return:
 *    html formatted string 
 */
function highlight_url($url) {
  $highlighted =  $url;
  $full = array();
  $domain = array();
    
  if (preg_match('/^(https?:\/\/|ftp:\/\/)?(?:www\.)?([^\/]+)?(.*)$/', $url, $full) > 0) {
    if (preg_match('/([\w\d\-\_]+)\.(co\.|com\.|gov\.|org\.)?([\w\d\-\_]+)$/', $full[2], $domain) > 0) {
      $highlighted = '<span class="gray">'.$full[1].substr($full[2], 0, 0 -strlen($domain[0])).'</span>'.$domain[0].'<span class="gray">'.$full[3].'</span>';
    }
  }
  return $highlighted;
}

/********************************************************************
 *  Show Access Table
 *    
 *  Params:
 *    None
 *  Return:
 *    True on results found
 */
function show_accesstable() {
  global $db, $page, $sort, $view;
  
  $rows = 0;
  $http_method = '';
  $referrer = '';
  $query = '';
  $remote_host = '';
  $table_row = '';
  $user_agent = '';
  $user_agent_array = array();
    
  echo '<div class="sys-group">'.PHP_EOL;
  if ($view == 'group') {                                  //Group view
    echo '<h6>Sorted by Unique Site</h6>'.PHP_EOL;
    $rows = count_rows('SELECT COUNT(DISTINCT site) FROM weblog');
    if ((($page-1) * ROWSPERPAGE) > $rows) $page = 1;
    
    $query = 'SELECT * FROM weblog GROUP BY site ORDER BY UNIX_TIMESTAMP(log_time) '.$sort.' LIMIT '.ROWSPERPAGE.' OFFSET '.(($page-1) * ROWSPERPAGE);
  }
  elseif ($view == 'time') {                               //Time View
    echo '<h6>Sorted by Time last seen</h6>'.PHP_EOL;
    $rows = count_rows('SELECT COUNT(*) FROM weblog');
    if ((($page-1) * ROWSPERPAGE) > $rows) $page = 1;

    $query = 'SELECT * FROM weblog ORDER BY UNIX_TIMESTAMP(log_time) '.$sort.' LIMIT '.ROWSPERPAGE.' OFFSET '.(($page-1) * ROWSPERPAGE);
  }

  if(!$result = $db->query($query)){
    echo '<h4><img src=./svg/emoji_sad.svg>Error running query</h4>'.PHP_EOL;
    echo 'show_accesstable: '.$db->error;
    echo '</div>'.PHP_EOL;
    die();
  }
  
  if ($result->num_rows == 0) {                            //Leave if nothing found
    $result->free();
    echo '<h4><img src=./svg/emoji_sad.svg>No Results Found</h4>'.PHP_EOL;
    echo '</div>';
    return false;
  }
  
  pagination($rows, 'view='.$view);                        //Draw pagination buttons
  
  echo '<table id="access-table">'.PHP_EOL;                //Start table
  echo '<tr><th>Date Time</th><th>Method</th><th>User Agent</th><th>Site</th></tr>'.PHP_EOL;
  
  while($row = $result->fetch_assoc()) {                   //Read each row of results
    if ($row['http_method'] == 'GET') {                    //Colour HTTP Method
      $http_method = '<span class="green">GET</span>';
    }
    else {
      $http_method = '<span class="violet">POST</span>';
    }

    $referrer = $row['referrer'];
    $user_agent = $row['user_agent'];
    $remote_host = $row['remote_host'];

    $user_agent_array = get_useragent($user_agent);        //Get OS and Browser from UserAgent
    
    //Build up the table row
    $table_row = '<tr><td>'.$row['log_time'].'</td><td>'.$http_method.'</td>';
    
    //DEBUG $table_row .='<td title="'.$user_agent.'"><div class="centered"><img src="./images/useragent/'.$user_agent_array[0].'.png" alt=""><img src="./images/useragent/'.$user_agent_array[1].'.png" alt="">'.$user_agent.'</div></td>';
    $table_row .='<td title="'.$user_agent.'"><div class="centered"><img src="./images/useragent/'.$user_agent_array[0].'.png" alt=""><img src="./images/useragent/'.$user_agent_array[1].'.png" alt=""></div></td>';
    
    $table_row .= '<td>'.highlight_url(htmlentities($row['site'].$row['uri_path'])).'<br>Referrer: '.highlight_url(htmlentities($referrer)).'<br>Requested By: '.$remote_host.'</td></tr>';
    
    echo $table_row.PHP_EOL;                               //Echo the table row
  }
  
  echo '</table><br>'.PHP_EOL;                             //End of table
  pagination($rows, 'view='.$view);                        //Draw pagination buttons
  echo '</div>'.PHP_EOL;                                   //End Sys-group div
  
  $result->free();

  return true;
}


/********************************************************************
 *  Show Visualisation
 *    
 *  Params:
 *    None
 *  Return:
 *    True on results found
 */
function show_visualisation() {
  global $CHARTCOLOURS, $db, $last, $unit;
  
  $site_names = array();
  $site_count = array();
  $total = 0;
  $other = 0;
  $numsites = 0;
  
  echo '<div class="sys-group">'.PHP_EOL;
  echo '<h6>Visualisation</h6>'.PHP_EOL;
  
  echo '<div class="pag-nav"><ul>'.PHP_EOL;
  echo '<li'.is_active_class($last.$unit, '1HOUR').'><a href="?view=vis&amp;last=1hour">1 Hour</a></li>'.PHP_EOL;
  echo '<li'.is_active_class($last.$unit, '4HOUR').'><a href="?view=vis&amp;last=4hour">4 Hours</a></li>'.PHP_EOL;
  echo '<li'.is_active_class($last.$unit, '8HOUR').'><a href="?view=vis&amp;last=8hour">8 Hours</a></li>'.PHP_EOL;
  echo '<li'.is_active_class($last.$unit, '1DAY').'><a href="?view=vis&amp;last=1day">1 Day</a></li>'.PHP_EOL;
  echo '<li'.is_active_class($last.$unit, '7DAY').'><a href="?view=vis&amp;last=7day">7 Days</a></li>'.PHP_EOL;
  echo '</ul></div>'.PHP_EOL;
  
  
  $total = count_rows('SELECT COUNT(*) FROM weblog WHERE log_time >= (NOW() - INTERVAL '.$last.' '.$unit.')');
  
  $query = 'SELECT site, COUNT(*) AS count FROM weblog WHERE log_time >= (NOW() - INTERVAL '.$last.' '.$unit.') GROUP BY site ORDER BY count DESC LIMIT 20';
  
  if(!$result = $db->query($query)){
    echo '<h4><img src=./svg/emoji_sad.svg>Error running query</h4>'.PHP_EOL;
    echo 'show_visualisation: '.$db->error;
    echo '</div>'.PHP_EOL;
    die();
  }
  
  if ($result->num_rows == 0) {                            //Leave if nothing found
    $result->free();
    echo '<h4><img src=./svg/emoji_sad.svg>No Results Found</h4>'.PHP_EOL;
    echo '</div>';
    return false;
  }

  while($row = $result->fetch_assoc()) {                   //Read each row of results
    $site_names[] = $row['site'];
    $site_count[] = $row['count'];
    $other += $row['count'];
  }
  
  $other = $total - $other;
  
  if ($other > 10) {                                       //Is it worth adding other?
    $site_names[] = 'Other';
    $site_count[] = $other;
  }
  
  $numsites = count($site_names);
  
  echo '<div class="piechart-container">'.PHP_EOL;
  echo '<svg width="100%" height="90%" viewbox="0 0 1500 1100">'.PHP_EOL;
  piechart($site_names, $site_count, 500, 540, 490, $CHARTCOLOURS);
  echo '<circle cx="500" cy="540" r="90" stroke="#00000A" stroke-width="2" fill="#f7f7f7" />'.PHP_EOL;   //Small overlay circle
  
  for ($i = 0; $i < $numsites; $i++) {
    echo '<rect x="1015" y="'.(($i*43)+90).'" rx="5" ry="5" width="38" height="38" style="fill:'.$CHARTCOLOURS[$i].'; stroke:#00000A; stroke-width=3" />';
    echo '<text x="1063" y="'.(($i*43)+118).'" style="font-family: Arial; font-size: 22px; fill:#00000A">'.$site_names[$i].': '.number_format(floatval($site_count[$i])).'</text>'.PHP_EOL;
  }
  
  echo '</svg>'.PHP_EOL;
  echo '</div>'.PHP_EOL;                                   //End piechart-container
  echo '</div>'.PHP_EOL;                                   //End Sys-group div
  
  $result->free();

  return true;
}

//Main---------------------------------------------------------------

/************************************************
*GET REQUESTS                                   *
************************************************/
if (isset($_GET['view'])) {
  switch($_GET['view']) {
    case 'group': $view = 'group'; break;
    case 'time': $view = 'time'; break;
    case 'vis': $view = 'visualisation'; break;
  }
}

if (isset($_GET['page'])) {
  $page = filter_integer($_GET['page'], 1, PHP_INT_MAX, 1);
}

if (isset($_GET['last'])) {
  $lastmatches = array();
  
  if (preg_match('/^(\d\d?)(hour|day|week)$/', $_GET['last'], $lastmatches) > 0) {
    $last = intval($lastmatches[1]);
    $unit = strtoupper($lastmatches[2]);
  }
  unset($lastmatches);
}
//Start of page------------------------------------------------------
echo '<div id="main">';

draw_subnav();

if (($view == 'group') || ($view == 'time'))  {
  show_accesstable();
}
elseif ($view == 'visualisation') {
  show_visualisation();
}

?>
</div>
<div id="scrollup" class="button-scroll" onclick="scrollToTop()"><img src="./svg/arrow-up.svg" alt="up"></div>
<div id="scrolldown" class="button-scroll" onclick="scrollToBottom()"><img src="./svg/arrow-down.svg" alt="down"></div>
</body>
</html>
