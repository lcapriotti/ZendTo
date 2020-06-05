%define version 1.0
%define release 1
%define name    zendto-saml

%define is_fedora %(test -e /etc/fedora-release && echo 1 || echo 0)

Name:        %{name}
Version:     %{version}
Release:     %{release}
Summary:     SAML authentication for ZendTo
Group:       Networking/WWW
License:     GPL
Vendor:      Julian Field www.zend.to
Packager:    Julian Field <Support@Zend.To>
URL:         https://zend.to/
AutoReq:     no
Requires:    zendto >= 5.24-2, memcached, php-memcached
Source:      ZendTo-saml-%{version}-%{release}.tgz
BuildRoot:   %{_tmppath}/%{name}-root
BuildArchitectures: noarch

%description
This package adds SAML authentication to ZendTo.
You must already have ZendTo version 5.24-2 or later installed.
To configure it please see the README-saml in /opt/zendto.
%prep

%build

%install
mkdir -p ${RPM_BUILD_ROOT}/opt
tar xzf ${RPM_SOURCE_DIR}/ZendTo-saml-%{version}-%{release}.tgz -C ${RPM_BUILD_ROOT}/opt
mv ${RPM_BUILD_ROOT}/opt/ZendTo-saml-%{version}-%{release} ${RPM_BUILD_ROOT}/opt/zendto
rm -rf ${RPM_BUILD_ROOT}/opt/zendto/docs
chmod +x ${RPM_BUILD_ROOT}/opt/zendto/sbin/*sh
chmod -x ${RPM_BUILD_ROOT}/opt/zendto/*README*

mkdir -p ${RPM_BUILD_ROOT}/var/zendto/saml-metadata/azure
mkdir -p ${RPM_BUILD_ROOT}/opt/zendto/simplesamlphp/cert
#chgrp -R apache ${RPM_BUILD_ROOT}/var/zendto/saml-metadata
#chmod -R g+w ${RPM_BUILD_ROOT}/var/zendto/saml-metadata

install -D --mode=0644 ${RPM_SOURCE_DIR}/cron.d ${RPM_BUILD_ROOT}/etc/cron.d/zendto-saml

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

# Construct /var/zendto and others.
# Cannot pre-determine the ownership as we can only read this from
# httpd.conf, when we are on the target system and installing ourselves.
mkdir -p /var/zendto/saml-metadata/azure /opt/zendto/simplesamlphp/cert
chown -R root:"$WWWGROUP" /var/zendto/saml-metadata /opt/zendto/simplesamlphp/cert
chgrp -R "$WWWGROUP" /var/zendto/saml-metadata /opt/zendto/simplesamlphp/cert
chmod -R 0775 /var/zendto/saml-metadata /opt/zendto/simplesamlphp/cert
# And reset all the SELinux attributes correctly.
restorecon -FR /var/zendto 2>/dev/null
restorecon -FR /opt/zendto 2>/dev/null
# And tweak httpd so it can talk to memcached
setsebool -P httpd_can_network_memcache 1 >/dev/null 2>&1

# Set the permissions in case we're on SuSE
chgrp "$WWWGROUP" /opt/zendto/simplesamlphp/config/*php >/dev/null 2>&1

# Clean the caches in case Smarty has been upgraded
rm -f /var/zendto/cache/*php >/dev/null 2>&1
rm -f /var/zendto/templates_c/*php >/dev/null 2>&1

if systemctl --version >/dev/null 2>&1; then
  # Find what cron/crond is called on this box
  CROND="$( systemctl list-unit-files | grep '^cron.*\.service\s' | awk '{ print $1 }' )"
  if [ "x$CROND" = "x" ]; then
    CROND="crond.service"
  fi
  systemctl reload "$CROND"
  # Not enabled or running by default
  systemctl enable memcached
  systemctl start memcached
  # We have changed an SELinux bool, so restart Apache
  systemctl restart httpd
else
  service crond reload
  service memcached restart
  service httpd restart
fi

if [ $1 = 1 ]; then
  # We are being installed, not upgraded (that would be 2)
  # See postun for the post-upgrade script.
  echo 'To configure the SAML support in ZendTo, please carefully'
  echo 'read and follow https://zend.to/saml.php'
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
fi
exit 0

%postun
[ "$1" -lt "1" ] && exit 0

# We are being upgraded or replaced, not deleted
# Clean the caches in case Smarty has been upgraded
rm -f /var/zendto/templates_c/*php >/dev/null 2>&1
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
echo 'Please ensure your /opt/zendto/simplesamlphp/config/* files are up to date.'
echo
exit 0

%posttrans
# This is run last. See
# https://fedoraproject.org/wiki/Packaging:Scriptlets
#

%files
%attr(755,root,root) %dir /opt/zendto
/opt/zendto/simplesamlphp
#%attr(775,root,apache) %dir /var/zendto
%attr(775,root,root) %dir /var/zendto/saml-metadata
%doc /opt/zendto/README-saml
%doc /opt/zendto/ChangeLog-saml


%config(noreplace) %attr(755,root,root) %dir /opt/zendto/simplesamlphp/cert
%attr(755,root,root) %dir /opt/zendto/simplesamlphp/config
%attr(755,root,root) %dir /opt/zendto/simplesamlphp/config
%config(noreplace) %attr(644,root,root) /opt/zendto/simplesamlphp/config/acl.php
%config(noreplace) %attr(644,root,root) /opt/zendto/simplesamlphp/config/authsources.php
%config(noreplace) %attr(644,root,root) /opt/zendto/simplesamlphp/config/config-metarefresh.php
%config(noreplace) %attr(644,root,root) /opt/zendto/simplesamlphp/config/config.php
%config(noreplace) %attr(644,root,root) /opt/zendto/simplesamlphp/config/module_cron.php

/opt/zendto/www/samllogout.php
%attr(755,root,root) /opt/zendto/sbin/refresh_saml_metadata.sh

%config(noreplace) /etc/cron.d/zendto-saml

%changelog
* Wed Apr 29 2020 Julian Field <jules@zend.to>
- 1st edition

