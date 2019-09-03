<?php

/********************************************************************
 *  Add Search Box String to SQL Search
 *
 *  Params:
 *    None
 *  Return:
 *    SQL Query string
 */
function add_searches() {
  global $blradio, $searchbox;
  $searchstr = '';
  
  if (($blradio != 'all') && ($searchbox != '')) {
    $searchstr = ' WHERE site LIKE \'%'.$searchbox.'%\' AND bl_source = \''.$blradio.'\' ';
  }
  elseif ($blradio != 'all') {
    $searchstr = ' WHERE bl_source = \''.$blradio.'\' ';
  }
  elseif ($searchbox != '') {
    $searchstr = ' WHERE site LIKE \'%'.$searchbox.'%\' ';
  }
  
  return $searchstr;
}


/********************************************************************
 *  Draw Blocklist Radio Form
 *    Radio list is made up of the items in $BLOCKLISTNAMES array
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function draw_blradioform() {
  global $BLOCKLISTNAMES, $showblradio, $blradio, $page, $searchbox;
  
  if ($showblradio) {                            //Are we drawing Form or Show button?
    echo '<form name = "blradform" method="GET">'.PHP_EOL;   //Form for Radio List
    echo '<input type="hidden" name="v" value="full">'.PHP_EOL;
    echo '<input type="hidden" name="page" value="'.$page.'">'.PHP_EOL;
    if ($searchbox != '') {
      echo '<input type="hidden" name="s" value="'.$searchbox.'">'.PHP_EOL;
    }    
  
    if ($blradio == 'all') {
      echo '<span class="blradiolist"><input type="radio" name="blrad" value="all" checked="checked" onclick="document.blradform.submit()">All</span>'.PHP_EOL;
    }
    else {
      echo '<span class="blradiolist"><input type="radio" name="blrad" value="all" onclick="document.blradform.submit()">All</span>'.PHP_EOL;
    }
  
    foreach ($BLOCKLISTNAMES as $key => $value) { //Use BLOCKLISTNAMES for Radio items
      if ($key == $blradio) {                    //Should current item be checked?
        echo '<span class="blradiolist"><input type="radio" name="blrad" value="'.$key.'" checked="checked" onclick="document.blradform.submit()">'.$value.'</span>'.PHP_EOL;
      }
      else {
        echo '<span class="blradiolist"><input type="radio" name="blrad" value="'.$key.'" onclick="document.blradform.submit()">'.$value.'</span>'.PHP_EOL;
      }
    }
  }  
  else {                                         //Draw Show button instead
    echo '<form action="?v=full&amp;page='.$page.'" method="POST">'.PHP_EOL;
    echo '<input type="hidden" name="showblradio" value="1">'.PHP_EOL;
    echo '<input type="submit" value="Select Block List">'.PHP_EOL;
  }
  
  echo '</form>'.PHP_EOL;                        //End of either form above
  echo '<br>'.PHP_EOL;
}


/********************************************************************
 *  Show Advanced Page
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function show_advanced() {
  global $Config;
  echo '<form action="?v=advanced" method="post">'.PHP_EOL;
  echo '<input type="hidden" name="action" value="advanced">';
  draw_systable('Advanced Settings');
  draw_sysrow('DNS Log Parsing Interval', '<input type="number" class="fixed10" name="parsing" min="1" max="60" value="'.$Config['ParsingTime'].'" title="Time between updates in Minutes">');
  draw_sysrow('Suppress Domains <div class="help-icon" title="Group together certain domains on the Stats page"></div>', '<textarea rows="5" name="suppress">'.str_replace(',', PHP_EOL, $Config['Suppress']).'</textarea>');
  echo '<tr><td>&nbsp;</td><td><input type="submit" value="Save Changes"></td></tr>'.PHP_EOL;
  echo '</table>'.PHP_EOL;
  echo '</div>'.PHP_EOL;
  echo '</form>'.PHP_EOL;
  
  //TODO Add reset
}


/********************************************************************
 *  Show Full Block List
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function show_full_blocklist() {
  global $db, $page, $searchbox, $blradio, $showblradio;
  global $BLOCKLISTNAMES;
  
  $key = '';
  $value ='';
  $rows = 0;
  $row_class = '';
  $bl_source = '';
  $linkstr = '';
  $i = 0;
  
  echo '<div class="sys-group">'.PHP_EOL;
  echo '<h5>Sites Blocked</h5>'.PHP_EOL;
    
  $rows = count_rows('SELECT COUNT(*) FROM blocklist'.add_searches());
    
  if ((($page-1) * ROWSPERPAGE) > $rows) $page = 1;
  $i = (($page-1) * ROWSPERPAGE) + 1;                      //Calculate count position
    
  $query = 'SELECT * FROM blocklist '.add_searches().'ORDER BY id LIMIT '.ROWSPERPAGE.' OFFSET '.(($page-1) * ROWSPERPAGE);
  
  if(!$result = $db->query($query)){                       //Run the Query
    echo '<h4><img src=./svg/emoji_sad.svg>Error running query</h4>'.PHP_EOL;
    echo 'show_full_blocklist: '.$db->error;
    echo '</div>'.PHP_EOL;
  }
  
  draw_blradioform();                                      //Block List selector form
  
  echo '<form method="GET">'.PHP_EOL;                      //Form for Text Search
  echo '<input type="hidden" name="page" value="'.$page.'">'.PHP_EOL;
  echo '<input type="hidden" name="v" value="full">'.PHP_EOL;
  echo '<input type="hidden" name="blrad" value="'.$blradio.'">'.PHP_EOL;
  echo '<input type="text" name="s" id="search" value="'.$searchbox.'">&nbsp;&nbsp;';
  echo '<input type="Submit" value="Search">'.PHP_EOL;
  echo '</form></div>'.PHP_EOL;                            //End form for Text Search
  
  
  if ($result->num_rows == 0) {                            //Leave if nothing found
    $result->free();
    echo '<div class="sys-group">'.PHP_EOL;
    echo '<h4><img src=./svg/emoji_sad.svg>No sites found in Block List</h4>'.PHP_EOL;
    echo '</div>'.PHP_EOL;
    return false;
  }
  
  if ($showblradio) {                                      //Add selected blocklist to pagination link string
    $linkstr .= '&amp;blrad='.$blradio;
  }  
  
  echo '<div class="sys-group">';                          //Now for the results
  
  pagination($rows, 'v=full'.$linkstr);                    //Draw Pagination box
    
  echo '<table id="block-table">'.PHP_EOL;
  echo '<tr><th>#</th><th>Block List</th><th>Site</th><th>Comment</th></tr>'.PHP_EOL;
   
  while($row = $result->fetch_assoc()) {                   //Read each row of results
    if ($row['site_status'] == 0) {                        //Is site enabled or disabled?
      $row_class = ' class="dark"';
    }
    else {
      $row_class = '';
    }
    
    if (array_key_exists($row['bl_source'], $BLOCKLISTNAMES)) { //Convert bl_name to Actual Name
      $bl_source = $BLOCKLISTNAMES[$row['bl_source']];
    }
    else {
      $bl_source = $row['bl_source'];
    }
    echo '<tr'.$row_class.'><td>'.$i.'</td><td>'.$bl_source.'</td><td>'.$row['site'].'</td><td>'.$row['comment'].'</td></tr>'.PHP_EOL;
    $i++;
  }
  echo '</table>'.PHP_EOL;                                 //End of table
  
  echo '<br>'.PHP_EOL;
  pagination($rows, 'v=full'.$linkstr);                    //Draw second Pagination box
  echo '</div>'.PHP_EOL; 
  
  $result->free();

  return true;
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
  global $Config, $SEARCHENGINELIST, $WHOISLIST;
  
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
  draw_sysrow('Network Device', $Config['NetDev']);
  if (($Config['IPVersion'] == 'IPv4') || ($Config['IPVersion'] == 'IPv6')) {
    draw_sysrow('Internet Protocol', $Config['IPVersion']);
    draw_sysrow('IP Address', $_SERVER['SERVER_ADDR']);
  }
  else {
    draw_sysrow('IP Address', $Config['IPVersion']);
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
  if ($Config['BlockMessage'] == 'pixel') draw_sysrow('Block Message', '<input type="radio" name="block" value="pixel" checked onclick="document.blockmsg.submit()">1x1 Blank Pixel (default)<br><input type="radio" name="block" value="message" onclick="document.blockmsg.submit()">Message - Blocked by NoTrack<br>');
  else draw_sysrow('Block Message', '<input type="radio" name="block" value="pixel" onclick="document.blockmsg.submit()">1x1 Blank Pixel (default)<br><input type="radio" name="block" value="messge" checked onclick="document.blockmsg.submit()">Message - Blocked by NoTrack<br>');  
  echo '</table></div></form>'.PHP_EOL;

  
  //Stats
  echo '<form name="stats" method="post">';
  echo '<input type="hidden" name="action" value="stats">';
  
  draw_systable('Domain Stats');
  echo '<tr><td>Search Engine: </td>'.PHP_EOL;
  echo '<td><select name="search" class="input-conf" onchange="submit()">'.PHP_EOL;
  echo '<option value="'.$Config['Search'].'">'.$Config['Search'].'</option>'.PHP_EOL;
  foreach ($SEARCHENGINELIST as $key => $value) {
    if ($key != $Config['Search']) {
      echo '<option value="'.$key.'">'.$key.'</option>'.PHP_EOL;
    }
  }
  echo '</select></td></tr>'.PHP_EOL;
  
  echo '<tr><td>Who Is Lookup: </td>'.PHP_EOL;
  echo '<td><select name="whois" class="input-conf" onchange="submit()">'.PHP_EOL;
  echo '<option value="'.$Config['WhoIs'].'">'.$Config['WhoIs'].'</option>'.PHP_EOL;
  foreach ($WHOISLIST as $key => $value) {
    if ($key != $Config['WhoIs']) {
      echo '<option value="'.$key.'">'.$key.'</option>'.PHP_EOL;
    }
  }
  echo '</select></td></tr>'.PHP_EOL;
  draw_sysrow('JsonWhois API <a href="https://jsonwhois.com/"><div class="help-icon"></div></a>', '<input type="text" name="whoisapi" class="input-conf" value="'.$Config['whoisapi'].'">');
  echo '</table></div></form>'.PHP_EOL;                    //End Stats
  
  return null;
}


/********************************************************************
 *  Show Menu
 *    Show menu using a flexbox (conf-nav) for each category
 *  Params:
 *    None
 *  Return:
 *    None
 */
function show_menu() {
  echo '<div class="sys-group">'.PHP_EOL;                 //Start System
  echo '<h5>System</h5>'.PHP_EOL;
  echo '<div class="conf-nav">'.PHP_EOL;
  echo '<a href="../admin/config.php?v=general"><img src="./svg/menu_config.svg"><span>General</span></a>'.PHP_EOL;
  echo '<a href="../admin/config.php?v=status"><img src="./svg/menu_status.svg"><span>Back-end Status</span></a>'.PHP_EOL;
  echo '<a href="../admin/security.php"><img src="./svg/menu_security.svg"><span>Security</span></a>'.PHP_EOL;
  echo '<a href="../admin/upgrade.php"><img src="./svg/menu_upgrade.svg"><span>Upgrade</span></a>'.PHP_EOL;
  echo '<a href="../admin/config.php?v=dnsmasq"><img src="./svg/menu_config.svg"><span>Work in progress</span></a>'.PHP_EOL;
  echo '</div></div>'.PHP_EOL;                             //End System
  
  echo '<div class="sys-group">'.PHP_EOL;                  //Start Block lists
  echo '<h5>Block Lists</h5>'.PHP_EOL;
  echo '<div class="conf-nav">'.PHP_EOL;
  echo '<a href="../admin/config/blocklists.php"><img src="./svg/menu_blocklists.svg"><span>Select Block Lists</span></a>'.PHP_EOL;
  echo '<a href="../admin/config/tld.php"><img src="./svg/menu_domain.svg"><span>Top Level Domains</span></a>'.PHP_EOL;
  echo '<a href="../admin/config/customblocklist.php?v=black"><img src="./svg/menu_black.svg"><span>Custom Black List</span></a>'.PHP_EOL;
  echo '<a href="../admin/config/customblocklist.php?v=white"><img src="./svg/menu_white.svg"><span>Custom White List</span></a>'.PHP_EOL;
  echo '<a href="../admin/config.php?v=full"><img src="./svg/menu_sites.svg"><span>View Sites Blocked</span></a>'.PHP_EOL;
  echo '</div></div>'.PHP_EOL;                             //End Block lists
  
  echo '<div class="sys-group">'.PHP_EOL;                  //Advanced
  echo '<h5>Advanced</h5>'.PHP_EOL;
  echo '<div class="conf-nav">'.PHP_EOL;
  echo '<a href="../admin/config.php?v=advanced"><img src="./svg/menu_advanced.svg"><span>Advanced Options</span></a>'.PHP_EOL;
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
