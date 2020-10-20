<?php
/********************************************************************
 *  Title : Login
 *  Description : Controls login for NoTrack, validating username and password,
 *                also throttles attemtps based on IP or username
 *  Author : QuidsUp
 *  Date : 2015-03-25, rewrite 2020-09-27
 *
 *  1. Start Session
 *  2. Check if session is already active then return to index.php
 *  3. Check if password is required
 *  3a. If not return to index.php (Otherwise you get trapped on this page)
 *  4. Has a password been sent with HTTP POST?
 *  4a. Check if delay is imposed in IP or username
 *      If yes then set $message to wait and don't evaluate logon attempt, jump to 5.
 *  4b. Use PHP password_verify function to check hashed version of user input with hash in $config->password
 *  4c. On failure log Delay on Memcache showing time and attempts.
 *      Show message of Incorrect Username or Password
 *      Add entry into error.log to allow functionality with Fail2ban
 *  5. Draw login form
 *  6. Draw box with $message (If its set)
 *  7. Draw hidden box informing user that Cookies must be enabled
 *  8. Use Javascript to check if Cookies have been enabled
 *     If Cookies are disabled then set 7. to Visible
 */
require('./include/global-vars.php');
require('./include/global-functions.php');
require('./include/config.php');

$message = '';
$password = '';
$username = '';

/********************************************************************
 *  Check IP Delay
 *    Check Memcache to see if a delay should be imposed against visitor IP
 *    Delay is last recorded time + no. attemps ^ 1.5
 *    This is lower as user may have accidentally typed wrong username
 *
 *  Params:
 *    Username (str): Username value to retrieve from Memcache
 *  Return:
 *    False: No delay should be imposed
 *    True: Impose delay
 */
function check_ip_delay($userip) {
  global $mem;

  if ($userip == '') {                                     //Can't proceed with blank
    return false;
  }

  //Attempt to load values from Memcache
  $time_userip = $mem->get($userip) ?? false;
  $attempts_userip = $mem->get("{$userip}_attempts") ?? 0;

  if ($time_userip === false) {                            //No delay set
    return false;
  }

  //Has delay passed?
  if (time() > ($time_userip + ($attempts_userip ** 1.5))) {
    return false;
  }

  return true;                                             //At this point impose a delay
}


/********************************************************************
 *  Check Username Delay
 *    Check Memcache to see if a delay should be imposed against username
 *    Delay is last recorded time + no. attemps squared
 *
 *  Params:
 *    Username (str): Username value to retrieve from Memcache
 *  Return:
 *    False: No delay should be imposed
 *    True: Impose delay
 */
function check_username_delay($username) {
  global $mem;

  if ($username == '') {                                   //Can't proceed with blank
    return false;
  }

  //Attempt to load values from Memcache
  $time_username = $mem->get($username) ?? false;
  $attempts_username = $mem->get("{$username}_attempts") ?? 0;

  if ($time_username === false) {                          //No delay set
    return false;
  }

  //Has delay passed?
  if (time() > ($time_username + ($attempts_username ** 2))) {
    return false;
  }

  return true;                                             //At this point impose a delay
}


/********************************************************************
 *  Set Delay against visitor IP
 *    Record current time and increase attempts
 *    Hold values for 600 seconds (10 mins)
 *  Params:
 *    UserIP (str): IP value to Set to from Memcache
 *  Return:
 *    None
 */
function delay_ip($userip) {
  global $mem;
  $memkey = '';                                            //Memcache name for attempts

  if ($userip == '') return;                               //Not dealing with blank vals

  $memkey = "{$userip}_attempts";

  if ($mem->get($userip)) {                                //Is userip value known?
    $mem->replace($userip, time(), 0, 600);                //Yes - Replace old time value
  }
  else {
    $mem->set($userip, time(), 0, 600);                    //No - Set a new time value
  }

  if ($mem->get($memkey)) {                                //Is user attempts value known?
    $mem->increment($memkey, 1);                           //Yes - Increase by 1
  }
  else {
    $mem->set($memkey, 1, 0, 600);
  }
}


/********************************************************************
 *  Set Delay against Username
 *    Record current time and increase attempts
 *    Hold values for 900 seconds (15 mins)
 *  Params:
 *    Username (str): Username value to Set to from Memcache
 *  Return:
 *    None
 */
function delay_username($username) {
  global $mem;
  $memkey = '';                                            //Memcache name for attempts

  if ($username == '') return;                             //Not dealing with blank vals

  $memkey = "{$username}_attempts";

  if ($mem->get($username)) {                              //Is username value known?
    $mem->replace($username, time(), 0, 900);              //Yes - Replace old time value
  }
  else {
    $mem->set($username, time(), 0, 900);                  //No - Set a new time value
  }

  if ($mem->get($memkey)) {                                //Is user attempts value known?
    $mem->increment($memkey, 1);                           //Yes - Increase by 1
  }
  else {
    $mem->set($memkey, 1, 0, 900);
  }
}
/********************************************************************
 *  Validate Password
 *    1. Check if delay should be imposed on IP
 *    2. Check if delay should be imposed on username
 *    3. Verify password
 *       Activate session and exit this page if correct
 *    4. Increase delay on IP and username if password is wrong
 *  Params:
 *    Username, Password, IP
 *  Return:
 *    message for error-box
 */
function validate_password($username, $password, $userip) {
  global $config;
  $message = '';

  if (check_ip_delay($userip)) {
    $message = 'Please Wait';
    return $message;
  }

  if (check_username_delay($username)) {
    $message = 'Please Wait';
    return $message;
  }

  //Use built in password_verify function to compare with $config->password hash
  if (($username == $config->username) and (password_verify($password, $config->password))) {
    activate_session();                                    //Set session to enabled
    header('Location: ./index.php');                       //Redirect to index.php
    exit;
  }
  else {
    if ($username != '') {
      //Output attempt to error.log
      trigger_error("Failed login from {$userip} with username {$username}", E_USER_NOTICE);
    }

    //Deny attacker knowledge of whether username OR password is wrong
    $message = 'Incorrect username or password';
  }

  delay_ip($userip);
  delay_username($username);

  return $message;
}
/********************************************************************/

//Leave if password protection is disabled
if (! $config->is_password_protection_enabled()) {
  header('Location: ./index.php');
  exit;
}

session_start();
//Leave is session is already active
if (is_active_session()) {
  header('Location: ./index.php');
  exit;
}

//Has a password been entered?
if (isset($_POST['password'])) {
  $username = $_POST['username'] ?? '';
  $password = $_POST['password'];
  $userip = $_SERVER['REMOTE_ADDR'];

  $username = strip_tags($username);                       //Reduce risk of XSS
  //Validate password and impose any delays
  $message = validate_password($username, $password, $userip);
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <link href="./css/login.css" rel="stylesheet" type="text/css">
  <link rel="icon" type="image/png" href="./favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>NoTrack Login</title>
</head>

<body>
<?php
if ($message != '') {                                      //Any Message to show?
  echo '<div id="error-box">'.PHP_EOL;
  echo '<h4>'.$message.'</h4>'.PHP_EOL;
  echo '</div>'.PHP_EOL;
}
?>

<div class="col-half">
  <div id="logo-box">
    <b>No</b>Track
  </div>
</div>

<div class="col-half">
<div id="login-box">
<form method="post" name="Login_Form">
<div class="centered"><input name="username" type="text" placeholder="Username" autofocus></div>
<div class="centered"><input name="password" type="password" placeholder="Password" required></div>
<div class="centered"><input type="submit" value="Login"></div>
</form>
</div>
</div>

<?php
echo '<div id="fade"></div>'.PHP_EOL;
echo '<div id="cookie-box">'.PHP_EOL;
echo '<h4 id="dialogmsg">Cookies need to be enabled</h4>'.PHP_EOL;
echo '</div>'.PHP_EOL;
?>

<script>
//has user disabled cookies for this site?
if (! navigator.cookieEnabled) {
  document.getElementById("cookie-box").style.display = "block";
  document.getElementById("fade").style.display = "block";
}
</script>
</body>
</html>
