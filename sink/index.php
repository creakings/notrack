<?php
$urllist = array();                                        //List of URLs
$paramlist = array();                                      //Sorted list of GET args

/********************************************************************
 *  Extract URLs
 *    Check for any URLs
 *    Strip tags from GET keys and values then store them in $paramlist
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function extract_urls() {
  global $paramlist, $urllist;
  $taglessvalue = '';                                      //Value with strip_tags

  foreach($_GET as $key => $value) {
    if ($key == 'ntrkorigin') continue;                    //Skip ntrkorigin

    $taglessvalue = strip_tags($value);                    //Prevent XSS
    $paramlist[strip_tags($key)] = $taglessvalue;          //Store parameter

    //Is the taglessvalue a URL?
    if (preg_match('/^https?:\/\/[\w-._]{3,256}(\/[\w-_.%\/]*)?(\?[\w%&=.:#@~$]*)?$/', $taglessvalue)) {
      if (! in_array($taglessvalue, $urllist)) {           //Prevent duplicates
        $urllist[] = $taglessvalue;                        //Add array to list
      }
    }
  }
}


/********************************************************************
 *  Draw the Extracted URLs
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function draw_urllist() {
  global $urllist;

  if (count($urllist) == 0) {                              //Any URLs extracted?
    return;
  }

  echo '<h4>Extracted URL&rsquo;s:</h4>'.PHP_EOL;
  echo '<ul>'.PHP_EOL;
  foreach($urllist as $url) {
    echo "<li><a href=\"{$url}\">{$url}</a></li>".PHP_EOL;
  }
  echo '</ul>'.PHP_EOL;
}


/********************************************************************
 *  Draw the Extracted Parameters
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function draw_paramlist() {
  global $paramlist;
  $i = 1;                                                  //Beautify

  echo '<h4>Parameter list:</h4>'.PHP_EOL;

  if (count($paramlist) == 0) {                            //Any parameters extracted?
    echo 'None specified<br>'.PHP_EOL;
    return;
  }

  ksort($paramlist);

  echo '<table>'.PHP_EOL;
  foreach($paramlist as $key => $value) {
    echo "<tr><td>{$i}</td><td>{$key}</td><td>{$value}</td></tr>".PHP_EOL;
    $i++;
  }
  echo '</table>'.PHP_EOL;
}
$host = $_SERVER['HTTP_HOST'];
$ntrkorigin = $_GET['ntrkorigin'] ?? '';

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <link href="./sink.css" rel="stylesheet" type="text/css">
  <meta name="viewport" content="width=device-width, initial-scale=0.9">
  <title>Blocked by NoTrack</title>
</head>
<body>
<div class="circle1"></div>
<div class="circle2"></div>
<div class="sys-group">

<?php
echo '<h1>Blocked by NoTrack</h1>'.PHP_EOL;
echo '<h2>Sorry about that</h2>'.PHP_EOL;
echo "<h3><a href=\"https://{$host}${ntrkorigin}\">{$host}${ntrkorigin}</a></h3>".PHP_EOL;

extract_urls();
draw_urllist();
draw_paramlist();

?>
</div>
</body>
</html>
