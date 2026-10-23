<?php
// Run only in an isolated disposable container: this starts an FPM pool and
// deliberately exhausts a single child's 16 MB PHP limit, never host memory.
function check($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: $message\n";
}

function record($type, $content)
{
    return pack('CCnnCC', 1, $type, 1, strlen($content), 0, 0) . $content;
}

function lengthPrefix($value)
{
    $length = strlen($value);
    return $length < 128 ? chr($length) : pack('N', $length | 0x80000000);
}

function request($script, $query = '', $body = '', $contentType = '')
{
    $socket = stream_socket_client('tcp://127.0.0.1:9000', $errno, $error, 5);
    if (!$socket) {
        throw new RuntimeException("FPM connection failed: $error");
    }
    stream_set_timeout($socket, 5);
    $params = '';
    foreach ([
        'GATEWAY_INTERFACE' => 'CGI/1.1', 'SERVER_PROTOCOL' => 'HTTP/1.1',
        'REQUEST_METHOD' => $body === '' ? 'GET' : 'POST',
        'SCRIPT_FILENAME' => $script, 'SCRIPT_NAME' => '/probe.php',
        'REQUEST_URI' => '/probe.php?' . $query, 'QUERY_STRING' => $query,
        'CONTENT_LENGTH' => (string) strlen($body), 'CONTENT_TYPE' => $contentType,
        'REMOTE_ADDR' => '127.0.0.1', 'SERVER_NAME' => 'localhost', 'SERVER_PORT' => '80',
        'HTTP_COOKIE' => 'PHPSESSID=concerto-runtime-check',
    ] as $name => $value) {
        $params .= lengthPrefix($name) . lengthPrefix($value) . $name . $value;
    }
    $wire = record(1, pack('nCxxxxx', 1, 0)) . record(4, $params) . record(4, '');
    foreach (str_split($body, 65535) as $chunk) {
        $wire .= record(5, $chunk);
    }
    $wire .= record(5, '');
    for ($offset = 0; $offset < strlen($wire); $offset += $written) {
        $written = fwrite($socket, substr($wire, $offset));
        if (!$written) {
            throw new RuntimeException('Could not send FastCGI request');
        }
    }
    $wire = stream_get_contents($socket);
    $timedOut = stream_get_meta_data($socket)['timed_out'];
    fclose($socket);
    if ($timedOut) {
        throw new RuntimeException('FastCGI response timed out');
    }
    $output = '';
    for ($offset = 0; $offset + 8 <= strlen($wire);) {
        $header = unpack('Cversion/Ctype/nid/nlength/Cpadding/Creserved', substr($wire, $offset, 8));
        if ($header['type'] === 6) {
            $output .= substr($wire, $offset + 8, $header['length']);
        }
        $offset += 8 + $header['length'] + $header['padding'];
    }
    $parts = explode("\r\n\r\n", $output, 2);
    return isset($parts[1]) ? json_decode($parts[1], true) : null;
}

$directory = sys_get_temp_dir() . '/concerto-php-check-' . bin2hex(random_bytes(6));
mkdir($directory, 0777);
chmod($directory, 0777);
$probe = $directory . '/probe.php';
file_put_contents($probe, <<<'PHP'
<?php
if (isset($_GET['oom'])) {
    ini_set('memory_limit', '16M');
    register_shutdown_function(function () {
        file_put_contents(__DIR__ . '/unsafe-shutdown', 'executed');
    });
    str_repeat('x', 32 * 1024 * 1024);
}
ini_set('session.save_path', __DIR__);
session_start();
$_SESSION['requests'] = ($_SESSION['requests'] ?? 0) + 1;
header('Content-Type: application/json');
echo json_encode([
    'sapi' => PHP_SAPI, 'version' => PHP_VERSION, 'pid' => getmypid(),
    'progress' => (bool) ini_get('session.upload_progress.enabled'),
    'body_parts' => (int) ini_get('max_multipart_body_parts'),
    'memory' => ini_get('memory_limit'), 'post_limit' => ini_get('post_max_size'),
    'file_limit' => ini_get('upload_max_filesize'), 'file_count_limit' => (int) ini_get('max_file_uploads'),
    'requests' => $_SESSION['requests'], 'fields' => $_POST, 'files' => count($_FILES),
    'upload' => isset($_FILES['document']) ? [
        'error' => $_FILES['document']['error'],
        'text' => file_get_contents($_FILES['document']['tmp_name']),
    ] : null,
]);
PHP
);

foreach ([
    'PHP_FPM_PM' => 'static', 'PHP_FPM_PM_MAX_CHILDREN' => '1',
    'PHP_FPM_PM_START_SERVERS' => '1', 'PHP_FPM_PM_MIN_SPARE_SERVERS' => '1',
    'PHP_FPM_PM_MAX_SPARE_SERVERS' => '1', 'PHP_FPM_PM_PROCESS_IDLE_TIMEOUT' => '10s',
    'PHP_FPM_PM_MAX_REQUESTS' => '100', 'CONCERTO_COOKIES_SECURE_PHP' => '0',
    'CONCERTO_COOKIES_SAME_SITE' => 'strict', 'CONCERTO_COOKIES_LIFETIME' => '0',
] as $name => $value) {
    putenv("$name=$value");
}
$process = proc_open([
    '/usr/sbin/php-fpm7.4', '--nodaemonize', '--php-ini', '/etc/php/7.4/fpm/php.ini',
    '--fpm-config', '/etc/php/7.4/fpm/php-fpm.conf',
], [0 => ['file', '/dev/null', 'r'], 1 => ['file', $directory . '/fpm.log', 'a'],
    2 => ['file', $directory . '/fpm.log', 'a']], $pipes);
$failed = false;
try {
    check(is_resource($process), 'FPM process starts');
    $ready = false;
    for ($i = 0; $i < 100; $i++) {
        $socket = @stream_socket_client('tcp://127.0.0.1:9000', $errno, $error, 0.1);
        if ($socket) {
            fclose($socket);
            $ready = true;
            break;
        }
        usleep(50000);
    }
    check($ready, 'FPM listens on loopback');
    $first = request($probe);
    check($first && $first['sapi'] === 'fpm-fcgi' && $first['version'] === '7.4.34-dev', 'Actual FPM serves the pinned PHP version');
    check(!$first['progress'] && $first['body_parts'] === 1020 && $first['memory'] === '128M'
        && $first['post_limit'] === '50M' && $first['file_limit'] === '48M'
        && $first['file_count_limit'] === 20, 'Actual FPM applies hardened parser settings');
    $second = request($probe);
    check($second['requests'] === $first['requests'] + 1, 'Sessions persist between requests');

    $boundary = 'ConcertoRuntimeBoundary';
    $body = "--$boundary\r\nContent-Disposition: form-data; name=\"title\"\r\n\r\nCSV import\r\n"
        . "--$boundary\r\nContent-Disposition: form-data; name=\"document\"; filename=\"data.csv\"\r\n"
        . "Content-Type: text/csv\r\n\r\nname,score\nexample,42\n\r\n--$boundary--\r\n";
    $upload = request($probe, '', $body, 'multipart/form-data; boundary=' . $boundary);
    check($upload && $upload['fields']['title'] === 'CSV import' && $upload['upload']['error'] === UPLOAD_ERR_OK
        && $upload['upload']['text'] === "name,score\nexample,42\n", 'Multipart fields and file uploads remain functional');

    // Empty file controls used to evade max_file_uploads. The backport counts
    // multipart parts independently, including these controls.
    $body = '';
    for ($i = 0; $i < 1021; $i++) {
        $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"empty$i\"; filename=\"\"\r\n\r\n\r\n";
    }
    $body .= "--$boundary--\r\n";
    $bounded = request($probe, '', $body, 'multipart/form-data; boundary=' . $boundary);
    check($bounded && $bounded['files'] === 1020, 'Backported multipart limit bounds empty file controls');

    $beforeOom = request($probe);
    request($probe, 'oom=1');
    $afterOom = request($probe);
    check(!file_exists($directory . '/unsafe-shutdown'), 'OOM exits without invoking unsafe request shutdown');
    check($afterOom && $afterOom['pid'] !== $beforeOom['pid'], 'FPM replaces the exhausted worker and serves the next request');
} catch (Throwable $error) {
    $failed = true;
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    fwrite(STDERR, (string) @file_get_contents($directory . '/fpm.log'));
} finally {
    if (is_resource($process)) {
        proc_terminate($process);
        proc_close($process);
    }
    foreach (glob($directory . '/*') as $path) {
        unlink($path);
    }
    rmdir($directory);
}
exit($failed ? 1 : 0);
