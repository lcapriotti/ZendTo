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
  @class NSSADAuthenticator
  
  Uses one or more Active Directory LDAP servers to authenticate users.  The constructor
  wants the following attributes:
  
    ===              	   	=====
    Key                 	Value
    ===                   	=====
    "authLDAPServers"     	Array of hostnames to try binding to
    "authLDAPBaseDN"      	Base distinguished name for search/bind
    "authLDAPAdmins"      	Cheap way to grant admin privs to users; an
                          	array of sAMAccountName's
    "authLDAPAccountSuffix"	Suffix required to specify Domain for username e.g.
				username@localdomain 
    "authLDAPUseSSL"		Should be set to true to encrypt passwords
    "authLDAPUseTLS"		Should be set to true to use modern TLS
    "authLDAPBindUser"		Unprivileged user in Active Directory as we cannot 
				bind anonymously
    "authLDAPBindUser"		Unprivileged user's password in AD as we cannot 
				bind anonymously


Example for preferences.php:

 'authenticator'         => 'AD',
 'authLDAPBaseDN'        => 'DC=myorg,DC=ac,DC=uk',
 'authLDAPServers'       => array('mydc.domain.tld','myotherserver.domain.tld'),
 'authLDAPAdmins'        => array('sysadmin1','sysadmin2'),
 'authLDAPAccountSuffix' => '@domain.tld',
 'authLDAPUseSSL'        => false,
 'authLDAPUseTLS'        => true,
 'authLDAPBindUser'      => 'dropboxunpriv@domain.tld',
 'authLDAPBindPass'      => 'secretpw',

*/
class NSSADAuthenticator extends NSSAuthenticator {

  //  Instance data:
  protected $_ldapServers = NULL;
  protected $_ldapBase = NULL;
  protected $_ldapAccountSuffix = NULL;
  protected $_ldapUseSSL = NULL;
  protected $_ldapUseTLS = NULL;
  protected $_ldapBindUser = NULL;
  protected $_ldapBindPass  = NULL; 
  protected $_ldapBindOrg = NULL;
  // Ondrej protected $_ldapMemberKey = NULL;
  protected $_ldapMemberRole = NULL;
  protected $_ldapAttribute = NULL;
 
  /*!
    @function _construct
    
    Makes instance-copies of the LDAP server list and base DN.
    $db parameter not used in this authenticator.
  */
  public function __construct(
    $prefs, $db, $aDropbox
  )
  {
    if  ( @$prefs['authLDAPAdmins'] && (! $prefs['authAdmins']) ) {
      $prefs['authAdmins'] = $prefs['authLDAPAdmins'];
    }
    parent::__construct($prefs, $db, $aDropbox);
    
    $this->_ldapServers1                = $prefs['authLDAPServers1'];
    $this->_ldapBase1                   = $prefs['authLDAPBaseDN1'];
    $this->_ldapAccountSuffix1          = $prefs['authLDAPAccountSuffix1'];
    $this->_ldapUseSSL1                 = @$prefs['authLDAPUseSSL1'];
    $this->_ldapUseTLS1                 = @$prefs['authLDAPUseTLS1'];
    $this->_ldapBindUser1               = $prefs['authLDAPBindUser1'];
    $this->_ldapBindPass1               = $prefs['authLDAPBindPass1'];
    $this->_ldapOrg1                    = $prefs['authLDAPOrganization1'];
    $this->_ldapAttribute1              = @$prefs['authLDAPUsernameAttribute1'];

    $this->_ldapServers2                = @$prefs['authLDAPServers2'];
    $this->_ldapBase2                   = @$prefs['authLDAPBaseDN2'];
    $this->_ldapAccountSuffix2          = @$prefs['authLDAPAccountSuffix2'];
    $this->_ldapUseSSL2                 = @$prefs['authLDAPUseSSL2'];
    $this->_ldapUseTLS2                 = @$prefs['authLDAPUseTLS2'];
    $this->_ldapBindUser2               = @$prefs['authLDAPBindUser2'];
    $this->_ldapBindPass2               = @$prefs['authLDAPBindPass2'];
    $this->_ldapOrg2                    = @$prefs['authLDAPOrganization2'];
    $this->_ldapAttribute2              = @$prefs['authLDAPUsernameAttribute2'];

    $this->_ldapServers3                = @$prefs['authLDAPServers3'];
    $this->_ldapBase3                   = @$prefs['authLDAPBaseDN3'];
    $this->_ldapAccountSuffix3          = @$prefs['authLDAPAccountSuffix3'];
    $this->_ldapUseSSL3                 = @$prefs['authLDAPUseSSL3'];
    $this->_ldapUseTLS3                 = @$prefs['authLDAPUseTLS3'];
    $this->_ldapBindUser3               = @$prefs['authLDAPBindUser3'];
    $this->_ldapBindPass3               = @$prefs['authLDAPBindPass3'];
    $this->_ldapOrg3                    = @$prefs['authLDAPOrganization3'];
    $this->_ldapAttribute3              = @$prefs['authLDAPUsernameAttribute3'];

    //Ondrej $this->_ldapMemberKey = strtolower(@$prefs['authLDAPMemberKey']);
    $this->_ldapMemberRole = strtolower(@$prefs['authLDAPMemberRole']);
  }
  


  /*!
    @function description
    
    Summarizes the instance -- includes the server list and base DN.
  */
  public function description()
  {
    if (is_array($this->_ldapBase)) {
      $base = '';
      foreach ( $this->_ldapBase as $ldapBase ) {
        $base .= " $ldapBase";
      }
    } else {
      $base = $this->_ldapBase;
    }
    $desc = 'NSSADAuthenticator {
  base-dn: '.$base.'
  servers: (
';
    if (is_array($this->_ldapServers)) {
      foreach ( $this->_ldapServers as $ldapServer ) {
        $desc .= "              $ldapServer\n";
      }
    }
    $desc.'           )
';
    $desc .= parent::description().'
}';
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
  public function checkRecipient(
    $sofar,
    $recipient
  )
  {
    return $sofar;
  }



  /*!
    @function validUsername
    
    Does an anonymous bind to one of the LDAP servers and searches for the
    first record that matches "uid=$uname".
  */
  public function validUsername(
    $uname,
    &$response,
    &$errormsg
  )
  {
    global $SYSADMIN;
    $result = FALSE;
    $errormsg = '';

    // Yes I know this is horrific. It'll do though, it works.

    if (is_array($this->_ldapServers1)) {
      $this->_ldapServers = $this->_ldapServers1;
      $this->_ldapUseSSL  = $this->_ldapUseSSL1;
      $this->_ldapUseTLS  = $this->_ldapUseTLS1;
      $this->_ldapBindUser = $this->_ldapBindUser1;
      $this->_ldapBindPass = $this->_ldapBindPass1;
      $this->_ldapBase     = $this->_ldapBase1;
      $this->_ldapAccountSuffix = $this->_ldapAccountSuffix1;
      $this->_ldapOrg      = $this->_ldapOrg1;
      $this->_ldapAttribute = $this->_ldapAttribute1;
      $result = $this->Tryvalid($uname, $response, $errormsg);
      if ($result !== -70 && $result !== -69) {
        return TRUE;
      }
    }

    // Bail out quietly if there isn't a 2nd AD forest
    if (empty($this->_ldapServers2))
      return FALSE;
    if (is_array($this->_ldapServers2) && empty($this->_ldapServers2[0]))
      return FALSE;

    if (is_array($this->_ldapServers2)) {
      $this->_ldapServers = $this->_ldapServers2;
      $this->_ldapUseSSL  = $this->_ldapUseSSL2;
      $this->_ldapUseTLS  = $this->_ldapUseTLS2;
      $this->_ldapBindUser = $this->_ldapBindUser2;
      $this->_ldapBindPass = $this->_ldapBindPass2;
      $this->_ldapBase     = $this->_ldapBase2;
      $this->_ldapAccountSuffix = $this->_ldapAccountSuffix2;
      $this->_ldapOrg      = $this->_ldapOrg2;
      $this->_ldapAttribute = $this->_ldapAttribute2;
      $result = $this->Tryvalid($uname, $response, $errormsg);
      if ($result !== -70 && $result !== -69) {
        return TRUE;
      }
    }

    // Bail out quietly if there isn't a 3rd AD forest
    if (empty($this->_ldapServers3))
      return FALSE;
    if (is_array($this->_ldapServers3) && empty($this->_ldapServers3[0]))
      return FALSE;

    if (is_array($this->_ldapServers3)) {
      $this->_ldapServers = $this->_ldapServers3;
      $this->_ldapUseSSL  = $this->_ldapUseSSL3;
      $this->_ldapUseTLS  = $this->_ldapUseTLS3;
      $this->_ldapBindUser = $this->_ldapBindUser3;
      $this->_ldapBindPass = $this->_ldapBindPass3;
      $this->_ldapBase     = $this->_ldapBase3;
      $this->_ldapAccountSuffix = $this->_ldapAccountSuffix3;
      $this->_ldapOrg      = $this->_ldapOrg3;
      $this->_ldapAttribute = $this->_ldapAttribute3;
      $result = $this->Tryvalid($uname, $response, $errormsg);
      if ($result === -70) {
        NSSError(gettext('Check User: Unable to connect to any of the authentication servers; could not authenticate user.').' '.$SYSADMIN, gettext('Active Directory Error'));
        if (empty($errormsg)) $errormsg = 'Error:';
        $errormsg .= ' Active Directory: Unable to connect to any of the authentication servers; could not authenticate user.';
        return FALSE;
      } else if ($result === -69) {
        // NSSError('Check User: Incorrect username or password.','LDAP Error');
        return FALSE;
      }
    }
    return TRUE;
  }

  public function Tryvalid(
    $uname,
    &$response,
    &$error
  )
  {
    global $smarty;

    $result = FALSE;
    
    // Get the LDAP username attribute we want to use
    $usernameAttr = 'sAMAccountName'; // Default value
    if ($this->_ldapAttribute != "") {
      $usernameAttr = $this->_ldapAttribute;
    }

    //  Bind to one of our LDAP servers:
    foreach ( $this->_ldapServers as $ldapServer ) {
      $ldapPort = 389; // Default LDAP port
      $lwords = explode(':', $ldapServer);
      if ($this->_ldapUseSSL && !$this->_ldapUseTLS) {
        // If using a URI, then ldap_connect ignores 2nd parameter
        if (substr($ldapServer, 0, 8) !== 'ldaps://')
          $ldapServer = "ldaps://".$ldapServer;
      } else if ($lwords[1]>0) {
        // Not using URI so split off any port number supplied
        $ldapServer = $lwords[0];
        $ldapPort = $lwords[1];
      }

      if ( $ldapConn = ldap_connect($ldapServer, $ldapPort) ) {
        // Unfortunately ldap_connect() doesn't actually send any packets,
        // so it will pretty much always succeed even if the server's not
        // there.
        // So if the ldap_bind() fails, I have to fail quietly. :-(
        // Set the protocol to 3 only:
        ldap_set_option($ldapConn,LDAP_OPT_PROTOCOL_VERSION,3);
        ldap_set_option($ldapConn,LDAP_OPT_REFERRALS,0); 
        if ($this->_ldapUseTLS) {
          if (!ldap_start_tls($ldapConn)) {
            $ldaperror = ldap_error($ldapConn);
            NSSError(sprintf(gettext('Connected to %1$s but could not start_tls, it said %2$s'), $ldapServer, $ldaperror), "Active Directory Error");
            if (empty($error)) $error = 'Error:';
            $error .= sprintf(' Connected to %s but could not start_tls, it said %s', ldapServer, $ldaperror);
            $ldapBind = false;
          } else {
            // start_tls worked, so continue with the bind attempt
            if ( $ldapBind = @ldap_bind($ldapConn,$this->_ldapBindUser,$this->_ldapBindPass) ) {
              break; // Get out of the for loop, we have a Binding. Yay!
            } else {
              // Failed to bind. If the error was 'Can't contact LDAP server'
              // then fail quietly and try the next server, else complain.
              $ldaperror = ldap_error($ldapConn);
              if (! preg_match('/can[not\']* *contact *ldap *server/i',
                               $ldaperror)) {
                NSSError(sprintf(gettext('Connected to %1$s but could not bind with TLS, it said %2$s'), $ldapServer, $ldaperror), "Active Directory Error");
                if (empty($error)) $error = 'Error:';
                $error .= sprintf(' Connected to %s but could not bind with TLS, it said %s', $ldapServer, $ldaperror);
              }
            }
          }
        } else {
          // Not using TLS
          // Connection made, now attempt to bind:
          if ( $ldapBind = @ldap_bind($ldapConn,$this->_ldapBindUser,$this->_ldapBindPass) ) {
            break; // Get out of the for loop, we have a Binding. Yay!
          } else {
            // Failed to bind. If the error was 'Can't contact LDAP server'
            // then fail quietly and try the next server, else complain.
            $ldaperror = ldap_error($ldapConn);
            if (! preg_match('/can[not\']* *contact *ldap *server/i',
                             $ldaperror)) {
              NSSError(sprintf(gettext('Connected to %1$s but could not bind without TLS, it said %2$s'), $ldapServer, $ldaperror), "Active Directory Error");
              if (empty($error)) $error = 'Error:';
              $error .= sprintf(' Connected to %s but could not bind without TLS, it said %s', $ldapServer, $ldaperror);
            }
          }
        }
      }
    }

    // If $ldapBind is not false, we have the first successful bind,
    // so use it.
    if ( $ldapBind ) {
      if (!is_array($this->_ldapBase)) {
        $this->_ldapBase = array($this->_ldapBase);
      }
      foreach ( $this->_ldapBase as $ldapBase ) {
        $ldapSearch = ldap_search($ldapConn,$ldapBase,"$usernameAttr=$uname");
        if ( $ldapSearch && ($ldapEntry = ldap_first_entry($ldapConn,$ldapSearch)) && ($ldapDN = ldap_get_dn($ldapConn,$ldapEntry)) ) {
          //  We got a result and a DN for the user in question, so
          //  that means s/he exists!
          $result = TRUE;
          if ( $responseArray = ldap_get_attributes($ldapConn,ldap_first_entry($ldapConn,$ldapSearch)) ) {
            $response = array();
            foreach ( $responseArray as $key => $value ) {
              if ( is_array($value) && array_key_exists('count', $value) && $value['count'] >= 1 ) {
                $response[$key] = $value[0];
                // For Klas Elmby and his AD "proxyAddresses" attribute
                // containing alternate email addresses for this user
                //if ($key=="proxyAddresses") {
                //  $num = 0;
                //  $response['proxyAdd'] = array();
                //  for ($n=0; $n<$value['count']; $n++) {
                //    if (strncasecmp($value[$n],"smtp:",5)==0) {
                //      $response['proxyAdd'][$num] = substr($value[$n],5);
                //      $num++;
                //    }
                //  }
                //  $response['proxyCount'] = $num;
                //  // BUG BUG BUG -- Klas? $response[$key] = $proxStr;
                //}
              } else {
                $response[$key] = $value;
              }
            }
            // Override the ldapOrg with AD's company name if present
            if (array_key_exists('company', $response) && $response['company'] !== '') {
              $response['organization'] = $response['company'];
            } else {
              $response['organization'] = $this->_ldapOrg;
            }
            // Do the authorisation check. User must be a member of a group.
            $authorisationPassed = TRUE;
            if ($this->_ldapMemberRole != '') {
              // lookup groups user is member of - have to do it this way
              // in order to cope with nested groups correctly
              $authorisationPassed = FALSE;
              // This will make it search nested groups properly.
              // The magic number is the LDAP_MATCHING_RULE_IN_CHAIN oid.
              // https://msdn.microsoft.com/en-us/library/aa746475(v=vs.85).aspx
              $filter = "(&(objectClass=group)(member:1.2.840.113556.1.4.1941:=$ldapDN))";
              $search = ldap_search($ldapConn, $ldapBase, $filter);
              if ($search && ($groups = ldap_get_entries($ldapConn, $search))) {
                foreach ($groups as $group) {
                  $groupDN = $group['dn'];
                  if (strtolower($groupDN) === $this->_ldapMemberRole) {
                    $authorisationPassed = TRUE;
                  }
                }
              }
            }
            if (!$authorisationPassed) {
              NSSError(gettext("Sorry, you are not authorized to use this service."), gettext('Authorization Failed'));
              if (empty($error)) $error = 'Warning:';
              $error .= ' User not authorized to use this service in AD.';
              // We found the user okay, but he wasn't a group member
              $result = -69;
              if ($ldapConn) {
                ldap_close($ldapConn);
              }
              return $result;
            }
            //  Chain to the super class for any further properties to be added
            //  to the $response array:
            parent::validUsername($uname, $response, $error);
            if ($ldapConn) {
              ldap_close($ldapConn);
            }
            return $result;
          }
        }
      }
      // If we get to here, we managed to contact the server, but couldn't
      // find them in any of the BaseDNs we were told to search.
      if ($ldapConn) {
        ldap_close($ldapConn);
      }
      return -69;
    } else {
      // NSSError('Invalid username: Unable to connect to any of the authentication servers; could not authenticate user.','LDAP Error');
      if ( $ldapConn ) {
        ldap_close($ldapConn);
      }
      return -70;
    }
    if ( $ldapConn ) {
      ldap_close($ldapConn);
    }
    return $result;
  }
  


  /*!
    @function authenticate
    
    Does an anonymous bind to one of the LDAP servers and searches for the
    first record that matches "uid=$uname".  Once that record is found, its
    DN is extracted and we try to re-bind non-anonymously, with the provided
    password.  If it works, voila, the user is authenticated and we return
    all the info from his/her directory entry.
  */
  public function authenticate(
    &$uname,
    $password,
    &$response,
    &$errormsg
  )
  {
    global $SYSADMIN;
    $result = 'NOTTRIED';
    $errormsg = '';
    
    // Only use this forest if it really has some servers set.
    if (!empty($this->_ldapServers1) &&
        is_array($this->_ldapServers1) &&
        !empty($this->_ldapServers1[0])) {
      $this->_ldapServers = $this->_ldapServers1;
      $this->_ldapUseSSL  = $this->_ldapUseSSL1;
      $this->_ldapUseTLS  = $this->_ldapUseTLS1;
      $this->_ldapBindUser = $this->_ldapBindUser1;
      $this->_ldapBindPass = $this->_ldapBindPass1;
      $this->_ldapBase     = $this->_ldapBase1;
      $this->_ldapAccountSuffix = $this->_ldapAccountSuffix1;
      $this->_ldapOrg      = $this->_ldapOrg1;
      $this->_ldapAttribute = $this->_ldapAttribute1;
      $result = $this->Tryauthenticate($uname, $password, $response, $errormsg);
      if ($result !== -70 && $result !== -69) {
        return TRUE;
      }
    }

    // Only use this forest if it really has some servers set.
    if (!empty($this->_ldapServers2) &&
        is_array($this->_ldapServers2) &&
        !empty($this->_ldapServers2[0])) {
      $this->_ldapServers = $this->_ldapServers2;
      $this->_ldapUseSSL  = $this->_ldapUseSSL2;
      $this->_ldapUseTLS  = $this->_ldapUseTLS2;
      $this->_ldapBindUser = $this->_ldapBindUser2;
      $this->_ldapBindPass = $this->_ldapBindPass2;
      $this->_ldapBase     = $this->_ldapBase2;
      $this->_ldapAccountSuffix = $this->_ldapAccountSuffix2;
      $this->_ldapOrg      = $this->_ldapOrg2;
      $this->_ldapAttribute = $this->_ldapAttribute2;
      $result = $this->Tryauthenticate($uname, $password, $response, $errormsg);
      if ($result !== -70 && $result !== -69) {
        return TRUE;
      }
    }

    // Only use this forest if it really has some servers set.
    if (!empty($this->_ldapServers3) &&
        is_array($this->_ldapServers3) &&
        !empty($this->_ldapServers3[0])) {
      $this->_ldapServers = $this->_ldapServers3;
      $this->_ldapUseSSL  = $this->_ldapUseSSL3;
      $this->_ldapUseTLS  = $this->_ldapUseTLS3;
      $this->_ldapBindUser = $this->_ldapBindUser3;
      $this->_ldapBindPass = $this->_ldapBindPass3;
      $this->_ldapBase     = $this->_ldapBase3;
      $this->_ldapAccountSuffix = $this->_ldapAccountSuffix3;
      $this->_ldapOrg      = $this->_ldapOrg3;
      $this->_ldapAttribute = $this->_ldapAttribute3;
      $result = $this->Tryauthenticate($uname, $password, $response, $errormsg);
      if ($result !== -70 && $result !== -69) {
        return TRUE;
      }
    }

    // If we haven't got any result at all, we never tried anything!
    if ($result === 'NOTTRIED') {
      NSSError(gettext('Check User: No AD servers configured.').' '.$SYSADMIN, gettext('Active Directory Error'));
      if (empty($errormsg)) $errormsg = 'Error:';
      $errormsg .= ' Active Directory: No AD servers configured.';
    }

    // BTW -70 ==> couldn't contact LDAP server, -69 ==> other failure
    return FALSE;
  }

  public function Tryauthenticate(
    $uname,
    $password,
    &$response,
    &$error
  )
  {
    global $smarty;
    global $SYSADMIN;

    // Get the LDAP username attribute we want to use
    $usernameAttr = 'sAMAccountName'; // Default value
    if ($this->_ldapAttribute != "") {
      $usernameAttr = $this->_ldapAttribute;
    }

    // JKF 2020-05-30.
    // Users have reported login problems when they login with an
    // email address instead of just a username.
    // This code attempted to cope with users who entered an email
    // address or DOMAIN\username when they shouldn't have.
    // Let's just allow that to fail instead.
    //
    // This is the only critical difference between the authentication
    // code and the username validity code. So let's get rid of it.
    //
    // // Only sanitise username if they are still using sAMAccountName
    // // attribute to fetch username
    // if (strcasecmp($usernameAttr, 'sAMAccountName') == 0) {
    //   // The username should not be their email address.
    //   // So remove everything after any @ sign.
    //   $uname = preg_replace('/@.*$/', '', $uname);
    //   $uname = preg_replace('/^.*\\\/', '', $uname);
    // }

    //  Bind to one of our LDAP servers:
    foreach ( $this->_ldapServers as $ldapServer ) {
      $ldapPort = 389; // Default LDAP port
      $lwords = explode(':', $ldapServer);
      if ($this->_ldapUseSSL && !$this->_ldapUseTLS) {
        // If using a URI, then ldap_connect ignores 2nd parameter
        if (substr($ldapServer, 0, 8) !== 'ldaps://')
          $ldapServer = "ldaps://".$ldapServer;
      } else if (@$lwords[1]>0) {
        // Not using URI so split off any port number supplied
        $ldapServer = $lwords[0];
        $ldapPort = $lwords[1];
      }

      if ( $ldapConn = ldap_connect($ldapServer, $ldapPort) ) {
        // Unfortunately ldap_connect() doesn't actually send any packets,
        // so it will pretty much always succeed even if the server's not
        // there.
        // So if the ldap_bind() fails, I have to fail quietly. :-(
        // Set the protocol to 3 only:
        ldap_set_option($ldapConn,LDAP_OPT_PROTOCOL_VERSION,3);
        ldap_set_option($ldapConn,LDAP_OPT_REFERRALS,0); 
        if ($this->_ldapUseTLS) {
          if (!ldap_start_tls($ldapConn)) {
            $ldaperror = ldap_error($ldapConn);
            //if (! preg_match('/can[not\']* *contact *ldap *server/i',
            //                 $ldaperror)) {
            NSSError(sprintf(gettext('Connected to %1$s but could not start_tls, it said %2$s'), $ldapServer, $ldaperror), "Active Directory Error");
            if (empty($error)) $error = 'Error:';
            $error .= sprintf(' Connected to %s but could not start_tls, it said %s', $ldapServer, $ldaperror);
            $ldapBind = false;
            //}
          } else {
            // start_tls worked, so continue with the bind attempt
            if ( $ldapBind = @ldap_bind($ldapConn,$this->_ldapBindUser,$this->_ldapBindPass) ) {
              break; // Get out of the for loop, we have a Binding. Yay!
            } else {
              // Failed to bind. If the error was 'Can't contact LDAP server'
              // then fail quietly and try the next server, else complain.
              $ldaperror = ldap_error($ldapConn);
              if (! preg_match('/can[not\']* *contact *ldap *server/i',
                               $ldaperror)) {
                NSSError(sprintf(gettext('Connected to %1$s but could not bind with TLS, it said %2$s'), $ldapServer, $ldaperror), "Active Directory Error");
              if (empty($error)) $error = 'Error:';
              $error .= sprintf(' Connected to %s but could not bind with TLS, it said %s', $ldapServer, $ldaperror);
              }
            }
          }
        } else {
          // Not using TLS
          // Connection made, now attempt to bind:
          if ( $ldapBind = @ldap_bind($ldapConn,$this->_ldapBindUser,$this->_ldapBindPass) ) {
            break; // Get out of the for loop, we have a Binding. Yay!
          } else {
            // Failed to bind. If the error was 'Can't contact LDAP server'
            // then fail quietly and try the next server, else complain.
            $ldaperror = ldap_error($ldapConn);
            if (! preg_match('/can[not\']* *contact *ldap *server/i',
                             $ldaperror)) {
              NSSError(sprintf(gettext('Connected to %1$s but could not bind without TLS, it said %2$s'), $ldapServer, $ldaperror), "Active Directory Error");
              if (empty($error)) $error = 'Error: ';
              $error .= sprintf(' Connected to %s but could not bind without TLS, it said %s', $ldapServer, $ldaperror);
            }
          }
        }
      }
    }

    // If $ldapBind is not false, we have the first successful bind,
    // so use it.
    if ( $ldapBind ) {
      if (!is_array($this->_ldapBase)) {
        $this->_ldapBase = array($this->_ldapBase);
      }
      foreach ( $this->_ldapBase as $ldapBase ) {
        $ldapSearch = ldap_search($ldapConn,$ldapBase,"$usernameAttr=$uname");
        if ( $ldapSearch && ($ldapEntry = ldap_first_entry($ldapConn,$ldapSearch)) && ($ldapDN = ldap_get_dn($ldapConn,$ldapEntry)) ) {
          //  We got a result and a DN for the user in question, so
          //  try binding as the user now:
          if ( $result = @ldap_bind($ldapConn,$ldapDN,$password) ) {
            if ( $responseArray = ldap_get_attributes($ldapConn,ldap_first_entry($ldapConn,$ldapSearch)) ) {
              $response = array();
              foreach ( $responseArray as $key => $value ) {
                if ( @$value['count'] >= 1 ) {
                  $response[$key] = $value[0];
                } else {
                  $response[$key] = $value;
                }
              }
              // Override the ldapOrg with AD's company name if present
              if (array_key_exists('company', $response) && $response['company'] !== '') {
                $response['organization'] = $response['company'];
              } else {
                $response['organization'] = $this->_ldapOrg;
              }
              // Do the authorisation check. User must be a member of a group.
              $authorisationPassed = TRUE;
              if ($this->_ldapMemberRole != '') {
                // lookup groups user is member of - have to do it this way
                // in order to cope with nested groups correctly
                $authorisationPassed = FALSE;
                // This will make it search nested groups properly.
                // The magic number is the LDAP_MATCHING_RULE_IN_CHAIN oid.
                // https://msdn.microsoft.com/en-us/library/aa746475(v=vs.85).aspx
                $filter = "(&(objectClass=group)(member:1.2.840.113556.1.4.1941:=$ldapDN))";
                $search = ldap_search($ldapConn, $ldapBase, $filter);
                if ($search && ($groups = ldap_get_entries($ldapConn, $search))) {
                  foreach ($groups as $group) {
                    $groupDN = $group['dn'];
                    if (strtolower($groupDN) === $this->_ldapMemberRole) {
                      $authorisationPassed = TRUE;
                    }
                  }
                }
              }
              if (!$authorisationPassed) {
                NSSError(gettext("Sorry, you are not authorized to use this service."), gettext('Authorization Failed'));
                $result = -69;
                if ( $ldapConn ) {
                  ldap_close($ldapConn);
                }
                return $result;
              }

              // Chain to the super class for any further properties to be added
              // to the $response array:
              parent::authenticate($uname, $password, $response, $error);
              if ( $ldapConn ) {
                ldap_close($ldapConn);
              }
              return $result;
            }
          } else {
            // We found a username matching but password didn't
            if ( $ldapConn ) {
              ldap_close($ldapConn);
            }
            return -69;
          }
        }
      }
      // If we get to here, we managed to contact the server, but couldn't
      // find them in any of the BaseDNs we were told to search.
      if ($ldapConn) {
        ldap_close($ldapConn);
      }
      return -69;
    } else {
      NSSError(gettext('Check User: Unable to connect to any of the authentication servers; could not authenticate user.').' '.$SYSADMIN, $this->_ldapOrg.' '.gettext('Active Directory Error'));
      if (empty($error)) $error = 'Error:';
      $error .= ' Active Directory: Unable to connect to any of the authentication servers; could not authenticate user in ' . $this->_ldapOrg . '.';
      if ( $ldapConn ) {
        ldap_close($ldapConn);
      }
      return -70;
    }
    if ( $ldapConn ) {
      ldap_close($ldapConn);
    }
    return $result;
  }

}

?>
