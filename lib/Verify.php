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
  @class Verify
*/
class Verify {

  //  Instance data:
  private $_dropbox = NULL;
  
  private $_authorizedUser;
  private $_emailAddr;
  
  private $_auth;
  private $_senderName;
  private $_senderOrganization;
  private $_senderEmail;
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
    $qResult = FALSE
  )
  {
    $this->_dropbox = $aDropbox;
    
    if ( ! $qResult ) {
      if ( @$_POST['Action'] == "verify" ) {
        $this->_showPasscodeHTML = FALSE;
        //  Try to create a new one from form data:
        $this->_formInitError = $this->initWithFormData();
      }
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
  public function claimID() { return $this->_claimID; }
  public function claimPasscode() { return $this->_claimPasscode; }
  public function claimDir() { return $this->_claimDir; }
  public function authorizedUser() { return $this->_authorizedUser; }
  public function senderName() { return $this->_senderName; }
  public function senderOrganization() { return $this->_senderOrganization; }
  public function senderEmail() { return $this->_senderEmail; }
  public function senderIP() { return $this->_senderIP; }
  public function confirmDelivery() { return $this->_confirmDelivery; }
  public function created() { return $this->_created; }
  public function recipients() { return $this->_recipients; }
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
  private function initWithFormData()
  {
    global $NSSDROPBOX_URL;
    global $BACKBUTTON;
    global $SYSADMIN;
    
    if ( $this->_dropbox->authorizedUser() ) {
      // They are an authenticated user so try to get their name and email
      // from the authentication system.
      $senderName = $this->_dropbox->authorizedUserData("displayName");
      if ( ! $senderName ) {
        $senderName = paramPrepare($_POST['senderName']);
      }
      $senderEmail = strtolower($this->_dropbox->authorizedUserData("mail"));
      if ( ! $senderEmail ) {
        $senderEmail = paramPrepare($_POST['senderEmail']);
      }
    } else {
      // They are not an authenticated user so get their name and email
      // from the form.
      $senderName = paramPrepare($_POST['senderName']);
      $senderEmail = paramPrepare(strtolower($_POST['senderEmail']));
    }
    $senderOrganization = paramPrepare($_POST['senderOrganization']);
    
    // Sanitise the data
    // Can't hit it with a cricket bat any more, need to be more subtle
    // $senderName = preg_replace('/[^a-zA-Z0-9\.\-\_\+\"\'\@\/\:\&\, ]/', '', $senderName);
    // $senderEmail = preg_replace('/[^a-zA-Z0-9\.\-\_\+\"\'\@\/\:\&\, ]/', '', $senderEmail);
    // $senderOrganization = preg_replace('/[^a-zA-Z0-9\.\-\_\+\"\'\@\/\:\&\, ]/', '', $senderOrganization);
    $senderName = preg_replace('/[<>]/', '', $senderName);
    $senderEmail = preg_replace('/[<>]/', '', $senderEmail);
    $senderOrganization = preg_replace('/[<>]/', '', $senderOrganization);

    if ( ! $senderName ) {
      return gettext("You must specify your name in the form.").' '.$BACKBUTTON;
    }
    if ( ! $senderEmail ) {
      return gettext("You must specify your own email address in the form.").' '.$BACKBUTTON;
    }
    if ( ! preg_match($this->_dropbox->validEmailRegexp(),$senderEmail,$emailParts) ) {
      return gettext("The email address you entered was invalid.").' '.$BACKBUTTON;
    }
    $senderEmail = $emailParts[1]."@".$emailParts[2];
    
    //  Insert into database:
    $auth = $this->_dropbox->WriteAuthData($senderName, $senderEmail, $senderOrganization);

    if ( $auth == '') {
      NSSError(gettext("Database failure writing authentication key.").' '.$SYSADMIN, gettext("Database Error"));
    }
  }

  public function sendVerifyEmail ()
  {
    global $smarty;
    global $NSSDROPBOX_URL;
    global $SYSADMIN;

    if ( $this->_dropbox->authorizedUser() ) {
      // They are an authenticated user so try to get their name and email
      // from the authentication system.
      $senderName = $this->_dropbox->authorizedUserData("displayName");
      if ( ! $senderName ) {
        $senderName = paramPrepare($_POST['senderName']);
      }
      $senderEmail = strtolower($this->_dropbox->authorizedUserData("mail"));
      if ( ! $senderEmail ) {
        $senderEmail = paramPrepare($_POST['senderEmail']);
      }
    } else {
      // They are not an authenticated user so get their name and email
      // from the form.
      $senderName = paramPrepare($_POST['senderName']);
      $senderEmail = paramPrepare(strtolower($_POST['senderEmail']));
    }
    $senderOrganization = paramPrepare($_POST['senderOrganization']);
    
    // Sanitise the data
    // Still needs doing to save us from nasty crap in email!
    // Can't hit it with a cricket bat any more, need to be more subtle
    // $senderName = preg_replace('/[^a-zA-Z0-9\.\-\_\+\"\'\@\/\:\&\,\$ ]/', '', $senderName);
    // $senderEmail = preg_replace('/[^a-zA-Z0-9\.\-\_\+\"\'\@\/\:\&\,\$ ]/', '', $senderEmail);
    // $senderOrganization = preg_replace('/[^a-zA-Z0-9\.\-\_\+\"\'\@\/\:\&\,\$ ]/', '', $senderOrganization);
    $senderName = preg_replace('/[<>]/', '', $senderName);
    $senderEmail = preg_replace('/[<>]/', '', $senderEmail);
    $senderOrganization = preg_replace('/[<>]/', '', $senderOrganization);

    if ( ! $senderName ) {
      return FALSE;
    }
    if ( ! $senderEmail ) {
      return FALSE;
    }
    if ( ! preg_match($this->_dropbox->validEmailRegexp(),$senderEmail,$emailParts) ) {
      return FALSE;
    }
    // $senderEmail = $emailParts[1]."@".$emailParts[2];
    
    //  Insert into database:
    $auth = $this->_dropbox->WriteAuthData($senderName, $senderEmail, $senderOrganization);

    if ( $auth == '') {
      NSSError(gettext("Database failure writing authentication key.").' '.$SYSADMIN, gettext("Database Error"));
      return FALSE;
    }

    $this->_senderName            = $senderName;
    $this->_senderOrganization    = $senderOrganization;
    $this->_senderEmail           = $senderEmail;

    // If they are authenticated user, then generate a form containing
    // the data and auto-post it.
    // And do the same if we aren't confirming external email addresses
    // at all.
    if ( $this->_dropbox->authorizedUser() || ! $this->_dropbox->confirmExternalEmails()) {
      Header( "HTTP/1.1 302 Moved Temporarily" ); 
      if ($this->_dropbox->hidePHP())
        Header( "Location: ".$NSSDROPBOX_URL."dropoff?auth=".$auth );
      else
        Header( "Location: ".$NSSDROPBOX_URL."dropoff.php?auth=".$auth );
      if (!$this->_dropbox->confirmExternalEmails()) {
        $this->_dropbox->writeToLog(sprintf("Info: skipped email confirmation for external user %s",$senderEmail));
      } else {
        $this->_dropbox->writeToLog(sprintf("Info: auto-verification for logged in user %s",$senderEmail));
      }
    } else {
      //  Construct the email notification and deliver:
      $smarty->assign('senderName',  $senderName);
      $smarty->assign('senderOrg',   $senderOrganization);
      $smarty->assign('senderEmail', $senderEmail);
      $emailSubject = $smarty->getConfigVars('EmailSubjectTag') .
                      gettext('You are trying to drop off some files');

      // advertisedServerRoot overrides serverRoot if it's defined
      $urlroot = $this->_dropbox->advertisedServerRoot();
      if ($urlroot) {
        // They *did* end it with a / didn't they??
        if (substr($urlroot, -1) !== '/') $urlroot .= '/';
        if ($this->_dropbox->hidePHP())
          $smarty->assign('URL', $urlroot."dropoff?auth=$auth");
        else
          $smarty->assign('URL', $urlroot."dropoff.php?auth=$auth");
      } else {
        if ($this->_dropbox->hidePHP())
          $smarty->assign('URL', $NSSDROPBOX_URL."dropoff?auth=$auth");
        else
          $smarty->assign('URL', $NSSDROPBOX_URL."dropoff.php?auth=$auth");
      }
 
      $htmltpl = '';
      $texttpl = '';
      try {
        $texttpl = $smarty->fetch('verify_email.tpl');
      }
      catch (SmartyException $e) {
        $this->_dropbox->writeToLog("Error: Could not create verify email text: ".$e->getMessage());
        $texttpl = $e->getMessage();
      }
      if ($smarty->templateExists('verify_email_html.tpl')) {
        try {
          $htmltpl = $smarty->fetch('verify_email_html.tpl');
        }
        catch (SmartyException $e) {
          $this->_dropbox->writeToLog("Error: Could not create verify email HTML: ".$e->getMessage());
          $htmltpl = $e->getMessage();
        }
      }

      $success = $this->_dropbox->deliverEmail(
                   $senderEmail,
                   $smarty->getConfigVars('EmailSenderAddress'),
                   '',
                   $emailSubject,
                   $texttpl,
                   $htmltpl);
      if ( $success ) {
        $this->_dropbox->writeToLog(sprintf("Info: address verification email delivered successfully to %s",$senderEmail));
      } else {
        $this->_dropbox->writeToLog(sprintf("Error: address verification email delivery failed to %s",$senderEmail));
        return FALSE;
      }
    }

    // Everything worked and the mail was sent!
    return TRUE;
  }
        

  // ReadAuthData(authkey, name, email, org, expiry)
  //
  public function ReadAuthData(
    $authkey,
    &$name,
    &$email,
    &$org,
    &$expiry
  )
  {
    // Only allow letters and numbers in $authkey
    $authkey = preg_replace('/[^a-zA-Z0-9]/', '', $authkey);

    $name = '';
    $email = '';
    $org   = '';
    $expiry = '';

    $recordlist = $this->_dropbox->DBReadAuthData($authkey);
    if ( $recordlist && count($recordlist) ) {
      // @ob_end_clean(); //turn off output buffering to decrease cpu usage
      $name = $recordlist[0]['FullName'];
      $email = $recordlist[0]['Email'];
      $org   = $recordlist[0]['Organization'];
      $expiry= $recordlist[0]['Expiry'];
      return 0;
    }
    return 1;
  }

  public function DeleteAuthData(
    $authkey
  )
  {
    $authkey = preg_replace('/[^a-zA-Z0-9]/', '', $authkey);
    $this->_dropbox->DBDeleteAuthData($authkey);
    return 1;
  }


}

?>
