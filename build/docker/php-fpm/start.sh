#!/bin/sh
set -eu

# Check the actual executables and configuration again on container startup.
sh /usr/local/share/concerto/php/verify-runtime.sh
mkdir -p /run/php
exec /usr/sbin/php-fpm7.4 --daemonize \
    --php-ini /etc/php/7.4/fpm/php.ini \
    --fpm-config /etc/php/7.4/fpm/php-fpm.conf
