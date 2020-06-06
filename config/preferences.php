<?PHP
//
// ZendTo
// Copyright (C) 2006 Jeffrey Frey, frey at udel dot edu
// Copyright (C) 2020 Julian Field, Jules at Zend dot To
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
// This file contains all the non-user interface parts of the ZendTo
// configuration.
// Before editing this file, read
//     https://zend.to/preferences.php
// as it will tell you what everything does, and lists all the settings
// you *must* change for it to work on your site.
// After that, look for the strings "soton", "ECS" and "152" to be sure
// you don't miss anything.
//

define('NSSDROPBOX_BASE_DIR','/opt/zendto/');
define('NSSDROPBOX_LIB_DIR', '/opt/zendto/lib/');
define('NSSDROPBOX_DATA_DIR','/var/zendto/');

// This defines the version number, please do not change
define('ZTVERSION','6.01-1');

// This is for gathering nightly stats, see docs about RRD and root's crontab
define('RRD_DATA_DIR',NSSDROPBOX_DATA_DIR.'rrd/');
define('RRD_DATA',RRD_DATA_DIR.'zendto.rrd');
define('RRDTOOL','/usr/bin/env rrdtool');

// This sets which Database engine you are using, either 'SQLite' or 'SQLite3'
// or 'MySQL'. If you are using SQLite on Ubuntu 12 (and higher) servers, be
// sure to specify 'SQLite3' and not 'SQLite'.
// It must look like one of these 3 examples:
// define('SqlBackend', 'SQLite');
// define('SqlBackend', 'SQLite3');
// define('SqlBackend', 'MySQL');
// SQLite3 is the easiest to use and works on everything except RHEL/CentOS 5.
// SQLite3 requires no database setup at all.
define('SqlBackend', 'SQLite3');

//
// Preferences are stored as a hashed array.  Inline comments
// indicate what everything is for.
//
$NSSDROPBOX_PREFS = array(

  // Next line needed for SQLite operation
  'SQLiteDatabase'       => NSSDROPBOX_DATA_DIR."zendto.sqlite",

  // Next 4 lines needed for MySQL operation
  'MySQLhost'            => 'localhost',
  'MySQLuser'            => 'zendto',
  'MySQLpassword'        => 'zendto',
  'MySQLdb'              => 'zendto',

  // This is where your drop-offs are stored.
  // It must be on the same filesystem as /var/zendto/incoming, and
  // on preferably on the same filesystem as /var/zendto.
  'dropboxDirectory'     => NSSDROPBOX_DATA_DIR."dropoffs",

  // This is where ZendTo writes its own log, visible to admins on
  // the web interface.
  // This used to be in NSSDROPBOX_DATA_DIR/zendto.log.
  // As of 5.12-1 I have moved it to /var/log/zendto/zendto.log,
  // and logrotate will now take care of its log rotation.
  // If your setting is still NSSDROPBOX_DATA_DIR/zendto.log, you
  // just need to change it here to /var/log/zendto/zendto.log and
  // the log rotation will happen automatically.
  'logFilePath'          => "/var/log/zendto/zendto.log",

  // If this file (or URL!) is readable, its contents will be shown
  // as a system-wide notice at the top of the ZendTo login, logout and
  // main menu pages.
  // The file may contain HTML, as well as just plain text.
  // If it has 2 or more lines, the first line is used as the announcement's
  // title, and subsequent lines are used as the content.
  // If it only has 1 line, the title defaults to "Please note" and the text
  // in the file is used as the announcement's content.
  //
  // Useful for notices to users about upcoming down-time for maintenance.
  // Leave the file totally empty, or else set this setting to '',
  // to not display anything.
  'systemAnnouncementFilePath' => NSSDROPBOX_BASE_DIR.'config/system-announcement.txt',

  // The root URL of the ZendTo web app in your organisation.
  // Make this "https" if you can.
  // It must end with a "/".
  'serverRoot'           => 'http://zendto.soton.ac.uk/',

  // Keep drop-offs for at most x days before auto-deleting them.
  'numberOfDaysToRetain' => 14,

  // If you want your users to be able to change the number of days
  // before expiry (limited in the range 0.1 to 'numberOfDaysToRetain',
  // then set this to be the number you want the default lifetime to be.
  // Then the chance to change the number will appear in the "new dropoff"
  // form.
  // Note: the number entered on the form by users does not have to be
  //       an integer, something like 1.5 is fine.
  // This number must be <= 'numberOfDaysToRetain'.
  //
  // You may like to increase the 'numberOfDaysToRetain', and set this
  // to your old value for 'numberOfDaysToRetain'. By default, users will
  // get the same life-time they did in previous versions, but they can
  // slightly increase the life-time of a drop-off if they need to.
  //
  // To disable this option and hide the box on the form, set it to 0.
  'defaultNumberOfDaysToRetain' => 14,

  // If someone sends a request for a Drop-off, how long does the
  // recipient have to reply?
  // After this time has passed, the request is disabled & deleted.
  // It is measured in seconds (3600 = 1 hour, 86400 = 1 day)
  'requestTTL'           => 604800, // 1 week

  // Requested drop-offs can be automatically encrypted with a passphrase
  // set by the person sending the request. The person who then uploads
  // the files cannot override this, nor do they know the passphrase at all.
  // This sets whether the "Encrypt all files" checkbox on the **request**
  // form (not the "new drop-off" form) is ticked by default.
  // Even if ticked by default, users can choose to un-tick it.
  'defaultEncryptRequests' => FALSE,

  // If someone sends a request for a drop-off, should the request
  // code be invalidated immediately after it has been used?
  // This should normally be TRUE.
  // If you set it to FALSE, the recipient of the request can keep
  // using repeatedly until the request finally expires after
  // 'requestTTL' seconds.
  // This means that someone could bombard you with drop-offs you
  // didn't want.
  // Think *VERY* carefully before considering setting this to FALSE.
  'deleteRequestsAfterUse' => TRUE,

  // When you are creating a request for a drop-off, do you want to be
  // able to edit the value set for the Organisation in the emails
  // sent to the person receiving the request?
  // Set this to FALSE for small or simple organisations.
  // Set this to TRUE for large or complex organisations where there may
  // be many sub-organisations sharing the same installation of ZendTo,
  // such as one deployed by a country's entire government.
  'requestSenderOrgIsEditable' => TRUE,

  // If no-one has picked up a dropoff x days before it's going to be
  // auto-deleted, start hassling the recipients daily about it.
  // Set this to 0 to disable it.
  'warnDaysBeforeDeletion' => 0,

  // The max size for an entire drop-off,
  'maxBytesForDropoff'   => 21474836480, // 20 GBytes = 20*1024*1024*1024
  // and the max size for each individual file in a drop-off
  'maxBytesForFile'      => 21474836480, // 20 GBytes = 20*1024*1024*1024

  // Some services (e.g. the free tier of service from Cloudflare) and
  // some anti-intrusion network appliances has a limit on the maximum
  // size of any http or https request sent through them.
  // Cloudflare's free limit is currently 100Mb per request.
  // Traditionally (if this setting is 0), ZendTo will send all the files
  // in a new drop-off to the server is 1 enormous request.
  // If this setting is not 0, it will be the maximum amount of user file
  // data that will be uploaded in 1 request. Make sure this is less
  // than the limit imposed by whatever service/appliance you are using,
  // as there is some overhead of other data and headers that must also be
  // sent as part of each request.
  //
  // To completely disable sending files in chunks, set this to 0.
  'uploadChunkSize' => 50000000, // 50 MB as an example

  // What language do you want ZendTo to run in?
  // These are locale codes (output from "locale -a" will show what are
  // available on your system currently) like 'en_GB' and 'pt_BR' and so on.
  // To add or change any text shown by ZendTo, please take a look at
  // https://zend.to/translators.php.
  // If you are just working in English then you can change the text by
  // adding a "translation" for the appropriate phrase to the en_US
  // translation (which is blank by default).
  // Also, don't forget that you can change simple stuff like the name
  // of your company in zendto.conf, to save you mucking with translations
  // at all.
  // The default language is 'en_US'.
  'language'             => 'en_US',

  // What is the list of valid languages your users can choose from,
  // and in what order does the list appear?
  // If you set this to array(), then the language picker does not
  // appear at all.
  'languageList' => array('cs_CZ Čeština', 'de_DE Deutsch', 'en_GB English (UK)', 'en_US English (US)', 'es_ES Espa&ntilde;ol', 'fr_FR Fran&ccedil;ais', 'gl_ES Galego', 'it_IT Italiano', 'hu_HU Magyar', 'nl_NL Nederlands', 'pl_PL Polski', 'pt_BR Portugu&ecirc;s (BR)', 'ru_RU &#1056;&#1091;&#1089;&#1089;&#1082;&#1080;&#1081;'),

  // If you are in the EU, you really should show a consent box to
  // ensure the user understands that the site is (1) storing a cookie
  // (albeit only a session cookie), and (2) will store their email address
  // and name in order to talk to the sender/recipients of the drop-off.
  // This is for EU GDPR purposes.
  'cookieGDPRConsent'    => FALSE,

  // Do you want to hide the fact that ZendTo is written in PHP?
  // Setting this to TRUE will remove all ".php" extensions from the
  // links within ZendTo and the emails it sends. Links which still
  // have ".php" in them will still work.
  // To set this to TRUE, you must add the following lines to the start
  // of each "VirtualHost" in your Apache config files.
  // These files have "zendto" in their name and are located in one of
  // these directories, depending on your Linux:
  //   /etc/httpd/conf.d
  //   /etc/apache2/sites-available
  //   /etc/apache2/vhosts.d
  // The lines to be added at/near the start of the VirtualHost are:
  //   <IfModule mod_rewrite.c>
  //     RewriteEngine on
  //     RewriteCond %{REQUEST_FILENAME} !-d
  //     RewriteCond %{REQUEST_FILENAME}\.php -f
  //     RewriteRule ^(.*)$ $1.php
  //   </IfModule>
  //   <IfModule mod_mime.c>
  //     AddType application/x-httpd-php .php
  //   </IfModule>
  // Note: Those lines are added by the ZendTo Installer.
  // After changing any Apache config file, you will need to restart
  // Apache with one of these commands, depending on your Linux:
  //   service httpd restart
  //   systemctl restart httpd
  //   systemctl restart apache2
  // If that still doesn't work, make sure with "httpd -M" that you have
  // mod_negotiation enabled (it is enabled by default).
  'hidePHP' => TRUE,

  // Usually, your internal (logged-in) users can change the name of their
  // "organization" that is used in new drop-offs. That is very useful for
  // places such as universities where your users might be doing work on
  // behalf of another orgnization while using university-supplied services.
  // Set this to TRUE to bypass this form completely, which makes the
  // drop-off process a lot quicker and simpler.
  // Note: This does *not* change the process for external users at all.
  'skipSenderInfo'       => TRUE,

  // Do you want recipients to be able to see the details of the other
  // recipients and who has picked up the drop-off and from where?
  'showRecipsOnPickup'   => FALSE,

  // The unique code for a request is either three 3- or 4-letter words,
  // or three 3-digit numbers (with leading zeros).
  // Use either 'words' or 'numbers'
  'wordlist'             => 'numbers',

  // When files are submitted in response to a request, you might want to
  // over-ride the recipient's email address to force the "files have been
  // dropped off for you" emails to go into your ticketing system's email
  // engine for automatic ticket assignment, rather than being sent to the
  // customer support rep who sent the request
  'requestTo'            => '', // Set to '' to disable this override

  // Allow external users (who can't login) to upload files?
  // Regardless of this setting, they always can if they've been given
  // a request code.
  // Setting this to FALSE stops external users sending files that
  // the recipient had not asked for.
  'allowExternalUploads' => TRUE,

  // Normally, internal users (who can login) can send files to anyone;
  // and external users can only send files to internal users.
  //
  // Leaving this set to TRUE does *not* mean that people outside your
  // organisation can use ZendTo to send files directly to other people
  // outside your organisation (that is obviously banned in ZendTo).
  //
  // If you set this to FALSE then *no-one* can send files to external
  // addresses, all allowed recipient addresses and/or domains have to
  // be listed in internaldomains.conf.
  'allowExternalRecipients' => TRUE,

  // Allow external users (who can't login) to pick up files using the
  // web interface?
  // Regardless of this setting, they always can if they've been given
  // the relevant link. It just removes the button from the main menu for
  // non-logged-in users.
  'allowExternalPickups' => TRUE,

  // You should check that senders who are not members of your organisation
  // (and so cannot login to ZendTo) are really the owner of the email
  // address they are sending from. So when they try to send you files,
  // you have some confidence they are who they say they are.
  // However, some sites are only expecting files from occasional people,
  // and don't want the senders to have to do the extra step.
  // Setting this to FALSE disables the email address confirmation step.
  'confirmExternalEmails' => TRUE,

  // Do you want to allow your own users to log in to ZendTo when they
  // are not connected to your local internal network.
  'allowExternalLogins'   => TRUE,

  // If a user fails to login with the correct password 'loginFailMax' times
  // in a row within 'loginFailTime' seconds, then the user is locked out
  // until the time period has passed.  86400 seconds = 1 day.
  // That means that if you fail to log in successfully 10 times in a row in
  // 1 day, your account is locked out for 1 day and you won't be able to
  // log in for that day.
  'loginFailMax'          => 10,
  'loginFailTime'         => 86400,

  // Do you want to restrict downloads to humans only? If this is false,
  // you may get a Denial of Service attack as anyone with the URL to
  // reach a file can download it, even malicious people. So someone can
  // command a botnet to download the same file 1,000,000 million times
  // simultaneously. Bad news for your server!
  // If this is true, unauthorised users trying to download a file have
  // to prove they are a real person and not a program.
  'humanDownloads'       => TRUE,

  // When a user sends a new drop-off, do you want them to be able to
  // use the "email recipients" tick box?
  'allowEmailRecipients' => TRUE,

  // When a user sends a new drop-off, do you want the "send email message
  // to recipients" tick box to be ticked by default?
  'defaultEmailRecipients' => TRUE,

  // When a user sends a new drop-off, do you want them to be able to
  // use the "email the Passcode as well as the Claim ID" tick box?
  'allowEmailPasscode'   => TRUE,

  // When a user sends a new drop-off, do you want the "email the
  // Passcode as well as the Claim ID" tick box to be ticked by default?
  'defaultEmailPasscode' => TRUE,

  // When a user sends a new drop-off, do you want the "send me an email
  // when a recipient picks up the drop-off" box to be ticked by default?
  'defaultConfirmDelivery' => TRUE,

  // These 5 affect the display of the "New Drop-off" form.
  // They *only* affect the display of the 5 tick boxes.
  // They do not affect the functionality at all.
  // They just give you a chance to stop your users changing settings,
  // or being confused by the large number (5!) of settings available.
  'showEncryptionCheckbox'      => TRUE,
  'showChecksumCheckbox'        => TRUE,
  'showConfirmDeliveryCheckbox' => TRUE,
  'showEmailRecipientsCheckbox' => TRUE,
  'showEmailPasscodeCheckbox'   => TRUE,

  // When recipients of drop-offs are picking up their files, this
  // "terms and conditions waiver" feature allows the user/administrator
  // to control if the recipient first has to tick a box agreeing to
  // whatever terms and conditions your organisation chooses.
  //
  // If 'showRecipientsWaiverCheckbox' is TRUE, then the "new drop-off"
  // form will contain an extra check box which the sender can choose to
  // tick or untick if they want. Its default state is set by the
  // 'defaultRecipientsWaiver' setting.
  //
  // If 'showRecipientsWaiverCheckbox' is FALSE, then the extra check box
  // will NOT appear in the "new drop-off" form, so individual users cannot
  // control it.
  // *HOWEVER*, if 'defaultRecipientsWaiver' is TRUE in this case,
  // then the use of the "waiver" is forced to be enabled for everyone.
  //
  'showRecipientsWaiverCheckbox' => TRUE,
  'defaultRecipientsWaiver'      => FALSE,

  // Maximum length of a submitted Request Subject, and Short Note
  'maxSubjectLength'     => 100,
  'maxNoteLength'        => 1000,

  // Only set this to TRUE if you are using random usernames, which may
  // happen if you are using hardware authentication tokens such as
  // Yubikeys. Don't change it in an existing installation, as it will
  // effectively wipe everyone's existing recipients' address book.
  // Normally (with this set to FALSE) the entries in the address book
  // db table are indexed by username. Setting this to TRUE forces ZendTo
  // to index them by email address instead. But in a lot of sites, users
  // can change their preferred email address. If this were set to TRUE and
  // someone changes their preferred address, their address book contents
  // will effectively disappear. So be warned! Leave it at FALSE if possible.
  'indexAddressbookByEmail' => FALSE,

  // ***********************
  // **** Customise me! ****
  // ***********************
  // This lists all the network numbers (class A, B and/or C) which your
  // site uses. Users coming from here will be considered "local" which
  // can be used to affect the user interface they get. If they visit ZendTo
  // from a "local" IP address, they are strongly encouraged to login
  // before trying to drop off files or use ZendTo.
  // These are the values for the University of Southampton.
  // Replace the contents of this array with a list of the network prefixes
  // you use for your site.
  'localIPSubnets'       => array('152.78.','10.','192.168.'),

  // ***********************
  // **** Customise me! ****
  // ***********************
  // The file specified here (full path starting with '/') contains
  // the list of the email domain names used by any of your
  // "internal" users. People from outside your organisation (who
  // cannot login) will only be able to send drop-offs to people
  // whose email addresses are in 1 or more of these domains.
  //
  // The file will contain a list of domain names, one per line.
  // Blank lines and comment lines starting wth '#' will be ignored.
  // If, for example, a line contains "my-company.com" then the list of
  // recipient email domains for un-authenticated users will contain
  // "my-company.com" and "*.my-company.com".
  //
  // For backward compatibility reasons, this can also be a regular
  // expression defining the set of valid domain names. In this case,
  // it must start *and* end with a '/'.
  // This example matches "soton.ac.uk" and "*.soton.ac.uk".
  // 'emailDomainRegexp' => '/^([a-zA-Z\.\-]+\.)?soton\.ac\.uk$/i',
  'emailDomainRegexp' => '/opt/zendto/config/internaldomains.conf',

  //
  // Data Integrity and Encryption
  //
  // The max size for a drop-off to have checksums calculated.
  // Disable this feature by setting the value to 0.
  'maxBytesForChecksum'  => 314572800, // 300 MBytes = 300*1024*1024

  // The max size for a drop-off to be encrypted
  // Disable this feature by setting the value to 0.
  'maxBytesForEncryption' => 314572800, // 300 MBytes = 300*1024*1024

  // Enforce encryption. This stops the user disabling it, and also
  // makes it ignore the maxBytesForEncryption size above.
  'enforceEncryption' => FALSE,

  // The minimum length of the passphrase a user can enter when using
  // encryption.
  // Don't worry about wanting capitals, digits and emoji in a passphrase.
  // A better defence is simply a much longer pass*phrase*, not a short
  // pass*word*.
  'minPassphraseLength' => 10,

  // How many times should you allow a wrong Passcode for a particular
  // pick-up attempt before auto-deleting the drop-off?
  // This helps protect against brute-force attacks on dropoff Passcodes.
  // Set it to 0 to disable this feature, but that will leave you open
  // to potential attack.
  'maxPickupFailures' => 50,

  // Do you want to disclose IP addresses in emails?
  // Yes if they are external ones, but possibly not if they are internal.
  'emailSenderIP' => TRUE,

  // When a user sends a new drop-off, do you want the sender to also
  // receive a copy of the email sent to the recipients? It will be a
  // Bcc copy of the message sent to the 1st recipient.
  'bccSender' => FALSE,

  // *If* bccSender is set to TRUE, do you want to send the Bcc to
  // external users outside your organisation, when they send a
  // new drop-off. Probably not.
  'bccExternalSender' => FALSE,

  // ZendTo can send you (the admins) a nightly summary of all the new
  // drop-offs that have been created in the previous 24 hours.
  // Note that this will exclude new drop-offs that have also been
  // deleted again. They have already totally gone from the database.
  //
  // Please don't monitor your employees unless you really have to, and
  // unless they know that this is happening.
  // Combining this with the 'external' or 'both' values for
  // 'nightlySummaryContains' will most likely breach GDPR and data
  // privacy legislation unless you have a VERY good reason for storing
  // the information.
  //
  // This feature is disabled by default by setting its value to array().
  // To enable this feature, add the admin email addresses (in quotes)
  // into this array:
  'nightlySummaryEmailAddresses' => array(),

  // You should only be monitoring the work of your "internal" users
  // (as defined by the contents of internaldomains.conf).
  // This setting can be set to 1 of 3 values:
  //    'internal'
  //    'external'
  // or 'both'
  // and controls what drop-off details are included in the nightly
  // summary above.
  // 'internal' will cause the nightly email to only contain details of
  // drop-offs created by your own users, i.e. those whose email address
  // matches a domain or address listed in internaldomains.conf.
  'nightlySummaryContains' => 'internal',

  // Do some of your users often need to send the same files to different
  // people over and over again?
  // Well, YOU WANT TO ENABLE THIS.
  //
  // It means they don't have to keep uploading the same files repeatedly,
  // nor do the individual copies for customers (recipients) take any
  // disk space.
  //
  // If you want to be able to optionally send files from a "library"
  // directory of frequently used files, set this to TRUE.
  // This will enable a user to either upload a file or pick one from
  // the library. The description used with the library file will be
  // whatever the last user set it to for that library file.
  // The extra bit of user interface will only appear for those users
  // who actively use the feature. All the rest of your users will never
  // know it is there.
  'usingLibrary' => FALSE,

  // This is the location of the library directory referred to above.
  // You might want to set up a WebDAV directory in your Apache web
  // server configuration, so that administrators can easily manage the
  // files in the library. Default points to /var/zendto/library.
  // The library should contain the files you want users to see in the
  // "new dropoff" form.
  // If you create subdirectories in here named the same as a username,
  // that user will see just the files in their subdirectory instead;
  // over-riding the files in the libraryDirectory itself.
  // If there are no files present, the library drop-down will not be
  // shown in the web user interface. Only the people that actually use it
  // will see it exists!
  // So by leaving libraryDirectory itself empty, but putting files in a
  // user's subdirectory, you can create a setup where only that user will
  // see any sign of there being a library.
  'libraryDirectory' => NSSDROPBOX_DATA_DIR."library",

  // *If* you do something like embed the whole of ZendTo in an iframe,
  // you may need to publicise a different URL for your installation
  // than the 'serverRoot' above which is the one that ZendTo itself
  // needs. So you will want a different one in the emails and
  // user-instructions that ZendTo sends.
  // Almost all sites can leave this set to '' as it will then use the
  // normal serverRoot defined above.
  // If defined and not '' this value will over-ride the serverRoot,
  // but only in email messagses and instructions to users.
  // If you use this feature, the URL must end with a '/'.
  'advertisedServerRoot' => '',

  // There are 2 CAPTCHAs available:
  // 1. The much improved Google reCAPTCHA v2, OR
  // # NO LONGER AVAILABLE 2. The AreYouAHuman CAPTCHA,
  // OR you can choose to disable the CAPTCHA altogether. If you do this
  //    it will be possible for bad people to attack your ZendTo website
  //    and send anyone in your organisation any malicious file they like.
  // The setting below must be one of 'google' or 'disabled'.
  'captcha' => 'google',

  //
  // Settings for the Google reCAPTCHA
  //
  // Get these 2 values from
  // https://www.google.com/recaptcha/admin
  'recaptchaPublicKey'   => '--Google-reCAPTCHA-Site-key-goes-here---',
  'recaptchaPrivateKey'  => '--Google-reCAPTCHA-Secret-key-goes-here-',
  // Are we using the new "Invisible" Google reCAPTCHA?
  // To use this service you must sign up for it at
  // https://www.google.com/recaptcha/intro/comingsoon/invisible.html
  // (Get your site and secret keys above first!)
  //
  // Note: July 2018 - The "Invisible" one appears to be causing some
  //                   problems right now. Leave this set to FALSE.
  'recaptchaInvisible'   => FALSE,

  // What language to use for the Invisible reCAPTCHA ?
  // This does NOT affect the VISIBLE one at all, that follows the
  // language chosen by the user.
  //
  // Note: these are **NOT** the same language codes that ZendTo uses!
  // Look it up here https://developers.google.com/recaptcha/docs/language
  // en = English (US)     en-GB = English (UK)
  // The default of ZendTo is US English. For British English, set this
  // to 'en-GB' and set the value of 'language' to 'en_GB'.
  'recaptchaLanguage'    => 'en-US',

  //
  // E-mail settings.
  //

  // the default email domain when just usernames are supplied
  'defaultEmailDomain' => 'soton.ac.uk',

  // There are 2 different ways you can send email messages.
  //
  // a) If you leave 'SMTPserver' set to '' then the old text-only
  //    method will be used (the PHP mail() function), and you will
  //    need to configure sendmail/Postfix yourself.
  //    NOTE: All the following SMTP settings will be ignored. This is to
  //          provide backward compatibility with existing installations.
  // OR
  // b) If you set 'SMTPserver' to the hostname or IP of your SMTP server,
  //    then PHPMailer will be used to send all mail to that server.
  //    PHPMailer has several advantages:
  //    1. Easier to setup (you've nearly done it)
  //    2. Can do STARTTLS for encryption
  //    3. Can authenticate to your SMTP server
  //    4. Can optionally send HTML versions of emails as well as the
  //       plain text ones.
  //       If any of these files in the templates directory exist:
  //          dropoff_email_html.tpl
  //          pickup_email_html.tpl
  //          request_email_html.tpl
  //          verify_email_html.tpl
  //       then both text and HTML versions of the relevant email are
  //       sent.
  //       These HTML email templates are optional. In each case, if it
  //       does not exist, just the plain text one will be used.
  //       Hint: If you want to include images in the HTML, embed them
  //             directly in the HTML code using a "data:image/..." URI.
  //             Then even recipients whose email app does not display
  //             remote images, will still display yours!
  //
  // Full hostname or IP address of your SMTP server
  // 'SMTPserver' => 'smtp.soton.ac.uk',
  'SMTPserver'   => '', // If blank, will use PHP mail(). See above.
  // SMTP port number. Usually 25, 465 or 587.
  'SMTPport'     => 25,
  // What encryption to use: must be '' (for no encryption) or 'tls'
  // (or 'ssl' which is deprecated)
  'SMTPsecure'   => 'tls',
  // Do you need to authenticate to your SMTP server?
  // If not, leave SMTPusername set to ''.
  // If you do, set the username and password.
  'SMTPusername' => '',
  'SMTPpassword' => '',
  // By default we will use the UTF-8 character set, so international
  // characters work better. The most common alternative is 'iso-8859-1'.
  // Note: This *must* be in upper case, or it has no effect!!
  'SMTPcharset'  => 'UTF-8',
  // Do you want debug output to appear on your ZendTo site?
  // BEWARE: Setting this to true will break most normal functionality
  // of your ZendTo server. So only use it when you are actually trying
  // to find a problem.
  // Once set to true, the only place you can test and debug SMTP
  // problems is by clicking the "Resend Drop-off" button from a drop-off
  // listed in your ZendTo Outbox.
  // Do *NOT* try to debug SMTP output by creating a new drop-off.
  'SMTPdebug'    => false,
  // Do you want the email messages to always come "From" the email
  // address set in zendto.conf? If so, set this to FALSE.
  // If you want, where it woudn't cause SPF/DKIM/DMARC problems, to
  // make the emails appear to come From the person who created the drop-off,
  // set this to TRUE.
  // If your SMTP server is running Exchange (or is Office 365), then you
  // should leave it set to FALSE.
  // Setting this to TRUE has helped a few people overcome spam detection
  // problems when delivering to GMail addresses.
  'SMTPsetFromToSender' => FALSE,

  // These are the usernames of the ZendTo administrators at your site.
  // Regardless of how you login, these must be all lower-case.
  'authAdmins'   => array('admin1','admin2','admin3'),

  // Are admin logins restricted to connections from the 'localIPSubnets'
  // networks?
  // Yes by default. This stops attempted remote logins to admin
  // accounts, so your admin accounts cannot be used maliciously by
  // outsiders.
  'adminLoginsMustBeLocal' => TRUE,
  
  // These usernames can only view the stats graphs, they cannot do other
  // admin functions. They can up and down load drop-offs, of course.
  // Regardless of how you login, these must be all lower-case.
  'authStats'    => array('view1','view2','view3'),

  // This is the login username of the "system account" you will use when
  // automating / scripting the process of sending a request for a drop-off.
  //
  // You should not use a normal user account as the password of this user
  // needs to be included in the call to the "autorequest" tool. This user
  // should not have any normal login rights on your client desktops.
  // It must be set if you want to script a "request for a drop-off"
  // using the "autorequest" tool.
  //
  // Do not attempt to use the normal web interface with this username.
  // It won't work. So don't be tempted to use your own username!
  //
  // If the user doesn't exist, the "autorequest" script won't work.
  //
  // You can list multiple usernames here. So different teams using the
  // scriptable requests could be done with different "system accounts" to
  // aid later diagnosis of problems as ZendTo will log the requests
  // against this username.
  'automationUsers' => array('apiuser'),

  //
  // Settings for SAML authentication.
  //
  // Ensure you have installed the "zendto-saml" package and followed
  // all the steps in /opt/zendto/README-saml before you try to
  // enable this.
  // 'authenticator' => 'SAML',
  //
  // SAML authentication returns a set of attributes for the user
  // that just logged in. The exact name of each of these attributes will
  // depend entirely on what you are authenticating against
  // (Microsoft Azure AD in my example).
  // These need to be translated into the attribute names that ZendTo uses.
  //
  // Once your SAML configuration is basically working, you can find these
  // attribute names by using the SimpleSAMLphp configuration page at
  //   https://your-zendto-site.example.com/saml/
  // Test the 'default-sp' authenticator successfully and it will show all
  // the attributes that were returned.
  // See the simplesamlphp.org documentation for more information.
  //
  // Each value can either be the full name of an attribute returned
  // by SAML, or a simple fixed string. If the attribute name is not
  // among those returned by the SAML autentication, the name is used
  // instead.
  // For example, you might not have any SAML attribute corresponding to
  // 'organization', so you can set that value for all users.
  //
  // Note that the attribute for 'uid' should contain a value of the form
  //   username@your-domain.example.com
  // But if your users can change their email address, ensure the 'uid'
  // attribute value does not change or else they will lose access to their
  // prevous drop-offs and their address book entries.
  //
  // These values happen to work with my own directory in Azure AD.
  // They are provided solely as an example.
  // They will *not* work with other directories or SAML identity providers.
  // You will need to set them correctly.
  // Here is an example map for a Shibboleth server:
  // 'samlAttributesMap' => array(
  //   'mail' => 'urn:oid:0.9.2342.19200300.100.1.3',
  //   'uid' => 'urn:oid:1.3.6.1.4.1.5923.1.1.1.6',
  //   'displayName' => 'urn:oid:2.16.840.1.113730.3.1.241',
  //   'organization' => 'First National Bank'),
  // And, until you change it, an example map for a Microsoft Azure directory:
  'samlAttributesMap' => array(
    'mail' => 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress',
    'uid' => 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress',
    'displayName' => 'http://schemas.microsoft.com/identity/claims/displayname',
    'organization' => 'First National Bank'),

  // If you are using the automation feature, you will hopefully have
  // realised that the automation scripts cannot log in via SAML.
  // So they have to use one of the other authenticators. As this is
  // *only* used for automation scripts (autodropoff etc) then the
  // 'Local' database-backed authenticator is probably a safe choice.
  'samlAutomationAuthenticator' => 'Local',

  //
  // Settings for the Local SQL-based authenticator.
  //
  // See the commands in /opt/zendto/bin and the ChangeLog to use this.
  'authenticator' => 'Local',

  //
  // Settings for the IMAP authenticator.
  //
  // If you work in a multi-domain site, where users authenticate by
  // entering their entire email address rather than just their username,
  // simply set 'authIMAPDomain' => '' and it will treat their full
  // email address as their username and then work as expected.
  //
  // To change the port add ":993" to the server name, to use SSL add "/ssl".
  // for other changes see flags for PHP function "imap_open" on php.net.
  // For example, recent versions of PHP try to use TLS where possible, so
  // if you are connecting to localhost then add "/novalidate-cert" on to the
  // end of your server name.
  // 'authenticator' => 'IMAP',
  'authIMAPServer' => 'mail.soton.ac.uk',
  'authIMAPDomain' => 'soton.ac.uk',
  'authIMAPOrganization' => 'University of Southampton',
  'authIMAPAdmins' => array(),

  //
  // Settings for the LDAP authenticator.
  //
  // authLDAPFullName = the list of LDAP properties used to build the user's
  //                    full name
  // If both 'authLDAPMemberKey' and 'authLDAPMemberRole' are set, then the
  // users must be members of this group/role. Here is an example:
  // 'authLDAPMemberKey'  => 'memberOf',
  // 'authLDAPMemberRole' => 'cn=ztUsers,OU=securityGroups,DC=example,DC=com',
  //
  // 'authenticator'         => 'LDAP',
  'authLDAPBaseDN'        => 'OU=users,DC=soton,DC=ac,DC=uk',
  'authLDAPServers'       => array('ldap1.soton.ac.uk','ldap2.soton.ac.uk'),
  'authLDAPAccountSuffix' => '@soton.ac.uk',
  'authLDAPUseSSL'        => false,
  'authLDAPStartTLS'      => false,
  'authLDAPBindDn'        => 'o=MyOrganization,uid=MyUser',
  'authLDAPBindPass'      => 'SecretPassword',
  'authLDAPOrganization'  => 'My Organization',
  'authLDAPUsernameAttr'  => 'uid',
  'authLDAPEmailAttr'     => 'mail',
  'authLDAPFullName'      => 'givenName sn',
  'authLDAPMemberKey'     => '',
  'authLDAPMemberRole'    => '',

  //
  // Settings for the 3-forest/3-domain AD authenticator.
  // Set 
  //     'authLDAPServers2' => array(),
  //     'authLDAPServers3' => array(),
  // if you only have to search 1 AD forest/domain.
  //
  // For help getting these settings right, and how to test them, see
  // https://zend.to/activedirectory.php
  //
  // TLS will be used in preference to SSL, if both are enabled.
  //
  // Update February 2020: Microsoft are revoking plain LDAP access, and
  // enforcing the use of LDAPS. For this to work, list each AD server as
  // an ldaps://ad.example.com URI, and set both UseSSL and UseTLS to false.
  // To test the SSL negotiation, try something like this:
  // openssl s_client -connect ad-server.example.com:636
  //
  // If you want to search for your user in multiple OUs in any of the
  // forests/domains, then make the authLDAPBaseDN1 (or 2 or 3) an
  // array of OUs, such as in this example:
  // 'authLDAPBaseDN1' => array('OU=Staff,DC=mycompany,DC=com', 'OU=Interns,DC=mycompany,DC=com'),
  //
  // 'authenticator'             => 'AD',
  'authLDAPServers1'          => array('ldaps://ad1.soton.ac.uk','ldaps://ad2.soton.ac.uk'),
  'authLDAPBaseDN1'           => 'OU=users,DC=ecs,DC=soton,DC=ac,DC=uk',
  'authLDAPAccountSuffix1'    => '@ecs.soton.ac.uk',
  'authLDAPUseSSL1'           => false,
  'authLDAPUseTLS1'           => false,
  'authLDAPBindUser1'         => 'SecretUsername1',
  'authLDAPBindPass1'         => 'SecretPassword1',
  'authLDAPOrganization1'     => 'ECS, University of Southampton',
  'authLDAPUsernameAttribute1' => 'sAMAccountName',
  // If you are not using this 2nd set of settings for a 2nd AD forest,
  // do not comment them out, but instead set them to be empty.
  // Set 
  //     'authLDAPServers2' => array(),
  //     'authLDAPServers3' => array(),
  // if you only have to search 1 AD forest/domain.
  'authLDAPServers2'          => array(),
  'authLDAPBaseDN2'           => '',
  'authLDAPAccountSuffix2'    => '',
  'authLDAPUseSSL2'           => false,
  'authLDAPUseTLS2'           => false,
  'authLDAPBindUser2'         => '',
  'authLDAPBindPass2'         => '',
  'authLDAPOrganization2'     => '',
  'authLDAPUsernameAttribute2' => '',
  // If you are not using this 3rd set of settings for a 3rd AD forest,
  // do not comment them out, but instead set them to be empty.
  // Set 
  //     'authLDAPServers3' => array(),
  // if you only have to search 1 or 2 AD forest/domains.
  'authLDAPServers3'          => array(),
  'authLDAPBaseDN3'           => '',
  'authLDAPAccountSuffix3'    => '',
  'authLDAPUseSSL3'           => false,
  'authLDAPUseTLS3'           => false,
  'authLDAPBindUser3'         => '',
  'authLDAPBindPass3'         => '',
  'authLDAPOrganization3'     => '',
  'authLDAPUsernameAttribute3' => '',
  // If both these 2 settings are set, then the users must be members of this
  // group/role. Please note this feature has not been rigorously tested yet.
  // 'authLDAPMemberKey'     => 'memberOf',
  // 'authLDAPMemberRole'    => 'cn=zendtoUsers,OU=securityGroups,DC=soton,DC=ac,DC=uk',

  //
  // Settings for the Multi authenticator.
  // This does not authenticate itself, but instead calls any combination
  // of the other authenticators in the order you list them.
  // The first authenticator that reports a username/password match will
  // be the one used for that session.
  // All the 'real' authenticators themselves are configured above as
  // normal.
  //
  // List the authenticator sequence as shown in this example:
  // 'authMultiAuthenticators' => array('IMAP', 'AD', 'Local'),
  //
  // 'authenticator' => 'Multi',
  'authMultiAuthenticators' => array('AD', 'Local'),

  // Regular expression defining a valid username for the Login page.
  // Usually no need to change this.
  'usernameRegexp'    => '/^([a-zA-Z0-9][a-zA-Z0-9\_\.\-\@\\\]*)$/i',

  // regular expression defining a valid email address for anyone.
  // Usually no need to change this.
  // Must look like /^(user)\@(domain)$/
  'validEmailRegexp' => '/^([a-zA-Z0-9][a-zA-Z0-9\.\_\-\+\&\']*)\@([a-zA-Z0-9][a-zA-Z0-9\_\-\.]+)$/i',

  'cookieName'        => 'zendto-session',
  // Get the value for the 'cookieSecret' from this command:
  // /opt/zendto/sbin/genCookieSecret.php
  'cookieSecret'      => '11111111111111111111111111111111',

  // This is the maximum lifetime of any ZendTo session cookie.
  //
  // If uploads that take a long time are not working, you need to
  // increase this value, along with the php.ini settings max_upload_time 
  // and max_run_time (which are also measured in seconds).
  //
  // Any upload taking longer than that will be killed.
  'cookieTTL'         => '43200', // 12 hours

  // Security HTTP headers
  //
  // Various HTTP headers are produced on every page ZendTo outputs, to
  // help protect you from various types of web-based attacks.
  // Note the value of each of these settings must be exactly right,
  // or they won't do anything. So don't guess the settings.
  // If you are not sure, leave them alone. Otherwise read the documentation
  // linked below.
  //
  // Also see you Apache configuration for ZendTo, as this adds the
  // "SameSite: strict" attribute to ZendTo's cookies, to help modern web
  // browsers protect you against CSRF attacks.
  //
  // https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/X-Frame-Options
  // This can be any of
  // 'deny' - denies any attempt to put ZendTo inside a <frame>, <iframe> or
  //          <object>.
  // 'sameorigin' - can only be put in a frame on the same origin as the page.
  // 'allow-from https://example.com/' - can only be put in a frame on the
  //                                     specified origin.
  // '' - do not send the X-Frame-Options header at all.
  'X-Frame-Options' => 'sameorigin',

  // How to get the IP address of the user's web browser.
  // tl;dr: If your ZendTo logs say that all use is coming from the
  //        same IP address, set this to TRUE.
  // If this is set to TRUE then the HTTP_X_FORWARDED_FOR and HTTP_CLIENT_IP
  // HTTP headeres are both used, in addition to the REMOTE_ADDR address
  // which will work on its own if you have a direct connection from the
  // outside world.
  // If you are not behind any sort of load balancer or proxy, then leave
  // this set to FALSE for security as those headers can be faked.
  // 
  'behindLoadBalancer' => FALSE,

  // The virus scanner uses ClamAV. You need to get clamav, clamav-db and
  // clamd installed (all available from RPMForge). If you cannot get the
  // permissions working, even after reading the documentation on
  // www.zend.to, then change the next line to '/usr/bin/clamscan --stdout'
  // and you will find it easier, though it will be a lot slower to scan.
  // If you need to disable virus scanning altogether, set this to 'DISABLED'.
  // Passing the '--fdpass' option to clamdscan speeds it up a lot!
  // The '--stdout' gets the ClamAV output into the ZendTo logfile.
  'clamdscan' => '/usr/bin/clamdscan --stdout --fdpass',
 
  // The command run to calculate the checksum of a file in a drop-off.
  // The (escaped+quoted) filename is appended to the end of this command.
  'checksum' => '/usr/bin/sha256sum --binary',

);

// ----                                        ---- //
// ---- DO NOT CHANGE ANYTHING BELOW THIS LINE ---- //
// ----                                        ---- //

// Do *not* change the next line. 
require_once(NSSDROPBOX_LIB_DIR.SqlBackend.'.php');

// IMPORTANT: Do not put extra spaces or lines after the PHP tag
//            just beneath this comment.
//            It will break dynamic/RRD images of your system stats.
?>
