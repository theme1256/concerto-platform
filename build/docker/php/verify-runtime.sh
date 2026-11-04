#!/bin/sh
set -eu

config_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
cmp "$config_dir/source.lock" /opt/concerto/php/share/concerto/source.lock
test "$(readlink -f /usr/bin/php)" = /opt/concerto/php/bin/php
test "$(readlink -f /usr/sbin/php-fpm8.5)" = /opt/concerto/php/sbin/php-fpm
(cd /opt/concerto/php && sha256sum --check --strict share/concerto/binaries.sha256)
. "$config_dir/source.lock"

PHP_EXPECTED_VERSION=$PHP_VERSION php -c /etc/php/8.5/fpm/php.ini <<'PHP'
<?php
function requireSetting($ok, $message) {
    if (!$ok) {
        file_put_contents('php://stderr', $message . "\n");
        exit(1);
    }
}
requireSetting(PHP_VERSION === getenv('PHP_EXPECTED_VERSION'), 'Unexpected PHP version: ' . PHP_VERSION);
requireSetting(ini_get('session.upload_progress.enabled') === '0', 'Upload progress must be disabled');
requireSetting((int) ini_get('max_multipart_body_parts') === 1020, 'Missing multipart body parts limit');
requireSetting(ini_get('memory_limit') === '128M', 'FPM must retain its memory limit');
requireSetting(ini_get('post_max_size') === '50M', 'Unexpected POST limit');
requireSetting(ini_get('upload_max_filesize') === '48M', 'Unexpected upload limit');
requireSetting((int) ini_get('max_file_uploads') === 20, 'Unexpected upload count limit');
foreach (['ctype', 'curl', 'dom', 'fileinfo', 'iconv', 'json', 'mbstring', 'mysqli',
          'openssl', 'PDO', 'pdo_mysql', 'posix', 'session', 'SimpleXML', 'sodium',
          'tokenizer', 'xml', 'xmlreader', 'xmlwriter', 'zip', 'zlib', 'Zend OPcache'] as $extension) {
    requireSetting(extension_loaded($extension), 'Missing PHP extension: ' . $extension);
}
echo 'Pinned PHP ', PHP_VERSION, ', extensions and FPM INI verified', "\n";
PHP
