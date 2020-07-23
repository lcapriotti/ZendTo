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
public $_newDatabase = FALSE;
private $dropbox = NULL;

public function __construct( $prefs, $aDropbox ) {
  $this->dropbox = $aDropbox; // Allows me to write to ZendTo log
  $this->DBConnect($prefs['SQLiteDatabase'], $aDropbox);
}  

//
// Database functions that work on NSSDropbox objects
//

private function DBConnect ( $sqlitename, $aDropbox ) {
  if ( ! file_exists($sqlitename) ) {
    if ( ! ($this->database = new SQLiteDatabase($sqlitename,0666)) ) {
      NSSError(gettext("Could not create the new database."), gettext("Database Error"));
      return FALSE;
    }
    //  It was a new file, so we need to create tables in the database
    //  right now, too!
    if ( ! $this->DBSetupDatabase($aDropbox) ) {
      NSSError(gettext("Could not create the tables in the new database."), gettext("Database Error"));
      $this->database = NULL; // Useless DB, so bin it
      return FALSE;
    }
    //  This was a new database:
    $this->_newDatabase = TRUE;
  } else {
    if ( ! ($this->database = new SQLiteDatabase($sqlitename)) ) {
      NSSError(gettext("Could not open the database."), gettext("Database Error"));
      return FALSE;
    }
    $table_t = gettext("Database Error creating %s table");
    // If the librarydesc table doesn't exist, create it!
    $query = $this->database->query("SELECT name FROM sqlite_master WHERE type='table' and name='librarydesc'"); 
    if ($query->numRows()<1){ 
      /* table does not exist...create it or do something... */ 
      if ( ! $this->DBCreateLibraryDesc() ) {
        $dropbox->writeToLog("Error: dailed to add librarydesc to database");
        NSSError($errorMsg, sprintf($table_t, 'librarydesc'));
        return FALSE;
      }
    }
    // If the addressbook table doesn't exist, create it!
    $query = $this->database->query("SELECT name FROM sqlite_master WHERE type='table' and name='addressbook'"); 
    if ($query->numRows()<1){ 
      /* table does not exist...create it or do something... */ 
      if ( ! $this->DBCreateAddressbook() ) {
        $dropbox->writeToLog("Error: failed to add addressbook to database");
        NSSError($errorMsg, sprintf($table_t, 'addressbook'));
        return FALSE;
      }
    }
  }
  return TRUE;
}

// Create the database as we need to do this for SQLite
private function DBSetupDatabase($dropbox) {
    if ( $this->database ) {

      if ( ! $this->DBCreate()) {
        NSSError($errorMsg,gettext("Database Error"));
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
      if ( ! $this->database->queryExec(
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
  lifeseconds         integer default 0,
  subject             character varying(500) default null
);",$errorMsg) ) {
        return FALSE;
      }

      if ( ! $this->database->queryExec(
"CREATE TABLE recipient (
  dID                 integer not null,
  
  recipName           character varying(32) not null,
  recipEmail          text not null
);",$errorMsg) ) {
        return FALSE;
      }

      if ( ! $this->database->queryExec(
"CREATE TABLE file (
  dID                 integer not null,
  
  tmpname             text not null,
  basename            text not null,
  lengthInBytes       bigint not null,
  mimeType            character varying(256) not null,
  description         text
);",$errorMsg) ) {
        return FALSE;
      }

      if ( ! $this->database->queryExec(
"CREATE TABLE pickup (
  dID                 integer not null,
  
  authorizedUser      character varying(255),
  emailAddr           text,
  recipientIP         character varying(255) not null,
  pickupTimestamp     timestamp with time zone not null
);",$errorMsg) ) {
        return FALSE;
      }

      //  Do the indexes now:

      if ( ! $this->database->queryExec(
"CREATE INDEX dropoff_claimID_index ON dropoff(claimID);",$errorMsg) ) {
        return FALSE;
      }

      if ( ! $this->database->queryExec(
"CREATE INDEX recipient_dID_index ON recipient(dID);",$errorMsg) ) {
        return FALSE;
      }

      if ( ! $this->database->queryExec(
"CREATE INDEX file_dID_index ON file(dID);",$errorMsg) ) {
        return FALSE;
      }

      if ( ! $this->database->queryExec(
"CREATE INDEX pickup_dID_index ON pickup(dID);",$errorMsg) ) {
        return FALSE;
      }

      return TRUE;
}

public function DBCreateReq() {
      if ( ! $this->database->queryExec(
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
);",$errorMsg) ) {
        return FALSE;
      }
      return TRUE;
}

// Needs to be public for people upgrading from Dropbox 2
public function DBCreateAuth() {
      if ( ! $this->database->queryExec(
"CREATE TABLE authtable (
  Auth         character varying(64) not null,
  FullName     character varying(32),
  Email        text not null,
  Organization character varying(32),
  Expiry       bigint not null
);",$errorMsg) ) {
        return FALSE;
      }
      return TRUE;
}

// Needs to be public for people upgrading from earlier versions
public function DBCreateUser() {
      if ( ! $this->database->queryExec(
"CREATE TABLE usertable (
  username     character varying(64) not null,
  password     character varying(256) not null,
  mail         character varying(256) not null,
  displayname  character varying(256) not null,
  organization character varying(256),
  quota        real
);",$errorMsg) ) {
        return FALSE;
      }
      if ( ! $this->database->queryExec(
"CREATE INDEX usertable_username_index ON usertable(username);",$errorMsg) ) {
        return FALSE;
      }
      return TRUE;
}

// Needs to be public for people upgrading from earlier versions
public function DBCreateRegexps() {
      if ( ! $this->database->queryExec(
"CREATE TABLE regexps (
  type         integer not null,
  re           text not null,
  created      bigint not null
);",$errorMsg) ) {
        return FALSE;
      }
      if ( ! $this->database->queryExec(
"CREATE INDEX regexpstable_type_index ON regexps(type);",$errorMsg) ) {
        return FALSE;
      }
      return TRUE;
}

// Needs to be public for people upgrading from earlier ZendTos
public function DBCreateLoginlog() {
      if ( ! $this->database->queryExec(
"CREATE TABLE loginlog (
  username     character varying(255) not null,
  created      bigint not null
);",$errorMsg) ) {
        return FALSE;
      }
      return TRUE;
}

// Needs to be public for people upgrading from earlier ZendTo
public function DBCreateLibraryDesc() {
      if ( ! $this->database->queryExec(
"CREATE TABLE librarydesc (
  filename    character varying(255) not null,
  description character varying(255)
);",$errorMsg) ) {
        return FALSE;
      }
      return TRUE;
}

public function DBCreateAddressbook() {
      if ( ! $this->database->queryExec(
"CREATE TABLE addressbook (
  username    character varying(255) not null,
  name        character varying(255),
  email       character varying(255) not null,
  lastused    timestamp with time zone not null
);",$errorMsg) ) {
        return FALSE;
      }
      if ( ! $this->database->queryExec(
"CREATE INDEX addressbook_username_index ON addressbook(username);",$errorMsg) ) {
        return FALSE;
      }

      return TRUE;
}

public function DBAddLoginlog($user) {
  $query = sprintf("INSERT INTO loginlog
                    (username, created)
                    VALUES
                    ('%s',%d)",
                    sqlite_escape_string(strtolower($user)),
                    time());
  if (!$this->database->queryExec($query)) {
    return gettext("Failed to add login record");
  }
  return '';
}

public function DBDeleteLoginlog($user) {
  if ($user == "") {
    $query = "DELETE FROM loginlog";
  } else {
    $query = sprintf("DELETE FROM loginlog WHERE username = '%s'",
                     sqlite_escape_string(strtolower($user)));
  }
  if ( !$this->database->queryExec($query) ) {
    return gettext("Failed to delete login records");
  }
  return '';
}

public function DBLoginlogLength($user, $since) {
  $query = sprintf("SELECT count(*) FROM loginlog
                    WHERE username = '%s' AND created > %d",
                   sqlite_escape_string(strtolower($user)),
                   $since);
  $res = $this->database->singleQuery($query);
  if (!$res) {
    return gettext("Failed to read anything from loginlog");
  }
  return $res[0];
}

public function DBLoginlogAll($since) {
  $recordlist = $this->database->arrayQuery(
                  sprintf("SELECT * FROM loginlog WHERE created > %d",
                          $since),
                  SQLITE_ASSOC
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
  if ( ! $this->database->queryExec(
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
                      sqlite_escape_string($re),
                      $now);
    if (!$this->database->queryExec($query)) {
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
  $res = $this->database->arrayQuery(
         sprintf("SELECT re,created FROM regexps WHERE type = %d",
                 $type),
         SQLITE_ASSOC);
  return $res;
}


public function DBReadLocalUser( $username ) {
    $recordlist = $this->database->arrayQuery(
                  sprintf("SELECT * FROM usertable WHERE username = '%s'",
                          sqlite_escape_string($username)),
                  SQLITE_ASSOC
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
                    sqlite_escape_string($username),
                    password_hash($password, PASSWORD_DEFAULT),
                    sqlite_escape_string($email),
                    sqlite_escape_string($name),
                    sqlite_escape_string($org));

  if (!$this->database->queryExec($query)) {
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

  $this->database->arrayQuery(
    sprintf("DELETE FROM usertable WHERE username = '%s'",
            sqlite_escape_string($username)),
            SQLITE_ASSOC);
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
                    sqlite_escape_string($username));
  if (!$this->database->queryExec($query)) {
    return sprintf(gettext("Failed to update password for user %s"), $username);
  }
  return '';
}

public function DBUserQuota ( $username ) {
  return 1;
  //global $NSSDROPBOX_PREFS;
  //$result = $this->database->arrayQuery(
  //         sprintf("SELECT quota FROM usertable WHERE username = '%s'",
  //           sqlite_escape_string($username)
  //         )
  //       );
  //return ($quota >= 1) ? $quota : $NSSDROPBOX_PREFS['defaultMyZendToQuota'];
}

public function DBRemainingQuota ( $username ) {
  return 1;
  //$result = $this->database->arrayQuery(
  //          sprintf("SELECT SUM(lengthInBytes) AS usedQuotaInBytes FROM dropoff LEFT JOIN file ON dropoff.rowID=file.dID WHERE dropoff.authorizeduser='%s'",
  //            sqlite_escape_string($username)
  //            )
  //          );
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
  //                  sqlite_escape_string($username));
  //if (!$this->database->queryExec($query)) {
  //  return sprintf(gettext("Failed to update quota for user %s"), $username);
  //}
  //return '';
}

public function DBListLocalUsers () {
  return $this->database->arrayQuery(
           "SELECT * FROM usertable ORDER BY username",
           SQLITE_ASSOC
         );
}

public function DBListClaims ( $claimID, &$extant ) {
  $extant = $this->database->arrayQuery("SELECT * FROM dropoff WHERE claimID = '".sqlite_escape_string($claimID)."'");
}

public function DBWriteReqData( $dropbox, $hash, $srcname, $srcemail, $srcorg, $destname, $destemail, $note, $subject, $expiry, $start, $passphrase = '') {
    if ( ! $this->DBStartTran() ) {
      $dropbox->writeToLog("Error: failed to BEGIN transaction adding request for $srcemail");
      return '';
    }
    // Try to add the column every time. If it fails, then hell.
    // It might fail because it's already there, which is fine.
    // It might fail for a million other reasons, but no one uses this
    // code any more anyway, so I don't care too much.
    // Suppress any PHP warning from it.
    @$this->database->exec('ALTER TABLE reqtable ADD COLUMN Passphrase text not null');
    @$this->database->exec('ALTER TABLE reqtable ADD COLUMN Start bigint not null DEFAULT 0');
    if (!isset($passphrase)) $passphrase = '';
    $query = sprintf("INSERT INTO reqtable
                      (Auth,SrcName,SrcEmail,SrcOrg,DestName,DestEmail,Note,Subject,Expiry,Start,Passphrase)
                      VALUES
                      ('%s','%s','%s','%s','%s','%s','%s','%s',%d,%d,'%s')",
                      $hash,
                      sqlite_escape_string($srcname),
                      sqlite_escape_string($srcemail),
                      sqlite_escape_string($srcorg),
                      sqlite_escape_string($destname),
                      sqlite_escape_string($destemail),
                      sqlite_escape_string($note),
                      sqlite_escape_string($subject),
                      $expiry,
                      $start,
                      sqlite_escape_string($passphrase));
    sodium_memzero($passphrase);
    if ( ! $this->database->queryExec($query) ) {
      //  Exit gracefully -- dump database changes
      $dropbox->writeToLog("Error: failed to add $hash for $srcemail to reqtable");
      if ( ! $this->DBRollbackTran() ) {
        $dropbox->writeToLog("Error: failed to ROLLBACK after botched addition of $hash for $srcemail to reqtable");
      }
      return '';
    }
    $this->DBCommitTran();
    return $hash;
}

public function DBReadReqData( $authkey ) {
    $recordlist = $this->database->arrayQuery(
                  sprintf("SELECT * FROM reqtable WHERE Auth = '%s'",
                          sqlite_escape_string($authkey)),
                  SQLITE_ASSOC
                );
    return $recordlist;
}

public function DBDeleteReqData( $authkey ) {
    $this->database->arrayQuery(
      sprintf("DELETE FROM reqtable WHERE Auth = '%s'", sqlite_escape_string($authkey)),
              SQLITE_ASSOC);
}

public function DBPruneReqData( $old ) {
    $this->database->arrayQuery(
      sprintf("DELETE FROM reqtable WHERE Expiry < %d", $old),
              SQLITE_ASSOC);
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
                     sqlite_escape_string($name),
                     sqlite_escape_string($email),
                     sqlite_escape_string($org),
                     $expiry);
    if ( ! $this->database->queryExec($query) ) {
      //  Exit gracefully -- dump database changes
      $dropbox->writeToLog("Error: failed to add $hash for $email to authtable");
      if ( ! $this->DBRollbackTran() ) {
        $dropbox->writeToLog("Error: failed to ROLLBACK after botched addition of $hash for $email to authtable");
      }
      return '';
    }
    $this->DBCommitTran();
    return $hash;
}

public function DBReadAuthData( $authkey ) {
    $recordlist = $this->database->arrayQuery(
                  sprintf("SELECT * FROM authtable WHERE Auth = '%s'",
                          sqlite_escape_string($authkey)),
                  SQLITE_ASSOC
                );
    return $recordlist;
}

public function DBDeleteAuthData( $authkey ) {
    $this->database->arrayQuery(
      sprintf("DELETE FROM authtable WHERE Auth = '%s'", sqlite_escape_string($authkey)),
              SQLITE_ASSOC);
}

public function DBPruneAuthData( $old ) {
    $this->database->arrayQuery(
      sprintf("DELETE FROM authtable WHERE Expiry < '%d'", $old),
              SQLITE_ASSOC);
}



public function DBDropoffsForMe( $targetEmail ) {
  $rows = $this->database->arrayQuery(
           "SELECT d.rowID,d.* FROM dropoff d,recipient r WHERE d.rowID = r.dID AND r.recipEmail = '".sqlite_escape_string($targetEmail)."' ORDER BY d.created DESC",
           SQLITE_ASSOC
         );
  return $this->dropbox->TrimOffDying($rows, 0);
}

public function DBDropoffsFromMe( $authSender, $targetEmail ) {
  $rows = $this->database->arrayQuery(
           sprintf("SELECT rowID,* FROM dropoff WHERE authorizedUser = '".sqlite_escape_string($authSender)."' %s ORDER BY created DESC",
               ( $targetEmail ? ("OR senderEmail = '".sqlite_escape_string($targetEmail)."'") : "")
             ),
             SQLITE_ASSOC
           );
  return $this->dropbox->TrimOffDying($rows, 0);
}

public function DBUpdateSchema() {
  //
  // This is only ever called by cleanup.php.
  //
  // Add the Passphrase column to reqtable.
  // Only way I can find if it works is to try it!
  // It will return TRUE if it succeeded, FALSE if not. But I don't care.
  @$this->database->exec(
    "ALTER TABLE reqtable ADD COLUMN Passphrase text not null"
  );
  @$this->database->exec(
    "ALTER TABLE dropoff ADD COLUMN lifeseconds int NOT NULL DEFAULT 0"
  );
  @$this->database->exec(
    "ALTER TABLE dropoff ADD COLUMN subject character varying(500) DEFAULT NULL"
  );

}

// Does not trim expired ones as it's only used for stats
public function DBDropoffsToday( $targetDate ) {
  return $this->database->arrayQuery(
           "SELECT rowID,* FROM dropoff WHERE created >= '$targetDate' ORDER BY created",
           SQLITE_ASSOC
         );
}

// Note this does NOT trim expired ones
public function DBDropoffsAll() {
  return $this->database->arrayQuery(
           "SELECT rowID,* FROM dropoff ORDER BY created DESC",
           SQLITE_ASSOC
         );
}

// Note this does NOT trim expired ones
public function DBDropoffsAllRev() {
  return $this->database->arrayQuery(
           "SELECT rowID,* FROM dropoff ORDER BY created",
           SQLITE_ASSOC
         );
}

public function DBFilesByDropoffID( $dropoffID ) {
  $res = $this->database->query(
           sprintf("SELECT rowID,* FROM file WHERE dID = %d ORDER by rowID",
           sqlite_escape_string($dropoffID)));
  $i = 0;
  $qResult = array();
  while ($line = $res->fetch(SQLITE_ASSOC)) {
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
  $result = $this->database->arrayQuery(
           sprintf("SELECT COUNT(*),SUM(lengthInBytes) FROM file WHERE dID IN (%s)",
             sqlite_escape_string($set)),
           SQLITE_NUM
         );
  return $result[0];
}

public function DBBytesOfDropoff( $dropoffID ) {
  $result = $this->database->arrayQuery(
             sprintf("SELECT SUM(lengthInBytes) FROM file WHERE dID = %d",
               sqlite_escape_string($dropoffID)),
             SQLITE_NUM
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
             sqlite_escape_string($dropoffID),
             sqlite_escape_string(basename($tmpname)),
             // SLASH sqlite_escape_string(stripslashes($filename)),
             sqlite_escape_string($filename),
             $contentLen,
             sqlite_escape_string($mimeType),
             // SLASH sqlite_escape_string(stripslashes($description))
             sqlite_escape_string($description)
          );
  if ( ! $this->database->queryExec($query) ) {
    //  Exit gracefully -- dump database changes and remove the dropoff
    //  directory:
    $d->writeToLog("Error: failed to add file record for $filename to dropoff $claimID");
    if ( ! $this->DBRollbackTran() ) {
      $d->writeToLog("Error: failed to ROLLBACK after botched addition of file record for $filename to dropoff $claimID");
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
               sqlite_escape_string($dropoffID)));
  } else {
    $res = $this->database->query(
             sprintf("SELECT * FROM file WHERE dID = %d AND rowID = %d",
               sqlite_escape_string($dropoffID),
               sqlite_escape_string($fileID)));
  }

  $i = 0;
  $qResult = array();
  while ($line = $res->fetch(SQLITE_ASSOC)) {
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
             sqlite_escape_string($dropoffID),
             sqlite_escape_string($email));
  $res = $this->database->singleQuery($query);
  if (!$res) return 0;
  return $res[0];
}

public function DBPickupFailures( $dropoffID ) {
         $query = sprintf("SELECT count(*) FROM pickup WHERE dID = %d AND emailAddr='FAILEDATTEMPT'", $this->database->escapeString($dropoffID));
         $res = $this->database->singleQuery($query);
         if (!$res) return 0;
         return $res[0];
}

public function DBAddToPickupLog( $d, $dropoffID, $authorizedUser, $emailAddr,
                                  $remoteAddr, $timeStamp, $claimID ) {
  $query = sprintf("INSERT INTO pickup (dID,authorizedUser,emailAddr,recipientIP,pickupTimestamp) VALUES (%d,'%s','%s','%s','%s')",
             sqlite_escape_string($dropoffID),
             sqlite_escape_string($authorizedUser),
             sqlite_escape_string($emailAddr),
             sqlite_escape_string($remoteAddr),
             sqlite_escape_string($timeStamp)
           );
  if ( ! $this->database->queryExec($query) ) {
    $d->writeToLog("Error: failed to add pickup record for $emailAddr to claimID $claimID");
  }
}

public function DBRemoveDropoff( $d, $dropoffID, $claimID ) {
      if ( $this->DBStartTran() ) {
        $query = sprintf("DELETE FROM pickup WHERE dID = %d",sqlite_escape_string($dropoffID));
        if ( ! $this->database->queryExec($query) ) {
          if ( $doLogEntries ) {
            $d->writeToLog("Error: failed to delete pickup records for claimID $claimID with '$query'");
          }
          $this->DBRollbackTran();
          return FALSE;
        }

        $query = sprintf("DELETE FROM file WHERE dID = %d",sqlite_escape_string($dropoffID));
        if ( ! $this->database->queryExec($query) ) {
          if ( $doLogEntries ) {
            $d->writeToLog("Error: failed to delete file records for claimID $claimID with '$query'");
          }
          $this->DBRollbackTran();
          return FALSE;
        }

        $query = sprintf("DELETE FROM recipient WHERE dID = %d",sqlite_escape_string($dropoffID));
        if ( ! $this->database->queryExec($query) ) {
          if ( $doLogEntries ) {
            $d->writeToLog("Error: failed to delete recipient records for claimID $claimID with '$query'");
          }
          $this->DBRollbackTran();
          return FALSE;
        }

        $query = sprintf("DELETE FROM dropoff WHERE claimID = '%s'",sqlite_escape_string($claimID));
        if ( ! $this->database->queryExec($query) ) {
          if ( $doLogEntries ) {
            $d->writeToLog("Error: failed to delete dropoff records for claimID $claimID with '$query'");
          }
          $this->DBRollbackTran();
          return FALSE;
        }

        if ( ! $this->DBCommitTran() ) {
          if ( $doLogEntries ) {
            $d->writeToLog("Error: failed to COMMIT removal of claimID $claimID");
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
             sqlite_escape_string($dropoffID)
           ));

  $i = 0;
  $qResult = array();
  while ($line = $res->fetch(SQLITE_ASSOC)) {
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
  while ($line = $res->fetch(SQLITE_NUM)) {
    $counters[$line[0]] = $line[1];
  }
  return $counters;
}

public function DBPickupsForDropoff ( $dropoffID ) {
  return $this->database->arrayQuery(
           sprintf("SELECT * FROM pickup WHERE dID = %d AND emailAddr != 'FAILEDATTEMPT' ORDER by pickupTimestamp",
             sqlite_escape_string($dropoffID)
           ),
           SQLITE_ASSOC
         );
}

public function DBDropoffsForClaimID ( $claimID ) {
  $rows = $this->database->arrayQuery(
           sprintf("SELECT rowID,* FROM dropoff WHERE claimID = '%s'",
             sqlite_escape_string($claimID)
           ),
           SQLITE_ASSOC
         );
  return $this->dropbox->TrimOffDying($rows, 0);
}

public function DBRecipientsForDropoff ( $rowID ) {
  return $this->database->arrayQuery(
           sprintf("SELECT recipName,recipEmail FROM recipient WHERE dID = %d",
             sqlite_escape_string($rowID)
           ),
           SQLITE_NUM
         );
}

public function DBStartTran () {
  return $this->database->queryExec('BEGIN');
}

public function DBRollbackTran () {
  return $this->database->queryExec('ROLLBACK');
}

public function DBCommitTran () {
  return $this->database->queryExec('COMMIT');
}

public function DBTouchDropoff ( $claimID, $now ) {
  $query = sprintf("UPDATE dropoff SET created='%s' WHERE claimID='%s'",
             sqlite_escape_string($now),
             sqlite_escape_string($claimID)
           );
  if ( $this->database->queryExec($query) ) {
    return TRUE;
  }
  return FALSE;
}

public function DBAddDropoff ( $claimID, $claimPasscode, $authorizedUser,
                               $senderName, $senderOrganization, $senderEmail,
                               $remoteIP, $confirmDelivery,
                               $now, $note, $lifeseconds, $subject ) {
  // Try to add the column anyway, to be on the safe side.
  @$this->database->exec('ALTER TABLE dropoff ADD COLUMN lifeseconds int not null DEFAULT 0');
  @$this->database->exec('ALTER TABLE dropoff ADD COLUMN subject character varying(255) DEFAULT NULL');
  $query = sprintf("INSERT INTO dropoff
                    (claimID,claimPasscode,authorizedUser,senderName,
                     senderOrganization,senderEmail,senderIP,
                     confirmDelivery,created,note,lifeseconds,subject)
                    VALUES
                    ('%s','%s','%s','%s','%s','%s','%s','%s','%s','%s',%d,'%s')",
             sqlite_escape_string($claimID),
             sqlite_escape_string($claimPasscode),
             sqlite_escape_string($authorizedUser),
             sqlite_escape_string($senderName),
             sqlite_escape_string($senderOrganization),
             sqlite_escape_string($senderEmail),
             sqlite_escape_string($remoteIP),
             ( $confirmDelivery ? 't' : 'f' ),
             sqlite_escape_string($now),
             sqlite_escape_string($note),
             $lifeseconds,
             sqlite_escape_string($subject)
           );
  if ( $this->database->queryExec($query) ) {
    return $this->database->lastInsertRowid();
  }
  return FALSE;
}

public function DBAddRecipients ( $recipients, $dropoffID ) {
  foreach ( $recipients as $recipient ) {
    $query = sprintf("INSERT INTO recipient
                      (dID,recipName,recipEmail)
                      VALUES
                      (%d,'%s','%s')",
               sqlite_escape_string($dropoffID),
               sqlite_escape_string($recipient[0]),
               sqlite_escape_string($recipient[1]));
    if ( ! $this->database->queryExec($query) ) {
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
             sqlite_escape_string($dropoffID),
             sqlite_escape_string($tmpname),
             sqlite_escape_string($basename),
             sqlite_escape_string($bytes),
             sqlite_escape_string($mimeType),
             sqlite_escape_string($description));
  if ( ! $this->database->queryExec($query) ) {
    return FALSE;
  }
  return TRUE;
}

public function DBGetAddressbook ( $user ) {
  return $this->database->arrayQuery(
            sprintf("SELECT name,email FROM addressbook WHERE username='%s'",
                    sqlite_escape_string($user)), SQLITE_ASSOC);
}

public function DBUpdateAddressbook ( $user, $recips ) {
  $user = sqlite_escape_string($user);
  foreach ($recips as $recip) {
    $name  = sqlite_escape_string(trim($recip[0]));
    $email = sqlite_escape_string(trim($recip[1]));
    $query = $this->database->arrayQuery(
               sprintf("SELECT COUNT(*) FROM addressbook WHERE username='%s' AND name='%s' AND email='%s'",
                       $user, $name, $email),
               SQLITE_NUM);
    if ($query[0][0]>=1) {
      // Entry for this person already exists, so UPDATE
      $query = $this->database->queryExec(
                 sprintf("UPDATE addressbook SET lastused=now() WHERE username='%s' AND name='%s' AND email='%s'",
                         $user, $name, $email));
    } else {
      $lastused = time();
      $query = $this->database->queryExec(
                 sprintf("INSERT INTO addressbook (username,name,email,lastused) VALUES ('%s','%s','%s',%d)",
                         $user, $name, $email, $lastused));
    }
  }
}

public function DBDeleteAddressbookEntry($user, $name, $email) {
  $user  = sqlite_escape_string($user);
  $name  = sqlite_escape_string($name);
  $email = sqlite_escape_string($email);
  $query = $this->database->queryExec(
            sprintf("DELETE FROM addressbook WHERE username='%s' AND name='%s' AND email='%s'",
                    $user, $name, $email));
  return $query;
}

// Build a mapping from filename to description
public function DBGetLibraryDescs () {
  $query = $this->database->arrayQuery(
            "SELECT filename,description FROM librarydesc",
            SQLITE_NUM
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
  $query = $this->database->arrayQuery(
             sprintf("SELECT COUNT(*) FROM librarydesc WHERE filename='%s'",
               sqlite_escape_string($file)),
             SQLITE_NUM
           );
  if ($query[0][0]>=1) {
    // Entry for this filename already exists, so UPDATE
    $query = sprintf("UPDATE librarydesc SET description='%s' WHERE filename='%s'",
               sqlite_escape_string($desc),
               sqlite_escape_string($file));
    $query = $this->database->queryExec($query);
  } else {
    // Entry for this filename does not exist, so INSERT
    $query = sprintf("INSERT INTO librarydesc (filename,description) VALUES ('%s','%s')",
               sqlite_escape_string($file),
               sqlite_escape_string($desc));
    $query = $this->database->queryExec($query);
  }
}

}

?>
