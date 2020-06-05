# Cron job for ZendTo SAML support to automatically refresh the IdP
# metadata in /var/zendto/saml-metadata/<IdP-name>
10 0 * * * root /opt/zendto/sbin/refresh_saml_metadata.sh >/dev/null 2>&1
