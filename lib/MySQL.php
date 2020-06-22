<?php
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
// This is the Sql class for SQLite
//

Class Sql {

public $database = NULL;
public $dbname = NULL;
public $_newDatabase = FALSE;
private $dropbox = NULL;

public function __construct( $prefs, $aDropbox ) {
  $this->dropbox = $aDropbox; // Allows me to write to ZendTo log
  $this->DBConnect($prefs);
}  

//
// Database functions that work on NSSDropbox objects
//

private function DBConnect ( $prefs ) {
  $this->dbname = $prefs['MySQLdb']; // For nightly schema checking code
  $this->database = new mysqli($prefs['MySQLhost'],
                               $prefs['MySQLuser'],
                               $prefs['MySQLpassword'],
                               $prefs['MySQLdb']);
  if ( ! $this->database ) {
    NSSError(sprintf(gettext("Could not open MySQL database on %s"), $prefs['MySQLhost']), gettext("Database Error"));
    return FALSE;
  }
  if ($this->database->connect_errno) {
    $this->dropbox->writeToLog(sprintf("Error: Coud not open MySQL database on %s. Error was %s", $prefs['MySQLhost'], $this->database->connect_error));
    return FALSE;
  }
  // Want to auto-commit except when I'm manually doing a transaction,
  // so don't set this after all.
  //// Switch off auto-commit and do transactions manually
  //mysqli_autocommit($this->database, FALSE);
  return TRUE;
}

public function DBCreateReq() {
  return TRUE;
}

// Needs to be public for people upgrading from Dropbox 2
public function DBCreateAuth() {
  return TRUE;
}

// Needs to be public for people upgrading from earlier versions
public function DBCreateUser() {
  return TRUE;
}

// Needs to be public for people upgrading from earlier versions
public function DBCreateRegexps() {
  return TRUE;
}

// Needs to be public for people upgrading from earlier versions
public function DBCreateLoginlog() {
  return TRUE;
}

// Needs to be public for people upgrading from earlier versions
public function DBCreateLibraryDesc() {
  return TRUE;
}

// Needs to be public for people upgrading from earlier versions
public function DBCreateAddressbook() {
  return TRUE;
}

public function DBAddLoginlog($user) {
  $query = sprintf("INSERT INTO loginlog
                    (username, created)
                    VALUES
                    ('%s','%u')",
                    $this->database->real_escape_string(strtolower($user)),
                    time());
  if (!$this->database->query($query)) {
    $this->dropbox->writeToLog(sprintf("Error: Failed to add login record for %s. Error was %s", strtolower($user), $this->database->error));
    return gettext("Failed to add login record");
  }
  return '';
}

public function DBDeleteLoginlog($user) {
  if ($user == "") {
    $query = "DELETE FROM loginlog";
  } else {
    $query = sprintf("DELETE FROM loginlog WHERE username = '%s'",
                     $this->database->real_escape_string(strtolower($user)));
  }
  if ( !$this->database->query($query) ) {
    $this->dropbox->writeToLog(sprintf("Error: Failed to delete login record for %s. Error was %s", strtolower($user), $this->database->error));
    return gettext("Failed to delete login records");
  }
  return '';
}

public function DBLoginlogLength($user, $since) {
  $query = sprintf("SELECT count(*) FROM loginlog
                    WHERE username = '%s' AND created > '%u'",
                   $this->database->real_escape_string(strtolower($user)),
                   $since);
  $res = $this->database->query($query);
  $line = $res->fetch_array(MYSQLI_NUM);
  return $line[0]; // Return the 1st field of the 1st line of the result
}

public function DBLoginlogAll($since) {
  $res = $this->database->query(
             sprintf("SELECT * FROM loginlog WHERE created > '%u'",
                     $since));
  $i = 0;
  $qResult = array();
  while ($line = $res->fetch_array()) {
    $qResult[$i++] = $line;
  }
  return $qResult;
}


// Delete the old regexps of this type and add the new ones
public function DBOverwriteRegexps($type, &$regexplist) {

  $now = time();

  if (!$this->DBStartTran()) {
    return gettext("Failed to BEGIN transaction block while updating regexps");
  }

  // Delete the old ones
  if (!$this->database->query(
              sprintf("DELETE FROM regexps WHERE type = %d", $type))) {
    $this->dropbox->writeToLog(sprintf("Error: Failed to delete delete old regexps. Error was %s", $this->database->error));
    if (!$this->DBRollbackTran()) {
      return gettext("Failed to ROLLBACK after aborting deletion of old regexps");
    }
    return gettext("Failed to delete old regexps");
  }

  // Add the new ones
  foreach ($regexplist as $re) {
    $query = sprintf("INSERT INTO regexps
                      (type, re, created)
                      VALUES
                      (%d,'%s','%u')",
                      $type,
                      $this->database->real_escape_string($re),
                      $now);
    if (!$this->database->query($query)) {
      $this->dropbox->writeToLog(sprintf("Error: Failed to add new regexps. Error was %s", $this->database->error));
      if (!$this->DBRollbackTran()) {
        return gettext("Failed to ROLLBACK after aborting addition of regexp");
      }
      return gettext("Failed to add regexp");
    }
  }

  // Yay! Success!
  $this->DBCommitTran();
  return '';
}

// List all the regexps matching the given type number
public function DBReadRegexps($type) {
  $res = $this->database->query(
            sprintf("SELECT re,created FROM regexps WHERE type = %d",
                    $type));
  $i = 0;
  $qResult = array();
  while ($line = $res->fetch_array()) {
    $qResult[$i++] = $line;
  }
  return $qResult;
}


public function DBReadLocalUser( $username ) {
  $res = $this->database->query(
            sprintf("SELECT * FROM usertable WHERE username = '%s'",
                    $this->database->real_escape_string($username)));
  $i = 0;
  $qResult = array();
  while ($line = $res->fetch_array()) {
    $qResult[$i++] = $line;
  }
  return $qResult;
}

// Add a new user to the local authentication table.
// Returns an error string, or '' on success.
// Checks to ensure user does not exist first!
public function DBAddLocalUser( $username, $password, $email, $name, $org, $quota=1 ) {
  if ($this->DBReadLocalUser($username)) {
    return sprintf(gettext("Aborting adding user %s as that user already exists"), $username);
  }

  if (!$this->DBStartTran()) {
    return sprintf(gettext("Failed to BEGIN transaction block while adding user %s"), $username);
  }

  // Use username as a default value for real name in case it's not set
  if ($name == '') {
    $name = $username;
  }

  $query = sprintf("INSERT INTO usertable
                    (username, password, mail, displayname, organization)
                    VALUES
                    ('%s','%s','%s','%s','%s')",
                    $this->database->real_escape_string($username),
                    password_hash($password, PASSWORD_DEFAULT),
                    $this->database->real_escape_string($email),
                    $this->database->real_escape_string($name),
                    $this->database->real_escape_string($org));

  if (!$this->database->query($query)) {
    $this->dropbox->writeToLog(sprintf("Error: Failed to add new local user %s. Error was %s", $username, $this->database->error));
    if (!$this->DBRollbackTran()) {
      return sprintf(gettext("Failed to ROLLBACK after aborting addition of user %s"), $username);
    }
    return sprintf(gettext("Failed to add user %s"), $username);
  }

  // Yay! Success!
  $this->DBCommitTran();
  return '';
}

// Delete a user from the local authentication table.
// Returns '' on success.
public function DBDeleteLocalUser ( $username ) {
  if (!$this->DBReadLocalUser($username)) {
    return sprintf(gettext("Aborting deleting user %s as that user does not exist"), $username);
  }

  if (!$this->database->query(
    sprintf("DELETE FROM usertable WHERE username = '%s'",
            $this->database->real_escape_string($username)))) {
    $this->dropbox->writeToLog(sprintf("Error: Failed to delete local user %s. Error was %s", $username, $this->database->error));
  }
  return '';
}

// Update an existing user's password.
// Returns '' on success.
public function DBUpdatePasswordLocalUser ( $username, $password ) {
  if (!$this->DBReadLocalUser($username)) {
    return sprintf(gettext("Aborting updating user %s as that user does not exist"), $username);
  }

  // This check will get done every time cleanup.php is run anyway.
  // But in case they try to change a local user's password before
  // that has a chance to fix the schema (longer password field),
  // check the schema here too, and update it if necessary.
  //
  // Read the data type and length of the usertable "password" column.
  $res = $this->database->query(
    "select data_type,character_maximum_length from information_schema.columns where table_schema='".$this->dbname."' and table_name='usertable' and column_name='password'");
  $line = $res->fetch_array();
  // If it is a short varchar, change it to varchar(256)
  if (strcasecmp($line[0], 'varchar') == 0 && $line[1] < 255) {
    $this->database->query("ALTER TABLE usertable MODIFY password varchar(256) NOT NULL");
  }

  $query = sprintf("UPDATE usertable
                    SET password='%s'
                    WHERE username='%s'",
                    password_hash($password, PASSWORD_DEFAULT),
                    $this->database->real_escape_string($username));
  if (!$this->database->query($query)) {
    $this->dropbox->writeToLog(sprintf("Error: Failed to update password for %s. Error was %s", $username, $this->database->error));
    return sprintf(gettext("Failed to update password for user %s"), $username);
  }
  return '';
}

public function DBUserQuota( $username ) {
  return 1;
  //global $NSSDROPBOX_PREFS;
  //$res = $this->database->query("SELECT quota FROM usertable WHERE username = '".$this->database->real_escape_string($username)."'");
  //$line = $res->fetch_array(MYSQLI_NUM);
//printf("line is $line\nline0 is ".$line[0]."\nline00 is ".$line[0][0]."\n");
  //$quota = $line[0];
  //return ($quota >= 1) ? $quota : $NSSDROPBOX_PREFS['defaultMyZendToQuota'];
}

public function DBRemainingQuota ( $username ) {
  return 1;
  //$res = $this->database->query(
  //          sprintf("SELECT SUM(lengthInBytes) AS usedQuotaInBytes FROM dropoff LEFT JOIN file ON dropoff.rowID=file.dID WHERE dropoff.authorizeduser='%s'",
  //            $this->database->real_escape_string($username)
  //            )
  //          );
  //$line = $res->fetch_array(MYSQLI_NUM);
  //return $this->DBUserQuota($username) - $line[0];
}

public function DBUpdateQuotaLocalUser ( $username, $quota ) {
  return '';
  //if (!$this->DBReadLocalUser($username)) {
  //  return sprintf(gettext("Aborting updating user %s as that user does not exist"), $username);
  //}
  //
  //$query = sprintf("UPDATE usertable
  //                  SET quota=%f
  //                  WHERE username='%s'",
  //                  $quota,
  //                  $this->database->real_escape_string($username));
  //if (!$this->database->query($query)) {
  //  return sprintf(gettext("Failed to update quota for user %s"), $username);
  //}
  //return '';
}

public function DBListLocalUsers () {
  $res = $this->database->query("SELECT * FROM usertable ORDER BY username");
  $i = 0;
  $extant = array();
  while ($line=$res->fetch_array()) {
    $extant[$i++] = $line;
  }
  return $extant;
}


public function DBListClaims ( $claimID, &$extant ) {
  $res = $this->database->query("SELECT * FROM dropoff WHERE claimID = '".$this->database->real_escape_string($claimID)."'");
  $i = 0;
  $extant = array();
  while ($line=$res->fetch_array()) {
    $extant[$i++] = $line;
  }
  return $extant;
}

public function DBWriteReqData( $dropbox, $hash, $srcname, $srcemail, $srcorg, $destname, $destemail, $note, $subject, $expiry, $passphrase = '') {
    if ( ! $this->DBStartTran() ) {
      $dropbox->writeToLog("Error: failed to BEGIN transaction block while adding req for $srcemail");
      return '';
    }
    if (!isset($passphrase)) $passphrase = '';

    if (!$this->DBReqPassphraseExists() && !$this->DBReqAddPassphrase()) {
      // Failed to add the new column, so try to continue anyway
      $dropbox->writeToLog("Error: failed to add new column Passphrase varchar(1024) NOT NULL DEFAULT '' to database table reqtable. Please add it by hand");
      $query = sprintf("INSERT INTO reqtable
                        (Auth,SrcName,SrcEmail,SrcOrg,DestName,DestEmail,Note,Subject,Expiry)
                        VALUES
                        ('%s','%s','%s','%s','%s','%s','%s','%s',%d)",
                       $this->database->real_escape_string($hash),
                       $this->database->real_escape_string($srcname),
                       $this->database->real_escape_string($srcemail),
                       $this->database->real_escape_string($srcorg),
                       $this->database->real_escape_string($destname),
                       $this->database->real_escape_string($destemail),
                       $this->database->real_escape_string($note),
                       $this->database->real_escape_string($subject),
                       $expiry);
    } else {
      $query = sprintf("INSERT INTO reqtable
                        (Auth,SrcName,SrcEmail,SrcOrg,DestName,DestEmail,Note,Subject,Expiry,Passphrase)
                        VALUES
                        ('%s','%s','%s','%s','%s','%s','%s','%s',%d,'%s')",
                       $this->database->real_escape_string($hash),
                       $this->database->real_escape_string($srcname),
                       $this->database->real_escape_string($srcemail),
                       $this->database->real_escape_string($srcorg),
                       $this->database->real_escape_string($destname),
                       $this->database->real_escape_string($destemail),
                       $this->database->real_escape_string($note),
                       $this->database->real_escape_string($subject),
                       $expiry,
                       $this->database->real_escape_string($passphrase));
    }
    sodium_memzero($passphrase);
    if ( ! $this->database->query($query) ) {
      //  Exit gracefully -- dump database changes
      $this->dropbox->writeToLog(sprintf("Error: Failed to add %s to reqtable for %s. Error was %s", $hash, $srcemail, $this->database->error));
      $dropbox->writeToLog("Error: failed to add $hash to reqtable for $srcemail");
      if ( ! $this->DBRollbackTran() ) {
        $dropbox->writeToLog("Error: failed to ROLLBACK after botched addition of $hash to reqtable for $srcemail");
      }
      return '';
    }
    $this->DBCommitTran();
    return $hash;
}

public function DBReadReqData( $hash ) {
    $res = $this->database->query(
                  sprintf("SELECT * FROM reqtable WHERE Auth = '%s'",
                          $this->database->real_escape_string($hash)));
    $i = 0;
    $recordlist = array();
    while ($line=$res->fetch_array()) {
      $recordlist[$i++] = $line;
    }
    return $recordlist;
}

public function DBDeleteReqData( $authkey ) {
    $this->database->query(
      sprintf("DELETE FROM reqtable WHERE Auth = '%s'", $this->database->real_escape_string($authkey)));
}

public function DBPruneReqData( $old ) {
    $this->database->query(
      sprintf("DELETE FROM reqtable WHERE Expiry < %d", $old));
}

public function DBWriteAuthData( $dropbox, $hash, $name, $email, $org, $expiry ) {
    if ( ! $this->DBStartTran() ) {
      $dropbox->writeToLog("Error: failed to BEGIN transaction while adding $email to authtable");
      return '';
    }
    $query = sprintf("INSERT INTO authtable
                      (Auth,FullName,Email,Organization,Expiry)
                      VALUES
                      ('%s','%s','%s','%s',%d)",
                     $this->database->real_escape_string($hash),
                     $this->database->real_escape_string($name),
                     $this->database->real_escape_string($email),
                     $this->database->real_escape_string($org),
                     $expiry);
    if ( ! $this->database->query($query) ) {
      //  Exit gracefully -- dump database changes
      $this->dropbox->writeToLog(sprintf("Error: Failed to add %s to authtable for %s. Error was %s", $hash, $email, $this->database->error));
      $dropbox->writeToLog("Error: failed to add $hash to authtable for $email");
      if ( ! $this->DBRollbackTran() ) {
        $dropbox->writeToLog("Error: failed to ROLLBACK after botched addition of $hash to authtable for $email");
      }
      return '';
    }
    $this->DBCommitTran();
    return $hash;
}

public function DBReadAuthData( $authkey ) {
    $res = $this->database->query(
                  sprintf("SELECT * FROM authtable WHERE Auth = '%s'",
                          $this->database->real_escape_string($authkey)));
    $i = 0;
    $recordlist = array();
    while ($line=$res->fetch_array()) {
      $recordlist[$i++] = $line;
    }
    return $recordlist;
}

public function DBDeleteAuthData( $authkey ) {
    $this->database->query(
      sprintf("DELETE FROM authtable WHERE Auth = '%s'", $this->database->real_escape_string($authkey)));
}

public function DBPruneAuthData( $old ) {
    $this->database->query(
      sprintf("DELETE FROM authtable WHERE Expiry < '%d'", $old));
}

public function DBDropoffsForMe( $targetEmail ) {
  $res = $this->database->query(
           "SELECT d.rowID,d.* FROM dropoff d,recipient r WHERE d.rowID = r.dID AND r.recipEmail = '".$this->database->real_escape_string($targetEmail)."' ORDER BY d.created DESC");
  $i = 0;
  $qResult = array();
  // JKF 20120322 Added isset() test to catch a PHP fatal error
  if (isset($res)) {
    while ($line = $res->fetch_array()) {
      $qResult[$i++] = $line;
    }
  }
  return $this->dropbox->TrimOffDying($qResult, 0);
}

public function DBDropoffsFromMe( $authSender, $targetEmail ) {
  $res = $this->database->query(
           sprintf("SELECT * FROM dropoff WHERE authorizedUser = '".$this->database->real_escape_string($authSender)."' %s ORDER BY created DESC",
               ( $targetEmail ? ("OR senderEmail = '".$this->database->real_escape_string($targetEmail)."'") : "")
             ));
  $i = 0;
  $qResult = array();
  while ($line = $res->fetch_array()) {
    $qResult[$i++] = $line;
  }
  return $this->dropbox->TrimOffDying($qResult, 0);
}

// This is only run daily, during the cleanup process.
// It will get run, but not too often.
public function DBUpdateSchema() {
  //
  // Read the data type of the dropoff "note" column.
  //
  $res = $this->database->query(
           "select data_type from information_schema.columns where table_schema='".$this->dbname."' and table_name='dropoff' and column_name='note'");
  $line = $res->fetch_array();
  // If it is tinytext, change it to text
  if (strcasecmp($line[0], 'tinytext') == 0) {
    $this->database->query("ALTER TABLE dropoff MODIFY note text NOT NULL");
  }
  //
  // Read the data type and length of the file "mimeType" column.
  //
  $res = $this->database->query(
           "select data_type,character_maximum_length from information_schema.columns where table_schema='".$this->dbname."' and table_name='file' and column_name='mimeType'");
  $line = $res->fetch_array();
  // If it is a short varchar, change it to varchar(256)
  if (strcasecmp($line[0], 'varchar') == 0 && $line[1] < 255) {
          $this->database->query("ALTER TABLE file MODIFY mimeType varchar(256) NOT NULL");
  }
  //
  // Read the data type and length of the usertable "password" column.
  // This is so we can properly encrypt local users' passwords.
  //
  $res = $this->database->query(
           "select data_type,character_maximum_length from information_schema.columns where table_schema='".$this->dbname."' and table_name='usertable' and column_name='password'");
  $line = $res->fetch_array();
  // If it is a short varchar, change it to varchar(256)
  if (strcasecmp($line[0], 'varchar') == 0 && $line[1] < 255) {
          $this->database->query("ALTER TABLE usertable MODIFY password varchar(256) NOT NULL");
  }
  //
  // Read the data type and length of the authtable "FullName" column.
  // 32 chars is way too short for many names (and IPv6 addresses!).
  //
  $res = $this->database->query(
           "select data_type,character_maximum_length from information_schema.columns where table_schema='".$this->dbname."' and table_name='authtable' and column_name='FullName'");
  $line = $res->fetch_array();
  // If it is a short varchar, change it to varchar(256)
  if (strcasecmp($line[0], 'varchar') == 0 && $line[1] < 255) {
          $this->database->query("ALTER TABLE authtable MODIFY FullName varchar(256) NOT NULL");
  }
  //
  // Need more room to squeeze in the crypto IV data
  //
  $res = $this->database->query(
           "select data_type,character_maximum_length from information_schema.columns where table_schema='".$this->dbname."' and table_name='dropoff' and column_name='senderIP'");
  $line = $res->fetch_array();
  // If it is a short varchar, change it to varchar(256)
  if (strcasecmp($line[0], 'varchar') == 0 && $line[1] < 255) {
          $this->database->query("ALTER TABLE dropoff MODIFY senderIP varchar(256) NOT NULL");
  }
  //
  // They might be using email address as username (so 64 chars not long enough)
  //
  $res = $this->database->query(
           "select data_type,character_maximum_length from information_schema.columns where table_schema='".$this->dbname."' and table_name='dropoff' and column_name='authorizedUser'");
  $line = $res->fetch_array();
  // If it is a short varchar, change it to varchar(256)
  if (strcasecmp($line[0], 'varchar') == 0 && $line[1] < 255) {
          $this->database->query("ALTER TABLE dropoff MODIFY authorizedUser varchar(255) NOT NULL");
  }
  $res = $this->database->query(
           "select data_type,character_maximum_length from information_schema.columns where table_schema='".$this->dbname."' and table_name='pickup' and column_name='authorizedUser'");
  $line = $res->fetch_array();
  // If it is a short varchar, change it to varchar(256)
  if (strcasecmp($line[0], 'varchar') == 0 && $line[1] < 255) {
          $this->database->query("ALTER TABLE pickup MODIFY authorizedUser varchar(255) NOT NULL");
  }
  $res = $this->database->query(
           "select data_type,character_maximum_length from information_schema.columns where table_schema='".$this->dbname."' and table_name='loginlog' and column_name='username'");
  $line = $res->fetch_array();
  // If it is a short varchar, change it to varchar(256)
  if (strcasecmp($line[0], 'varchar') == 0 && $line[1] < 255) {
          $this->database->query("ALTER TABLE loginlog MODIFY username varchar(255) NOT NULL");
  }
  $res = $this->database->query(
           "select data_type,character_maximum_length from information_schema.columns where table_schema='".$this->dbname."' and table_name='addressbook' and column_name='username'");
  $line = $res->fetch_array();
  // If it is a short varchar, change it to varchar(256)
  if (strcasecmp($line[0], 'varchar') == 0 && $line[1] < 255) {
    $this->database->query("ALTER TABLE addressbook MODIFY username varchar(255) NOT NULL");
  }
  // If the reqtable doesn't contain Passphrase column, add it
  if (!$this->DBReqPassphraseExists()) {
    // If it fails, just carry on quietly.
    $this->DBReqAddPassphrase();
  }
  // If the dropoff table doesn't contain lifeseconds column, add it
  if (!$this->DBLifesecondsExists()) {
    // If it fails, just carry on quietly
    $this->DBAddLifeseconds();
  }
  // If the dropoff table doesn't contain subject column, add it
  if (!$this->DBSubjectExists()) {
    // If it fails, just carry on quietly
    $this->DBAddSubject();
  }
}

// Does the table 'dropoff' contain a field called "subject"?
private function DBSubjectExists() {
  $res = $this->database->query(
           "select if(count(*) = 1, 'yes','no') AS result from information_schema.columns where table_schema='".$this->dbname."' and table_name='dropoff' and column_name='subject'");
  $line = $res->fetch_array();
  // If it is not there, add it
  return (strcasecmp($line[0], 'yes') == 0)?TRUE:FALSE;

}

private function DBAddSubject() {
  $res = $this->database->query("ALTER TABLE dropoff ADD COLUMN subject varchar(500) DEFAULT NULL");
  return ($res === FALSE) ? FALSE : TRUE;
}

// Does the table 'dropoff' contain a field called "lifeseconds"?
private function DBLifesecondsExists() {
  $res = $this->database->query(
           "select if(count(*) = 1, 'yes','no') AS result from information_schema.columns where table_schema='".$this->dbname."' and table_name='dropoff' and column_name='lifeseconds'");
  $line = $res->fetch_array();
  // If it is not there, add it
  return (strcasecmp($line[0], 'yes') == 0)?TRUE:FALSE;

}

private function DBAddLifeseconds() {
  $res = $this->database->query("ALTER TABLE dropoff ADD COLUMN lifeseconds int NOT NULL DEFAULT 0");
  return ($res === FALSE) ? FALSE : TRUE;
}

// Does the table 'reqtable' contain a field called "Passphrase"?
private function DBReqPassphraseExists() {
  $res = $this->database->query(
           "select if(count(*) = 1, 'yes','no') AS result from information_schema.columns where table_schema='".$this->dbname."' and table_name='reqtable' and column_name='Passphrase'");
  $line = $res->fetch_array();
  // If it is not there, add it
  return (strcasecmp($line[0], 'yes') == 0)?TRUE:FALSE;
}

private function DBReqAddPassphrase() {
  $res = $this->database->query("ALTER TABLE reqtable ADD COLUMN Passphrase varchar(1024) NOT NULL DEFAULT ''");
  return ($res === FALSE) ? FALSE : TRUE;
}

// Does not trim expired ones as it's only used for stats
public function DBDropoffsToday( $targetDate ) {
  $res = $this->database->query(
           "SELECT * FROM dropoff WHERE created >= '$targetDate' ORDER BY created");
  $i = 0;
  $qResult = array();
  while ($line = $res->fetch_array()) {
    $qResult[$i++] = $line;
  }
  return $qResult;
}

// Note this does NOT trim expired ones
public function DBDropoffsAll() {
  $res = $this->database->query(
           "SELECT * FROM dropoff ORDER BY created DESC");
  $i = 0;
  $qResult = array();
  while ($line = $res->fetch_array()) {
    $qResult[$i++] = $line;
  }
  return $qResult;
}

// Note this does NOT trim expired ones
public function DBDropoffsAllRev() {
  $res = $this->database->query(
           "SELECT * FROM dropoff ORDER BY created");
  $i = 0;
  $qResult = array();
  while ($line = $res->fetch_array()) {
    $qResult[$i++] = $line;
  }
  return $qResult;
}

function DBFilesByDropoffID( $dropoffID ) {
  $res = $this->database->query(
           sprintf("SELECT * FROM file WHERE dID = %d ORDER by rowID",
             $this->database->real_escape_string($dropoffID)));
  $i = 0;
  $qResult = array();
  while ($line = $res->fetch_array()) {
    // Dismantle the tmpname into its components, if they exist.
    // If there is a | then pull out the tmpname and checksum
    if (strpos($line['tmpname'], '|')) {
      list($tmpname, $checksum) = explode('|', $line['tmpname']);
      $line['tmpname'] = $tmpname;
      $line['checksum'] = $checksum;
    } else {
      $line['checksum'] = '';
    }
    $qResult[$i++] = $line;
  }
  return $qResult;
}

public function DBDataForRRD( $set ) {
  $res = $this->database->query(
           sprintf("SELECT COUNT(*),SUM(lengthInBytes) FROM file WHERE dID IN (%s)", $set));
  $line = $res->fetch_row();
  return $line;
}

public function DBBytesOfDropoff( $dropoffID ) {
  $res = $this->database->query(
           sprintf("SELECT SUM(lengthInBytes) FROM file WHERE dID = %d",
             $this->database->real_escape_string($dropoffID)));
  $line = $res->fetch_array(MYSQLI_NUM);
  return $line[0];
}

public function DBAddFile2( $d, $dropoffID, $tmpname, $filename,
                    $contentLen, $mimeType, $description, $claimID ) {
  if ( ! $this->DBStartTran() ) {
    $d->writeToLog("Error: failed to BEGIN transaction block while adding $filename to dropoff $claimID");
    return false;
  }

  $mimeType = preg_replace('/[<>]/', '', $mimeType); // extra sanitising!

  $query = sprintf("INSERT INTO file (dID,tmpname,basename,lengthInBytes,mimeType,description) VALUES (%d,'%s','%s',%.0f,'%s','%s')",
             $dropoffID,
             $this->database->real_escape_string(basename($tmpname)),
             $this->database->real_escape_string(paramPrepare($filename)),
             $contentLen,
             $this->database->real_escape_string($mimeType),
             // 20120518 Not sure if this paramPrepare should be here
             $this->database->real_escape_string(paramPrepare($description))
          );
  if ( ! $this->database->query($query) ) {
    //  Exit gracefully -- dump database changes and remove the dropoff
    //  directory:
    $this->dropbox->writeToLog(sprintf("Error: Failed to add %s to dropoff %s. Error was %s", $filename, $claimID, $this->database->error));
    $d->writeToLog("Error: failed to add $filename to dropoff $claimID");
    if ( ! $this->DBRollbackTran() ) {
      $d->writeToLog("Error: failed to ROLLBACK after botched addition of $filename to dropoff $claimID");
    }
    return false;
  }
  return $this->DBCommitTran();
}

public function DBFileList( $dropoffID, $fileID ) {
  // Only include the rowID if we're looking for a single file
  if ($fileID === 'all') {
    $res = $this->database->query(
             sprintf("SELECT * FROM file WHERE dID = %d",
               $this->database->real_escape_string($dropoffID)));
  } else {
    $res = $this->database->query(
             sprintf("SELECT * FROM file WHERE dID = %d AND rowID = %d",
               $this->database->real_escape_string($dropoffID),
               $this->database->real_escape_string($fileID)));
  }

  $i = 0;
  $qResult = array();
  while ($line = $res->fetch_array()) {
    // Dismantle the tmpname into its components, if they exist.
    // If there is a | then pull out the tmpname and checksum
    if (strpos($line['tmpname'], '|')) {
      list($tmpname, $checksum) = explode('|', $line['tmpname']);
      $line['tmpname'] = $tmpname;
      $line['checksum'] = $checksum;
    } else {
      $line['checksum'] = '';
    }
    $qResult[$i++] = $line;
  }
  return $qResult;
}

public function DBExtantPickups( $dropoffID, $email ) {
  $query = sprintf("SELECT count(*) FROM pickup WHERE dID = %d AND emailAddr='%s'",
                   $this->database->real_escape_string($dropoffID),
                   $this->database->real_escape_string($email)
           );
  $res = $this->database->query($query);
  $line = $res->fetch_array(MYSQLI_NUM);
  return $line[0]; // Return the 1st field of the 1st line of the result
}

public function DBPickupFailures( $dropoffID ) {
  $query = sprintf("SELECT count(*) FROM pickup WHERE dID = %d AND emailAddr='FAILEDATTEMPT'", $this->database->real_escape_string($dropoffID));
  $res = $this->database->query($query);
  $line = $res->fetch_array(MYSQLI_NUM);
  return $line[0]; // Return the 1st field of the 1st line of the result
}

public function DBAddToPickupLog( $d, $dropoffID, $authorizedUser, $emailAddr,
                                  $remoteAddr, $timeStamp, $claimID ) {
  $query = sprintf("INSERT INTO pickup (dID,authorizedUser,emailAddr,recipientIP,pickupTimestamp) VALUES (%d,'%s','%s','%s','%s')",
             $this->database->real_escape_string($dropoffID),
             $this->database->real_escape_string($authorizedUser),
             $this->database->real_escape_string($emailAddr),
             $this->database->real_escape_string($remoteAddr),
             $this->database->real_escape_string($timeStamp)
           );
  if ( ! $this->database->query($query) ) {
    $this->dropbox->writeToLog(sprintf("Error: Failed to add pickup record for %s of drop-off %s. Error was %s", $emailAddr, $claimID, $this->database->error));
    $d->writeToLog("Error: unable to add pickup record for claimID $claimID  by $emailAddr");
  }
}

public function DBRemoveDropoff( $d, $dropoffID, $claimID ) {
      if ( $this->DBStartTran() ) {
        $query = sprintf("DELETE FROM pickup WHERE dID = %d",$this->database->real_escape_string($dropoffID));
        if ( ! $this->database->query($query) ) {
          $this->dropbox->writeToLog(sprintf("Info: Failed to delete pickup entries when deleting drop-off %s (but there might not have been any). Error was %s", $claimID, $this->database->error));
          // if ( $doLogEntries ) {
          //   $d->writeToLog("Error: failed to delete pickup entries when deleting dropoff $claimID with '$query'");
          // }
          $this->DBRollbackTran();
          return FALSE;
        }

        $query = sprintf("DELETE FROM file WHERE dID = %d",$this->database->real_escape_string($dropoffID));
        if ( ! $this->database->query($query) ) {
          $this->dropbox->writeToLog(sprintf("Error: Failed to delete file entries when deleting drop-off %s. Error was %s", $claimID, $this->database->error));
          // if ( $doLogEntries ) {
          //   $d->writeToLog("Error: failed to delete file entries when deleting dropoff $claimID with '$query'");
          // }
          $this->DBRollbackTran();
          return FALSE;
        }

        $query = sprintf("DELETE FROM recipient WHERE dID = %d",$this->database->real_escape_string($dropoffID));
        if ( ! $this->database->query($query) ) {
          $this->dropbox->writeToLog(sprintf("Error: Failed to delete recipient entries when deleting drop-off %s. Error was %s", $claimID, $this->database->error));
          // if ( $doLogEntries ) {
          //   $d->writeToLog("Error: failed to delete recipient entries when deleting dropoff $claimID with '$query'");
          // }
          $this->DBRollbackTran();
          return FALSE;
        }

        $query = sprintf("DELETE FROM dropoff WHERE claimID = '%s'",$this->database->real_escape_string($claimID));
        if ( ! $this->database->query($query) ) {
          $this->dropbox->writeToLog(sprintf("Error: Failed to delete dropoff entries when deleting drop-off %s. Error was %s", $claimID, $this->database->error));
          // if ( $doLogEntries ) {
          //   $d->writeToLog("Error: failed to delete dropoff entries when deleting dropoff $claimID with '$query'");
          // }
          $this->DBRollbackTran();
          return FALSE;
        }

        if ( ! $this->DBCommitTran() ) {
          $this->dropbox->writeToLog(sprintf("Error: Failed to COMMIT transaction when deleting drop-off %s. Error was %s", $claimID, $this->database->error));
          // if ( $doLogEntries ) {
          //   $d->writeToLog("Error: failed to COMMIT when deleting dropoff $claimID");
          // }
          $this->DBRollbackTran();
          return FALSE;
        }
        return TRUE;
      }
      return FALSE;
}

public function DBFilesForDropoff ( $dropoffID ) {
  $res = $this->database->query(
           sprintf("SELECT * FROM file WHERE dID = %d ORDER by rowID",
             $this->database->real_escape_string($dropoffID)
           ));
  $i = 0;
  $qResult = array();
  while ($line = $res->fetch_array()) {
    // Dismantle the tmpname into its components, if they exist.
    // If there is a | then pull out the tmpname and checksum
    if (strpos($line['tmpname'], '|')) {
      list($tmpname, $checksum) = explode('|', $line['tmpname']);
      $line['tmpname'] = $tmpname;
      $line['checksum'] = $checksum;
    } else {
      $line['checksum'] = '';
    }
    $qResult[$i++] = $line;
  }
  return $qResult;
}

public function DBPickupCountsForAllDropoffs () {
  $res = $this->database->query(
           "SELECT dID, count(1) FROM pickup WHERE emailAddr != 'FAILEDATTEMPT' GROUP BY dID"
         );
  $counters = array();
  while ($line = $res->fetch_array()) {
    $counters[$line[0]] = $line[1];
  }
  return $counters;
}

public function DBPickupsForDropoff ( $dropoffID ) {
  $res = $this->database->query(
           sprintf("SELECT * FROM pickup WHERE dID = %d AND emailAddr != 'FAILEDATTEMPT' ORDER by pickupTimestamp",
             $this->database->real_escape_string($dropoffID)
           ));
  $i = 0;
  $qResult = array();
  while ($line = $res->fetch_array()) {
    $qResult[$i++] = $line;
  }
  return $qResult;
}

public function DBDropoffsForClaimID ( $claimID ) {
  $res = $this->database->query(
           sprintf("SELECT * FROM dropoff WHERE claimID = '%s'",
             $this->database->real_escape_string($claimID)
           ));
  $i = 0;
  $qResult = array();
  while ($line = $res->fetch_array()) {
    $qResult[$i++] = $line;
  }
  return $this->dropbox->TrimOffDying($qResult, 0);
}

public function DBRecipientsForDropoff ( $rowID ) {
  $res = $this->database->query(
           sprintf("SELECT recipName,recipEmail FROM recipient WHERE dID = %d",
             $this->database->real_escape_string($rowID)
           ));
  $i = 0;
  $qResult = array();
  while ($line = $res->fetch_array()) {
    $qResult[$i++] = $line;
  }
  return $qResult;
}

public function DBStartTran () {
  return $this->database->query('BEGIN');
}

public function DBRollbackTran () {
  return $this->database->query('ROLLBACK');
}

public function DBCommitTran () {
  return $this->database->query('COMMIT');
}

public function DBTouchDropoff ( $claimID, $now ) {
  $query = sprintf("UPDATE dropoff SET created='%s' WHERE claimID='%s'",
             $now,
             $this->database->real_escape_string($claimID)
           );
  if ( $this->database->query($query) ) {
    return TRUE;
  }
  return FALSE;
}
  
public function DBAddDropoff ( $claimID, $claimPasscode, $authorizedUser,
                               $senderName, $senderOrganization, $senderEmail,
                               $remoteIP, $confirmDelivery,
                               $now, $note, $lifeseconds, $subject ) {
  // This shouldn't be needed. Hopefully post-RPM/DEB install script
  // will do this first.
  // if (!$this->DBLifesecondsExists())
  //   $this->DBReqAddLifeseconds();

  $query = sprintf("INSERT INTO dropoff
                    (claimID,claimPasscode,authorizedUser,senderName,
                     senderOrganization,senderEmail,senderIP,
                     confirmDelivery,created,note,lifeseconds,subject)
                    VALUES
                    ('%s','%s','%s','%s', '%s','%s','%s', %d,'%s','%s',%d,'%s')",
             $this->database->real_escape_string($claimID),
             $this->database->real_escape_string($claimPasscode),
             $this->database->real_escape_string($authorizedUser),
             $this->database->real_escape_string($senderName),
             $this->database->real_escape_string($senderOrganization),
             $this->database->real_escape_string($senderEmail),
             $this->database->real_escape_string($remoteIP),
             ( $confirmDelivery ? 1 : 0 ),
             $now,
             $this->database->real_escape_string($note),
             $lifeseconds,
             $this->database->real_escape_string($subject)
           );
  if ( !$this->database->query($query) ) {
    $this->dropbox->writeToLog(sprintf("Error: Failed to add drop-off %s. Error was %s", $claimID, $this->database->error));
    return FALSE;
  }
  return $this->database->insert_id;
}

public function DBAddRecipients ( $recipients, $dropoffID ) {
  foreach ( $recipients as $recipient ) {
    $query = sprintf("INSERT INTO recipient
                      (dID,recipName,recipEmail)
                      VALUES
                      (%d,'%s','%s')",
               $this->database->real_escape_string($dropoffID),
               $this->database->real_escape_string($recipient[0]),
               $this->database->real_escape_string($recipient[1]));
    if ( ! $this->database->query($query) ) {
      $this->dropbox->writeToLog(sprintf("Error: Failed to add recipient %s to drop-off %s. Error was %s", $recipient[1], $dropoffID, $this->database->error));
      return FALSE;
    }
  }
  return TRUE;
}

public function DBAddFile1 ( $dropoffID, $tmpname, $basename, $bytes,
                            $mimeType, $description, $checksum ) {
  // To avoid another schema change, I'm putting the checksum on the end
  // of the tmpname.
  $tmpname .= '|' . $checksum;

  $mimeType = preg_replace('/[<>]/', '', $mimeType); // extra sanitising!

  $query = sprintf("INSERT INTO file
                    (dID,tmpname,basename,lengthInBytes,mimeType,description)
                    VALUES
                    (%d,'%s','%s',%.0f,'%s','%s')",
             $dropoffID,
             $this->database->real_escape_string($tmpname),
             $this->database->real_escape_string($basename),
             $bytes,
             $this->database->real_escape_string($mimeType),
             $this->database->real_escape_string($description));
  if ( ! $this->database->query($query) ) {
    $this->dropbox->writeToLog(sprintf("Error: Failed to add file1 %s to drop-off %s. Error was %s", $basename, $dropoffID, $this->database->error));
    return FALSE;
  }
  return TRUE;
}

// Find the addressbook for a user
public function DBGetAddressbook ( $user ) {
  $res = $this->database->query(
           sprintf("SELECT name,email FROM addressbook WHERE username='%s'",
             $this->database->real_escape_string($user)
           ));
  $i = 0;
  $qResult = array();
  while ($line = $res->fetch_array()) {
    $qResult[$i++] = $line;
  }
  return $qResult;
}

// Update the addressbook entries for this user that are the passed recipients
public function DBUpdateAddressbook ( $user, $recips ) {
  $user = $this->database->real_escape_string($user);
  foreach ($recips as $recip) {
    $name  = $this->database->real_escape_string(trim($recip[0]));
    $email = $this->database->real_escape_string(trim($recip[1]));
    $res = $this->database->query(
             sprintf("SELECT COUNT(*) FROM addressbook WHERE username='%s' AND name='%s' and email='%s'",
               $user, $name, $email));
    $line = $res->fetch_array();
    if ($line[0]>=1) {
      $query = sprintf("UPDATE addressbook SET lastused=now() WHERE username='%s' AND name='%s' AND email='%s'", $user, $name, $email);
      $query = $this->database->query($query);
    } else {
      $query = sprintf("INSERT INTO addressbook (username,name,email) VALUES ('%s','%s','%s')", $user, $name, $email);
      $query = $this->database->query($query);
    }
    if (!$query) {
      $this->dropbox->writeToLog(sprintf("Error: Failed to add/update address book record %s for %s. Error was %s", $email, $user, $this->database->error));
    }
  }
}

public function DBDeleteAddressbookEntry($user, $name, $email) {
  $user  = $this->database->real_escape_string($user);
  $name  = $this->database->real_escape_string($name);
  $email = $this->database->real_escape_string($email);
  $query = $this->database->query(
            sprintf("DELETE FROM addressbook WHERE username='%s' AND name='%s' AND email='%s'",
                    $user, $name, $email));
  if (!$query) {
      $this->dropbox->writeToLog(sprintf("Error: Failed to delete address book record %s for %s. Error was %s", $email, $user, $this->database->error));
  }
  return $query;
}

// Get a mapping from library filename to description
public function DBGetLibraryDescs () {
  $res = $this->database->query(
           "SELECT filename,description FROM librarydesc");
  $i = 0;
  $qResult = array();
  while ($line = $res->fetch_array()) {
    // Only set it if it's not already set and we're not setting it to blank
    if (!isset($qResult[$line[0]]) && isset($line[1])) {
      $qResult[trim($line[0])] = trim($line[1]);
    }
    // $qResult[$line[0]] = $line[1];
  }
  return $qResult;
}

// Update the mapping from filename to description
public function DBUpdateLibraryDescription ( $file, $desc ) {
  $file = trim($file);
  $desc = trim($desc);
  $res = $this->database->query(
             sprintf("SELECT COUNT(*) FROM librarydesc WHERE filename='%s'",
               $this->database->real_escape_string($file)));
  $line = $res->fetch_array();

  if ($line[0]>=1) {
    // Entry for this filename already exists, so UPDATE
    $query = sprintf("UPDATE librarydesc SET description='%s' WHERE filename='%s'",
               $this->database->real_escape_string($desc),
               $this->database->real_escape_string($file));
    $query = $this->database->query($query);
  } else {
    // Entry for this filename does not exist, so INSERT
    $query = sprintf("INSERT INTO librarydesc (filename,description) VALUES ('%s','%s')",
               $this->database->real_escape_string($file),
               $this->database->real_escape_string($desc));
    $query = $this->database->query($query);
  }
  if (!$query) {
    $this->dropbox->writeToLog(sprintf("Error: Failed to add/update library description for %s. Error was %s", $file, $this->database->error));
  }
}

}

?>
