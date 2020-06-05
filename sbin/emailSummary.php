#!/usr/bin/env php
<?PHP


// ZendTo
// Copyright (C) 2006 Jeffrey Frey, frey at udel dot edu
// Copyright (C) 2020 Julian Field, Jules at Zend dot To

// Based on the original PERL dropbox written by Doke Scott.
// Developed by Julian Field.

// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.

// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.

/*
   This script uses the preferences.php settings
   'nightlySummaryEmailAddresses' and
   'nightlySummaryContains'
   The first contains an array (empty if feature is disabled) of email
   addresses where the nightly summary should be sent.
   The second has one of these 3 values: 'internal', 'external' or 'both'.

   The script sends out an email listing all the new drop-offs that have
   been created in the last 24 hours and are still being stored.
   Any drop-offs that have been created and then deleted within the last
   24 hours are already gone and so cannot be included in the list.
   You may need to monitor what files your employees ("internal users")
   send and to where. In this case, set
   'nightlySummaryContains' => 'internal',
   The corresponding meanings of 'external' and 'both' should be obvious.

   It is run automatically every night via cron.

   Please only use this feature if your users know you are doing it, and
   are happy for you to do it, and your management also agree.
   Many countries have employee privacy laws that may ban this type of
   workplace monitoring.
*/

$ztprefs = getenv('ZENDTOPREFS');
if (@$ztprefs) {
  array_splice($argv, 1, 0, $ztprefs);
}

if ( count($argv) < 2 ) {
  printf("
  usage:
  
   %s <ZendTo preferences.php file>
  
   The ZendTo preferences.php file path should be canonical, not relative.
   Alternatively, do
     export ZENDTOPREFS=<full file path of preferences.php>
     %s

   The summary report is emailed to the addresses set in the preferences.php
   setting 'nightlySummaryEmailAddresses'.

",$argv[0],$argv[0]);
  return 0;
}

if ( ! preg_match('/^\/.+/',$argv[1]) ) {
  echo "ERROR:  You must provide a canonical path to the preferences.php file.\n";
  return 1;
}

include $argv[1];
include_once(NSSDROPBOX_LIB_DIR."Smartyconf.php");
include_once(NSSDROPBOX_LIB_DIR."NSSDropoff.php");

// Get into the right directory so relative paths work
chdir(__DIR__);

if ( $theDropbox = new NSSDropbox($NSSDROPBOX_PREFS) ) {
  
  // Do we want to do this at all?
  $sendTo = (array)$theDropbox->nightlySummaryEmailAddresses();
  if (empty($sendTo))
    exit;

  // Do we want to log drop-offs from internal users, external, or both?
  $logWho = $theDropbox->nightlySummaryContains();
  if (empty($logWho))
    exit; // Neither
  $logInternal = preg_match('/internal|both/i', $logWho);
  $logExternal = preg_match('/external|both/i', $logWho);
  if (!$logInternal && !$logExternal)
    exit; // Neither
  // Work out the string to sat at the top of the email.
  if ($logInternal && $logExternal)
    $logWhoText = 'both internal and external';
  elseif ($logInternal)
    $logWhoText = 'internal';
  elseif ($logExternal)
    $logWhoText = 'external';

  //
  // Get all drop-offs for the past 24 hours:
  //
  $newDropoffs = NSSDropoff::dropoffsCreatedToday($theDropbox);
  $totalInternalDropoffs = 0;
  $totalExternalDropoffs = 0;
  $totalFiles = 0;
  $totalBytes = 0.0;

  // Force it to be an empty array rather than FALSE or null.
  if (empty($newDropoffs)) $newDropoffs = array();

  $outputDropoffs = array();
  // Build each dropoff
  foreach ($newDropoffs as $d) {
    // Do we want to log this drop-off
    $isInternal = $theDropbox->checkRecipientDomain($d->senderEmail());
    // Count them whether we are logging them or not.
    if ($isInternal)
      $totalInternalDropoffs++;
    else
      $totalExternalDropoffs++;

    // Skip it if it's internal & we're not logging that.
    // Skip it if it's external (ie not internal) & we're not logging that.
    if (($isInternal && !$logInternal) ||
        (!$isInternal && !$logExternal))
      continue;

    // All the core drop-off properties
    $o = array();
    $o['claimID']        = $d->claimID();
    $o['senderName']     = $d->senderName();
    $o['senderOrg']      = $d->senderOrganization();
    $o['senderEmail']    = $d->senderEmail();
    $o['note']           = $d->note();
    $o['senderIP']       = explode('|',$d->senderIP())[0];
    $o['createdDate']    = timestampForDate($d->created());
    $o['formattedBytes'] = $d->formattedBytes();
    $o['isEncrypted']    = $d->isEncrypted();
    $o['numPickups']     = $d->numPickups();
    $o['bytes']          = $d->bytes();

    // Its recipients
    $o['recipients'] = array();
    foreach ($d->recipients() as $recip) {
      $r = array();
      $r['name']  = htmlentities($recip[0], ENT_NOQUOTES, 'UTF-8');
      $r['email'] = htmlentities($recip[1], ENT_NOQUOTES, 'UTF-8');
      $o['recipients'][] = $r;
    }

    // Its files
    // $files = DBFilesForDropoff($d->dropoffID());
    // DBFilesByDropoffID
    $o['files'] = array();
    foreach ($d->files() as $file) {
      // Skip elements of $fileList which aren't actually files
      if (! is_array($file))
        continue;
      $f = array();
      $f['name']     = $file['basename'];
      $f['size']     = $file['lengthInBytes'];
      $f['mimetype'] = $file['mimeType'];
      $f['desc']     = $file['description'];
      $f['checksum'] = $file['checksum'];
      $o['files'][] = $f;
      $totalFiles++;
    }

    // Add it to the list
    $outputDropoffs[] = $o;

    $totalBytes += $o['bytes'];
  }

  $totalDropoffs = 0;
  $totalDropoffs += $logInternal?$totalInternalDropoffs:0;
  $totalDropoffs += $logExternal?$totalExternalDropoffs:0;
  $grandTotal = $totalInternalDropoffs + $totalExternalDropoffs;

  $smarty->assign('zendToURL',     $NSSDROPBOX_URL);
  $smarty->assign('totalFiles',    $totalFiles);
  $smarty->assign('totalBytes',    NSSFormattedMemSize($totalBytes));
  $smarty->assign('totalDropoffs', $totalDropoffs);
  $smarty->assign('totalInternalDropoffs', $totalInternalDropoffs);
  $smarty->assign('totalExternalDropoffs', $totalExternalDropoffs);
  $smarty->assign('grandTotal',    $grandTotal);
  $smarty->assign('logWho',        $logWhoText);
  $smarty->assign('logInternal',   $logInternal);
  $smarty->assign('logExternal',   $logExternal);
  $smarty->assignByRef('dropoffs', $outputDropoffs);

  $emailTXT = '';
  $emailHTML = '';
  // Generate the plain-text email message
  try {
    $emailTXT = $smarty->fetch('summary_email.tpl');
  } catch (SmartyException $e) {
    $theDropbox->writeToLog("Error: Could not create nightly summary email text: ".$e->getMessage());
    $emailTXT = $e->getMessage();
  }
  // Now generate the HTML email message
  try {
    $emailHTML = $smarty->fetch('summary_email_html.tpl');
  } catch (SmartyException $e) {
    $theDropbox->writeToLog("Error: Could not create nightly summary email HTML: ".$e->getMessage());
    $emailHTML = $e->getMessage();
  }

  // print $emailTXT;
  // print $emailHTML;
  // $success = true;

  $success = $theDropbox->deliverEmail(
      $sendTo,                                                     // to
      $smarty->getConfigVars('EmailSenderAddress'),                // from address
      $smarty->getConfigVars('ServiceTitle'),                      // from name
      $smarty->getConfigVars('ServiceTitle') . ' 24 hour drop-off summary', // subject
      $emailTXT,
      $emailHTML
  );

  if ($success) {
    $theDropbox->writeToLog("Info: nightly summary emailed to " .
      implode(', ', $sendTo));
  } else {
    $theDropbox->writeToLog("Error: failed to email nightly summary to " .
      implode(', ', $sendTo));
  }

  
}

?>
