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
require_once(NSSDROPBOX_LIB_DIR."Verify.php");

if ( $theDropbox = new NSSDropbox($NSSDROPBOX_PREFS) ) {
  
  $theDropbox->SetupPage();

  // Quick bail out here: If they have just chosen this from the main
  // menu (so Action !== verify) and they are an authorised user,
  // then circumvent this form if the prefs setting tells us to.
  if ( @$_POST['Action'] !== "verify" &&
       $theDropbox->authorizedUser() &&
       $theDropbox->skipSenderInfo() ) {
    displayNewDropoffForm();
    exit;
  }

  if ( @$_POST['Action'] == "verify" ) {
    //
    // Posted form data indicates that a dropoff form was filled-out
    // and submitted.
    //

    // If the request key is provided, then pull it out and look it up.
    // If it's a real one, then just redirect straight out to the pre-
    // populated New Dropoff page by simulating them clicking the link
    // in the email they will receive.

    $error_t = gettext("Your Request Code could not be found or has already been used.");
    $title_t = gettext("Request Code Error");
    if ( $_POST['req'] != '' ) {
      $reqKey = $_POST['req'];
      $reqKey = preg_replace('/[^a-zA-Z0-9]/', '', $reqKey);
      $reqKey = strtolower(substr($reqKey, 0, 12)); // Get 1st 3 short words
      $recordlist = $theDropbox->database->DBReadReqData($reqKey);
      if ( $recordlist && count($recordlist) ) {
        // Key exists in database, so use it.
        Header( "HTTP/1.1 302 Moved Temporarily" );
        if ($theDropbox->hidePHP())
          Header( 'Location: '.$NSSDROPBOX_URL.'req?req='.$reqKey );
        else
          Header( 'Location: '.$NSSDROPBOX_URL.'req.php?req='.$reqKey );
        exit(0);
      } else {
        if ( ! $theDropbox->authorizedUser() ) {
          if ( $theDropbox->allowExternalUploads() ) {
            NSSError($error_t.' '.gettext("You can still send files straight from the main menu, or ask for a new Request Code."), $title_t);
          } else {
            NSSError($error_t.' '.gettext("Please check it was entered correctly, or ask for a new Request Code."), $title_t);
          }
        } else {
            NSSError($error_t.' '.gettext("Either complete this form, or press your browser's Back button and re-enter your Request Code."), $title_t);
        }
      }
    }


    //
    // If posted form data is around, creating a new dropoff instance
    // creates a new dropoff using said form data.
    //

    if ( ! $theDropbox->authorizedUser() ) {

      $captcha = $theDropbox->captcha();
      $resp = FALSE;
      // If they aren't authorised and we aren't allowing external
      // users to upload (except with a request code), then we
      // don't do any captcha stuff anyway.
      if ( $theDropbox->allowExternalUploads() ) {
        if ($captcha === 'google') {
          // Using Google or an old version without this set
          $reCaptchaPrivateKey = $theDropbox->recaptchaPrivateKey();
          if ($reCaptchaPrivateKey === 'disabled') {
            $resp = TRUE;
          } else {
            $recaptcha = new \ReCaptcha\ReCaptcha($reCaptchaPrivateKey,
                           new \ReCaptcha\RequestMethod\CurlPost());
            $rcresponse = $recaptcha->verify($_POST["g-recaptcha-response"],
                                             getClientIP($NSSDROPBOX_PREFS));
            if ($rcresponse->isSuccess()) {
              $resp = TRUE;
            // } else {
            //   // ReCaptcha check failed, so let's try to log something useful
            //   $theDropbox->writeToLog("Error: Sent this as the ReCaptcha response '" . $_POST["g-recaptcha-response"] . "' and Google said this '" . implode(',',$rcresponse->getErrorCodes()) . "'");
            }
            if (!$resp) {
              foreach ($rcresponse->getErrorCodes() as $code) {
                if ($code == "missing-input-response")
                  $code = gettext("I do not think you are a real person.");
                elseif ($code == 'timeout-or-duplicate')
                  $code = gettext("You tried to re-submit this form. Please go back to the main menu and start again.");
                NSSError($code, gettext("Are you a real person?"));
              }
            }
          }
        } else {
          // Captcha must be disabled
          $resp = TRUE;
        }
      }

      if ($resp && ( $theVerify = new Verify($theDropbox) )) {
        // They passed the Captcha so send them on their way if at all possible!
        if ($theVerify->formInitError() != "") {
          NSSError($theVerify->formInitError(), gettext("Verify Error"));
          $smarty->display('error.tpl');
        } else {
          if (! $theVerify->sendVerifyEmail()) {
            NSSError(gettext("Sending the verification email failed."), gettext("Email Error"));
          }
          $smarty->assign('autoHome', TRUE);
          $smarty->display('verify_sent.tpl');
        }
        exit;
      }
      // If they reached here, they failed the Captcha test
      $smarty->assign('verifyFailed', TRUE);
    } else {
      // They are an authorised user so don't need a Captcha
      displayNewDropoffForm($_POST['senderOrganization']);
      exit;
    }
  }

  //
  // We need to present the dropoff sender form.  This page will include some
  // JavaScript that does basic checking of the form prior to submission
  // as well as the code to handle the attachment of multiple files.
  // After all that, we start the page body and write-out the HTML for
  // the form.
  //
  // If the user is authenticated then some of the fields will be
  // already-filled-in (sender name and email).
  //

  $smarty->assign('senderName', ($theDropbox->authorizedUser() ? htmlentities($theDropbox->authorizedUserData("displayName"), ENT_COMPAT, 'UTF-8') : (isset($_POST['senderName'])?htmlentities($_POST['senderName'], ENT_COMPAT, 'UTF-8'):NULL)));
  $smarty->assign('senderOrg', ($theDropbox->authorizedUser() ? htmlentities($theDropbox->authorizedUserData("organization"), ENT_COMPAT, 'UTF-8') : (isset($_POST['senderOrganization'])?htmlentities($_POST['senderOrganization'], ENT_COMPAT, 'UTF-8'):NULL)));
  $smarty->assign('senderEmail', ($theDropbox->authorizedUser() ? strtolower($theDropbox->authorizedUserData("mail")) : (isset($_POST['senderEmail'])?htmlentities($_POST['senderEmail'], ENT_COMPAT, 'UTF-8'):NULL)));
  $smarty->assign('allowUploads', TRUE);

  if ( ! $theDropbox->authorizedUser() ) {
    global $currentLocale;
    // If the locale isn't in this mapping, just use its first 2 letters
    $localeTranslate = array('zh_HK' => 'zh-HK', 'zh_CN' => 'zh-CN',
                             'zh_TW' => 'zh-TW', 'en_GB' => 'en-GB',
                             'fr_CA' => 'fr-CA', 'de_AT' => 'de-AT',
                             'de_CH' => 'de-CH', 'pt_BR' => 'pt-BR');

    // Are uploads allowed by external users? (other than request codes)
    $smarty->assign('allowUploads', $theDropbox->allowExternalUploads());
    // Set up the CAPTCHA
    $captcha = $theDropbox->captcha();
    if ($captcha === 'google' || $captcha === '') {
      // Using Google or an old version without this set
      $reCaptchaPublicKey = $theDropbox->recaptchaPublicKey();
      if ($reCaptchaPublicKey === 'disabled') {
        $smarty->assign('recaptchaDisabled', TRUE);
      } else {
        // Set the reCAPTCHA language correctly (default = US English)
        if (isset($localeTranslate[$currentLocale]))
          $lang = $localeTranslate[$currentLocale];
        else
          $lang = substr($currentLocale, 0, 2);
        $smarty->assign('recaptchaLang', $lang);
        $smarty->assign('recaptchaSiteKey', $reCaptchaPublicKey);
        $smarty->assign('recaptchaHTML',
                        recaptcha_get_html($reCaptchaPublicKey,
                        @$NSSDROPBOX_PREFS['recaptchaInvisible']));
        $smarty->assign('recaptchaDisabled', FALSE);
        $smarty->assign('invisibleCaptcha',
                        @$NSSDROPBOX_PREFS['recaptchaInvisible']);
      }
    } else {
      // Captcha must be disabled
      $smarty->assign('recaptchaDisabled', TRUE);
    }
  }
  $smarty->display('verify.tpl');
}

//
// Display the new drop-off form, as pre-populated as we can.
//

function displayNewDropoffForm(
  $authOrganization = ''
)
{
  global $theDropbox;
  global $smarty;

  // They are an authorised user so don't need a Captcha
  if ( $theVerify = new Verify($theDropbox) ) {
    if ($theVerify->formInitError() != "") {
      NSSError($theVerify->formInitError(),"Verify Error");
      $smarty->display('error.tpl');
    } else {
      // The form worked, go for it!
      $theDropbox->SetupPage();

      $authFullName     = $theDropbox->authorizedUserData("displayName");
      $authEmail        = $theDropbox->authorizedUserData("mail");
      if (!$authOrganization)
        $authOrganization = $theDropbox->authorizedUserData("organization");
      // Can't hit it with a cricket bat, do it more gently
      $authOrganization = preg_replace('/[<>]/', '', $authOrganization);

      // Escape them here, not in the template
      $smarty->assign('senderName', htmlentities($authFullName, ENT_NOQUOTES, 'UTF-8'));
      $smarty->assign('senderOrg', htmlentities($authOrganization, ENT_NOQUOTES, 'UTF-8'));
      $smarty->assign('senderEmail', htmlentities(strtolower($authEmail), ENT_NOQUOTES, 'UTF-8'));
      $smarty->assign('recipEmailNum', 1);
      $smarty->assign('addressbook', $theDropbox->getAddressbook());

      # Generate unique ID required for progress bars status
      $smarty->assign('progress_id', uniqid(""));
      $smarty->assign('note','');
      $smarty->assign('subject', sprintf(gettext('%s has dropped off files for you'), htmlentities($authFullName, ENT_QUOTES, 'UTF-8')));
      $smarty->assign('maxBytesForFileInt', $theDropbox->maxBytesForFile());
      $smarty->assign('maxBytesForDropoffInt', $theDropbox->maxBytesForDropoff());
      $smarty->assign('maxNoteLength', $theDropbox->maxnotelength());
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
      $smarty->assign('allowPassphraseDialog', TRUE);
      $smarty->assign('chunkName', NSSGenerateCode(32));
      $smarty->assign('uploadChunkSize', $theDropbox->uploadChunkSize());
      $smarty->assign('lifedays', $theDropbox->defaultLifetime());
      $smarty->assign('defaultNumberOfDaysToRetain', $theDropbox->defaultNumberOfDaysToRetain());

      // If we are using a library of files, fill the structures it needs.
      if ($theDropbox->authorizedUser() && $theDropbox->usingLibrary()) {
        $library = $theDropbox->getLibraryDescs();
        $smarty->assign('library', $library);
        $smarty->assign('usingLibrary', ($library==='[]')?FALSE:TRUE);
      } else {
        $smarty->assign('usingLibrary', FALSE);
        $smarty->assign('library', '[]');
      }

      $smarty->display('new_dropoff.tpl');
    }
  } else {
    $smarty->display('error.tpl');
  }
}
?>
