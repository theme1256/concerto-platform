#!/bin/sh
set -eu

# Preserve the paths used by cron and extension builds without installing a
# second, unpatched PHP from Ubuntu's public repositories.
ln -s /opt/concerto/php/bin/php /usr/bin/php
ln -s /opt/concerto/php/bin/php /usr/bin/php7.4
ln -s /opt/concerto/php/bin/phpize /usr/bin/phpize
ln -s /opt/concerto/php/bin/php-config /usr/bin/php-config
ln -s /opt/concerto/php/sbin/php-fpm /usr/sbin/php-fpm7.4
mkdir -p /etc/php/7.4/cli /etc/php/7.4/conf.d /run/php /var/lib/php/sessions
chmod 1733 /var/lib/php/sessions
cp /opt/concerto/php/share/concerto/php.ini-production /etc/php/7.4/cli/php.ini
cat >> /etc/php/7.4/cli/php.ini <<'INI'

; Preserve unlimited memory for trusted CLI import/cache jobs only.
memory_limit = -1
session.upload_progress.enabled = 0
expose_php = 0
INI
printf 'zend_extension=opcache.so\n' > /etc/php/7.4/conf.d/10-opcache.ini
chmod 755 /usr/local/bin/concerto-start-php-fpm
