<?PHP
//
// ZendTo
// Copyright (C) 2006 Jeffrey Frey, frey at udel dot edu
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
// Include the dropbox preferences -- we need this to have the
// dropbox filepaths setup for us, beyond simply needing our
// configuration!
//
require "../config/preferences.php";
require_once(NSSDROPBOX_LIB_DIR."Smartyconf.php");
require_once(NSSDROPBOX_LIB_DIR."NSSDropoff.php");

header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');              // Date in the past
header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');     // always modified
header('Cache-Control: no-cache, must-revalidate');            // HTTP/1.1
header('Pragma: no-cache');   
sendHTTPSecurity($NSSDROPBOX_PREFS);
header('X-Frame-Options: SAMEORIGIN');
header('Content-type: image/png');

# Read and sanitise the number of days (default is 7)
$p = isset($_GET['p'])?$_GET['p']:7;
$p = preg_replace('/[^0-9]/', '', $p);
# Read and sanitise the name of the graph (default is dropoff_count)
$m = isset($_GET['m'])?$_GET['m']:'dropoff_count';
$m = preg_replace('/[^a-z_]/', '', $m);

# Is $p valid? If not default to 7.
$valid_p = array(7, 30, 90, 365, 3650);
if ( ! in_array($p, $valid_p) )
  $p = 7;

if ( $theDropbox = new NSSDropbox($NSSDROPBOX_PREFS) ) {
  //
  // This page displays usage graphs for the system.
  //
  if ( $theDropbox->authorizedUser() && $theDropbox->authorizedUserData('grantStatsPriv') ) {
    
    $path = RRD_DATA_DIR.$m.$p.'.png';
    if ( is_readable($path) ) {
      readfile($path);
      exit(0);
    }
  }
}
readfile(NSSDROPBOX_BASE_DIR.'www/images/notfound.png');

?>
