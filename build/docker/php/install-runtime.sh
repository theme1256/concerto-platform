#!/bin/sh
set -eu

# Expose the PHP built from source on the paths used by cron and extension
# builds, without installing a second PHP from Ubuntu's repositories.
ln -s /opt/concerto/php/bin/php /usr/bin/php
ln -s /opt/concerto/php/bin/php /usr/bin/php8.5
ln -s /opt/concerto/php/bin/phpize /usr/bin/phpize
ln -s /opt/concerto/php/bin/php-config /usr/bin/php-config
ln -s /opt/concerto/php/sbin/php-fpm /usr/sbin/php-fpm8.5
mkdir -p /etc/php/8.5/cli /etc/php/8.5/conf.d /run/php /var/lib/php/sessions
chmod 1733 /var/lib/php/sessions
cp /opt/concerto/php/share/concerto/php.ini-production /etc/php/8.5/cli/php.ini
cat >> /etc/php/8.5/cli/php.ini <<'INI'

; Preserve unlimited memory for trusted CLI import/cache jobs only.
memory_limit = -1
session.upload_progress.enabled = 0
expose_php = 0
INI
chmod 755 /usr/local/bin/concerto-start-php-fpm
