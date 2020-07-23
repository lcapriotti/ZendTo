<?PHP
//
// ZendTo
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
require_once(NSSDROPBOX_LIB_DIR."NSSUtils.php");

//
// I think they are trying to change locale.
// So firstly set up the new locale for them, when work out what the
// heck to do next so they wind up at roughly the form they were looking at.
//

function isLocaleValid( $l ) {
  return file_exists(NSSDROPBOX_BASE_DIR.'/config/locale/'.$l.'/LC_MESSAGES/zendto.mo');
}

$defaultLocale = 'en_US';
$currentLocale = 'en_US'; // Global (default value)
$localeCookieName = 'ZendTo-locale';

// Replace global default with their prefs setting if they have one
if (array_key_exists('language', $NSSDROPBOX_PREFS) &&
    $NSSDROPBOX_PREFS['language'] !== '') {
  $currentLocale = $NSSDROPBOX_PREFS['language'];
  // Assume that one is valid
  $defaultLocale = $currentLocale;
}

// Replace the prefs setting with their cookie value if there is one
if (isset($_COOKIE[$localeCookieName]) &&
    ($localeCookieValue = $_COOKIE[$localeCookieName])) {
  // They have requested a locale, does it exist on this installation?
  if (isLocaleValid($localeCookieValue))
    $currentLocale = $localeCookieValue;
  else
    $currentLocale = $defaultLocale; // Back off if it doesn't exist here
}
// But they might be actively requesting a change in locale
$localeChange = isset($_POST['locale'])?$_POST['locale']:(isset($_GET['locale'])?$_GET['locale']:NULL);
// If they are asking for a locale change that exists here, do it
if ($localeChange) {
  // They demanded a locale change, but is it valid?
  if (isLocaleValid($localeChange))
    $currentLocale = $localeChange;
  else
    $currentLocale = $defaultLocale; // No, so chuck them back to default
}

// Set cookie security
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
// If your serverRoot setting is https, ban cookies over http
global $cookie_secure;
$cookie_secure = false;
// Work out the server's root URL
global $NSSDROPBOX_URL;
$NSSDROPBOX_URL = serverRootURL($NSSDROPBOX_PREFS);
if (strpos($NSSDROPBOX_URL, 'https') === 0) {
  ini_set('session.cookie_secure', 1);
  $cookie_secure = true;
}

// Store the new locale back in a cookie
setcookie($localeCookieName,
          $currentLocale,
          time() + 3600*24*365*2, // Now + 2 years-ish = permanent-ish
          "/",
          "",
          $cookie_secure,
          TRUE);

// Actually set the locale.
putenv("LANGUAGE=$currentLocale");
putenv("LC_MESSAGES=$currentLocale");
putenv("LANG=$currentLocale");
// Try various different versions until one exists. These will need to
// already be installed on your system. Use 'locale -a' to list them all.
setlocale(LC_MESSAGES, $currentLocale."UTF-8", $currentLocale."utf-8",
                       $currentLocale."UTF8", $currentLocale."utf8",
                       $currentLocale);
bind_textdomain_codeset('zendto', 'UTF-8');
bindtextdomain('zendto', NSSDROPBOX_BASE_DIR.'/config/locale');
textdomain('zendto');

//
// Now we have to work out what page they were looking at and how they
// got there, so we can pretend as if we're just redrawing the same page
// they were looking at, but in a new language.
//
$gotHere  = @$_POST['gothere']; // The script filename that generated their view
$template = @$_POST['template']; // The template file that drew their view
$getData  = @$_POST['getdata'];
$postData = @$_POST['postdata'];
$getData  = html_entity_decode( $getData, ENT_QUOTES | ENT_XML1, 'UTF-8');
$postData = html_entity_decode( $postData, ENT_QUOTES | ENT_XML1, 'UTF-8');

// These are just PHP or template filenames, so sanitise HARD.
// But gotHere will include a URL path if they are running in a
// sub-folder of the root of the domain, and not at the root of the
// domain. So just pull out the leaf filename first and sanitise that.
$gotHere = preg_replace('/^.*\//', '', $gotHere);
$gotHere  = preg_replace('/[^a-zA-Z0-9_.-]+/', '', $gotHere);
$template = preg_replace('/[^a-zA-Z0-9_.-]+/', '', $template);

// getData and postData are JSON.
$getData  = json_decode( $getData, true );
$postData = json_decode( $postData, true );
// And if the decode failed, just blank them to empty arrays
if (!is_array($getData))
  $getData = array();
if (!is_array($postData))
  $postData = array();

// SAML login immediately after/before changing language can
// break this so gotHere is null. Main menu by default.
if (empty($gotHere))
  $gotHere = 'index.php';

// And a bit of tidying up for special cases
if (strpos($template, 'show_dropoff') !== false) {
  // Otherwise a language change on a drop-off download page will
  // attempt (and fail) to do the last download again in some places.
  unset($getData['fid']);
  unset($postData['fid']);
}

$_GET = $getData;
$_POST = $postData;
$php_self = $gotHere;

// And now for the magic...
include $gotHere;

?>