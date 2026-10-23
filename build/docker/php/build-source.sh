#!/bin/sh
set -eu

build_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
. "$build_dir/source.lock"

export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y --no-install-recommends \
    autoconf bison build-essential ca-certificates curl pkg-config re2c \
    libargon2-dev libcurl4-openssl-dev libonig-dev libreadline-dev \
    libsodium-dev libsqlite3-dev libssl-dev libxml2-dev libzip-dev zlib1g-dev

mkdir -p /tmp/php-src
curl --fail --location --retry 3 --proto '=https' --tlsv1.2 \
    "https://codeload.github.com/php/php-src/tar.gz/$PHP_SOURCE_COMMIT" \
    --output /tmp/php-src.tar.gz
printf '%s  %s\n' "$PHP_SOURCE_SHA256" /tmp/php-src.tar.gz | sha256sum --check --strict
tar -xzf /tmp/php-src.tar.gz --strip-components=1 -C /tmp/php-src
cd /tmp/php-src
./buildconf --force
./configure \
    --prefix=/opt/concerto/php \
    --with-config-file-path=/etc/php/7.4/cli \
    --with-config-file-scan-dir=/etc/php/7.4/conf.d \
    --enable-fpm --with-fpm-user=www-data --with-fpm-group=www-data \
    --disable-cgi --without-pear \
    --enable-calendar --enable-exif --enable-ftp --enable-mbstring \
    --enable-opcache --enable-pcntl --enable-shmop --enable-sockets \
    --enable-sysvmsg --enable-sysvsem --enable-sysvshm \
    --with-curl --with-gettext --with-mysqli=mysqlnd --with-pdo-mysql=mysqlnd \
    --with-openssl --with-password-argon2 --with-readline --with-sodium \
    --with-zip --with-zlib
make -j "${PHP_BUILD_JOBS:-4}"
make install
strip /opt/concerto/php/bin/php /opt/concerto/php/sbin/php-fpm
mkdir -p /opt/concerto/php/share/concerto
cp php.ini-production /opt/concerto/php/share/concerto/php.ini-production
cp "$build_dir/source.lock" /opt/concerto/php/share/concerto/source.lock
cd /opt/concerto/php
sha256sum bin/php sbin/php-fpm > share/concerto/binaries.sha256
