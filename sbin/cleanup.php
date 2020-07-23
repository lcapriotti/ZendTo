#!/usr/bin/env php
<?PHP

$ztprefs = getenv('ZENDTOPREFS');
if (@$ztprefs) {
  array_splice($argv, 1, 0, $ztprefs);
}

if ( count($argv) < 2 ) {
  printf("
  usage:
  
   %s <ZendTo preferences.php file> [ --no-purge ] [ --no-warnings ]

   The ZendTo preferences.php file path should be canonical, not relative.
   Alternatively, do
     export ZENDTOPREFS=<full file path of preferences.php>
     %s [ --no-purge ] [ --no-warnings ]

   --no-purge : Do not attempt to delete any expired dropoffs,
                or warn about ones expiring soon.
                Used in the post-installation package scriptlets.

   --no-warnings : Do not warn about dropoffs expiring soon.
                   Only run it without this option once per day.

",$argv[0],$argv[0]);
  return 0;
}

if ( ! preg_match('/^\/.+/',$argv[1]) ) {
  echo "ERROR:  You must provide a canonical path to the preference file.\n";
  return 1;
}

include $argv[1];
include NSSDROPBOX_LIB_DIR."Smartyconf.php";
include_once(NSSDROPBOX_LIB_DIR."NSSDropoff.php");

// Switch --no-purge used in rpm+deb postinstall scripts so we don't
// accidentally delete drop-offs that shouldn't be. In the postinstall
// script, the user won't have had a chance to set the drop-off expiry
// time, the user will be working with all the default values.
$dontPurge = in_array('--no-purge', $argv);

$noWarnings = in_array('--no-warnings', $argv);

chdir(NSSDROPBOX_LIB_DIR); // So relative URLs work in email templates

if ( $theDropbox = new NSSDropbox($NSSDROPBOX_PREFS) ) {
  
  //
  // Get all drop-offs; they come back sorted according to their
  // creation date:
  //
  printf("\nCleanup of ZendTo for preference file:\n  %s\n\n",$argv[1]);
  print("Updating database schema\n");
  $theDropbox->database()->DBUpdateSchema();
  print("Gathering expired dropoffs\n");
  $theDropbox->writeToLog("Info: Cleanup expired drop-offs");
  $oldDropoffs = NSSDropoff::dropoffsOutsideRetentionTime($theDropbox);
  if ($dontPurge) {
    // $oldDropoffs = array(); // Don't do anything
    print "But not doing anything to them\n";
  }
  if ( $oldDropoffs && ($iMax = count($oldDropoffs)) ) {
    $i = 0;
    while ( $i < $iMax ) {
      printf("- %sRemoving [%s] %s <%s>\n",
        $dontPurge?"NOT ":"",
        $oldDropoffs[$i]->claimID(),
        $oldDropoffs[$i]->senderName(),
        $oldDropoffs[$i]->senderEmail()
      );
      if (!$dontPurge) {
        $theDropbox->writeToLog(sprintf("Info: Cleanup deleting expired drop-off %s from %s <%s>",
          $oldDropoffs[$i]->claimID(),
          $oldDropoffs[$i]->senderName(),
          $oldDropoffs[$i]->senderEmail()
        ));
        $oldDropoffs[$i]->removeDropoff();
      }
      $i++;
    }
  } else {
    print "No dropoffs have expired.\n\n";
  }
  
  //
  // Now nag daily about dropoffs near to their retention limit,
  // that haven't been picked up by anyone.
  //
  if ($theDropbox->warningDays()>0 && !$dontPurge && !$noWarnings) {
    printf("\nNSSDropbox Warn recipients about dropoffs close to expiry.\n");
    $oldDropoffs = NSSDropoff::dropoffsNearRetentionTime($theDropbox);
    if ( $oldDropoffs && ($iMax = count($oldDropoffs)) ) {
      $i = 0;
      while ( $i < $iMax ) {
        // Have there been any pickups of this dropoff?
        if ( $oldDropoffs[$i]->numPickups() < 1 ) {
          // Get a new $smarty object for each email
          include NSSDROPBOX_LIB_DIR."Smartyconf.php";
          printf("- Reminding about [%s] %s <%s>\n",
            $oldDropoffs[$i]->claimID(),
            $oldDropoffs[$i]->senderName(),
            $oldDropoffs[$i]->senderEmail()
          );
          $theDropbox->writeToLog(sprintf("Info: Reminding %s <%s> to pick up drop-off %s near to expiry",
            $oldDropoffs[$i]->senderName(),
            $oldDropoffs[$i]->senderEmail(),
            $oldDropoffs[$i]->claimID()
          ));
          // We don't know for certain here whether the original email
          // to the recipient(s) included the passcode or not.
          // So we have to assume that if it is allowed at all, and the
          // default is to send the passcode, then we will send the
          // passcode here too.
          // It won't have been set by creating the NSSDropoff object
          // at all, as there's no corresponding database field for it
          // in the dropoff table.
          if ($theDropbox->allowEmailPasscode() &&
              $theDropbox->defaultEmailPasscode()) {
            $oldDropoffs[$i]->setresendWithPasscode(1);
          } else {
            $oldDropoffs[$i]->setresendWithPasscode(0);
          }
          // The 0 tells it not to reset the expiry timer.
          $oldDropoffs[$i]->resendDropoff(FALSE);
        }
        $i++;
      }
    } else {
      print "No dropoffs near expiry.\n\n";
    }
  }

  // Only do this if we are doing a normal clean-up.
  // Don't do it if we are just trying to fix the schema.
  if (!$dontPurge) {
    //
    // Do a orphan purge, too:
    //
    printf("Purging orphaned dropoffs:\n");
    NSSDropoff::cleanupOrphans($theDropbox);

    //
    // Now prune the auth table of old keys
    //
    printf("Purging old sender verification data:\n");
    $theDropbox->PruneAuthData();

    //
    // Now prune the req table of old keys
    //
    printf("Purging old request data:\n");
    $theDropbox->PruneReqData();
  }
}

?>
