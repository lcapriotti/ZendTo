%define version 6.03
%define release 1
%define name    zendto

%define is_fedora %(test -e /etc/fedora-release && echo 1 || echo 0)

Name:        %{name}
Version:     %{version}
Release:     %{release}
Summary:     Web-based File Transfer and Storage System
Group:       Networking/WWW
License:     GPL
Vendor:      Julian Field www.zend.to
Packager:    Julian Field <Support@Zend.To>
URL:         https://zend.to/
AutoReq:     no
Requires:    httpd jq
Source:      ZendTo-%{version}-%{release}.tgz
BuildRoot:   %{_tmppath}/%{name}-root
BuildArchitectures: noarch

%description
ZendTo is a web-based package that allows for the easy transfer of large
files both into and out of your organisation, without users outside
your organisation needing any usernames or passwords to be able to send
files to you. It also of course allows your own internal users to send
files to anyone with an email address. All submissions are scanned for
viruses but are otherwise unrestricted.

Uploaded files can be securely encrypted for privacy.

It cannot be used by external users to distribute files to other external
users, and therefore cannot be abused to distribute illegal software or
other files outside of your organisation. It also cannot be abused by
outside spammers to automatically "spam" everyone in your organisation
with file notifications.

It is specifically designed to look after itself once installed and
maintain itself automatically. Customising the user interface is very
simply done by editing templates. Multiple languages and text customisation
is done via gettext.

It is very easy to use, and is effectively a modern web-based replacement
for old "anonymous ftp" methods.
%prep

%build

%install
mkdir -p ${RPM_BUILD_ROOT}/opt
tar xzf ${RPM_SOURCE_DIR}/ZendTo-%{version}-%{release}.tgz -C ${RPM_BUILD_ROOT}/opt
mv ${RPM_BUILD_ROOT}/opt/ZendTo-%{version}-%{release} ${RPM_BUILD_ROOT}/opt/zendto
rm -rf ${RPM_BUILD_ROOT}/opt/zendto/docs/{rpm,debian,upgrade}
rm -rf ${RPM_BUILD_ROOT}/opt/zendto/templates-v3
chmod +x ${RPM_BUILD_ROOT}/opt/zendto/sbin/UPGRADE/*php
chmod +x ${RPM_BUILD_ROOT}/opt/zendto/sbin/UPGRADE/*sh
chmod +x ${RPM_BUILD_ROOT}/opt/zendto/sbin/*pl
chmod +x ${RPM_BUILD_ROOT}/opt/zendto/bin/*
chmod -x ${RPM_BUILD_ROOT}/opt/zendto/bin/*README*
chmod +x ${RPM_BUILD_ROOT}/opt/zendto/bin/*language*
chmod +x ${RPM_BUILD_ROOT}/opt/zendto/bin/upgrade*

mkdir -p ${RPM_BUILD_ROOT}/var/zendto
#chgrp apache ${RPM_BUILD_ROOT}/var/zendto
#chmod g+w ${RPM_BUILD_ROOT}/var/zendto

install -D --mode=0644 ${RPM_SOURCE_DIR}/cron.d ${RPM_BUILD_ROOT}/etc/cron.d/zendto
install -D --mode=0644 ${RPM_SOURCE_DIR}/logrotate.d ${RPM_BUILD_ROOT}/etc/logrotate.d/zendto
install -D --mode=0755 ${RPM_SOURCE_DIR}/profile.d.sh ${RPM_BUILD_ROOT}/etc/profile.d/zendto.sh
install -D --mode=0755 ${RPM_SOURCE_DIR}/profile.d.csh ${RPM_BUILD_ROOT}/etc/profile.d/zendto.csh
mkdir -p ${RPM_BUILD_ROOT}/var/log/zendto

%clean
rm -rf ${RPM_BUILD_ROOT}

%pre

%post
# Work out what the Apache username and group are
WWWUSER="$( grep -ri --no-filename '^\s*User\s\s*' /etc/httpd /etc/apache2 2>/dev/null | head -1 | sed -e 's/#.*$//' | awk '{ print $2 }' )"
WWWGROUP="$( grep -ri --no-filename '^\s*Group\s\s*' /etc/httpd /etc/apache2 2>/dev/null | head -1 | sed -e 's/#.*$//' | awk '{ print $2 }' )"
if [ "x$WWWUSER" = "x" ]; then
  WWWUSER='apache'
fi
if [ "x$WWWGROUP" = "x" ]; then
  WWWGROUP='apache'
fi
export WWWUSER
export WWWGROUP

# Construct /var/zendto and others.
# Cannot pre-determine the ownership as we can only read this from
# httpd.conf, when we are on the target system and installing ourselves.
mkdir -p /var/zendto
chown root:"$WWWGROUP" /var/zendto
chgrp "$WWWGROUP" /var/zendto
chmod 0775 /var/zendto
for F in incoming dropoffs rrd library cache templates_c
do
  if [ \! -d /var/zendto/$F/ ]; then
    mkdir -p /var/zendto/$F
    chown "$WWWUSER":"$WWWGROUP" /var/zendto/$F
    chmod 0755 /var/zendto/$F
  fi
done
if [ \! -d /var/log/zendto ]; then
  mkdir -p /var/log/zendto
  chgrp "$WWWGROUP" /var/log/zendto
  chmod 0775 /var/log/zendto
  :> /var/log/zendto/zendto.log
  chown "$WWWUSER":"$WWWGROUP" /var/log/zendto/zendto.log
  chmod u=rw,g=rw,o=r /var/log/zendto/zendto.log
  semanage fcontext --add -s system_u -t httpd_log_t '/var/log/zendto(/.*)?' 2>/dev/null && restorecon -FR /var/log/zendto 2>/dev/null
fi
#if [ \! -f /var/zendto/zendto.log ]; then
#  :> /var/zendto/zendto.log
#  chown "$WWWUSER":"$WWWGROUP" /var/zendto/zendto.log
#  chmod u=rw,g=rw,o=r /var/zendto/zendto.log
#fi
for F in cache templates_c
do
  :> /var/zendto/$F/This.Dir.Must.Be.Writeable.By.Apache
  chown "$WWWUSER":"$WWWGROUP" /var/zendto/$F/This.Dir.Must.Be.Writeable.By.Apache
  chmod u=rw,g=rw,o=r /var/zendto/$F/This.Dir.Must.Be.Writeable.By.Apache
done
cp /opt/zendto/www/images/notfound.png /var/zendto/rrd/notfound.png
chmod a+r /var/zendto/rrd/notfound.png

# Set the permissions in case we're on SuSE
for F in zendto.conf preferences.php internaldomains.conf system-announcement.txt
do
  chgrp "$WWWGROUP" /opt/zendto/config/$F
done
chgrp "$WWWGROUP" /opt/zendto/config/locale/*_*/LC_MESSAGES/zendto.{po,mo}* 2>/dev/null

# Clean the caches in case Smarty has been upgraded
rm -f /var/zendto/cache/*php >/dev/null 2>&1
rm -f /var/zendto/templates_c/*php >/dev/null 2>&1

# Remove obsolete caches in case they are still there
rm -rf /opt/zendto/cache >/dev/null 2>&1
rm -rf /opt/zendto/templates_c >/dev/null 2>&1

# Build the language files
/opt/zendto/bin/makelanguages >/dev/null
chmod -R go+rX /opt/zendto/config/locale/*_*

if systemctl --version >/dev/null 2>&1; then
  # Find what cron/crond is called on this box
  CROND="$( systemctl list-unit-files | grep '^cron.*\.service\s' | awk '{ print $1 }' )"
  if [ "x$CROND" = "x" ]; then
    CROND="crond.service"
  fi
  systemctl reload "$CROND"
else
  service crond reload
fi

if [ $1 = 1 ]; then
  # We are being installed, not upgraded (that would be 2)
  # See postun for the post-upgrade script.

  # Use cleanup.php to create the SQLite3 database
  SH="$( command -v bash )"
  PH="$( command -v php )"
  su --shell="$SH" --group="$WWWGROUP" --command="$PH /opt/zendto/sbin/cleanup.php /opt/zendto/config/preferences.php --no-purge" "$WWWUSER" >/dev/null

  # And double-check
  if [ -f /var/zendto/zendto.sqlite ]; then
    chown "$WWWUSER"  /var/zendto/zendto.sqlite
    chgrp "$WWWGROUP" /var/zendto/zendto.sqlite
    chmod ug+w        /var/zendto/zendto.sqlite
  fi

  echo 'To edit any text in the web interface, beyond the settings in'
  echo 'zendto.conf, or to add/edit a language see'
  echo '    https://zend.to/translators.php'
  echo
  echo For technical support, please go to https://zend.to.
  echo
fi

%preun
if [ $1 = 0 ]; then
  # We are being deleted, not upgraded
  if systemctl --version >/dev/null 2>&1; then
    # Find what cron/crond is called on this box
    CROND="$( systemctl list-unit-files | grep '^cron.*\.service\s' | awk '{ print $1 }' )"
    if [ "x$CROND" = "x" ]; then
      CROND="crond.service"
    fi
    systemctl reload "$CROND"
  else
    service crond reload
  fi
  echo 'You can delete all the files created by ZendTo with the command'
  echo 'rm -rf /var/zendto'
fi
exit 0

%postun
[ "$1" -lt "1" ] && exit 0

# We are being upgraded or replaced, not deleted
# Clean the caches in case Smarty has been upgraded
rm -f /var/zendto/templates_c/*php >/dev/null 2>&1
rm -f /var/zendto/myzendto.templates_c/*php >/dev/null 2>&1
rm -f /var/zendto/cache/*php >/dev/null 2>&1
if systemctl --version >/dev/null 2>&1; then
  # Find what cron/crond is called on this box
  CROND="$( systemctl list-unit-files | grep '^cron.*\.service\s' | awk '{ print $1 }' )"
  if [ "x$CROND" = "x" ]; then
    CROND="crond.service"
  fi
  systemctl reload "$CROND"
else
  service crond reload
fi
# Apply schema fixes
/usr/bin/php /opt/zendto/sbin/cleanup.php /opt/zendto/config/preferences.php --no-purge >/dev/null
# Reset SELinux attributes for new /opt/zendto files
# Shouldn't be needed
# restorecon -FR /opt/zendto 2>/dev/null

echo 'Please ensure your /opt/zendto/config/* files are up to date.'
echo
echo 'To help you, there is a simple tool for upgrading both the'
echo 'preferences.php and zendto.conf files.'
echo 'Simply run'
echo '    /opt/zendto/bin/upgrade'
echo 'or for a dry run (no actions taken), run'
echo '    /opt/zendto/bin/upgrade --dry-run'
echo
echo 'To edit any text in the web interface, or add/edit a language,'
echo 'see'
echo '    https://zend.to/translators.php'
echo
exit 0

%posttrans
# This is run last. See
# https://fedoraproject.org/wiki/Packaging:Scriptlets
#
# Throw away any remaining relics of MyZendTo
rm -rf /opt/zendto/myzendto.templates_c >/dev/null 2>&1
rm -rf /var/zendto/myzendto.templates_c >/dev/null 2>&1
rm -rf /opt/zendto/myzendto.www >/dev/null 2>&1
rm -rf /opt/zendto/myzendto.templates >/dev/null 2>&1

%files
%attr(755,root,root) %dir /opt/zendto
/opt/zendto/lib
/opt/zendto/www
/opt/zendto/sql
#%attr(775,root,apache) %dir /var/zendto
%attr(775,root,root) %dir /var/zendto
%config(noreplace) %attr(644,root,root) /opt/zendto/www/favicon.ico
%config(noreplace) %attr(755,root,root) %dir /opt/zendto/www/css
%config(noreplace) %attr(644,root,root) /opt/zendto/www/css/local.css
%config(noreplace) %attr(755,root,root) %dir /opt/zendto/www/images/swish
%config(noreplace) %attr(644,root,root) /opt/zendto/www/images/email/email-logo.png
%config(noreplace) %attr(644,root,root) /opt/zendto/www/images/dropbox-icon.png

%doc /opt/zendto/docs
%doc /opt/zendto/README
%doc /opt/zendto/GPL.txt
%doc /opt/zendto/ChangeLog


%attr(755,root,root) %dir /opt/zendto/config
%config(noreplace) %attr(644,root,root) /opt/zendto/config/zendto.conf
%config(noreplace) %attr(640,root,root) /opt/zendto/config/preferences.php
%config(noreplace) %attr(644,root,root) /opt/zendto/config/internaldomains.conf
%config(noreplace) %attr(640,root,root) /opt/zendto/config/system-announcement.txt
/opt/zendto/config/locale/supplied
/opt/zendto/config/locale/zendto.pot
%config(noreplace) %attr(644,root,root) /opt/zendto/config/locale/en_US/LC_MESSAGES/zendto.po
%config(noreplace) %attr(644,root,root) /opt/zendto/config/locale/en_GB/LC_MESSAGES/zendto.po
%config(noreplace) %attr(644,root,root) /opt/zendto/config/locale/fr_FR/LC_MESSAGES/zendto.po
%config(noreplace) %attr(644,root,root) /opt/zendto/config/locale/de_DE/LC_MESSAGES/zendto.po
%config(noreplace) %attr(644,root,root) /opt/zendto/config/locale/es_ES/LC_MESSAGES/zendto.po
%config(noreplace) %attr(644,root,root) /opt/zendto/config/locale/it_IT/LC_MESSAGES/zendto.po
%config(noreplace) %attr(644,root,root) /opt/zendto/config/locale/nl_NL/LC_MESSAGES/zendto.po
%config(noreplace) %attr(644,root,root) /opt/zendto/config/locale/pt_BR/LC_MESSAGES/zendto.po
%config(noreplace) %attr(644,root,root) /opt/zendto/config/locale/cs_CZ/LC_MESSAGES/zendto.po
%config(noreplace) %attr(644,root,root) /opt/zendto/config/locale/gl_ES/LC_MESSAGES/zendto.po
%config(noreplace) %attr(644,root,root) /opt/zendto/config/locale/pl_PL/LC_MESSAGES/zendto.po
%config(noreplace) %attr(644,root,root) /opt/zendto/config/locale/ru_RU/LC_MESSAGES/zendto.po
%config(noreplace) %attr(644,root,root) /opt/zendto/config/locale/hu_HU/LC_MESSAGES/zendto.po

%attr(755,root,root) %dir /opt/zendto/templates
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/about.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/claimid_box.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/delete.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/dropoff_email.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/dropoff_email_html.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/dropoff_list.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/email_footer_html.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/email_header_html.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/email_logo_html.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/error.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/footer.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/functions.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/header.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/login.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/logout.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/log.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/main_menu.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/new_dropoff.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/new_dropoff.js.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/no_download.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/pickupcheck.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/pickup_email.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/pickup_email_html.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/pickup_list_all.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/pickup_list.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/request_email.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/request_email_html.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/request_sent.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/request.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/resend.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/security.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/show_dropoff.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/stats.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/summary_email.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/summary_email_html.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/unlock.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/verify_email.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/verify_email_html.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/verify_sent.tpl
%config(noreplace) %attr(644,root,root) /opt/zendto/templates/verify.tpl

%attr(755,root,root) %dir /opt/zendto/sbin
%attr(755,root,root) /opt/zendto/sbin/cleanup.php
%attr(755,root,root) /opt/zendto/sbin/emailSummary.php
%attr(755,root,root) /opt/zendto/sbin/stats.php
%attr(755,root,root) /opt/zendto/sbin/genCookieSecret.php
%attr(755,root,root) /opt/zendto/sbin/rrdInit.php
%attr(755,root,root) /opt/zendto/sbin/rrdUpdate.php
%attr(755,root,root) /opt/zendto/sbin/setphpini.pl

%attr(755,root,root) %dir /opt/zendto/sbin/UPGRADE
%doc /opt/zendto/sbin/UPGRADE/README.FIRST.txt
%attr(755,root,root) /opt/zendto/sbin/UPGRADE/addAuthTable.php
%attr(755,root,root) /opt/zendto/sbin/UPGRADE/addLoginlogTable.php
%attr(755,root,root) /opt/zendto/sbin/UPGRADE/addNotesColumn.sh
%attr(755,root,root) /opt/zendto/sbin/UPGRADE/addReqTable.php
%attr(755,root,root) /opt/zendto/sbin/UPGRADE/addUserTable.php
%attr(755,root,root) /opt/zendto/sbin/UPGRADE/addRegexpsTable.php
%attr(755,root,root) /opt/zendto/sbin/UPGRADE/fixDropoffTable.php
%attr(755,root,root) /opt/zendto/sbin/UPGRADE/upgrade
%attr(755,root,root) /opt/zendto/sbin/UPGRADE/upgrade_preferences_php
%attr(755,root,root) /opt/zendto/sbin/UPGRADE/upgrade_zendto_conf

%attr(755,root,root) %dir /opt/zendto/bin
%attr(755,root,root) /opt/zendto/bin/addlanguage
%attr(755,root,root) /opt/zendto/bin/adduser
%attr(755,root,root) /opt/zendto/bin/autodropoff
%attr(755,root,root) /opt/zendto/bin/autolist
%attr(755,root,root) /opt/zendto/bin/autopickup
%attr(755,root,root) /opt/zendto/bin/autorequest
%attr(755,root,root) /opt/zendto/bin/deleteuser
%attr(755,root,root) /opt/zendto/bin/extractdropoff
%attr(755,root,root) /opt/zendto/bin/listusers
%attr(755,root,root) /opt/zendto/bin/makelanguages
%attr(755,root,root) /opt/zendto/bin/setpassword
%attr(755,root,root) /opt/zendto/bin/templatecheck
%attr(755,root,root) /opt/zendto/bin/testlocale
%attr(755,root,root) /opt/zendto/bin/tsmarty2c
%attr(755,root,root) /opt/zendto/bin/unlockuser
%attr(755,root,root) /opt/zendto/bin/upgrade
%attr(755,root,root) /opt/zendto/bin/upgrade_preferences_php
%attr(755,root,root) /opt/zendto/bin/upgrade_zendto_conf
%doc /opt/zendto/bin/README.txt
%doc /opt/zendto/bin/tsmarty2c.README

%config(noreplace) /etc/cron.d/zendto
%config(noreplace) /etc/logrotate.d/zendto
%attr(755,root,root) /etc/profile.d/zendto.sh
%attr(755,root,root) /etc/profile.d/zendto.csh

%changelog
* Mon Jul 8 2019 Jules Field <jules@zend.to>
- Added system-announcement.txt
* Sun Jun 2 2019 Jules Field <jules@zend.to>
- Added jq dependency for autopickup
* Wed Dec 19 2018 Jules Field <jules@zend.to>
- Added upgrade
* Mon Oct 01 2018 Jules Field <jules@zend.to>
- Removed MyZendTo
* Mon Sep 03 2018 Jules Field <jules@zend.to>
- Added logrotate config file
* Mon May 28 2018 Jules Field <jules@zend.to>
- Added chgrp stuff for SuSE
* Fri Jan 05 2018 Jules Field <jules@zend.to>
- Added updates for upcoming version 5
* Mon Apr 03 2017 Jules Field <jules@zend.to>
- Added upgrade_zendto_conf
* Tue Mar 14 2017 Jules Field <jules@zend.to>
- Added new HTML email templates
* Thu Dec 22 2016 Jules Field <jules@zend.to>
- Added upgrade_preferences_php
* Sun Dec 18 2016 Jules Field <jules@zend.to>
- Added internaldomains.conf
* Fri Dec 16 2016 Jules Field <jules@zend.to>
- Moved cache, templates_c to /var/zendto
* Mon Nov 28 2016 Jules Field <jules@zend.to>
- Do not package old templates-v3
* Sat Nov 26 2016 Jules Field <jules@zend.to>
- Fixing it up for new release 4.19
* Thu Dec 08 2011 Julian Field <jules@zend.to>
- Added var library directory
* Thu Aug 11 2011 Julian Field <jules@zend.to>
- Added files for Resend functionality
* Sat Jul 16 2011 Julian Field <jules@zend.to>
- Updated UI for MyZendTo, including quota support
* Fri Apr 15 2011 Julian Field <jules@zend.to>
- Added more dependencies, wish CentOS would release v6!
* Wed Mar 30 2011 Julian Field <jules@zend.to>
- Moved existing templates to templates-v3 and added new templates
* Mon Feb 21 2011 Julian Field <jules@zend.to>
- Added "Send a Request"
* Wed Feb 09 2011 Julian Field <jules@zend.to>
- Added progress bars
* Fri Aug 06 2010 Julian Field <jules@zendto.com>
- Added profile.d files
* Tue Jul 27 2010 Julian Field <jules@zendto.com>
- Added addLoginlogTable.php and unlockuser.php
* Sat Jul 24 2010 Julian Field <jules@zendto.com>
- Added MyZendTo application to the package
* Sun Jul 18 2010 Julian Field <jules@zendto.com>
- Added zendto/bin and all Local Authenticator files
* Thu Jul 08 2010 Julian Field <jules@zendto.com>
- 1st edition

