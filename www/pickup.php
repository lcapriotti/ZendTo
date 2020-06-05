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

//
// This is pretty straightforward; depending upon the form data coming
// into this PHP session, creating a new dropoff object will either
// display the claimID-and-claimPasscode "dialog" (no form data or
// missing/invalid passcode); display the selected dropoff if the
// claimID and claimPasscode are valid OR the recipient matches the
// authenticate user -- it's all built-into the NSSDropoff class.
//

global $SYSADMIN;

if ( $theDropbox = new NSSDropbox($NSSDROPBOX_PREFS) ) {

  # Send this just in case we're automated. Humans will never see it.
  if (! $theDropbox->authorizedUser())
    header("X-ZendTo-Response: ".
           json_encode(array("status" => "error", "error" => "login failed")));

  // If they are an authorised user, just display the normal pickup page.
  if ($theDropbox->authorizedUser() ||
      !$theDropbox->humanDownloads() ||
      (($theDropbox->captcha() == 'google' || $theDropbox->captcha() == '') &&
       $theDropbox->recaptchaPrivateKey() == 'disabled') ||
      $theDropbox->captcha() == 'disabled') {

    // 2-line addition by Francois Conil to fix problems with no CAPTCHA
    // and anonymous users who don't have a link to click on.
    $auth = $theDropbox->WriteAuthData(getClientIP($NSSDROPBOX_PREFS), '', '');
    $smarty->assign('auth', $auth);

    if ( $thePickup = new NSSDropoff($theDropbox) ) {
      //
      // Start the page and add some Javascript for automatically
      // filling-in the download form and submitting it when the
      // user clicks on a file in the displayed dropoff.
      //
      $theDropbox->SetupPage($thePickup->HTMLOnLoadJavascript(),
                             array('auth' => $auth));
      $smarty->display($thePickup->HTMLWrite());
    } else {
      // Can't *only* send header here, we can't test for automation
      // as that only works once authorizedUser has been set.
      header("X-ZendTo-Response: ".
           json_encode(array("status" => "error", "error" => "login failed")));
      $theDropbox->SetupPage(NULL, array('auth' => $auth));
      $smarty->display('error.tpl');
    }
    exit(0);
  }

  //
  // They are not an authorised user.
  //

  // Start by checking their passed in auth key. If they have auth'ed
  // successfully, then we don't need to do the captcha again, we just
  // present whatever we were going to present.

  $authSuccess = FALSE;
  if (isset($_POST['auth']) && $_POST['auth']) {
    $auth = $_POST['auth'];
    // Sanitise the auth data
    $auth = preg_replace('/[^a-zA-Z0-9]/', '', $auth);
    $authIP = '';
    $authEmail = '';
    $authOrganization = '';
    $authExpiry = 0;
    $result = $theDropbox->ReadAuthData($auth, $authIP,
                                        $authEmail, $authOrganization,
                                        $authExpiry);
    if (! $result) {
      // Can't *only* send header here, we can't test for automation
      // as that only works once authorizedUser has been set.
      header("X-ZendTo-Response: ".
           json_encode(array("status" => "error", "error" => "login failed")));
      $theDropbox->SetupPage();
      NSSError(gettext("Please click again on the link you were sent to pickup your files."), gettext("Authentication Failure"));
    }
    // I finally fixed the schema using the overnight cleanup job.
    // if ( $authExpiry > time() && strncasecmp($authIP, getClientIP(), 32) == 0 ) {
    if ( $authExpiry > time() && $authIP == getClientIP($NSSDROPBOX_PREFS) ) {
      $authSuccess = TRUE;
    }
    // Everything succeeded, so let them through.
  }

  $captcha = $theDropbox->captcha();

  // Check their recaptcha result. If they passed, then write an AuthData
  // record with their IP in the Name field. This is then used by download.php.
  // If they failed, re-present the pickup page as if they just went there
  // again, but with an error message at the top telling them they were wrong.
  if ( $authSuccess ||
       ( isset($_POST['Action']) && $_POST['Action'] == "Pickup" )
     ) {
    $resp = FALSE;
    if (!$authSuccess) {
      if ($captcha == 'google') {
        // Using Google or an old version without this set
        $reCaptchaPrivateKey = $theDropbox->recaptchaPrivateKey();
        if ($reCaptchaPrivateKey == 'disabled') {
          $resp = TRUE;
        } else {
          // Old version 1 code.
          // $resp = recaptcha_check_answer($reCaptchaPrivateKey,
          //                                getClientIP(),
          //                                $_POST["g-recaptcha-response"]);
          $recaptcha = new \ReCaptcha\ReCaptcha($reCaptchaPrivateKey,
            new \ReCaptcha\RequestMethod\CurlPost());
          $rcresponse = $recaptcha->verify($_POST["g-recaptcha-response"],
                                           getClientIP($NSSDROPBOX_PREFS));
          if ($rcresponse->isSuccess()) {
            $resp = TRUE;
          }
          if (!$resp) {
            foreach ($rcresponse->getErrorCodes() as $code) {
              if ($code == "missing-input-response")
                $code = gettext("I do not think you are a real person.");
              elseif ($code == 'timeout-or-duplicate')
                $code = gettext("You tried to re-submit this form. Please start again.");
              NSSError($code, gettext("Are you a real person?"));
            }
          }
        }
      } else {
        $resp = TRUE;
      }
    }

    if ($authSuccess || $resp) {
      // They have passed the CAPTCHA so write an AuthData record for them.
      if (!$authSuccess) {
        // But only if they haven't already been auth-ed once.
        $auth = $theDropbox->WriteAuthData(getClientIP($NSSDROPBOX_PREFS), '', '');
      }
      if ( $auth == '') {
        // Write failed.
        NSSError(gettext("Database failure writing authentication key.").' '.$SYSADMIN, gettext("Database Error"));
        displayPickupCheck($theDropbox, $smarty, $auth);
        exit(0);
      }
    } else {
      // The CAPTCHA response was wrong, so re-present the page with an error
      NSSError(gettext("You failed the test to see if you are a person and not a computer. Please try again."), gettext("Test failed"));
      // // They haven't auth-ed so add a record for them
      // $auth = $theDropbox->WriteAuthData(getClientIP($NSSDROPBOX_PREFS), '', '');
      displayPickupCheck($theDropbox, $smarty, '');
      exit(0);
    }

    // They have passed the test and we have written their AuthData record.
    $smarty->assign('auth', $auth); // And save their auth key!

    if ( $thePickup = new NSSDropoff($theDropbox) ) {
      //
      // Start the page and add some Javascript for automatically
      // filling-in the download form and submitting it when the
      // user clicks on a file in the displayed dropoff.
      //
      // Add the new auth value so the language picker can use it.
      $theDropbox->SetupPage($thePickup->HTMLOnLoadJavascript(),
                             array('auth' => $auth));
      $smarty->display($thePickup->HTMLWrite());
    } else {
      // Add the new auth value so the language picker can use it.
      $theDropbox->SetupPage(NULL, array('auth' => $auth));
      $smarty->display('error.tpl');
    }
  } else {
    // It's not a pickup attempt, it's going to display the CAPTCHA form
    // instead which will pass us back to me again.
    displayPickupCheck($theDropbox, $smarty, '');
  }
} else {
  $smarty->display('error.tpl');
}

function displayPickupCheck($theDropbox, $smarty, $auth) {
    global $currentLocale;
    // If the locale isn't in this mapping, just use its first 2 letters
    $localeTranslate = array('zh_HK' => 'zh-HK', 'zh_CN' => 'zh-CN',
                             'zh_TW' => 'zh-TW', 'en_GB' => 'en-GB',
                             'fr_CA' => 'fr-CA', 'de_AT' => 'de-AT',
                             'de_CH' => 'de-CH', 'pt_BR' => 'pt-BR');

    $theDropbox->SetupPage();
    $claimID = isset($_POST['claimID'])?$_POST['claimID']:(isset($_GET['claimID'])?$_GET['claimID']:NULL);
    $claimPasscode = isset($_POST['claimPasscode'])?$_POST['claimPasscode']:(isset($_GET['claimPasscode'])?$_GET['claimPasscode']:NULL);
    $emailAddr = isset($_POST['emailAddr'])?$_POST['emailAddr']:(isset($_GET['emailAddr'])?$_GET['emailAddr']:NULL);

    $auth = preg_replace('/[^a-zA-Z0-9]/', '', $auth);
    $claimID = preg_replace('/[^a-zA-Z0-9]/', '', $claimID);
    $claimPasscode = preg_replace('/[^a-zA-Z0-9]/', '', $claimPasscode);
    if ( isset($emailAddr) && ! preg_match($theDropbox->validEmailRegexp(),$emailAddr) ) {
      $emailAddr = 'INVALID';
    }

    $smarty->assign('claimID', $claimID);
    $smarty->assign('claimPasscode', $claimPasscode);
    $smarty->assign('emailAddr', $emailAddr);
    $smarty->assign('auth', $auth);

    if ($theDropbox->captcha() == 'google') {
      // It's the Google reCAPTCHA
      // Set the reCAPTCHA language correctly (default = US English)
      if (isset($localeTranslate[$currentLocale]))
        $lang = $localeTranslate[$currentLocale];
      else
        $lang = substr($currentLocale, 0, 2);
      $smarty->assign('recaptchaLang', $lang);
      $reCaptchaPublicKey = $theDropbox->recaptchaPublicKey();
      $invisible = $theDropbox->recaptchaInvisible();
      $smarty->assign('recaptchaSiteKey', $reCaptchaPublicKey);
      $smarty->assign('recaptchaHTML',
                      recaptcha_get_html($reCaptchaPublicKey,
                                         $invisible));
      $smarty->assign('invisibleCaptcha', $invisible);
    } else {
      $smarty->assign('recaptchaHTML', '');
      $smarty->assign('invisibleCaptcha', false);
    }
    $smarty->display('pickupcheck.tpl');
}
?>
