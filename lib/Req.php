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

require_once(NSSDROPBOX_LIB_DIR."NSSDropbox.php");
require_once(NSSDROPBOX_LIB_DIR."NSSUtils.php");
require_once(NSSDROPBOX_LIB_DIR."Timestamp.php");

/*!
  @class Req
*/
class Req {

  //  Instance data:
  private $_dropbox = NULL;
  
  private $_auth;
  private $_words;
  private $_senderName;
  private $_senderEmail;
  private $_senderOrg;
  private $_recipName;
  private $_recipEmail;
  private $_note;
  private $_subject;
  private $_encrypted;
  private $_formInitError = NULL;
  
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
    $recipEmail,
    $qResult = FALSE
  )
  {
    $this->_dropbox = $aDropbox;
    
    if ( ! $qResult ) {
      //  Try to create a new one from form data:
      $this->_formInitError = $this->initWithFormData($recipEmail);
    } else {
      NSSError(gettext("This form cannot be called like this, please return to the main menu."), gettext("Internal Error"));
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
  public function authorizedUser() { return $this->_authorizedUser; }
  public function auth() { return $this->_auth; }
  public function words() { return $this->_words; }
  public function senderName() { return $this->_senderName; }
  public function senderEmail() { return $this->_senderEmail; }
  public function recipName() { return $this->_recipName; }
  public function recipEmail() { return $this->_recipEmail; }
  public function confirmDelivery() { return $this->_confirmDelivery; }
  public function created() { return $this->_created; }
  public function recipients() { return $this->_recipients; }
  public function encrypted() { return $this->_encrypted; }
  public function formInitError() { return $this->_formInitError; }
  

  /*!
    @function initWithFormData
    
    This monster routine examines POST-type form data coming from our verify
    form, validates all of it, and writes a new authtable record.
    
    The validation is done primarily on the email addresses that are involved,
    and all of that is documented inline below.  We also have to be sure that
    the user didn't leave any crucial fields blank.
    
    If any errors occur, this function will return an error string.  But
    if all goes according to plan, then we return NULL!
  */
  private function initWithFormData(
            $recipEmail
  )
  {
    global $smarty;
    global $NSSDROPBOX_URL;
    global $BACKBUTTON;
    global $SYSADMIN;
    
    // They are an authenticated user so try to get their name and email
    // from the authentication system.
    $senderName = $this->_dropbox->authorizedUserData("displayName");
    if ( ! $senderName || $this->_dropbox->isAutomated() ) {
      $senderName = paramPrepare($_POST['senderName']);
    }
    $senderEmail = strtolower($this->_dropbox->authorizedUserData("mail"));
    if ( ! $senderEmail || $this->_dropbox->isAutomated() ) {
      $senderEmail = paramPrepare($_POST['senderEmail']);
    }
    // Only use the value from the request form if they were allowed to
    // edit it. Otherwise use what it would have been forced to in req.php.
    if ( $this->_dropbox->requestOrgEditable() ) {
      $senderOrganization = paramPrepare(@$_POST['senderOrg']);
    } else {
      $senderOrganization = $this->_dropbox->authorizedUserData("organization");
    }
    $recipName = isset($_POST['recipName'])?$_POST['recipName']:'';
    # This is now read from a parameter passed to us
    #$recipEmail = stripslashes(strtolower($_POST['recipEmail']));
    // SLASH $note = stripslashes($_POST['note']);
    $note = $_POST['note'];
    // Only want the passphrase if they ticked the box too
    if (@$_POST['encryptFiles']) {
      $passphrase = @$_POST['encryptPassword'];
    } else {
      $passphrase = '';
    }
    
    // Sanitise the data
    // $subject            = preg_replace('/[<>]/', '', $_POST['subject']);
    $subject = trim(html_entity_decode($_POST['subject'], ENT_QUOTES, 'UTF-8'));
    $senderName         = preg_replace('/[<>]/', '', $senderName);
    $senderOrganization = preg_replace('/[<>]/', '', $senderOrganization);
    $recipName          = preg_replace('/[<>]/', '', $recipName);
    // Thanks to Luigi Capriotti for this suggestion!
    $expiryString = @$_POST['expiryDateTime'];
    $expiryDateTime = 0;
    if ($expiryString) {
      if (DateTime::createFromFormat('Y-m-d H:i:s', $expiryString) !== FALSE ) {
        $expiryDateTime = timeForTimestamp($expiryString);
      }
    }

    if ( ! $senderName ) {
      return gettext("You must specify your name in the form.").' '.$BACKBUTTON;
    }
    if ( ! $senderEmail ) {
      return gettext("You must specify your own email address in the form.").' '.$BACKBUTTON;
    }
    //if ( ! $recipName ) {
    //  return gettext("You must specify the recipient's name in the form.").' '.$BACKBUTTON;
    //}
    if ( ! $recipEmail ) {
      return gettext("You must specify the recipient's email address in the form.").' '.$BACKBUTTON;
    }
    if ( ! preg_match($this->_dropbox->validEmailRegexp(),$senderEmail,$emailParts) ) {
      return gettext("Your email address you entered was invalid.").' '.$BACKBUTTON;
    }
    $senderEmail = $emailParts[1]."@".$emailParts[2];
    if ( ! preg_match($this->_dropbox->validEmailRegexp(),$recipEmail,$emailParts) ) {
      return gettext("The recipient's email address you entered was invalid.").' '.$BACKBUTTON;
    }
    $recipEmail = $emailParts[1]."@".$emailParts[2];
    
    // Check the length of the subject.
    //$subject = $smarty->getConfigVars('EmailSubjectTag') . $subject;
    $subjectlength = mb_strlen($smarty->getConfigVars('EmailSubjectTag') . $subject);
    $maxlen = $this->_dropbox->maxsubjectlength();
    if ($subjectlength>$maxlen) {
      return sprintf(gettext("Your subject line to the recipients is %1$d characters long. It must be less than %2$d."), $subjectlength, $maxlen).' '.$BACKBUTTON;
    }

    // The subject line of the files will be a "Re: +subject"
    $reSubject = trim($subject);
    if (!preg_match('/^Re:/i', $reSubject)) {
      $reSubject = 'Re: ' . $reSubject;
    }

    // Before I write the passphrase, I need to obfuscate it then bin2hex it
    if (isset($passphrase) && $passphrase !== '') {
      // Check if the source of the key is good enough
      $secret = $this->_dropbox->secretForCookies();
      $secret = preg_replace("/[^0-9a-fA-F]/", "", $secret);
      if (strlen($secret)<2*SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        // If it's not, squeal but continue anyway.
        $this->_dropbox->writeToLog("Error: The 'cookieSecret' set in preferences.php is less than ".(SODIUM_CRYPTO_SECRETBOX_KEYBYTES*2)." hex characters! Use ".NSSDROPBOX_BASE_DIR."sbin/genCookieSecret.php to create a valid one");
      }
      $obfusPassphrase = encryptForDB($passphrase, $this->_dropbox->secretForCookies());
      $this->_encrypted = TRUE;
      $this->_dropbox->writeToLog("Info: Creating request with encryption enabled");
    } else {
      $obfusPassphrase = ''; // Empty string in DB table implies no passphrase
      $this->_encrypted = FALSE;
    }

    //  Insert into database:
    $words = $this->_dropbox->WriteReqData($senderName, $senderEmail, $senderOrganization, $recipName, $recipEmail, $note, $reSubject, $obfusPassphrase, $expiryDateTime);

    // Wipe sensitive data
    if (isset($passphrase))      sodium_memzero($passphrase);
    if (isset($obfusPassphrase)) sodium_memzero($obfusPassphrase);

    if ( $words == '') {
      return gettext("Database failure writing request information.").' '.$SYSADMIN;
    } else {
      $this->_words       = $words;
      $this->_auth        = preg_replace('/[^a-zA-Z0-9]/', '', $words);
      $this->_senderName  = $senderName;
      $this->_senderEmail = $senderEmail;
      $this->_senderOrg   = $senderOrganization;
      $this->_recipName   = $recipName;
      $this->_recipEmail  = $recipEmail;
      $this->_note        = $note;
      $this->_subject     = $subject;
      // ->_encrypted is set a few lines above
    }
    return "";
  }

  public function sendReqEmail ()
  {
    global $smarty;
    global $NSSDROPBOX_URL;

    //  Construct the email notification and deliver:
    $smarty->assign('fromName',  $this->_senderName);
    $smarty->assign('fromEmail', $this->_senderEmail);
    $smarty->assign('fromOrg',   $this->_senderOrg);
    $smarty->assign('toName',    $this->_recipName);
    $smarty->assign('toEmail',   $this->_recipEmail);
    $smarty->assign('note',      $this->_note);
    $emailSubject = $smarty->getConfigVars('EmailSubjectTag') . $this->_subject;
    $smarty->assign('subject', $emailSubject);
    // Tell the recipient if their drop-off will automatically be
    // encrypted. Passphrase only known to person who sent the request.
    $smarty->assign('encrypted', $this->_encrypted);

    // advertisedServerRoot overrides serverRoot if it's defined
    $urlroot = $this->_dropbox->advertisedServerRoot();
    if ($urlroot) {
      // They *did* end it with a / didn't they??
      if (substr($urlroot, -1) !== '/') $urlroot .= '/';
      if ($this->_dropbox->hidePHP())
        $smarty->assign('URL', $urlroot.'req?req='.$this->_auth);
      else
        $smarty->assign('URL', $urlroot.'req.php?req='.$this->_auth);
      $smarty->assign('zendToURL', $urlroot);
    } else {
      if ($this->_dropbox->hidePHP())
        $smarty->assign('URL', $NSSDROPBOX_URL.'req?req='.$this->_auth);
      else
        $smarty->assign('URL', $NSSDROPBOX_URL.'req.php?req='.$this->_auth);
      $smarty->assign('zendToURL', $NSSDROPBOX_URL);
    }
 
    $htmltpl = '';
    $texttpl = '';
    try {
      $texttpl = $smarty->fetch('request_email.tpl');
    }
    catch (SmartyException $e) {
      $this->_dropbox->writeToLog("Error: Could not create request email text: ".$e->getMessage());
      $texttpl = $e->getMessage();
    }
    if ($smarty->templateExists('request_email_html.tpl')) {
      try {
        $htmltpl = $smarty->fetch('request_email_html.tpl');
      }
      catch (SmartyException $e) {
        $this->_dropbox->writeToLog("Error: Could not create request email HTML: ".$e->getMessage());
        $htmltpl = $e->getMessage();
      }
    }

    $success = $this->_dropbox->deliverEmail(
                 $this->_recipEmail,
                 $this->_senderEmail,
                 $this->_senderName,
                 $emailSubject,
                 $texttpl,
                 $htmltpl);

    // Before we go any further, reset the original value of zendToURL
    // in case we use it again later when displaying a results page.
    $smarty->assign('zendToURL', $NSSDROPBOX_URL);

    // Did the email go successfully?
    if ( $success ) {
      $this->_dropbox->writeToLog(sprintf("Info: request-for-dropoff email delivered successfully from %s to %s",$this->_senderEmail, $this->_recipEmail));
    } else {
      $this->_dropbox->writeToLog(sprintf("Error: request-for-dropoff email delivery failed from %s to %s",$this->_senderEmail, $this->_recipEmail));
      return FALSE;
    }

    // Everything worked and the mail was sent!
    return TRUE;
  }
        
}

?>
