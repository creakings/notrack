<?php
require('./include/global-vars.php');
require('./include/global-functions.php');
require('./include/config.php');
require('./include/menu.php');

ensure_active_session();

//-------------------------------------------------------------------
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <link href="./css/master.css" rel="stylesheet" type="text/css">
  <link rel="icon" type="image/png" href="./favicon.png">
  <script src="./include/config.js"></script>
  <script src="./include/menu.js"></script>
  <meta name="viewport" content="width=device-width, initial-scale=0.8">
  <title>NoTrack - Security</title>  
</head>

<body>
<?php
draw_topmenu('Config');
draw_sidemenu();
echo '<div id="main">';

/************************************************
*Constants                                      *
************************************************/
define ('DEF_DELAY', 30);

/********************************************************************
 *  Disable Password Protection
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function disable_password_protection() {
  global $config;
  
  $config->settings['Username'] = '';
  $config->settings['Password'] = '';
  $config->save();
}

/********************************************************************
 *  Change Password Form
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function change_password_form() {
  echo '<form name="security" method="post">'.PHP_EOL;
  echo '<input type="hidden" name="change_password">'.PHP_EOL;
  echo '<table class="sys-table">'.PHP_EOL;
  
  draw_sysrow('Old Password', '<input type="password" name="old_password" id="password" placeholder="Old Password">');
  
  draw_sysrow('New Password', '<input type="password" name="password" id="password" placeholder="Password" onkeyup="checkPassword();" required>');
  draw_sysrow('Confirm Password', '<input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" onkeyup="checkPassword();">');
  
  echo '<tr><td colspan="2"><div class="centered"><input type="submit" value="Change Password"></div></td></tr>';
  
  echo '</table></form>'.PHP_EOL;
  
  echo '<table class="sys-table">'.PHP_EOL;
  echo '<tr><td colspan="2"><div class="centered"><form method="post"><input type="hidden" name="disable_password"><input type="submit" class="button-danger" value="Turn off password protection"></form></div></td></tr>'.PHP_EOL;
  echo '</table>'.PHP_EOL;
}

/********************************************************************
 *  New Password Input Form
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function new_password_input_form() {
  global $config;
  
  echo '<form name="security" method="post">';
  echo '<table class="sys-table">'.PHP_EOL;
    
  draw_sysrow('NoTrack Username', '<input type="text" name="username" value="'.$config->settings['Username'].'" placeholder="Username"><p><i>Optional authentication username</i></p>');
  
  draw_sysrow('NoTrack Password', '<input type="password" name="password" id="password" placeholder="Password" onkeyup="checkPassword();" required><p><i>Authentication password</i></p>');
  draw_sysrow('Confirm Password', '<input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" onkeyup="checkPassword();">');
  
  draw_sysrow('Delay', '<input type="number" class="fixed10" name="delay" min="5" max="2400" value="'.$config->settings['Delay'].'"><p><i>Delay in seconds between attempts</i></p>');
  echo '<tr><td colspan="2"><div class="centered"><input type="submit" value="Save Changes"></div></td></tr>';
  echo '</table></form>'.PHP_EOL;
}

/********************************************************************
 *  Update Password Config
 *
 *  Params:
 *    Username, either from POST or Existing
 *  Return:
 *    true on success, false on fail
 */
function update_password_config($username) {
  global $config, $message;
  
  $confirm_password = '';
  $password = $_POST['password'];
  if (isset($_POST['confirm_password'])) $confirm_password = $_POST['confirm_password'];
  
  
  //Is username valid?
  if (preg_match('/[!\"£\$%\^&\*\(\)\[\]+=<>:\,\|\/\\\\]/', $username) != 0) {
    $message = 'Invalid Username';
    return false;
  }
  
  if ($password != $confirm_password) {                              //Does validate password match?
    $message = 'Passwords don\'t match';
    return false;
  }
  
  if (($username == '') && ($password == '')) {                      //Removing password
    $config->settings['Username'] = '';
    $config->settings['Password'] = '';
  }
  else {  
    $config->settings['Username'] = $username;
    $config->settings['Password'] = password_hash($password, PASSWORD_DEFAULT);
    
    if (isset($_POST['delay'])) {                                    //Set Delay
      $config->settings['Delay'] = filter_integer($_POST['delay'], 5, 2401, DEF_DELAY);
    }
    else {                                                           //Fallback if Delay not posted
      $config->settings['Delay'] = DEF_DELAY;
    }
  }
  
  return true;
}

/********************************************************************
 *  Validate Old Password
 *
 *  Params:
 *    None
 *  Return:
 *    true on success, false on fail
 */
 
function validate_oldpassword() {
  global $config;
  
  if (! isset($_POST['old_password'])) return false;                 //Has old password been entered?
  
  if (password_verify($_POST['old_password'], $config->settings['Password'])) {
    return true;
  }
  
  return false;
}
//-------------------------------------------------------------------
$show_password_input_form = false;
$show_button_on = true;
$message = '';

if (isset($_POST['enable_password'])) {
  $show_password_input_form = true;
  $show_button_on = false;
}
elseif (isset($_POST['change_password']) && (isset($_POST['password']))) {
  if (validate_oldpassword()) {
    if (update_password_config($config->settings['Username'])) {
      $config->save();
      $message = 'Password Changed';
    }
  }
  else {
    $message = 'Old password incorrect';
  }
  $show_button_on = false;
}
elseif (isset($_POST['disable_password'])) {
  disable_password_protection();
  $show_password_input_form = false;
  $message = 'Password Protection Removed';
  if (session_status() == PHP_SESSION_ACTIVE) session_destroy();
}
elseif ((isset($_POST['username']) && (isset($_POST['password'])))) {
  if (update_password_config($_POST['username'])) {
    $config->save();
    if (session_status() == PHP_SESSION_ACTIVE) session_destroy();   //Force logout
    $message = 'Password Protection Enabled';
    $show_button_on = false;
  }  
}

echo '<div class="sys-group">'.PHP_EOL;
echo '<h5>Security&nbsp;<a href="./help.php?p=security"><div class="help-icon"></div></a></h5>'.PHP_EOL;

if (is_password_protection_enabled()) {
  change_password_form();
  
  $show_password_input_form = false;
  $show_button_on = false;
}

if ($show_button_on) {
  echo '<form method="post"><input type="hidden" name="enable_password"><input type="submit" value="Turn on password protection"></form>'.PHP_EOL;
}

if ($show_password_input_form) {
  new_password_input_form();
}


if ($message != '') {
  echo '<br>'.PHP_EOL;
  echo '<h3>'.$message.'</h3>'.PHP_EOL;
}
  
echo '</div>'.PHP_EOL;
echo '</div>'.PHP_EOL;
?>

<script>
function checkPassword() {
  if (document.getElementById('password').value == document.getElementById('confirm_password').value) {
    document.getElementById('confirm_password').style.background='#00BB00';
  }
  else {
    document.getElementById('confirm_password').style.background='#B1244A';
  }
}
</script>
</body>
</html>
