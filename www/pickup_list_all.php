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
// Include the dropbox preferences -- we need this to have the
// dropbox filepaths setup for us, beyond simply needing our
// configuration!
//
require "../config/preferences.php";
require_once(NSSDROPBOX_LIB_DIR."Smartyconf.php");
require_once(NSSDROPBOX_LIB_DIR."NSSDropoff.php");

if ( $theDropbox = new NSSDropbox($NSSDROPBOX_PREFS) ) {
  //
  // This page handles the listing of an authenticated user's 
  // dropoffs.  If the user is NOT authenticated, then an error
  // is presented.
  //

  $theDropbox->SetupPage();

  if ( ( $theDropbox->authorizedUser() && $theDropbox->authorizedUserData('grantAdminPriv') ) || $theDropbox->isAutomated()) {
    //
    // Returns an array of all NSSDropoff instances belonging to
    // this user.
    //
    $allDropoffs = NSSDropoff::allDropoffs($theDropbox, TRUE);
    //
    // Start the web page and add some Javascript to automatically
    // fill-in and submit a pickup form when a dropoff on the page
    // is clicked.
    //
    $iMax = count($allDropoffs);
    $totalsize = 0;
    $smarty->assign('countDropoffs', $iMax);
    
    if ( $allDropoffs && $iMax>0 ) {
      $outputDropoffs = array();
      $i = 0;
      foreach($allDropoffs as $dropoff) {
        $b = $dropoff->bytes();
        $totalsize += $b;
        $outputDropoffs[$i] = array();
        $outputDropoffs[$i]['claimID'] = $dropoff->claimID();
        if ($theDropbox->isAutomated())
          $outputDropoffs[$i]['claimPasscode'] = $dropoff->claimPasscode();
        $outputDropoffs[$i]['senderName'] = $dropoff->senderName();
        $outputDropoffs[$i]['senderOrg']  = htmlspecialchars($dropoff->senderOrganization());
        $outputDropoffs[$i]['senderEmail'] = $dropoff->senderEmail();

        // Display different dates and sizes if automated
        $created = timeForDate($dropoff->created());
        $expires = $created + $dropoff->lifeseconds();
        if ($theDropbox->isAutomated()) {
          $outputDropoffs[$i]['created'] = $created;
          $outputDropoffs[$i]['formattedCreated'] = strftime('%F %T %Z', $created);
          $outputDropoffs[$i]['expires'] = $expires;
          $outputDropoffs[$i]['formattedExpires'] = strftime('%F %T %Z', $expires);
          $outputDropoffs[$i]['bytes'] = $b;
        } else {
          $outputDropoffs[$i]['createdDate'] = $created;
          $outputDropoffs[$i]['expiresDate'] = $expires;
          $outputDropoffs[$i]['Bytes'] = $b;
        }

        $outputDropoffs[$i]['formattedBytes'] = $dropoff->formattedBytes();
        $outputDropoffs[$i]['isEncrypted'] = $dropoff->isEncrypted();
        $outputDropoffs[$i]['numPickups'] = $dropoff->numPickups();
        // And extra fields we only put into the JSON output
        if ($theDropbox->isAutomated()) {
          $outputDropoffs[$i]['note'] = $dropoff->note();
          // And now for all the recipients
          $recilist = array();
          foreach ($dropoff->recipients() as $r) {
            $recilist[] = array('name'  => $r[0], 'email' => $r[1]);
          }
          $outputDropoffs[$i]['recipients'] = $recilist;
          // Don't publish all the metadata for every file,
          // just the useful fields.
          $filelist = array();
          foreach ($dropoff->files() as $f) {
            if (is_array($f)) {
              $filelist[] = array('name'     => $f['basename'],
                                  'fileID'   => $f['rowid'],
                                  'size'     => $f['lengthInBytes'],
                                  'mimeType' => $f['mimeType'],
                                  'description' => $f['description'],
                                  'checksum' => $f['checksum']);
            }
          }
          $outputDropoffs[$i]['files'] = $filelist;
        } else {
          // HTML output wants to include recipients
          $recilist = array();
          foreach ($dropoff->recipients() as $r) {
            if (empty($r[0])) {
              $ea = '<' . $r[1] . '>';
            } else {
              $ea = $r[0] . ' <' . $r[1] . '>';
            }
            $recilist[] = htmlentities($ea, ENT_NOQUOTES, 'UTF-8');
          }
          $outputDropoffs[$i]['recipients'] = implode('<br/>', $recilist);
        }
        //$totalsize += $theDropbox->database()->DBBytesOfDropoff($dropoff->dropoffID());
        $i++;
      }
      $smarty->assignByRef('dropoffs', $outputDropoffs);
      $smarty->assign('formattedTotalBytes', NSSFormattedMemSize($totalsize));
    }
  } else {
    if ($theDropbox->isAutomated()) {
      header("X-ZendTo-Response: " .
             json_encode(array("status" => "error", "error" => "login error")));
      exit;
    } else {
      NSSError(gettext("This is available to administrators only."),
             gettext("Administrators only"));
    }
  }

  if ($theDropbox->isAutomated()) {
    // Automated, so just output JSON and headers
    // Can't output all the JSON as a header due to size limits in curl
    //header("X-ZendTo-Response: " .
    //       json_encode(array("status" => "OK", "dropoffs" => $outputDropoffs)));
    header("X-ZendTo-Response: " . json_encode(array("status" => "OK")));
    print(json_encode(array("status" => "OK", "dropoffs" => $outputDropoffs)));
    print("\n");
  } else {
    // Not automated, so show the web page
    $smarty->display('pickup_list_all.tpl');
  }
}

?>
