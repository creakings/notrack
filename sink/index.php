<?php
$urllist = array();                                        //List of URLs
$paramlist = array();                                      //Sorted list of GET args

/********************************************************************
 *  Extract URLs
 *    Check for any URLs
 *    Strip tags from GET values and store them in $paramlist
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
    $paramlist[$key] = $taglessvalue;                      //Store sorted parameter

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

  echo 'Extracted URL&rsquo;s:<br>'.PHP_EOL;
  foreach($urllist as $url) {
    echo "<a href=\"{$url}\">{$url}</a><br>".PHP_EOL;
  }
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

  echo 'Parameter list:<br>'.PHP_EOL;

  if (count($paramlist) == 0) {                            //Any parameters extracted?
    echo 'None specified<br>'.PHP_EOL;
    return;
  }

  ksort($paramlist);

  foreach($paramlist as $key => $value) {
    echo "{$i}: {$key} - {$value}<br>".PHP_EOL;
    $i++;
  }
}
$host = $_SERVER['HTTP_HOST'];
$ntrkorigin = $_GET['ntrkorigin'] ?? '';

echo '<!DOCTYPE html>'.PHP_EOL;
echo '<h1>Blocked by NoTrack</h1>'.PHP_EOL;
echo '<h3>Sorry about that</h3>'.PHP_EOL;
echo "Host: $host<br>";
echo "Folder: $ntrkorigin<br>";
echo '<br>'.PHP_EOL;
extract_urls();
draw_urllist();
echo '<br>'.PHP_EOL;
draw_paramlist();
echo '<br>'.PHP_EOL;
echo '</html>'.PHP_EOL;
?>
