<?PHP
//
// ZendTo
// Copyright (C) 2006 Jeffrey Frey, frey at udel dot edu
// Copyright (C) 2010 Julian Field, Jules at ZendTo dot com 
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
require_once(NSSDROPBOX_LIB_DIR."Smartyconf.php");
require_once(NSSDROPBOX_LIB_DIR."NSSDropoff.php");

//
// download.php
//
// Handles download of a file associated with a drop-off.  Posted
// form data is necessary, containing a claimID and a file
// identifier (fid).  The "fid" is used instead of an actual filename
// for the sake of simplicity.
//
// The necessary authentication is all handled by:
//
//   (1) When the dropbox instance is created, the page's cookie is
//       consulted and authentication may be pulled from that.
//   (2) When the pickup instance is created, the authenticated
//       username itself may imply authorization; otherwise, posted
//       form data (claimID and claimPasscode) will provide the
//       authorization.
//
// Also, once the pickup instance has been created, check for an AuthData
// record that matches the posted form data and IP it's being posted from.
// Unless they are an authenticated user, in which case don't check AuthData.
//

if ( $theDropbox = new NSSDropbox($NSSDROPBOX_PREFS) ) {
  $theDropbox->SetupPage();
  $thePickup = new NSSDropoff($theDropbox);
  
  // If not an authenticated user, go and get their AuthData record from
  // the posted hash. Even if they are present, check the name matches
  // their IP address.
  // If anything fails, use NSSError to post an error message saying they
  // have failed checks and should click again on the link they were sent.
  if ($theDropbox->humanDownloads() &&
      ! $theDropbox->authorizedUser() &&
      $theDropbox->captcha() !== 'disabled') {
    $authIP = '';
    $authEmail = '';
    $authOrganization = '';
    $authExpiry = 0;
    $auth = isset($_POST['auth'])?$_POST['auth']:(isset($_GET['auth'])?$_GET['auth']:NULL);
    #$auth = $_POST['auth']?$_POST['auth']:$_GET['auth'];
    // Only allow letters and numbers in $authkey.
    // This is done again in ReadAuthData() but we need the value
    // further down too.
    $auth = preg_replace('/[^a-zA-Z0-9]/', '', $auth);
    $result = $theDropbox->ReadAuthData($auth, $authIP,
                                        $authEmail, $authOrganization,
                                        $authExpiry);
    if (! $result) {
      // Can't *only* send header here, we can't test for automation
      // as that only works once authorizedUser has been set.
      header("X-ZendTo-Response: ".
           json_encode(array("status" => "error", "error" => "login failed")));
      $theDropbox->SetupPage();
      NSSError(gettext("Please click again on the link you were sent to pick-up your files."), gettext("Authentication Failure"));
      $smarty->display('no_download.tpl');
      exit;
    }
    if ($authExpiry < time()) {
      // Can't *only* send header here, we can't test for automation
      // as that only works once authorizedUser has been set.
      header("X-ZendTo-Response: ".
           json_encode(array("status" => "error", "error" => "login failed")));
      $theDropbox->SetupPage();
      NSSError(gettext("Your session has expired. Please start again."), gettext("Session Expired"));
      $smarty->display('no_download.tpl');
      exit;
    }
    // Have finally fixed the schema using the overnight cleanup.php job.
    // if (strncasecmp($authIP, getClientIP(), 32) != 0) {
    if ($authIP != getClientIP($NSSDROPBOX_PREFS)) {
      // Can't *only* send header here, we can't test for automation
      // as that only works once authorizedUser has been set.
      header("X-ZendTo-Response: ".
           json_encode(array("status" => "error", "error" => "login failed")));
      $theDropbox->SetupPage();
      NSSError(gettext("Your computer address appears to have changed. Click again on the link you were sent to pick-up your files."), gettext("Session Error"));
      $smarty->display('no_download.tpl');
      exit;
    }
    // Everything succeeded, so let them through.
  }
 
  
  if ($theDropbox->isAutomated()) {
    // Assume everything went well unless it didn't
    // If it fails early in downloadFile(), this header
    // will get replaced by the error one.
    header("X-ZendTo-Response: ". json_encode(array("status" => "OK")));
  }
  $downloaded = FALSE;
  if ( $thePickup->dropoffID() > 0 ) {
    ($fid = @$_POST['fid']) || ($fid = @$_GET['fid']);
    if ($fid)
      $downloaded = $thePickup->downloadFile($fid);
  }
  $smarty->assign('wasDownloaded', $downloaded?TRUE:FALSE);
  if ( ! $downloaded ) {
    // Download failed.
    // If we are being automated, it shouldn't get this far.
    if (!isset($auth))
      $auth = '';
    $smarty->assign('auth', $auth); // And save their auth key!
    $theDropbox->SetupPage(NULL, array('auth' => $auth));
    $template = 'show_dropoff.tpl';
    // Allow HTMLWrite to over-ride the template file if it wants to
    $template2 = $thePickup->HTMLWrite();
    if ($template2 != "")
      $template = $template2;
    $smarty->display($template);
    //$smarty->display('no_download.tpl');
  }
  
}

?>
