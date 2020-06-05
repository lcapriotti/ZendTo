# Cron jobs for ZendTo to remove expired drop-offs and update usage graphs
5 0 * * * root /usr/bin/php /opt/zendto/sbin/cleanup.php /opt/zendto/config/preferences.php >/dev/null 2>&1
25 * * * * root /usr/bin/php /opt/zendto/sbin/cleanup.php /opt/zendto/config/preferences.php --no-warnings >/dev/null 2>&1
15 0 * * * root /usr/bin/php /opt/zendto/sbin/emailSummary.php /opt/zendto/config/preferences.php >/dev/null 2>&1
5 */4 * * * root find -H /var/zendto/incoming -type f -mmin +1440 -delete >/dev/null 2>&1
1 1 * * * root /usr/bin/php /opt/zendto/sbin/rrdInit.php /opt/zendto/config/preferences.php 2>&1 | /bin/grep -iv 'illegal attempt to update using time'
3 3 * * * root /usr/bin/php /opt/zendto/sbin/rrdUpdate.php /opt/zendto/config/preferences.php 2>&1 | sed '$ d' | /bin/grep -v '^[0-9]*x[0-9]*$'
