#!/bin/sh
set -eu

# Build the existing Oracle extension against our PHP, without php7.4-dev or
# php-pear pulling an unpatched Ubuntu interpreter back into the image.
test -n "${ORACLE_HOME:-}"
build_dir=$(mktemp -d)
trap 'rm -rf "$build_dir"' EXIT HUP INT TERM
curl --fail --location --retry 3 --proto '=https' --tlsv1.2 \
    https://pecl.php.net/get/oci8-2.2.0.tgz --output "$build_dir/oci8.tgz"
printf '%s  %s\n' cddf43b9c7204cf95980a1e4761a30313a07fbf163fc2120517f4e1b64df114a \
    "$build_dir/oci8.tgz" | sha256sum --check --strict
tar -xzf "$build_dir/oci8.tgz" -C "$build_dir"
cd "$build_dir/oci8-2.2.0"
phpize
./configure --with-php-config=/opt/concerto/php/bin/php-config \
    --with-oci8="instantclient,$ORACLE_HOME"
make -j "${PHP_BUILD_JOBS:-4}"
make install
printf 'extension=oci8.so\n' > /etc/php/7.4/conf.d/20-oci8.ini
php --ri oci8
