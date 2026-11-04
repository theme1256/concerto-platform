#!/bin/sh
set -eu

build_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
. "$build_dir/source.lock"

export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y --no-install-recommends \
    build-essential ca-certificates curl file pkg-config xz-utils \
    libargon2-dev libcurl4-openssl-dev libonig-dev libreadline-dev \
    libsodium-dev libsqlite3-dev libssl-dev libxml2-dev libzip-dev zlib1g-dev

mkdir -p /tmp/php-src
curl --fail --location --retry 3 --proto '=https' --tlsv1.2 \
    "$PHP_SOURCE_URL" --output /tmp/php-src.tar.xz
printf '%s  %s\n' "$PHP_SOURCE_SHA256" /tmp/php-src.tar.xz | sha256sum --check --strict
tar -xJf /tmp/php-src.tar.xz --strip-components=1 -C /tmp/php-src
cd /tmp/php-src
./configure \
    --prefix=/opt/concerto/php \
    --with-config-file-path=/etc/php/8.5/cli \
    --with-config-file-scan-dir=/etc/php/8.5/conf.d \
    --enable-fpm --with-fpm-user=www-data --with-fpm-group=www-data \
    --disable-cgi --without-pear \
    --enable-calendar --enable-exif --enable-ftp --enable-mbstring \
    --enable-pcntl --enable-shmop --enable-sockets \
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

# Ubuntu packages providing the shared libraries the binaries link against, so the runtime
# stage installs exactly these (package names change between releases, e.g. libssl3t64).
for lib in $(ldd bin/php sbin/php-fpm | awk '$2 == "=>" && $3 ~ /^\// {print $3}' | sort -u); do
    dpkg -S "$lib" 2>/dev/null || dpkg -S "$(readlink -f "$lib")" 2>/dev/null \
        || dpkg -S "/usr$lib" 2>/dev/null || { echo "no package provides $lib" >&2; exit 1; }
done | cut -d: -f1 | sort -u > share/concerto/runtime-packages
cat share/concerto/runtime-packages
