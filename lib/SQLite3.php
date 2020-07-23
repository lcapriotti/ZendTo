<?php
//
// ZendTo
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
// This is the Sql class for SQLite
//

Class Sql {

public $database = NULL;
public $_newDatabase = FALSE;
private $dropbox = NULL; // Allows me to write to ZendTo log

public function __construct( $prefs, $aDropbox ) {
  $this->dropbox = $aDropbox;
  $this->DBConnect($prefs['SQLiteDatabase'], $aDropbox);
}  

//
// Database functions that work on NSSDropbox objects
//

private function DBConnect ( $sqlitename, $aDropbox ) {
  if ( ! file_exists($sqlitename) ) {
    // Flags in next line fixed, thanks for Paolo Perfetti
    try {
      $this->database = new SQLite3($sqlitename,
                            SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
    } catch (Exception $e) {
      NSSError(gettext("Could not create the new database."), gettext("Database Error"));
      $this->database = NULL;
      return FALSE;
    }
    if ( ! ($this->database)  ) {
      NSSError(gettext("Could not create the new database."), gettext("Database Error"));
      return FALSE;
    }
    //  It was a new file, so we need to create tables in the database
    //  right now, too!
    if ( ! $this->DBSetupDatabase($aDropbox) ) {
      NSSError(gettext("Could not create the tables in the new database."), gettext("Database Error"));
      $this->database = NULL; // Bin it, it's useless anyway
      return FALSE;
    }
    //  This was a new database:
    $this->_newDatabase = TRUE;
  } else {
    try {
      $this->database = new SQLite3($sqlitename);
    } catch (Exception $e) {
      NSSError(gettext("Could not open the database."), gettext("Database Error"));
      $this->database = NULL;
      return FALSE;
    }
    if ( ! ($this->database) ) {
      NSSError(gettext("Could not open the database."), gettext("Database Error"));
      return FALSE;
    }

    $table_t = gettext("Database Error creating %s table");
    $errorMsg = '';

    // If the librarydesc table doesn't exist, create it!
    try {
      $res = $this->DBCheckTable('librarydesc');
      if ( $res === 0 ) {
        // table does not exist...create it or do something...
        if ( ! $this->DBCreateLibraryDesc() ) {
          NSSError($errorMsg, sprintf($table_t, 'librarydesc'));
          return FALSE;
        }
      } else if ( $res === FALSE ) {
        // Failed to even check for existence of table, bail out
        NSSError($errorMsg, "Failed to read database");
        return FALSE;
      }

      // If the addressbook table doesn't exist, create it!
      $res = $this->DBCheckTable('addressbook');
      if ( $res === 0 ) {
        // table does not exist...create it or do something... 
        if ( ! $this->DBCreateAddressbook() ) {
          NSSError($errorMsg, sprintf($table_t, 'addressbook'));
          return FALSE;
        }
      } else if ( $res === FALSE ) {
        // Failed to even check for existence of table, bail out
        NSSError($errorMsg, "Failed to read database");
        return FALSE;
      }
    }
    catch (RuntimeException $e) {
      // Failed to even check for existence of table, bail out
      NSSError($errorMsg, "Failed to read database");
      return FALSE;
    }
  }

  // Set the busy timeout and WAL mode
  // https://www.sqlite.org/wal.html
  // https://www.sqlite.org/pragma.html#pragma_busy_timeout
  $this->database->exec('PRAGMA journal_mode = wal;');
  $this->database->exec('PRAGMA busy_timeout = 5000;'); // milliseconds
  return TRUE;
}

// Create the database as we need to do this for SQLite
private function DBSetupDatabase($dropbox) {
    if ( $this->database ) {

      if ( ! $this->DBCreate()) {
        NSSError($errorMsg, gettext("Database Error"));
        return FALSE;
      }

      $table_t = gettext("Database Error creating %s table");

      if ( ! $this->DBCreateReq() ) {
        $dropbox->writeToLog("Error: failed to add reqtable to database");
        NSSError($errorMsg, sprintf($table_t, 'reqtable'));
        return FALSE;
      }

      if ( ! $this->DBCreateAuth() ) {
        $dropbox->writeToLog("Error: failed to add authtable to database");
        NSSError($errorMsg, sprintf($table_t, 'authtable'));
        return FALSE;
      }

      if ( ! $this->DBCreateUser() ) {
        $dropbox->writeToLog("Error: failed to add usertable to database");
        NSSError($errorMsg, sprintf($table_t, 'usertable'));
        return FALSE;
      }

      if ( ! $this->DBCreateRegexps() ) {
        $dropbox->writeToLog("Error: failed to add regexps to database");
        NSSError($errorMsg, sprintf($table_t, 'regexps'));
        return FALSE;
      }

      if ( ! $this->DBCreateLoginlog() ) {
        $dropbox->writeToLog("Error: failed to add loginlog to database");
        NSSError($errorMsg, sprintf($table_t, 'loginlog'));
        return FALSE;
      }

      if ( ! $this->DBCreateLibraryDesc() ) {
        $dropbox->writeToLog("Error: failed to add librarydesc to database");
        NSSError($errorMsg, sprintf($table_t, 'librarydesc'));
        return FALSE;
      }

      if ( ! $this->DBCreateAddressbook() ) {
        $dropbox->writeToLog("Error: failed to add addressbook to database");
        NSSError($errorMsg, sprintf($table_t, 'addressbook'));
        return FALSE;
      }

      $dropbox->writeToLog("Info: initial setup of database complete");

      return TRUE;
    }
    return FALSE;
}



private function DBCreate () {
      if ( ! $this->database->exec(
"CREATE TABLE dropoff (
  claimID             character varying(16) not null,
  claimPasscode       character varying(16),
  
  authorizedUser      character varying(255),
  
  senderName          character varying(32) not null,
  senderOrganization  character varying(32),
  senderEmail         text not null,
  senderIP            character varying(255) not null,
  confirmDelivery     boolean default FALSE,
  created             timestamp with time zone not null,
  note                text,
  lifeseconds         integer not null default 0,
  subject             character varying(500) default null
);") ) {
        return FALSE;
      }

      if ( ! $this->database->exec(
"CREATE TABLE recipient (
  dID                 integer not null,
  
  recipName           character varying(32) not null,
  recipEmail          text not null
);") ) {
        return FALSE;
      }

      if ( ! $this->database->exec(
"CREATE TABLE file (
  dID                 integer not null,
  
  tmpname             text not null,
  basename            text not null,
  lengthInBytes       bigint not null,
  mimeType            character varying(256) not null,
  description         text
);") ) {
        return FALSE;
      }

      if ( ! $this->database->exec(
"CREATE TABLE pickup (
  dID                 integer not null,
  
  authorizedUser      character varying(255),
  emailAddr           text,
  recipientIP         character varying(255) not null,
  pickupTimestamp     timestamp with time zone not null
);") ) {
        return FALSE;
      }

      //  Do the indexes now:

      if ( ! $this->database->exec(
"CREATE INDEX dropoff_claimID_index ON dropoff(claimID);") ) {
        return FALSE;
      }

      if ( ! $this->database->exec(
"CREATE INDEX recipient_dID_index ON recipient(dID);") ) {
        return FALSE;
      }

      if ( ! $this->database->exec(
"CREATE INDEX file_dID_index ON file(dID);") ) {
        return FALSE;
      }

      if ( ! $this->database->exec(
"CREATE INDEX pickup_dID_index ON pickup(dID);") ) {
        return FALSE;
      }

      return TRUE;
}

public function DBCreateReq() {
      if ( ! $this->database->exec(
"CREATE TABLE reqtable (
  Auth        character varying(64) not null,
  SrcName  character varying(32),
  SrcEmail text not null,
  SrcOrg   character varying(32),
  DestName   character varying(32),
  DestEmail  text not null,
  Note        text not null,
  Subject     text not null,
  Expiry      bigint not null,
  Passphrase  text not null DEFAULT '',
  Start       bigint not null DEFAULT 0
);") ) {
        return FALSE;
      }
      return TRUE;
}

// Needs to be public for people upgrading from Dropbox 2
public function DBCreateAuth() {
      if ( ! $this->database->exec(
"CREATE TABLE authtable (
  Auth         character varying(64) not null,
  FullName     character varying(32),
  Email        text not null,
  Organization character varying(32),
  Expiry       bigint not null
);") ) {
        return FALSE;
      }
      return TRUE;
}

// Needs to be public for people upgrading from earlier versions
public function DBCreateUser() {
      if ( ! $this->database->exec(
"CREATE TABLE usertable (
  username     character varying(64) not null,
  password     character varying(256) not null,
  mail         character varying(256) not null,
  displayname  character varying(256) not null,
  organization character varying(256),
  quota        real
);") ) {
        return FALSE;
      }
      if ( ! $this->database->exec(
"CREATE INDEX usertable_username_index ON usertable(username);") ) {
        return FALSE;
      }
      return TRUE;
}

// Needs to be public for people upgrading from earlier versions
public function DBCreateRegexps() {
      if ( ! $this->database->exec(
"CREATE TABLE regexps (
  type         integer not null,
  re           text not null,
  created      bigint not null
);") ) {
        return FALSE;
      }
      if ( ! $this->database->exec(
"CREATE INDEX regexpstable_type_index ON regexps(type);") ) {
        return FALSE;
      }
      return TRUE;
}

// Needs to be public for people upgrading from earlier ZendTos
public function DBCreateLoginlog() {
      if ( ! $this->database->exec(
"CREATE TABLE loginlog (
  username     character varying(255) not null,
  created      bigint not null
);") ) {
        return FALSE;
      }
      return TRUE;
}

// Needs to be public for people upgrading from earlier ZendTo
public function DBCreateLibraryDesc() {
      if ( ! $this->database->exec(
"CREATE TABLE librarydesc (
  filename    character varying(255) not null,
  description character varying(255)
);") ) {
        return FALSE;
      }
      return TRUE;
}

public function DBCreateAddressbook() {
      if ( ! $this->database->exec(
"CREATE TABLE addressbook (
  username    character varying(255) not null,
  name        character varying(255),
  email       character varying(255) not null,
  lastused    timestamp with time zone not null
);") ) {
        return FALSE;
      }
      if ( ! $this->database->exec(
"CREATE INDEX addressbook_username_index ON addressbook(username);") ) {
        return FALSE;
      }

      return TRUE;
}

// Check if table exists in database. Return True if table exists.
// Return 0 if table doesn't exist. Return False on error.
public function DBCheckTable($table){
      $query = sprintf("SELECT count(*) FROM sqlite_master WHERE type='table' and name='%s'",
                       $this->database->escapeString($table));
      $res = $this->database->query($query);
      // if ($res === FALSE) return FALSE; // Query failed
      if ($res === FALSE) {
        throw new RuntimeException('Cannot query database'); // Query failed
      }
      $count = $res->fetchArray();
      if ( $count[0] > 0 ) {
        return TRUE;
      }
      return 0;
}


public function DBAddLoginlog($user) {
  $query = sprintf("INSERT INTO loginlog
                    (username, created)
                    VALUES
                    ('%s',%d)",
                    $this->database->escapeString(strtolower($user)),
                    time());
  if (!$this->database->exec($query)) {
    return gettext("Failed to add login record");
  }
  return '';
}

public function DBDeleteLoginlog($user) {
  if (empty($user)) {
    $query = "DELETE FROM loginlog";
  } else {
    $query = sprintf("DELETE FROM loginlog WHERE username = '%s'",
                     $this->database->escapeString(strtolower($user)));
  }
  if ( !$this->database->exec($query) ) {
    return gettext("Failed to delete login records");
  }
  return '';
}

public function DBLoginlogLength($user, $since) {
  $query = sprintf("SELECT count(*) FROM loginlog
                    WHERE username = '%s' AND created > %d",
                   $this->database->escapeString(strtolower($user)),
                   $since);
  $res = $this->database->query($query);
  if (!$res) {
    return gettext("Failed to read anything from loginlog");
  }
  $row = $res->fetchArray();
  return $row[0];
}

private function arrayQuery($string, $type) {
  $res = $this->database->query($string);
  $rows = array();
  while ($row = $res->fetchArray($type)) {
    // SQLite3 lower-cases all the named field names in SELECT statements!
    // Fortunately the only ones that matter are called 'rowID' :-)
    if (isset($row['rowid']) && !isset($row['rowID'])) {
      $row['rowID'] = $row['rowid'];
    }
    $rows[] = $row;
  }
  return $rows;
}

public function DBLoginlogAll($since) {
  $recordlist = $this->arrayQuery(
                  sprintf("SELECT * FROM loginlog WHERE created > %d",
                          $since),
                  SQLITE3_ASSOC
                );
  return $recordlist;
}

// Delete the old regexps of this type and add the new ones
public function DBOverwriteRegexps($type, &$regexplist) {
  $now = time();
  if (!$this->DBStartTran()) {
    return gettext("Failed to BEGIN transaction block while updating regexps");
  }

  // Delete the old ones
  if ( ! $this->database->exec(
                sprintf("DELETE FROM regexps WHERE type = %d", $type))) {
    if (!$this->DBRollbackTran()) {
      return gettext("Failed to ROLLBACK after aborting addition of regexp");
    }
    return gettext("Failed to add regexp");
  }

  // Add the new ones
  foreach ($regexplist as $re) {
    $query = sprintf("INSERT INTO regexps
                      (type, re, created)
                      VALUES
                      (%d,'%s',%d)",
                      $type,
                      $this->database->escapeString($re),
                      $now);
    if (!$this->database->exec($query)) {
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
  $res = $this->arrayQuery(
         sprintf("SELECT re,created FROM regexps WHERE type = %d",
                 $type),
         SQLITE3_ASSOC);
  return $res;
}


public function DBReadLocalUser( $username ) {
    $recordlist = $this->arrayQuery(
                  sprintf("SELECT * FROM usertable WHERE username = '%s'",
                          $this->database->escapeString($username)),
                  SQLITE3_ASSOC
                );
    return $recordlist;
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
                    $this->database->escapeString($username),
                    password_hash($password, PASSWORD_DEFAULT),
                    $this->database->escapeString($email),
                    $this->database->escapeString($name),
                    $this->database->escapeString($org));

  if (!$this->database->exec($query)) {
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

  $this->arrayQuery(
    sprintf("DELETE FROM usertable WHERE username = '%s'",
            $this->database->escapeString($username)),
            SQLITE3_ASSOC);
  return '';
}

// Update an existing user's password.
// Returns '' on success.
public function DBUpdatePasswordLocalUser ( $username, $password ) {
  if (!$this->DBReadLocalUser($username)) {
    return sprintf(gettext("Aborting updating user %s as that user does not exist"), $username);
  }

  $query = sprintf("UPDATE usertable
                    SET password='%s'
                    WHERE username='%s'",
                    password_hash($password, PASSWORD_DEFAULT),
                    $this->database->escapeString($username));
  if (!$this->database->exec($query)) {
    return sprintf(gettext("Failed to update password for user %s"), $username);
  }
  return '';
}

public function DBUserQuota ( $username ) {
  return 1;
  //global $NSSDROPBOX_PREFS;
  //$result = $this->arrayQuery(
  //          sprintf("SELECT quota FROM usertable WHERE username = '%s'",
  //           $this->database->escapeString($username)
  //          ),
  //          SQLITE3_NUM);
  //$quota = $result[0][0];
  //return ($quota >= 1) ? $quota : $NSSDROPBOX_PREFS['defaultMyZendToQuota'];
}

public function DBRemainingQuota ( $username ) {
  return 1;
  //$result = $this->arrayQuery(
  //          sprintf("SELECT SUM(lengthInBytes) AS usedQuotaInBytes FROM dropoff LEFT JOIN file ON dropoff.rowID=file.dID WHERE dropoff.authorizeduser='%s'",
  //            $this->database->escapeString($username)
  //            ),
  //          SQLITE3_NUM);
  //return $this->DBUserQuota($username) - $result[0][0];
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
  //                  $this->database->escapeString($username));
  //if (!$this->database->exec($query)) {
  //  return sprintf(gettext("Failed to update quota for user %s"), $username);
  //}
  //return '';
}

public function DBListLocalUsers () {
  return $this->arrayQuery(
           "SELECT * FROM usertable ORDER BY username",
           SQLITE3_ASSOC
         );
}

public function DBListClaims ( $claimID, &$extant ) {
  $extant = $this->arrayQuery("SELECT * FROM dropoff WHERE claimID = '".$this->database->escapeString($claimID)."'",SQLITE3_NUM);
}

public function DBWriteReqData( $dropbox, $hash, $srcname, $srcemail, $srcorg, $destname, $destemail, $note, $subject, $expiry, $start, $passphrase = '') {
    if ( ! $this->DBStartTran() ) {
      $dropbox->writeToLog("Error: failed to BEGIN transaction adding request for $srcemail");
      return '';
    }
    if (!isset($passphrase)) $passphrase = '';

    // If the new column is missing and we can't add it, try to continue anyway
    if ((!$this->DBReqPassphraseExists() || !$this->DBReqStartExists()) &&
        (!$this->DBReqAddPassphrase()    && !$this->DBReqAddStart())) {
      $dropbox->writeToLog("Error: failed to add new columns Passphrase text NOT NULL DEFAULT '' and Start bigint NOT NULL DEFAULT 0 to database table reqtable. Please add them by hand");
      $query = sprintf("INSERT INTO reqtable
                        (Auth,SrcName,SrcEmail,SrcOrg,DestName,DestEmail,Note,Subject,Expiry)
                        VALUES
                        ('%s','%s','%s','%s','%s','%s','%s','%s',%d)",
                        $hash,
                        $this->database->escapeString($srcname),
                        $this->database->escapeString($srcemail),
                        $this->database->escapeString($srcorg),
                        $this->database->escapeString($destname),
                        $this->database->escapeString($destemail),
                        $this->database->escapeString($note),
                        $this->database->escapeString($subject),
                        $expiry);
    } else {
      $query = sprintf("INSERT INTO reqtable
                        (Auth,SrcName,SrcEmail,SrcOrg,DestName,DestEmail,Note,Subject,Expiry,Start,Passphrase)
                        VALUES
                        ('%s','%s','%s','%s','%s','%s','%s','%s',%d,%d,'%s')",
                        $hash,
                        $this->database->escapeString($srcname),
                        $this->database->escapeString($srcemail),
                        $this->database->escapeString($srcorg),
                        $this->database->escapeString($destname),
                        $this->database->escapeString($destemail),
                        $this->database->escapeString($note),
                        $this->database->escapeString($subject),
                        $expiry,
                        $start,
                        $this->database->escapeString($passphrase));
    }

    sodium_memzero($passphrase);
    if ( ! $this->database->exec($query) ) {
      //  Exit gracefully -- dump database changes
      $dropbox->writeToLog("Error: failed to add req $hash to reqtable");
      if ( ! $this->DBRollbackTran() ) {
        $dropbox->writeToLog("Error: failed to ROLLBACK after botched addition of $hash to reqtable");
      }
      return '';
    }
    $this->DBCommitTran();
    return $hash;
}

public function DBReadReqData( $authkey ) {
    $recordlist = $this->arrayQuery(
                  sprintf("SELECT * FROM reqtable WHERE Auth = '%s'",
                          $this->database->escapeString($authkey)),
                  SQLITE3_ASSOC
                );
    return $recordlist;
}

public function DBDeleteReqData( $authkey ) {
    $this->arrayQuery(
      sprintf("DELETE FROM reqtable WHERE Auth = '%s'", $this->database->escapeString($authkey)),
              SQLITE3_ASSOC);
}

public function DBPruneReqData( $old ) {
    $this->arrayQuery(
      sprintf("DELETE FROM reqtable WHERE Expiry < %d", $old),
              SQLITE3_ASSOC);
}

public function DBWriteAuthData( $dropbox, $hash, $name, $email, $org, $expiry ) {
    if ( ! $this->DBStartTran() ) {
      $dropbox->writeToLog("Error: failed to BEGIN transaction adding authdata for $email");
      return '';
    }
    $query = sprintf("INSERT INTO authtable
                      (Auth,FullName,Email,Organization,Expiry)
                      VALUES
                      ('%s','%s','%s','%s',%d)",
                     $hash,
                     $this->database->escapeString($name),
                     $this->database->escapeString($email),
                     $this->database->escapeString($org),
                     $expiry);
    if ( ! $this->database->exec($query) ) {
      //  Exit gracefully -- dump database changes
      $dropbox->writeToLog("Error: failed to add authdata for $email to authtable");
      if ( ! $this->DBRollbackTran() ) {
        $dropbox->writeToLog("Error: failed to ROLLBACK after botched addition for $email to authtable");
      }
      return '';
    }
    $this->DBCommitTran();
    return $hash;
}

public function DBReadAuthData( $authkey ) {
    $recordlist = $this->arrayQuery(
                  sprintf("SELECT * FROM authtable WHERE Auth = '%s'",
                          $this->database->escapeString($authkey)),
                  SQLITE3_ASSOC
                );
    return $recordlist;
}

public function DBDeleteAuthData( $authkey ) {
    $this->database->exec(
      sprintf("DELETE FROM authtable WHERE Auth = '%s'", $this->database->escapeString($authkey)));
}

public function DBPruneAuthData( $old ) {
    $this->database->exec(
      sprintf("DELETE FROM authtable WHERE Expiry < '%d'", $old));
}

public function DBDropoffsForMe( $targetEmail ) {
  $rows = $this->arrayQuery(
           "SELECT d.rowID,d.* FROM dropoff d,recipient r WHERE d.rowID = r.dID AND r.recipEmail = '".$this->database->escapeString($targetEmail)."' ORDER BY d.created DESC",
           SQLITE3_ASSOC
         );
  return $this->dropbox->TrimOffDying($rows, 0);
}

public function DBDropoffsFromMe( $authSender, $targetEmail ) {
  $rows = $this->arrayQuery(
           sprintf("SELECT rowID,* FROM dropoff WHERE authorizedUser = '".$this->database->escapeString($authSender)."' %s ORDER BY created DESC",
               ( $targetEmail ? ("OR senderEmail = '".$this->database->escapeString($targetEmail)."'") : "")
             ),
             SQLITE3_ASSOC
           );
  return $this->dropbox->TrimOffDying($rows, 0);
}


public function DBUpdateSchema() {
  //
  // This is only ever called by cleanup.php.
  //
  // Add the Passphrase column to reqtable if it doesn't already exist
  if (!$this->DBReqPassphraseExists()) {
    $this->DBReqAddPassphrase();
  }
  // Add the lifeseconds column to dropoff table if it doesn't already exist
  if (!$this->DBLifesecondsExists()) {
    $this->DBAddLifeseconds();
  }
  // Add the subject column to dropoff table if it doesn't already exist
  if (!$this->DBSubjectExists()) {
    $this->DBAddSubject();
  }
}

// Does the table 'dropoff' contain a field called "subject"?
private function DBSubjectExists() {
  // This generates a PHP warning if the column doesn't exist,
  // which we want to suppress as that's exactly what we're testing for.
  $res = @$this->database->prepare('SELECT subject FROM dropoff');
  if ($res === FALSE) {
    return FALSE;
  } else {
    $res->close();
    return TRUE;
  }
}

private function DBAddSubject() {
  $res = @$this->database->exec(
         "ALTER TABLE dropoff ADD COLUMN subject character varying(500) DEFAULT NULL"
         );
  return $res;
}

// Does the table 'dropoff' contain a field called "lifeseconds"?
private function DBLifesecondsExists() {
  // This generates a PHP warning if the column doesn't exist,
  // which we want to suppress as that's exactly what we're testing for.
  $res = @$this->database->prepare('SELECT lifeseconds FROM dropoff');
  if ($res === FALSE) {
    return FALSE;
  } else {
    $res->close();
    return TRUE;
  }
}

private function DBAddLifeseconds() {
  $res = @$this->database->exec(
         "ALTER TABLE dropoff ADD COLUMN lifeseconds int NOT NULL DEFAULT 0"
         );
  return $res;
}

// Does the table 'reqtable' contain a field called "Passphrase"?
private function DBReqPassphraseExists() {
  // This generates a PHP warning if the column doesn't exist,
  // which we want to suppress as that's exactly what we're testing for.
  $res = @$this->database->prepare('SELECT Passphrase FROM reqtable');
  if ($res === FALSE) {
    return FALSE;
  } else {
    $res->close();
    return TRUE;
  }
}

private function DBReqAddPassphrase() {
  $res = @$this->database->exec(
         "ALTER TABLE reqtable ADD COLUMN Passphrase text NOT NULL DEFAULT ''"
         );
  return $res;
}

// Does the table 'reqtable' contain a field called "Start"?
private function DBReqStartExists() {
  // This generates a PHP warning if the column doesn't exist,
  // which we want to suppress as that's exactly what we're testing for.
  $res = @$this->database->prepare('SELECT Start FROM reqtable');
  if ($res === FALSE) {
    return FALSE;
  } else {
    $res->close();
    return TRUE;
  }
}

private function DBReqAddStart() {
  $res = @$this->database->exec(
         "ALTER TABLE reqtable ADD COLUMN Start bigint NOT NULL DEFAULT 0"
         );
  return $res;
}

// Does not trim expired ones as it's only used for stats
public function DBDropoffsToday( $targetDate ) {
  return $this->arrayQuery(
           "SELECT rowID,* FROM dropoff WHERE created >= '$targetDate' ORDER BY created",
           SQLITE3_ASSOC
         );
}

// Note this does NOT trim expired ones
public function DBDropoffsAll() {
  return $this->arrayQuery(
           "SELECT rowID,* FROM dropoff ORDER BY created DESC",
           SQLITE3_ASSOC
         );
}

// Note this does NOT trim expired ones
public function DBDropoffsAllRev() {
  return $this->arrayQuery(
           "SELECT rowID,* FROM dropoff ORDER BY created",
           SQLITE3_ASSOC
         );
}

public function DBFilesByDropoffID( $dropoffID ) {
  $res = $this->database->query(
           sprintf("SELECT rowID,* FROM file WHERE dID = %d ORDER by rowID",
             $this->database->escapeString($dropoffID)));

  $i = 0;
  $qResult = array();
  while ($line = $res->fetchArray(SQLITE3_ASSOC)) {
    // SQLite3 lower-cases all the named field names in SELECT statements!
    if (isset($line['rowid']) && !isset($line['rowID'])) {
      $line['rowID'] = $line['rowid'];
    }
    if (isset($line['mimetype']) && !isset($line['mimeType'])) {
      $line['mimeType'] = $line['mimetype'];
    }
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
  $result = $this->arrayQuery(
           sprintf("SELECT COUNT(*),SUM(lengthInBytes) FROM file WHERE dID IN (%s)",
             $this->database->escapeString($set)),
           SQLITE3_NUM
         );
  return $result[0];
}

public function DBBytesOfDropoff( $dropoffID ) {
  $result = $this->arrayQuery(
             sprintf("SELECT SUM(lengthInBytes) FROM file WHERE dID = %d",
               $this->database->escapeString($dropoffID)),
             SQLITE3_NUM
           );
  return $result[0][0];
}

public function DBAddFile2( $d, $dropoffID, $tmpname, $filename,
                    $contentLen, $mimeType, $description, $claimID ) {
  if ( ! $this->DBStartTran() ) {
    $d->writeToLog("Error: failed to BEGIN transaction adding $filename to dropoff $claimID");
    return false;
  }

  $mimeType = preg_replace('/[<>]/', '', $mimeType); // extra sanitising!

  $query = sprintf("INSERT INTO file (dID,tmpname,basename,lengthInBytes,mimeType,description) VALUES (%d,'%s','%s',%.0f,'%s','%s')",
             $this->database->escapeString($dropoffID),
             $this->database->escapeString(basename($tmpname)),
             // SLASH sqlite_escape_string(stripslashes($filename)),
             $this->database->escapeString($filename),
             $contentLen,
             $this->database->escapeString($mimeType),
             // SLASH sqlite_escape_string(stripslashes($description))
             $this->database->escapeString($description)
          );
  if ( ! $this->database->exec($query) ) {
    //  Exit gracefully -- dump database changes and remove the dropoff
    //  directory:
    $d->writeToLog("Error: failed adding $filename to dropoff $claimID");
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
               $this->database->escapeString($dropoffID)));
  } else {
    $res = $this->database->query(
             sprintf("SELECT * FROM file WHERE dID = %d AND rowID = %d",
               $this->database->escapeString($dropoffID),
               $this->database->escapeString($fileID)));
  }

  $i = 0;
  $qResult = array();
  while ($line = $res->fetchArray(SQLITE3_ASSOC)) {
    // SQLite3 lower-cases all the named field names in SELECT statements!
    if (isset($line['rowid']) && !isset($line['rowID'])) {
      $line['rowID'] = $line['rowid'];
    }
    if (isset($line['mimetype']) && !isset($line['mimeType'])) {
      $line['mimeType'] = $line['mimetype'];
    }
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
        $query = sprintf("SELECT count(*) FROM pickup WHERE dID = %d AND emailAddr='%s'", $this->database->escapeString($dropoffID), $this->database->escapeString($email));
        $res = $this->database->query($query);
        if (!$res) return 0;
        $row = $res->fetchArray();
        return $row[0];
}

public function DBPickupFailures( $dropoffID ) {
          $query = sprintf("SELECT count(*) FROM pickup WHERE dID = %d AND emailAddr='FAILEDATTEMPT'", $this->database->escapeString($dropoffID));
          $res = $this->database->query($query);
          if (!$res) return 0;
          $row = $res->fetchArray();
          return $row[0];
}

public function DBAddToPickupLog( $d, $dropoffID, $authorizedUser, $emailAddr,
                                  $remoteAddr, $timeStamp, $claimID ) {
  $query = sprintf("INSERT INTO pickup (dID,authorizedUser,emailAddr,recipientIP,pickupTimestamp) VALUES (%d,'%s','%s','%s','%s')",
             $this->database->escapeString($dropoffID),
             $this->database->escapeString($authorizedUser),
             $this->database->escapeString($emailAddr),
             $this->database->escapeString($remoteAddr),
             $this->database->escapeString($timeStamp)
           );
  if ( ! $this->database->exec($query) ) {
    $d->writeToLog("Error: failed to add pickup record for $emailAddr to claimID ".$claimID);
  }
}

public function DBRemoveDropoff( $d, $dropoffID, $claimID ) {
      if ( $this->DBStartTran() ) {
        $query = sprintf("DELETE FROM pickup WHERE dID = %d",$this->database->escapeString($dropoffID));
        if ( ! $this->database->exec($query) ) {
          if ( $doLogEntries ) {
            $d->writeToLog("Error: failed to delete pickup records for claimID $claimID with '$query'");
          }
          $this->DBRollbackTran();
          return FALSE;
        }

        $query = sprintf("DELETE FROM file WHERE dID = %d",$this->database->escapeString($dropoffID));
        if ( ! $this->database->exec($query) ) {
          if ( $doLogEntries ) {
            $d->writeToLog("Error: failed to delete file records for claimID $claimID with '$query'");
          }
          $this->DBRollbackTran();
          return FALSE;
        }

        $query = sprintf("DELETE FROM recipient WHERE dID = %d",$this->database->escapeString($dropoffID));
        if ( ! $this->database->exec($query) ) {
          if ( $doLogEntries ) {
            $d->writeToLog("Error: failed to delete recipient records for claimID $claimID with '$query'");
          }
          $this->DBRollbackTran();
          return FALSE;
        }

        $query = sprintf("DELETE FROM dropoff WHERE claimID = '%s'",$this->database->escapeString($claimID));
        if ( ! $this->database->exec($query) ) {
          if ( $doLogEntries ) {
            $d->writeToLog("Error: failed to delete dropoff records for claimID $claimID with '$query'");
          }
          $this->DBRollbackTran();
          return FALSE;
        }

        if ( ! $this->DBCommitTran() ) {
          if ( $doLogEntries ) {
            $d->writeToLog("Error: failed to COMMIT deletion of claimID ".$claimID);
          }
          $this->DBRollbackTran();
          return FALSE;
        }
        return TRUE;
      }
      return FALSE;
}

public function DBFilesForDropoff ( $dropoffID ) {
  $res = $this->database->query(
           sprintf("SELECT rowID,* FROM file WHERE dID = %d ORDER by rowID",
             $this->database->escapeString($dropoffID)));

  $i = 0;
  $qResult = array();
  while ($line = $res->fetchArray(SQLITE3_ASSOC)) {
    // SQLite3 lower-cases all the named field names in SELECT statements!
    if (isset($line['rowid']) && !isset($line['rowID'])) {
      $line['rowID'] = $line['rowid'];
    }
    if (isset($line['mimetype']) && !isset($line['mimeType'])) {
      $line['mimeType'] = $line['mimetype'];
    }
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
  while ($line = $res->fetchArray(SQLITE3_NUM)) {
    $counters[$line[0]] = $line[1];
  }
  return $counters;
}

public function DBPickupsForDropoff ( $dropoffID ) {
  return $this->arrayQuery(
           sprintf("SELECT * FROM pickup WHERE dID = %d AND emailAddr != 'FAILEDATTEMPT' ORDER by pickupTimestamp",
             $this->database->escapeString($dropoffID)
           ),
           SQLITE3_ASSOC
         );
}

public function DBDropoffsForClaimID ( $claimID ) {
  $rows = $this->arrayQuery(
           sprintf("SELECT rowID,* FROM dropoff WHERE claimID = '%s'",
             $this->database->escapeString($claimID)
           ),
           SQLITE3_ASSOC
         );
  return $this->dropbox->TrimOffDying($rows, 0);
}

public function DBRecipientsForDropoff ( $rowID ) {
  return $this->arrayQuery(
           sprintf("SELECT recipName,recipEmail FROM recipient WHERE dID = %d",
             $this->database->escapeString($rowID)
           ),
           SQLITE3_NUM
         );
}

public function DBStartTran () {
  return $this->database->exec('BEGIN');
}

public function DBRollbackTran () {
  return $this->database->exec('ROLLBACK');
}

public function DBCommitTran () {
  return $this->database->exec('COMMIT');
}

public function DBTouchDropoff ( $claimID, $now ) {
  $query = sprintf("UPDATE dropoff SET created='%s' WHERE claimID='%s'",
             $this->database->escapeString($now),
             $this->database->escapeString($claimID)
           );
  if ( $this->database->exec($query) ) {
    return TRUE;
  }
  return FALSE;
}

public function DBAddDropoff ( $claimID, $claimPasscode, $authorizedUser,
                               $senderName, $senderOrganization, $senderEmail,
                               $remoteIP, $confirmDelivery,
                               $now, $note, $lifeseconds, $subject ) {
  $query = sprintf("INSERT INTO dropoff
                    (claimID,claimPasscode,authorizedUser,senderName,
                     senderOrganization,senderEmail,senderIP,
                     confirmDelivery,created,note,lifeseconds,subject)
                    VALUES
                    ('%s','%s','%s','%s', '%s','%s','%s','%s','%s','%s',%d,'%s')",
             $this->database->escapeString($claimID),
             $this->database->escapeString($claimPasscode),
             $this->database->escapeString($authorizedUser),
             $this->database->escapeString($senderName),
             $this->database->escapeString($senderOrganization),
             $this->database->escapeString($senderEmail),
             $this->database->escapeString($remoteIP),
             ( $confirmDelivery ? 't' : 'f' ),
             $this->database->escapeString($now),
             $this->database->escapeString($note),
             $lifeseconds,
             $this->database->escapeString($subject)
           );
  if ( $this->database->exec($query) ) {
    return $this->database->lastInsertRowID();
  }
  return FALSE;
}

public function DBAddRecipients ( $recipients, $dropoffID ) {
  foreach ( $recipients as $recipient ) {
    $query = sprintf("INSERT INTO recipient
                      (dID,recipName,recipEmail)
                      VALUES
                      (%d,'%s','%s')",
               $this->database->escapeString($dropoffID),
               $this->database->escapeString($recipient[0]),
               $this->database->escapeString($recipient[1]));
    if ( ! $this->database->exec($query) ) {
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
             $this->database->escapeString($dropoffID),
             $this->database->escapeString($tmpname),
             $this->database->escapeString($basename),
             $this->database->escapeString($bytes),
             $this->database->escapeString($mimeType),
             $this->database->escapeString($description));
  if ( ! $this->database->exec($query) ) {
    return FALSE;
  }
  return TRUE;
}

public function DBGetAddressbook ( $user ) {
  return $this->arrayQuery(
            sprintf("SELECT name,email FROM addressbook WHERE username='%s'",
                    $this->database->escapeString($user)), SQLITE3_ASSOC);
}

public function DBUpdateAddressbook ( $user, $recips ) {
  $user = $this->database->escapeString($user);
  foreach ($recips as $recip) {
    $name  = $this->database->escapeString(trim($recip[0]));
    $email = $this->database->escapeString(trim($recip[1]));
    $query = $this->arrayQuery(
               sprintf("SELECT COUNT(*) FROM addressbook WHERE username='%s' AND name='%s' AND email='%s'",
                       $user, $name, $email),
               SQLITE3_NUM);
    $now = time();
    if ($query[0][0]>=1) {
      // Entry for this person already exists, so UPDATE
      $query = $this->database->exec(
                 sprintf("UPDATE addressbook SET lastused=%d WHERE username='%s' AND name='%s' AND email='%s'",
                         $now, $user, $name, $email));
    } else {
      $query = $this->database->exec(
                 sprintf("INSERT INTO addressbook (username,name,email,lastused) VALUES ('%s','%s','%s',%d)",
                         $user, $name, $email, $now));
    }
  }
}

public function DBDeleteAddressbookEntry($user, $name, $email) {
  $user  = $this->database->escapeString($user);
  $name  = $this->database->escapeString($name);
  $email = $this->database->escapeString($email);
  $query = $this->database->exec(
            sprintf("DELETE FROM addressbook WHERE username='%s' AND name='%s' AND email='%s'",
                    $user, $name, $email));
  return $query;
}

// Build a mapping from filename to description
public function DBGetLibraryDescs () {
  $query = $this->arrayQuery(
            "SELECT filename,description FROM librarydesc",
            SQLITE3_NUM
           );
  $result = array();
  foreach ($query as $q) {
    // Only set it if it's not already set and we're setting it non-blank
    if (!isset($result[$q[0]]) && isset($q[1])) {
      // Trim the leading+trailing space from everything
      $result[trim($q[0])] = trim($q[1]);
    }
    // $result[$q[0]] = $q[1];
  }
  return $result;
}

// Update the mapping from filename to description
public function DBUpdateLibraryDescription ( $file, $desc ) {
  $file = trim($file);
  $desc = trim($desc);
  $query = $this->arrayQuery(
             sprintf("SELECT COUNT(*) FROM librarydesc WHERE filename='%s'",
               $this->database->escapeString($file)),
             SQLITE3_NUM
           );
  if ($query[0][0]>=1) {
    // Entry for this filename already exists, so UPDATE
    $query = sprintf("UPDATE librarydesc SET description='%s' WHERE filename='%s'",
               $this->database->escapeString($desc),
               $this->database->escapeString($file));
    $query = $this->database->exec($query);
  } else {
    // Entry for this filename does not exist, so INSERT
    $query = sprintf("INSERT INTO librarydesc (filename,description) VALUES ('%s','%s')",
               $this->database->escapeString($file),
               $this->database->escapeString($desc));
    $query = $this->database->exec($query);
  }
}

}

?>
