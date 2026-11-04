#!/bin/sh
set -eu

# Build the existing Oracle extension against our PHP, without php8.5-dev or
# php-pear pulling an unpatched Ubuntu interpreter back into the image.
test -n "${ORACLE_HOME:-}"
build_dir=$(mktemp -d)
trap 'rm -rf "$build_dir"' EXIT HUP INT TERM
curl --fail --location --retry 3 --proto '=https' --tlsv1.2 \
    https://pecl.php.net/get/oci8-3.4.1.tgz --output "$build_dir/oci8.tgz"
printf '%s  %s\n' c6d612bc750d551dfc15b969346f6a67167f4441f08943adaa3e7fda5d90dd2b \
    "$build_dir/oci8.tgz" | sha256sum --check --strict
tar -xzf "$build_dir/oci8.tgz" -C "$build_dir"
cd "$build_dir/oci8-3.4.1"
phpize
./configure --with-php-config=/opt/concerto/php/bin/php-config \
    --with-oci8="instantclient,$ORACLE_HOME"
make -j "${PHP_BUILD_JOBS:-4}"
make install
printf 'extension=oci8.so\n' > /etc/php/8.5/conf.d/20-oci8.ini
php --ri oci8
