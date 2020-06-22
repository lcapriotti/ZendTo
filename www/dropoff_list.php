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
  // This page handles the listing of dropoffs made by an
  // authenticated user.  If the user is NOT authenticated,
  // then an error is presented.
  //

  $theDropbox->SetupPage();

  if ( $theDropbox->authorizedUser() ) {
    //
    // Returns an array of all NSSDropoff instances belonging to
    // this user.
    //
    $allDropoffs = NSSDropoff::dropoffsFromCurrentUser($theDropbox);
    //
    // Start the web page and add some Javascript to automatically
    // fill-in and submit a pickup form when a dropoff on the page
    // is clicked.
    //
    $iMax = is_array($allDropoffs)?count($allDropoffs):0;
    $smarty->assign('countDropoffs', $iMax);
    $totalsize = 0;

    if ( $allDropoffs && $iMax>0 ) {
      $outputDropoffs = array();
      $i = 0;
      foreach($allDropoffs as $dropoff) {
        $outputDropoffs[$i] = array();
        $outputDropoffs[$i]['claimID'] = $dropoff->claimID();
        $outputDropoffs[$i]['senderName'] = $dropoff->senderName();
        $outputDropoffs[$i]['senderEmail'] = $dropoff->senderEmail();
        $outputDropoffs[$i]['senderOrg']  = htmlspecialchars($dropoff->senderOrganization());
        $outputDropoffs[$i]['subject'] = htmlentities($dropoff->subject(), ENT_QUOTES, 'UTF-8');

        $created = timeForDate($dropoff->created());
        $expires = $created + $dropoff->lifeseconds();
        $outputDropoffs[$i]['createdDate'] = $created;
        $outputDropoffs[$i]['expiresDate'] = $expires;
        $outputDropoffs[$i]['formattedBytes'] = $dropoff->formattedBytes();
        $outputDropoffs[$i]['isEncrypted'] = $dropoff->isEncrypted();
        $outputDropoffs[$i]['numPickups'] = $dropoff->numPickups();

        $b = $dropoff->bytes();
        $outputDropoffs[$i]['Bytes'] = $b;
        $totalsize += $b;

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

        $i++;
      }
      $smarty->assignByRef('dropoffs', $outputDropoffs);
      $smarty->assign('formattedTotalBytes', NSSFormattedMemSize($totalsize));
    }
  } else {
    NSSError(gettext("This feature is only available to users who have logged in."), gettext("Access Denied"));
  }

  $smarty->display('dropoff_list.tpl');
}

?>
