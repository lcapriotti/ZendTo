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
  @function timestampForTime
  
  Returns a textual timestamp corresponding to the number of
  seconds since the epoch that is passed to us.
*/
function timestampForTime(
  $timeSinceEpoch = 0
)
{
  // Removed %z from the end of the string for MySQL 5.6.
  // Thanks for Rajib Sarkar!
  //return strftime('%Y-%m-%d %H:%M:%S',$timeSinceEpoch);
  return strftime('%F %T',$timeSinceEpoch);
}



/*!
  @function timeForTimestamp
  
  Parse a timestamp and return the number of seconds since the
  epoch.
*/
function timeForTimestamp(
  $timestamp = '1970-01-01 00:00:00-00'
)
{
  return strtotime($timestamp);
}



/*!
  @function dateForTimestamp
  
  Parse a timestamp and return an array of values akin to what
  the PHP function getDate() returns -- which makes sense, since
  it calls getDate()!
*/
function dateForTimestamp(
  $timestamp = '1970-01-01 00:00:00-00'
)
{
  return getDate(timeForTimestamp($timestamp));
}



/*!
  @function timeForDate
  
  Given an array of values as returned by the getDate() function,
  returns the corresponding number of seconds since the epoch.
*/
function timeForDate(
  $aDate = NULL
)
{
  if ( $aDate ) {
    return mktime($aDate['hours'],$aDate['minutes'],$aDate['seconds'],$aDate['mon'],$aDate['mday'],$aDate['year']);
  }
  return 0;
}



/*!
  @function timestampForDate
  
  Given an array of values as returned by the getDate() function,
  returns a textual timestamp.
*/
function timestampForDate(
  $aDate = NULL
)
{
  return timestampForTime(timeForDate($aDate));
}

/*!
  @function secsToString

  Input = length of time in seconds.
  Output = string describing the approximate length of the input time.
*/
function secsToString(
  $secs
)
{
  $timeindays = floor($secs / 86400);
  $timeinhours = floor($secs / 3600);
  $timeinmins = floor($secs / 60);
  $timeinsecs = $secs;

  $days = floor($secs / 86400);
  $secs = $secs - 86400*$days;
  $hours = floor($secs / 3600);
  $secs = $secs - 3600*$hours;
  $mins = floor($secs / 60);
  $secs = $secs - 60*$mins;

  $r = '';

  if ($timeinhours >= 50) {
    // >=50 hours ==> tell it in days and possibly hours
    if ($hours < 2) // Treat <2 hours as roughly 0
      $r = sprintf(gettext('%d days'), $timeindays);
    else
      $r = sprintf(gettext('%1$d days and %2$d hours'), $timeindays, $hours);
  } else if ($timeinmins >= 121) {
    // hours in 2 and a bit..49 ==> tell it in hours and possibly minutes
    if ($mins < 2) // Treat <2 minutes as roughly 0
      $r = sprintf(gettext('%d hours'), $timeinhours);
    else
      $r = sprintf(gettext('%1$d hours and %2$d minutes'), $timeinhours, $mins);
  } else if ($timeinsecs >= 120) {
    // minutes in 2..120 ==> tell it in minutes and possibly seconds
    if ($secs < 10) // Treat <10 seconds as roughly 0
      $r = sprintf(gettext('%d minutes'), $timeinmins);
    else
      $r = sprintf(gettext('%1$d minutes and %2$d seconds'), $timeinmins, $secs);
  } else {
    // tell it in seconds
    if ($secs == 1)
      $r = sprintf(gettext('%d second'), $timeinsecs);
    else
      $r = sprintf(gettext('%d seconds'), $timeinsecs);
  }

  return $r;
}

?>
