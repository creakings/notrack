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
<title>NoTrack - Config</title>
</head>
<?php


/********************************************************************
 Main
*/
draw_topmenu('Config');
draw_sidemenu();

echo '<div id="main">'.PHP_EOL;

echo '<section id="system">'.PHP_EOL;                      //Start System
echo '<div class="sys-group">'.PHP_EOL;
echo '<h5>System</h5>'.PHP_EOL;
echo '<div class="conf-nav">'.PHP_EOL;
echo '<a href="./general.php"><img src="../svg/menu_config.svg"><span><h6>General</h6></span></a>'.PHP_EOL;
//echo '<a href="./status.php"><img src="../svg/menu_status.svg"><span><h6>Back-end Status</h6></span></a>'.PHP_EOL;
echo '<a href="./security.php"><img src="../svg/menu_security.svg"><span><h6>Security</h6></span></a>'.PHP_EOL;
echo '<a href="./apisetup.php"><img src="../svg/menu_security.svg"><span><h6>API Setup</h6></span></a>'.PHP_EOL;
echo '<a href="../../admin/upgrade.php"><img src="../svg/menu_upgrade.svg"><span><h6>Upgrade</h6></span></a>'.PHP_EOL;
echo '<a href="../config/dns.php"><img src="../svg/menu_config.svg"><span><h6>DNS</h6></span></a>'.PHP_EOL;
echo '</div></div>'.PHP_EOL;
echo '</section>'.PHP_EOL;                                 //End System

echo '<section id="blocklists">'.PHP_EOL;                  //Start Block lists
echo '<div class="sys-group">'.PHP_EOL;
echo '<h5>Block Lists</h5>'.PHP_EOL;
echo '<div class="conf-nav">'.PHP_EOL;
echo '<a href="./blocklists.php"><img src="../svg/menu_blocklists.svg"><span><h6>Select Block Lists</h6></span></a>'.PHP_EOL;
echo '<a href="./tld.php"><img src="../svg/menu_domain.svg"><span><h6>Top Level Domains</h6></span></a>'.PHP_EOL;
echo '<a href="./customblocklist.php?v=black"><img src="../svg/menu_black.svg"><span><h6>Custom Black List</h6></span></a>'.PHP_EOL;
echo '<a href="./customblocklist.php?v=white"><img src="../svg/menu_white.svg"><span><h6>Custom White List</h6></span></a>'.PHP_EOL;
echo '<a href="./domains.php"><img src="../svg/menu_sites.svg"><span><h6>View Domains Blocked</h6></span></a>'.PHP_EOL;
echo '</div></div>'.PHP_EOL;
echo '</section>'.PHP_EOL;                                 //End Block lists

echo '</div>'.PHP_EOL;                                     //End main
?>

</body>
</html>
