<?php


/********************************************************************
 *  Show Advanced Page
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function show_advanced() {
  global $config;
  echo '<form action="?v=advanced" method="post">'.PHP_EOL;
  echo '<input type="hidden" name="action" value="advanced">';
  draw_systable('Advanced Settings');
  draw_sysrow('DNS Log Parsing Interval', '<input type="number" class="fixed10" name="parsing" min="1" max="60" value="'.$config->settings['ParsingTime'].'" title="Time between updates in Minutes">');
  draw_sysrow('Suppress Domains <div class="help-icon" title="Group together certain domains on the Stats page"></div>', '<textarea rows="5" name="suppress">'.str_replace(',', PHP_EOL, $config->settings['Suppress']).'</textarea>');
  echo '<tr><td>&nbsp;</td><td><input type="submit" value="Save Changes"></td></tr>'.PHP_EOL;
  echo '</table>'.PHP_EOL;
  echo '</div>'.PHP_EOL;
  echo '</form>'.PHP_EOL;
  
  //TODO Add reset
}




/********************************************************************
 *  Show Menu
 *    Show menu using a flexbox (conf-`nav) for each category
 *  Params:
 *    None
 *  Return:
 *    None
 */
function show_menu() {
  echo '<div class="sys-group">'.PHP_EOL;                 //Start System
  echo '<h5>System</h5>'.PHP_EOL;
  echo '<div class="conf-nav">'.PHP_EOL;
  echo '<a href="../admin/config/general.php"><img src="./svg/menu_config.svg"><span><h6>General</h6></span></a>'.PHP_EOL;
  echo '<a href="../admin/config/status.php"><img src="./svg/menu_status.svg"><span><h6>Back-end Status</h6></span></a>'.PHP_EOL;
  echo '<a href="../admin/security.php"><img src="./svg/menu_security.svg"><span><h6>Security</h6></span></a>'.PHP_EOL;
  echo '<a href="../admin/config/apisetup.php"><img src="./svg/menu_security.svg"><span><h6>API Setup</h6></span></a>'.PHP_EOL;
  echo '<a href="../admin/upgrade.php"><img src="./svg/menu_upgrade.svg"><span><h6>Upgrade</h6></span></a>'.PHP_EOL;
  echo '<a href="../admin/config.php?v=dnsmasq"><img src="./svg/menu_config.svg"><span><h6>Work in progress</h6></span></a>'.PHP_EOL;
  echo '</div></div>'.PHP_EOL;                             //End System
  
  echo '<div class="sys-group">'.PHP_EOL;                  //Start Block lists
  echo '<h5>Block Lists</h5>'.PHP_EOL;
  echo '<div class="conf-nav">'.PHP_EOL;
  echo '<a href="../admin/config/blocklists.php"><img src="./svg/menu_blocklists.svg"><span><h6>Select Block Lists</h6></span></a>'.PHP_EOL;
  echo '<a href="../admin/config/tld.php"><img src="./svg/menu_domain.svg"><span><h6>Top Level Domains</h6></span></a>'.PHP_EOL;
  echo '<a href="../admin/config/customblocklist.php?v=black"><img src="./svg/menu_black.svg"><span><h6>Custom Black List</h6></span></a>'.PHP_EOL;
  echo '<a href="../admin/config/customblocklist.php?v=white"><img src="./svg/menu_white.svg"><span><h6>Custom White List</h6></span></a>'.PHP_EOL;
  echo '<a href="../admin/config/domains.php"><img src="./svg/menu_sites.svg"><span><h6>View Domains Blocked</h6></span></a>'.PHP_EOL;
  echo '</div></div>'.PHP_EOL;                             //End Block lists
  
  echo '<div class="sys-group">'.PHP_EOL;                  //Advanced
  echo '<h5>Advanced</h5>'.PHP_EOL;
  echo '<div class="conf-nav">'.PHP_EOL;
  echo '<a href="../admin/config.php?v=advanced"><img src="./svg/menu_advanced.svg"><span><h6>Advanced Options</h6></span></a>'.PHP_EOL;
  echo '</div></div>'.PHP_EOL;                             //End Config
}


?>
