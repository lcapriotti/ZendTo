These are various utilities for ZendTo, some of which are for
maintaining the local SQL-based authentication table of users and passwords.

*Also* - Run the "upgrade" command to automatically upgrade your
preferences.php and zendto.conf files, archiving the old and the
supplied ones.

You can get the usage details of each script by just running it with no
parameters on the command-line.

adduser         - Add a new user to the table
deleteuser      - Remove a user from the table
listusers       - List all the details of the users (except their passwords)
                  Run with "--help" to describe the output format
setpassword     - Change the password for a user
unlockuser      - Unlock a user who has had too many failed logins

addlanguage     - Add a new language and its directories within ZendTo.
makelanguages   - Rebuild and recompile the language translations.
                  This is needed after any change to any zendto.po files.

extractdropoff  - Given a claim ID, extract all the files from it to the
                  current directory. Will prompt for passphrase if needed.

To save you having to put the full location of the ZendTo preferences.php
file in every command, you can set the shell environment variable
ZENDTOPREFS instead, like this for example:
    export ZENDTOPREFS=/opt/zendto/config/preferences.php
Then the commands will automatically find the preferences.php file.
