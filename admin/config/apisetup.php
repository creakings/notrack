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


/************************************************
*Arrays                                         *
************************************************/


/********************************************************************
 *  Draw Form
 *    Form items
 *  Params:
 *    None
 *  Return:
 *    None
 */
function draw_form() {
  global $config;

  echo '<form method="POST">'.PHP_EOL;
  
  echo '<div class="sys-group">'.PHP_EOL;                  //Start sys-group box
  echo '<h5>API Setup</h5>'.PHP_EOL;
  echo '<table class="sys-table">'.PHP_EOL;
  echo '<tr><td>API Key</td><td><input type="text" name="apikey" id="apikey" value="'.$config->settings['api_key'].'">&nbsp;<button class="button-grey icon-generate" type="button" onclick="generateKey(\'apikey\')">Generate</button></td></tr>'.PHP_EOL;
  echo '<tr><td>Read Only</td><td><input type="text" name="apireadonly" id="apireadonly" value="'.$config->settings['api_readonly'].'">&nbsp;<button class="button-grey icon-generate" type="button" onclick="generateKey(\'apireadonly\')">Generate</button></td></tr>'.PHP_EOL;
  
  echo '<tr><td colspan="2"><div class="centered"><button class="icon-tick" type="submit">Save Changes</button></div></td></tr>'.PHP_EOL;
  
  echo '</table>'.PHP_EOL;
  
  echo '</div>'.PHP_EOL;                                   //End sys-group box
  echo '</form>'.PHP_EOL;
}


/********************************************************************
 *  Save Changes
 *    1. Check POST vars have been set
 *    2. Carry out input validation using regex
 *    3. Failed input validation results in Config settings remaining unchanged
 *    4. Save Config array to file
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function save_changes() {
  global $config;

  $apikey = '';
  $apireadonly = '';

  //Use null coalescing operator in PHP 7 to check if POST vars have been set
  $apikey = $_POST['apikey'] ?? '';
  $apireadonly = $_POST['apireadonly'] ?? '';

  //Carry out input validation of apikey
  if (preg_match(REGEX_VALIDAPI, $apikey)) {
    $config->settings['api_key'] = $apikey;
  }

  //Carry out input validation of apireadonly
  if (preg_match(REGEX_VALIDAPI, $apireadonly)) {
    $config->settings['api_readonly'] = $apireadonly;
  }

  $config->save();
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
  <title>NoTrack - API Setup</title>
</head>
<?php

/********************************************************************
 Main
*/
draw_topmenu('API Setup');
draw_sidemenu();

echo '<div id="main">'.PHP_EOL;

if (sizeof($_POST) > 0) {                                  //Anything in POST to process?
  save_changes();
}

draw_form();

echo '</div>'.PHP_EOL;
?>
<script>
/********************************************************************
 *  Buffer 2 Hex
 *    Convert a Uint8Array array into hexadecimal characters,
 *     including padding any single chars, e.g. 15 = f is padded to 0f
 *  Params:
 *    A Uint8Array
 *  Return:
 *    Hex string
 */
function buf2hex(buffer) {
  return Array.prototype.map.call(new Uint8Array(buffer), x => ('00' + x.toString(16)).slice(-2)).join('');
}


/********************************************************************
 *  Generate Key
 *    Use getRandomValues crypto function to generate a 20 byte array of random ints
 *     (20 byte is used as it will look like a 40 character SHA1 Hash)
 *    Appropriate textbox is filled in with hex version of the number array
 *  Params:
 *    Textbox name
 *  Return:
 *    None
 */
function generateKey(textbox) {
  let numarray = new Uint8Array(20);                       //20 byte (40) character = SHA1

  window.crypto.getRandomValues(numarray);                 //Random 8 bit ints

  document.getElementById(textbox).value = buf2hex(numarray);
}
</script>
</body>
</html>
