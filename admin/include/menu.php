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
  echo '<nav><div id="menu-side">'.PHP_EOL;
  echo '<a href="/admin/"><img src="/admin/svg/smenu_dashboard.svg" alt="" title="Dashboard">Dashboard</a>'.PHP_EOL;
  echo '<a href="/admin/queries.php"><img src="/admin/svg/smenu_queries.svg" alt="" title="DNS Queries">DNS Queries</a>'.PHP_EOL;
  echo '<a href="/admin/dhcp.php"><img src="/admin/svg/smenu_dhcp.svg" alt="" title="DHCP">DHCP</a>'.PHP_EOL;
  echo '<a href="/admin/live.php"><img src="/admin/svg/smenu_live.svg" alt="" title="Live">Live</a>'.PHP_EOL;
  echo '<a href="/admin/analytics.php"><img src="/admin/svg/smenu_analytics.svg" alt="" title="Alerts">Alerts</a>'.PHP_EOL;
  echo '<a href="/admin/blocked.php"><img src="/admin/svg/smenu_blocked.svg" alt="" title="Sites Blocked">Sites Blocked</a>'.PHP_EOL;
  echo '<a href="/admin/investigate.php"><img src="/admin/svg/smenu_investigate.svg" alt="" title="Investigate">Investigate</a>'.PHP_EOL;
  echo '<a href="/admin/config.php"><img src="/admin/svg/smenu_config.svg" alt="" title="Config">Config</a>'.PHP_EOL;
  echo '<a href="/admin/help.php"><img src="/admin/svg/smenu_help.svg" alt="" title="Help">Help</a>'.PHP_EOL;

  sidemenu_sysstatus();
  
  echo '<span id="menu-side-bottom"><a href="https://quidsup.net/donate" target="_blank"><img src="/admin/svg/smenu_don.svg" alt="Donate" title="Donate"></a></span>'.PHP_EOL;
  echo '</div></nav>'.PHP_EOL;
  echo PHP_EOL;
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
  echo '<nav><div id="menu-side">'.PHP_EOL;
  echo '<a href="/admin/"><img src="/admin/svg/smenu_dashboard.svg" alt="" title="Dashboard">Dashboard</a>'.PHP_EOL;
  echo '<a href="/admin/help.php"><img src="/admin/svg/smenu_help.svg" alt="">Help</a>'.PHP_EOL;
  echo '<a href="/admin/help.php?p=security">Security</a>'.PHP_EOL;
  echo '<a href="/admin/help.php?p=position" title="Where To Position NoTrack Device">Positioning Device</a>'.PHP_EOL;
  
  echo '</div></nav>'.PHP_EOL;
  echo PHP_EOL;
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
  global $Config, $mem;
  
  echo '<nav><div id="menu-top">'.PHP_EOL;
  echo '<span class="hamburger pointer mobile-show" onclick="openNav()">&#9776;</span>'.PHP_EOL;   //Hamburger menu to show #menu-side mobile-show
  
  if ($currentpage == '') {                                //Display version number when $currentpage has not been set
    echo '<a href="/admin/"><span id="menu-top-logo" class="logo"><b>No</b>Track <small>v'.VERSION.'</small></span></a>'.PHP_EOL;
  }
  else {                                                   //$currentpage set, display that next to NoTrack logo
    echo '<a href="/admin/"><span id="menu-top-logo" class="logo"><span class="mobile-hide"><b>No</b>Track - </span><small> '.$currentpage.'</small></span></a>'.PHP_EOL;
  }
  
  
  //If Status = Paused AND UnpauseTime < Now plus a few seconds then force reload of Config
  if (($Config['status'] & STATUS_PAUSED) && ($Config['unpausetime'] < (time()+10))) {
    $mem->delete('Config');
    load_config();
  }

  echo '<div id="pause-group">'.PHP_EOL;
  //echo '<input type="hidden" name="pause-time" id="pause-time" value="">'.PHP_EOL;
  if ($Config['status'] & STATUS_PAUSED) {
    echo '<img id="pause-button" class="pointer" title="Resume Blocking" onclick="enableNoTrack()" src="/admin/svg/tmenu_play.svg" alt="">'.PHP_EOL;
  }
  elseif ($Config['status'] & STATUS_DISABLED) {
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
  if ($Config['status'] & STATUS_INCOGNITO) {              //Is Incognito set? Draw purple button and text
    echo '<img id="incognito-button" class="pointer" title="Incognito" onclick="menuIncognito()" src="/admin/svg/menu_incognito_active.svg" alt="">'.PHP_EOL;
  }
  else {                                                   //No, draw white button and text
    echo '<img id="incognito-button" class="pointer" title="Incognito" onclick="menuIncognito()" src="/admin/svg/menu_incognito.svg" alt="">'.PHP_EOL;
  }
  
  if (is_password_protection_enabled()) {                  //Show Logout button if there is a password
    echo '<a href="/admin/logout.php"><img title="Logout" src="/admin/svg/menu_logout.svg" alt=""></a>'.PHP_EOL;
  }

  echo '<img class="pointer" title="Options" onclick="showOptions()" src="/admin/svg/menu_option.svg" alt="">'.PHP_EOL;

  echo '</div>'.PHP_EOL;
  echo '</div>'.PHP_EOL;
  echo '</nav>'.PHP_EOL;

  
  
  echo '<div id="options-box">'.PHP_EOL;
  echo '<h2>Options</h2>'.PHP_EOL;

  echo '<button onclick="updateBlocklist()" title="Force Download and Update Blocklist" class="button-grey button-options">Update Blocklist</button>'.PHP_EOL;
  echo '<button onclick="restartSystem()" class="button-grey">Restart System</button>'.PHP_EOL;
  echo '<button onclick="shutdownSystem()" class="button-danger">Shutdown System</button>'.PHP_EOL;
    
  echo '<div class="close-button"><img src="/admin/svg/button_close.svg" onmouseover="this.src=\'/admin/svg/button_close_over.svg\'" onmouseout="this.src=\'/admin/svg/button_close.svg\'" alt="Close" onclick="hideOptions()"></div>'.PHP_EOL;
  echo '</div>'.PHP_EOL;

  echo '<div id="fade" onclick="hideOptions()"></div>'.PHP_EOL;
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
  global $Config;
  
  $sysload = sys_getloadavg();
  $freemem = preg_split('/\s+/', exec('free -m | grep Mem'));
  
  $mempercentage = round(($freemem[2]/$freemem[1])*100);

  echo '<div id="menu-side-status">'.PHP_EOL;              //Start menu-side-status
  echo '<div><img src="/admin/svg/status_screen.svg" alt="">System Status</div>';
  
  if ($Config['status'] & STATUS_ENABLED) {
    if (file_exists(NOTRACK_LIST)) {
      echo '<div id="menu-side-blocking"><img src="/admin/svg/status_green.svg" alt="">Blocking: Enabled</div>'.PHP_EOL;
    }
    else {
      if (file_exists(NOTRACK_LIST)) {
        echo '<div id="menu-side-blocking"><img src="/admin/svg/status_red.svg" alt="">Blocklist Missing</div>'.PHP_EOL;
      }
    }
  }
  elseif ($Config['status'] & STATUS_PAUSED) {
    echo '<div id="menu-side-blocking"><img src="/admin/svg/status_yellow.svg" alt="">Blocking: Paused - '.date('H:i', $Config['unpausetime']).'</div>'.PHP_EOL;
  }
  elseif ($Config['status'] & STATUS_DISABLED) {
    echo '<div id="menu-side-blocking"><img src="/admin/svg/status_red.svg" alt="">Blocking: Disabled</div>'.PHP_EOL;
  }
  
  if ($mempercentage > 85) echo '<div><img src="/admin/svg/status_red.svg" alt="">Memory Used: '.$mempercentage.'%</div>'.PHP_EOL;
  elseif ($mempercentage > 60) echo '<div><img src="/admin/svg/status_yellow.svg" alt="">Memory Used: '.$mempercentage.'%</div>'.PHP_EOL;
  else echo '<div><img src="/admin/svg/status_green.svg" alt="">Memory Used: '.$mempercentage.'%</div>'.PHP_EOL;
  
  if ($sysload[0] > 0.85) echo '<div><img src="/admin/svg/status_red.svg" alt="">Load: ', $sysload[0].' | '.$sysload[1].' | '.$sysload[2].'</div>'.PHP_EOL;
  elseif ($sysload[0] > 0.60) echo '<div><img src="/admin/svg/status_yellow.svg" alt="">Load: ', $sysload[0].' | '.$sysload[1].' | '.$sysload[2].'</div>'.PHP_EOL;
  else echo '<div><img src="/admin/svg/status_green.svg" alt="">Load: ', $sysload[0].' | '.$sysload[1].' | '.$sysload[2].'</div>'.PHP_EOL;
  
  echo '</div>'.PHP_EOL;                                   //End menu-side-status
}
