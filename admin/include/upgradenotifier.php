<?php
/*Class for NoTrack Upgrade Notifier
 *
 *
 *
 */
define('SETTINGS_LATESTVER', $_SERVER['DOCUMENT_ROOT'].'/admin/settings/latestversion.php');

class UpgradeNotifier {
  private $latestversion = '';

  /********************************************************************
   *  Constructor
   *
   *  Params:
   *    None
   *  Return:
   *    None
   */
  /*public function __construct() {

  }*/


  /********************************************************************
   *  Get Value
   *    Checks private variable exists, then returns it
   *
   *  Params:
   *    Name (str): variable name to get
   *  Return:
   *    Specified private variable
   */
  public function __get($name) {
    if (property_exists($this, $name)) {
      return $this->{$name};
    }
    else {
      trigger_error("Undefined variable {$name}", E_USER_WARNING);
    }
  }


  /********************************************************************
   *  Set Value
   *    1. Check new value matches regex pattern
   *    2. Assign value
   *
   *  Params:
   *    name (str)
   *    value (mixed)
   *  Return:
   *    Value on success
   *    False on failure
   */
  public function __set($name, $value) {
    //Does specified name exist in $setfilters array?
    if ($name == 'latestversion') {
      if (preg_match('/^\d{1,2}\.\d{1,2}(\.\d{1,2})?$/', $value)) {
        $this->latestversion = $value;
      }
    }

    //echo "setting {$name} {$value}";
    return $value;
  }


  /********************************************************************
   *  Is Upgrade Available
   *    Compare the current version against the latest version
   *    Use integer comparison and remove the dots from the string version
   *  Params:
   *    None
   *  Return:
   *    None
   */
  public function is_upgrade_available() {
    $intcurrent = 0;
    $intlatest = 0;

    if ($this->latestversion == '') {
      return false;
    }

    $intcurrent = intval(str_replace('.', '', VERSION));
    $intlatest = intval(str_replace('.', '', $this->latestversion));

    if ($intlatest > $intcurrent) {
      return true;
    }

    return false;
  }
}


/********************************************************************
 *  Load latestversion.php
 *    1. Check latestversion.php exists in settings folder
 *    2. Execute latestversion.php
 *
 */
function load_versionsettings() {
  global $upgradenotifier;

  if (file_exists(SETTINGS_LATESTVER)) {
    include SETTINGS_LATESTVER;
  }
}


$upgradenotifier = new UpgradeNotifier;
load_versionsettings();
