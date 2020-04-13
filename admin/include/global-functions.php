<?php
//Global Functions used in NoTrack Admin

/********************************************************************
 *  Draw Sys Table
 *    Start off a sys-group table
 *  Params:
 *    Title
 *  Return:
 *    None
 */ 
function draw_systable($title) {
  echo '<div class="sys-group">'.PHP_EOL;
  echo '<h5>'.$title.'</h5>'.PHP_EOL;
  echo '<table class="sys-table">'.PHP_EOL;
  
  return null;
}


/********************************************************************
 *  Draw Sys Table
 *    Start off a sys-group table
 *  Params:
 *    Description, Value
 *  Return:
 *    None
 */
function draw_sysrow($description, $value) {
  echo '<tr><td>'.$description.': </td><td>'.$value.'</td></tr>'.PHP_EOL;
  
  return null;
}

/********************************************************************
 *  Draw Copy Message
 *    Div for Domain Copied Message
 *  Params:
 *    None
 *  Return:
 *    None
 */
function draw_copymsg() {
  echo '<div id="copymsg">'.PHP_EOL;
  echo 'Domain copied to clipboard'.PHP_EOL;
  echo '</div>'.PHP_EOL;
}

/********************************************************************
 *  Activate Session
 *    Create login session
 *  Params:
 *    None
 *  Return:
 *    None
 */
function activate_session() {  
  $_SESSION['session_expired'] = false;
  $_SESSION['session_start'] = time();  
}

function ensure_active_session() {
  if (is_password_protection_enabled()) {
    session_start();
    if (isset($_SESSION['session_start'])) {
      if (!is_active_session()) {
        $_SESSION['session_expired'] = true;
        header('Location: /admin/login.php');
        exit;
      }
    }
    else {
      header('Location: /admin/login.php');
      exit;
    }
  }
}


function is_active_session() {
  $session_duration = 1800;
  if (isset($_SESSION['session_start'])) {
    if ((time() - $_SESSION['session_start']) < $session_duration) {
      return true;
    }
  }
  return false;
}

function is_password_protection_enabled() {
  global $config;
  
  if ($config->settings['Password'] != '') return true;
  return false;
}


/********************************************************************
 *  Compare Version possibly DEPRECATED
 *    1. Split strings by '.'
 *    2. Combine back together and multiply with Units array
 *    e.g 1.0 - 1x10000 + 0x100 = 10,000
 *    e.g 0.8.0 - 0x10000 + 8x100 + 0x1 = 800
 *    e.g 0.7.10 - 0x10000 + 7x100 + 10x1 = 710
 *  Params:
 *    Version
 *  Return:
 *    true if latestversion >= currentversion, or false if latestversion < currentversion
 */
function compare_version($latestversion) {
  //If LatestVersion is less than Current Version then function returns false
  
  $numversion = 0;
  $numlatest = 0;
  $units = array(10000,100,1);
  
  $splitversion = explode('.', VERSION);
  $splitlatest = explode('.', $latestversion);
  
  for ($i = 0; $i < count($splitversion); $i++) {
    $numversion += ($units[$i] * intval($splitversion[$i]));
  }
  for ($i = 0; $i < count($splitlatest); $i++) {
    $numlatest += ($units[$i] * intval($splitlatest[$i]));
  }
  
  if ($numlatest < $numversion) return false;
  
  return true;
}

/********************************************************************
 *  Count rows in table
 *
 *  Params:
 *    SQL Query
 *  Return:
 *    Number of Rows
 */
function count_rows($query) {
  global $db;
  
  $rows = 0;
  
  if(!$result = $db->query($query)){
    die('count_rows() error running the query '.$db->error);
  }
    
  $rows = $result->fetch_row()[0];                         //Extract value from array
  $result->free();
  
  return $rows;
}

/********************************************************************
 *  Extract Domain
 *    Extract domain with optional double-barrelled tld
 *  Params:
 *    URL to check
 *  Return:
 *    Filtered domain
 */
function extract_domain($url) {
  $regex_domain = '/[\w\d\-\_]+\.(org\.|co\.|com\.|gov\.)?[\w\d\-]+$/';
  $regex_suppressed_domain = '/^(\*\.)([\w\d\-\_]+\.(org\.|co\.|com\.|gov\.)?[\w\d\-]+)$/';
  
  if (preg_match($regex_suppressed_domain, $url, $matches)) {
    return $matches[2];
  }
  preg_match($regex_domain, $url, $matches);

  return $matches[0];
}



/********************************************************************
 *  Filter Boolean Value
 *    Checks if value given is 'true' or 'false'
 *  Params:
 *    Value to Check
 *  Return:
 *    true or false
 */
function filter_bool($value) {
  if (($value == 'true') || ($value == '1')) {
    return true;
  }
  else {
    return false;
  }
}


/********************************************************************
 *  Filter Domain
 *    1. Check Domain length (must be less than 253 chars)
 *    2. Perform regex match to see if domain is in the form of some-site.com, or some_site.co.uk
 *
 *  Regex:
 *    Group 1: *. (optional)
 *    Group 2: subdomain(s) (optional)
 *    Group 3: domain 1 to 63 chars
 *    Group 4: TLD 2 to 63 chars
 *  Params:
 *    Domain to check
 *  Return:
 *    True on success, False on failure
 */
function filter_domain($domain) {
  if (strlen($domain) > 253) {
    return false;
  }

  if (preg_match('/^(\*\.)?([\w\-_\.]+)?[\w\-_]{1,63}\.[\w\-]{2,63}$/', $domain) > 0) {
    return true;
  }
  else {
    return false;
  }
}


/********************************************************************
 *  Filter Integer Value
 *    Checks if Integer value given is between min and max
 *  Params:
 *    Value to Check, Minimum, Maximum, Default Value
 *  Return:
 *    value on success, default value on fail
 */
function filter_integer($value, $min, $max, $defaultvalue=0) {
  if (is_numeric($value)) {
    if (($value >= $min) && ($value <= $max)) {
      return intval($value);
    }
  }

  return $defaultvalue;
}


/********************************************************************
 *  Filter MAC Address
 *    Checks value is a valid colon-seperated MAC address
 *  Params:
 *    String to check
 *  Return:
 *    True on success, False on failure
 */
function filter_macaddress($str) {
  if (preg_match('/^([\dA-Fa-f]{2}:){5}[\dA-Fa-f]{2}$/', $str)) {
    return true;
  }

  return false;
}



/********************************************************************
 *  Filter String
 *    Checks if $var exists in POST or GET array
 *    Check strlen
 *
 *  Params:
 *    Array value to check, method - POST / GET, max string length
 *  Return:
 *    true on acceptable value
 *    false for unacceptable value
 */
function filter_string($var, $method, $maxlen=255) {
  if ($method == 'POST') {
    if (isset($_POST[$var])) {
      if (strlen($_POST[$var]) <= $maxlen) {
        return true;
      }
    }
  }

  elseif ($method == 'GET') {
    if (isset($_GET[$var])) {
      if (strlen($_GET[$var]) <= $maxlen) {
        return true;
      }
    }
  }

  return false;
}


/********************************************************************
 *  Filter URL
 *    perform regex match to see if url is in the form of some-site.com, or some_site.co.uk
 *
 *  Regex:
 *    Group 1: FTP or HTTP
 *    Group 2: Domain
 *    Group 3: URI
 *  Params:
 *    URL to check
 *  Return:
 *    True on success, False on failure
 */
function filter_url($url) {
  if (preg_match('/^(ftp|http?s):\/\/([\w\-_\.]+)\/?[\w\.\-\?&=]*$/', $url) > 0) {
    return true;
  }
  else {
    return false;
  }
}
/********************************************************************
 *  Format Number
 *    Returns a number rounded to 3 significant figures
 *  Params:
 *    Number 
 *  Return:
 *    Number rounded to 3sf
 */
function formatnumber($number) {
  if ($number < 1000) return $number;
  elseif ($number < 10000) return number_format($number / 1000, 2).'k';
  elseif ($number < 100000) return number_format($number / 1000, 1).'k';
  elseif ($number < 1000000) return number_format($number / 1000, 0).'k';
  elseif ($number < 10000000) return number_format($number / 1000000, 2).'M';
  elseif ($number < 100000000) return number_format($number / 1000000, 1).'M';
  elseif ($number < 1000000000) return number_format($number / 1000000, 0).'M';
  elseif ($number < 10000000000) return number_format($number / 1000000000, 2).'G';
  elseif ($number < 100000000000) return number_format($number / 1000000000, 1).'G';
  elseif ($number < 1000000000000) return number_format($number / 1000000000, 0).'G';
  
  return number_format($number / 1000000000000, 0).'T';
}


/********************************************************************
 *  Pluralise
 *
 *  Params:
 *    count, text
 *  Return:
 *    pluralised string
 */
function pluralise($count, $text)
{
  return $count.(($count == 1) ? (" {$text}") : (" {$text}s"));
}

/********************************************************************
 *  Simplified Time
 *    Returns a simplified time from now - $timestr
 *
 *  Params:
 *    date-time string
 *  Return:
 *    A simplified time string
 */
function simplified_time($timestr) {
  $datetime = new DateTime($timestr);
  $interval = date_create('now')->diff($datetime);

  $suffix = ($interval->invert ? ' ago' : '');
  if ($interval->y >= 1 ) return pluralise($interval->y, 'year').$suffix;
  if ($interval->m >= 1 ) return pluralise($interval->m, 'month').$suffix;
  if ($interval->d > 7) return pluralise(floor($interval->d / 7), 'week').$suffix;
  if ($interval->d >= 1) return pluralise($interval->d, 'day').$suffix;
  if ($interval->h >= 1 ) return pluralise($interval->h, 'hour').$suffix;
  if ($interval->i >= 1 ) return pluralise($interval->i, 'minute').$suffix;
  return pluralise($interval->s, 'second').$suffix;
}


/********************************************************************
 *  Is Active Class
 *    Used to allocate class="active" against li
 *
 *  Params:
 *    Current View, Item
 *  Return:
 *    class="active" or nothing when inactive
 */
function is_active_class($currentview, $item) {
  return ($currentview == $item) ? ' class="active"' : '';
}


/********************************************************************
 *  Is Checked
 *    Used to in forms to determine if tickbox should be checked
 *  Params:
 *    value
 *  Return:
 *    checked="checked" or nothing
 */
 function is_checked($value) {
  if ($value == 1 || $value === true) {
    return ' checked="checked"';
  }
  
  return '';
}


/********************************************************************
 *  Is Commented
 *    Used in config files to check if Regex group 1 (start of line) is a # comment
 *  Params:
 *    value
 *  Return:
 *    false if value is #, or true for nothing
 */
 function is_commented($value) {
  if ($value == '#') {
    return false;
  }
  
  return true;
}

/********************************************************************
 *  Pagination
 *  
 *  Draw up to 7 buttons
 *  Main [<] [1] [x] [x+1] [L] [>]
 *  Or   [ ] [1] [2] [>]
 *
 *  Params:
 *    rows
 *    $linktext = text for a href
 *  Return:
 *    None
 */
function pagination($totalrows, $linktext) {
  global $page;

  $numpages = 0;
  $currentpage = 0;
  $startloop = 0;
  $endloop = 0;
  
  if ($totalrows > ROWSPERPAGE) {                     //Is Pagination needed?
    $numpages = ceil($totalrows / ROWSPERPAGE);       //Calculate List Size
    
    echo '<div class="pag-nav"><ul>'.PHP_EOL;
  
    if ($page == 1) {                            // [ ] [1]
      echo '<li><span>&nbsp;&nbsp;</span></li>'.PHP_EOL;
      echo '<li class="active"><a href="?page=1&amp;'.$linktext.'">1</a></li>'.PHP_EOL;
      $startloop = 2;
      if ($numpages > 4)  $endloop = $page + 4;
      else $endloop = $numpages;
    }
    else {                                       // [<] [1]
      echo '<li><a href="?page='.($page-1).'&amp;'.$linktext.'">&#x00AB;</a></li>'.PHP_EOL;
      echo '<li><a href="?page=1&amp;'.$linktext.'">1</a></li>'.PHP_EOL;
      
      if ($numpages < 5) {
        $startloop = 2;                          // [1] [2] [3] [4] [L]
      }
      elseif (($page > 2) && ($page > $numpages -4)) {
        $startloop = ($numpages - 3);            //[1]  [x-1] [x] [L]
      }
      else {
        $startloop = $page;                      // [1] [x] [x+1] [L]
      }
      
      if (($numpages > 3) && ($page < $numpages - 2)) {
        $endloop = $page + 3;                    // [y] [y+1] [y+2] [y+3]
      }
      else {
        $endloop = $numpages;                    // [1] [x-2] [x-1] [y] [L]
      }
    }
    
    for ($i = $startloop; $i < $endloop; $i++) { //Loop to draw 3 buttons
      if ($i == $page) {
        echo '<li class="active"><a href="?page='.$i.'&amp;'.$linktext.'">'.$i.'</a></li>'.PHP_EOL;
      }
      else {
        echo '<li><a href="?page='.$i.'&amp;'.$linktext.'">'.$i.'</a></li>'.PHP_EOL;
      }
    }
    
    if ($page == $numpages) {                    // [Final] [ ]
      echo '<li class="active"><a href="?page='.$numpages.'&amp;'.$linktext.'">'.$numpages.'</a></li>'.PHP_EOL;
      echo '<li><span>&nbsp;&nbsp;</span></li>'.PHP_EOL;
    }    
    else {                                       // [Final] [>]
      echo '<li><a href="?page='.$numpages.'&amp;'.$linktext.'">'.$numpages.'</a></li>'.PHP_EOL;
      echo '<li><a href="?page='.($page+1).'&amp;'.$linktext.'">&#x00BB;</a></li>'.PHP_EOL;
    }
    
  echo '</ul></div>'.PHP_EOL;  
  }
}


/********************************************************************
 *  Check SQL Table Exists
 *    Uses LIKE to check for table name in order to avoid error message.
 *  Params:
 *    SQL Table
 *  Return:
 *    True if table exists
 *    False if table does not exist
 */
function table_exists($table) {
  global $db;
  $exists = false;
  
  $result = $db->query("SHOW TABLES LIKE '$table'");
  
  if ($result->num_rows == 1) { 
    $exists = true;
  }
  
  $result->free();
  return $exists;
}


/********************************************************************
 *  Draw Line Chart
 *    Draws background line chart using SVG
 *    1. Calulate maximum values of input data for $ymax
 *    2. Draw grid lines
 *    3. Draw axis labels
 *    4. Draw coloured graph lines
 *    5. Draw coloured circles to reduce sharpness of graph line
 *
 *  Params:
 *    $values1 - array 1, $values2 array 2, $xlabels array
 *  Return:
 *    None
 */
function linechart($values1, $values2, $xlabels, $link_labels, $extraparams, $title) {
  $jump = 0;
  $max_value = 0;
  $ymax = 0;
  $xstep = 0;
  $pathout = '';
  $numvalues = 0;
  $x = 0;
  $y = 0;

//Prepare chart
  $max_value = max(array(max($values1), max($values2)));
  $numvalues = count($values1);
  $values1[] = 0;                                          //Ensure line returns to 0
  $values2[] = 0;                                          //Ensure line returns to 0
  $xlabels[] = $xlabels[$numvalues-1];                     //Increment xlables

  $xstep = 1900 / $numvalues;                              //Calculate x axis increment
  if ($max_value < 200) {                                  //Calculate y axis maximum
    $ymax = (ceil($max_value / 10) * 10) + 10;             //Change offset for low values
  }
  elseif ($max_value < 10000) {
    $ymax = ceil($max_value / 100) * 100;
  }
  else {
    $ymax = ceil($max_value / 1000) * 1000;
  }

  $jump = floor($numvalues / 12);                          //X Axis label and line gap

  echo '<div class="linechart-container">'.PHP_EOL;        //Start Chart container
  echo '<h2>'.$title.'</h2>';
  echo '<svg width="100%" height="100%" viewbox="0 0 2000 760">'.PHP_EOL;

  //Axis line rectangle with rounded corners
  echo '<rect class="axisline" width="1900" height="701" x="100" y="0" rx="5" ry="5" />'.PHP_EOL;

  for ($i = 0.25; $i < 1; $i += 0.25) {                    //Draw Y Axis lables and (horizontal lines)
    echo '<path class="gridline" d="M100,'.($i*700).' H2000" />'.PHP_EOL;
    echo '<text class="axistext" x="8" y="'.(18+($i*700)).'">'.formatnumber((1-$i)*$ymax).'</text>'.PHP_EOL;
  }
  echo '<text x="8" y="705" class="axistext">0</text>';
  echo '<text x="8" y="38" class="axistext">'.formatnumber($ymax).'</text>';
  
  
  for ($i = 0; $i < $numvalues; $i += $jump) {             //Draw X Axis and labels (vertical lines)
    echo '<text x="'.(60+($i * $xstep)).'" y="746" class="axistext">'.$xlabels[$i].'</text>'.PHP_EOL;
    echo '<path class="gridline" d="M'.(100+($i*$xstep)).',2 V700" />'.PHP_EOL;
  }
  
  draw_graphline($values1, $xstep, $ymax, '#00b7ba');
  draw_graphline($values2, $xstep, $ymax, '#b1244a');

  //Draw circles over line points in order to smooth the apperance
  for ($i = 1; $i < $numvalues; $i++) {
    $x = 100 + (($i) * $xstep);                            //Calculate X position

    if ($values1[$i] > 0) {                                //$values1[] (Allowed)
      $y = 700 - (($values1[$i] / $ymax) * 700);           //Calculate Y position of $values1
      echo '<a href="./queries.php?datetime='.$link_labels[$i].$extraparams.'&amp;groupby=time&amp;sort=ASC" target="_blank">'.PHP_EOL;
      echo '  <circle cx="'.$x.'" cy="'.(700-($values1[$i]/$ymax)*700).'" r="10px" fill="#00b7ba" fill-opacity="1" stroke="#EAEEEE" stroke-width="4px">'.PHP_EOL;
      echo '    <title>'.$xlabels[$i].' '.$values1[$i].' Allowed</title>'.PHP_EOL;
      echo '  </circle>'.PHP_EOL;
      echo '</a>'.PHP_EOL;
    }

    if ($values2[$i] > 0) {                                //$values2[] (Blocked)
      $y = 700 - (($values2[$i] / $ymax) * 700);           //Calculate Y position of $values2
      echo '<a href="./queries.php?datetime='.$link_labels[$i].$extraparams.'&amp;groupby=time&amp;sort=ASC&amp;filter=B" target="_blank">'.PHP_EOL;
      echo '  <circle cx="'.$x.'" cy="'.(700-($values2[$i]/$ymax)*700).'" r="10px" fill="#b1244a" fill-opacity="1" stroke="#EAEEEE" stroke-width="4px">'.PHP_EOL;
      echo '    <title>'.$xlabels[$i].' '.$values2[$i].' Blocked</title>'.PHP_EOL;
      echo '  </circle>'.PHP_EOL;
      echo '</a>'.PHP_EOL;
    }
  }
  
  echo '</svg>'.PHP_EOL;                                   //End SVG
  echo '</div>'.PHP_EOL;                                   //End Chart container

}


/********************************************************************
 *  Draw Graph Line
 *    Calulates and draws the graph line using straight point-to-point notes
 *
 *  Params:
 *    $values array, x step, y maximum value, line colour
 *  Return:
 *    None
 */

function draw_graphline($values, $xstep, $ymax, $colour) {
  $path = '';
  $x = 0;                                                  //Node X
  $y = 0;                                                  //Node Y
  $numvalues = count($values);
  
  $path = "<path d=\"M 100,700 ";                          //Path start point
  for ($i = 1; $i < $numvalues; $i++) {
    $x = 100 + (($i) * $xstep);
    $y = 700 - (($values[$i] / $ymax) * 700);
    $path .= "L $x $y";
  }
  $path .= 'V700 " stroke="'.$colour.'" stroke-width="5px" fill="'.$colour.'" fill-opacity="0.15" />'.PHP_EOL;
  echo $path;
}


/********************************************************************
 *  Draw Pie Chart
 *    Credit to Branko: http://www.tekstadventure.nl/branko/blog/2008/04/php-generator-for-svg-pie-charts
 *    Modified by quidsup to write label values and percentages
 *  Params:
 *    array of labels, array of values, the centre coordinates x and y, radius of the piechart, colours
 *  Return:
 *    true on success, false on failure
 */
function piechart($labels, $data, $cx, $cy, $radius, $colours) {
  $chartelem = '';
  $total = 0;

  $max = count($data);
  
  if (max($data) == 0) return false;                       //Prevent divide by zero warning

  foreach ($data as $key=>$val) {
    $total += $val;
  }
  $deg = $total/360;                                       //one degree
  $jung = $total/2;                                        //necessary to test for arc type

  //Data for grid, circle, and slices
  $dx = $radius;                                           //Starting point:
  $dy = 0;                                                 //first slice starts in the East
  $oldangle = 0;

  for ($i = 0; $i<$max; $i++) {                            //Loop through the slices
    $chartelem = '';
    $angle = $oldangle + $data[$i]/$deg;                   //cumulative angle
    $x = cos(deg2rad($angle)) * $radius;                   //x of arc's end point
    $y = sin(deg2rad($angle)) * $radius;                   //y of arc's end point

    $colour = $colours[$i];

    if ($data[$i] > $jung) {                               //Does arc spans more than 180 degrees
      $laf = 1;
    }
    else {
      $laf = 0;
    }

    $ax = $cx + $x;                                        //absolute $x
    $ay = $cy + $y;                                        //absolute $y
    $adx = $cx + $dx;                                      //absolute $dx
    $ady = $cy + $dy;                                      //absolute $dy
    $chartelem .= "  <path d=\"M$cx,$cy ";                 //move cursor to center
    $chartelem .= " L$adx,$ady ";                          //draw line away away from cursor
    $chartelem .= " A$radius,$radius 0 $laf,1 $ax,$ay ";   //draw arc
    $chartelem .= " z\" ";                                 //z = close path
    $chartelem .= " fill=\"$colour\" stroke=\"#262626\" stroke-width=\"2\" ";
    $chartelem .= " fill-opacity=\"0.95\" stroke-linejoin=\"round\" />";
    $chartelem .= PHP_EOL;

    echo '<g>'.PHP_EOL;                                    //Start svg group
    echo $chartelem;                                       //Write chart element
    echo '  <title>'.$labels[$i].': '.number_format(($data[$i] / $total) * 100, 1).'%</title>'.PHP_EOL;
    echo '</g>'.PHP_EOL;                                   //End svg group

    $dx = $x;                                              //old end points become new starting point
    $dy = $y;                                              //id.
    $oldangle = $angle;
  }

  return true;
}

/********************************************************************
 *  Load latest version from settings folder
 *
 *  Params:
 *    None
 *  Return:
 *    true - latestversion.php loaded successfully
 *    false - latestversion.php missing
 */
function load_latestversion() {
  global $config;
  if (file_exists('./settings/latestversion.php')) {       //Attempt to load latestversion
    include_once './settings/latestversion.php';
    return true;
  }
  else {
    return false;
  }
}

?>
