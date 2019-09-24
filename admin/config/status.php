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


?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <link href="../css/master.css" rel="stylesheet" type="text/css">
  <link href="../css/icons.css" rel="stylesheet" type="text/css">
  <link rel="icon" type="image/png" href="../favicon.png">
  <script src="../include/menu.js"></script>
  <meta name="viewport" content="width=device-width, initial-scale=0.9">
  <title>NoTrack - Status</title>
</head>
<?php

/********************************************************************
 Main
*/
draw_topmenu('Status');
draw_sidemenu();

echo '<div id="main">'.PHP_EOL;

//Display output of notrack --test
echo '<div class="sys-group">'.PHP_EOL;
echo '<pre>'.PHP_EOL;
system('/usr/local/sbin/notrack --test');
echo '</pre>'.PHP_EOL;
echo '<div class="centered">'.PHP_EOL;
echo '<a href="./"><button>Back</button></a>'.PHP_EOL;
echo '</div>'.PHP_EOL;                                     //End Centered
echo '</div>'.PHP_EOL;                                     //End sys-group

echo '</div>'.PHP_EOL;                                     //End main
?>
</body>
</html>
