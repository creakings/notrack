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
 *  Show General View
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function show_general() {
  global $config;
  
  $key = '';
  $value = '';
  
  $sysload = sys_getloadavg();
  $freemem = preg_split('/\s+/', exec('free -m | grep Mem'));

  $pid_dnsmasq = preg_split('/\s+/', exec('ps -eo fname,pid,stime,pmem | grep dnsmasq'));

  $pid_lighttpd = preg_split('/\s+/', exec('ps -eo fname,pid,stime,pmem | grep lighttpd'));

  $Uptime = explode(',', exec('uptime'))[0];
  if (preg_match('/\d\d\:\d\d\:\d\d\040up\040/', $Uptime) > 0) $Uptime = substr($Uptime, 13);  //Cut time from string if it exists
  
  draw_systable('Server');
  draw_sysrow('Name', gethostname());
  draw_sysrow('Network Device', $config->settings['NetDev']);
  if (($config->settings['IPVersion'] == 'IPv4') || ($config->settings['IPVersion'] == 'IPv6')) {
    draw_sysrow('Internet Protocol', $config->settings['IPVersion']);
    draw_sysrow('IP Address', $_SERVER['SERVER_ADDR']);
  }
  else {
    draw_sysrow('IP Address', $config->settings['IPVersion']);
  }
  
  draw_sysrow('Sysload', $sysload[0].' | '.$sysload[1].' | '.$sysload[2]);
  draw_sysrow('Memory Used', $freemem[2].' MB');
  draw_sysrow('Free Memory', $freemem[3].' MB');
  draw_sysrow('Uptime', $Uptime);
  draw_sysrow('NoTrack Version', VERSION); 
  echo '</table></div>'.PHP_EOL;
  
  draw_systable('Dnsmasq');
  if ($pid_dnsmasq[0] != null) draw_sysrow('Status','Dnsmasq is running');
  else draw_sysrow('Status','Inactive');
  draw_sysrow('Pid', $pid_dnsmasq[1]);
  draw_sysrow('Started On', $pid_dnsmasq[2]);
  //draw_sysrow('Cpu', $pid_dnsmasq[3]);
  draw_sysrow('Memory Used', $pid_dnsmasq[3].' MB');
  draw_sysrow('Historical Logs', count_rows('SELECT COUNT(DISTINCT(DATE(log_time))) FROM dnslog').' Days');
  draw_sysrow('DNS Queries', number_format(count_rows('SELECT COUNT(*) FROM dnslog')));
  draw_sysrow('Delete All History', '<button class="button-danger" onclick="confirmLogDelete();">Purge</button>');
  echo '</table></div>'.PHP_EOL;

  
  //Web Server
  echo '<form name="blockmsg" action="?" method="post">';
  echo '<input type="hidden" name="action" value="webserver">';
  draw_systable('Lighttpd');
  if ($pid_lighttpd[0] != null) draw_sysrow('Status','Lighttpd is running');
  else draw_sysrow('Status','Inactive');
  draw_sysrow('Pid', $pid_lighttpd[1]);
  draw_sysrow('Started On', $pid_lighttpd[2]);
  //draw_sysrow('Cpu', $pid_lighttpd[3]);
  draw_sysrow('Memory Used', $pid_lighttpd[3].' MB');
  if ($config->settings['blockmessage'] == 'pixel') draw_sysrow('Block Message', '<input type="radio" name="block" value="pixel" checked onclick="document.blockmsg.submit()">1x1 Blank Pixel (default)<br><input type="radio" name="block" value="message" onclick="document.blockmsg.submit()">Message - Blocked by NoTrack<br>');
  else draw_sysrow('Block Message', '<input type="radio" name="block" value="pixel" onclick="document.blockmsg.submit()">1x1 Blank Pixel (default)<br><input type="radio" name="block" value="messge" checked onclick="document.blockmsg.submit()">Message - Blocked by NoTrack<br>');  
  echo '</table></div></form>'.PHP_EOL;

  
  //Stats
  echo '<form name="stats" method="post">';
  echo '<input type="hidden" name="action" value="stats">';
  
  draw_systable('Domain Stats');
  echo '<tr><td>Search Engine: </td>'.PHP_EOL;
  echo '<td><select name="search" class="input-conf" onchange="submit()">'.PHP_EOL;
  echo '<option value="'.$config->settings['Search'].'">'.$config->settings['Search'].'</option>'.PHP_EOL;
  foreach ($config::SEARCHENGINELIST as $key => $value) {
    if ($key != $config->settings['Search']) {
      echo '<option value="'.$key.'">'.$key.'</option>'.PHP_EOL;
    }
  }
  echo '</select></td></tr>'.PHP_EOL;
  
  echo '<tr><td>Who Is Lookup: </td>'.PHP_EOL;
  echo '<td><select name="whois" class="input-conf" onchange="submit()">'.PHP_EOL;
  echo '<option value="'.$config->settings['WhoIs'].'">'.$config->settings['WhoIs'].'</option>'.PHP_EOL;
  foreach ($config::WHOISLIST as $key => $value) {
    if ($key != $config->settings['WhoIs']) {
      echo '<option value="'.$key.'">'.$key.'</option>'.PHP_EOL;
    }
  }
  echo '</select></td></tr>'.PHP_EOL;
  draw_sysrow('JsonWhois API <a href="https://jsonwhois.com/"><div class="help-icon"></div></a>', '<input type="text" name="whoisapi" class="input-conf" value="'.$config->settings['whoisapi'].'">');
  echo '</table></div></form>'.PHP_EOL;                    //End Stats
  
  return null;
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
  echo '<a href="../admin/config.php?v=general"><img src="./svg/menu_config.svg"><span><h6>General</h6></span></a>'.PHP_EOL;
  echo '<a href="../admin/config.php?v=status"><img src="./svg/menu_status.svg"><span><h6>Back-end Status</h6></span></a>'.PHP_EOL;
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

  
/********************************************************************
 *  Show Back End Status
 *    Display output of notrack --test
 *  Params:
 *    None
 *  Return:
 *    None
 */
function show_status() {
  echo '<pre>'.PHP_EOL;
  system('/usr/local/sbin/notrack --test');
  echo '</pre>'.PHP_EOL;
}

?>
