<?php
//Title : Login
//Description : Controls loin for NoTrack, validating username and password, and throttling password attemtps
//Author : QuidsUp
//Date : 2015-03-25


//1. Start Session
//2. Check if session is already active then return to index.php
//3. Check if password is required
//3a. If not return to index.php (Otherwise you get trapped on this page)
//4. Has a password been sent with HTTP POST?
//4a. Check if delay is imposed on Memcache variable 'Delay'
//4ai. If yes then set $message to wait and don't evaluate logon attempt, jump to 5.
//4b. Username is optional, check if it has been set in HTTP POST, otherwise set it to blank
//4c. Create access log file if it doesn't exist
//4d. Use PHP password_verify function to check hashed version of user input with hash in $config->password
//4ei. If username and password match set SESSION['sid'] to 1 (Future version may use a random number, to make it even harder to hijack a session)
//4eii. On failure write Delay into Memcache and show message of Incorrect Username or Password
//      Add entry into ntrk-access.log to allow functionality with Fail2ban
//      (Deny attacker knowledge of whether Username OR Password is wrong)

//5. Draw basic top menu
//6. Draw form login
//7. Draw box with $message (If its set)
//8. Draw hidden box informing user that Cookies must be enabled
//9. Use Javascript to check if Cookies have been enabled
//9a. If Cookies are disabled then set 8. to Visible

require('./include/global-vars.php');
require('./include/global-functions.php');
require('./include/config.php');

$message = '';
$password = '';
$username = '';

/********************************************************************
 *  Check IP Delay
 *    Check Memcache to see if a delay should be imposed against visitor IP
 *    Delay is last recorded time + no. attemps squared
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

  $calcdelay = ($time_userip + ($attempts_userip ** 2));
  $difference = $calcdelay - time();
  if (time() > $calcdelay) {                               //Has delay passed?
    return false;
  }
  echo "IP Fail {$attempts_userip}, Delay {$difference}";
  return true;
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

  $calcdelay = ($time_username + ($attempts_username ** 2));
  $difference = $calcdelay - time();
  if (time() > $calcdelay) {                               //Has delay passed?
    return false;
  }
  echo "User Fail {$attempts_username}, Delay {$difference}";
  return true;
}


/********************************************************************
 *  Set Delay against visitor IP
 *    Record current time and increase attempts
 *    Hold values for 900 seconds (15 mins)
 *  Params:
 *    UserIP (str): IP value to Set to from Memcache
 *  Return:
 *    None
 */
function delay_ip($userip) {
  global $mem;

  if ($userip == '') {
    return;
  }

  $memkey = "{$userip}_attempts";

  if ($mem->get($userip)) {                                //Is userip value known?
    $mem->replace($userip, time(), 0, 900);                //Yes - Replace old time value
  }
  else {
    $mem->set($userip, time(), 0, 900);                    //No - Set a new time value
  }

  if ($mem->get($memkey)) {                                //Is user attempts value known?
    $mem->increment($memkey, 1);                           //Yes - Increase by 1
  }
  else {
    $mem->set($memkey, 1, 0, 900);
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

  if ($username == '') {
    return;
  }

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
 *    Populates filter bar with Mark resolved
 *  Params:
 *    None
 *  Return:
 *    None
 */
function validate_password($username, $password, $userip) {
  global $config;
  $message = '';

  if (check_ip_delay($userip)) {
    $message = 'Unable to verify';
    return $message;
  }

  if (check_username_delay($username)) {
    $message = 'Unable to verify';
    return $message;
  }

  if (($username == $config->username) && (password_verify($password, $config->password))) {
    activate_session();                      //Set session to enabled
    header('Location: ./index.php');         //Redirect to index.php
    exit;
  }

  $message = "Incorrect username or password";   //Deny attacker knowledge of whether username OR password is wrong

  delay_ip($userip);
  delay_username($username);

  return $message;
}

if (! $config->is_password_protection_enabled()) {         //Leave if password protection disabled
  header('Location: ./index.php');
  exit;
}

session_start();
if (is_active_session()) {
  header('Location: ./index.php');
  exit;
}


if (isset($_POST['password'])) {
  $username = $_POST['username'] ?? '';
  $password = $_POST['password'];
  $userip = $_SERVER['REMOTE_ADDR'];

  $username = strip_tags($username);

  $message = validate_password($username, $password, $userip);
  /*if ($mem->get('delay')) {                      //Load Delay from Memcache
    $message = 'Wait';                           //If it is set then Wait
  }
  else {                                         //No Delay, check Password
    $password = $_POST['password'];


    //Use built in password_verify function to compare with $config->password hash




    //At this point the Password is Wrong
    $mem->set('delay', 10, 0, 10);
    $message = "Incorrect username or password";   //Deny attacker knowledge of whether username OR password is wrong

    //Output attempt to error.log
    trigger_error("Failed login from {$_SERVER['REMOTE_ADDR']} with username {$username}", E_USER_NOTICE);
  }*/
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
if ($message != '') {                            //Any Message to show?
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
if (! navigator.cookieEnabled) {                           //has user disabled cookies for this site?
  document.getElementById("cookie-box").style.display = "block";
  document.getElementById("fade").style.display = "block";
}
</script>
</body>
</html>
