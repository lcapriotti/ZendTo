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

require "../config/preferences.php";
require_once(NSSDROPBOX_LIB_DIR."Smartyconf.php");
require_once(NSSDROPBOX_LIB_DIR."NSSDropbox.php");

// Size of in-memory buffer used when copying in->out
$fileCopyBufferSize = 10 * 1024 * 1000;

if ( $theDropbox = new NSSDropbox($NSSDROPBOX_PREFS) ) {

  // We have already checked the username.
  // The extra data in the form is tiny compared with the chunk
  // size (hopefully >=99 MB).

  // Append this data to the file given by its file number.
  $dirName = ini_get('upload_tmp_dir');
  if (empty($dirName)) {
    $theDropbox->writeToLog("Error: upload_tmp_dir is not set in php.ini");
    print("Error: upload_tmp_dir is not set in php.ini. Contact your systems administrator");
    exit;
  }
  // Add trailing / if not already there 
  if (substr($dirName, -1) !== DIRECTORY_SEPARATOR)
    $dirName .= DIRECTORY_SEPARATOR;

  $name = @$_POST['chunkName'];
  $nameLen = strlen(@$_POST['chunkName']);
  // Sanitise the chunkName
  $lastElement = preg_replace('/[^0-9a-zA-Z]/', '', $name);
  $lastElement = substr($lastElement, 0, 65); // chunkName should be max 32 long
  if ($nameLen == 0) {
    $theDropbox->writeToLog("Error: chunk name missing");
    print("Error: chunk name missing");
    exit;
  }
  if ($nameLen > 65) {
    $theDropbox->writeToLog("Error: chunk name '".$name."' is too long");
    print("Error: chunk name too long");
    exit;
  }
  // Does this look valid?
  if ($lastElement !== $name) {
    $theDropbox->writeToLog("Error: chunk name '".$name."' contains invalid characters I did not generate");
    print("Error: chunk name contains bad characters");
    exit;
  }
  $dirName .= $lastElement;

  // Read which uploaded file this is part of.
  // Only contains digits.
  $number = @$_POST['chunkOf'];
  $fileNum = preg_replace('/[^0-9]/', '', $number);
  if ($fileNum <= 0) {
    $theDropbox->writeToLog("Error: Missing/bad chunk number '".$number."'");
    print("Error: Missing/bad chunk number");
    exit;
  }

  $chunkFile = $_FILES['chunkData'];

  // Catch upload errors before we go any further!
  if ( in_array('error', $chunkFile) )
    $errorNum = $chunkFile['error'];
  else
    $errorNum = NULL;

  $retry = FALSE;
  if ( $errorNum !== UPLOAD_ERR_OK ) {
    $error = '';
    switch ( $errorNum ) {
      // These are the 2 useful ones!
      case UPLOAD_ERR_PARTIAL:
        $error = "The upload failed to complete.";
        $retry = TRUE;
        break;
      case UPLOAD_ERR_NO_FILE:
        $error = "The upload never got going.";
        $retry = TRUE;
        break;
      // All the rest are configuration errors. Just log them.
      case UPLOAD_ERR_INI_SIZE:
        $error = "The uploadChunkSize defined in preferences.php is bigger than the max upload size or max request size set in php.ini.";
        break;
      case UPLOAD_ERR_FORM_SIZE:
        $error = "The uploadChunkSize defined in preferences.php is bigger than the maxBytesForFile setting there.";
        break;
      case UPLOAD_ERR_NO_TMP_DIR:
        $error = "The server has no temporary upload folder configured in php.ini.";
        break;
      case UPLOAD_ERR_CANT_WRITE:
        $error = "The temporary upload folder configured in php.ini cannot be written to by the web server.";
        break;
      default:
        $error = "Unknown upload error ".print_r($errorNum, TRUE);
        $retry = TRUE;
        break;
    }
    $theDropbox->writeToLog('Upload error: ' .
      $theDropbox->authorizedUser() . ' ' .
      $error);
    // Trigger a retry if it is not a configuration error
    if ($retry)
      print "Retry: Failed because " . $error;
    else
      print "Error: Failed because " . $error;
    // It failed, so bail out
    exit;
  }

  $chunkName = $chunkFile['tmp_name'];

  $outName = $dirName . '.' . $fileNum;
  $infile = fopen($chunkName, 'rb'); // read-only, binary
  $outfile = fopen($outName, 'ab'); // append-only, create if needed, binary
  if (!$infile) {
    $theDropbox->writeToLog("Error: Opening chunk from $chunkName failed");
    print("Error: Failed to open new chunk for reading");
    exit;
  }
  if (!$outfile) {
    $theDropbox->writeToLog("Error: Opening temp file $outName for appending failed");
    print("Error: Failed to open temp file for appending");
    exit;
  }
  $buffer = '';
  $totalWritten = 0;
  while (!feof($infile)) {
    $buffer = fread($infile, $fileCopyBufferSize);
    // Check fread worked
    if ($buffer === FALSE) {
      $theDropbox->writeToLog("Error: Reading upload chunk $chunkName failed");
      print("Error: Failed to read chunk");
      exit;
    }
    $bytesWritten = fwrite($outfile, $buffer);
    if ($bytesWritten === FALSE) {
      $theDropbox->writeToLog("Error: Writing upload chunk to $outName failed");
      print("Error: Failed to write chunk");
      exit;
    }
    $totalWritten += $bytesWritten;
    if ($totalWritten > $theDropbox->maxBytesForFile()) {
      $theDropbox->writeToLog("Error: User attempted to upload chunked file bigger than maxBytesForFile setting");
      break;
    }
  }
  fclose($infile);
  fclose($outfile);
  print("Success"); // This string tells the AJAX code it worked.
}

?>
