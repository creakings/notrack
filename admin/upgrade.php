<?php
//There are two views for upgrade:
//1. Carrying out upgrade (dependant on POST['doupgrade']) which shows the result of ntrk-upgrade
//2. Version info, upgrade button, and curl output

require('./include/global-vars.php');
require('./include/global-functions.php');
require('./include/config.php');
require('./include/upgradenotifier.php');
require('./include/menu.php');

ensure_active_session();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <link href="./css/master.css" rel="stylesheet" type="text/css">
  <link rel="icon" type="image/png" href="./favicon.png">
  <script src="./include/menu.js"></script>
  <title>NoTrack - Upgrade</title>
</head>

<body>
<?php
draw_topmenu('Config');
draw_sidemenu();

echo '<div id="main">'.PHP_EOL;


draw_systable('NoTrack Upgrade');
if ($upgradenotifier->is_upgrade_available()) {
  draw_sysrow('Status', 'Running version v'.VERSION.'<br>Latest version available: v'.$upgradenotifier->latestversion);
}
else {
  draw_sysrow('Status', 'Running the latest version v'.VERSION);
}
echo '</table>'.PHP_EOL;
echo '<h3>Note:</h3>';
echo '<p>Due to security reasons, the ability to upgrade here has been removed. To manually upgrade, please run:<br>'.PHP_EOL;
echo '<code>cd ~/notrack/src</code> or <br>'.PHP_EOL;
echo '<code>cd /opt/notrack/src</code><br>'.PHP_EOL;
echo '<code>sudo python3 ntrkupgrade.py</code></p>'.PHP_EOL;
echo '</div>'.PHP_EOL;


//Display changelog
if (extension_loaded('curl')) {                          //Check if user has Curl installed
  $ch = curl_init();                                     //Initiate curl
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
  curl_setopt($ch, CURLOPT_URL,'https://gitlab.com/quidsup/notrack/raw/master/changelog.txt');
  $data = curl_exec($ch);                                //Download Changelog
  curl_close($ch);                                       //Close curl
  echo '<pre>'.PHP_EOL;
  echo $data;                                            //Display Changelog
  echo '</pre>'.PHP_EOL;
}

?>
</div>
</body>
</html>
