# Roll the ZendTo log, monthly or when it is bigger than 200 MB.
# Keep for max 1 year, name the old ones with a datestamp.
/var/log/zendto/zendto.log /var/zendto/zendto.log {
    monthly
    rotate 12
    maxsize 200M
    maxage 365
    compress
    dateext
    missingok
    notifempty
    su apache apache
    create 640
}
