#!/bin/sh
set -eu

# Check the actual executables and configuration again on container startup.
sh /usr/local/share/concerto/php/verify-runtime.sh
mkdir -p /run/php
exec /usr/sbin/php-fpm8.5 --daemonize \
    --php-ini /etc/php/8.5/fpm/php.ini \
    --fpm-config /etc/php/8.5/fpm/php-fpm.conf
