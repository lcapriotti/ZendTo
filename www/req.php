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

//
// Include the dropbox preferences -- we need this to have the
// dropbox filepaths setup for us, beyond simply needing our
// configuration!
//
require "../config/preferences.php";
require "RCautoload.php";
require_once(NSSDROPBOX_LIB_DIR."Smartyconf.php");
require_once(NSSDROPBOX_LIB_DIR."NSSDropoff.php");
require_once(NSSDROPBOX_LIB_DIR."Req.php");

// This gets called with nothing, in which case we present the form,
// else it gets called with GET['req'], in which case we read the DB
// info and present the dropoff form,
// else it gets called with POST['Action']==send, in which case we send
// the email.

if ( $theDropbox = new NSSDropbox($NSSDROPBOX_PREFS) ) {
  
  $srcname = '';
  $srcemail = '';
  $srcorg = '';
  $destname = '';
  $destemail = '';
  $note = '';
  $subject = '';
  $expiry = 0;
  $start = time();
  $passphrase = '';

  if (isset($_GET['req'])) {
    // They got this link in an email, so...
    // Read the DB info and present the dropoff form
    $authkey = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['req']);
    $authkey = strtolower(substr($authkey, 0, 12)); // Get 1st 3 words

    // Present the new_dropoff form
    $theDropbox->SetupPage();

    if ( ! $theDropbox->ReadReqData($authkey, $srcname, $srcemail, $srcorg, $destname, $destemail, $note, $subject, $expiry, $start, $passphrase) ) {
      // Error!
      NSSError(gettext("Your Request Code could not be found or has already been used.").' '.gettext("You can still send files straight from the main menu, or ask for a new Request Code."), gettext("Request Code Used"));
      $smarty->display('error.tpl');
      exit;
    }

    if ($expiry < time()) {
      NSSError(gettext("Please ask for a new Request."), gettext("Your Request Code has expired."));
      $smarty->display('error.tpl');
      exit;
    }

    if ($start > time()) {
      NSSError(sprintf(gettext('Please wait for %1$s, until %2$s.'), secsToString($start-time()), timestampForTime($start)), gettext("Your Request Code cannot be used yet."));
      $smarty->display('error.tpl');
      exit;
    }

    // Escape these here, not in the template
    // $smarty->assign('senderName', $destname);
    // $smarty->assign('senderEmail', strtolower($destemail));
    $smarty->assign('senderName', htmlentities($destname, ENT_NOQUOTES, 'UTF-8'));
    $smarty->assign('senderEmail', strtolower($destemail));
    // Correctly escaping this results in over-escaping later, so just
    // do a brutal job to break actual attacks.
    $smarty->assign('recipName_1', preg_replace('/[<>\']/', '', $srcname));
    $smarty->assign('recipEmail_1', strtolower($srcemail));
    $smarty->assign('subject', htmlentities($subject, ENT_QUOTES, 'UTF-8'));
    $smarty->assign('note', htmlentities($note, ENT_NOQUOTES, 'UTF-8'));
    $smarty->assign('maxBytesForFileInt', $theDropbox->maxBytesForFile());
    $smarty->assign('maxBytesForDropoffInt', $theDropbox->maxBytesForDropoff());
    $smarty->assign('recipEmailNum', 1);
    $smarty->assign('reqKey', $authkey);
    $smarty->assign('addressbook', $theDropbox->getAddressbook());
    $smarty->assign('maxNoteLength', $theDropbox->maxnotelength());
    // Generate unique ID required for progress bars status
    $smarty->assign('progress_id', uniqid(""));
    $smarty->assign('allowEmailRecipients', $theDropbox->allowEmailRecipients()?"true":"false");
    $smarty->assign('defaultEmailRecipients', $theDropbox->defaultEmailRecipients()?"true":"false");
    $smarty->assign('allowEmailPasscode', $theDropbox->allowEmailPasscode()?"true":"false");
    $smarty->assign('defaultEmailPasscode', $theDropbox->defaultEmailPasscode()?"true":"false");
    $smarty->assign('defaultConfirmDelivery', $theDropbox->defaultConfirmDelivery()?"true":"false");
    $smarty->assign('maxBytesForChecksum', $theDropbox->maxBytesForChecksum());
    $smarty->assign('maxBytesForEncrypt', $theDropbox->maxBytesForEncrypt());
    $smarty->assign('enforceEncrypt', $theDropbox->enforceEncrypt()?"true":"false");
    $smarty->assign('minPassphraseLength', $theDropbox->minPassphraseLength());
    $smarty->assign('showEncryptCheckbox', $theDropbox->showEncryption()?"true":"false");
    $smarty->assign('showChecksumCheckbox', $theDropbox->showChecksum()?"true":"false");
    $smarty->assign('showConfirmDeliveryCheckbox', $theDropbox->showConfirmDelivery()?"true":"false");
    $smarty->assign('showEmailRecipientsCheckbox', $theDropbox->showEmailRecipients()?"true":"false");
    $smarty->assign('showPasscodeCheckbox', $theDropbox->showPasscode()?"true":"false");
    $smarty->assign('showWaiverCheckbox', $theDropbox->showRecipWaiver()?"true":"false");
    $smarty->assign('defaultRecipWaiver', $theDropbox->defaultRecipWaiver()?"true":"false");
    $smarty->assign('chunkName', NSSGenerateCode(32));
    $smarty->assign('uploadChunkSize', $theDropbox->uploadChunkSize());
    $smarty->assign('lifedays', $theDropbox->defaultLifetime());
    $smarty->assign('defaultNumberOfDaysToRetain', $theDropbox->defaultNumberOfDaysToRetain());

    // If the request contained a passphrase, enforce encryption regardless,
    // and stop the passphrase dialog from showing at all.
    if (isset($passphrase) && $passphrase !== '') {
      // Enforce encryption, but make it visually obvious to the user
      $smarty->assign('enforceEncrypt', "true");
      $smarty->assign('allowPassphraseDialog', FALSE);
      $smarty->assign('showEncryptCheckbox', "true");
    } else {
      $smarty->assign('allowPassphraseDialog', TRUE);
    }

    // And setup the library of files appropriately
    if ($theDropbox->authorizedUser() && $theDropbox->usingLibrary()) {
      $library = $theDropbox->getLibraryDescs();
      $smarty->assign('library', $library);
      $smarty->assign('usingLibrary', ($library==='[]')?FALSE:TRUE);
    } else {
      $smarty->assign('usingLibrary', FALSE);
      $smarty->assign('library', '[]');
      $smarty->assign('addressbook', '[]');
    }
    $smarty->display('new_dropoff.tpl');
    exit;
  }

  // They are either trying to submit or display the "New Request" form,
  // so they must be logged in.
  if ( ! $theDropbox->authorizedUser() ) {
    // Can't *only* send header here, we can't test for automation
    // as that only works once authorizedUser has been set.
    header("X-ZendTo-Response: ".
           json_encode(array("status" => "error", "error" => "login failed")));
    $theDropbox->SetupPage();
    NSSError(gettext("This feature is only available to users who have logged in."), gettext("Access Denied"));
    $smarty->display('error.tpl');
    exit;
  }

  if (isset($_POST['Action']) && $_POST['Action'] === 'send') {
    // Don't always actually want to send the emails.
    $sendEmails = @$_POST['sendEmail'] ? TRUE : FALSE; // This is a checkbox

    // Work out the entire URL except the string of words/numbers on the end
    //
    // advertisedServerRoot overrides serverRoot if it's defined
    $urlroot = $theDropbox->advertisedServerRoot();
    if ($urlroot) {
      // They *did* end it with a / didn't they??
      if (substr($urlroot, -1) !== '/') $urlroot .= '/';
      if ($theDropbox->hidePHP())
        $urlroot = $urlroot . 'req?req=';
      else
        $urlroot = $urlroot . 'req.php?req=';
    } else {
      if ($theDropbox->hidePHP())
        $urlroot = $NSSDROPBOX_URL.'req?req=';
      else
        $urlroot = $NSSDROPBOX_URL.'req.php?req=';
    }

    // Set up the output page
    $theDropbox->SetupPage();

    // Get the start and expiry dates from the form, and sanitise them
    $start = @$_POST['startTime'];
    $expiry = @$_POST['expiryTime'];
    // Default start time is now.
    if (empty($start) || $start<=0) {
      // Were neither start nor end set?
      if (empty($expiry) || $expiry<=0)
        // Neither set, so default to old behaviour (now+requestTTL)
        $expiry = time() + $theDropbox->requestTTL();
      $start = time();
    }
    // Default end time is start + 1 week (i.e. requestTTL from prefs.php)
    if (empty($expiry) || $expiry==0 || $expiry<=$start)
      $expiry = $start + $theDropbox->requestTTL();
    // Has the expiry time already passed? If so, error
    if ($expiry <= time()) {
      if ($theDropbox->isAutomated()) {
        header("X-ZendTo-Response: " .
               json_encode(array("status" => "error", "error" => "end time has already passed")));
      } else {
        NSSError(gettext("The end time you set has already passed."), gettext("Request Error"));
        $smarty->display('error.tpl');
      }
      exit;

    }

    // Read the contents of the form, and send the email of it all
    // Loop through all the email addresses we were given, creating a new
    // Req object for each one. Then piece together the bits of the output
    // we need to make the resulting web page look pretty.
    $emailAddrs = preg_split('/[;, ]+/', strtolower($_POST['recipEmail']), NULL, PREG_SPLIT_NO_EMPTY);
    // Get the recipient name if specified, else default to '' (not null!)
    $toName = isset($_POST['recipName'])?$_POST['recipName']:'';
    $toName = preg_replace('/[<>]/', '', $toName);
    $authList = array();
    $wordList = array();
    $urlList  = array();
    $emailList = array(); // This is the output list, separate for safety

    // Need to know if any of them are encrypted (implies all)
    $anyEncrypted = FALSE;
    $bookentry = array();
    foreach ($emailAddrs as $re) {
      $req = new Req($theDropbox, $re);
      if ($req->formInitError() != "") {
        if ($theDropbox->isAutomated()) {
          header("X-ZendTo-Response: " .
                 json_encode(array("status" => "error", "error" => "request error: ".$req->formInitError())));
        } else {
          NSSError($req->formInitError(),gettext("Request Error"));
          $smarty->display('error.tpl');
        }
        exit;
      }
      if ( $sendEmails ) {
        if (! $req->sendReqEmail()) {
          if ($theDropbox->isAutomated()) {
            header("X-ZendTo-Response: " .
                   json_encode(array("status" => "error", "error" => "email error")));
          } else {
            NSSError(gettext("Sending the request email failed."),gettext("Email Error"));
            $smarty->display('error.tpl');
          }
          exit;
        }
      }
      $authList[]  = $req->auth(); // This is the words without spaces
      $wordList[]  = $req->words();
      $urlList[]   = $urlroot . $req->auth();
      $emailList[] = $req->recipEmail();
      $anyEncrypted = $anyEncrypted || $req->encrypted();

      // And update their address book
      $bookentry[0][0] = $toName;
      $bookentry[0][1] = $req->recipEmail();
      $theDropbox->updateAddressbook($bookentry);

    }


    // Assemble and send the API header output
    // Sends back a hash of email addresses and their corresponding codes
    if ($theDropbox->isAutomated()) {
      // Assemble the data structure.
      // We have the email recipient addresses in $emailList and
      // and the word/number codes in $wordList.
      // We also need to create the unique URL for each recipient.
      $requests = array();
      for ($r=0; $r<count($emailList); $r++) {
        $requests[] = array("email" => $emailList[$r],
                            "code"  => $wordList[$r],
                            "url"   => $urlList[$r]);
      }

      header("X-ZendTo-Response: " .
             json_encode(array("status" => "OK",
                   "starts" => $start,
                   "expires" => $expiry,
                   "startsstring" => timestampForTime($start),
                   "expiresstring" => timestampForTime($expiry),
                   // "lifetimestring" => secsToString($theDropbox->requestTTL()),
                   // "lifetimesecs" => $theDropbox->requestTTL(),
                   "name" => $toName,
                   "requests" => $requests,
                   "sentemails" => $sendEmails,
                   "encrypted" => $anyEncrypted)));
    } else {
      // advertisedServerRoot overrides serverRoot if it's defined
      // We need to display this here, regardless that it's not an
      // email message, as these instructions might be read out to
      // a customer while they're waiting for the request to arrive.
      $urlroot = $theDropbox->advertisedServerRoot();
      if ($urlroot) {
        // They *did* end it with a / didn't they??
        if (substr($urlroot, -1) !== '/') $urlroot .= '/';
        $smarty->assign('advertisedRootURL', $urlroot);
      } else {
        $smarty->assign('advertisedRootURL', $NSSDROPBOX_URL);
      }

      $smarty->assign('toName', $toName);
      $smarty->assign('toEmail', implode(', ', $emailList));
      $smarty->assign('reqKey', implode(', ', $wordList));
      $smarty->assign('reqURL', implode(', ', $urlList));
      $smarty->assign('encrypted', $anyEncrypted);
      $smarty->assign('sentEmails', $sendEmails);
      $smarty->assign('startTime', timestampForTime($start));
      $smarty->assign('expiryTime', timestampForTime($expiry));
      $smarty->display('request_sent.tpl');
    }
    exit;
  }

  // It got presented with nothing except a user who should be logged in,
  // so present the form.
  $senderName  = $theDropbox->authorizedUserData("displayName");
  $senderEmail = $theDropbox->authorizedUserData("mail");
  $senderOrg   = $theDropbox->authorizedUserData("organization");

  $theDropbox->SetupPage('req.recipName');

  $smarty->assign('senderName', htmlentities($senderName, ENT_NOQUOTES, 'UTF-8'));
  $smarty->assign('senderEmail', $senderEmail);
  $smarty->assign('senderOrg', htmlspecialchars($senderOrg, ENT_NOQUOTES, 'UTF-8'));
  $smarty->assign('senderOrgEditable', $theDropbox->requestOrgEditable());
  $smarty->assign('maxNoteLength', $theDropbox->maxnotelength());
  $smarty->assign('minPassphraseLength', $theDropbox->minPassphraseLength());
  $smarty->assign('addressbook', $theDropbox->getAddressBook());
  $smarty->assign('defaultEncryptRequests', $theDropbox->defaultEncryptRequests());
  $smarty->assign('requestTTLms', $theDropbox->requestTTL() * 1000); // millisecs

  $smarty->display('request.tpl');
}

?>
