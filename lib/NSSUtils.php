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

/*!
  @function NSSError
  
  Generic error output routine.  If there's a remote IP in the $_SERVER
  global then we'll figure on HTML output.  Otherwise, we just do standard
  textual output.
*/
function NSSError(
  $text,
  $title = NULL
)
{
  global $smarty;
  global $pageErrorList;

  if ( isset($_SERVER['REMOTE_ADDR']) ) {
    $pageErrorList[] = array('title'=>$title, 'text'=>$text);
  } else {
    // Doesn't need translating as never sent to a web client
    printf("ZendTo Error: %s%s%s\n",($title ? $title : ""),($title ? " : " : ""),$text);
  }
}



/*!
  @function NSSFormattedMemSize
  
  Creates a string the gives a more human-readable memory size description.
  If $bytes is less than 1K then it returns $bytes plus the word "bytes";
  otherwise, the result is a floating-point value with one digit past the
  decimal and the appropriate label (KB, MB, or GB).
*/
function NSSFormattedMemSize(
  $bytes
)
{
  // This is now a global, so I can initialise it with function calls.
  global $NSSFormattedMemSize_Formats;
  // static $NSSFormattedMemSize_Formats = array (
  // gettext("%d bytes"),
  // gettext("%.1f KB"),
  // gettext("%.1f MB"),
  // gettext("%.1f GB"),
  // gettext("%.1f TB"));
  
  if ( floor($bytes) < 0.0 ) {
    //  Grrr...stupid 32-bit nonsense.  Convert to the positive
    //  value float-wise:
    $bytes = (floor($bytes) & 0x7FFFFFFF) + 2147483648.0;
  } else if ( floor($bytes) < 1.0 ) {
    return gettext("0 bytes");
  } else if ( floor($bytes) < 2.0 ) {
    return gettext("1 byte");
  }

  
  $unitIdx = floor(log($bytes = abs($bytes)) / log(1024));
  $unitIdx = ( ($unitIdx < count($NSSFormattedMemSize_Formats)) ? $unitIdx : count($NSSFormattedMemSize_Formats) );
  return sprintf($NSSFormattedMemSize_Formats[$unitIdx],($unitIdx ? $bytes / pow(1024.0,$unitIdx) : $bytes));
}



/*!
  @function NSSGenerateCode
  
  Generate a random, alphanumeric code string.  The length is by-default 16
  characters.
  
  The characters are chosen from the $NSSGenerateCode_CharSet variable at
  indices dictated by $codeLength sequential calls to the PHP mt_rand()
  random number generator.
*/
function NSSGenerateCode(
  $codeLength = 16
)
{
  static $NSSGenerateCode_CharSet = "abcdefghijkmnopqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789";
  $code = "";
  $count = $codeLength;
  $size = strlen($NSSGenerateCode_CharSet) - 1;
  if (function_exists('random_int')) {
    while ( $count-- ) {
      $code .= substr($NSSGenerateCode_CharSet, random_int(0,$size), 1);
    }
  } else {
    while ( $count-- ) {
      $code .= substr($NSSGenerateCode_CharSet, mt_rand(0,$size), 1);
    }
  }
  return $code;
}



/*!
  @function NSSGenerateCookieSecret
  
  Generates a 64-character hexadecimal string (a'la an MD5 checksum) to use
  in HTML cookies.  Two methods for this:  dump a 1024 byte chunk of random
  memory out of /dev/random and compute its MD5 checksum; or, use the built-in
  extended random generator to create the 32 bytes.
  Make it SODIUM_CRYPTO_SECRETBOX_KEYBYTES (32) bytes in length (2 hex digits
  per byte) so it is big enough to use as an encryption key.
*/
function NSSGenerateCookieSecret()
{
  if (function_exists('random_bytes'))
    return bin2hex(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));

  // No "random_bytes()" for some strange reason, so do it the bad way.
  // With PHP 7 this should never happen.
  $sum = "";
  $count = SODIUM_CRYPTO_SECRETBOX_KEYBYTES;
  while ( $count-- ) {
    $sum .= sprintf("%02X",mt_rand(0,255));
  }
  return $sum;
}


/*!
  @function rmdir_r
  
  Recursive directory removal. Returns TRUE on success or FALSE on failure.
*/
function rmdir_r(
  $path
)
{
  if ( is_dir($path) ) {
    $files = array_diff(scandir($path), array('..', '.'));
    foreach ( $files as $file ) {
      $f = $path . DIRECTORY_SEPARATOR . $file;
      if ( is_dir($f) ) {
        rmdir_r($f);
      } else if ( !unlink($f) ) {
        return FALSE;
      }
    }
    if ( rmdir($path) ) {
      return TRUE;
    }
  }
  return FALSE;
}

/* Strip slashes except when get_magic_quotes is enabled. */
function paramPrepare(
  $value
)
{
  // Since PHP 5.4.0 get_magic_quotes_gpc() will always return FALSE.
  // So this function now does absolutely nothing.
  // return get_magic_quotes_gpc()?stripslashes($value):$value;
  return $value;
}

/* Reduce a list of (name,email) pairs down to the list with unique emails */
function uniqueifyRecipients(
  &$in
)
{
  $out = array();
  foreach ($in as $recip) {
    $email2name[$recip[1]] = $recip[0];
  }
  foreach ($email2name as $email => $name) {
    $recip = array($name?$name:"", $email);
    $out[] = $recip;
  }
  $in = $out;
}

/* Fetch the REMOTE_ADDR of the http connection, even through proxies */
function getClientIP($prefs)
{
  $useheaders = @$prefs['behindLoadBalancer'];
  // Get the forwarded IP if it exists
  if ( $useheaders && isset($_SERVER['HTTP_CLIENT_IP']) && array_key_exists('HTTP_CLIENT_IP', $_SERVER) ) {
    $the_ip = $_SERVER['HTTP_CLIENT_IP'];
  } elseif ( $useheaders && isset($_SERVER['HTTP_X_FORWARDED_FOR']) && array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER )) {
    $the_ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $the_ip = trim($the_ips[0]);
  } elseif ( isset($_SERVER['REMOTE_ADDR']) ) {
    $the_ip = $_SERVER['REMOTE_ADDR'];
  } else {
    $the_ip = '0.0.0.0';
  }

  // Now validate it
  $filtered_ip = filter_var($the_ip, FILTER_VALIDATE_IP);
  if ($filtered_ip) {
    $the_ip = $filtered_ip;
  } else {
    $the_ip = '0.0.0.0';
  }

  return $the_ip;
}

/* Calculate the checksum of the given filename. */
function checksumFile(
        $cmd, $filename
)
{
  $output = exec($cmd . ' ' . escapeshellarg($filename));

  # The $output will look like a string of hex digits followed by other
  # stuff we don't care about (such as whitespace & the filename).
  $matches = array();
  $checksum = '';
  if (preg_match('/^([0-9A-Fa-f]+)/', $output, $matches)) {
    $checksum = strtoupper($matches[1]);
  }
  return $checksum;
}

/* Read a password prompt from stdin without echoing it */
function readPassword(
  $prompt = "Enter Password: "
)
{
  $command = "env bash -c 'read -s -p \"" . addslashes($prompt) .
    "\" mypassword && echo -n \$mypassword'";
  $password = shell_exec($command);
  echo "\n";
  return $password;
}

/* Work out the root URL of this server */
/* Have to pass in the $NSSDROPBOX_PREFS as we can't get at it otherwise */
function serverRootURL($prefs)
{

  // If it's in the preferences.php then use the value there always.
  if ( array_key_exists('serverRoot', $prefs) &&
       $prefs['serverRoot'] !== '' &&
       !preg_match('/zendto\.soton\.ac\.uk/', $prefs['serverRoot']) ) {
    $rootURL = @$prefs['serverRoot'];
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
      $rootURL = "http".($https ? "s" : "")."://".@$_SERVER['SERVER_NAME'].$port.@$_SERVER['REQUEST_URI'];
      // Delete anything after a ? (and the ? itself)
      $rootURL = preg_replace('/\?.*$/', '', $rootURL);
      // Should now end in blahblah.php or simply a directory /
      if ( !preg_match('/\/$/',$rootURL) ) {
        // Delete anything after the last / (but leave the /)
        $rootURL = preg_replace('/\/[^\/]+$/','/',$rootURL);
      }
    } else {
      // We are running from the cli, so a very poor guess.
      $rootURL = 'http://'.php_uname('n').'/';
    }
  }
  // If it doesn't end with a / then we'll add one to be on the safe side!
  if (!preg_match('/\/$/', $rootURL)) {
    $rootURL .= '/';
  }

  return $rootURL;
}

/* Send security-related HTTP headers when appropriate */
/* Have to pass in the $NSSDROPBOX_PREFS as we can't get at it otherwise */
function sendHTTPSecurity($prefs)
{
  // If we're a web page, output any extra headers needed
  if (isset($_SERVER['REMOTE_ADDR'])) {
    // This stops ZendTo being put in an iframe unless it's
    // being done by the ZendTo server itself for some readson.
    header('X-XSS-Protection: 1; mode=block');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    // Get the value of the X-Frame-Options header from preferences.php
    // Should I forcibly upper-case it??
    $xfo = 'sameorigin'; // Default value
    if (array_key_exists('X-Frame-Options', $prefs)) {
      $xfo = $prefs['X-Frame-Options'];
    }
    // Don't send the header at all if the string is ''
    if ($xfo !== '') {
      header('X-Frame-Options: ' . $xfo);
    }
  }
}

/* Given the RECAPTCHA site key,
   return the HTML required for the CAPTCHA */
/* If they are using the invisible Google CAPTCHA, just
   generate the code for the button instead. */
function recaptcha_get_html(
  $siteKey, $invisible=FALSE
)
{
  if ($invisible) {
    return 'class="g-recaptcha" data-sitekey="' . $siteKey .
           '" data-callback="submitform" type="button"';
  } else {
    // Not using the invisible Google reCAPTCHA
    //return '<div class="g-recaptcha" data-sitekey="' . $siteKey .
    //       '"></div>' . "\n";
    return $siteKey; // Do it explicitly now, so totally new code
  }
}

/* Encrypt and replace a file using the supplied password and IV.
   Try to avoid making copies of the password and IV all over the stack.
*/
function encryptAndReplace(
  $aDropbox, $filename, &$password, &$iv
)
{
  // get permissions of input file
  clearstatcache();
  $perms = fileperms($filename) & 0777;

  $encFile = tempnam(dirname($filename), 'enc-');
  if (encryptFile($aDropbox, $filename, $encFile, $password, $iv)) {
    // Encryption succeeded.
    // Move the encrypted version back over the top of the original.
    // Note the original might be a soft-link.
    // In which case delete the soft-link on the way.
    rename($encFile, $filename);
    // Re-apply permissions from original
    chmod($filename, $perms);
    return TRUE;
  } else {
    unlink($encFile);
  }

  // If we got here, it failed
  return FALSE;
}


/* Encrypt a file using the supplied password and IV (initialisation vector).
   This assumes the libsodium PHP (7.2+) extension, or the libsodium PECL
   module (7.0+) is installed. */
function encryptFile(
   $aDropbox, $in_filename, $out_filename, $password, $iv
)
{
  // Encrypt the file in chunks, so we can handle huge files
  // This is written into the header of the encrypted file, so it
  // doesn't matter if we change this value later.
  $chunk_size = 64*1024; // 64 KB chunks

  // Open the input and output files
  if (!file_exists($in_filename) ||
      ($fd_in  = fopen($in_filename, 'rb')) === false) {
    $aDropbox->writeToLog(sprintf("Error: Encryption failed to open input file %s", $in_filename));
    return false;
  }
  if (!file_exists($out_filename) ||
      ($fd_out = fopen($out_filename, 'wb')) === false) {
    $aDropbox->writeToLog(sprintf("Error: Encryption failed to open output file %s", $out_filename));
    return false;
  }

  // Need to set the OPSLIMIT and MEMLIMIT appropriately, RTFM.
  // Reduced both from MODERATE to INTERACTIVE, as MODERATE uses
  // over 268MB RAM each time. Multiple simultaneous decryptions
  // can therefore demand huge amounts of RAM. INTERACTIVE only
  // only uses 67MB, and is much faster too.
  $alg = SODIUM_CRYPTO_PWHASH_ALG_DEFAULT;
  $opslimit = SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE;
  $memlimit = SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE;

  // Salt for producing a key from their password
  $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);

  // Write a header of everything we need to re-create the key
  // from a guess at the password
  fwrite($fd_out, pack('P', $alg));
  fwrite($fd_out, pack('P', $opslimit));
  fwrite($fd_out, pack('P', $memlimit));
  fwrite($fd_out, pack('P', $chunk_size)); // JKF Added this
  fwrite($fd_out, pack('P', filesize($in_filename))); // JKF Added this
  fwrite($fd_out, $salt);
  // echo "Have written ".ftell($fd_out)." bytes of header\n";

  // Generate the key from their crappy password
  $key = sodium_crypto_pwhash(
           SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES,
           $password, $salt, $opslimit, $memlimit, $alg);

  // Create a stream using the key and an internally-generated IV.
  // Put the result into the header of length
  // crypto_secretstream_xchacha20poly1305_HEADERBYTES
  list($stream, $header) =
          sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
  // Write the header
  if (!fwrite($fd_out, $header)) {
    $aDropbox->writeToLog(sprintf("Error: Encryption failed to write the encryption metadata header to %s", $out_filename));
    if (is_string($header)) sodium_memzero($header);
    if (is_string($key))    sodium_memzero($key);
    if (is_string($salt))   sodium_memzero($salt);
    if (is_string($header)) sodium_memzero($stream);
    return false;
  }

  // echo "Now written a total of ".ftell($fd_out)." bytes\n";
  // echo "That should be the previous number + ".
  //      SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES."\n";

  // Need to know the file size so we can accurately detect the last block
  // of a file that is an exact multiple of the $chunk_size. Otherwise
  // feof() won't be true after reading the last chunk (nasty PHP!), but
  // we'll only be able to read 0 bytes of the next chunk so we'll have
  // a null chunk that we can't encrypt. :-(
  $in_size = fstat($fd_in)['size'];

  // Tags are either 'message' (we're in the stream) or 'final' (at the end)
  $tag = SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
  $ok = true;
  do {
    $chunk = fread($fd_in, $chunk_size);
    if ($chunk === FALSE) {
      $aDropbox->writeToLog(sprintf("Error: Encryption failed to read data chunk from %s", $in_filename));
      $ok = false;
      break;
    }
    // echo "Length of chunk is ".strlen($chunk)."\n";
    // Note that if the file was an exact multiple of $chunk_size then when
    // we have read the last block feof() will be FALSE!
    // But ftell() will tell us we are 1 byte beyond the last in the file.
    if (ftell($fd_in) === $in_size || feof($fd_in)) {
      // We have reached the end of the plaintext, so we are at the end.
      $tag = SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
    }
    // The '' can be used to supply an IV as well.
    $encrypted_chunk = sodium_crypto_secretstream_xchacha20poly1305_push(
                         $stream, $chunk, $iv, $tag);
    // echo "Length of encrypted chunk is ".strlen($encrypted_chunk)."\n";
    if (fwrite($fd_out, $encrypted_chunk) === FALSE) {
      $ok = false;
      break;
    }

    // Only use the IV on the very first chunk, not necessary after that.
    if (is_string($iv)) sodium_memzero($iv);
    $iv = '';
  } while ($tag !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);

  // Wipe memory properly
  if (is_string($stream))          sodium_memzero($stream);
  if (is_string($header))          sodium_memzero($header);
  if (is_string($key))             sodium_memzero($key);
  if (is_string($salt))            sodium_memzero($salt);
  if (is_string($encrypted_chunk)) sodium_memzero($encrypted_chunk);
  if (is_string($chunk))           sodium_memzero($chunk);

  // Close both files
  if (!fclose($fd_in) || !fclose($fd_out)) {
    $aDropbox->writeToLog("Error: Encryption failed to close the input and output files");
    return false;
  }

  return $ok;
}

/* This function is only used by the command-line util provided to decrypt a
   drop-off. It is not used by the web app itself, that's done elsewhere. */

/* Decrypt a file using the supplied password and IV (initialisation vector).
   Note the output is a file handle returned by fdopen(...,'wb'),
   not a filename. This means we never leave stray crap in the filesystem.
   Returns true if all went okay, false if not. Tries very hard to bail out
   before outputting anything to out_fd if it's likely to fail at all.
   This assumes the libsodium PHP (7.2+) extension, or the libsodium PECL
   module (7.0+) is installed. */
function decryptAndSendFile(
   $in_filename, $out_fd, $password, $iv
)
{

  $chunk_size = 0;
  $alg = '';
  $opslimit = '';
  $memlimit = '';

  // Open the input and output files
  $fd_in  = fopen($in_filename, 'rb');
  $fd_out = $out_fd;
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

  do {
    // That ABYTES constant is difference between encrypted length & plaintext
    $chunk = fread($fd_in,
                   $chunk_size + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES);
    // If there were exactly the number of bytes left to read as we just
    // asked it to, then feof() will be false, despite us actually being
    // at the end of the file. So compare where we are with the size of
    // the file too, as that will tell us for sure.
    $hit_eof = (feof($fd_in) || ftell($fd_in) === $in_size);
  
    // Decrypt the next chunk from the stream
    $res = sodium_crypto_secretstream_xchacha20poly1305_pull($stream, $chunk, $iv);
    if ($res === FALSE) {
      // Decryption failed for some reason!
      $decrypt_failed = true;
      // Wipe the IV from memory properly, rest is done after this loop
      if (is_string($iv)) sodium_memzero($iv);
      break;
    } else {
      list($decrypted_chunk, $tag) = $res;
      fwrite($fd_out, $decrypted_chunk);
      // Only use the IV on the very first chunk
      if (is_string($iv)) sodium_memzero($iv);
      $iv = '';

      $hit_finaltag = ($tag ===
                       SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);
    }
  } while (!$hit_eof && !$hit_finaltag && !$decrypt_failed);

  // Wipe the secret bits from memory properly
  if (is_string($password))        sodium_memzero($password);
  if (is_string($key))             sodium_memzero($key);
  if (is_string($stream))          sodium_memzero($stream);
  if (is_string($res))             sodium_memzero($res);
  if (isset($decrypted_chunk) && is_string($decrypted_chunk))
                                   sodium_memzero($decrypted_chunk);

  fclose($fd_out);
  fclose($fd_in);
  if (is_string($fd_in))  sodium_memzero($fd_in);
  if (is_string($fd_out)) sodium_memzero($fd_out);

  // Did it succeed or not?
  // Should be at end of file *and* have hit 'end' tag at the same time.
  // And of course the decrypt calls must have succeeded.
  $ok = ($hit_eof && $hit_finaltag && !$decrypt_failed);
  
  return $ok;
}

// Reduce the "senderIP" field of the drop-off table to *just* the sender IP.
function stripToSenderIP(
  $IPstring
)
{
  $words = explode('|', $IPstring);
  return $words[0];
}

// Does a string end with a sub-string?
function str_ends($haystack,  $needle) {
  return 0 === substr_compare($haystack, $needle, -strlen($needle));
}

// Obfuscate a binary string. Used in encryption/decryption of drop-offs.
// (But only for concealing IV data when stored in the DB.
//  NOT used in any way as an encryption algorithm!!)
// Changing this algorithm will break all existing encrypted drop-offs.
// Returns a binary string, *not* text.
// If you want text, call sodium_bin2hex() on the result.
function obfuscateString( $in ) {
  $out = '';
  for ($i=0; $i<strlen($in); $i++) {
    if ($i==0)
      $out .= $in[$i] ^ 'Z';
    else
      $out .= $in[$i] ^ $out[$i-1];
  }
  $out = strrev($out);
  return $out;
}

// De-obfuscate a binary string. Used in encryption/decryption of drop-offs.
// (But only for concealing IV data when stored in the DB.
//  NOT used in any way as an encryption algorithm!!)
// Changing this algorithm will break all existing encrypted drop-offs.
function deobfuscateString( $in ) {
  $out = '';
  $in = strrev($in);
  for ($i=0; $i<strlen($in); $i++) {
    if ($i==0)
      $out .= $in[$i] ^ 'Z';
    else
      $out .= $in[$i] ^ $in[$i-1];
  }
  return $out;
}

// Make an encryption key from what is hopefully a long enough random
// string of hex bytes (output of bin2hex).
// If it's okay but too short, repeat it.
// Return '' if we can't do it because it's just way too short.
// Further up the call stack, it will be logged loudly if the string of
// hex bytes is not good enough.
// This is used when the encryption key for a drop-off has been set in a
// request-for-drop-off, and hence sadly has to be stored in the DB.
// As soon as the drop-off is sent (long before it is picked up), this
// string becomes totally useless and is deleted from the DB anyway.
function hex2key( $hexsecret ) {
  // Try to make the hexsecret actually hex.
  $secret = strtolower($hexsecret); // Don't overwrite hexsecret so we can 0 it
  $secret = preg_replace("/[^0-9a-f]/", "", $secret);
  // Need an even number of hex characters
  if (strlen($secret) % 2 == 1)
    $secret = substr($secret, 0, -1);

  // If it's way too short, fail
  if (strlen($secret)<10) {
    sodium_memzero($hexsecret);
    sodium_memzero($secret);
    return '';
  }

  // Convert the bin2hex-ed secret into a binary one
  try {
    $binsecret = sodium_hex2bin($secret);
  } catch (SodiumException $e) {
    // If it couldn't hex2bin it, fail
    sodium_memzero($hexsecret);
    sodium_memzero($secret);
    return '';
  }
  sodium_memzero($hexsecret);
  sodium_memzero($secret);

  // Make a binary string at least SODIUM_CRYPTO_SECRETBOX_KEYBYTES long.
  $keytemplate = $binsecret;
  while (strlen($keytemplate) < SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
    $keytemplate .=  $binsecret;
  }
  sodium_memzero($binsecret);

  // And trim it to exactly the right length
  $key = substr($keytemplate, 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
  sodium_memzero($keytemplate);

  return $key;
}


// Encrypt a passphrase. Takes an ascii string and returns one.
// hexsecret is a bin2hex-ed string that is hopefully random.
// This is used when the encryption key for a drop-off has been set in a
// request-for-drop-off, and hence sadly has to be stored in the DB.
// As soon as the drop-off is sent (long before it is picked up), this
// string becomes totally useless and is deleted from the DB anyway.
function encryptForDB( $in, $hexsecret ) {
  // Badly construct a key from the possibly bin2hex-ed string
  $key = hex2key($hexsecret);
  sodium_memzero($hexsecret);

  if ($key === '') {
    // It failed, so do it the crappy way.
    return sodium_bin2hex('BadKey' . obfuscateString($in));
  }

  // Make a random nonce and encrypt our input string
  $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
  $ciphertext = sodium_crypto_secretbox($in, $nonce, $key);
  sodium_memzero($in);
  sodium_memzero($key);

  // Glue them together and return
  return sodium_bin2hex($nonce . $ciphertext);
}

// Decrypt a passphrase. Takes a hex string and returns an ascii one.
// hexsecret is a bin2hex-ed string that is vaguely random.
// This is used when the encryption key for a drop-off has been set in a
// request-for-drop-off, and hence sadly has to be stored in the DB.
// As soon as the drop-off is sent (long before it is picked up), this
// string becomes totally useless and is deleted from the DB anyway.
function decryptForDB( $hexin, $hexsecret ) {
  // Convert it back to binary, carefully.
  try {
    $in = sodium_hex2bin($hexin);
  } catch (SodiumException $e) {
    // If it couldn't hex2bin it, fail
    sodium_memzero($hexsecret);
    sodium_memzero($hexin);
    return '';
  }
  sodium_memzero($hexin);

  // Did the encryptForDB() that made this, fail?
  if (substr($in, 0, 6) === 'BadKey') {
    sodium_memzero($hexsecret);
    return deobfuscateString(substr($in, 6));
  }

  // Badly construct a key from the possibly bin2hex-ed string
  $key = hex2key($hexsecret);
  sodium_memzero($hexsecret);

  if ($key === '') {
    // It failed. Shouldn't happen as we managed it when we
    // encryptForDB-ed the string in the first place!
    sodium_memzero($in);
    return '';
  }

  $nonce = substr($in, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
  $ciphertext = substr($in, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
  $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
  sodium_memzero($nonce);
  sodium_memzero($key);
  sodium_memzero($ciphertext);
  if ($plaintext === FALSE) {
    return '';
  }
  return $plaintext;
}

?>
