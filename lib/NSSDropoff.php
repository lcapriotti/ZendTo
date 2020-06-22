<?PHP
//
// ZendTo
// Copyright (C) 2006 Jeffrey Frey, frey at udel dot edu
// Copyright (C) 2020 Julian Field, Jules at ZendTo dot com 
//
// Based on the original PERL dropbox written by Doke Scott.
// Developed by Julian Field.
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
//
//

require_once(NSSDROPBOX_LIB_DIR."NSSDropbox.php");
require_once(NSSDROPBOX_LIB_DIR."NSSUtils.php");
require_once(NSSDROPBOX_LIB_DIR."Timestamp.php");
require_once(NSSDROPBOX_LIB_DIR."DecryptStream.php");
require_once(NSSDROPBOX_LIB_DIR."Psr/Http/Message/StreamInterface.php");

/*!
  @class NSSDropoff
  
  Wraps an item that's been dropped-off.  There are two methods of
  allocation available.  The primary is using the dropoff ID,
  for which the database will be queried and used to initialize
  the instance.  The second includes no ID, and in this instance
  the $_FILES array will be examined -- if any files were uploaded
  then the dropoff is initialized using $_FILES and $_POST data.
  
  Dropoffs have evolved a bit since the previous version of this
  service.  Each dropoff can now have multiple files associated
  with it, eliminating the need for the end-user to archive
  multiple files for dropoff.  Dropoffs are now created as a
  one-to-many relationship, where the previous version was setup to
  be a one-to-one deal only.
  
  Of course, we're also leveraging the power of SQL to maintain
  the behind-the-scenes data for each dropoff.
*/
class NSSDropoff {

  //  Instance data:
  private $_dropbox = NULL;
  
  private $_dropoffID = -1;
  private $_claimID;
  private $_claimPasscode;
  private $_claimDir;
  
  private $_authorizedUser;
  private $_emailAddr;
  
  private $_auth;
  private $_senderName;
  private $_senderOrganization;
  private $_expiry;
  private $_senderEmail;
  private $_senderIP;
  private $_confirmDelivery;
  private $_informRecipients;
  private $_informPasscode;
  private $_resendWithPasscode;
  private $_note;
  private $_subject;
  private $_created;
  private $_lifeseconds;
  private $_bytes;
  private $_formattedBytes;
  
  private $_recipients;

  // This one is only filled in places we need it as it causes more db work
  private $_numPickups = 0;
  
  private $_showIDPasscode = FALSE;
  private $_showPasscodeHTML = TRUE;
  private $_cameFromEmail = FALSE;
  private $_invalidClaimID = FALSE;
  private $_invalidClaimPasscode = FALSE;
  private $_isNewDropoff = FALSE;
  private $_formInitError = NULL;
  private $_okayForDownloads = FALSE;
  
  /*!
    @function dropoffsForCurrentUser
    
    Static function that returns an array of all dropoffs (as
    NSSDropoff instances) that include the currently-authenticated
    user in their recipient list.  
  */
  public static function dropoffsForCurrentUser(
    $aDropbox
  )
  {
    $allDropoffs = array();
    
    if ( $targetEmail = strtolower($aDropbox->authorizedUserData('mail')) ) {
      $qResult = $aDropbox->database->DBDropoffsForMe($targetEmail);
      if ( $qResult && ($iMax = count($qResult)) ) {
        // Read the global map of dropoffID to numPickups
        $dID2NumPickups = $aDropbox->database->DBPickupCountsForAllDropoffs();

        //  Allocate all of the wrappers:
        $i = 0;
        while ( $i < $iMax ) {
          $params = $qResult[$i];
          $altParams = array();
          foreach ( $params as $key => $value ) {
            $altParams[preg_replace('/^d\./','',$key)] = $value;
          }
          if ( $nextD = new NSSDropoff($aDropbox, $altParams) ) {
            // Fill in the number of pickups, we'll need it. $np may be NULL
            $np = @$dID2NumPickups[$nextD->_dropoffID];
            $nextD->_numPickups = ($np>0)?$np:0;
            $allDropoffs[] = $nextD;
          }
          $i++;
        }
      }
    }
    return $allDropoffs;
  }
  
  /*!
    @function dropoffsFromCurrentUser
    
    Static function that returns an array of all dropoffs (as
    NSSDropoff instances) that were created by the currently-
    authenticated user.  Matches are made based on the
    user's username OR the user's email address -- that catches
    authenticated as well as anonymouse dropoffs by the user.
  */
  public static function dropoffsFromCurrentUser(
    $aDropbox
  )
  {
    $allDropoffs = NULL;
    
    if ( $authSender = $aDropbox->authorizedUser() ) {
      $targetEmail = strtolower($aDropbox->authorizedUserData('mail'));
      
      $qResult = $aDropbox->database->DBDropoffsFromMe($authSender, $targetEmail);
      if ( $qResult && ($iMax = count($qResult)) ) {
        // Read the global map of dropoffID to numPickups
        $dID2NumPickups = $aDropbox->database->DBPickupCountsForAllDropoffs();

        //  Allocate all of the wrappers:
        $i = 0;
        while ( $i < $iMax ) {
          if ( $nextD = new NSSDropoff($aDropbox,$qResult[$i]) ) {
            // Fill in the number of pickups, we'll need it. $np may be NULL
            $np = @$dID2NumPickups[$nextD->_dropoffID];
            $nextD->_numPickups = ($np>0)?$np:0;
            $allDropoffs[] = $nextD;
          }
          $i++;
        }
      }
    }
    return $allDropoffs;
  }

  /*!
    @function dropoffsOutsideRetentionTime
    
    Static function that returns an array of all dropoffs (as
    NSSDropoff instances) that are older than the dropbox's
    retention time.  Subsequently, they should be removed --
    see the "cleanup.php" admin script.
  */
  public static function dropoffsOutsideRetentionTime(
    $aDropbox
  )
  {
    $rows = $aDropbox->database->DBDropoffsAllRev();
    $qResult = $aDropbox->TrimOffLive($rows);

    if ( $qResult && ($iMax = count($qResult)) ) {
      // Read the global map of dropoffID to numPickups
      $dID2NumPickups = $aDropbox->database->DBPickupCountsForAllDropoffs();

      //  Allocate all of the wrappers:
      $i = 0;
      while ( $i < $iMax ) {
        if ( $nextD = new NSSDropoff($aDropbox,$qResult[$i]) ) {
          // Fill in the number of pickups, we'll need it. $np may be NULL
          $np = @$dID2NumPickups[$nextD->_dropoffID];
          $nextD->_numPickups = ($np>0)?$np:0;
          $allDropoffs[] = $nextD;
        }
        $i++;
      }
    }
    return $allDropoffs;
    // return NSSDropoff::dropoffsOlderThan( $aDropbox,
    //                           $aDropbox->retainDays() );
  }

  public static function dropoffsNearRetentionTime(
    $aDropbox
  )
  {
    $rows = $aDropbox->database->DBDropoffsAllRev();
    $qResult = $aDropbox->TrimOffLive($rows,
                $aDropbox->warningDays()*3600*24);

    if ( $qResult && ($iMax = count($qResult)) ) {
      // Read the global map of dropoffID to numPickups
      $dID2NumPickups = $aDropbox->database->DBPickupCountsForAllDropoffs();

      //  Allocate all of the wrappers:
      $i = 0;
      while ( $i < $iMax ) {
        if ( $nextD = new NSSDropoff($aDropbox,$qResult[$i]) ) {
          // Fill in the number of pickups, we'll need it. $np may be NULL
          $np = @$dID2NumPickups[$nextD->_dropoffID];
          $nextD->_numPickups = ($np>0)?$np:0;
          $allDropoffs[] = $nextD;
        }
        $i++;
      }
    }
    return $allDropoffs;
    // return NSSDropoff::dropoffsOlderThan( $aDropbox,
    //                           $aDropbox->retainDays() -
    //                           $aDropbox->warningDays() );
  }

/*  Obsolete function - was used by dropoffsOutsideRetentionTime
    and dropoffsNearRetentionTime.
  static function dropoffsOlderThan(
    $aDropbox,
    $days
  )
  {
    $allDropoffs = NULL;
    
    $targetDate = timestampForTime( time() - $days * 24 * 60 * 60 );
    
    $qResult = $aDropbox->database->DBDropoffsTooOld($targetDate);
    if ( $qResult && ($iMax = count($qResult)) ) {
      // Read the global map of dropoffID to numPickups
      $dID2NumPickups = $aDropbox->database->DBPickupCountsForAllDropoffs();

      //  Allocate all of the wrappers:
      $i = 0;
      while ( $i < $iMax ) {
        if ( $nextD = new NSSDropoff($aDropbox,$qResult[$i]) ) {
          // Fill in the number of pickups, we'll need it. $np may be NULL
          $np = @$dID2NumPickups[$nextD->_dropoffID];
          $nextD->_numPickups = ($np>0)?$np:0;
          $allDropoffs[] = $nextD;
        }
        $i++;
      }
    }
    return $allDropoffs;
  }
*/
  /*!
    @function dropoffsCreatedToday
    
    Static function that returns an array of all dropoffs (as
    NSSDropoff instances) that were made in the last 24 hours.
  */
  public static function dropoffsCreatedToday(
    $aDropbox
  )
  {
    $allDropoffs = NULL;
    
    $targetDate = timestampForTime( time() - 24 * 60 * 60 );
    
    $qResult = $aDropbox->database->DBDropoffsToday($targetDate);
    if ( $qResult && ($iMax = count($qResult)) ) {
      // Read the global map of dropoffID to numPickups
      $dID2NumPickups = $aDropbox->database->DBPickupCountsForAllDropoffs();

      //  Allocate all of the wrappers:
      $i = 0;
      while ( $i < $iMax ) {
        if ( $nextD = new NSSDropoff($aDropbox,$qResult[$i]) ) {
          // Fill in the number of pickups, we'll need it. $np may be NULL
          $np = @$dID2NumPickups[$nextD->_dropoffID];
          $nextD->_numPickups = ($np>0)?$np:0;
          $allDropoffs[] = $nextD;
        }
        $i++;
      }
    }
    return $allDropoffs;
  }

  /*!
    @function allDropoffs
    
    Static function that returns an array of every single dropoff (as
    NSSDropoff instances) that exist in the database.
  */
  public static function allDropoffs(
    $aDropbox, $trimDead = TRUE
  )
  {
    $allDropoffs = NULL;
    
    $qResult = $aDropbox->database->DBDropoffsAll();
    if ( $qResult && !empty($qResult) ) {
      // Read the global map of dropoffID to numPickups
      $dID2NumPickups = $aDropbox->database->DBPickupCountsForAllDropoffs();

      // Trim off all the expired ones
      if ($trimDead)
        $notExpired = $aDropbox->TrimOffDying($qResult, 0);
      else
        $notExpired = $qResult;

      // Any left?
      if ( is_array($notExpired) && !empty($notExpired)) {
        //  Allocate all of the wrappers:
        $i = 0;
        $iMax = count($notExpired);
        while ( $i < $iMax ) {
          if ( $nextD = new NSSDropoff($aDropbox,$notExpired[$i]) ) {
            // Fill in the number of pickups, we'll need it. $np may be NULL
            $np = $dID2NumPickups[$nextD->_dropoffID];
            $nextD->_numPickups = ($np>0)?$np:0;
            $allDropoffs[] = $nextD;
          }
          $i++;
        }
      }
    }
    return $allDropoffs;
  }

  /*!
    @function cleanupOrphans
    
    Static function that looks for orphans:  directories in the dropoff
    directory that have no matching record in the database AND records in
    the database that have no on-disk directory anymore.  Scrubs both
    types of orphans.  This function gets called from the "cleanup.php"
    script after purging "old" dropoffs.
  */
  public static function cleanupOrphans(
    $aDropbox
  )
  {
    // Find the ClaimIDs of all the dropoffs created today, so we skip them
    $todayClaimIDs = array();
    if ( $today = NSSDropoff::dropoffsCreatedToday($aDropbox) ) {
      foreach ( $today as $d ) {
        $todayClaimIDs[] = $d->claimID();
      }
    }

    // Get the dropoffs without pruning any dead ones.
    // As we are trying to clean up dead ones!
    $qResult = $aDropbox->database->DBDropoffsAll();
    $scrubCount = 0;
    if ( $qResult && ($iMax = count($qResult)) ) {
      //
      //  Build a list of claim IDs and walk the dropoff directory
      //  to remove any directories that aren't in the database:
      //
      $dropoffDir = $aDropbox->dropboxDirectory();
      if ( $dirRes = opendir($dropoffDir) ) {
        $i = 0;
        $validClaimIDs = array();
        while ( $i < $iMax ) {
          $nextClaim = $qResult[$i]['claimID'];
          
          //  If there's no directory and the drop-off isn't very recent
          //  (it might still be being constructed),
          //  then we should scrub this entry from the database:
          if ( !is_dir($dropoffDir."/".$nextClaim)  &&
               !in_array($nextClaim, $todayClaimIDs )) {
            if ( $aDropoff = new NSSDropoff($aDropbox,$qResult[$i]) ) {
              $aDropoff->removeDropoff(FALSE);
              echo "- Removed orphaned record:             $nextClaim\n";
              $aDropbox->writeToLog(sprintf("Info: Cleanup deleting orphaned drop-off records for %s", $nextClaim));
            } else {
              echo "- Unable to remove orphaned record:    $nextClaim\n";
              $aDropbox->writeToLog(sprintf("Warning: Cleanup unable to delete orphaned drop-off records for %s", $nextClaim));
            }
            $scrubCount++;
          } else {
            $validClaimIDs[] = $nextClaim;
          }
          $i++;
        }
        // Now go through all the directories looking for ones with
        // no DB records.
        while ( $nextDir = readdir($dirRes) ) {
          //  Each item is a NAME, not a PATH.  Test whether it's a directory
          //  and no longer in the database:
          if ( ( $nextDir != '.' && $nextDir != '..' ) &&
               is_dir($dropoffDir."/".$nextDir) &&
               !in_array($nextDir,$validClaimIDs) ) {

            // Find the most recent modification date of files in the dir.
            // Don't use the mtime of the dir itself as backup software
            // can screw with that.
            $mostrecent = 0;
            $ddir = dir($dropoffDir."/".$nextDir);
            while ($dfile = $ddir->read()) {
              if ($dfile !== '.' && $dfile !== '..') {
                $dfmtime = @filemtime($dropoffDir."/".$nextDir."/".$dfile);
                $mostrecent = max($mostrecent, $dfmtime);
              }
            }
            $ddir->close();
            // If there weren't any files in it, then use the mtime of
            // the dir as a last resort.
            if ($mostrecent == 0)
              $mostrecent = filemtime($dropoffDir."/".$nextDir."/.");

            // Don't attempt to remove the dir if it has a file in it
            // less than 1 day old.
            $onedayago = time() - 24 * 60 * 60 ;
            if ( $mostrecent <= $onedayago ) {
              if ( rmdir_r($dropoffDir."/".$nextDir) ) {
                echo "- Removed orphaned directory:          $nextDir\n";
                $aDropbox->writeToLog(sprintf("Info: Cleanup deleting orphaned directory for %s", $nextDir));
              } else {
                echo "- Unable to remove orphaned directory: $nextDir\n";
                $aDropbox->writeToLog(sprintf("Warning: Cleanup unable to delete orphaned directory for %s", $nextDir));
              }
              $scrubCount++;
            }
          }
        }
        closedir($dirRes);
      }
    }
    if ( $scrubCount ) {
      printf("%d orphan%s removed.\n\n",$scrubCount,($scrubCount == 1 ? "" : "s"));
      $aDropbox->writeToLog(sprintf("Info: Cleanup removed %s orphan%s", $scrubCount, ($scrubCount==1?"":"s")));
    } else {
      echo "No orphans found.\n\n";
    }
  }

  /*!
    @function __construct
    
    Object constructor.  First of all, if we were passed a query result hash
    in $qResult, then initialize the instance using data from the SQL query.
    Otherwise, we need to look at the disposition of the incoming form data:
    
    * The only GET-type form we do comes from the email notifications we
      send to notify recipients.  So the presence of claimID (and possibly
      claimPasscode) in $_GET means we can init as though the user were
      making a pickup.
    
    * If there a POST-type form and a claimID exists in $_POST, then
      try to initialize using that claimID.
    
    * Otherwise, we need to see if the POST-type form data has an action
      of "dropoff" -- if it does, then attempt to create a ~new~ dropoff
      with $_FILES and $_POST.
    
    A _lot_ of state stuff going on in here; might be ripe for simplification
    in the future.
  */
  public function __construct(
    $aDropbox,
    $qResult = FALSE
  )
  {
    $this->_dropbox = $aDropbox;
    
    if ( ! $qResult ) {
      if ( isset($_POST['claimID']) && $_POST['claimID'] ) {
        //  Coming from a web form:
        if ( $this->initWithClaimID($_POST['claimID']) ) {
          $this->_showPasscodeHTML = FALSE;
        } else {
          $this->_invalidClaimID = TRUE;
        }
      } else if ( isset($_GET['claimID']) && $_GET['claimID'] ) {
        //  Coming from an email:
        $this->_cameFromEmail = TRUE;
        if ( ! $this->initWithClaimID($_GET['claimID']) ) {
          $this->_invalidClaimID = TRUE;
        }
      } else if ( isset($_POST['Action']) && $_POST['Action'] == "dropoff" ) {
        $this->_isNewDropoff = TRUE;
        $this->_showPasscodeHTML = FALSE;
        //  Try to create a new one from form data:
        $this->_formInitError = $this->initWithFormData();
        // initWithFormData will set _showIDPasscode if appropriate.
      }
      
      //  If we got a dropoff ID, check the passcode now:
      if ( ! $this->_isNewDropoff && $this->_dropoffID > 0 ) {
        //  Several ways to "authorize" this:
        //
        //    1) if the target user is the currently-logged-in user
        //    2) if the sender is the currently-logged-in user
        //    3) if the incoming form data has the valid passcode
        //
        $curUser = $this->_dropbox->authorizedUser();
        $curUserEmail = $this->_dropbox->authorizedUserData("mail");
        if ( $this->validRecipientEmail($curUserEmail) ||
             ($curUser && ($curUser == $this->_authorizedUser)) ||
             ($curUserEmail && ($curUserEmail == $this->_senderEmail)) ) {
          $this->_showPasscodeHTML = FALSE;
          $this->_okayForDownloads = TRUE;
          $this->_showIDPasscode   = TRUE;
        } else if ( $this->_cameFromEmail ) {
          // If they tried a passcode and it doesn't match, show the HTML
          // but log the failure against them.
          // If their passcode was blank, just show the HTML but don't
          // count the failure.
          $passattempt = trim(@$_GET['claimPasscode']);
          if ($passattempt !== $this->_claimPasscode) {
            // The supplied passcode didn't match, so ask for another
            $this->_showPasscodeHTML = TRUE;
            if ($passattempt == '') {
              // Didn't try, so don't hold it against them.
              $this->_invalidClaimPasscode = FALSE;
            } else {
              // They tried, and failed, so count failure against them.
              $this->_invalidClaimPasscode = TRUE;
            }
          } else {
            $this->_showPasscodeHTML = FALSE;
            $this->_okayForDownloads = TRUE;
            // JKF: If people from email links get the ClaimID and Passcode,
            // JKF: they can forward it easily to other people. Bad!
            $this->_showIDPasscode   = FALSE;
          }
        } else {
          if ( ! $this->_dropbox->authorizedUserData('grantAdminPriv') ) {
            // They aren't an admin, so they must get stuff right
            $passattempt = trim(@$_POST['claimPasscode']);
            if ($passattempt != $this->_claimPasscode) {
              // The supplied passcode didn't match, so ask for another
              $this->_showPasscodeHTML = TRUE;
              if ($passattempt == '') {
                // Didn't try, so don't hold it against them.
                $this->_invalidClaimPasscode = FALSE;
              } else {
                // They tried, and failed, so count failure against them.
                $this->_invalidClaimPasscode = TRUE;
              }
            } else {
              $this->_showPasscodeHTML = FALSE;
              $this->_okayForDownloads = TRUE;
              // JKF: Tempted to this to FALSE too, but need to test it
              // JKF: very thoroughly.
              $this->_showIDPasscode   = TRUE;
            }
          } else {
            $this->_okayForDownloads = TRUE;
            $this->_showIDPasscode   = TRUE;
          }
        }
      }
    } else {
      $this->initWithQueryResult($qResult);
      $this->_showIDPasscode = TRUE;
    }
  }

  /*
    These are all accessors to get the value of all of the dropoff
    parameters.  Note that there are no functions to set these
    parameters' values:  an instance is immutable once it's created!
    
    I won't document each one of them because the names are
    strategically descriptive *grin*
  */
  public function dropbox() { return $this->_dropbox; }
  public function dropoffID() { return $this->_dropoffID; }
  public function claimID() { return $this->_claimID; }
  public function claimPasscode() { return $this->_claimPasscode; }
  public function claimDir() { return $this->_claimDir; }
  public function authorizedUser() { return $this->_authorizedUser; }
  public function auth() { return $this->_auth; }
  public function senderName() { return $this->_senderName; }
  public function senderOrganization() { return $this->_senderOrganization; }
  public function senderEmail() { return $this->_senderEmail; }
  public function expiry() { return $this->_expiry; }
  public function senderIP() { return $this->_senderIP; }
  public function confirmDelivery() { return $this->_confirmDelivery; }
  public function informRecipients() { return $this->_informRecipients; }
  public function informPasscode() { return $this->_informPasscode; }
  public function resendWithPasscode() { return $this->_resendWithPasscode; }
  public function note() { return $this->_note; }
  public function subject() { return $this->_subject; }
  public function created() { return $this->_created; }
  public function lifeseconds() { return $this->_lifeseconds; }
  public function recipients() { return $this->_recipients; }
  public function bytes() { return $this->_bytes; }
  public function formattedBytes() { return $this->_formattedBytes; }
  public function formInitError() { return $this->_formInitError; }
  public function numPickups() { return $this->_numPickups; }
  
  // But there is a set function for this, as we need to be able to
  // override it when automatically sending reminders. In that exact case,
  // it won't be set at all when the object instance is created as
  // there's no matching database field. But we can work out what the
  // most likely answer is.
  public function setresendWithPasscode( $a ) {
    $this->_resendWithPasscode = $a;
  }

  /*!
    @function validRecipientEmail
    
    Returns TRUE is the incoming $recipEmail address is a member of the
    recipient list for this dropoff.  Returns FALSE otherwise.
  */
  public function validRecipientEmail(
    $recipEmail
  )
  {
    foreach ( $this->_recipients as $recipient ) {
      if ( strcasecmp($recipient[1],$recipEmail) == 0 ) {
        return TRUE;
      }
    }
    return FALSE;
  }
  
  /*!
    @function files
    
    Returns a hash array containing info for all of the files in
    the dropoff.
  */
  public function files()
  {
    if ( ($dropoffFiles = $this->_dropbox->database->DBFilesByDropoffID($this->_dropoffID)) && (($iMax = count($dropoffFiles)) > 0) ) {
      $fileInfo = array();
      
      $totalBytes = 0.0;
      $i = 0;
      while ( $i < $iMax ) {
        $totalBytes += floatval($dropoffFiles[$i++]['lengthInBytes']);
      }
      $dropoffFiles['totalFiles'] = $iMax;
      $dropoffFiles['totalBytes'] = $totalBytes;
      $dropoffFiles['formattedBytes'] = NSSFormattedMemSize($totalBytes);
      return $dropoffFiles;
    }
    return NULL;
  }

  /*!
    @function addFileWithContent
    
    JKF This function is never actually called.

    Add another file to this dropoff's payload, using the provided content,
    filename, and MIME type.
  */
  /*
  public function addFileWithContent(
    $content,
    $filename,
    $description,
    $mimeType = 'application/octet-stream'
  )
  {
    if ( ($contentLen = strlen($content)) && strlen($filename) && $this->_dropoffID ) {
      if ( strlen($mimeType) < 1 ) {
        $mimeType = 'application/octet-stream';
      }
      if ( $this->_claimDir ) {
        $tmpname = tempnam($this->_claimDir,'aff_');
        if ( $fptr = fopen($tmpname,'w') ) {
          fwrite($fptr,$content,$contentLen);
          fclose($fptr);
          
          //  Add to database:
          if ( ! $this->_dropbox->database->DBAddFile2( $this->_dropbox, $this->_dropoffID, $tmpname, $filename,
                            $contentLen, $mimeType, $description, $claimID ) ) {
            unlink($tmpname);
            return false;
          }
          return true;
        }
      }
    }
    return false;
  }
  */

  /*!
    @function downloadEncrypted

    Given a file to download, decrypt it and send it to the user's browser.
    Returns true on success, false otherwise.
    This does NOT support byte ranges, as that would imply having to create
    a temporary file or waste a lot of time decrypting a file multiple times.
    They can just start again, encrypted drop-offs aren't going to be huge
    anyway, so it shouldn't be a real problem for users.
  */
  public function downloadEncrypted(
    $file,
    $password
  )
  {
    global $NSSDROPBOX_PREFS;

    $in_filename = $this->_claimDir."/".$file['tmpname'];
    $iv = '';
    $metadata = $this->_senderIP; // '|'-separated list of tokens
    $words = explode('|', $metadata);
    $skip = strlen('ENCRYPT:');
    foreach ($words as $word) {
      if (!strncasecmp($word, 'ENCRYPT:', $skip)) {
        $iv = sodium_hex2bin(substr($word, $skip));
        break;
      }
    }
    if ($iv === '') return false; // No IV data? Bail out!

    $chunk_size = 0;
    $alg = '';
    $opslimit = '';
    $memlimit = '';
    $decrypted_chunk = '';

    // Open the input file, or bail out
    if (!($fd_in = fopen($in_filename, 'rb'))) return false;
    $in_size = fstat($fd_in)['size'];

    // Read the info about the secret key we stored at the start.
    $alg = unpack('P', fread($fd_in, 8))[1];
    $opslimit = unpack('P', fread($fd_in, 8))[1];
    $memlimit = unpack('P', fread($fd_in, 8))[1];
    $chunk_size = unpack('P', fread($fd_in, 8))[1]; // JKF Added this
    $decrypt_size = unpack('P', fread($fd_in, 8))[1]; // JKF Added this
    $salt = fread($fd_in, SODIUM_CRYPTO_PWHASH_SALTBYTES);

    // Read the header we wrote next
    $header = fread($fd_in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);

    // From that lot, work out the key
    $key = sodium_crypto_pwhash(
             SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES,
             $password, $salt, $opslimit, $memlimit, $alg);

    // Create a stream using the header and the key
    $stream = sodium_crypto_secretstream_xchacha20poly1305_init_pull(
                $header, $key);

    // Read the file until we hit the end or something goes wrong
    $decrypt_failed = false;
    $hit_eof = false;
    $hit_finaltag = false;
    $first_chunk = true;
    do {
      // That ABYTES constant is difference between encrypted length & plaintext
      $chunk = fread($fd_in,
                     $chunk_size +
                     SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES);
      // feof() will say FALSE if you read to exactly the end of the file :(
      $hit_eof = (feof($fd_in) || ftell($fd_in) === $in_size);

      // Decrypt the next chunk from the stream
      $res = sodium_crypto_secretstream_xchacha20poly1305_pull($stream, $chunk, $iv);
      //if ($res === FALSE || connection_aborted()) {
      if ($res === FALSE) {
        // Decryption failed for some reason!
        // Or the browser broke the connection before finishing.
        $decrypt_failed = true;
        // Wipe the secrets from memory properly
        if (is_string($iv))              sodium_memzero($iv);
        if (is_string($password))        sodium_memzero($password);
        if (is_string($salt))            sodium_memzero($salt);
        if (is_string($key))             sodium_memzero($key);
        if (is_string($stream))          sodium_memzero($stream);
        if (is_string($decrypted_chunk)) sodium_memzero($decrypted_chunk);
        // If we have still not output anything, we can bail out with
        // a nice user interface, as no headers have been sent.
        if ($first_chunk) {
          // Don't as we haven't started yet: ob_end_flush();
          fclose($fd_in);
          if (is_string($fd_in)) sodium_memzero($fd_in);
          return false;
        }
        break;
      }

      // Everything has worked so far. The decryption did actually start ok.
      // So if we are on the first block, output all the HTTP headers
      // before we send any real file data.
      if ($first_chunk) {
        header("Content-type: " . htmlspecialchars($file['mimeType']));

        // If we're talking to IE, encode the attachment filename or else
        // intl characters get garbled completely.
        // Thanks to UxBoD for this.
        // Changed urlencode to rawurlencode on advice from Liam Gretton.
        if (preg_match("/MSIE/", $_SERVER["HTTP_USER_AGENT"])) {
          header('Content-Disposition: attachment; filename="' .
                 rawurlencode($file['basename']) . '"');
        } else {
          header('Content-Disposition: attachment; filename="' .
                 $file['basename'] . '"');
        }
        header('Content-Transfer-Encoding: binary');

        header('Last-Modified: ' . substr(gmdate('r', filemtime($this->_claimDir."/".$file['tmpname'])), 0, -5) . 'GMT');
        header('ETag: ' . $this->_dropoffID . $file['tmpname']);

        sendHTTPSecurity($NSSDROPBOX_PREFS);

        //  No browser caching, please:
        header('Cache-control: private');
        header('Pragma: private');
        header('Expires: 0');
        header('Content-Length: '.$decrypt_size);
        flush();
        ignore_user_abort(true);

        ob_start(); // Start buffering output
        $stdout = fopen('php://output', 'wb');
      }

      // Send a chunk of decrypted data
      set_time_limit(0);
      list($decrypted_chunk, $tag) = $res;
      // Because my chunks are a lot > than 8KB, I can't just "print"
      // as it may not send all of it.
      // So use fwrite($stdout), but that won't always send all the data,
      // it can just send some of it and still succeed.
      $bytes_to_write = strlen($decrypted_chunk);
      $fwrite_wrote  = 0;
      while ($fwrite_wrote < $bytes_to_write) {
        // We have at least 1 byte to write
        if ($fwrite_wrote == 0)
          $fwrite_wrote = fwrite($stdout, $decrypted_chunk);
        else
          $fwrite_wrote = fwrite($stdout,
                                 substr($decrypted_chunk, $bytes_written));
        // fwrite() only returns false on parameter errors,
        // but 0 on many other failures.
        // So if either happens, and we weren't trying to write nothing,
        // then bail out.
        if ($fwrite_wrote === false || $fwrite_wrote == 0) {
          $decrypt_failed = true;
          break;
        }
      }
      // Have now (hopefully!) written 1 chunk, so flush everything
      flush();
      ob_flush();

      // Only use the IV on the very first chunk, so wipe it now
      if (is_string($iv)) sodium_memzero($iv);
      $iv = '';
      $first_chunk = false; // Cannot bail out nicely now we've sent stuff

      $hit_finaltag = ($tag ===
                       SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);
    } while (!$hit_eof && !$hit_finaltag && !$decrypt_failed);

    // Have now sent the whole file or something went wrong.
    ob_end_flush();

    // Wipe the secret bits from memory properly
    if (is_string($password))        sodium_memzero($password);
    if (is_string($salt))            sodium_memzero($salt);
    if (is_string($key))             sodium_memzero($key);
    if (is_string($stream))          sodium_memzero($stream);
    if (is_string($res))             sodium_memzero($res);
    if (is_string($decrypted_chunk)) sodium_memzero($decrypted_chunk);

    fclose($fd_in);

    // Did it succeed or not?
    // Should be at end of file *and* have hit 'end' tag at the same time.
    // And of course the decrypt calls must have succeeded.
    $ok = ($hit_eof && $hit_finaltag && !$decrypt_failed);

    return $ok;
  }

  /* Construct a reasonably safe zipfile name for this drop-off.
     Note it does *not* include the ".zip" on the end.
  */
  public function zipForDownload() {
    global $smarty;

    $zipname = $smarty->getConfigVars('ServiceTitle') . '-' .
               $this->_claimID;
    $zipname = trim(str_replace(['"', "'", '\\', ';', "\n", "\r"],
                                '', $zipname));
    return $zipname;
  }

  /* Send the HTTP headers for a zip download.
     $when is an optional timestamp. Defaults to "now".
  */
  public function sendZipHeaders( $zipname, $when = NULL ) {

    global $NSSDROPBOX_PREFS;

    if (!$when)
      $when = time();

    header("Content-type: application/x-zip");
    // If we're talking to IE, encode the attachment filename or else
    // intl characters get garbled completely.
    // Thanks to UxBoD for this.
    // Changed urlencode to rawurlencode on advice from Liam Gretton.
    if (preg_match("/MSIE/", $_SERVER["HTTP_USER_AGENT"])) {
      header('Content-Disposition: attachment; filename="' .
             rawurlencode("$zipname.zip") . '"');
    } else {
      header('Content-Disposition: attachment; filename="' .
             $zipname . '.zip"');
    }
    header('Content-Transfer-Encoding: binary');
    header('Last-Modified: ' . substr(gmdate('r', $when), 0, -5) . 'GMT');
    header('ETag: ' . $zipname);

    sendHTTPSecurity($NSSDROPBOX_PREFS);

    //  No caching, please:
    header('Cache-control: private');
    header('Pragma: private');
    header('Expires: 0');
  }

  public function downloadEncryptedZip(
    $files, $passphrase
  )
  {
    $zipname = $this->zipForDownload();

    $this->_dropbox->writeToLog(sprintf('Info: downloading encrypted claimID %s as single zip file %s',
        $this->_claimID, "$zipname.zip"));

    $ivhex = '';
    $metadata = $this->_senderIP; // '|'-separated list of tokens
    $words = explode('|', $metadata);
    $skip = strlen('ENCRYPT:');
    foreach ($words as $word) {
      if (!strncasecmp($word, 'ENCRYPT:', $skip)) {
        $ivhex = substr($word, $skip);
        break;
      }
    }
    if ($ivhex === '') {
      // No IV data? Bail out!
      if (is_string($passphrase)) sodium_memzero($passphrase);
      return false;
    }
    $iv = sodium_hex2bin($ivhex);
    if (is_string($ivhex)) sodium_memzero($ivhex);
    # Obfuscate $iv and $passphrase in case they end up in a logfile
    $ivhex = sodium_bin2hex(obfuscateString($iv));
    if (is_string($iv)) sodium_memzero($iv);
    # Note they are different, as $iv is random data to start with.
    $passhex = sodium_bin2hex(obfuscateString(gzcompress($passphrase)));
    if (is_string($passphrase)) sodium_memzero($passphrase);

    // Here we go!
    // Delay sending HTTP headers until we know we can decrypt something
    $ok = true;
    ob_start();

    $regret = stream_register_wrapper("decrypt", "DecryptStream");
    if (!$regret) {
      $this->_dropbox->writeToLog("Error: Failed to register DecryptStream stream wrapper");
      flush();
      ob_end_flush();
      if (is_string($ivhex))      sodium_memzero($ivhex);
      if (is_string($passhex))    sodium_memzero($passhex);
      return false;
    }

    // Create the ZipStream and set its options
    $options = new ZipStream\Option\Archive();
    $options->setComment('Generated by ZendTo https://zend.to/');
    $options->setZeroHeader(true);
    $options->setSendHttpHeaders(false);
    $options->setLargeFileSize(20*1024*1024); // Don't compress beyond this
    $options->setLargeFileMethod(ZipStream\Option\Method::STORE());
    $options->setFlushOutput(true); // Flush after every write
    // Only use Zip64 if we have to, as it causes compatibility problems
    // as macOS's Archive Utility cannot unpack streamed Zip64 files
    // (as of macOS 10.14.5).
    // Rounded down the 4GB and 64k files limit, just to be safe.
    if ($this->bytes() > 4000000000 || count($files) > 65500) {
      $options->setEnableZip64(true);
    } else {
      $options->setEnableZip64(false);
    }

    $zip = new ZipStream\ZipStream("$zipname.zip", $options);

    // Send the HTTP headers after we know we can decrypt the first file.
    $sent_headers = false;
    foreach ($files as $file) {
      set_time_limit(0);
      $dec = @fopen('decrypt://'.$ivhex.':'.$passhex.'@a'.
                   $this->_claimDir."/".$file['tmpname'], 'rb');
      if ($dec === false) {
        $this->_dropbox->writeToLog(sprintf("Error: Failed to open decrypt stream from file %s", $this->_claimDir."/".$file['tmpname']));
        $ok = false;
        break;
      }
      if (!$sent_headers) {
        // Managed to start decrypting, and haven't sent HTTP headers yet
        $this->sendZipHeaders($zipname,
          filemtime($this->_claimDir."/".$file['tmpname']));
        $sent_headers = true;
      }

      $zip->addFileFromStream($zipname.'/'.$file['basename'], $dec);
      // Don't need to close $dec as ZipStream does it for us
      if (is_resource($dec)) fclose($dec);
      flush();
      ob_flush();
      $ok = (connection_status() > 0)?false:true;
      if (!$ok) break;
    }
    if ($ok && $sent_headers)
      $zip->finish();
    flush();
    ob_end_flush();
    if (is_string($ivhex))      sodium_memzero($ivhex);
    if (is_string($passhex))    sodium_memzero($passhex);
    return $ok;
  }



  /* Download an uncompressed zip file containing all the files in
     this drop-off. Use streams to avoid running out of memory or
     temporary disk space.
  */
  public function downloadPlainZip(
    $files
  )
  {
    $zipname = $this->zipForDownload();

    $this->_dropbox->writeToLog(sprintf('Info: downloading claimID %s as single zip file %s',
        $this->_claimID, "$zipname.zip"));

    // Here we go!
    $this->sendZipHeaders($zipname,
           filemtime($this->_claimDir."/".$files[0]['tmpname']));
    $ok = true;
    ob_start();

    // Create the ZipStream and set its options
    $options = new ZipStream\Option\Archive();
    $options->setSendHttpHeaders(false);
    $options->setComment('Generated by ZendTo https://zend.to/');
    $options->setLargeFileSize(20*1024*1024); // Don't compress beyond this
    $options->setLargeFileMethod(ZipStream\Option\Method::STORE());
    $options->setZeroHeader(true); // Give the metadata at the end
    $options->setFlushOutput(true); // Flush after every write
    // Only use Zip64 if we have to, as it causes compatibility problems
    // as macOS's Archive Utility cannot unpack streamed Zip64 files
    // (as of macOS 10.14.5).
    // Rounded down the 4GB and 64k files limit, just to be safe.
    if ($this->bytes() > 4000000000 || count($files) > 65500) {
      $options->setEnableZip64(true);
    } else {
      $options->setEnableZip64(false);
    }

    $zip = new ZipStream\ZipStream("$zipname.zip", $options);

    foreach ($files as $file) {
      set_time_limit(0);
      $zip->addFileFromPath($zipname.'/'.$file['basename'],
                            $this->_claimDir."/".$file['tmpname']);
      flush();
      ob_flush();
      $ok = (connection_status() > 0)?false:true;
      if (!$ok) break;
    }
    $zip->finish();
    flush();
    ob_end_flush();
    return $ok;
  }


  /*!
    @function downloadPlaintext

    Given a file to download, send it to the user's browser.
    Returns true on success, false otherwise.
    This support byte ranges so partial files can be sent if needed.
  */
  public function downloadPlaintext(
    $file
  )
  {
    global $NSSDROPBOX_PREFS;

    header("Content-type: " . htmlspecialchars($file['mimeType']));
    // If we're talking to IE, encode the attachment filename or else
    // intl characters get garbled completely.
    // Thanks to UxBoD for this.
    // Changed urlencode to rawurlencode on advice from Liam Gretton.
    if (preg_match("/MSIE/", $_SERVER["HTTP_USER_AGENT"])) {
      header('Content-Disposition: attachment; filename="' .
             rawurlencode($file['basename']) . '"');
    } else {
      header('Content-Disposition: attachment; filename="' .
             $file['basename'] . '"');
    }
    header('Content-Transfer-Encoding: binary');

    //  Range-based support stuff:
    header('Last-Modified: ' . substr(gmdate('r', filemtime($this->_claimDir."/".$file['tmpname'])), 0, -5) . 'GMT');
    header('ETag: ' . $this->_dropoffID . $file['tmpname']);
    header('Accept-Ranges: bytes');

    sendHTTPSecurity($NSSDROPBOX_PREFS);

    //  No caching, please:
    header('Cache-control: private');
    header('Pragma: private');
    header('Expires: 0');

    // JKF was $fullSize = $file['lengthInBytes'];
    // Changed to read real file size not what the DB said.
    // If it was a library file, then the real file might have been
    // changed between the drop-off being made and the file being
    // downloaded. Must have the correct file size.
    $fullSize = filesize($this->_claimDir."/".$file['tmpname']);

    //  Multi-thread and resumed downloading should be supported by this next
    //  block:
    if ( isset($_SERVER['HTTP_RANGE']) ) {
      if ( preg_match('/^[Bb][Yy][Tt][Ee][Ss]=([0-9]*)-([0-9]*)$/',$_SERVER['HTTP_RANGE'],$rangePieces) ) {
        if ( is_numeric($rangePieces[1]) && ($offset = intval($rangePieces[1])) ) {
          if ( ($offset >= 0) && ($offset < $fullSize) ) {
            //  Are we doing an honest-to-god range, or a start-to-end range:
            if ( is_numeric($rangePieces[2]) && ($endOfRange = intval($rangePieces[2])) ) {
              if ( $endOfRange >= 0 ) {
                if ( $endOfRange >= $fullSize ) {
                  $endOfRange = $fullSize - 1;
                }
                if ( $endOfRange >= $offset ) {
                  $length = $endOfRange - $offset + 1;
                } else {
                  $offset = 0; $length = $fullSize;
                }
              } else {
                $offset = 0; $length = $fullSize;
              }
            } else {
              //  start-to-end range:
              $length = $fullSize - $offset;
            }
          } else {
            $offset = 0; $length = $fullSize;
          }
        } else if ( is_numeric($rangePieces[2]) && ($length = intval($rangePieces[2])) ) {
          //  The last $rangePieces[2] bytes of the file:
          $offset = $fullSize - $length;
          if ( $offset < 0 ) {
            $offset = 0; $length = $fullSize;
          }
        } else {
          $offset = 0;
          $length = $fullSize;
        }
      } else {
        $offset = 0;
        $length = $fullSize;
      }
    } else {
      $offset = 0;
      $length = $fullSize;
    }
    if ( ($offset > 0) && ($length < $fullSize) ) {
      header("HTTP/1.1 206 Partial Content");
      $this->_dropbox->writeToLog(sprintf('Info: downloading claimID %s file %s - partial download of %d bytes, range %d - %d / %d (%s)',
          $this->_claimID,
          $file['basename'],
          $length,
          $offset,
          $offset + $length - 1,
          $fullSize,
          $_SERVER['HTTP_RANGE']
        )
      );
    } else {
      $this->_dropbox->writeToLog(sprintf('Info: downloading claimID %s file %s - complete download of %d bytes',
          $this->_claimID,
          $file['basename'],
          $length
        )
      );
    }
    header(sprintf('Content-Range: bytes %d-%d/%d',$offset,$offset + $length - 1,$fullSize));
    header('Content-Length: '.$length);

    //  Open the file:
    $ok = true;
    ob_start();
    $fptr = fopen($this->_claimDir."/".$file['tmpname'],'rb');
    if ($fptr) {
      fseek($fptr,$offset);
      //while ( ! feof($fptr) && ! connection_aborted() ) {
      while ( ! feof($fptr) ) {
        set_time_limit(0);
        print( fread($fptr,8 * 1024) );
        flush();
        ob_flush();
      }
      fclose($fptr);
      ob_end_flush();
      $ok = (connection_status() > 0)?false:true;
    } else {
      $ok = false;
    }

    return $ok;
  }

  /*!
    @function isEncrypted

    Returns true or false depending on whether the current drop-off is
    encrypted or not. Only works when the _senderIP has been set.
    So does not work in new drop-offs, only when we are retrieving them.
  */
  public function isEncrypted()
  {
    // May return 0 (if found at start of string) so must use ===
    if (strpos($this->_senderIP, '|ENCRYPT:') === false)
      return false;
    else
      return true;
  }

  /*
    Does this drop-off enforrce the showing of a recipient waiver?
  */
  public function showRecipWaiver()
  {
    if (strpos($this->_senderIP, '|WAIVER') === false)
      return false;
    else
      return true;
  }

  /*!
    @function downloadFile
    
    Given a fileID -- which is simply a rowID from the "file" table in
    the database -- attempt to download that file.  Download requires that
    NO HTTP headers have been transmitted yet, so we have to be very
    careful to call this function BEFORE the PHP has generated ANY output.
    
    We do quite a bit of logging here:
    
    * Log the pickup to the database; this gives the authorized sender
      the ability to examine who made a pick-up, from when and where.
    
    * Log the pickup to the log file -- UID for auth users, 'emailAddr'
      possibly coming in from a form, or anonymously; claim ID; file
      name.
    
    If all goes well, then the user gets a file and we return TRUE.
    Otherwise, and FALSE is returned.
  */
  public function downloadFile(
    $fileID
  )
  {
    global $smarty;
    global $NSSDROPBOX_PREFS;

    //  First, make sure we've been properly authenticated:
    if ( !$this->_okayForDownloads )
      return false;

    // The passed in $fileID must either be a positive integer, or "all"
    if ( $fileID !== 'all' && !ctype_digit($fileID) )
      return false;

    //  Do we have such a file on-record?
    $fileList = $this->_dropbox->database->DBFileList($this->_dropoffID,
                                                      $fileID);
    // This dropoff file doesn't exist
    if ( !$fileList || count($fileList)==0 ) {
      if ($this->_dropbox->isAutomated()) {
        header("X-ZendTo-Response: ".
             json_encode(array("status" => "error", "error" => "Bad fileID")));
        exit(1);
      }
      return false;
    }

    @ob_end_clean(); //turn off output buffering to decrease cpu usage

    $downloadSuccess = true;
    // Are we downloading the entire drop-off as a zip file?
    if ($fileID === 'all') {
      if ($this->isEncrypted()) {
        // Download encrypted drop-off as a zip file
        $downloadSuccess = $this->downloadEncryptedZip($fileList, @$_POST['n']);
        if (is_string(@$_POST['n'])) sodium_memzero($_POST['n']);
        if (!$downloadSuccess) {
          $this->_dropbox->writeToLog(sprintf('Warning: decrypting claimID %s to send as zip failed',
              $this->_claimID));
          if ($this->_dropbox->isAutomated()) {
            header("X-ZendTo-Response: ".
               json_encode(array("status" => "error", "error" => "Decryption failed")));
            exit(1);
          }
          NSSError(gettext("Decrypting and downloading the file failed. You probably entered the passphrase incorrectly. Please try again."), gettext("Decryption failed"));
        }
      } else {
        // Download unencrypted drop-off as a zip file
        $downloadSuccess = $this->downloadPlainZip($fileList);
      }
    } else {
      // Downloading a single file in fileID
      if ($this->isEncrypted()) {
        $downloadSuccess = $this->downloadEncrypted($fileList[0], @$_POST['n']);
        if (is_string(@$_POST['n'])) sodium_memzero($_POST['n']);
        if (!$downloadSuccess) {
          // Download or decryption failed. No headers have been output
          // at all by now, so we can still generate an HTML page to
          // show the error message and the show_dropoff.tpl form again.
          $this->_dropbox->writeToLog(sprintf('Warning: decrypting claimID %s file %s failed',
              $this->_claimID,
              $fileList[0]['basename']
            )
          );
          if ($this->_dropbox->isAutomated()) {
            header("X-ZendTo-Response: ".
               json_encode(array("status" => "error", "error" => "Decryption failed")));
            exit(1);
          }
          NSSError(gettext("Decrypting and downloading the file failed. You probably entered the passphrase incorrectly. Please try again."), gettext("Decryption failed"));
        }
      } else {
        // No key stored, so it's not encrypted. So treat as before.
        $downloadSuccess = $this->downloadPlaintext($fileList[0]);
      }
    }

    //  Who made the pick-up?
    $whoWasIt = $this->_dropbox->authorizedUser();
    $unknown  = gettext('one of the recipients');
    $whoWasItEmail = '';
    $whoWasItUID = '';
    if ( $whoWasIt ) {
      $whoWasItUID = $whoWasIt;
      $whoWasIt = $this->_dropbox->authorizedUserData('displayName');
      $whoWasItEmail = $this->_dropbox->authorizedUserData('mail');
    } else {
      if (isset($_POST['emailAddr']) && strlen($_POST['emailAddr'])>0) {
        $whoWasIt = trim($_POST['emailAddr']);
        $whoWasIt = preg_replace('/[<>]/', '', $whoWasIt); // Protect it
        if (strcasecmp($whoWasIt, $unknown) == 0) {
          $whoWasIt = $unknown;
          $whoWasItEmail = " ";
        } else if (preg_match($this->_dropbox->validEmailRegexp(),
                        $whoWasIt,$wWIParts) ) {
          $whoWasItEmail = $wWIParts[1]."@".$wWIParts[2];
        } else {
          $whoWasIt      = $unknown;
          $whoWasItEmail = " ";
        }
      } else if (strlen($_GET['emailAddr'])>0) {
        $whoWasIt = trim($_GET['emailAddr']);
        $whoWasIt = preg_replace('/[<>]/', '', $whoWasIt); // Protect it
        if (strcasecmp($whoWasIt, $unknown) == 0) {
          $whoWasIt = $unknown;
          $whoWasItEmail = " ";
        } else if (preg_match($this->_dropbox->validEmailRegexp(),
                        $whoWasIt,$wWIParts) ) {
          $whoWasItEmail = $wWIParts[1]."@".$wWIParts[2];
        } else {
          $whoWasIt      = $unknown;
          $whoWasItEmail = " ";
        }
      } else {
        $whoWasIt = $unknown;
        $whoWasItEmail = preg_replace('/ /', '', $whoWasIt);
      }
    }

    //  Only send emails, etc, if the transfer didn't end with an aborted
    //  connection:
    if ( !$downloadSuccess ) {
      if ($fileID === 'all') {
        $this->_dropbox->writeToLog(sprintf('Warning: downloading claimID %s as zip - aborted by %s',
              $this->_claimID,
              ( $whoWasItUID ? $whoWasItUID : $whoWasIt )
        ));
      } else {
        $this->_dropbox->writeToLog(sprintf('Warning: downloading claimID %s file %s - aborted by %s',
              $this->_claimID,
              $fileList[0]['basename'],
              ( $whoWasItUID ? $whoWasItUID : $whoWasIt )
        ));
      }
      if ($this->_dropbox->isAutomated()) {
        header("X-ZendTo-Response: ".
             json_encode(array("status" => "error", "error" => "Download aborted")));
        exit(1);
      }
      return false;
    }

    //  Have any pick-ups been made already?
    $extantPickups = $this->_dropbox->database->DBExtantPickups($this->_dropoffID, $whoWasItEmail);
   
    // if ( $this->_confirmDelivery && (! $extantPickups || ($extantPickups[0][0] == 0)) ) {
    if ( $this->_confirmDelivery && ! $extantPickups ) {
      $this->_dropbox->writeToLog("Info: sending pickup confirmation email to ".$this->_senderEmail." for claim ".$this->_claimID);
      $senderIP = stripToSenderIP($this->_senderIP); // Ditch other cack in it!
      $senderHost = gethostbyaddr($senderIP);
      if ($senderHost != '') {
        $senderHost = '(' . $senderHost . ')';
      }
      $smarty->assign('whoWasIt', $whoWasIt);
      $smarty->assign('claimID', $this->_claimID);
      $smarty->assign('filename', htmlentities($fileList[0]['basename'], ENT_NOQUOTES, 'UTF-8'));
      $smarty->assign('remoteAddr', getClientIP($NSSDROPBOX_PREFS));
      $smarty->assign('hostname', gethostbyaddr(getClientIP($NSSDROPBOX_PREFS)));
      $smarty->assign('senderName',     $this->_senderName);
      $smarty->assign('senderOrg',      $this->_senderOrganization);
      $smarty->assign('senderEmail',    $this->_senderEmail);
      $smarty->assign('showIP',         $this->_dropbox->emailSenderIP());
      $smarty->assign('senderIP',       $senderIP);
      $smarty->assign('senderHost',     $senderHost);
      $smarty->assign('createdDate', timeForDate($this->created()));
      $smarty->assign('note',           trim($this->_note));

      $files = $this->files();
      $realFileCount = $files['totalFiles'];
      // Files in email count from 0, files from database count from 0.
      $tplFiles = array();
      for ($i=0; $i<$realFileCount; $i++) {
        $tplFiles[$i] = array();
        $file = $files[$i];
        $tplFiles[$i]['name'] = $file['basename'];
        $tplFiles[$i]['type'] = $file['mimeType'];
        $tplFiles[$i]['size'] = NSSFormattedMemSize($file['lengthInBytes']);
        $tplFiles[$i]['description'] = $file['description'];
        if ($file['checksum'] != '')
          $tplFiles[$i]['checksum'] = $file['checksum'];
        else
          $tplFiles[$i]['checksum'] = gettext('Not calculated');
      }
      $smarty->assignByRef('files', $tplFiles);
      $smarty->assign('fileCount',      $realFileCount);

      $emailSubject = $smarty->getConfigVars('EmailSubjectTag') .
                 sprintf(gettext('%s has picked up your drop-off!'), $whoWasIt);

      $htmltpl = '';
      $texttpl = '';
      if ($smarty->templateExists('pickup_email_html.tpl')) {
        try {
          $htmltpl = $smarty->fetch('pickup_email_html.tpl');
        }
        catch (SmartyException $e) {
          $this->_dropbox->writeToLog("Error: Could not create pickup email HTML: ".$e->getMessage());
          $htmltpl = $e->getMessage();
        }
      }
      try {
        $texttpl = $smarty->fetch('pickup_email.tpl');
      }
      catch (SmartyException $e) {
        $this->_dropbox->writeToLog("Error: Could not create pickup email text: ".$e->getMessage());
        $texttpl = $e->getMessage();
      }
      if ( ! $this->_dropbox->deliverEmail(
              $this->_senderEmail,
              $whoWasItEmail,
              '',
              $emailSubject,
              $texttpl,
              $htmltpl
           )
         ) {
        $this->_dropbox->writeToLog("Error: failed to deliver confirmation email to ".$this->_senderEmail." for claim ".$this->_claimID);
      }
    } else {
      $this->_dropbox->writeToLog("Info: no need to send confirmation email to ".$this->_senderEmail." for claim ".$this->_claimID);
    }
    if ($fileID === 'all') {
      $this->_dropbox->writeToLog(sprintf("Info: pickup of claimID %s as zip by %s%scompleted.",
            $this->_claimID,
            $whoWasItUID ? $whoWasItUID : $whoWasIt,
            $this->showRecipWaiver() ? ' (waiver accepted) ' : ' '
      ));
    } else {
      $this->_dropbox->writeToLog(sprintf("Info: pickup of claimID %s file %s by %s%scompleted.",
            $this->_claimID,
            $fileList[0]['basename'],
            $whoWasItUID ? $whoWasItUID : $whoWasIt,
            $this->showRecipWaiver() ? ' (waiver accepted) ' : ' '
      ));
    }
    
    //  Add to the pickup log:
    $this->_dropbox->database->DBAddToPickupLog($this->_dropbox,
                     $this->_dropoffID,
                     $this->_dropbox->authorizedUser(),
                     $whoWasItEmail,
                     getClientIP($NSSDROPBOX_PREFS),
                     timestampForTime(time()),
                     $this->_claimID);

    return TRUE;
  }


  /*!
    @function resendDropoff
    
    Re-send the dropoff to its recipients.

    If $touchit is 1, then update the datestamp to reset the expiry timer.
    If $touchit is FALSE, then it's a reminder, so change the text.
  */
  public function resendDropoff(
    $touchit = FALSE
  )
  {
        global $NSSDROPBOX_URL;
        global $smarty;

        $senderName = $this->_senderName;
        $senderOrganization = $this->_senderOrganization;
        $senderEmail = $this->_senderEmail;
        $senderIP = stripToSenderIP($this->_senderIP);
        $senderHost = gethostbyaddr($senderIP);
        if ($senderHost != '') {
          $senderHost = '(' . $senderHost . ')';
        }
        // Note doesn't want to be escaped here as it's escaped in the HTML
        // email template, and we want the plain text too for the plain email.
        // $note = htmlentities($this->_note, ENT_NOQUOTES, 'UTF-8');
        $note = $this->_note;
        $claimID = $this->_claimID;
        $claimPasscode = $this->_claimPasscode;
        $isReminder = FALSE;
        $informPasscode = $this->_resendWithPasscode;
        $files = $this->files();
        $realFileCount = $files['totalFiles'];
        // Work out the real email subject line.
        $subject = trim($this->_subject);
        if (empty($subject)) {
          if ($realFileCount == 1) {
            $emailSubject = sprintf($smarty->getConfigVars(
                                    'EmailSubjectTag') .
                                    gettext('%s has dropped off a file for you'),
                                    $senderName);
          } else {
            $emailSubject = sprintf($smarty->getConfigVars(
                                    'EmailSubjectTag') .
                                    gettext('%s has dropped off files for you'),
                                    $senderName);
          }
        } else {
          $emailSubject = $smarty->getConfigVars('EmailSubjectTag') . $subject;
        }
        // Files in email count from 0, files from database count from 0.
        $tplFiles = array();
        for ($i=0; $i<$realFileCount; $i++) {
          $tplFiles[$i] = array();
          $file = $files[$i];
          $tplFiles[$i]['name'] = $file['basename'];
          // If it's automated, all the mimeTypes will be 'application/octet-stream' which just confuses users.
          $tplFiles[$i]['type'] = ($this->_dropbox->isAutomated())?'':$file['mimeType'];
          $tplFiles[$i]['size'] = NSSFormattedMemSize($file['lengthInBytes']);
          $tplFiles[$i]['description'] = $file['description'];
          if ($file['checksum'] != '')
            $tplFiles[$i]['checksum'] = $file['checksum'];
          else
            $tplFiles[$i]['checksum'] = gettext('Not calculated');
        }

        // Work out the time left if we need to.
        if ($touchit) {
          // Are updating timestamp like we used to.
          // This currently doesn't use the user-specified lifetime,
          // just the prefs default. So re-sending (and updating its
          // lifetime) will set its lifetime to defulatLifetime from
          // prefs, not the lifetime the user originally gave it.
          $daysLeft    = $this->_dropbox->defaultLifetime();
          $timeLeft    = sprintf(gettext('%d days'), $daysLeft);
          $secs_remaining = $daysLeft*3600*24;
          $createdTime = timestampForTime(time());
          $isReminder  = FALSE;
        } else {
          // Not updating timestamp at all
          $secs_gone = time() - timeForDate($this->_created);
          // This is stored in the dropoff itself now.
          $secs_remaining = timeForDate($this->_lifeseconds) - $secs_gone;
          $timeLeft = secsToString($secs_remaining);
          // $secs_remaining = $this->_dropbox->retainDays()*86400 - $secs_gone;
          // if ($secs_remaining <= 86400) {
          //   $things_remaining = intval($secs_remaining/3600);
          //   $things = ($things_remaining==1) ? gettext('1 hour') : gettext('%d hours');
          // } else {
          //   $things_remaining = intval($secs_remaining/86400);
          //   $things = ($things_remaining==1) ? gettext('1 day') : gettext('%d days');
          // }
          // $timeLeft = sprintf($things, $things_remaining);
          $daysLeft = intval($secs_remaining/86400); // backwards compat
          $createdTime = timestampForDate($this->_created);
          $isReminder  = TRUE;
        }

        //  Construct the email notification and deliver:
        $smarty->assign('senderName',  $senderName);
        $smarty->assign('senderOrg',   $senderOrganization);
        $smarty->assign('senderEmail', $senderEmail);
        $smarty->assign('showIP',      $this->_dropbox->emailSenderIP());
        $smarty->assign('senderIP',    $senderIP);
        $smarty->assign('senderHost',  $senderHost);
        $smarty->assign('note',        trim($note));
        $smarty->assign('subject',     $emailSubject);
        $smarty->assign('now',         $createdTime);
        $smarty->assign('claimID',     $claimID);
        $smarty->assign('claimPasscode', $claimPasscode);
        $smarty->assign('informPasscode', $informPasscode);
        $smarty->assign('fileCount',   $realFileCount);
        $smarty->assign('retainDays',  $daysLeft);
        $smarty->assign('timeLeft',    $timeLeft);
        $smarty->assign('isReminder',  $isReminder);
        $smarty->assign('isEncrypted', $this->isEncrypted());
        $smarty->assignByRef('files',  $tplFiles);

        // advertisedServerRoot overrides serverRoot if it's defined
        $urlroot = $this->_dropbox->advertisedServerRoot();
        if ($urlroot) {
          // They *did* end it with a / didn't they??
          if (substr($urlroot, -1) !== '/') $urlroot .= '/';
          $smarty->assign('zendToURL', $urlroot);
          $smarty->assign('linkURL', $urlroot);
        } else {
          $smarty->assign('zendToURL', $NSSDROPBOX_URL);
          $smarty->assign('linkURL', $NSSDROPBOX_URL);
        }

        $emailTXTContent = '';
        $emailHTMLContent = '';
        try {
          $emailTXTContent = $smarty->fetch('dropoff_email.tpl');
        }
        catch (SmartyException $e) {
          $this->_dropbox->writeToLog("Error: Could not create dropoff email text: ".$e->getMessage());
          $emailTXTContent = $e->getMessage();
        }
        if ($smarty->templateExists('dropoff_email_html.tpl')) {
          try {
            $emailHTMLContent = $smarty->fetch('dropoff_email_html.tpl');
          }
          catch (SmartyException $e) {
            $this->_dropbox->writeToLog("Error: Could not create dropoff email HTML: ".$e->getMessage());
            $emailHTMLContent = $e->getMessage();
          }
        }

        // We've now created the email message texts, so don't need this
        // "email-specific" root URL any more.
        // Just to play safe, in case we end up using this in the
        // web page we display after sending all the emails,
        // let's put it back how it was.
        $smarty->assign('zendToURL', $NSSDROPBOX_URL);

        // Make the mail come from the sender, not ZendTo
        foreach ( $this->_recipients as $recipient ) {
          // In MyZendTo, don't send email to myself
          //if (preg_match('/^[^yYtT1]/', MYZENDTO) ||
          //    (preg_match('/^[yYtT1]/', MYZENDTO) && $senderEmail != $recipient[1])) {
            // Bug fix by Sebastian Tyler
            $emailTXTContent2 = preg_replace('/__EMAILADDR__/',
                            urlencode($recipient[1]), $emailTXTContent);
            $emailHTMLContent2 = preg_replace('/__EMAILADDR__/',
                            urlencode($recipient[1]), $emailHTMLContent);
            $success = $this->_dropbox->deliverEmail(
                $recipient[1],
                $senderEmail,
                $senderName,
                $emailSubject,
                $emailTXTContent2,
                $emailHTMLContent2
             );
            if ( ! $success ) {
              $this->_dropbox->writeToLog(sprintf("Error: re-delivery of notification email failed to %s for claimID $claimID",$recipient[1]));
            } else {
              // Succeeded, so touch the dropoff's datestamp to reset number
              // of days until auto-deletion
              if ($touchit) {
                $this->_dropbox->database->DBTouchDropoff($claimID,
                                             $createdTime);
              }
              $this->_dropbox->writeToLog(sprintf("Info: re-delivery of notification email succeeded to %s for claimID $claimID",$recipient[1]));
            }
          //}
        }

        return TRUE;
  }

  /*!
    @function removeDropoff
    
    Scrub the database and on-disk directory for this dropoff, effectively
    removing it.  We do some writing to the log file to make sure we know
    when this happens.
  */
  public function removeDropoff(
    $doLogEntries = TRUE
  )
  {
    $who = $this->_dropbox->authorizedUser();
    if (! $who)
      $who = "auto-expiry";

    if ( is_dir($this->_claimDir) ) {
      //  Remove the contents of the directory:
      if ( rmdir_r($this->_claimDir) ) {
        if ( $doLogEntries ) {
          $this->_dropbox->writeToLog("Info: drop-off directory for claimID ".$this->_claimID." deleted by ".$who);
        }
      } else {
        if ( $doLogEntries ) {
          $this->_dropbox->writeToLog("Error: could not delete drop-off directory ".$this->_claimDir." for claimID ".$this->_claimID);
        }
        // Don't bail out if this failed, try to continue: return FALSE;
      }
    }

    //  Remove any stuff from the database:
    if ( $this->_dropbox->database->DBRemoveDropoff($this->_dropbox, $this->_dropoffID, $this->_claimID) ) {
      if ( $doLogEntries ) {
        $this->_dropbox->writeToLog("Info: drop-off records for claimID ".$this->_claimID." removed by ".$who);
      }
    } else {
      if ( $doLogEntries ) {
        $this->_dropbox->writeToLog("Error: could not delete drop-off records for claimID ".$this->_claimID);
      }
      // Don't bail out if this failed, try to continue: return FALSE;
    }

    if ( $doLogEntries ) {
      $this->_dropbox->writeToLog("Info: drop-off removal complete for claimID ".$this->_claimID." removed by ".$who);
    }
    return TRUE;
  }

  /*!
    @function HTMLOnLoadJavascript
    
    Returns the "[form name].[field name]" string that's most appropriate for the page
    that's going to display this object.  Basically allows us to give focus to the
    claim ID or passcode field according to what data we have so far.
  */
  public function HTMLOnLoadJavascript()
  {
    if ( $this->_showPasscodeHTML ) {
      if ( !$this->_invalidClaimID && (@$_GET['claimID'] && !@$_GET['claimPasscode']) || (@$_POST['claimID'] && !@$_POST['claimPasscode']) ) {
        return "pickup.claimPasscode";
      }
      return "pickup.claimID";
    }
    return NULL;
  }

  /*!
    @function HTMLWrite
    
    Composes and writes the HTML that should be output for this
    instance.  If the instance is a fully-initialized, existing
    dropoff, then we'll wind up calling HTMLSummary().  Otherwise,
    we output one of several possible errors (wrong claim passcode,
    e.g.) and possibly show the claim ID and passcode "dialog".
  */
  public function HTMLWrite()
  {
    global $NSSDROPBOX_URL;
    global $NSSDROPBOX_PREFS;
    global $smarty;

    $claimID = $this->_claimID;
    
    $smarty->assign('maxBytesForFile', NSSFormattedMemSize($this->_dropbox->maxBytesForFile()));
    $smarty->assign('maxBytesForDropoff', NSSFormattedMemSize($this->_dropbox->maxBytesForDropoff()));
    $smarty->assign('retainDays',  $this->_dropbox->retainDays());

    if ( $this->_invalidClaimID ) {
      NSSError(gettext("The Claim ID or Passcode was incorrect, or the drop-off has expired. Please re-check and note that drop-offs must be collected before they expire otherwise they are automatically deleted."),
               gettext("Invalid Claim ID or Passcode"));
      $claimID = ( $this->_cameFromEmail ? $_GET['claimID'] : $_POST['claimID'] );
    }

    // Sanitise $claimID before using it
    $claimID = preg_replace('/[^a-zA-Z0-9]/', '', trim($claimID));
    if ( $this->_invalidClaimPasscode ) {
      NSSError(gettext("The Claim ID or Passcode was incorrect, or the drop-off has expired. Please re-check and note that drop-offs must be collected before they expire otherwise they are automatically deleted."),
               gettext("Invalid Claim ID or Passcode"));
      // NEW And log the failed attempt at guessing the passcode!
      // Only log and count and delete if it's a real dropoff
      if (! $this->_invalidClaimID) {
        $dID = $this->_dropoffID;
        $this->_dropbox->database->DBAddToPickupLog($this->_dropbox,
                       $dID,
                       $this->_dropbox->authorizedUser(),
                       'FAILEDATTEMPT',
                       getClientIP($NSSDROPBOX_PREFS),
                       timestampForTime(time()),
                       $claimID);
        // How many failures have we had?
        $failuresCount = $this->_dropbox->database->DBPickupFailures($dID);
        $maxFailures = $this->_dropbox->maxPickupFailures();
        if ($maxFailures>0 && $failuresCount >= $maxFailures) {
          // Too many failures, so this drop-off must self-destruct!
          // Very easy, just delete it from under myself.
          // The rest of this fn will handle the UI just fine.
          $this->_dropbox->writeToLog("Warning: drop-off for claimID ".$claimID." removed due to too many failed pickup attempts (".$failuresCount." >= ".$maxFailures."!");
          $this->removeDropoff(TRUE); // Force logging on
        }
      }
    }

    // If we are automated, then check the validity of the ClaimID and
    // Passcode carefully. Don't want to give away info we shouldn't.
    if ($this->_dropbox->isAutomated() &&
        ($this->_invalidClaimID       ||
         $this->_invalidClaimPasscode ||
         $this->_showPasscodeHTML)) {
      header("X-ZendTo-Response: ".
             json_encode(array("status" => "error", "error" => "Invalid ClaimID or Passcode")));
      exit;
    }

    $smarty->assign('claimID', $claimID);
    if ( $this->_isNewDropoff ) {
      if ( $this->_formInitError ) {
        if ($this->_dropbox->isAutomated()) {
          header("X-ZendTo-Response: ".
                 json_encode(array("status" => "error", "error" => "dropoff error: ". $this->_formInitError)));
          exit;
        } else {
          NSSError($this->_formInitError, gettext("Upload Error"));
        }
      } else {
        if ($this->_dropbox->isAutomated()) {
          $results = array();
          $results['status'] = 'OK';
          $results['lifetimedays'] = floatval(timeForDate($this->_created) + $this->lifeseconds() - time())/(3600*24);
          // Need to return the checksums if there are any
          $files = $this->files();
          $realFileCount = $files['totalFiles'];
          $fileinfo = array();
          for ($i=0; $i<$realFileCount; $i++) {
            $fileinfo[$i]['name'] = $files[$i]['basename'];
            $fileinfo[$i]['size'] = $files[$i]['lengthInBytes'];
            if ($this->_dropbox->checksum())
              $fileinfo[$i]['checksum'] = $files[$i]['checksum'];
          }
          $results['files'] = $fileinfo;

          // Construct the link to the drop-off for each recipient
          // advertisedServerRoot overrides serverRoot if it's defined
          $link = '';
          $urlroot = $this->_dropbox->advertisedServerRoot();
          if ($urlroot) {
            // They *did* end it with a / didn't they??
            if (substr($urlroot, -1) !== '/') $urlroot .= '/';
            $link = $urlroot;
          } else {
            $link = $NSSDROPBOX_URL;
          }
          $link .= ($this->_dropbox->hidePHP())?'pickup':'pickup.php';
          $link .= '?claimID=' . $this->claimID() .
                   '&claimPasscode=' . $this->claimPasscode();
          // That's got the start of the URLs done, now personalise them
          $recilist = array();
          foreach ($this->_recipients as $reci) {
            $recilist[] = array('name'  => $reci[0],
                                'email' => $reci[1],
                                'link'  => $link.'&emailAddr='.
                                           urlencode($reci[1]));
          }
          $results['recipients'] = $recilist;
          header("X-ZendTo-Response: " . json_encode($results));
          exit;
        } else {
          $this->HTMLSummary(FALSE,TRUE);
          return 'show_dropoff.tpl';
        }
      }
    } else if ( $this->_showPasscodeHTML ) {
      $smarty->assign('cameFromEmail', $this->_cameFromEmail);
      $smarty->assign('emailAddr', $this->_emailAddr);
      return 'claimid_box.tpl';
    } else {
      if ($this->_dropbox->isAutomated()) {
        // Write the JSON header containing the data all about the drop-off
        $results = array();
        $results['status'] = 'OK';
        $results['sendername'] = $this->senderName();
        $results['senderorganization'] = $this->senderOrganization();
        $results['senderemail'] = $this->senderEmail();
        $results['senderip'] = explode('|',$this->senderIP())[0];
        $results['note'] = $this->note();
        $results['encrypted'] = $this->isEncrypted();
        $results['created'] = timeForDate($this->created());
        $results['expiry'] = timeForDate($this->created())+$this->lifeseconds();
        // $results['expiry'] = timeForDate($this->created())+3600*24*$this->_dropbox->retainDays();
        // Work out readable string of approx time remaining until expiry
        $secs_gone = time() - timeForDate($this->_created);
        $secs_remaining = $this->lifeseconds() - $secs_gone;
        $results['expiresin'] = secsToString($secs_remaining);
        // if ($secs_remaining <= 86400) {
        //   $things_remaining = intval($secs_remaining/3600);
        //   $things = ($things_remaining==1) ? gettext('1 hour')
        //                                    : gettext('%d hours');
        // } else {
        //   $things_remaining = intval($secs_remaining/86400);
        //   $things = ($things_remaining==1) ? gettext('1 day')
        //                                    : gettext('%d days');
        // }
        // $timeLeft = sprintf($things, $things_remaining);
        // $results['expiresin'] = $timeLeft;

        $files = $this->files();
        $realFileCount = $files['totalFiles'];
        $results['totalFiles'] = $realFileCount;
        $results['totalBytes'] = $files['totalBytes'];
        $fileinfo = array();
        for ($i=0; $i<$realFileCount; $i++) {
          $fileinfo[$i]['id']          = $files[$i]['rowID'];
          $fileinfo[$i]['name']        = $files[$i]['basename'];
          $fileinfo[$i]['size']        = $files[$i]['lengthInBytes'];
          $fileinfo[$i]['mimetype']    = $files[$i]['mimeType'];
          $fileinfo[$i]['checksum']    = $files[$i]['checksum'];
          $fileinfo[$i]['description'] = $files[$i]['description'];
        }
        $results['files'] = $fileinfo;
        header("X-ZendTo-Response: " . json_encode($results));
        exit;
      } else {
        $this->HTMLSummary(TRUE);
        return 'show_dropoff.tpl';
      }
    }
    return "";
  }

  /*!
    @function HTMLSummary
    
    Compose and write the HTML that shows all of the info for a dropoff.
    This includes:
    
    * A table of claim ID and passcode; sender info; and list of recipients
    
    * A list of the files included in the dropoff.  The icons and names in
      this list will be hyperlinked as download triggers if the $clickable
      argument is TRUE.
    
    * A table of the pickup history for this dropoff.
    
  */
  public function HTMLSummary(
    $clickable = FALSE,
    $overrideShowRecips = FALSE
  )
  {
    global $NSSDROPBOX_URL;
    global $smarty;
    global $php_self;

    $curUser = $this->_dropbox->authorizedUser();
    $curUserEmail = $this->_dropbox->authorizedUserData("mail");
    $isSender = FALSE;
    $isAdmin  = FALSE;
    $overrideShowRecips = FALSE;
    if ( $curUser ) {
      if ( $curUserEmail && (strcasecmp($curUserEmail,$this->_senderEmail) == 0) ) {
        $isSender = TRUE;
      }
      if ( $this->_dropbox->authorizedUserData('grantAdminPriv') ) {
        $isAdmin = TRUE;
      }
      if ( ($curUser == $this->_authorizedUser) || $isSender ) {
        $overrideShowRecips = TRUE;
      }
    }
    $senderIP = stripToSenderIP($this->_senderIP);
    if ( $senderIP !== '' ) {
      //  Try to get a hostname for the IP, too:
      $remoteHostName = gethostbyaddr($senderIP);
    }
    // JKF 20180926 Removing this feature (recipient could delete drop-off
    // if they were the only recipient). If, after creating the drop-off,
    // you send the link to other people, there might be multiple people
    // expecting to pick it up, but officially only 1 recipient. At which
    // point anyone can delete it, stopping the others getting it! :-(
    //$isSingleRecip = FALSE;
    //if ( count($this->_recipients) == 1 ) {
    //  $isSingleRecip = TRUE;
    //}
    $smarty->assign('isClickable', $clickable);

    // JKF 20180926 Removing the isSingleRecip test.
    //$smarty->assign('isDeleteable', ( $clickable && ( $isAdmin || $isSender || $isSingleRecip )));
    $smarty->assign('isDeleteable', ( $clickable && ( $isAdmin || $isSender )));
    $smarty->assign('isSendable', ( $clickable && ( $isAdmin || ( $isSender && $this->_dropbox->allowEmailRecipients() ) )));
    $smarty->assign('showIDPasscode', $this->_showIDPasscode);

    $smarty->assign('inPickupPHP', preg_match('/pickup\.php/', $php_self));
    $smarty->assign('claimPasscode', $this->_claimPasscode);

    $smarty->assign('senderName',  $this->_senderName);
    $smarty->assign('senderOrg',   htmlspecialchars($this->_senderOrganization));
    $smarty->assign('senderEmail', $this->_senderEmail);
    $smarty->assign('senderHost',  $remoteHostName);
    $smarty->assign('createdDate', timeForDate($this->created()));
    $smarty->assign('expiryDate', timeForDate($this->created())+3600*24*$this->_dropbox->retainDays());
    $smarty->assign('confirmDelivery', $this->_confirmDelivery?"true":"false");
    $smarty->assign('informRecipients', $this->_informRecipients?"true":"false");
    $smarty->assign('allowEmailPasscode', $this->_dropbox->allowEmailPasscode()?"true":"false");
    $smarty->assign('defaultEmailPasscode', $this->_dropbox->defaultEmailPasscode()?"true":"false");
    $smarty->assign('defaultRecipWaiver', $this->_dropbox->defaultRecipWaiver()?"true":"false");
    $smarty->assign('isEncrypted', $this->isEncrypted());
    $smarty->assign('showWaiver', $this->_isNewDropoff?false:$this->showRecipWaiver());
    // If we have just created a new drop-off, the time remaining is
    // probably a second or two short of the lifetime already.
    // Which makes for ugly results: "29 days and 23 hours" instead of
    // "30 days". So let's cheat a bit...
    // First the accurate one
    $e1 = secsToString(timeForDate($this->_created) + $this->lifeseconds() - time());
    // Then add a few seconds and see if it produces a shorter string!
    $e2 = secsToString(timeForDate($this->_created) + ($this->lifeseconds()+10) - time());
    if (strlen($e2) < strlen($e1))
      $smarty->assign('expiresin', $e2);
    else
      $smarty->assign('expiresin', $e1);

    $smarty->assign('showRecips', ( $this->_dropbox->showRecipsOnPickup() || $overrideShowRecips || ($this->_dropbox->authorizedUser() && $this->_dropbox->authorizedUserData('grantAdminPriv')) ));
    // MyZendTo: If there is only 1 recipient then that must be the sender
    //if (preg_match('/^[yYtT1]/', MYZENDTO) && count($this->_recipients)<=1) {
    //  $smarty->assign('showRecips', FALSE);
    //}
    $reciphtml = array();
    foreach($this->_recipients as $r) {
      $reciphtml[] = array(htmlentities($r[0], ENT_NOQUOTES, 'UTF-8'), htmlentities($r[1], ENT_NOQUOTES, 'UTF-8'));
    }
    $smarty->assign('recipients', $reciphtml);

    $smarty->assign('note', htmlentities($this->_note, ENT_NOQUOTES, 'UTF-8'));
    $smarty->assign('subject', htmlentities($this->_subject, ENT_NOQUOTES, 'UTF-8'));

    $dropoffFiles = $this->_dropbox->database->DBFilesForDropoff($this->_dropoffID);
    $smarty->assign('dropoffFilesCount', count($dropoffFiles));

    // Fill the outputFiles array with all the dropoffFiles, over-riding
    // one or two elements as we go so it's ready-formatted.
    $outputFiles = array();
    $i = 0;
    $totalLength = 0;
    foreach($dropoffFiles as $file) {
      $outputFiles[$i] = $file;
      $outputFiles[$i]['basename'] = htmlentities($file['basename'], ENT_NOQUOTES, 'UTF-8');
      // This one is for the link's download attribute
      $outputFiles[$i]['downloadname'] = htmlentities($file['basename'], ENT_QUOTES, 'UTF-8');
      $outputFiles[$i]['length'] = NSSFormattedMemSize($file['lengthInBytes']);
      $totalLength += $file['lengthInBytes'];
      $outputFiles[$i]['description'] = htmlentities($file['description'],ENT_NOQUOTES, 'UTF-8');
      // Try to find a matching icon from the mimeType
      // Sanitise by changing / -> - and .. -> .
      $mT = str_replace(array('/', '..'), array('-', '.'), $file['mimeType']);
      $mTiconURL = 'images/filetypes/'.strtolower("$mT.svg");
      $mTiconFile = NSSDROPBOX_BASE_DIR.'www/'.$mTiconURL;
      if ($mT !== '' && (is_file($mTiconFile) || is_link($mTiconFile)))
        $outputFiles[$i]['icon'] = $mTiconURL;
      else
        $outputFiles[$i]['icon'] = "images/filetypes/unknown.svg";
      // If we split the checksum string in half, this is where to split it
      $outputFiles[$i]['wrapat'] = floor(strlen($file['checksum'])/2);
      $i++;
    }
    $smarty->assignByRef('files', $outputFiles);
    $smarty->assign('totalBytes', $totalLength);

    $emailAddr = isset($this->_emailAddr)?$this->_emailAddr:'';
    if (!$emailAddr) {
      $emailAddr = isset($_POST['emailAddr'])?$_POST['emailAddr']:(isset($_GET['emailAddr'])?$_GET['emailAddr']:NULL);
      $emailAddr = trim($emailAddr);
      if ( strlen($emailAddr)>0 ) {
        if ( preg_match($this->_dropbox->validEmailRegexp(),
                        $emailAddr, $eAParts) )
          $emailAddr = $eAParts[1]."@".$eAParts[2];
        else
          $emailAddr = gettext('one of the recipients');
      } else {
        // No email address at all, so it wasn't invalid but blank.
        // $emailAddr = $smarty->getConfigVars('UnknownRecipient');
        $emailAddr = gettext('one of the recipients');
      }
    }

    $smarty->assign('emailAddr', $emailAddr);
    if ($this->_dropbox->hidePHP())
      $smarty->assign('downloadURL', $NSSDROPBOX_URL.'download?claimID=' . $this->_claimID . '&claimPasscode=' . $this->_claimPasscode . ($emailAddr?('&emailAddr='.urlencode($emailAddr)):''));
    else
      $smarty->assign('downloadURL', $NSSDROPBOX_URL.'download.php?claimID=' . $this->_claimID . '&claimPasscode=' . $this->_claimPasscode . ($emailAddr?('&emailAddr='.urlencode($emailAddr)):''));

    $pickups = $this->_dropbox->database->DBPickupsForDropoff($this->_dropoffID);
    $smarty->assign('pickupsCount', count($pickups));

    // Fill the outputPickups array with all the pickups, over-riding
    // one or two elements as we go so it's ready-formatted.
    $outputPickups = array();
    $i = 0;
    foreach($pickups as $pickup) {
      $outputPickups[$i] = $pickup;
      $hostname = gethostbyaddr($pickups[$i]['recipientIP']);
      if ( $hostname != $pickups[$i]['recipientIP'] ) {
        $hostname = "$hostname (".$pickups[$i]['recipientIP'].")";
      }
      $outputPickups[$i]['hostname'] = htmlentities($hostname, ENT_NOQUOTES, 'UTF-8');
      $outputPickups[$i]['pickupDate'] = timeForTimestamp($pickups[$i]['pickupTimestamp']);
      $authorizedUser = htmlentities($pickups[$i]['authorizedUser'], ENT_NOQUOTES, 'UTF-8');
      if ( ! $authorizedUser ) {
        $authorizedUser = $pickups[$i]['emailAddr'];
      }
      $outputPickups[$i]['pickedUpBy'] = $authorizedUser;
      $i++;
    }
    $smarty->assignByRef('pickups', $outputPickups);
  }

  /*!
    @function ClaimID2Dropoff

    Convert a claimID (random string) into the Dropoff record for it.

    Returns the Dropoff record on success, FALSE otherwise.
  */
  private function ClaimID2Dropoff(
    $claimID
  )
  {
    global $SYSADMIN;
    $claimID = preg_replace('/[^a-zA-Z0-9]/', '', $claimID); // Protect it!
    if ( $this->_dropbox ) {
      $qResult = $this->_dropbox->database->DBDropoffsForClaimID($claimID);
      if ( $qResult && ($iMax = count($qResult)) ) {
        //  Set the fields:
        if ( $iMax == 1 ) {
          return $qResult[0];
        } else {
          NSSError(gettext("There appears to be more than 1 drop-off with that claim ID.").' '.$SYSADMIN, gettext("Invalid Claim ID"));
        }
      }
    }
    return FALSE;
  }

  /*!
    @function initWithClaimID
    
    Completes the initialization (begun by the __construct function)
    by looking-up a dropoff by the $claimID.
    
    Returns TRUE on success, FALSE otherwise.
  */
  private function initWithClaimID(
    $claimID
  )
  {
    $dropoffDBRecord = $this->ClaimID2Dropoff($claimID);
    if (is_array($dropoffDBRecord) &&
        array_key_exists('rowID', $dropoffDBRecord)) {
      $d = $this->initWithQueryResult($dropoffDBRecord);
      // We will end up here if we are doing anything with a drop-off,
      // including re-sending it.
      // If we're doing a pick-up and we haven't yet got a decent email
      // address, pull one from the form data instead. Sanitise!
      if (! $this->_emailAddr && is_array($_POST) &&
          array_key_exists('emailAddr', $_POST)) {
        $addrattempt = trim($_POST['emailAddr']);
        if (preg_match($this->_dropbox->validEmailRegexp(), $addrattempt))
          $this->_emailAddr = $addrattempt;
      }
      // To know whether to resend with the passcode, we need to look at
      // the form data to see if they want us to (if we are allowed to!).
      // Assume we cannot send passcode by default
      $this->_resendWithPasscode = FALSE;
      if ($this->_dropbox->allowEmailPasscode()) {
        // Most people want it set, so start that way
        $this->_resendWithPasscode = TRUE;
        // Get the value of the 'resendWithPasscode' form field
        if (is_array($_POST) &&
            array_key_exists('resendWithPasscode', $_POST) &&
            $_POST['resendWithPasscode'] === "no")
          $this->_resendWithPasscode = FALSE;
        if (is_array($_GET) &&
            array_key_exists('resendWithPasscode', $_GET) &&
            $_GET['resendWithPasscode'] === "no")
          $this->_resendWithPasscode = FALSE;
      }
      return $d;
    }
    return FALSE;
  }
  
  /*!
    @function initWithQueryResult
    
    Completes the initialization (begun by the __construct function)
    by pulling instance data from a hash of results from an SQL query.
    
    Also builds an in-memory recipient list by doing a query on the
    recipient table.  The list is a 2D array, each outer element being
    a hash containing values keyed by 'recipName' and 'recipEmail'.
    
    Returns TRUE on success, FALSE otherwise.
  */
  private function initWithQueryResult(
    $qResult
  )
  {
    global $SYSADMIN;
    $trimmed = trim($qResult['claimID']);
    $this->_dropoffID           = $qResult['rowID'];
    
    $this->_claimID             = preg_replace('/[^a-zA-Z0-9]/', '', $qResult['claimID']);
    $this->_claimPasscode       = preg_replace('/[^a-zA-Z0-9]/', '', $qResult['claimPasscode']);
    
    $this->_authorizedUser      = $qResult['authorizedUser'];
    $this->_emailAddr           = @$qResult['emailAddr'];
    
    $this->_senderName          = $qResult['senderName'];
    $this->_senderOrganization  = $qResult['senderOrganization'];
    $this->_senderEmail         = $qResult['senderEmail'];
    $this->_note                = @$qResult['note'];
    $this->_subject             = @$qResult['subject'];
    // Subject can be null instead of '', but treat the same way
    if (is_null($this->_subject) || empty($this->_subject)) {
      // If the email subject is empty, it came from before subject line
      // editing was introduced.
      // So work out what the old default would have been.
      $files = $this->files();
      $realFileCount = $files['totalFiles'];
      if ($realFileCount == 1) {
        $this->_subject = sprintf(gettext('%s has dropped off a file for you'),
                                  $this->_senderName);
      } else {
        $this->_subject = sprintf(gettext('%s has dropped off files for you'),
                                  $this->_senderName);
      }
    }

    // Note this value won't be just the senderIP, it may have other metadata
    // attached on the end as a series of "|"-separated words.
    $this->_senderIP            = $qResult['senderIP'];
    $this->_confirmDelivery     = ( isset($qResult['confirmDelivery']) && preg_match('/[tT1]/',$qResult['confirmDelivery']) ) ? TRUE : FALSE;
    $this->_informRecipients    = ( isset($qResult['informRecipients']) && preg_match('/[tT1]/',$qResult['informRecipients']) ) ? TRUE : FALSE;
    $this->_informPasscode      = ( isset($qResult['informPasscode']) && preg_match('/[tT1]/',$qResult['informPasscode']) ) ? TRUE : FALSE;
    $this->_created             = dateForTimestamp($qResult['created']);
    
    $this->_recipients          = $this->_dropbox->database->DBRecipientsForDropoff($qResult['rowID']);
    $this->_bytes               = $this->_dropbox->database->DBBytesOfDropoff($qResult['rowID']);
    $this->_formattedBytes      = NSSFormattedMemSize($this->_bytes);
    
    // Verify emailAddr is legal and not malicious.
    // Must be non-blank and must match our regexp.
    // If malicious, just blank it.
    if ( isset($this->_emailAddr) && trim($this->_emailAddr) !== '' &&
         ! preg_match($this->_dropbox->validEmailRegexp(), $this->_emailAddr)) {
      $this->_emailAddr = '';
    }

    // Get the dropoff lifetime, defaulting to what prefs might say.
    $this->_lifeseconds         = @$qResult['lifeseconds'];
    if (empty($this->_lifeseconds))
        $this->_lifeseconds = $this->_dropbox->defaultLifetime()*3600*24;


    if ( ! $this->_dropbox->directoryForDropoff($trimmed, $this->_claimDir) ) {
      NSSError(gettext("The directory containing this drop-off's files has gone missing.").' '.$SYSADMIN, gettext("Drop-off Directory Not Found"));
      return FALSE;
    } else {
      return TRUE;
    }
  }
  
  // Work out how many files they submitted in the form.
  private $maxFilesKey = 0; // This holds the biggest file index we ever look at
  private function numberOfFiles() {
    $i=1;
    $files=0;
    while ($i<200) { // Okay, we can never have more than 200 files in 1 dropoff
      if ((array_key_exists("file_select_".$i, $_POST) && // Library file
           $_POST["file_select_".$i] != -1) ||
          (array_key_exists("file_".$i, $_POST) && // Sent in chunks
           !empty($_POST["file_".$i]) ) ||
          (array_key_exists("file_".$i, $_FILES) && // All sent together
           array_key_exists("tmp_name", $_FILES["file_".$i]) &&
           $_FILES["file_".$i]['tmp_name'] !== "")) {
        $files++;
        $this->maxFilesKey = $i;
      }
      $i++;
    }
    return $files;
  }



  /*!
    @function initWithFormData
    
    This monster routine examines POST-type form data coming from our dropoff
    form, validates all of it, and actually creates a new dropoff.
    
    The validation is done primarily on the email addresses that are involved,
    and all of that is documented inline below.  We also have to be sure that
    the user didn't leave any crucial fields blank.
    
    We examine the incoming files to be sure that individually they are all
    below our parent dropbox's filesize limit; in the process, we sum the
    sizes so that we can confirm that the whole dropoff is below the parent's
    dropoff size limit.
    
    Barring any problems with all of that, we get a new claimID and claim
    directory for this dropoff and move the uploaded files into it.  We add
    a record to the "dropoff" table in the database.
    
    We also have to craft and email and send it to all of the recipients.  A
    template string is created with the content and then filled-in individually
    (think form letter) for each recipient (we embed the recipient's email address
    in the URL so that it _might_ be possible to identify the picker-upper even
    when the user isn't logged in).
    
    If any errors occur, this function will return an error string.  But
    if all goes according to plan, then we return NULL!
  */
  private function initWithFormData()
  {
    global $NSSDROPBOX_URL;
    global $NSSDROPBOX_PREFS;
    global $smarty;
    global $BACKBUTTON;
    global $SYSADMIN;
    
    // Start off with the data from the form posting, overwriting it with
    // stored data as necessary.
    $senderName = paramPrepare(@$_POST['senderName']);
    $senderName = preg_replace('/[<>]/', '', $senderName);
    $senderEmail = paramPrepare(strtolower(@$_POST['senderEmail']));
    $senderOrganization = paramPrepare($_POST['senderOrganization']);
    $senderOrganization = preg_replace('/[<>]/', '', $senderOrganization);
    $note = $_POST['note'];
    $chunkName = preg_replace('/[^0-9a-zA-Z]/', '', $_POST['chunkName']);
    $chunkName = substr($chunkName, 0, 100);
    $chunkPath = ini_get('upload_tmp_dir');
    if (substr($chunkPath, -1) !== DIRECTORY_SEPARATOR)
      $chunkPath .= DIRECTORY_SEPARATOR;
    $chunkPath .= $chunkName;

    $expiry = 0;
    // Grab this early as if the req supplied a passphrase then we
    // want to over-ride any passphrase read from the form, and
    // enforce encryption.
    $encryptPassword = @$_POST['encryptPassword'];
    $wantToEncrypt = FALSE; // Need to check encryptPassword

    // Assume they are an outsider until proven otherwise.
    // Outsiders don't get to see the ClaimID or Passcode of their
    // drop-off, so they can't send it to other outsiders.
    $showIDPasscode = FALSE;

    // If they have a valid req key, then they don't need to be verified
    // or logged in.
    $reqSubject = '';
    $req = '';
    if (isset($_POST['req']) && !empty($_POST['req'])) {
      $dummy = '';
      $recipName = '';  // Never actually use this
      $recipEmail = ''; // Never actually use this
      $req = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['req']);
      $reqpassphrase = ''; // This will be obfuscated and bin2hex-ed
      if ($this->_dropbox->ReadReqData($req,
                                       $recipName, $recipEmail,
                                       $senderOrganization,
                                       $senderName, $senderEmail,
                                       $dummy, $reqSubject, $expiry,
                                       $reqpassphrase)) {
        if ($expiry < time()) {
          $this->_dropbox->DeleteReqData($req);
          return gettext("Failed to read your verification information. Your drop-off session has probably expired. Please start again.");
        }
        // It was a valid req key, so leave $req alone (and true).
        $reqSubject = trim($reqSubject);
        $this->_subject = $reqSubject;
        $senderOrganization = ''; // Want sender of drop-off, not of req!
        // If the req had a passphrase, use it to overwrite anything
        // that the new drop-off form might have sent us.
        // And enforce encryption of this drop-off.
        if (!empty($reqpassphrase)) {
          // It is stored obfuscated then in hex.
          // So convert it back to binary, then de-obfuscate it.
          $encryptPassword = decryptForDB($reqpassphrase, $this->_dropbox->secretForCookies());
          sodium_memzero($reqpassphrase);
          if ($encryptPassword === '') {
            $this->_dropbox->writeToLog("Error: Failed to decode the passphrase that was set for this drop-off when the request was created! Have you changed 'cookieSecret'? That will break existing requests.");
            $this->_dropbox->setenforceEncrypt(FALSE);
          } else {
            $this->_dropbox->setenforceEncrypt(TRUE);
            $wantToEncrypt = TRUE;
          }
        }
      } else {
        // Invalid request code, so ignore them
        $req = FALSE;
        $reqSubject = '';
      }
    }

    // It is not a request, or not a valid request
    if ($req == '') {
      // So now they must be authorized as it's not a request
      if (! $this->_dropbox->authorizedUser()) {
        $auth = $_POST['auth'];

        // Can't *only* send header here, we can't test for automation
        // as that only works once authorizedUser has been set.
        header("X-ZendTo-Response: ".
          json_encode(array("status" => "error", "error" => "login failed")));

        // JKF Get the above from the auth database
        // JKF Fail if it doesn't exist or it's a pickup auth not a dropoff
        $authdatares = $this->_dropbox->ReadAuthData($auth,
                                             $senderName,
                                             $senderEmail,
                                             $senderOrganization,
                                             $expiry);
        if (! $authdatares) {
          return gettext("Failed to read your verification information. Your drop-off session has probably expired. Please start again.");
        }
        // If the email is blank (and name has no spaces) then it's a pickup.
        // In a pickup, the name is used as the sender's IP address.
        if (!preg_match('/ /', $senderName) && $senderEmail == '') {
          return gettext("Failed to read your verification information. Your drop-off session has probably expired. Please start again.");
        }
        if ($expiry < time()) {
          $this->_dropbox->DeleteAuthData($auth);
          return gettext("Failed to read your verification information. Your drop-off session has probably expired. Please start again.");
        }
      } else {
        // Only overwrite the sender details we've been POSTed if we are
        // *not* being automated.
        // If we *are* being automated, leave them alone but pick up the
        // email subject line.
        if ($this->_dropbox->isAutomated()) {
          if (!empty(@$_POST['subject'])) {
            // Sanitise subject line against embedded newlines too
            // $this->_subject = trim(preg_replace('/[<>\n\r]/', '', $_POST['subject']);
            $this->_subject = trim(html_entity_decode($_POST['subject'], ENT_QUOTES, UTF-8));
          }
        } else {
          // Logged-in user so just read their data
          $senderName = $this->_dropbox->authorizedUserData("displayName");
          $senderOrganization = paramPrepare(@$_POST['senderOrganization']);
          $senderOrganization = preg_replace('/[<>]/', '', $senderOrganization);
          $senderEmail = trim($this->_dropbox->authorizedUserData("mail"));
          if (empty($this->_subject)  && !empty(@$_POST['subject'])) {
            // Sanitise subject line against embedded newlines too
            // $this->_subject = trim(preg_replace('/[<>\n\r]/', '', $_POST['subject']));
            $this->_subject = trim(html_entity_decode($_POST['subject'], ENT_QUOTES, UTF-8));
            // Check the length of the subject.
            $subjectlength = mb_strlen($smarty->getConfigVars('EmailSubjectTag') . $subject);
            $maxlen = $this->_dropbox->maxsubjectlength();
            if ($subjectlength>$maxlen) {
              return sprintf(gettext("Your subject line to the recipients is %1$d characters long. It must be less than %2$d."), $subjectlength, $maxlen).' '.$BACKBUTTON;
            }
          }
        }
        // Remember they are a member of our organisation,
        // needed when constructing page showing contents of drop-off.
        $showIDPasscode = TRUE;
      }
    }


    // Erase the note if it is just white space.
    $note = trim($note);
    // if (preg_match('/^\s*$/', $note)) {
    //   $note = "";
    // }
    // Check the length of the note.
    // Don't reject it any more, just trim it if it's too long.
    if (! function_exists('mb_strimwidth')) {
      return gettext('Admin: Install the PHP module/rpm "mbstring".');
    }
    $maxlen = $this->_dropbox->maxnotelength();
    $note = mb_strimwidth($note, 0, $maxlen, '...');

    $confirmDelivery = ( @$_POST['confirmDelivery'] ? TRUE : FALSE );
    $informRecipients = ( @$_POST['informRecipients'] ? TRUE : FALSE );
    $informPasscode = ( @$_POST['informPasscode'] ? TRUE : FALSE );
    $checksumFiles = ( @$_POST['checksumFiles'] ? TRUE : FALSE );
    $recipWaiver = ( @$_POST['recipWaiver'] ? TRUE : FALSE );

    // User-specified lifetime in days, else 0.
    $lifedays = floatval(@$_POST['lifedays']);
    // If lifetime has been set sensibly, just limit its range
    if ($lifedays > 0) {
      // Life time must be 1 hour .. max-dropoff-expiry-life (in days)
      $lifeseconds = max(3600, $lifedays*3600*24);
      // $lifetime now counts in seconds
      $lifeseconds = min($lifeseconds, $this->_dropbox->retainDays()*3600*24);
    } else {
      // 0 or unspecified. So use default days from prefs.
      $lifeseconds = $this->_dropbox->defaultLifetime()*3600*24;
    }
    $this->_lifeseconds = $lifeseconds;

    // Double-check here.
    // If we got the encryption passphrase from a request, but failed to
    // be able to read it, the user won't have been prompted to enter one.
    // So we have no way of finding it! So if that failed, don't encrypt
    // at all.
    // Have to @ here, as if the drop-off came from a request that enforced
    // encryption, the 'encryptFiles' checkbox will have been disabled.
    // Disabled form inputs aren't POSTed at all, they're ignored.
    $wantToEncrypt = $wantToEncrypt ||
                     ( @$_POST['encryptFiles'] ? TRUE : FALSE ) ||
                     $this->_dropbox->enforceEncrypt();
    if ( $wantToEncrypt ) {
      if ( isset($encryptPassword) && $encryptPassword !== '' ) {
        $wantToEncrypt = TRUE; // Effectively do nothing, as all is fine
      } else {
        // Want to encrypt, but can't as I've got no passphrase!!
        $this->_dropbox->writeToLog("Error: Should be encrypting files but failed to read the passphrase, so not encrypting. If this drop-off came from a request, the encoded passphrase in the request got corrupted!");
        // Make this a blocking situation. Otherwise we would store the
        // drop-off without encrypting it, which is A Bad Thing(tm).
        //$wantToEncrypt = FALSE;
        return gettext("Responding to a request for an encrypted drop-off, but failed to read the passphrase.").' '.$SYSADMIN;
      }
    }
    
    $recipients = array();
    $recipIndex = -1; // <0 => no recipients found
    $arraykeys = array_keys($_POST);
    foreach ($arraykeys as $arraykey) {
     $matches = array();
     if ( preg_match('/^recipient_(\d+)/', $arraykey, $matches) ) {
      $recipIndex = $matches[1];
      //while ( array_key_exists('recipient_'.$recipIndex,$_POST) ) {
      $recipName = paramPrepare($_POST['recipName_'.$recipIndex]);
      $recipName = mb_strimwidth($recipName, 0, 100, '...');
      $recipEmail = paramPrepare($_POST['recipEmail_'.$recipIndex]);
      if ( $recipName || $recipEmail ) {
        //  Take the email to purely lowercase for simplicity:
        $recipEmail = strtolower($recipEmail);
         
        //  Just a username?  We add an implicit "@domain.com" for these and validate them!
        $emailParts[1] = NULL;
        $emailParts[2] = NULL;
        if ( strpos($recipEmail, '@') !== FALSE ) {
          // Has an @ sign so is an email address. Must be valid!
          if ( ! preg_match($this->_dropbox->validEmailRegexp(),$recipEmail,$emailParts) ) {
            return sprintf(gettext("The recipient email address '%s' is invalid."), htmlspecialchars($recipEmail)).' '.$BACKBUTTON;
           }
        } else {
          // No @ sign so just stick default domain in right hand side
          $emailParts[1] = $recipEmail;
          $emailParts[2] = $this->_dropbox->defaultEmailDomain();
        }
        $recipEmailDomain = $emailParts[2];
        // Don't think this line is needed any more, but harmless
        $recipEmail = $emailParts[1]."@".$emailParts[2];
    
        //  Look at the recipient's email domain; un-authenticated users can only deliver
        //  to the dropbox's domain:
        // JKF Changed checkRecipientDomain to return true if it's a local user
        // New config option 'allowExternalRecipients' to create totally closed sites.
        // checkRD = TRUE only if it's a valid address and it's an internal address.
        $checkRD = $this->_dropbox->checkRecipientDomain($recipEmail);
        if ( ! $this->_dropbox->authorizedUser() && ! $checkRD ) {
                // TRANSLATORS: %1$s = OrganizationShortName
                return sprintf(gettext('You must be logged in as a %1$s user in order to drop-off a file for a non-%1$s user.'), $smarty->getConfigVars('OrganizationShortName')) .
                       '<br/>&nbsp;<br/>' .
                       sprintf(gettext("Return to the %s main menu to log in and then try again."), $smarty->getConfigVars('ServiceTitle'));
        }
        // If they are internal user and all external recipients are banned, and
        // it's an external recipient, still reject it.
        // Do this separately from above so they get a different error.
        if ( $this->_dropbox->authorizedUser() &&
             ! $this->_dropbox->allowExternalRecipients() &&
             ! $checkRD ) {
          return sprintf(gettext('You can only send to other %1$s users. You cannot drop-off a file for a non-%1$s user.'), $smarty->getConfigVars('OrganizationShortName'));
        }
        $recipients[] = array(( $recipName ? $recipName : "" ),$recipEmail);
      } else if ( $recipName && !$recipEmail ) {
        return gettext("You must specify all recipients' email addresses in the form.").' '.$BACKBUTTON;
      }
      //$recipIndex++;
     }
    }
    // No recipients found?
    if ( $recipIndex<0 ) {
      return gettext("You must specify all recipients' email addresses in the form.").' '.$BACKBUTTON;
    }

    
    //
    //  Check for an uploaded CSV/TXT file containing addresses:
    //
    if ( isset($_FILES) && isset($_FILES['recipient_csv']) && $_FILES['recipient_csv']['tmp_name'] ) {
      if ( $_FILES['recipient_csv']['error'] != UPLOAD_ERR_OK ) {
        // TRANSLATORS: %s = filename
        $error = sprintf(gettext("There was an error while uploading '%s'."), htmlspecialchars($_FILES['recipient_csv']['name']));
        switch ( $_FILES['recipient_csv']['error'] ) {
          case UPLOAD_ERR_INI_SIZE:
            $error .= gettext("The recipients file size exceeds the limit imposed by PHP on the server.").' '.$SYSADMIN;
            break;
          case UPLOAD_ERR_FORM_SIZE:
            $error .= sprintf(gettext("The recipients file size exceeds the size limit (the maximum is %s)."), NSSFormattedMemSize($this->_dropbox->maxBytesForFile()));
            break;
          case UPLOAD_ERR_PARTIAL:
            $error .= gettext("The recipients file was only partially uploaded. Your network connection may have timed out while attempting to upload.");
            break;
          case UPLOAD_ERR_NO_FILE:
            $error .= gettext("No recipient file was actually uploaded.");
            break;
          case UPLOAD_ERR_NO_TMP_DIR:
            $error .= gettext("The server was not configured with a temporary folder for uploads.").' '.$SYSADMIN;
            break;
          case UPLOAD_ERR_CANT_WRITE:
            $error .= gettext("The server's temporary folder is misconfigured.").' '.$SYSADMIN;
            break;
        }
        return $error;
      }
      
      //  Parse the CSV/TXT file:
      if ( $csv = fopen($_FILES['recipient_csv']['tmp_name'],'r') ) {
        while ( $fields = fgetcsv($csv) ) {
          if ( $fields[0] !== NULL ) {
            //  Got one; figure out which field is an email address:
            foreach ( $fields as $recipEmail ) {
              //  Take the email to purely lowercase for simplicity:
              $recipEmail = strtolower($recipEmail);
               
              // JKF Don't allow just usernames in CSV file!
              if ( ! preg_match($this->_dropbox->validEmailRegexp(),$recipEmail,$emailParts) ) {
                continue;
              }
              $recipEmailDomain = $emailParts[2];
              $recipEmail = $emailParts[1]."@".$emailParts[2];
          
              //  Look at the recipient's email domain;
              //  un-authenticated users can only deliver to the dropbox's
              //  domain:
              // New config option 'allowExternalRecipients' to create totally closed sites.
              // checkRD = TRUE only if it's a valid address and it's an internal address.
              $checkRD = $this->_dropbox->checkRecipientDomain($recipEmail);
              if ( ! $this->_dropbox->authorizedUser() && ! $checkRD ) {
                // TRANSLATORS: %1$s = OrganizationShortName
                return sprintf(gettext("You must be logged in as a %1$s user in order to drop-off a file for a non-%$1s user."), $smarty->getConfigVars('OrganizationShortName')) .
                       '<br/>&nbsp;<br/>' .
                       sprintf(gettext("Return to the %s main menu to log in and then try again."), $smarty->getConfigVars('ServiceTitle'));
              }
              // If they are internal user and all external recipients are banned, and
              // it's an external recipient, still reject it.
              // Do this separately from above so they get a different error.
              if ( $this->_dropbox->authorizedUser() &&
                   ! $this->_dropbox->allowExternalRecipients() &&
                   ! $checkRD ) {
                return sprintf(gettext('You can only send to other %1$s users. You cannot drop-off a file for a non-%1$s user.'), $smarty->getConfigVars('OrganizationShortName'));
              }              
              // $recipients[] = array(( $recipName ? $recipName : "" ),$recipEmail);
              $recipients[] = array("",$recipEmail);
            }
          }
        }
        fclose($csv);
      } else {
        return gettext("Could not read the uploaded recipients file.");
      }
    }
    
    // If it's in response to a request, and the recipient override
    // is set, then zap the first recipient and replace with ours.
    $reqRecipient = $this->_dropbox->reqRecipient();
    if ($req != '' && $reqRecipient != '') {
      $recipients[0][1] = $reqRecipient;
    }

    // Reduce the list of recipients to those with unique email addresses
    uniqueifyRecipients($recipients);

    //  Confirm that all fields are present and accounted for:
    $fileCount = $this->numberOfFiles();
    if ( $fileCount == 0 ) {
      return gettext("You must choose at least one file to drop-off.").' '.$BACKBUTTON;
    }
    
    // Look through first for file uploads which have been chunked.
    // For each one of those found, make up a fake $_FILES[] entry.
    for ($i=1; $i<=$this->maxFilesKey; $i++) {
      $key = 'file_'.$i;
      if (array_key_exists($key, $_POST) && !empty($_POST[$key])) {
        // The file has been uploaded in chunks
        $json = $_POST[$key];
        $f = json_decode($json, TRUE);
        if ($f !== NULL) {
          $bytes = $f['size'];
          $new = array();
          $new['name'] = $f['name'];
          $new['size'] = $f['size'];
          $new['type'] = $f['type'];
          $new['error'] = UPLOAD_ERR_OK;
          $new['tmp_name'] = $chunkPath . '.' . $f['tmp_name'];
          $_FILES[$key] = $new; // Fake an uploaded file entry!
        }
      }
    }



    //  Now make sure each file was uploaded successfully, isn't too large,
    //  and that the total size of the upload isn't over capacity:
    $i = 1;
    $totalBytes = 0.0;
    $totalFiles = 0;
    // while ( $i <= $fileCount ) {
    while ( $i <= $this->maxFilesKey ) {
      $key = "file_".$i;
      if ( array_key_exists('file_select_'.$i, $_POST) && $_POST['file_select_'.$i] != "-1" ) {
        $totalFiles++;
      } elseif ( $_FILES[$key]['name'] ) {
        if ( $_FILES[$key]['error'] != UPLOAD_ERR_OK ) {
          // TRANSLATORS: %s = filename
          $error = sprintf(gettext("There was an error while uploading '%s'."), htmlspecialchars($_FILES[$key]['name']));
          switch ( $_FILES[$key]['error'] ) {
            case UPLOAD_ERR_INI_SIZE:
              $error .= gettext("The file size exceeds the limit imposed by PHP on the server.").' '.$SYSADMIN;
              break;
            case UPLOAD_ERR_FORM_SIZE:
              $error .= sprintf(gettext("The file '%s' was too large. Each dropped-off file may be at most %s."), htmlspecialchars($_FILES[$key]['name']), NSSFormattedMemSize($this->_dropbox->maxBytesForFile()));
              break;
            case UPLOAD_ERR_PARTIAL:
              $error .= gettext("The file was only partially uploaded. Your network connection may have timed out while attempting to upload.");
              break;
            case UPLOAD_ERR_NO_FILE:
              $error .= gettext("No file was actually dropped-off.");
              break;
            case UPLOAD_ERR_NO_TMP_DIR:
              $error .= gettext("The server was not configured with a temporary folder for uploads.").' '.$SYSADMIN;
              break;
            case UPLOAD_ERR_CANT_WRITE:
              $error .= gettext("The server's temporary folder is misconfigured.").' '.$SYSADMIN;
              break;
          }
          return $error;
        }
        if ( ($bytes = $_FILES[$key]['size']) < 0 ) {
          //  Grrr...stupid 32-bit nonsense.  Convert to the positive
          //  value float-wise:
          $bytes = ($bytes & 0x7FFFFFFF) + 2147483648.0;
        }
        if ( $bytes > $this->_dropbox->maxBytesForFile() ) {
          return sprintf(gettext("The file '%s' was too large. Each dropped-off file may be at most %s."), htmlspecialchars($_FILES[$key]['name']), NSSFormattedMemSize($this->_dropbox->maxBytesForFile()));
        }
        if ( ($totalBytes += $bytes) > $this->_dropbox->maxBytesForDropoff() ) {
          return sprintf(gettext("The total size of the dropped-off files exceeds the maximum for a single drop-off. Altogether, a single drop-off can be at most %s."),
                      NSSFormattedMemSize($this->_dropbox->maxBytesForDropoff())
                    );
        }
        $totalFiles++;
      }
      $i++;
    }
    if ( $totalFiles == 0 ) {
      return gettext("You must choose at least one file to drop-off.").' '.$BACKBUTTON;
    }

    // Call clamdscan on all the files, fail if they are infected
    // If the name of the scanner is set to '' or 'DISABLED' then skip this.
    $clamdscancmd = $this->_dropbox->clamdscan();
    if ($clamdscancmd != 'DISABLED') {
      $ccfilecount = 1;
      $ccfilelist = '';
      $foundsometoscan = FALSE;
      while ( $ccfilecount <= $this->maxFilesKey ) {
        // For every possible file, we only add it to the list of clamd
        // targets if it's not a library file (assumed clean), but it is
        // an uploaded file, and that file-slot in the form was used.
        $filekey = "file_".$ccfilecount;
        $selectkey = "file_select_".$ccfilecount;
        if (!(array_key_exists($selectkey, $_POST) && // If not library file
              $_POST[$selectkey] != "-1") &&
            array_key_exists($filekey, $_FILES) &&   // and is uploaded file
            array_key_exists('tmp_name', $_FILES[$filekey]) &&
            $_FILES[$filekey]['tmp_name'] !== '') {  // and is not blank
          $ccfilelist .= ' ' . $_FILES[$filekey]['tmp_name'];
          $foundsometoscan = TRUE;
        }
        $ccfilecount++;
      }
      if ($foundsometoscan) { // Don't do any of this if they uploaded nothing
        exec("/bin/chmod go+r " . $ccfilelist); // Need clamd to read them!
        $clamdinfected = 0;
        $clamdoutput = array();
        $clamcmd = exec($clamdscancmd . $ccfilelist,
                        $clamdoutput, $clamdinfected);
        // Return values: 0=>OK, 1=>virus, 2=>error
        if ($clamdinfected == 1) {
          // JKF 2017-02-27 Added more logging
          $this->_dropbox->writeToLog("Warning: Virus scan of dropped-off files ".$ccfilelist." for ".$this->_dropbox->authorizedUser()." found virus ".implode(' ', $clamdoutput));
          return gettext("One or more of the files you dropped-off was infected with a virus. The drop-off has been abandoned. Please clean your files and try again.");
        }
        if ($clamdinfected == 2) {
          // JKF 2017-02-27 Added more logging
          $this->_dropbox->writeToLog("Error: Virus scan of dropped-off files ".$ccfilelist." for ".$this->_dropbox->authorizedUser()." failed with ".implode(' ', $clamdoutput));
          return gettext("The attempt to virus-scan your drop-off failed.").' '.$SYSADMIN;
        }
        // Log clean scans too
        $this->_dropbox->writeToLog("Info: Virus scan of dropped-off files ".$ccfilelist." for ".$this->_dropbox->authorizedUser()." passed successfully");
        
      }
    }

    // Call sha256sum on all the files, if they wanted checksums and if
    // the total drop-off size is below our limit (or else it takes too long!)
    // Do all the checks to confirm whether we are actually going to
    // checksum the files or not. From now on we just check $checksumFiles
    // which will be true or false.
    $checksumcmd = $this->_dropbox->checksum();
    if ( $checksumcmd === 'DISABLED' || $checksumcmd === '' ||
         $totalBytes > $this->_dropbox->maxBytesForChecksum())
      $checksumFiles = FALSE;

    // Encrypt the files if we can. If it's too big, we won't try too.
    // Prefs can also disable or enforce encryption.
    if ($this->_dropbox->enforceEncrypt()) {
      // Over-ride user's request to encrypt or not
      $wantToEncrypt = TRUE;
      $encryptFiles = TRUE;
    } else {
      $encryptFiles = ($this->_dropbox->maxBytesForEncrypt()>0 &&
                       $wantToEncrypt);
      if ($encryptFiles &&
          $totalBytes > $this->_dropbox->maxBytesForEncrypt()) {
        $encryptFiles = FALSE;
        NSSError(gettext("The drop-off was too large to encrypt, so it has been stored unencrypted. If this is not what you want, please immediately delete the drop-off from your Outbox."),gettext("Drop-off not encrypted"));
      }
    }
    $encryptIV    = '';
    $encryptIVHex = '';
    if ($encryptFiles) {
      // Okay, we are going to encrypt them.
      // So we need to remember the $encryptionPassword for now.
      // And invent some initialisation vector data ($encryptionIV).
      // And we need to add the IV on the end of the senderIP field correctly.
      $encryptIV = random_bytes(16);
      // This includes the tag so the decryption code can find it again.
      $encryptIVHex = '|ENCRYPT:'.sodium_bin2hex($encryptIV);
    }
    
    // Will the recipients be shown the waiver?
    $waiverFlag = '';
    // They either asked for it, or it's enforced (not shown but is default)
    if ( $recipWaiver ||
         ( $this->_dropbox->defaultRecipWaiver() &&
           !$this->_dropbox->showRecipWaiver() )
       ) {
      $waiverFlag = '|WAIVER';
    }
    //if ( ! $senderName ) {
    //  return gettext("You must specify your name in the form.").' '.$BACKBUTTON;
    //}
    if ( ! $senderEmail ) {
      return gettext("You must specify your own email address in the form.").' '.$BACKBUTTON;
    }
    if ( ! preg_match($this->_dropbox->validEmailRegexp(),$senderEmail,$emailParts) ) {
      return gettext("The sender email address you entered was invalid.").' '.$BACKBUTTON;
    }
    $senderEmail = $emailParts[1]."@".$emailParts[2];
    
    //  Invent a passcode and claim ID:
    $claimPasscode = NSSGenerateCode();
    $claimID = NULL; $claimDir = NULL;
    if ( ! $this->_dropbox->directoryForDropoff($claimID,$claimDir) ) {
      return gettext("A unique directory to contain your dropped-off files could not be created.").' '.$SYSADMIN;
    }

    //
    // Before starting the SQL transaction, I must checksum and encrypt
    // all the files that need it. SQLite blocks write access to the DB
    // while another write (or a transaction involving writes) is in
    // progress. If I hold a transaction open for the whole time I'm
    // encrypting and checksumming files, other uploads will fail
    // as they timeout waiting for DB access.
    //
    // However, there is quite a bit of metadata I need to gather along
    // the way, so that the checksum and encrypt operation work. So
    // let's put that in an array '$fileDetails' which is an array of
    // of hashes.
    //

    //  Process the files: Collect all the info, do the checksum & encryption
    $i = 1;
    $fileDetails = array(); // Where I store per-file variables for DB transaction
    while ( $i <= $this->maxFilesKey ) {
      $fileDetails[$i] = array();
      $key = "file_".$i;
      $selectkey = 'file_select_'.$i;
      if ( array_key_exists($selectkey, $_POST) &&
           $_POST[$selectkey] != "-1" ) {
        $fileDetails[$i]['key'] = $selectkey;
        // It's a library file.

        // Get the name of the library file they want (safely)
        // by removing all "../" elements and things like it
        $libraryfile = preg_replace('/\.\.[:\/\\\]/', '', $_POST[$selectkey]);
        $libraryfile = preg_replace('/\</', '', $libraryfile); // Protect further
        $libraryfile = paramPrepare($libraryfile);
        $fileDetails[$i]['libraryfile'] = $libraryfile;
        // Generate a random filename (collisions are very unlikely)
        $tmpname = mt_rand(10000000, 99999999);
        $fileDetails[$i]['tmpname'] = $tmpname;
        // Link in the library file
        symlink($this->_dropbox->libraryDirectory().'/'.$libraryfile,
                $claimDir.'/'.$tmpname);

        // Now strip off the possible subdirectory name as we only
        // want it in the symlink and not after that.
        $libraryfilesize = filesize($this->_dropbox->libraryDirectory().'/'.$libraryfile);
        $libraryfile = trim(preg_replace('/^.*\//', '', $libraryfile));
        $fileDetails[$i]['libraryfilesize'] = $libraryfilesize;
        $fileDetails[$i]['libraryfile'] = $libraryfile;

        // We use this a few times
        $librarydesc = paramPrepare(trim($_POST["desc_".$i]));
        $librarydesc = mb_strimwidth($librarydesc, 0, 100, '...');
        $fileDetails[$i]['librarydesc'] = $librarydesc;

        // Checksum it if small enough (awkward to check size)
        $checksum = '';
        if ($checksumFiles &&
            $libraryfilesize <= $this->_dropbox->maxBytesForChecksum()) {
          $checksum = checksumFile($checksumcmd, $claimDir.'/'.$tmpname);
        }
        $fileDetails[$i]['checksum'] = $checksum;

        // Encrypt it if they want us to.
        // It's a library file, so we can't work out the size.
        // So a potential DoS hazard here, but I cannot yet see how
        // to avoid it.
        if ($encryptFiles) {
          $fileToCrypt = $claimDir.'/'.$tmpname;
          if (!encryptAndReplace($this->_dropbox, $fileToCrypt,
                                 $encryptPassword, $encryptIV))
            return gettext("Encryption failed.").' '.$SYSADMIN;
        }

      } elseif ( $_FILES[$key]['name'] ) {
        $fileDetails[$i]['key'] = $key;

        // It's an uploaded file
        $tmpname = basename($_FILES[$key]['tmp_name']);
        $fileDetails[$i]['tmpname'] = $tmpname;

        // Get file size from local copy, not what browser told us
        $bytes = filesize($_FILES[$key]['tmp_name']);
        $fileDetails[$i]['bytes'] = $bytes;

        if ( empty($tmpname) || $bytes < 1 ) {
          // I think we've got a screwed upload.
          // A file with no temporary name and no bytes in it?
          // Really?
          // Find the free space for new uploads
          $freeSpace = disk_free_space(ini_get('upload_tmp_dir'));
          // If the space check worked & there's less than 1 upload space
          if ($freeSpace !== false && intval($freeSpace) <= $this->_dropbox->maxBytesForDropoff())
            return sprintf(gettext("It looks like your drop-off failed due to lack of free space on the server. It only has %s left."), NSSFormattedMemSize(intval($freeSpace))).' '.$SYSADMIN;
        }
        // If it's a POSTed uploaded file, let PHP move it the nice way.
        // Else if it was delivered in chunks, move it myself.
        if ( move_uploaded_file($_FILES[$key]['tmp_name'], $claimDir."/".$tmpname) ||
             ( array_key_exists($key, $_POST) &&
               !empty($_POST[$key]) &&
               rename($_FILES[$key]['tmp_name'], $claimDir."/".$tmpname))) {
          // Strip unwanted permissions - Want ug=r and nothing else
          chmod($claimDir."/".$tmpname, 0440);
        } else {
          //  Exit gracefully -- dump database changes and remove the dropoff
          //  directory:
          $this->_dropbox->writeToLog("Error: failed to store dropoff files for $claimID");
          if ( ! rmdir_r($claimDir) ) {
            $this->_dropbox->writeToLog("Error: backing out new dropoff, unable to remove $claimDir - orphaned!");
          }
          // No longer need to do this, as we haven't started the transaction yet at all.
          //if ( ! $this->_dropbox->database->DBRollbackTran() ) {
          //  $this->_dropbox->writeToLog("Error: backing out new dropoff, failed to ROLLBACK after botched dropoff $claimID, there may be orphan files");
          //}
          return sprintf(gettext("Trouble while attempting to drop '%s' into its drop-off directory."), htmlspecialchars($_FILES[$key]['name'])).' '.$SYSADMIN;
        }

        // Checksum it if small enough (awkward to check size)
        $checksum = '';
        if ($checksumFiles) {
          $checksum = checksumFile($checksumcmd, $claimDir.'/'.$tmpname);
        }
        $fileDetails[$i]['checksum'] = $checksum;

        // Encrypt it if they want us to
        if ($encryptFiles) {
          $fileToCrypt = $claimDir.'/'.$tmpname;
          if (!encryptAndReplace($this->_dropbox, $fileToCrypt,
                                 $encryptPassword, $encryptIV))
            return gettext("Encryption failed.").' '.$SYSADMIN;
        }

        // Santitise the MIME type. Set default then over-ride.
        
        if (array_key_exists('type', $_FILES[$key]) &&
            !empty($_FILES[$key]['type'])) {
          $uploadedMIME = preg_replace('/[<>]/', '', $FILES[$key]['type']);
        } else {
          $uploadedMIME = "application/octet-stream";
        }
        $fileDetails[$i]['uploadedMIME'] = $uploadedMIME;
      }

      $i++;
    }


    //  Insert into database:
    if ( $this->_dropbox->database->DBStartTran() ) {
      if ( $dropoffID = $this->_dropbox->database->DBAddDropoff($claimID,
                          $claimPasscode,
                          $this->_dropbox->authorizedUser(),
                          $senderName, $senderOrganization, $senderEmail,
                          getClientIP($NSSDROPBOX_PREFS) .
                           $encryptIVHex . $waiverFlag,
                          $confirmDelivery,
                          timestampForTime(time()),
                          $note,
                          $lifeseconds,
                          $this->_subject) ) {

        //  Add recipients:
        if ( ! $this->_dropbox->database->DBAddRecipients($recipients, $dropoffID) ) {
          $this->_dropbox->database->DBRollbackTran();
          return gettext("Could not add recipients to the database.").' '.$SYSADMIN;
        }
        
        //  Process the files: 2nd pass, just write the info collected earlier.
        $i = 1;
        $realFileCount = 0;
        $tplFiles = array(); // These are the file hashes we process in tpl.
        while ( $i <= $this->maxFilesKey ) {
          $key = "file_".$i;
          $selectkey = 'file_select_'.$i;
          if ( array_key_exists($selectkey, $_POST) &&
               $_POST[$selectkey] != "-1" ) {
            // It's a library file
            $selectkey = $fileDetails[$i]['key'];
            $libraryfile = $fileDetails[$i]['libraryfile'];
            $tmpname = $fileDetails[$i]['tmpname'];
            $libraryfilesize = $fileDetails[$i]['libraryfilesize'];
            $libraryfile = $fileDetails[$i]['libraryfile'];
            $librarydesc = $fileDetails[$i]['librarydesc'];
            $checksum = $fileDetails[$i]['checksum'];

            //  Add to database:
            if ( ! $this->_dropbox->database->DBAddFile1($dropoffID, $tmpname,
                             $libraryfile,
                             $libraryfilesize,
                             "application/octet-stream",
                             $librarydesc,
                             $checksum) ) {
              //  Exit gracefully -- dump database changes and remove the dropoff
              //  directory:
              $this->_dropbox->writeToLog("Error: failed to add dropoff library file $libraryfile to database for $claimID, backing out new dropoff");
              if ( ! rmdir_r($claimDir) ) {
                $this->_dropbox->writeToLog("Error: backing out new dropoff, unable to remove $claimDir - orphaned!");
              }
              if ( ! $this->_dropbox->database->DBRollbackTran() ) {
                $this->_dropbox->writeToLog("Error: backing out new dropoff, failed to ROLLBACK after botched dropoff $claimID, there may be orphan files");
              }
              return sprintf(gettext("Trouble while attempting to save the information for '%s'."), $libraryfile).' '.$SYSADMIN;
            }
            
            //  That's right, one more file!
            $tplFiles[$realFileCount] = array();
            $tplFiles[$realFileCount]['name'] = $libraryfile;
            $tplFiles[$realFileCount]['type'] = ($this->_dropbox->isAutomated())?'':gettext('Library');
            $tplFiles[$realFileCount]['size'] = NSSFormattedMemSize($libraryfilesize);
            $tplFiles[$realFileCount]['description'] = $librarydesc;
            if ($checksum != '')
              $tplFiles[$realFileCount]['checksum'] = $checksum;
            else
              $tplFiles[$realFileCount]['checksum'] = gettext('Not calculated');
            $realFileCount++;
            
            // Update the description in the library index
            // But not if we're automated, it may screw existing text up
            if (! $this->_dropbox->isAutomated()) {
              $this->_dropbox->database()->DBUpdateLibraryDescription($libraryfile, $librarydesc);
            }

          } elseif ( $_FILES[$key]['name'] ) {
            // It's an uploaded file
            $tmpname = $fileDetails[$i]['tmpname'];
            $bytes = $fileDetails[$i]['bytes'];
            $checksum = $fileDetails[$i]['checksum'];
            $uploadedMIME = $fileDetails[$i]['uploadedMIME'];

            //  Add to database:
            if ( ! $this->_dropbox->database->DBAddFile1($dropoffID, $tmpname,
                             paramPrepare($_FILES[$key]['name']),
                             $bytes,
                             $uploadedMIME,
                             paramPrepare($_POST["desc_".$i]),
                             $checksum) ) {
              //  Exit gracefully -- dump database changes and remove the dropoff
              //  directory:
              $this->_dropbox->writeToLog("Error: failed to add dropoff file to database for $claimID");
              if ( ! rmdir_r($claimDir) ) {
                $this->_dropbox->writeToLog("Error: backing out new dropoff, unable to remove $claimDir - orphaned!");
              }
              if ( ! $this->_dropbox->database->DBRollbackTran() ) {
                $this->_dropbox->writeToLog("Error: backing out new dropoff, failed to ROLLBACK after botched dropoff $claimID, there may be orphan files");
              }
              // TRANSLATORS: %s = filename
              return sprintf(gettext("Trouble while attempting to save the information for '%s'."), htmlspecialchars($_FILES[$key]['name'])).' '.$SYSADMIN;
            }
            
            //  That's right, one more file!
            $tplFiles[$realFileCount] = array();
            $tplFiles[$realFileCount]['name'] = paramPrepare($_FILES[$key]['name']);
            // If it's automated, all the mimeTypes will be 'application/octet-stream' which just confuses users.
            $tplFiles[$realFileCount]['type'] = ($this->_dropbox->isAutomated())?'':$uploadedMIME;
            $tplFiles[$realFileCount]['size'] = NSSFormattedMemSize($bytes);
            // Force the file description to max 100 chars
            $tplFiles[$realFileCount]['description'] =
              mb_strimwidth(paramPrepare($_POST["desc_".$i]), 0, 100, '...');
            // Used to put "Not calculated", now just leave the line out.
            $tplFiles[$realFileCount]['checksum'] = $checksum;
            //if ($checksum != '')
            //  $tplFiles[$realFileCount]['checksum'] = $checksum;
            //else
            //  $tplFiles[$realFileCount]['checksum'] = gettext('Not calculated');
            $realFileCount++;
          }
          $i++;
        }
        
        //  Once we get here, it's time to commit the stuff to the database:
        $this->_dropbox->database->DBCommitTran();

        $this->_dropoffID             = $dropoffID;
          
        //  At long last, fill-in the fields:
        $this->_claimID               = $claimID;
        $this->_claimPasscode         = $claimPasscode;
        $this->_claimDir              = $claimDir;
        
        $this->_authorizedUser        = $this->_dropbox->authorizedUser();
        
        $this->_note                  = $note;
        $this->_senderName            = $senderName;
        $this->_senderOrganization    = $senderOrganization;
        $this->_senderEmail           = $senderEmail;
        $this->_showIDPasscode        = $showIDPasscode;

        $senderIP                     = getClientIP($NSSDROPBOX_PREFS);
        $this->_senderIP              = $senderIP . $encryptIVHex;
        // Wipe the IV (text version) from memory
        if (is_string($encryptIVHex)) sodium_memzero($encryptIVHex);
        $senderHost = gethostbyaddr($senderIP);
        if ($senderHost)
          $senderHost = '(' . $senderHost . ')';

        $this->_confirmDelivery       = $confirmDelivery;
        $this->_informRecipients      = $informRecipients;
        $this->_informPasscode        = $informPasscode;
        $this->_created               = getdate();
        $this->_lifeseconds           = $lifeseconds;
        
        $this->_recipients            = $recipients;
        
        // This Drop-off request has been fulfilled, so kill the keys
        // to stop playback attacks.
        if ($this->_dropbox->deleteRequestsAfterUse()) {
          if (isset($req)) {
            $this->_dropbox->writeToLog("Info: Deleting request code $req as it has been used");
            $this->_dropbox->DeleteReqData($req);
          }
          if (isset($auth)) {
            // $this->_dropbox->writeToLog("Info: Deleting auth code $auth as it has been used");
            $this->_dropbox->DeleteAuthData($auth);
          }
        }

        // Work out the real email subject line.
        $emailSubject = $this->_subject; // Start with what they passed me.
        if (!empty($reqSubject)) {
          $emailSubject = $reqSubject; // Use the request subject if exists
        }
        // If we're automatied, override totally with what they sent us
        if ($this->_dropbox->isAutomated() && !empty($this->_subject)) {
          $emailSubject = $this->_subject;
        }
        // If it's still emapty, supply a default value
        if (empty($emailSubject)) {
          // Set the default subject if they didn't specify one
          if ($realFileCount == 1) {
            $emailSubject = sprintf(gettext('%s has dropped off a file for you'),
                              $senderName);
          } else {
            $emailSubject = sprintf(gettext('%s has dropped off files for you'),
                              $senderName);
          }
        }
        // Now add the EmailSubjectTag on the front
        // The tag always needs to include a trailing space
        $emailSubject = $smarty->getConfigVars('EmailSubjectTag') . $emailSubject;

        // How long till expiry?
        $daysLeft    = $lifeseconds/(3600*24);
        $timeLeft    = secsToString($lifeseconds);

        // Construct the email notification and deliver:
        $smarty->assign('senderName',     $senderName);
        $smarty->assign('senderOrg',      $senderOrganization);
        $smarty->assign('senderEmail',    $senderEmail);
        // Don't put in the IP address of the automation server!
        $smarty->assign('showIP',         ($this->_dropbox->emailSenderIP() &&
                                           !$this->_dropbox->isAutomated()));
        $smarty->assign('senderIP',       $senderIP);
        $smarty->assign('senderHost',     $senderHost);
        $smarty->assign('note',           trim($note));
        $smarty->assign('subject',        $emailSubject);
        $smarty->assign('now',            timestampForTime(time()));
        $smarty->assign('claimID',        $claimID);
        $smarty->assign('claimPasscode',  $claimPasscode);
        $smarty->assign('informPasscode', $informPasscode);
        $smarty->assign('fileCount',      $realFileCount);
        $smarty->assign('retainDays',     $daysLeft);
        $smarty->assign('timeLeft',       $timeLeft);
        $smarty->assign('showIDPasscode', $showIDPasscode);
        $smarty->assign('isEncrypted',    $this->isEncrypted());
        $smarty->assign('passFromReq',    ($req != '' && $this->isEncrypted()));
        $smarty->assignByRef('files',     $tplFiles);

        // advertisedServerRoot overrides serverRoot if it's defined
        $urlroot = $this->_dropbox->advertisedServerRoot();
        if ($urlroot) {
          // They *did* end it with a / didn't they??
          if (substr($urlroot, -1) !== '/') $urlroot .= '/';
          $smarty->assign('zendToURL', $urlroot);
          $smarty->assign('linkURL',   $urlroot);
        } else {
          $smarty->assign('zendToURL', $NSSDROPBOX_URL);
          $smarty->assign('linkURL',   $NSSDROPBOX_URL);
        }

        $emailTXTTemplate = '';
        $emailHTMLTemplate = '';
        try {
          $emailTXTTemplate = $smarty->fetch('dropoff_email.tpl');
        }
        catch (SmartyException $e) {
          $this->_dropbox->writeToLog("Error: Could not create dropoff email text: ".$e->getMessage());
          $emailTXTTemplate = $e->getMessage();
        }
        if ($smarty->templateExists('dropoff_email_html.tpl')) {
          try {
            $emailHTMLTemplate = $smarty->fetch('dropoff_email_html.tpl');
          }
          catch (SmartyException $e) {
            $this->_dropbox->writeToLog("Error: Could not create dropoff email HTML: ".$e->getMessage());
            $emailHTMLTemplate = $e->getMessage();
          }
        }

        // We've now created the email message texts, so don't need this
        // "email-specific" root URL any more.
        // Just to play safe, in case we end up using this in the
        // web page we display after sending all the emails,
        // let's put it back how it was.
        $smarty->assign('zendToURL', $NSSDROPBOX_URL);

        // Update the address book entries for this user
        $this->_dropbox->updateAddressbook($recipients);

        // Inform all the recipients by email if they want me to
        if ($informRecipients) {
          // Do we want to Bcc the sender as well?
          // It also must be EITHER an internal user, OR they want
          // to Bcc external senders as well.
          $emailBcc = '';
          if ($this->_dropbox->bccSender() &&
              ($this->_dropbox->authorizedUser() ||
               $this->_dropbox->bccExternalSender() )) {
            $emailBcc = $senderEmail;
            // // and don't forget to encode it if there are intl chars in it
            // if (preg_match('/[^\x00-\x7f]/', $senderEmail)) {
            //   $emailBcc = "Bcc: =?UTF-8?B?".base64_encode(html_entity_decode($senderEmail))."?=".PHP_EOL;
            // } else {
            //   $emailBcc = "Bcc: $senderEmail".PHP_EOL;
            // }
          }
          // Make the mail come from the sender, not ZendTo
          foreach ( $recipients as $recipient ) {
              $emailTXTContent = preg_replace('/__EMAILADDR__/', urlencode($recipient[1]), $emailTXTTemplate);
              $emailHTMLContent = preg_replace('/__EMAILADDR__/', urlencode($recipient[1]), $emailHTMLTemplate);
              $success = $this->_dropbox->deliverEmail(
                  array($recipient[1], $emailBcc),
                  $senderEmail,
                  $senderName,
                  $emailSubject,
                  $emailTXTContent,
                  $emailHTMLContent
               );
              $emailBcc = ''; // Only Bcc the sender on the first email out
              if ( ! $success ) {
                // Wipe the IV from memory first
                if (is_string($encryptIVHex)) sodium_memzero($encryptIVHex);
                if (is_string($encryptIV))    sodium_memzero($encryptIV);
                $this->_dropbox->writeToLog(sprintf("Error: failed to deliver notification email to %s for claimID $claimID",$recipient[1]));
                return sprintf(gettext('Failed to send email to the recipient %s. But your drop-off has been stored with claimID %s'), $recipient[1], $claimID);
              } else {
                $this->_dropbox->writeToLog(sprintf("Info: successfully delivered notification email to %s for claimID $claimID",$recipient[1]));
              }
          }
        }
        
        //  Log our success:
        $this->_dropbox->writeToLog(sprintf("Info: new %s dropoff $claimID of %s%s created for %s user $senderName <$senderEmail>",
               ( $encryptFiles ? "encrypted" : "unencrypted" ),
               ( $realFileCount == 1 ? "1 file" : "$realFileCount files" ),
               ( strlen($waiverFlag) > 0 ? " with waiver" : "" ),
               ( $showIDPasscode ? "internal" : "external" )));
      } else {
        // Wipe the IV from memory first
        if (is_string($encryptIVHex)) sodium_memzero($encryptIVHex);
        if (is_string($encryptIV))    sodium_memzero($encryptIV);

        return gettext("Unable to add a drop-off record to the database.").' '.$SYSADMIN;
      }
    } else {
      // Wipe the IV from memory first
      if (is_string($encryptIVHex)) sodium_memzero($encryptIVHex);
      if (is_string($encryptIV))    sodium_memzero($encryptIV);

      return gettext("Unable to begin database transaction.").' '.$SYSADMIN;
    }
    return NULL;
  }

}

?>
