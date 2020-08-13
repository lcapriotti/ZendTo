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
// Authenticator that uses a simple table in the SQL database.
// Each user has the following properties:
// username, passwordhash (stored as MD5 hash), mail, displayName and
// organization.
// The properties in the results hash from authenticate and validate are
// uid = username, mail = mail, cn = displayName, displayName = displayName,
// organization = organization.

class NSSMultiAuthenticator extends NSSAuthenticator {
  private $_db = NULL;
  private $_prefs = NULL;

  private $_subauths = array();
  private $_subauthUsed = NULL; # The name of the real subauth used

  public function __construct( $prefs, $db, $aDropbox )
  {
    if ( @$prefs['authMultiAdmins'] && (! $prefs['authAdmins']) ) {
      $prefs['authAdmins'] = $prefs['authMultiAdmins'];
    }

    # Get the ordered list of authenticators.
    # They should give me an array() of names of authenticators.
    # But if they give me a comma/space separated string of names, work too.
    # Else default to just "Local".
    $authnamelist = @$prefs['authMultiAuthenticators'];
    if (! is_array($authnamelist) ) {
      if ( is_string($authnamelist) ) {
        $authnamelist = preg_split('/[\s,]+/', $authnamelist, NULL,
                                   PREG_SPLIT_NO_EMPTY);
      } else {
        $authnamelist = array('Local');
      }
    }

    # Create the ordered list of authenticator objects
    foreach ($authnamelist as $subname) {
      $subclassname = 'NSS'.$subname.'Authenticator';
      $subfilename =  NSSDROPBOX_LIB_DIR.$subclassname.'.php';
      if (file_exists($subfilename)) {
        include_once($subfilename);
        $this->_subauths[$subname] = new $subclassname ( $prefs, $db, $aDropbox );
      }
    }

    # And set the admins and such like
    parent::__construct($prefs, $db, $aDropbox);
    
    // Set $this->_db in here to get the database handle.
    $this->_db = $db;
    $this->_prefs = $prefs;
  }

  public function description()
  {
    $desc = "NSSMultiAuthenticator {\n".
            "  database:  ".$this->_db."\n".
            parent::description()."\n".
            "}";
    return $desc;
  }

  /*!
    @function checkRecipient

    Performs any additional checks on the recipient email address to
    see if it is valid or not, given the result so far and the
    recipient email address.
    The result is ignored if the user has logged in, this is only for
    un-authenticated users.
    Can over-ride the result so far if it chooses.

    Over-ride this function in your authenticator class if necessary
    for your site.
  */
  public function checkRecipient( $sofar, $recipient )
  {
    # If they have authenticated, we know how they did it,
    # so only use that one to ensure we get the right email domain.
    $subname = $this->getAuthName();
    if ( isset($subname) ) {
      return $this->_subauths[$subname]->checkRecipient($uname, $response);
    }

    # Succeed if any of the sub-authenticators checks succeed.
    # It only has to be valid for 1 of them.
    foreach ($this->_subauths as $subname => $subobject) {
      if ( $subobject->checkRecipient($sofar, $recipient) ) {
        return TRUE;
      }
    }
    return $sofar;
  }


  // Is this a valid username, and if so what are all its properties.
  // Need to calculate uid, mail, cn, displayName and organization.
  public function validUsername ( $uname, &$response, &$errormsg )
  {
    # If they have authenticated, we know how they did it,
    # so only use that one to ensure we get the right email domain.
    $subname = $this->getAuthName();
    if ( isset($subname) ) {
      return $this->_subauths[$subname]->validUsername($uname, $response, $errormsg);
    }

    # They haven't successfully authenticated against anything,
    # so all we can do is try them in order...
    # If it is valid for any sub=authenticator, use that result
    foreach ($this->_subauths as $subname => $subobject) {
      if ( $subobject->validUsername($uname, $response, $errormsg) ) {
        return TRUE;
      }
    }

    # Not valid for any of them
    return FALSE;
  }


  // Try to authenticate this username and password.
  // Fill in the response if it's valid, with the uid, mail, cn, displayName
  // and organization. They will be columns in the database table.
  public function authenticate( &$uname, $password, &$response, $errormsg )
  {
    # If authentication succeeds for any sub=authenticator, use that result
    foreach ($this->_subauths as $subname => $subobject) {
      if ( $subobject->authenticate($uname, $password, $response, $errormsg) ) {
        $this->setAuthName($subname);
        return TRUE;
      }
    }
    # Authentication failed for all of them
    $this->setAuthName(NULL);
    return FALSE;
  }

  /*!
    @function setAuthName

    Sets the name of the actual authentication mechanism used,
    as it will have been read from the user's session cookie.
    It is needed as validUsername() needs to know the real authenticator
    used so the right default properties are filled in.
  */
  public function setAuthName( $subname )
  {
    $this->_subauthUsed = $subname;
  }

  /*!
    @function getAuthName

    Returns the name of the actual authentication mechanism used.
    This is currently only over-ridden by the "Multi" authenticator.
    It is needed as validUsername() needs to know the real authenticator
    used so the right default properties are filled in.
  */
  public function getAuthName()
  {
    return $this->_subauthUsed;
  }

}

?>
