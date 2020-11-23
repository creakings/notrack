<?php
/********************************************************************
 *  Draw Side Menu
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function draw_sidemenu() {
  $alert_count = 0;
  $alert_count = sidemenu_get_alert_count();

  echo '<nav id="menu-side">'.PHP_EOL;
  echo '<a href="/admin/"><img src="/admin/svg/smenu_dashboard.svg" alt="" title="Dashboard">Dashboard</a>'.PHP_EOL;
  echo '<a href="/admin/queries.php"><img src="/admin/svg/smenu_queries.svg" alt="" title="DNS Queries">DNS Queries</a>'.PHP_EOL;
  echo '<a href="/admin/dhcp.php"><img src="/admin/svg/smenu_dhcp.svg" alt="" title="Network DHCP">Network</a>'.PHP_EOL;
  echo '<a href="/admin/live.php"><img src="/admin/svg/smenu_live.svg" alt="" title="Live">Live</a>'.PHP_EOL;

  //Only display an alert count if its above zero
  if ($alert_count == 0) {
    echo '<a href="/admin/analytics.php"><img src="/admin/svg/smenu_analytics.svg" alt="" title="Alerts">Alerts</a>'.PHP_EOL;
  }
  else {
    echo '<a href="/admin/analytics.php"><img src="/admin/svg/smenu_analytics.svg" alt="" title="Alerts"><div class="alert-count">'.formatnumber($alert_count).'</div>Alerts</a>'.PHP_EOL;
  }

  echo '<a href="/admin/investigate.php"><img src="/admin/svg/smenu_investigate.svg" alt="" title="Investigate">Investigate</a>'.PHP_EOL;
  echo '<a href="/admin/config"><img src="/admin/svg/smenu_config.svg" alt="" title="Config">Config</a>'.PHP_EOL;
  echo '<a href="/admin/help"><img src="/admin/svg/smenu_help.svg" alt="" title="Help">Help</a>'.PHP_EOL;

  sidemenu_sysstatus();

  echo '</nav>'.PHP_EOL;
}


/********************************************************************
 *  Draw Help Menu
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function draw_helpmenu() {
  echo '<nav id="menu-side">'.PHP_EOL;
  echo '<a href="/admin/"><img src="/admin/svg/smenu_dashboard.svg" alt="" title="Dashboard">Dashboard</a>'.PHP_EOL;
  echo '<a href="/admin/help/"><img src="/admin/svg/smenu_help.svg" alt="">Help</a>'.PHP_EOL;
  echo '<a href="/admin/help/?p=security">Security</a>'.PHP_EOL;
  echo '<a href="/admin/help/?p=position" title="Where To Position NoTrack Device">Positioning Device</a>'.PHP_EOL;
  echo '<a href="https://quidsup.net/donate" target="_blank"><img src="/admin/svg/smenu_don.svg" alt="Donate" title="Donate">Donate</a>'.PHP_EOL;

  echo '</nav>'.PHP_EOL;
}


/********************************************************************
 *  Draw Top Menu
 *    mobile-hide class is used to hide button text on mobile sized displays
 *
 *  Params:
 *    Current Page Title (optional)
 *  Return:
 *    None
 */
function draw_topmenu($currentpage='') {
  global $config, $mem;

  echo '<nav><div id="menu-top">'.PHP_EOL;
  echo '<span class="hamburger pointer mobile-show" onclick="openNav()">&#9776;</span>'.PHP_EOL;   //Hamburger menu to show #menu-side mobile-show

  if ($currentpage == '') {                                //Display version number when $currentpage has not been set
    echo '<a href="/admin/"><span id="menu-top-logo" class="logo"><b>No</b>Track <small>v'.VERSION.'</small></span></a>'.PHP_EOL;
  }
  else {                                                   //$currentpage set, display that next to NoTrack logo
    echo '<a href="/admin/"><span id="menu-top-logo" class="logo"><span class="mobile-hide"><b>No</b>Track - </span><small> '.$currentpage.'</small></span></a>'.PHP_EOL;
  }

  echo '<div id="pause-group">'.PHP_EOL;
  //echo '<input type="hidden" name="pause-time" id="pause-time" value="">'.PHP_EOL;
  if ($config->status & STATUS_PAUSED) {
    echo '<img id="pause-button" class="pointer" title="Resume Blocking" onclick="enableNoTrack()" src="/admin/svg/tmenu_play.svg" alt="">'.PHP_EOL;
  }
  elseif ($config->status & STATUS_DISABLED) {
    echo '<img id="pause-button" class="pointer" title="Resume Blocking" onclick="enableNoTrack()" src="/admin/svg/tmenu_play.svg" alt="">'.PHP_EOL;
  }
  else {
    echo '<img id="pause-button" class="pointer" title="Disable Blocking" onclick="enableNoTrack()" src="/admin/svg/tmenu_pause.svg" alt="">'.PHP_EOL;
  }

  //Dropdown menu for default pause times
  echo '<div tabindex="1" id="dropbutton" title="Pause for..."><img class="pointer" src="/admin/svg/tmenu_dropdown.svg" alt="">'.PHP_EOL;
  echo '<div id="pause-menu">'.PHP_EOL;
  echo '<span class="pointer" onclick="pauseNoTrack(5)">Pause for 5 minutes</span>'.PHP_EOL;
  echo '<span class="pointer" onclick="pauseNoTrack(15)">Pause for 15 minutes</span>'.PHP_EOL;
  echo '<span class="pointer" onclick="pauseNoTrack(30)">Pause for 30 minutes</span>'.PHP_EOL;
  echo '<span class="pointer" onclick="pauseNoTrack(60)">Pause for 1 Hour</span>'.PHP_EOL;
  echo '<span class="pointer" onclick="pauseNoTrack(120)">Pause for 2 Hours</span>'.PHP_EOL;
  echo '</div></div></div>'.PHP_EOL;


  echo '<div id="menu-top-group">';
  if ($config->status & STATUS_INCOGNITO) {              //Is Incognito set? Draw purple button and text
    echo '<img id="incognito-button" class="pointer" title="Incognito" onclick="menuIncognito()" src="/admin/svg/menu_incognito_active.svg" alt="">'.PHP_EOL;
  }
  else {                                                   //No, draw white button and text
    echo '<img id="incognito-button" class="pointer" title="Incognito" onclick="menuIncognito()" src="/admin/svg/menu_incognito.svg" alt="">'.PHP_EOL;
  }

  if ($config->is_password_protection_enabled()) {         //Show Logout button if there is a password
    echo '<a href="/admin/logout.php"><img title="Logout" src="/admin/svg/menu_logout.svg" alt=""></a>'.PHP_EOL;
  }

  echo '</div>'.PHP_EOL;
  echo '</div></nav>'.PHP_EOL;                             //End menu-top
}


/********************************************************************
 *  Side Menu Alert Count
 *    1. Attempt to load alert_count value from Memcache
 *    2. count_alert function is provided from $dbwrapper, but the current page may not contain that object
 *    3. Once obtained store the value in Memcache for 1 hour
 *
 *  Params:
 *    None
 *  Return:
 *    alert_count
 */
function sidemenu_get_alert_count() {
  global $dbwrapper, $mem;

  $alert_count = 0;

  $alert_count = $mem->get('alert_count');

  if (empty($alert_count)) {
    if (isset($dbwrapper)) {
      $alert_count = $dbwrapper->analytics_count();
      $mem->set('alert_count', $alert_count, 0, 3600);
    }
  }

  return $alert_count;
}

/********************************************************************
 *  Side Menu Status
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function sidemenu_sysstatus() {
  global $config;

  $sysload = sys_getloadavg();
  $freemem = preg_split('/\s+/', exec('free -m | grep Mem'));

  $mempercentage = round(($freemem[2]/$freemem[1])*100);

  echo '<div id="menu-side-status">'.PHP_EOL;              //Start menu-side-status
  echo '<div><img src="/admin/svg/status_screen.svg" alt="">System Status</div>';

  if ($config->status & STATUS_ENABLED) {
    if (file_exists(NOTRACK_LIST)) {
      echo '<div id="menu-side-blocking"><img src="/admin/svg/status_green.svg" alt="">Blocking: Enabled</div>'.PHP_EOL;
    }
    else {
      if (file_exists(NOTRACK_LIST)) {
        echo '<div id="menu-side-blocking"><img src="/admin/svg/status_red.svg" alt="">Blocklist Missing</div>'.PHP_EOL;
      }
    }
  }
  elseif ($config->status & STATUS_PAUSED) {
    echo '<div id="menu-side-blocking"><img src="/admin/svg/status_yellow.svg" alt="">Blocking: Paused - '.date('H:i', $config->unpausetime).'</div>'.PHP_EOL;
  }
  elseif ($config->status & STATUS_DISABLED) {
    echo '<div id="menu-side-blocking"><img src="/admin/svg/status_red.svg" alt="">Blocking: Disabled</div>'.PHP_EOL;
  }

  if ($mempercentage > 75) echo '<div><img src="/admin/svg/status_red.svg" alt="">Memory Used: '.$mempercentage.'%</div>'.PHP_EOL;
  elseif ($mempercentage > 60) echo '<div><img src="/admin/svg/status_yellow.svg" alt="">Memory Used: '.$mempercentage.'%</div>'.PHP_EOL;
  else echo '<div><img src="/admin/svg/status_green.svg" alt="">Memory Used: '.$mempercentage.'%</div>'.PHP_EOL;

  if ($sysload[0] > 0.85) echo '<div><img src="/admin/svg/status_red.svg" alt="">Load: ', $sysload[0].' | '.$sysload[1].' | '.$sysload[2].'</div>'.PHP_EOL;
  elseif ($sysload[0] > 0.60) echo '<div><img src="/admin/svg/status_yellow.svg" alt="">Load: ', $sysload[0].' | '.$sysload[1].' | '.$sysload[2].'</div>'.PHP_EOL;
  else echo '<div><img src="/admin/svg/status_green.svg" alt="">Load: ', $sysload[0].' | '.$sysload[1].' | '.$sysload[2].'</div>'.PHP_EOL;

  echo '</div>'.PHP_EOL;                                   //End menu-side-status
}
