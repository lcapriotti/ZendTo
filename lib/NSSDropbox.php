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

// JKF Uncomment the next 2 lines for loads of debug information.
// error_reporting(E_ALL);
// ini_set("display_errors", 1);

require_once(NSSDROPBOX_LIB_DIR."NSSAuthenticator.php");
require_once(NSSDROPBOX_LIB_DIR."NSSUtils.php");
require_once(NSSDROPBOX_LIB_DIR."Timestamp.php");
require_once(NSSDROPBOX_LIB_DIR."OthersAutoload.php");

global $PHPMailerVersion;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Work out the server's root URL
global $NSSDROPBOX_URL;
$NSSDROPBOX_URL = serverRootURL($NSSDROPBOX_PREFS);

// If it's in the preferences.php then use the value there always.
if ( array_key_exists('serverRoot', $NSSDROPBOX_PREFS) &&
     !empty($NSSDROPBOX_PREFS['serverRoot']) &&
     strpos($NSSDROPBOX_PREFS['serverRoot'], 'zendto.soton.ac.uk') === FALSE) {
     //!preg_match('/zendto\.soton\.ac\.uk/', $NSSDROPBOX_PREFS['serverRoot']) ) {
  $NSSDROPBOX_URL = @$NSSDROPBOX_PREFS['serverRoot'];
} else {
  // Not defined, so we'll have to work it out
  if (@$_SERVER['SERVER_NAME']) {
    // We are being run from the web, so can get a very good guess
    $port = @$_SERVER['SERVER_PORT'];
    $https = @$_SERVER['HTTPS'];
    if (($https && $port==443) || (!$https && $port==80)) {
      $port = '';
    } else {
      $port = ":$port";
    }
    $NSSDROPBOX_URL = "http".($https ? "s" : "")."://".@$_SERVER['SERVER_NAME'].$port.@$_SERVER['REQUEST_URI'];
    // Delete anything after a ? (and the ? itself)
    // $NSSDROPBOX_URL = preg_replace('/\?.*$/', '', $NSSDROPBOX_URL);
    $NSSDROPBOX_URL = substr($NSSDROPBOX_URL, 0, strpos($NSSDROPBOX_URL, '?'));
    // Should now end in blahblah.php or simply a directory /
    // if ( !preg_match('/\/$/',$NSSDROPBOX_URL) ) {
    if ( substr($NSSDROPBOX_URL, -1) !== '/' ) {
      // Delete anything after the last / (but leave the /)
      $NSSDROPBOX_URL = preg_replace('/\/[^\/]+$/','/', $NSSDROPBOX_URL);
    }
  } else {
    // We are running from the cli, so a very poor guess.
    $NSSDROPBOX_URL = 'http://'.php_uname('n').'/';
  }
}
// If it doesn't end with a / then we'll add one to be on the safe side!
//if (!preg_match('/\/$/', $NSSDROPBOX_URL)) {
if (substr($NSSDROPBOX_URL, -1) !== '/') {
  $NSSDROPBOX_URL .= '/';
}

// Set cookie security
if (php_sapi_name() !== 'cli') {
  ini_set('session.cookie_httponly', 1);
  ini_set('session.use_only_cookies', 1);
}
// If your serverRoot setting is https, ban cookies over http
global $cookie_secure;
$cookie_secure = false;
// if (preg_match('/^https/i', $NSSDROPBOX_URL) && php_sapi_name() !== 'cli') {
if (stripos($NSSDROPBOX_URL, 'https', 0) !== FALSE && php_sapi_name() !== 'cli') {
  ini_set('session.cookie_secure', 1);
  $cookie_secure = true;
}

//
// Set the TMPDIR to somewhere within /opt/zendto so that SELinux is happy.
//
// Attempt to create the dir as best as we can.
if (file_exists(NSSDROPBOX_DATA_DIR.'cache/temp')) {
  if (is_file(NSSDROPBOX_DATA_DIR.'cache/temp')) {
    unlink(NSSDROPBOX_DATA_DIR.'cache/temp');
    mkdir(NSSDROPBOX_DATA_DIR.'cache/temp');
  }
} else {
  mkdir(NSSDROPBOX_DATA_DIR.'cache/temp');
}
putenv('TMPDIR=' . NSSDROPBOX_DATA_DIR . 'cache/temp');

// Word list now loaded just before it is needed.
$ShortWordsList = array();

//
// Setup i18n config for gettext()
//
// If 'language' has not been set in preferences.php
// then disable all multi-lingual features completely.
//
// Your (compiled by msgfmt) .mo file should be at
// $locale_dir/$locale/LC_MESSAGES/zendto.mo
// Safest to put the .po source file there too.
function isLocaleValidOrNot( $l ) {
  return file_exists(NSSDROPBOX_BASE_DIR.'/config/locale/'.$l.'/LC_MESSAGES/zendto.mo');
}
// We might be being included by changelocale.php.
// If that is the case, $currentLocale will already be set for us,
// and all the locale changing will have been done already.
global $currentLocale;
if (!$currentLocale) {
  $currentLocale = 'en_US'; // Global default value
  // Try to replace that with their prefs language setting
  if (array_key_exists('language', $NSSDROPBOX_PREFS) &&
      $NSSDROPBOX_PREFS['language'] !== '')
    $currentLocale = $NSSDROPBOX_PREFS['language'];
  // Try to replace that with the value of their ZendTo-locale cookie.
  $localeCookieName = "ZendTo-locale";
  if (isset($_COOKIE[$localeCookieName]) &&
      ($localeCookieValue = $_COOKIE[$localeCookieName])) {
    // They have requested a locale, does it exist on this installation?
    if (isLocaleValidOrNot($localeCookieValue))
      // So let them use it
      $currentLocale = $localeCookieValue;
  }
  putenv("LANGUAGE=$currentLocale");
  putenv("LC_MESSAGES=$currentLocale");
  putenv("LANG=$currentLocale");
  // Try various different versions until one exists. These will need to
  // already be installed on your system. Use 'locale -a' to list them all.
  setlocale(LC_MESSAGES, $currentLocale.".UTF-8", $currentLocale.".utf-8",
                         $currentLocale.".UTF8", $currentLocale.".utf8",
                         $currentLocale);
  // Thanks to Francis for spotting that I had omitted the next line!
  bind_textdomain_codeset('zendto', 'UTF-8');
  bindtextdomain('zendto', NSSDROPBOX_BASE_DIR.'/config/locale');
  textdomain('zendto');
}

// If you want to change this text, do *not* change it here.
// Instead, add a "translation" for it so the actual text output
// is a modification of this. See http://zend.to/language.php.
global $BACKBUTTON;
$BACKBUTTON = gettext("Use the Back button in your browser to go back and fix this error before trying again.");
global $SYSADMIN;
$SYSADMIN = gettext("Please notify the system administrator.");

// And we need to setup the contents of the translation table for
// NSSFormattedMemSize() in NSSUtils.php.
global $NSSFormattedMemSize_Formats;
$NSSFormattedMemSize_Formats = array (
            gettext("%d bytes"),
            gettext("%.1f KB"),
            gettext("%.1f MB"),
            gettext("%.1f GB"),
            gettext("%.1f TB")
);

// If we're a web page, output any extra headers needed
sendHTTPSecurity($NSSDROPBOX_PREFS);


/*!

  @class NSSDropbox
  
  An instance of NSSDropbox serves as the parent for all dropped-off "stuff".
  The instance also acts as a container for the site's preferences; the
  connection to the SQLite database backing the dropbox; and the authenticator
  used to validate and authenticate users.  Accessors are provided for all of
  the instance data (some are read-only, but many of the preference fields
  are read-write), and some methods are implemented to handle common actions
  on the instance data.
*/
class NSSDropbox {

  //  Instance data:
  private $_dropboxDirectory;
  private $_dropboxLog = '/var/zendto/zendto.log';
  private $_retainDays = 14;
  private $_warningDays = 1;
  private $_captcha = 'google';
  private $_recaptchaPublicKey;
  private $_recaptchaPrivateKey;
  private $_recaptchaLanguage = 'en';
  private $_recaptchaInvisible = FALSE;
  private $_emailDomainRegexp;
  private $_secretForCookies;
  private $_cookieName = "zendto_session";
  private $_cookieTTL = 43200; // 12 hours
  private $_requestTTL = 604800; // 1 week
  private $_advertisedServerRoot = '';
  
  private $_maxBytesForFile = 1048576000.0;  //  1000 MB
  private $_maxBytesForDropoff = 2097152000.0; // 2000 MB
  
  private $_authenticator = NULL;
  private $_authenticatorName = NULL;
  private $_authorizedUser = NULL;
  private $_authorizationFailed = FALSE;
  private $_authorizedUserData = NULL;
  private $_emailSenderAddr = NULL;
  private $_emailSenderIP = TRUE;
  private $_defaultEmailDomain = NULL;
  private $_usernameRegexp = NULL;
  private $_validEmailRegexp = NULL;
  private $_reqRecipient = NULL;
  private $_localIPSubnets = NULL;
  private $_languageList = NULL;
  private $_hidePHP = FALSE;
  private $_skipSenderInfo = FALSE;
  private $_humanDownloads = TRUE;
  private $_showRecipsOnPickup = TRUE;
  private $_bccSender = FALSE;
  private $_bccExternalSender = FALSE;
  private $_allowExternalLogins  = TRUE;
  private $_allowExternalUploads = TRUE;
  private $_allowExternalPickups = TRUE;
  private $_allowExternalRecipients = TRUE;
  private $_allowEmailRecipients = TRUE;
  private $_allowEmailPasscode = TRUE;
  private $_defaultEmailPasscode = TRUE;
  private $_defaultEmailRecipients = TRUE;
  private $_defaultConfirmDelivery = TRUE;
  private $_defaultEncryptRequests = FALSE;
  private $_showEncryption = TRUE;
  private $_showChecksum = TRUE;
  private $_showConfirmDelivery = TRUE;
  private $_showEmailRecipients = TRUE;
  private $_showPasscode = TRUE;
  private $_showRecipWaiver = TRUE;
  private $_defaultRecipWaiver = TRUE;
  private $_maxPickupFailures = 0;
  private $_maxBytesForChecksum = 314572800; // 300 MB
  private $_maxBytesForEncrypt  = 314572800; // 300 MB
  private $_enforceEnrypt = FALSE;
  private $_minEncryptPassLength = 8;
  private $_cookieGDPRConsent = FALSE;
  private $_confirmExternalEmails = TRUE;
  private $_checksum = '/usr/bin/sha256sum --binary';
  private $_clamdscan = '/usr/bin/clamdscan --fdpass';
  private $_maxnotelength = 1000;
  private $_maxsubjectlength = 100;
  private $_loginFailMax = 0;
  private $_loginFailTime = 0;
  private $_SMTPserver = '';
  private $_SMTPport = 25;
  private $_SMTPsecure = '';
  private $_SMTPusername = '';
  private $_SMTPpassword = '';
  private $_SMTPcharset = 'UTF-8';
  private $_SMTPdebug = FALSE;
  private $_SMTPsetFromToSender = FALSE;
  private $_automationUsers = '';
  private $_motdtitle = '';
  private $_motdtext  = '';
  private $_deleteRequestsAfterUse = TRUE;
  private $_requestOrgEditable = TRUE;
  private $_indexAddressbookByEmail = FALSE;
  private $_nightlySummaryEmailAddresses = array();
  private $_nightlySummaryContains = 'both';
  private $_uploadChunkSize = 50*1000*1000; // 50 MB
  private $_samlAttributesMap = array();
  private $_samlAuthSimple = NULL; // An object, not a prefs[] value
  private $_defaultLifetime = 0;
  private $_adminLoginsMustBeLocal = TRUE; // security by default
  private $_authAdmins = array();
  private $_authStats  = array();
  
  // private $_newDatabase = FALSE;
  public  $database = NULL; // JKF Handle to database, whatever type

  /*!
    @function __construct
    
    Class constructor.  Takes a hash array of preference fields as its
    only parameter and initializes the instance using those values.
    
    Also gets our backing-database open and creates the appropriate
    authenticator according to the preferences.

    If the optional parameter $dbOnly is true, then just the database
    is setup and nothing more than that. Useful for ancillary scripts.
  */
  public function __construct(
    $prefs, $dbOnly=FALSE
  )
  {
    global $NSSDROPBOX_URL;
    global $smarty;
    global $cookie_secure;

    if ( $prefs ) {
      if ( ! $this->checkPrefs($prefs) ) {
        NSSError(gettext("The preferences are not configured properly!"), gettext("Invalid Configuration"));
        exit(1);
      }
    
      // Instance copies of the preference data:
      // Need these before attempting to open database
      $this->_dropboxDirectory      = $prefs['dropboxDirectory'];
      $this->_captcha               = $prefs['captcha'];
      $this->_recaptchaPublicKey    = $prefs['recaptchaPublicKey'];
      $this->_recaptchaPrivateKey   = $prefs['recaptchaPrivateKey'];
      $this->_recaptchaLanguage     = $prefs['recaptchaLanguage'];
      $this->_recaptchaInvisible    = $prefs['recaptchaInvisible'];
      $this->_emailDomainRegexp     = $prefs['emailDomainRegexp'];
      $this->_dropboxLog            = $prefs['logFilePath'];
      $this->_cookieName            = $prefs['cookieName'];
      $this->_defaultEmailDomain    = $prefs['defaultEmailDomain'];
      $this->_usernameRegexp        = $prefs['usernameRegexp'];
      $this->_reqRecipient          = $prefs['requestTo'];
      $this->_localIPSubnets        = $prefs['localIPSubnets'];
      $this->_humanDownloads        = $prefs['humanDownloads'];
      $this->_libraryDirectory      = $prefs['libraryDirectory'];
      $this->_usingLibrary          = $prefs['usingLibrary'];
      $this->_bccSender             = $prefs['bccSender'];
      $this->_SMTPdebug             = $prefs['SMTPdebug'];
      $this->_SMTPsetFromToSender   = $prefs['SMTPsetFromToSender'];

      //  Get the database open:
      try {
        $db = new Sql($prefs, $this);
      } catch (RuntimeException $e) {
        NSSError(gettext("Could not create ZendTo database handle"),
                         "Internal Error");
        $this->database = NULL;
        return;
      }
      if ( ! $db || !($db->database) ) {
        NSSError(gettext("Could not create ZendTo database handle"),
                         "Internal Error");
        $this->database = NULL;
        return;
      }
      $this->database = $db;
      // Bail out right now if we just want a database connection.
      // Must do it here as we must have the dropboxLog setup right.
      if ($dbOnly) {
        return 0;
      }

      if ( ! ($this->_emailSenderAddr = $smarty->getConfigVars('EmailSenderAddress')) ) {
        $execAsUser = posix_getpwuid(posix_geteuid());
        $this->_emailSenderAddr = sprintf("%s <%s@%s>",
                                      $smarty->getConfigVars('ServiceTitle'),
                                      $execAsUser['name'],
                                      $_SERVER['SERVER_NAME']
                                     );
      }
      
      if ( $intValue = intval( $prefs['numberOfDaysToRetain'] ) ) {
        $this->_retainDays          = $intValue;
      }
      if ( isset($prefs['warnDaysBeforeDeletion']) ) {
        $intValue = intval( $prefs['warnDaysBeforeDeletion'] );
        $this->_warningDays         = $intValue;
      }
      if ( $prefs['cookieSecret'] ) {
        $this->_secretForCookies    = $prefs['cookieSecret'];
      }
      if ( $intValue = intval($prefs['cookieTTL']) ) {
        $this->_cookieTTL           = $intValue;
      }
      if ( $intValue = intval($prefs['requestTTL']) ) {
        $this->_requestTTL          = $intValue;
      }
      if ( $intValue = intval($prefs['maxBytesForFile']) ) {
        $this->_maxBytesForFile     = $intValue;
      }
      if ( $intValue = intval($prefs['maxBytesForDropoff']) ) {
        $this->_maxBytesForDropoff  = $intValue;
      }
      if ( $intValue = intval($prefs['maxPickupFailures']) ) {
        $this->_maxPickupFailures   = $intValue;
      }
      if ( isset($prefs['maxBytesForChecksum']) ) {
        $intValue = intval( $prefs['maxBytesForChecksum'] );
        $this->_maxBytesForChecksum = $intValue;
      }
      if ( isset($prefs['maxBytesForEncryption']) ) {
        $intValue = intval( $prefs['maxBytesForEncryption'] );
        $this->_maxBytesForEncrypt  = $intValue;
      }
      if ( isset($prefs['enforceEncryption']) ) {
        $this->_enforceEncrypt = $prefs['enforceEncryption'];
      }
      if ( isset($prefs['minPassphraseLength']) ) {
        $intValue = intval( $prefs['minPassphraseLength'] );
        $this->_minPassphraseLength  = $intValue;
      }
      if ( isset($prefs['languageList']) && is_array($prefs['languageList']) ) {
        $this->_languageList = $prefs['languageList'];
      }
      if ( isset($prefs['samlAttributesMap']) && is_array($prefs['samlAttributesMap']) ) {
        $this->_samlAttributesMap = $prefs['samlAttributesMap'];
      }
      if ( $prefs['validEmailRegexp'] ) {
        $this->_validEmailRegexp = $prefs['validEmailRegexp'];
      }
      if ( isset($prefs['hidePHP']) ) {
        $this->_hidePHP  = $prefs['hidePHP'];
      }
      if ( isset($prefs['allowExternalUploads']) ) {
        $this->_allowExternalUploads = $prefs['allowExternalUploads'];
      }
      if ( isset($prefs['allowExternalPickups']) ) {
        $this->_allowExternalPickups = $prefs['allowExternalPickups'];
      }
      if ( isset($prefs['allowExternalRecipients']) ) {
        $this->_allowExternalRecipients = $prefs['allowExternalRecipients'];
      }
      if ( isset($prefs['indexAddressbookByEmail']) ) {
        $this->_indexAddressbookByEmail = $prefs['indexAddressbookByEmail'];
      }
      if ( isset($prefs['requestSenderOrgIsEditable']) ) {
        $this->_requestOrgEditable = $prefs['requestSenderOrgIsEditable'];
      }
      if ( isset($prefs['showRecipsOnPickup']) ) {
        $this->_showRecipsOnPickup  = $prefs['showRecipsOnPickup'];
      }
      if ( isset($prefs['allowExternalLogins']) ) {
        $this->_allowExternalLogins = $prefs['allowExternalLogins'];
      }
      if ( isset($prefs['allowEmailRecipients']) ) {
        $this->_allowEmailRecipients = $prefs['allowEmailRecipients'];
      }
      if ( isset($prefs['allowEmailPasscode']) ) {
        $this->_allowEmailPasscode  = $prefs['allowEmailPasscode'];
      }
      if ( isset($prefs['defaultEmailPasscode']) ) {
        $this->_defaultEmailPasscode = $prefs['defaultEmailPasscode'];
      }
      if ( isset($prefs['defaultEmailRecipients']) ) {
        $this->_defaultEmailRecipients = $prefs['defaultEmailRecipients'];
      }
      if ( isset($prefs['defaultConfirmDelivery']) ) {
        $this->_defaultConfirmDelivery = $prefs['defaultConfirmDelivery'];
      }
      if ( isset($prefs['defaultEncryptRequests']) ) {
        $this->_defaultEncryptRequests = $prefs['defaultEncryptRequests'];
      }
      if ( isset($prefs['showEncryptionCheckbox']) ) {
        $this->_showEncryption = $prefs['showEncryptionCheckbox'];
      }
      if ( isset($prefs['showChecksumCheckbox']) ) {
        $this->_showChecksum = $prefs['showChecksumCheckbox'];
      }
      if ( isset($prefs['showConfirmDeliveryCheckbox']) ) {
        $this->_showConfirmDelivery = $prefs['showConfirmDeliveryCheckbox'];
      }
      if ( isset($prefs['showEmailRecipientsCheckbox']) ) {
        $this->_showEmailRecipients = $prefs['showEmailRecipientsCheckbox'];
      }
      if ( isset($prefs['showEmailPasscodeCheckbox']) ) {
        $this->_showPasscode = $prefs['showEmailPasscodeCheckbox'];
      }
      if ( isset($prefs['showRecipientsWaiverCheckbox']) ) {
        $this->_showRecipWaiver = $prefs['showRecipientsWaiverCheckbox'];
      }
      if ( isset($prefs['defaultRecipientsWaiver']) ) {
        $this->_defaultRecipWaiver = $prefs['defaultRecipientsWaiver'];
      }

      if ( isset($prefs['cookieGDPRConsent']) ) {
        $this->_cookieGDPRConsent   = $prefs['cookieGDPRConsent'];
      }
      if ( isset($prefs['confirmExternalEmails']) ) {
        $this->_confirmExternalEmails = $prefs['confirmExternalEmails'];
      }
      if ( isset($prefs['checksum']) ) {
        $this->_checksum            = $prefs['checksum'];
      }
      if ( isset($prefs['clamdscan']) ) {
        $this->_clamdscan           = $prefs['clamdscan'];
      }
      if ( isset($prefs['maxNoteLength']) ) {
        $this->_maxnotelength       = $prefs['maxNoteLength'];
      }
      if ( isset($prefs['maxSubjectLength']) ) {
        $this->_maxsubjectlength    = $prefs['maxSubjectLength'];
      }
      if ( isset($prefs['loginFailMax']) ) {
        $this->_loginFailMax        = $prefs['loginFailMax'];
      }
      if ( isset($prefs['loginFailTime']) ) {
        $this->_loginFailTime       = $prefs['loginFailTime'];
      }
      if ( isset($prefs['SMTPserver']) ) {
        $this->_SMTPserver          = $prefs['SMTPserver'];
      }
      if ( isset($prefs['SMTPport']) ) {
        $this->_SMTPport            = $prefs['SMTPport'];
      }
      if ( isset($prefs['SMTPsecure']) ) {
        $this->_SMTPsecure          = $prefs['SMTPsecure'];
      }
      if ( isset($prefs['SMTPusername']) ) {
        $this->_SMTPusername        = $prefs['SMTPusername'];
      }
      if ( isset($prefs['SMTPpassword']) ) {
        $this->_SMTPpassword        = $prefs['SMTPpassword'];
      }
      if ( isset($prefs['SMTPcharset']) ) {
        $this->_SMTPcharset         = $prefs['SMTPcharset'];
      }
      if ( isset($prefs['bccExternalSender']) ) {
        $this->_bccExternalSender   = $prefs['bccExternalSender'];
      }
      if ( isset($prefs['skipSenderInfo']) ) {
        $this->_skipSenderInfo      = $prefs['skipSenderInfo'];
      }
      if ( isset($prefs['emailSenderIP']) ) {
        $this->_emailSenderIP       = $prefs['emailSenderIP'];
      }
      if ( isset($prefs['advertisedServerRoot']) ) {
        $this->_advertisedServerRoot = $prefs['advertisedServerRoot'];
      }
      if ( isset($prefs['deleteRequestsAfterUse']) ) {
        $this->_deleteRequestsAfterUse = $prefs['deleteRequestsAfterUse'];
      }
      if ( isset($prefs['automationUsers']) ) {
        $this->_automationUsers     = $prefs['automationUsers'];
      }
      if ( isset($prefs['nightlySummaryEmailAddresses']) ) {
        $this->_nightlySummaryEmailAddresses =
          (array) $prefs['nightlySummaryEmailAddresses'];
      }
      if ( isset($prefs['nightlySummaryContains']) ) {
        $this->_nightlySummaryContains = $prefs['nightlySummaryContains'];
      }
      if ( isset($prefs['uploadChunkSize']) ) {
        $this->_uploadChunkSize = intval($prefs['uploadChunkSize']);
      }
      if ( isset($prefs['defaultNumberOfDaysToRetain']) ) {
        $l = intval($prefs['defaultNumberOfDaysToRetain']);
        $l = max(0, $l);
        $l = min($l, $this->_retainDays);
        $this->_defaultLifetime = $l;
      }
      if ( isset($prefs['adminLoginsMustBeLocal'])) {
        $this->_adminLoginsMustBeLocal = $prefs['adminLoginsMustBeLocal'];
      }
      if ( isset($prefs['authAdmins']) ) {
        $this->_authAdmins = (array)$prefs['authAdmins'];
      }
      if ( isset($prefs['authStats']) ) {
        $this->_authStats = (array)$prefs['authStats'];
      }
      if ( isset($prefs['systemAnnouncementFilePath']) ) {
        $motdname = $prefs['systemAnnouncementFilePath'];
        if (@is_readable($motdname) && @filesize($motdname)>1) {
          // If the motd is coming from a URL, set 2 seconds max timeout
          $context = stream_context_create(array(
                       "http" => array( "timeout" => 2 ) ) );
          // Put the 1st line into _motdtitle and the rest into _motdtext.
          // Unless there's only 1 line so no title, then use default title.
          $motdlines = array();
          $motdlines = @file($motdname, FALSE, $context);
          if ($motdlines !== FALSE) {
            if (count($motdlines)>1) {
              $this->_motdtitle = trim(reset($motdlines));
              unset($motdlines[0]);
              $this->_motdtext  = trim(implode($motdlines));
              // The 2nd line onwards were blank
              if ($this->_motdtext == '') {
                $this->_motdtext = $this->_motdtitle;
                $this->_motdtitle = gettext('Please note');
              }
            } else {
              $this->_motdtitle = gettext('Please note');
              $this->_motdtext  = trim(implode($motdlines));
            }
          } else {
            $this->_motdtitle = '';
            $this->_motdtext  = '';
          }
          #$this->_motd = @file_get_contents($motdname, FALSE, $context,
          #                                  0, $this->_maxnotelength);
          #$this->_motd = trim($this->_motd);
        }
      }
      
      // Set the domain for our only permanent cookie
      // Remove http....://
      $cookieDomain = preg_replace('/^[^:]*:\/*/', '', $NSSDROPBOX_URL);
      // Remove everything after the / which should be at the end of hostname
      $cookieDomain = preg_replace('/\/.*$/', '', $cookieDomain);
      $smarty->assign('cookieDomain', $cookieDomain);
      
      //  Create an authenticator based on our prefs:
      $this->_authenticatorName = $prefs['authenticator'];
      $this->_authenticator = NSSAuthenticator($prefs, $this->database, $this);
      
      if ( ! $this->_authenticator ) {
        NSSError(gettext("The ZendTo preferences.php has no authentication method selected."), gettext("Authentication Error"));
        exit(1);
      }

      //  First try an authentication, since it _could_ override a cookie
      //  that was already set.  If that doesn't work, then try the cookie:
      if ( $this->userFromAuthentication() ) {
        
        //$this->writeToLog("Info: user authentication verified user as '".$this->_authorizedUser."'");
        
        //  Set the cookie now:
        setcookie(
            $this->_cookieName,
            $this->cookieForSession(),
            time() + $this->_cookieTTL,
            "/",
            "",
            $cookie_secure,
            TRUE
          );
      } else {
        if ( $this->userFromCookie() ) {
          //  Update the cookie's time-to-live:
          setcookie(
              $this->_cookieName,
              $this->cookieForSession(),
              time() + $this->_cookieTTL,
              "/",
              "",
              $cookie_secure,
              TRUE
            );
        }
      }
    } else {
      NSSError(gettext("The preferences are not configured properly (they're empty)!"), gettext("Invalid Configuration"));
      exit(1);
    }
  }
  
  // Is this actually an automated call to ZendTo?
  //
  // If the user is one of the special automation users,
  // then return TRUE.
  public function isAutomated() {
    $autousers = $this->_automationUsers;
    // Allow the prefs setting to be an array or a string.
    if (is_array($autousers)) {
      return in_array($this->_authorizedUser, $autousers, TRUE);
    } else {
      return ($this->_authorizedUser === $autousers);
    }
  }

  // Are we using SAML authentication?
  public function usingSaml() {
    return ( $this->_authenticatorName === 'SAML' );
  }
  public function samlAuthSimple() {
    return $this->_samlAuthSimple;
  }

  /*!
    @function description
    
    Debugging too, for the most part.  Give a description of the
    instance.
  */
  public function description()
  {
    return sprintf("NSSDropbox {
  directory:          %s
  log:                %s
  retainDays:         %d
  warningDays:        %d
  recaptchaPublicKey: %s
  recaptchaPrivateKey:%s
  emailDomainRegexp:  %s
  secretForCookies:   %s
  authorizedUser:     %s
  authenticator:      %s
}",
                $this->_dropboxDirectory,
                $this->_dropboxLog,
                $this->_retainDays,
                $this->_warningDays,
                $this->_recaptchaPublicKey,
                $this->_recaptchaPrivateKey,
                $this->_emailDomainRegexp,
                $this->_secretForCookies,
                $this->_authorizedUser,
                ( $this->_authenticator ? $this->_authenticator->description() : "<no authenticator>" )
          );
  
  }

  /*!
    @function logout
    
    Logout the current user.  This amounts to nulling our cookie and giving it a zero
    second time-to-live, which should force the browser to drop the cookie.
  */
  public function logout()
  {
    global $cookie_secure;

    $this->writeToLog("Info: logged out user '".$this->_authorizedUser."'");
    setcookie(
        $this->_cookieName,
        "",
        time() - 3600*24*365, // 1 year in the past was 0,
        "/",
        "",
        $cookie_secure,
        TRUE
      );
    $this->_authorizedUser = NULL;
    $this->_authorizedUserData = NULL;
  }

/*!
  Construct the array of filenames mapped to descriptions.
*/
public function getLibraryDescs()
{
  // Read the list of descriptions we know about
  $name2desc = $this->database->DBGetLibraryDescs();

  // Read the list of filenames from the default library directory.
  // This will be overwritten with the user-specific set if they have
  // a library directory of their own
  $files = array();

  $here = $this->_libraryDirectory;
  $where = ''; // Relative subdirectory to put in "where" element
  if (is_dir($here.'/'.$this->_authorizedUser)) {
    $here = $here . '/' . $this->_authorizedUser;
    $where = $this->_authorizedUser . '/';
  }
  $dir = opendir($here);
  // No library directory? No worries, just tell them it's empty
  if (!$dir) {
    return '[]';
  }
  while (($file = readdir($dir)) !== false) {
    // Ignore filenames starting with '.'
    if (! preg_match('/^\./', $file) && ! is_dir($here.'/'.$file)) {
      $files[] = $file;
    }
  }
  closedir($dir);

  // strcasecmp is case-insensitive
  usort($files, function ($a, $b) { return strcasecmp($a, $b); });

  $results = array();
  $a = 0;
  foreach ($files as $file) {
    $results[$a] = array();
    $results[$a]['filename'] = $file;
    $results[$a]['where'] = $where . $file; # Relative path
    if (array_key_exists($file, $name2desc) && $name2desc[$file]) {
      // Slot in the file's description, if present
      $results[$a]['description'] = $name2desc[$file];
    } else {
      $results[$a]['description'] = '';
    }
    $a++;
  }
  // This returns the string "[]" if there are no files.
  return json_encode($results); // Seb wants it in JSON
}

// Construct a JSON version of the list of available locales,
// sorted by name.
public function getLocaleList() {
  if (!is_array($this->_languageList))
    return '[]';

  $results = array();
  $a = 0;
  foreach ($this->_languageList as $langString) {
    if ($langString === '') continue;
    $words = preg_split('/\s+/', $langString, 2);
    $results[$a]['locale'] = $words[0];
    $results[$a]['name']   = $words[1];
    $a++;
  }
  return json_encode($results);
}



public function getAddressbook() {
  if ($this->_authorizedUser) {
    // A few sites using things like Yubikeys have users login with a
    // random username, so mustn't index anything by username for these
    // sites, but email address instead. But changing this everywhere
    // would break/zero all existing address books, so have to add a
    // config option to set it. False by default.
    if ($this->_indexAddressbookByEmail) {
      return json_encode($this->database->DBGetAddressbook($this->authorizedUserData('mail')));
    } else {
      return json_encode($this->database->DBGetAddressbook($this->_authorizedUser));
    }
  } else {
    return '[]';
  }
}

public function updateAddressbook( $recips ) {
  if ($this->_authorizedUser) {
    if ($this->_indexAddressbookByEmail) {
      return $this->database->DBUpdateAddressbook($this->authorizedUserData('mail'), $recips);
    } else {
      return $this->database->DBUpdateAddressbook($this->_authorizedUser, $recips);
    }
  }
}

public function deleteAddressbookEntry ( $name, $email ) {
  if ($this->_authorizedUser) {
    if ($this->_indexAddressbookByEmail) {
      return $this->database->DBDeleteAddressbookEntry($this->authorizedUserData('mail'), $name, $email);
    } else {
      return $this->database->DBDeleteAddressbookEntry($this->_authorizedUser, $name, $email);
    }
  } else {
    return FALSE;
  }
}


  /*!
    @function libraryDirectory
    
    Accessor pair for getting/setting the directory where library files are
    stored.  Always use a canonical path -- and, of course, be sure your
    web server is allowed to read from it!!
  */
  public function libraryDirectory() { return $this->_libraryDirectory; }
  public function setLibraryDirectory(
    $libraryDirectory
  )
  {
    if ( $libraryDirectory && $libraryDirectory != $this->_libraryDirectory && is_dir($libraryDirectory) ) {
      $this->_libraryDirectory = $libraryDirectory;
    }
  }
  
  /*!
    @function dropboxDirectory
    
    Accessor pair for getting/setting the directory where dropoffs are
    stored.  Always use a canonical path -- and, of course, be sure your
    web server is allowed to write to it!!
  */
  public function dropboxDirectory() { return $this->_dropboxDirectory; }
  public function setDropBoxDirectory(
    $dropboxDirectory
  )
  {
    if ( $dropboxDirectory && $dropboxDirectory != $this->_dropboxDirectory && is_dir($dropboxDirectory) ) {
      $this->_dropboxDirectory = $dropboxDirectory;
    }
  }
  
  /*!
    @function dropboxLog
    
    Accessor pair for getting/setting the path to the log file for this
    dropbox.  Make sure your web server has access privs on the file
    (or the enclosing directory, in which case the file will get created
    automatically the first time we log to it).
  */
  public function dropboxLog() { return $this->_dropboxLog; }
  public function setDropBoxLog(
    $dropboxLog
  )
  {
    if ( $dropboxLog && $dropboxLog != $this->_dropboxLog ) {
      $this->_dropboxLog = $dropboxLog;
    }
  }
  
  /*!
    @function requestTTL
    
    Accessor pair for getting/setting the number of seconds that a request
    token is valid for.
  */
  public function requestTTL() { return $this->_requestTTL; }
  public function setRequestTTL(
    $requestTTL
  )
  {
    if ( intval($requestTTL) > 0 && intval($requestTTL) != $this->_requestTTL ) {
      $this->_requestTTL = intval($requestTTL);
    }
  }

  /*!
    @function retainDays
    
    Accessor pair for getting/setting the number of days that a dropoff
    is allowed to reside in the dropbox.  The "cleanup.php" admin script
    actually removes them, we don't do it from the web interface.
  */
  public function retainDays() { return $this->_retainDays; }
  public function setRetainDays(
    $retainDays
  )
  {
    if ( intval($retainDays) > 0 && intval($retainDays) != $this->_retainDays ) {
      $this->_retainDays = intval($retainDays);
    }
  }

  /*!
    @function warningDays
    
    Accessor pair for getting/setting the number of days before a dropoff
    is deleted (see retainDays()) when the sender will be warned that their
    dropoff is about to be deleted, if there have been no pickups of it.
    The "cleanup.php" admin script uses this.
  */
  public function warningDays() { return $this->_warningDays; }
  public function setWarningDays(
    $warningDays
  )
  {
    if ( intval($warningDays) > 0 && intval($warningDays) != $this->_warningDays ) {
      $this->_warningDays = intval($warningDays);
    }
  }

  /*!
    @function maxBytesForFile
    
    Accessor pair for getting/setting the maximum size (in bytes) of a single
    file that is part of a dropoff.  Note that there is a PHP system parameter
    that you must be sure is set high-enough to accomodate what you select
    herein!
  */
  public function maxBytesForFile() { return $this->_maxBytesForFile; }
  public function setMaxBytesForFile(
    $maxBytesForFile
  )
  {
    if ( ($intValue = intval($maxBytesForFile)) > 0 ) {
      $this->_maxBytesForFile = $intValue;
    }
  }

  /*!
    @function maxBytesForDropoff
    
    Accessor pair for getting/setting the maximum size (in bytes) of a dropoff
    (all files summed).  Note that there is a PHP system parameter that you must
    be sure is set high-enough to accomodate what you select herein!
  */
  public function maxBytesForDropoff() { return $this->_maxBytesForDropoff; }
  public function setMaxBytesForDropoff(
    $maxBytesForDropoff
  )
  {
    if ( ($intValue = intval($maxBytesForDropoff)) > 0 ) {
      $this->_maxBytesForDropoff = $intValue;
    }
  }

  /*!
    @function maxPickupFailures
    
    Accessor pair for getting/setting the maximum number of failed pickups
    for a drop-off before it is automatically deleted.
    Setting it to 0 disables the feature.
  */
  public function maxPickupFailures() { return $this->_maxPickupFailures; }
  public function setMaxPickupFailures(
    $maxPickupFailures
  )
  {
    if ( ($intValue = intval($maxPickupFailures)) >= 0 ) {
      $this->_maxPickupFailures = $intValue;
    }
  }

  /*!
    @function maxBytesForChecksum
    
    Accessor pair for getting/setting the maximum size of a drop-off
    whose files will be checksummed after upload.
    Setting it to 0 disables the feature.
  */
  public function maxBytesForChecksum() { return $this->_maxBytesForChecksum; }
  public function setmaxBytesForChecksum(
    $maxBytesForChecksum
  )
  {
    if ( ($intValue = intval($maxBytesForChecksum)) >= 0 ) {
      $this->_maxBytesForChecksum = $intValue;
    }
  }

  /*!
    @function maxBytesForEncrypt
    
    Accessor pair for getting/setting the maximum size of a drop-off
    whose files will be checksummed after upload.
    Setting it to 0 disables the feature.
  */
  public function maxBytesForEncrypt() { return $this->_maxBytesForEncrypt; }
  public function setmaxBytesForEncrypt(
    $maxBytesForEncrypt
  )
  {
    if ( ($intValue = intval($maxBytesForEncrypt)) >= 0 ) {
      $this->_maxBytesForEncrypt = $intValue;
    }
  }

  /*!
    @function enforceEncrypt
    
    Accessor pair for getting/setting the maximum size of a drop-off
    whose files will be checksummed after upload.
    Setting it to 0 disables the feature.
  */
  public function enforceEncrypt() { return $this->_enforceEncrypt; }
  public function setenforceEncrypt(
    $enforceEncrypt
  )
  {
    $this->_enforceEncrypt = $enforceEncrypt;
  }

  /*!
    @function minPassphraseLength
    
    Accessor pair for getting/setting the maximum size of a drop-off
    whose files will be checksummed after upload.
    Setting it to 0 disables the feature.
  */
  public function minPassphraseLength() { return $this->_minPassphraseLength; }
  public function setminPassphraseLength(
    $minPassphraseLength
  )
  {
    if ( ($intValue = intval($minPassphraseLength)) >= 0 ) {
      $this->_minPassphraseLength = $intValue;
    }
  }

  /*!
    @function validEmailRegexp
    
    Accessor pair for getting/setting the regexp that defines a valid
    sender email address.
  */
  public function validEmailRegexp() { return $this->_validEmailRegexp; }
  public function setvalidEmailRegexp(
    $validEmailRegexp
  )
  {
    if ( $validEmailRegexp && $validEmailRegexp != $this->_validEmailRegexp ) {
      $this->_validEmailRegexp = $validEmailRegexp;
    }
  }

  /*!
    @function languageList
    
    Accessor pair for getting/setting the regexp that defines a valid
    sender email address.
  */
  public function languageList() { return $this->_languageList; }
  public function setlanguageList(
    $languageList
  )
  {
    if ( is_array($languageList) ) {
      $this->_languageList = $languageList;
    }
  }

  public function allowExternalLogins() { return $this->_allowExternalLogins; }
  public function setallowExternalLogins(
    $allowExternalLogins
  )
  {
    $this->_allowExternalLogins = $allowExternalLogins;
  }

  public function allowEmailRecipients() { return $this->_allowEmailRecipients; }
  public function setallowEmailRecipients(
    $allowEmailRecipients
  )
  {
    $this->_allowEmailRecipients = $allowEmailRecipients;
  }

  public function allowEmailPasscode() { return $this->_allowEmailPasscode; }
  public function setallowEmailPasscode(
    $allowEmailPasscode
  )
  {
    $this->_allowEmailPasscode = $allowEmailPasscode;
  }

  public function defaultEmailPasscode() { return $this->_defaultEmailPasscode; }
  public function setdefaultEmailPasscode(
    $defaultEmailPasscode
  )
  {
    $this->_defaultEmailPasscode = $defaultEmailPasscode;
  }

  public function defaultEmailRecipients() { return $this->_defaultEmailRecipients; }
  public function setdefaultEmailRecipients(
    $defaultEmailRecipients
  )
  {
    $this->_defaultEmailRecipients = $defaultEmailRecipients;
  }

  public function defaultConfirmDelivery() { return $this->_defaultConfirmDelivery; }
  public function setdefaultConfirmDelivery(
    $defaultConfirmDelivery
  )
  {
    $this->_defaultConfirmDelivery = $defaultConfirmDelivery;
  }

  public function defaultEncryptRequests() { return $this->_defaultEncryptRequests; }
  public function setdefaultEncryptRequests(
    $defaultEncryptRequests
  )
  {
    $this->_defaultEncryptRequests = $defaultEncryptRequests;
  }

  public function showEncryption() { return $this->_showEncryption; }
  public function setshowEncryption(
    $showEncryption
  )
  {
    $this->_showEncryption = $showEncryption;
  }

  public function showChecksum() { return $this->_showChecksum; }
  public function setshowChecksum(
    $showChecksum
  )
  {
    $this->_showChecksum = $showChecksum;
  }
    
  public function showConfirmDelivery() { return $this->_showConfirmDelivery; }
  public function setshowConfirmDelivery(
    $showConfirmDelivery
  )
  {
    $this->_showConfirmDelivery = $showConfirmDelivery;
  }
    
  public function showEmailRecipients() { return $this->_showEmailRecipients; }
  public function setshowEmailRecipients(
    $showEmailRecipients
  )
  {
    $this->_showEmailRecipients = $showEmailRecipients;
  }
    
  public function showPasscode() { return $this->_showPasscode; }
  public function setshowPasscode(
    $showPasscode
  )
  {
    $this->_showPasscode = $showPasscode;
  }

  public function showRecipWaiver() { return $this->_showRecipWaiver; }
  public function setshowRecipWaiver(
    $showRecipWaiver
  )
  {
    $this->_showRecipWaiver = $showRecipWaiver;
  }

  public function defaultRecipWaiver() { return $this->_defaultRecipWaiver; }
  public function setdefaultRecipWaiver(
    $defaultRecipWaiver
  )
  {
    $this->_defaultRecipWaiver = $defaultRecipWaiver;
  }


  public function emailSenderIP() { return $this->_emailSenderIP; }
  public function setemailSenderIP(
    $emailSenderIP
  )
  {
    $this->_emailSenderIP = $emailSenderIP;
  }
    
  public function bccSender() { return $this->_bccSender; }
  public function setbccSender(
    $bccSender
  )
  {
    $this->_bccSender = $bccSender;
  }
    
  public function hidePHP() { return $this->_hidePHP; }
  public function sethidePHP(
    $hidePHP
  )
  {
    $this->_hidePHP = $hidePHP;
  }
    
  public function skipSenderInfo() { return $this->_skipSenderInfo; }
  public function setskipSenderInfo(
    $skipSenderInfo
  )
  {
    $this->_skipSenderInfo = $skipSenderInfo;
  }
    
  public function bccExternalSender() { return $this->_bccExternalSender; }
  public function setbccExternalSender(
    $bccExternalSender
  )
  {
    $this->_bccExternalSender = $bccExternalSender;
  }

  public function allowExternalUploads() { return $this->_allowExternalUploads; }
  public function setallowExternalUploads(
    $allowExternalUploads
  )
  {
    $this->_allowExternalUploads = $allowExternalUploads;
  }
    
  public function allowExternalPickups() { return $this->_allowExternalPickups; }
  public function setallowExternalPickups(
    $allowExternalPickups
  )
  {
    $this->_allowExternalPickups = $allowExternalPickups;
  }
    
  public function allowExternalRecipients() { return $this->_allowExternalRecipients; }
  public function setallowExternalRecipients(
    $allowExternalRecipients
  )
  {
    $this->_allowExternalRecipients = $allowExternalRecipients;
  }
    
    public function clamdscan() { return $this->_clamdscan; }
  public function setclamdscan(
    $clamdscan
  )
  {
    $this->_clamdscan = $clamdscan;
  }
    
  public function checksum() { return $this->_checksum; }
  public function setchecksum(
    $checksum
  )
  {
    $this->_checksum = $checksum;
  }
    
  public function maxnotelength() { return $this->_maxnotelength; }
  public function setmaxnotelength(
    $maxnotelength
  )
  {
    $this->_maxnotelength = $maxnotelength;
  }
    
  public function usingLibrary() { return $this->_usingLibrary; }
  public function setusingLibrary(
    $usingLibrary
  )
  {
    $this->_usingLibrary = $usingLibrary;
  }
    
  public function maxsubjectlength() { return $this->_maxsubjectlength; }
  public function setmaxsubjectlength(
    $maxsubjectlength
  )
  {
    $this->_maxsubjectlength = $maxsubjectlength;
  }
    
  public function advertisedServerRoot() { return $this->_advertisedServerRoot; }
  public function setadvertisedServerRoot(
    $advertisedServerRoot
  )
  {
    $this->_advertisedServerRoot = $advertisedServerRoot;
  }
    
  public function cookieName() { return $this->_cookieName; }
  public function setcookieName(
    $cookieName
  )
  {
    $this->_cookieName = $cookieName;
  }
    
  public function recaptchaPublicKey() { return $this->_recaptchaPublicKey; }
  public function setrecaptchaPublicKey(
    $recaptchaPublicKey
  )
  {
    if ( $recaptchaPublicKey && $recaptchaPublicKey != $this->_recaptchaPublicKey ) {
      $this->_recaptchaPublicKey = $recaptchaPublicKey;
    }
  }

  public function recaptchaPrivateKey() { return $this->_recaptchaPrivateKey; }
  public function setrecaptchaPrivateKey(
    $recaptchaPrivateKey
  )
  {
    if ( $recaptchaPrivateKey && $recaptchaPrivateKey != $this->_recaptchaPrivateKey ) {
      $this->_recaptchaPrivateKey = $recaptchaPrivateKey;
    }
  }

  public function recaptchaLanguage() { return $this->_recaptchaLanguage; }
  public function setrecaptchaLanguage(
    $recaptchaLanguage
  )
  {
    if ( $recaptchaLanguage && $recaptchaLanguage != $this->_recaptchaLanguage ) {
      $this->_recaptchaLanguage = $recaptchaLanguage;
    }
  }

  public function recaptchaInvisible() { return $this->_recaptchaInvisible; }
  public function setrecaptchaInvisible(
    $recaptchaInvisible
  )
  {
    if ( isset($recaptchaInvisible) && $recaptchaInvisible != $this->_recaptchaInvisible ) {
      $this->_recaptchaInvisible = $recaptchaInvisible;
    }
  }

  public function cookieGDPRConsent() { return $this->_cookieGDPRConsent; }
  public function setcookieGDPRConsent(
    $cookieGDPRConsent
  )
  {
    if ( isset($cookieGDPRConsent) && $cookieGDPRConsent != $this->_cookieGDPRConsent ) {
      $this->_cookieGDPRConsent = $cookieGDPRConsent;
    }
  }

  public function confirmExternalEmails() { return $this->_confirmExternalEmails; }
  public function setconfirmExternalEmails(
    $confirmExternalEmails
  )
  {
    if ( isset($confirmExternalEmails) && $confirmExternalEmails != $this->_confirmExternalEmails ) {
      $this->_confirmExternalEmails = $confirmExternalEmails;
    }
  }

  public function emailDomainRegexp() { return $this->_emailDomainRegexp; }
  public function setemailDomainRegexp(
    $emailDomainRegexp
  )
  {
    if ( $emailDomainRegexp && $emailDomainRegexp != $this->_emailDomainRegexp )
    {
      $this->_emailDomainRegexp = $emailDomainRegexp;
    }
  }

  public function defaultEmailDomain() { return $this->_defaultEmailDomain; }
  public function setdefaultEmailDomain(
    $defaultEmailDomain
  )
  {
    if ( $defaultEmailDomain && $defaultEmailDomain != $this->_defaultEmailDomain ) {
      $this->_defaultEmailDomain = $defaultEmailDomain;
    }
  }

  public function usernameRegexp() { return $this->_usernameRegexp; }
  public function setusernameRegexp(
    $usernameRegexp
  )
  {
    if ( $usernameRegexp && $usernameRegexp != $this->_usernameRegexp ) {
      $this->_usernameRegexp = $usernameRegexp;
    }
  }

  public function localIPSubnets() { return $this->_localIPSubnets; }
  public function setlocalIPSubnets(
    $localIPSubnets
  )
  {
    $this->_localIPSubnets = $localIPSubnets;
  }

  public function humanDownloads() { return $this->_humanDownloads; }
  public function sethumanDownloads(
    $humanDownloads
  )
  {
    $this->_humanDownloads = $humanDownloads;
  }

  public function requestOrgEditable() { return $this->_requestOrgEditable; }
  public function setrequestOrgEditable(
    $requestOrgEditable
  )
  {
    $this->_requestOrgEditable = $requestOrgEditable;
  }

  public function reqRecipient() { return $this->_reqRecipient; }
  public function setreqRecipient(
    $reqRecipient
  )
  {
    $this->_reqRecipient = $reqRecipient;
  }

  public function contactHelp() { return $this->_contactHelp; }
  public function setcontactHelp(
    $contactHelp
  )
  {
    if ( $contactHelp && $contactHelp != $this->_contactHelp ) {
      $this->_contactHelp = $contactHelp;
    }
  }

  public function secretForCookies() { return $this->_secretForCookies; }
  public function setSecretForCookies(
    $secretForCookies
  )
  {
    if ( $secretForCookies && $secretForCookies != $this->_secretForCookies ) {
      $this->_secretForCookies = $secretForCookies;
    }
  }

  public function deleteRequestsAfterUse() { return $this->_deleteRequestsAfterUse; }
  public function setdeleteRequestsAfterUse(
    $deleteRequestsAfterUse
  )
  {
    $this->_deleteRequestsAfterUse = $deleteRequestsAfterUse;
  }

  public function motdtitle() { return $this->_motdtitle; }
  public function motdtext() { return $this->_motdtext; }

  public function nightlySummaryEmailAddresses() {
    return $this->_nightlySummaryEmailAddresses;
  }

  public function nightlySummaryContains() {
    return $this->_nightlySummaryContains;
  }

  public function uploadChunkSize() {
    return $this->_uploadChunkSize;
  }

  public function defaultLifetime() {
    return ($this->_defaultLifetime>0)?$this->_defaultLifetime:$this->_retainDays;
  }
  public function defaultNumberOfDaysToRetain() {
    return $this->_defaultLifetime;
  }

  public function adminLoginsMustBeLocal() {
    return $this->_adminLoginsMustBeLocal;
  }

  public function authAdmins() {
    return $this->_authAdmins;
  }

  public function authStats() {
    return $this->_authStats;
  }

  /*!
    @function loginFailMax
    
    Returns the value of _loginFailMax.
  */
  public function loginFailMax() { return $this->_loginFailMax; }
  
  public function captcha() { return $this->_captcha; }

  /*!
    @function loginFailTime
    
    Returns the value of _loginFailTime.
  */
  public function loginFailTime() { return $this->_loginFailTime; }
  
  /*!
    @function isNewDatabase
    
    Returns TRUE if the backing-database was newly-created by this instance.
  */
  public function isNewDatabase() { return $this->_newDatabase; }
  
  /*!
    @function database
    
    Returns a reference to the database object (class is SQLiteDatabase)
    backing this dropbox.
  */
  public function &database() { return $this->database; }
  
  /*!
    @function authorizedUser
    
    If the instance was created and was able to associate with a valid user
    (either via cookie or explicit authentication) the username in question
    is returned.
  */
  public function authorizedUser() { return $this->_authorizedUser; }

  /*!
    @function authorizedUserData
    
    If the instance was created and was able to associate with a valid user
    (either via cookie or explicit authentication) then this function returns
    either the entire hash of user information (if $field is NULL) or a
    particular value from the hash of user information.  For example, you
    could grab the user's email address using:
    
      $userEmail = $aDropbox->authorizedUserData('mail');
      
    If the field you request does not exist, NULL is returned.  Note that
    as the origin of this data is probably an LDAP lookup, there _may_ be
    arrays involved if a given field has multiple values.
  */
  public function authorizedUserData(
    $field = NULL
  )
  {
    if ( $field ) {
      return @$this->_authorizedUserData[$field];
    }
    return $this->_authorizedUserData;
  }
  
  public function showRecipsOnPickup() { return $this->_showRecipsOnPickup; }
  public function setShowRecipsOnPickup(
    $showIt
  )
  {
    $this->_showRecipsOnPickup = $showIt;
  }

  /*!
    @function directoryForDropoff
    
    If $claimID enters with a value already assigned, then this function attempts
    to find the on-disk directory which contains that dropoff's files; the directory
    is returned in the $claimDir variable-reference.
    
    If $claimID is NULL, then we're being requested to setup a new dropoff.  So we
    pick a new claim ID, make sure it doesn't exist, and then create the directory.
    The new claim ID goes back in $claimID and the directory goes back to the caller
    in $claimDir.
    
    Returns TRUE on success, FALSE on failure.
  */
  public function directoryForDropoff(
    &$claimID = NULL,
    &$claimDir = NULL
  )
  {
    if ( $claimID ) {
      if ( is_dir($this->_dropboxDirectory."/$claimID") ) {
        $claimDir = $this->_dropboxDirectory."/$claimID";
        return TRUE;
      }
    } else {
      while ( 1 ) {
        $claimID = NSSGenerateCode();
        //  Is it already in the database?
        $this->database->DBListClaims($claimID, $extant);
        if ( !$extant || (count($extant) == 0) ) {
          //  Make sure there's no directory hanging around:
          if ( ! file_exists($this->_dropboxDirectory."/$claimID") ) {
            if ( mkdir($this->_dropboxDirectory."/$claimID",0700) ) {
              $claimDir = $this->_dropboxDirectory."/$claimID";
              return TRUE;
            }
            $this->writeToLog("Error: unable to create directory for new drop-off ".$this->_dropboxDirectory."/$claimID");
            break;
          }
        }
      }
    }
    return FALSE;
  }
  
  /*!
    @function authenticator
    
    Returns the authenticator object (subclass of NSSAuthenticator) that was created
    when we were initialized.
  */
  public function authenticator() { return $this->_authenticator; }

  /*!
    @function deliverEmail
    
    Send the $content of an email message to (one or more) address(es) in
    $toAddr.
    $toAddr is now an array.
  */
  public function deliverEmail(
    $toAddr,
    $fromAddr,
    $fromName,
    $subject,
    $textcontent,
    $htmlcontent = ''
  )
  {
    $mail = 0;
    // If they have set an SMTP server, use PHPMailer.
    // Otherwise, use mail().
    if ($this->_SMTPserver !== '') {
     try {
      $mail = new PHPMailer;
      // Need to work out the From: name and email
      // $fromAddr can be 1 of
      // 1 Empty
      // 2 Email address user@domain
      // 3 Name and email address John Smith <user@domain>
      $fromA = '';
      $fromN = '';
      if ($fromAddr === '') {
        // 1. It is empty
        $fromA = '';
        $fromN = '';
      } else if (strpos($fromAddr, ' ') === FALSE) {
        // 2. No space, so email address only
        $fromA = $fromAddr;
        // If we have a fromName, use it.
        $fromN = $fromName;
      } else {
        // 3. Has a space, so is both parts
        $tt = array();
        $tt = $mail->parseAddresses($fromAddr);
        $fromA = $tt[0]['address'];
        $fromN = $tt[0]['name'];
      }


      $mail->SMTPDebug = $this->_SMTPdebug?2:0;
      $mail->isSMTP();
      $mail->Host = $this->_SMTPserver;
      $mail->Port = $this->_SMTPport;
      $mail->Debugoutput = 'html'; // or 'error_log' or 'echo'
      $mail->SMTPAuth = FALSE;
      if ($this->_SMTPusername !== '') {
        $mail->SMTPAuth = TRUE;
        $mail->Username = $this->_SMTPusername;
        $mail->Password = $this->_SMTPpassword;
      }
      if ($this->_SMTPsecure !== '') {
        $mail->SMTPSecure = $this->_SMTPsecure;
      } else {
        // If they really want no security, give them no security
        $mail->SMTPAutoTLS = FALSE;
      }
      $mail->CharSet = $this->_SMTPcharset;
      $mail->XMailer = ' ';
      $mail->Subject = $subject;

      // Set the From: and envelope sender.
      // Also save the sender email address.
      $sender = '';
      if (strpos($this->_emailSenderAddr, ' ') !== FALSE) {
        $s = array();
        $s = $mail->parseAddresses($this->_emailSenderAddr);
        $mail->setFrom($s[0]['address'], $s[0]['name']);
        $sender = $s[0]['address'];
      } else {
        $mail->setFrom($this->_emailSenderAddr);
        $sender = $this->_emailSenderAddr;
      }

      // We are about to need to domain of the sender
      $senderParts = array();
      if (preg_match($this->validEmailRegexp(),
                     $sender, $senderParts))
        $senderDomain = $senderParts[2];
      else
        $senderDomain = '';

      if ($fromAddr !== "") {
        // If they want to try to make the From and Reply-To the same...
        if ($this->_SMTPsetFromToSender) {
          // We've got a from address of some sort. Need the domain first
          $fromParts = array();
          if (preg_match($this->validEmailRegexp(),
                         $fromA, $fromParts))
            $fromDomain = $fromParts[2];
          else
            $fromDomain = '';

          // If the sender domain and the from domain are the same
          // (and not blank, which signifies something went wrong!),
          // we can safely overwrite the From we set above, without
          // causing SPF/DKIM/DMARC problems.
          // Loosened this up so it will overwrite the From if
          // the From domain is a sub-domain of the sender domain.
          // Make very sure the SPF records for your sub-domains
          // include the SMTP server for your top-level domain.
          if ($senderDomain !== '' &&
              (strcasecmp($senderDomain, $fromDomain) == 0 ||
               str_ends($fromDomain, $senderDomain))) {
            // They match
            $mail->setFrom($fromA, $fromN);
            // Despite the docs, if setFrom has been called already,
            // calling it again will *not* overwrite the envelope sender.
            // So we have to do it by hand.
            $mail->Sender = $fromA;
          }
        }
        // Don't strictly need this if the domains match, *and* they wanted
        // to set the From to the drop-off sender's address.
        $mail->addReplyTo($fromA, $fromN);
      }

      // "To" the 1st address, "Bcc" remaining non-null ones
      if (is_array($toAddr)) {
        $mail->addAddress(array_shift($toAddr));
        while ( $bcc = array_shift($toAddr) ) {
          if ($bcc !== '') {
            $mail->addBCC($bcc);
          }
        }
      } else {
        $mail->addAddress($toAddr);
      }

      if ($htmlcontent !== '') {
        $mail->isHTML(true);
        $mail->ContentType = 'text/html; charset=UTF-8';
        $mail->Encoding = '8bit';
        $mail->msgHTML($htmlcontent);
        $mail->AltBody = $textcontent;
      } else {
        $mail->isHTML(false);
        $mail->ContentType = 'text/plain; charset=UTF-8; format=flowed';
        $mail->Encoding = '8bit';
        $mail->WordWrap = 0;
        $mail->Body = $textcontent;
      }
      if (!$mail->send()) {
        NSSError($mail->ErrorInfo, gettext("Mail Error"));
        return FALSE;
      }
      return TRUE;
     } catch (Exception $e) {
      NSSError($e->errorMessage(), gettext("Mail Error"));
      $this->writeToLog(sprintf("Error: Mail send failed with %s",
                                $e->errorMessage()));
      return FALSE;
     } catch (\Exception $e) {
      NSSError($e->getMessage(), gettext("Mail Error"));
      $this->writeToLog(sprintf("Error: Mail send failed with %s",
                                $e->getMessage()));
      return FALSE;
     }

    } else {

      // If it contains any characters outside 0x00-0x7f, then encode it
      if (preg_match('/[^\x00-\x7f]/', $subject)) {
        $subject = "=?UTF-8?B?".base64_encode(html_entity_decode($subject))."?=";
      }
      if (preg_match('/[^\x00-\x7f]/', $fromAddr)) {
        $fromAddr = "=?UTF-8?B?".base64_encode(html_entity_decode($fromAddr))."?=";
      }
      if (preg_match('/[^\x00-\x7f]/', $this->_emailSenderAddr)) {
        $sender = "=?UTF-8?B?".base64_encode(html_entity_decode($this->_emailSenderAddr))."?=";
      } else {
        $sender = $this->_emailSenderAddr;
      }

      $headers = '';

      // The $toAddr might be an array: To the first, Bcc the rest
      if (is_array($toAddr)) {
        $to = array_shift($toAddr);
        while ( $bcc = array_shift($toAddr) ) {
          if ($bcc !== '') {
            // Encode it if necessary
            if (preg_match('/[^\x00-\x7f]/', $bcc)) {
              $bcc = "=?UTF-8?B?".base64_encode(html_entity_decode($bcc))."?=";
            }
            $headers .= sprintf("Bcc: %s", $bcc) . PHP_EOL;
          }
        }
      } else {
        $to = $toAddr;
      }
      // Add the From: and Reply-To: headers if they have been supplied.
      if ($fromAddr !== "") {
        // Changed the From: from $sender to $fromAddr to avoid Gmail grief
        // Backed out that change.
        //$headers = sprintf("From: %s", $fromAddr) . PHP_EOL .
        $headers = sprintf("From: %s", $sender) . PHP_EOL .
                   sprintf("Reply-to: %s", $fromAddr) . PHP_EOL .
                   $headers;
      } else {
        // No from, so just use $sender instead
        $headers = sprintf("From: %s", $sender) . PHP_EOL .
                   $headers;
      }

      // Add the MIME headrs for 8-bit UTF-8 encoding
      $headers .= "MIME-Version: 1.0".PHP_EOL;
      $headers .= "Content-Type: text/plain; charset=UTF-8; format=flowed".PHP_EOL;
      $headers .= "Content-Transfer-Encoding: 8bit".PHP_EOL;

      return mail(
                $to,
                $subject,
                $textcontent,
                $headers
              );
    }
  }

  /*!
    @function writeToLog
    
    Write the $logText to the log file.  Each line is formatted to have a date
    and time, as well as the name of this dropbox.
  */
  public function writeToLog(
    $logText
  )
  {
    global $smarty;
    global $NSSDROPBOX_PREFS;

    $logText = sprintf("%s %s [%s]: %s\n",strftime("%Y-%m-%d %T"),
                       getClientIP($NSSDROPBOX_PREFS),
                       $smarty->getConfigVars('ServiceTitle'),
                       preg_replace('/%0[adAD]/', ' ', $logText));
    if (!file_put_contents($this->_dropboxLog,$logText,FILE_APPEND | LOCK_EX)) {
      NSSError(sprintf(gettext("Could not write to log file %s, ensure that web server user can write to the log file as set in preferences.php"), $this->_dropboxLog), gettext("Configuration Error"));
    }
  }
  
  /*!
    @function SetupPage
    
    End the <HEAD> section; write-out the standard stuff for the <BODY> --
    the page header with title, etc.  Upon exit, the caller should begin
    writing HTML content for the page.
    
    We also get a chance here to spit-out an error if the authentication
    of a user failed.
    
    The single argument gives the text field that we should throw focus
    to when the page loads.  You should pass the text field as
    "[form name].[field name]", which the function turns into
    "document.[form name].[field name].focus()".
  */
  public function SetupPage(
    $focusTarget = NULL,
    $extraPost = NULL
  )
  {  
    global $NSSDROPBOX_URL;
    global $smarty;
    global $currentLocale;
    global $php_self;

    $smarty->assign('zendToURL', $NSSDROPBOX_URL);

    // advertisedServerRoot overrides serverRoot if it's defined
    $urlroot = $this->advertisedServerRoot();
    if ($urlroot) {
      // They *did* end it with a / didn't they??
      if (substr($urlroot, -1) !== '/') $urlroot .= '/';
      //$smarty->assign('zendToURL', $urlroot);
      $smarty->assign('linkURL', $urlroot);
    } else {
      //$smarty->assign('zendToURL', $NSSDROPBOX_URL);
      $smarty->assign('linkURL', $NSSDROPBOX_URL);
    }

    $smarty->assign('focusTarget', $focusTarget);
    $smarty->assign('padlockImage', ($this->_authorizedUser?"images/locked.png":"images/unlocked.png"));
    $smarty->assign('padlockImageAlt', ($this->_authorizedUser?"locked":"unlocked"));
    $smarty->assign('isAuthorizedUser', ($this->_authorizedUser?TRUE:FALSE));
    $smarty->assign('validEmailRegexp', $this->validEmailRegexp());
    $smarty->assign('SMTPdebug', $this->_SMTPdebug);
    $smarty->assign('cookieGDPRConsent', $this->_cookieGDPRConsent);
    $smarty->assign('confirmExternalEmails', $this->_confirmExternalEmails);
    $smarty->assign('hidePHP', $this->_hidePHP);
    $smarty->assign('currentLocale', $currentLocale);
    $smarty->assign('localeList', $this->getLocaleList());
    if (!$php_self)
      // Prune it to the leaf filename, then sanitise it hard
      $php_self = preg_replace('/^.*\//', '', $_SERVER['PHP_SELF']);
      $php_self = preg_replace('/[^a-zA-Z0-9_.-]+/', '', $php_self);
    $smarty->assign('isIndexPage',
      preg_match('/^index.*/', $php_self) ? TRUE : FALSE);

    $smarty->assign('gotHere', $php_self);
    $newget = $_GET;
    // Add/replace elements of $_POST as necessary.
    // This allows us to tweak what gets sent back for the language picker.
    $newpost = $_POST;
    if (is_array($extraPost)) {
      foreach ($extraPost as $k => $v) {
        $newpost[$k] = $v;
      }
    }

    // Make sure we don't send back their passwords!
    // and wipe them securely
    $wipeme = array('password', 'n', 'encryptPassword', 'encryptPassphrase',
                    'encryptPassphrase1', 'encryptPassphrase2',
                    'encryptPassword1', 'encryptPassword2');
    foreach($wipeme as $k) {
      if (array_key_exists($k, $newget)) {
        if (isset($newget[$k])) sodium_memzero($newget[$k]);
        unset($newget[$k]);
      }
      if (array_key_exists($k, $newpost)) {
        if (isset($newpost[$k])) sodium_memzero($newpost[$k]);
        unset($newpost[$k]);
      }
    }

    $smarty->assign('getData', json_encode($newget, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK | JSON_PARTIAL_OUTPUT_ON_ERROR));
    $smarty->assign('postData', json_encode($newpost, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK | JSON_PARTIAL_OUTPUT_ON_ERROR));

    $smarty->assign('keepForDays', $this->retainDays());
    $smarty->assign('defaultNumberOfDaysToRetain', $this->defaultNumberOfDaysToRetain());
    $smarty->assign('defaultLifetime', $this->defaultLifetime());
    $smarty->assign('requestTTL', secsToString($this->requestTTL()));
    $smarty->assign('ztVersion', ZTVERSION);
    $smarty->assign('whoAmI', $this->authorizedUserData("displayName"));
    $smarty->assign('whoAmIuid', $this->authorizedUserData("uid"));
    $smarty->assign('whoAmImail', $this->authorizedUserData("mail"));
    $smarty->assign('isAdminUser', (@$this->_authorizedUserData['grantAdminPriv'])?TRUE:FALSE);
    $smarty->assign('isStatsUser', (@$this->_authorizedUserData['grantStatsPriv'])?TRUE:FALSE);

    // If we are using SAML, then the login button goes to SimpleSAML not ZendTo
    if (@$this->usingSaml()) {
      $smarty->assign('SAML', 'saml');
      if ($this->_samlAuthSimple == NULL)
        $this->_samlAuthSimple = new SimpleSAML\Auth\Simple('default-sp');
      $smarty->assign('loginUrl', $this->_samlAuthSimple->getLoginURL());
    } else {
      $smarty->assign('SAML', '');
      $smarty->assign('loginUrl', 'index.php?action=login');
    }

    // -1 as it only does max-1 files, the maxth is the other form data
    $maxuploads = ini_get('max_file_uploads');
    if ($maxuploads>0) {
      $smarty->assign('uploadFilesMax', $maxuploads-1);
    } else {
      $smarty->assign('uploadFilesMax', 999);
    }

    if ( !function_exists('sodium_crypto_secretstream_xchacha20poly1305_init_push') )
      NSSError('You need the libsodium support in PHP. Please download '.
               'and run the ZendTo installer from http://zend.to/ and '.
               'it will upgrade your system to the PHP setup required.',
               'Installation Error');

    if ( !function_exists('curl_init') )
      NSSError('You need the curl support in PHP. Please install the package ' .
               'that provides the PHP curl module, using "yum" or "apt".',
               'Installation Error');

    if ( $this->_authorizationFailed ) {
      NSSError(gettext("The username or password was incorrect."),
               gettext("Authentication Error"));
    }
  }
  
  //
  // JKF
  //
  // Setup the new database tables I need.
  // Must be able to add this on the fly if a SELECT fails.
  public function setupDatabaseReqTable()
  {
    if ( $this->database ) {
      if ( ! $this->database->DBCreateReq() ) {
        $this->writeToLog("Error: dailed to add reqtable to database");
        return FALSE;
      }
      $this->writeToLog("Info: added reqtable to database");
      return TRUE;
    }
    return FALSE;
  }

  // Setup the new database table I need.
  // Must be able to add this on the fly if a SELECT fails.
  public function setupDatabaseAuthTable()
  {
    if ( $this->database ) {
      if ( ! $this->database->DBCreateAuth() ) {
        $this->writeToLog("Error: failed to add authtable to database");
        return FALSE;
      }
      $this->writeToLog("Info: added authtable to database");
      return TRUE;
    }
    return FALSE;
  }

  // Setup the new database table I need.
  // Must be able to add this on the fly if a SELECT fails.
  public function setupDatabaseUserTable()
  {
    if ( $this->database ) {
      if ( ! $this->database->DBCreateUser() ) {
        $this->writeToLog("Error: failed to add usertable to database");
        return FALSE;
      }
      $this->writeToLog("Info: added usertable to database");
      return TRUE;
    }
    return FALSE;
  }

  // Setup the new database table I need.
  // Must be able to add this on the fly if a SELECT fails.
  public function setupDatabaseRegexpsTable()
  {
    if ( $this->database ) {
      if ( ! $this->database->DBCreateRegexps() ) {
        $this->writeToLog("Error: failed to add regexps to database");
        return FALSE;
      }
      $this->writeToLog("Info: added regexps to database");
      return TRUE;
    }
    return FALSE;
  }

  // Setup the new database table I need.
  // Must be able to add this on the fly if a SELECT fails.
  public function setupDatabaseLoginlogTable()
  {
    if ( $this->database ) {
      if ( ! $this->database->DBCreateLoginlog() ) {
        $this->writeToLog("Error: failed to add loginlog to database");
        return FALSE;
      }
      $this->writeToLog("Info: added loginlog to database");
      return TRUE;
    }
    return FALSE;
  }

  /*!
    @function isLocalUser

    Returns true if the user is coming from an IP address defined as being
    'local' in preferences.php 'localIPSubnets'. Returns false otherwise.
  */
  public function isLocalIP()
  {
    global $NSSDROPBOX_PREFS;

    foreach ($this->localIPSubnets() as $subnet) {
      // Add a . on the end if there isn't one, so 152.78 becomes 152.78.
      // Except when there are already 3 dots, which implies it's a
      // complete address. And yes, I know this doesn't work with IPv6.
      if (substr_count($subnet, '.')<3 && ! substr($subnet, -1)=='.') {
        $subnet .= '.';
      }
      $sublen = strlen($subnet);
      if (substr(getClientIP($NSSDROPBOX_PREFS), 0, $sublen) == $subnet) {
        return TRUE;
      }
    }
    return FALSE;
  }


  /*!
    @function cookieForSession
    
    Returns an appropriate cookie for the current session.  An initial key is
    constructed using the username, remote IP, current time, a random value,
    the user's browser agent tag, and our special cookie secret.  This key is
    hashed, and included as part of the actual cookie.  The cookie contains
    more or less all but the secret value, so that the initial key and its
    hash can later be reconstructed for authenticity's sake.
  */
  private function cookieForSession()
  {
    global $NSSDROPBOX_PREFS;

    $now = time();
    $nonce = function_exists('random_bytes') ? bin2hex(random_bytes(8))
                                             : mt_rand();
    $digestString = sprintf("%s %s %s %d %d %s %s",
                        $this->_authorizedUser,
                        getClientIP($NSSDROPBOX_PREFS),
                        $this->_authenticator->getAuthName(),
                        $now,
                        $nonce,
                        $_SERVER['HTTP_USER_AGENT'],
                        $this->_cookieName
                        // $this->_secretForCookies
                      );
    $cook = sprintf("%s,%s,%s,%d,%d,%s",
                        $this->_authorizedUser,
                        getClientIP($NSSDROPBOX_PREFS),
                        $this->_authenticator->getAuthName(),
                        $now,
                        $nonce,
                        md5($digestString)
                      );
    # Yes, I know the iv should be random, but where to store it?
    # And please don't say "another cookie".
    return(openssl_encrypt($cook, 'AES-128-CBC', $this->_secretForCookies, 0, 'qpwoeirituyutiro'));
  }
  
  /*!
    @function userFromCookie
    
    Attempt to parse our cookie (if it exists) and establish the current user's
    username.
  */
  private function userFromCookie()
  {
    global $NSSDROPBOX_PREFS;

    if ( isset($_COOKIE[$this->_cookieName]) && ($cookieVal = $_COOKIE[$this->_cookieName]) ) {
      $cookieVal = openssl_decrypt($cookieVal, 'AES-128-CBC', $this->_secretForCookies, 0, 'qpwoeirituyutiro');
      // Added (?:\:[0-9]+)? to support IIS7 adding port numbers! :-(
      // Thanks to dturvey@bhc.ltd.uk for this.
      // Simplified to also allow IPv6. Thanks for blaisot.org for this.
      if ( preg_match('/^(.+)\,([0-9a-fA-F\:.]+(?:\:[0-9]+)?),([A-Za-z]+),([0-9]+),([A-Fa-f0-9]+),([A-Fa-f0-9]+)$/',$cookieVal,$cookiePieces) ) {
        //  Coming from the same remote IP?
        if ( $cookiePieces[2] != getClientIP($NSSDROPBOX_PREFS) ) {
          return FALSE;
        }
        
        //  How old is the internal timestamp?
        if ( time() - $cookiePieces[4] > $this->_cookieTTL ) {
          return FALSE;
        }
        
        //  Verify the MD5 checksum.  This implies that everything
        //  (including the HTTP agent) is unchanged.
        $digestString = sprintf("%s %s %s %d %d %s %s",
                            $cookiePieces[1],
                            $cookiePieces[2],
                            $cookiePieces[3],
                            $cookiePieces[4],
                            $cookiePieces[5],
                            $_SERVER['HTTP_USER_AGENT'],
                            $this->_cookieName
                            // $this->_secretForCookies
                          );
        if ( md5($digestString) !== $cookiePieces[6] ) {
          return FALSE;
        }
        
        //  Success!

        //  We now know what authenticator they used (if using Multi).
        //  So tell the Multi authenticator before we can check the username.
        $this->_authenticator->setAuthName($cookiePieces[3]);

        //  Verify the username as valid:
        if ( $this->_authenticator->validUsername($cookiePieces[1],$this->_authorizedUserData) ) {
          $this->_authorizedUser = $cookiePieces[1];
          return TRUE;
        }
      }
    }
    return FALSE;
  }
  
  /*!
    @function userFromAuthentication
    
    Presumes that a username and password have come in POST'ed form
    data.  We need to do an LDAP bind to verify the user's identity.
  */
  private function userFromAuthentication()
  {
    $result = FALSE;
    
    if ( ($usernameRegex = $this->usernameRegexp()) == NULL ) {
      $usernameRegex = '/^([a-zA-Z0-9][a-zA-Z0-9\_\.\-\@\\\]*)$/';
    }
    
    // SAML setup.
    // If they are trying to log in as a SAML user, the uname and password
    // fields will both say 'saml'.
    // If they are trying to login as a non-SAML user, then we are running
    // automated and so need to fall through to whatever other authenticator
    // they chose.
    if ($this->usingSaml()) {
      // They should already be logged in if they need to be.
      require_once (dirname(__FILE__) . '/../simplesamlphp/lib/_autoload.php');
      if ($this->_samlAuthSimple == NULL)
        $this->_samlAuthSimple = new SimpleSAML\Auth\Simple('default-sp');

      if (!isset($_POST['uname']) && !isset($_POST['password'])) {
        // Not passed any username/password at all.
        // So not attempting to authenticate manually.
        if ($this->_samlAuthSimple->isAuthenticated()) {
          // They are authenticated, so we need to populate authorizedUserData[]
          // Don't need to worry about lockouts, as SAML will have taken
          // care of that problem already.
          \SimpleSAML\Session::getSessionFromRequest()->cleanup();
          $uname = $this->setSamlUserData();
          if ($uname !== NULL) {
            $isadmin = '';
            if ( $this->_authAdmins )
              $isadmin = in_array(strtolower($uname),
                         array_map('strtolower', $this->_authAdmins))?'admin ':'';

            $this->writeToLog("Info: SAML ".$isadmin."authorization succeeded for ".$uname);
            $this->_authorizedUser = $uname;
            $this->_authorizationFailed = FALSE;
            return TRUE;
          }
        }
        \SimpleSAML\Session::getSessionFromRequest()->cleanup();
        return FALSE; // They aren't authenticated
      } elseif ( isset($_POST['uname']) && isset($_POST['password']) ) {
        // They are trying to login with a username and password outside SAML.
        // As we are using SAML, so they shouldn't be trying to login except
        // for automation. So only allow the automationUsers.
        $autousers = $this->_automationUsers;
        // Allow the prefs setting to be an array or a string.
        if (is_array($autousers)) {
          if (!in_array($_POST['uname'], $autousers, TRUE)) {
            $this->writeToLog("Warning: automation authorization attempt for non-automationUser ".$_POST['uname']);
            return FALSE;
          }
        } else {
          if ($_POST['uname'] !== $autousers) {
            $this->writeToLog("Warning: automation authorization attempt for non-automationUser ".$_POST['uname']);
            return FALSE;
          }
        }
      }
    }

    // We reach here if either
    // 1. We aren't using SAML at all, OR
    // 2. We are using SAML, but they are trying to login with a non-SAML username
    //    (which means we are running automated/scripted).

    if ( $this->_authenticator && isset($_POST['uname']) && preg_match($usernameRegex,$_POST['uname']) && isset($_POST['password']) && $_POST['password'] ) {
      $password = $_POST['password']; // JKF Don't unquote the password as it might break passwords with backslashes in them. stripslashes($_POST['password']);
      $uname    = paramPrepare(strtolower($_POST['uname']));

      // Check to ensure they aren't already locked out
      if ( $this->database->DBLoginlogLength($uname,
                                             time()-$this->_loginFailTime)
           >= $this->_loginFailMax ) {
        // There have been too many failure attempts.
        $this->_authorizationFailed = TRUE;
        $this->writeToLog("Warning: authorization attempt for locked-out user ".$uname);
        // Add a new failure record
        $this->database->DBAddLoginlog($uname);
        $this->_authorizedUserData = NULL;
        $this->_authorizedUser = '';
        $result = FALSE;
      } else {
        // They are allowed to try to login
        $isadmin = '';
        if ( $this->_authAdmins )
          $isadmin = in_array(strtolower($uname),
                     array_map('strtolower', $this->_authAdmins))?'admin ':'';

        if ( $result = $this->_authenticator->authenticate($uname,$password,$this->_authorizedUserData) ) {
          // They have been authenticated, yay!
          $this->_authorizedUser = $uname;
          $this->_authorizationFailed = FALSE;
          $this->writeToLog("Info: ".$isadmin."authorization succeeded for ".$uname);
          // Reset their login log
          $this->database->DBDeleteLoginlog($uname);
          $result = TRUE;
        } else {
          // Password check failed. :(
          $this->_authorizationFailed = TRUE;
          $this->writeToLog("Warning: ".$isadmin."authorization failed for ".$uname);
          // Add a new failure record
          $this->database->DBAddLoginlog($uname);
          $this->_authorizedUserData = NULL;
          $this->_authorizedUser = '';
          $result = FALSE;
        }
      }

    } else {
      // Login attempt failed, check for bad usernames and report them.
      // But only if there is a username and a password, or else this will
      // false alarm on any page that displays the login box.
      if ( isset($_POST['uname']) && $_POST['uname'] != "" && 
           ! preg_match($usernameRegex,$_POST['uname']) ) {
        $this->writeToLog("Warning: illegal username \"".paramPrepare($_POST['uname']).
                          "\" attempted to login");
        $this->_authorizationFailed = TRUE;
        $this->_authorizedUserData = NULL;
        $this->_authorizedUser = '';
        $result = FALSE;
      }
    }
    return $result;
  }

  /*!
    @function setSamlUserData

    Extract the attributes we need from the SAML response, and use them to
    set up the authorizedUserData properties we need.

    Attributes we need to find are
    mail
    displayName
    uid (set same as mail)
    organization

    The mapping list to attribute name needs to be customisable as different
    IDPs will provide them as differently-named attributes.
  */
  public function setSamlUserData()
  {
    global $smarty;
    $keysToSet = array('mail', 'displayName', 'uid', 'organization');

    $attrs = $this->_samlAuthSimple->getAttributes();
    if (!is_array($attrs))
      return NULL;

    // Attribute map is stored in a preferences.php hash array
    $map = $this->_samlAttributesMap;
    // Sanity check the attributes map
    if (is_array($map) && !empty($map)) {
      // Loop through mail, displayName etc
      foreach ($keysToSet as $k) {
        // Does preferences.php provide a mapping for this key at all?
        if (isset($map[$k])) {
          // Does the preferences.php map give an attribute name that exists?
          if (isset($attrs[$map[$k]]))
            // Get the value of the relevant atttribute
            $v = $attrs[$map[$k]];
            // If the attribute is an array, just use the first element
            if (is_array($v))
              $v = $v[0];
          else
            // preferences.php gives non-existent attribute name,
            // so assume it's a fixed string (e.g. for organization)
            $v = $map[$k];
          // And store the resulting value in the user info hash
          $this->_authorizedUserData[$k] = $v;
        } else {
          // preferences.php doesn't provide any mapping for this key!
          NSSError(sprintf("SAML attribute mapping for '%s' is not set. %s",
                           $k, $SYSADMIN),
                   gettext("Configuration Error"));
        }
      }
      // Set their isAdmin and isStats flags correctly
      if ( $this->_authAdmins ) {
        $isadminuser = in_array(
                  strtolower(@$this->_authorizedUserData['uid']),
                  array_map('strtolower', $this->_authAdmins));
        // If admin logins have to be local and this one isn't,
        // override value of isadminuser.
        if ($this->adminLoginsMustBeLocal() && !$this->isLocalIP())
          $isadminuser = FALSE;

        // Grant Admin and Stats to admins
        $this->_authorizedUserData['grantAdminPriv'] = $isadminuser;
        $this->_authorizedUserData['grantStatsPriv'] = $isadminuser;
      }
      if ( $this->_authStats ) {
        $isstatsuser = in_array(
                  strtolower(@$this->_authorizedUserData['uid']),
                  array_map('strtolower', $this->_authStats));
        // Stats users are not location-restricted.
        $this->_authorizedUserData['grantStatsPriv'] = $isstatsuser;
      }

      // Return value is their username within ZendTo, so needs to be
      // email address if it exists. No email? Then lookup has all failed.
      return @$this->_authorizedUserData['uid'];

    } else {
      // Default behaviour if no samlAttributesMap configured
      NSSError("SAML attributes map not defined",
               gettext("Configuration Error"));
    }
    return NULL;
  }

  /*!
    @function checkRecipientDomain

    Given a complete recipient email address, check if it is valid.
    The result is ignored if the user has logged in, this is only for
    un-authenticated users.
    This now also checks the entire address, as we now allow
    internaldomains.conf to contain individual email addresses and
    *@domain.com syntax.
  */
  public function checkRecipientDomain( $recipient )
  {
    $result = FALSE;

    $data = explode('@', $recipient);
    $recipDomain = $data[1];
    $re = $this->emailDomainRegexp();

    if (preg_match('/^\/.*[^\/i]$/', $re)) {
      // emailDomainRegexp() is a filename.
      // Get all the current regexps from the database, and use the
      // timestamp of one of them to compare against the modtime of the
      // text config file. If there aren't any, or the timestamp is older
      // than the modtime, then rebuild all the regexps.
      // emailDomainRegexps are hereby decreed to be type number 1.
      $relist = $this->database->DBReadRegexps(1);
      if ($relist) {
        $rebuildtime = $relist[0]['created'];
      } else {
        // There weren't any stored, so build for 1st time.
        $rebuildtime = 0;
      }

      if (filemtime($re) > $rebuildtime) {
        // File has been modified since we last read it.
        // Build and store the regexps, and read them back in.
        $this->RebuildDomainRegexps(1, $re);
        $relist = $this->database->DBReadRegexps(1);
      }
      // Check against every RE we built from the file
      foreach ($relist as $rerow) {
        $re = $rerow['re'];
        if ($re != '') {
          // If we have a match, then set result and short-cut out of here!
          if (preg_match($re, $recipDomain) || preg_match($re, $recipient)) {
            $result = TRUE;
            break;
          }
        }
      }
    } else {
      // It is not a filename so must be an attempt at a Regexp.
      // Add a / on the front if not already there.
      if (!preg_match('/^\//', $re)) {
        $re = '/' . $re;
      }
      // Add a / on the end if not already there.
      if (!preg_match('/\/[a-z]?$/', $re)) {
        $re = $re . '/';
      }
      if (preg_match($re, $recipDomain) || preg_match($re, $recipient) ) {
        $result = TRUE;
      }
    }

    // Ask the authenticator if it has a different idea of the truth
    return $this->_authenticator->checkRecipient($result, $recipient);
  }

  // Rebuild the list of regular expressions given in the $filename.
  // Put 10 domains in each regexp to make sure we don't make them
  // too long.
  // Need to collect 10 at a time into a little array, then call
  // another function to make an re from a list of domain names.
  private function RebuildDomainRegexps($type, $filename) {
    if (!is_readable($filename)) {
      $this->writeToLog("Warning: domains list file $filename is not readable or does not exist");
      return;
    }

    // Read $filename into an array
    $contents = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (! $contents) {
      NSSError(sprintf(gettext("Could not read local domain list file %s"), $filename));
      return;
    }

    // For each line, add the domain to the temporary list of domains.
    // Once we have 10 domains, convert it into a regexp and zap the temp list.
    $relist = array();
    $emaillist = array();
    $domainlist = array();
    $esize = 0;
    $dsize = 0;
    $ecounter = 0;
    $dcounter = 0;
    foreach($contents as $l) {
      // Ignore blank lines and # comment lines
      $line = trim(strtolower($l));
      if ($line == '' || preg_match('/(^#|^\/\/)/', $line)) {
        continue;
      }
      // If they didn't read the docs, they might have put "*."
      // at the start of some lines. So remove that if it's there.
      if (strpos($line, '*.') === 0)
        $line = substr($line, 2);
      // It's either a *@domain or email@domain or just a domain
      if (strpos($line, '@') !== false) {
        // It's email addresses of some sort
        $emaillist[] = $line;
        $esize++;
        $ecounter++;
      } else {
        // It's a domain
        $domainlist[] = $line;
        $dsize++;
        $dcounter++;
      }
      // Have we got 10 yet, which is enough for 1 RE ?
      if ($size >= 10) {
        $relist[] = $this->MakeEmailRE($emaillist);
        $emaillist = array();
        $esize = 0;
      }
      if ($dsize >= 10) {
        $relist[] = $this->MakeDomainRE($domainlist);
        $domainlist = array();
        $dsize = 0;
      }
    }
    // Don't forget the last trailing few in the file
    if ($esize > 0) {
      $relist[] = $this->MakeEmailRE($emaillist);
    }
    if ($dsize > 0) {
      $relist[] = $this->MakeDomainRE($domainlist);
    }
    // Now we have all the regexps in $relist. So store them in the db.
    $this->database->DBOverwriteRegexps($type, $relist);
    $this->writeToLog("Info: Successfully read $dcounter local domains and $ecounter email addresses from $filename");
  }

  // Given a list of email addresses,
  // construct an RE which will match ^email1|email2$
  private function MakeEmailRE($emails) {
    // Error checking on inputs
    if (! $emails)
      return;
    // Replace every '.' with '\.' and * with .* to allow
    // lines like *@domain.com
    $efixed = array_map('strtolower', str_replace(array('.', '*'), array('\.', '.*'), $emails));
    // Turn the list of emails into an RE that matches them all
    $re = '/^(' . implode('|', $efixed) . ')$/';
    return $re;
  }

  // Given a ref to a regular expression variable and a list of domains,
  // construct an RE which will match ^([a-zA-Z0-9-]+\.)?(domain1|domain2)$
  private function MakeDomainRE($domains) {
    // Error checking on inputs
    if (! $domains) {
      return;
    } else {
      // Replace every '.' with '\.'
      $dfixed = array_map('strtolower', str_replace('.', '\.', $domains));
      // Turn the list of domain names into an RE that matches them all
      $re = implode('|', $dfixed);
      // Added '.' in the [] list to include sub-sub-domains etc.
      $re = '/^([a-zA-Z0-9.\-]+\.)?('.$re.')$/';
      return $re;
    }
  }


  /*!
    @function checkPrefs
    
    Examines a preference hash to be sure that all of the required parameters
    are extant.
  */
  private function checkPrefs(
    $prefs
  )
  {
    static $requiredKeys = array(
              'dropboxDirectory',
              'recaptchaPublicKey',
              'recaptchaPrivateKey',
              'emailDomainRegexp',
              'defaultEmailDomain',
              'logFilePath',
              'cookieName',
              'authenticator'
            );
    foreach ( $requiredKeys as $key ) {
      if ( !$prefs[$key] || ($prefs[$key] == "") ) {
        NSSError(sprintf(gettext("You must provide a value for the following preference key: '%s'"), $key), gettext("Undefined Preference Key"));
        return FALSE;
      }
    }
    return TRUE;
  }
  
  // Construct a string containing 3 random words with a space between each.
  public function ThreeRandomWords(
  )
  {
    global $NSSDROPBOX_PREFS;
    global $ShortWordsList;
    // This is only ever called at most once per page.
    // So just read them all in now. Doesn't matter if
    // we throw them away.
    $wordlist = 'numbers';
    if (array_key_exists('wordlist', $NSSDROPBOX_PREFS) &&
      $NSSDROPBOX_PREFS['wordlist'] !== '') {
      $wordlist = $NSSDROPBOX_PREFS['wordlist'];
    }
    if ($wordlist == 'numbers') {
      for ($n=0; $n<1000; $n++) {
        $ShortWordsList[] = sprintf("%03d", $n);
      }
    } else {
      require_once(NSSDROPBOX_LIB_DIR."wordlist-".$wordlist.".php");
    }

    $avoid = array();
    $word1 = $this->OneRandomWord($avoid);
    $avoid[] = $word1;
    $word2 = $this->OneRandomWord($avoid);
    $avoid[] = $word2;
    $word3 = $this->OneRandomWord($avoid);
    return "$word1 $word2 $word3";
  }

  private function OneRandomWord(
    $avoid
  )
  {
    global $ShortWordsList;

    // Find a random word, avoiding words we are given in $avoid[]
    $len = count($ShortWordsList);
    do {
      $word = $ShortWordsList[mt_rand(0, $len-1)];
    } while (in_array($avoid, $word));

    return $word;
  }

  public function WriteReqData(
    $srcname,
    $srcemail,
    $srcorg,
    $destname,
    $destemail,
    $note,
    $subject,
    $passphrase = '',
    $expiryDateTime = 0
  )
  {
    $words = $this->ThreeRandomWords();
    $hash = preg_replace('/[^a-zA-Z0-9]/', '', $words);
    // Allow the requester to set the exact expiry time.
    // Only currently possible via autorequest.
    // Thanks for Luigi Capriotti for this suggestion!
    if ($expiryDateTime > 0) {
      $expiry = $expiryDateTime;
    } else {
      // This value will always get used by the web interface
      $expiry  = time() + $this->_requestTTL;
    }
    if ( $this->database->DBWriteReqData($this, $hash, $srcname, $srcemail,
                                         $srcorg, $destname, $destemail,
                                         $note, $subject, $expiry,
                                         $passphrase) != '' ) {
      return $words;
    } else {
      return '';
    }
  }

  public function ReadReqData(
    $authkey,
    &$srcname,
    &$srcemail,
    &$srcorg,
    &$destname,
    &$destemail,
    &$note,
    &$subject,
    &$expiry,
    &$passphrase
  )
  {
    // Only allow letters and numbers in $authkey
    $authkey = preg_replace('/[^a-zA-Z0-9]/', '', $authkey);

    $srcname = '';
    $srcemail = '';
    $srcorg = '';
    $destname = '';
    $destemail = '';
    $note   = '';
    $subject= '';
    $expiry = '';
    $passphrase = '';

    $recordlist = $this->database->DBReadReqData($authkey);
    if ( $recordlist && count($recordlist) ) {
      // @ob_end_clean(); //turn off output buffering to decrease cpu usage
      // Over-quoting! These are quoted before use later.
      // $srcname = htmlentities($recordlist[0]['SrcName'], ENT_QUOTES, 'UTF-8');
      // $destname = htmlentities($recordlist[0]['DestName'], ENT_QUOTES, 'UTF-8');
      $srcname = $recordlist[0]['SrcName'];
      $destname = $recordlist[0]['DestName'];
      // Trim accidental whitespace, it's hard to detect and will cause failure
      $srcemail = trim($recordlist[0]['SrcEmail']); // This is already checked carefully
      $destemail = trim($recordlist[0]['DestEmail']); // This is already checked carefully
      $srcorg = trim($recordlist[0]['SrcOrg']);
      // Over-quoting! These are quoted before use later.
      // $note = htmlentities($recordlist[0]['Note'], ENT_QUOTES, 'UTF-8');
      // $subject = htmlentities($recordlist[0]['Subject'], ENT_QUOTES, 'UTF-8');
      $note = $recordlist[0]['Note'];
      $subject = $recordlist[0]['Subject'];
      $expiry= $recordlist[0]['Expiry'];
      $passphrase = @$recordlist[0]['Passphrase'];
      // Be doubly-careful with possibly missing (hence NULL) strings
      if (!isset($passphrase)) {
        $passphrase = '';
      }
      return 1;
    }
    return 0;
  }

  public function DeleteReqData(
    $authkey
  )
  {
    $authkey = preg_replace('/[^a-zA-Z0-9]/', '', $authkey);
    $this->database->DBDeleteReqData($authkey);
  }

  public function PruneReqData(
  )
  {
    $old = time() - 86400; // 1 day ago
    $this->database->DBPruneReqData($old);
  }

  // Add a record to the database for this user, right now.
  // Return the auth string to use in forms, or '' on failure.
  public function WriteAuthData(
    $name,
    $email,
    $org
  )
  {
    $randint = mt_rand();
    $hash    = strtolower(md5($randint));
    $expiry = time() + 86400;

    //  Add to database:
    return $this->database->DBWriteAuthData($this, $hash, $name, $email,
                                            $org, $expiry);
  }

  //
  // JKF
  //
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

    $recordlist = $this->database->DBReadAuthData($authkey);
    if ( $recordlist && count($recordlist) ) {
      // @ob_end_clean(); //turn off output buffering to decrease cpu usage
      // We are over-quoting it! It gets quoted before going in the template
      // $name = htmlentities($recordlist[0]['FullName'], ENT_QUOTES, 'UTF-8');
      $name  = $recordlist[0]['FullName'];
      // Trim accidental whitespace, it's hard to detect and will cause failure
      $email = trim($recordlist[0]['Email']); // This is already checked carefully
      // We are over-quoting it! It gets quoted before going in the template
      // $org   = htmlentities($recordlist[0]['Organization'], ENT_QUOTES, 'UTF-8');
      $org   = $recordlist[0]['Organization'];
      $expiry= $recordlist[0]['Expiry'];
      return 1;
    }
    return 0;
  }

  public function DeleteAuthData(
    $authkey
  )
  {
    $authkey = preg_replace('/[^a-zA-Z0-9]/', '', $authkey);
    $this->database->DBDeleteAuthData($authkey);
  }

  public function PruneAuthData(
  )
  {
    global $NSSDROPBOX_PREFS;

    $old = time() - 86400; // 1 day ago
    $this->database->DBPruneAuthData($old);

    // 1 in 3 days, phone home with my root URL.
    // This is purely for ZendTo interests, the data is never
    // made public or sold or anything, don't worry!
    // I just like to have an idea where it is being used.
    if (rand(1,3) == 2) {
      $root = @$NSSDROPBOX_PREFS['serverRoot'];
      // Only want the domain name
      $domain = exec('hostname -d 2>/dev/null');
      exec('curl --fail'.
           ' -F '.escapeshellarg('hostdomain='.$domain).
           ' -F '.escapeshellarg('zendto='.$root).
           ' http://zend.to/et.php >/dev/null 2>&1');
    }
  }


  // Take an array of hashes of dropoffs,
  // delete the expired ones accurately.
  // marginsecs specifies the maximum time we can have until
  // the dropoff expires.
  public function TrimOffDying( $dropoffs , $marginsecs = 0) {
    $trimmed = array();

    if (is_array($dropoffs)) {
      $now = time();
      foreach ($dropoffs as $row) {
        $lifeseconds = $row['lifeseconds'];
        if (!$lifeseconds)
          $lifeseconds = $this->defaultLifetime()*3600*24;
        if ($now < timeForTimestamp($row['created']) +
                                    $lifeseconds -
                                    $marginsecs) {
          $trimmed[] = $row;
        }
      }
    }

    return $trimmed;
  }

  // Prune out all the dropoffs that have NOT expired yet
  public function TrimOffLive( $dropoffs , $marginsecs = 0 ) {
    $trimmed = array();

    if (is_array($dropoffs)) {
      $now = time();
      foreach ($dropoffs as $row) {
        $lifeseconds = $row['lifeseconds'];
        if (!$lifeseconds)
          $lifeseconds = $this->defaultLifetime()*3600*24;
        if ($now >= timeForTimestamp($row['created']) +
                                    $lifeseconds -
                                    $marginsecs) {
          $trimmed[] = $row;
        }
      }
    }

    return $trimmed;
  }

}

?>
