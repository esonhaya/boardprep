<?php

declare(strict_types=1);

$artifact = null;
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--artifact=')) {
        $artifact = substr($argument, 11);
    } else {
        fwrite(STDERR, "Usage: php tools/Tests/SharedHostingPublicHtmlSimulationTest.php --artifact=/path/to/v1.0.1.zip\n");
        exit(2);
    }
}
if ($artifact === null || !is_file($artifact) || !class_exists(ZipArchive::class)) {
    fwrite(STDERR, "A readable package and PHP Zip extension are required.\n");
    exit(2);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$removeTree = static function (string $directory) use (&$removeTree): void {
    if (!is_dir($directory)) return;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir() && !$item->isLink()) rmdir($item->getPathname());
        else unlink($item->getPathname());
    }
    rmdir($directory);
};

$copyTree = static function (string $source, string $destination) use (&$copyTree): void {
    if (!is_dir($destination) && !mkdir($destination, 0770, true) && !is_dir($destination)) {
        throw new RuntimeException('Unable to create public asset fixture.');
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    $sourceLength = strlen(rtrim($source, DIRECTORY_SEPARATOR)) + 1;
    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), $sourceLength);
        $target = $destination . DIRECTORY_SEPARATOR . $relative;
        if ($item->isLink()) throw new RuntimeException('Package assets unexpectedly contain a symlink.');
        if ($item->isDir()) {
            if (!is_dir($target) && !mkdir($target, 0770, true) && !is_dir($target)) {
                throw new RuntimeException('Unable to create public asset directory.');
            }
        } elseif (!copy($item->getPathname(), $target)) {
            throw new RuntimeException('Unable to copy a public asset.');
        }
    }
};

$run = static function (string $command, string $workingDirectory, array $environment): array {
    $prefix = 'env';
    foreach ($environment as $key => $value) {
        $prefix .= ' ' . escapeshellarg($key . '=' . (string) $value);
    }
    $process = proc_open($prefix . ' ' . $command, [
        0 => ['file', '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, $workingDirectory);
    if (!is_resource($process)) throw new RuntimeException('Unable to start a simulation command.');
    $stdout = stream_get_contents($pipes[1]) ?: '';
    $stderr = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);
    return ['status' => proc_close($process), 'stdout' => $stdout, 'stderr' => $stderr];
};

$request = static function (string $url, ?string $cookie = null): array {
    $headers = $cookie === null ? [] : ['Cookie: ' . $cookie];
    $context = stream_context_create(['http' => [
        'ignore_errors' => true,
        'timeout' => 30,
        'header' => implode("\r\n", $headers),
    ]]);
    $body = @file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];
    $status = 0;
    if (isset($responseHeaders[0]) && preg_match('/\s(\d{3})\s/', $responseHeaders[0], $match) === 1) {
        $status = (int) $match[1];
    }
    return ['status' => $status, 'body' => $body === false ? '' : $body, 'headers' => $responseHeaders];
};

$temporary = sys_get_temp_dir() . '/boardprep-public-html-' . bin2hex(random_bytes(6));
if (!mkdir($temporary, 0770, true)) {
    fwrite(STDERR, "Unable to create a temporary simulation directory.\n");
    exit(1);
}

$process = null;
try {
    $zip = new ZipArchive();
    $assert($zip->open($artifact) === true, 'package archive could not be opened');
    $firstName = (string) $zip->getNameIndex(0);
    $prefix = str_contains($firstName, '/') ? substr($firstName, 0, strpos($firstName, '/') + 1) : '';
    $manifest = json_decode((string) $zip->getFromName($prefix . 'RELEASE_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);
    $assert(is_array($manifest) && ($manifest['release_tag'] ?? '') === 'v1.0.1', 'package is not the v1.0.1 release');
    $assert($zip->extractTo($temporary), 'package extraction failed');
    $zip->close();

    $extractedRoot = $temporary . '/' . rtrim($prefix, '/');
    $privateRoot = $temporary . '/boardprep-private';
    $assert(is_dir($extractedRoot) && rename($extractedRoot, $privateRoot), 'private package fixture could not be prepared');
    $privateStorage = $temporary . '/boardprep-state';
    $copyTree($privateRoot . '/storage', $privateStorage);
    $webRoot = $temporary . '/public_html';
    $assert(mkdir($webRoot, 0770), 'public_html fixture could not be created');
    $copyTree($privateRoot . '/public/assets', $webRoot . '/assets');

    $shim = <<<'PHP'
<?php
declare(strict_types=1);
$privateRoot = realpath(__DIR__ . '/../boardprep-private');
if ($privateRoot === false || !is_file($privateRoot . '/public/index.php')) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Service unavailable.');
}
require $privateRoot . '/public/index.php';
PHP;
    $healthShim = <<<'PHP'
<?php
declare(strict_types=1);
$privateRoot = realpath(__DIR__ . '/../boardprep-private');
if ($privateRoot === false || !is_file($privateRoot . '/public/health.php')) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Service unavailable.');
}
require $privateRoot . '/public/health.php';
PHP;
    $assert(file_put_contents($webRoot . '/index.php', $shim) !== false, 'front-controller shim could not be written');
    $assert(file_put_contents($webRoot . '/health.php', $healthShim) !== false, 'health shim could not be written');
    $assert(file_put_contents($webRoot . '/.htaccess', "Options -Indexes\nRewriteEngine On\nRewriteCond %{REQUEST_FILENAME} -f [OR]\nRewriteCond %{REQUEST_FILENAME} -d\nRewriteRule ^ - [L]\nRewriteRule ^ index.php [QSA,L]\n") !== false, 'Apache routing fixture could not be written');

    $routerPath = $temporary . '/public-html-router.php';
    $router = '<?php $docroot = ' . var_export($webRoot, true) . '; '
        . '$path = parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH); '
        . '$candidate = $docroot . (is_string($path) ? $path : ""); '
        . 'if ($path !== "/" && is_file($candidate)) return false; '
        . 'require $docroot . "/index.php";';
    $assert(file_put_contents($routerPath, $router) !== false, 'local rewrite simulator could not be written');

    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorNumber, $errorMessage);
    $assert(is_resource($socket), 'unable to reserve a local HTTP port');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $port = (int) substr(strrchr((string) $address, ':'), 1);
    $environment = [
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
        'APP_TIMEZONE' => 'UTC',
        'APP_MEMORY_LIMIT' => '512M',
        'DB_DRIVER' => 'json',
        'APP_STORAGE_PATH' => $privateStorage,
        'APP_PUBLIC_PATH' => $webRoot,
    ];
    $serverLog = $temporary . '/server.log';
    $process = proc_open([
        PHP_BINARY,
        '-d',
        'display_errors=0',
        '-S',
        '127.0.0.1:' . $port,
        '-t',
        $webRoot,
        $routerPath,
    ], [
        0 => ['file', '/dev/null', 'r'],
        1 => ['file', $serverLog, 'a'],
        2 => ['file', $serverLog, 'a'],
    ], $pipes, $temporary, array_merge(getenv(), $environment));
    $assert(is_resource($process), 'local HTTP simulation did not start');
    $base = 'http://127.0.0.1:' . $port;
    $ready = false;
    for ($attempt = 0; $attempt < 30; $attempt++) {
        if ($request($base . '/health.php')['status'] > 0) {
            $ready = true;
            break;
        }
        usleep(100000);
    }
    $assert($ready, 'local HTTP simulation did not become ready: ' . (string) @file_get_contents($serverLog));

    $home = $request($base . '/');
    $dashboard = $request($base . '/dashboard');
    $study = $request($base . '/study');
    $asset = $request($base . '/assets/css/style.css');
    $serverState = proc_get_status($process);
    $assert(
        $home['status'] === 200,
        'shim home route returned HTTP ' . $home['status'] . ': ' . substr($home['body'], 0, 160)
        . ' server_running=' . (($serverState['running'] ?? false) ? 'yes' : 'no')
        . ' log=' . substr((string) @file_get_contents($serverLog), 0, 1000)
    );
    $assert($dashboard['status'] === 200 && $study['status'] === 200, 'learner routes did not return HTTP 200');
    $assert($asset['status'] === 200 && trim($asset['body']) !== '', 'copied public asset was not served');

    $cookie = '';
    foreach ($home['headers'] as $header) {
        if (preg_match('/^Set-Cookie:\s*PHPSESSID=([^;]+)/i', $header, $match) === 1) {
            $cookie = 'PHPSESSID=' . $match[1];
            if (stripos($header, 'httponly') === false || stripos($header, 'samesite=lax') === false) {
                throw new RuntimeException('session cookie safeguards are missing');
            }
            break;
        }
    }
    $assert($cookie !== '', 'session cookie was not issued');
    $assert($request($base . '/dashboard', $cookie)['status'] === 200, 'session cookie was not accepted on a follow-up request');

    $health = $request($base . '/health.php');
    $healthJson = json_decode($health['body'], true);
    $assert($health['status'] === 200 && is_array($healthJson), 'health shim did not return safe JSON');
    $assert(($healthJson['product_version'] ?? '') === '1.0.1', 'health identity version is incorrect');
    $assert(($healthJson['build_commit'] ?? '') === ($manifest['build_commit'] ?? ''), 'health identity commit does not match the package');
    $assert(!isset($healthJson['storage_path'], $healthJson['filesystem_path'], $healthJson['error']), 'health output includes internal details');

    foreach (['/.env', '/storage/questions.json', '/config/app.php', '/tools/doctor.php', '/RELEASE_MANIFEST.json'] as $path) {
        $response = $request($base . $path);
        $assert($response['status'] === 404, 'sensitive path was not hidden: ' . $path);
        $assert(!str_contains($response['body'], $privateRoot), 'response disclosed the private application path');
    }
    $assert($request($base . '/developer')['status'] === 404, 'production developer route is publicly available');
    $assert($request($base . '/api/doctor.php')['status'] === 404, 'production Doctor API is publicly available');
    $assert($request($base . '/login')['status'] === 404, 'unexpected production login route exists');

    $preflight = $run(
        escapeshellarg(PHP_BINARY) . ' -d display_errors=0 tools/doctor.php --preflight --json',
        $privateRoot,
        $environment
    );
    $preflightJson = json_decode(trim($preflight['stdout']), true);
    $assert($preflight['status'] === 0 && ($preflightJson['status'] ?? '') === 'PASS', 'private storage preflight did not pass');

    $sourceRoot = dirname(__DIR__, 2);
    $updatedPreflightCommand = escapeshellarg(PHP_BINARY) . ' -d display_errors=0 '
        . escapeshellarg($sourceRoot . '/tools/doctor.php') . ' --preflight --root='
        . escapeshellarg($privateRoot) . ' --json';
    $updatedPreflight = $run($updatedPreflightCommand, $sourceRoot, $environment);
    $updatedPreflightJson = json_decode(trim($updatedPreflight['stdout']), true);
    $assert($updatedPreflight['status'] === 0 && ($updatedPreflightJson['status'] ?? '') === 'PASS', 'Batch 661 preflight did not verify the untouched package');
    $assert(
        ($updatedPreflightJson['checks']['PUBLIC_DATA_ISOLATION']['status'] ?? '') === 'PASS',
        'Batch 661 preflight did not verify the separate public_html root'
    );
    $exceptionScript = 'require ' . var_export($privateRoot . '/app/Core/Autoloader.php', true)
        . '; \\App\\Core\\Autoloader::register(); (new \\App\\Core\\ExceptionHandler())->handle('
        . 'new \\RuntimeException("secret-canary ' . addslashes($privateRoot) . '"));';
    $exceptionResponse = $run(
        escapeshellarg(PHP_BINARY) . ' -d display_errors=0 -r ' . escapeshellarg($exceptionScript),
        $privateRoot,
        $environment
    );
    $assert(
        str_contains($exceptionResponse['stdout'], 'Something went wrong.')
        && !str_contains($exceptionResponse['stdout'], 'secret-canary')
        && !str_contains($exceptionResponse['stdout'], $privateRoot),
        'production exception response disclosed internal details'
    );

    $publicFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($webRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($publicFiles as $file) {
        $assert(!$file->isLink(), 'public_html fixture requires symlink support');
    }

    proc_terminate($process);
    proc_close($process);
    $process = null;

    $publicRoot = $privateRoot . '/public';
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorNumber, $errorMessage);
    $assert(is_resource($socket), 'unable to reserve a direct-public-root HTTP port');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $port = (int) substr(strrchr((string) $address, ':'), 1);
    $directEnvironment = array_merge($environment, ['APP_PUBLIC_PATH' => $publicRoot]);
    $process = proc_open([
        PHP_BINARY,
        '-d',
        'display_errors=0',
        '-S',
        '127.0.0.1:' . $port,
        '-t',
        $publicRoot,
        $publicRoot . '/router.php',
    ], [
        0 => ['file', '/dev/null', 'r'],
        1 => ['file', $serverLog, 'a'],
        2 => ['file', $serverLog, 'a'],
    ], $pipes, $temporary, array_merge(getenv(), $directEnvironment));
    $assert(is_resource($process), 'direct public-root HTTP simulation did not start');
    $directBase = 'http://127.0.0.1:' . $port;
    $ready = false;
    for ($attempt = 0; $attempt < 30; $attempt++) {
        if ($request($directBase . '/health.php')['status'] > 0) {
            $ready = true;
            break;
        }
        usleep(100000);
    }
    $assert($ready, 'direct public-root HTTP simulation did not become ready');

    $directHome = $request($directBase . '/');
    $directStudy = $request($directBase . '/study');
    $directAsset = $request($directBase . '/assets/css/style.css');
    $assert($directHome['status'] === 200 && $directStudy['status'] === 200, 'configured public document root did not route learner pages');
    $assert($directAsset['status'] === 200 && trim($directAsset['body']) !== '', 'configured public document root did not serve a public asset');
    $directCookie = '';
    foreach ($directHome['headers'] as $header) {
        if (preg_match('/^Set-Cookie:\s*PHPSESSID=([^;]+)/i', $header, $match) === 1) {
            $directCookie = 'PHPSESSID=' . $match[1];
            if (stripos($header, 'httponly') === false || stripos($header, 'samesite=lax') === false) {
                throw new RuntimeException('direct-root session cookie safeguards are missing');
            }
            break;
        }
    }
    $assert($directCookie !== '' && $request($directBase . '/dashboard', $directCookie)['status'] === 200, 'direct-root session did not persist across requests');
    $directHealth = $request($directBase . '/health.php');
    $directHealthJson = json_decode($directHealth['body'], true);
    $assert($directHealth['status'] === 200 && ($directHealthJson['product_version'] ?? '') === '1.0.1', 'direct-root health identity did not match v1.0.1');
    foreach (['/.env', '/storage/questions.json', '/config/app.php', '/tools/doctor.php', '/RELEASE_MANIFEST.json', '/developer', '/api/doctor.php'] as $path) {
        $response = $request($directBase . $path);
        $assert($response['status'] === 404, 'direct public root exposed a sensitive path: ' . $path);
        $assert(!str_contains($response['body'], $privateRoot), 'direct-root response disclosed the private application path');
    }

    proc_terminate($process);
    proc_close($process);
    $process = null;

    echo "public_root_layout=PASS routes=PASS storage=PASS health_identity=PASS session_cookie=PASS sensitive_paths=PASS; public_html_layout=PASS routes=PASS storage=PASS health_identity=PASS session_cookie=PASS sensitive_paths=PASS symlinks=NOT_REQUIRED package_preflight=PASS postrelease_root_preflight=PASS apache=NOT_VERIFIED\n";
} catch (Throwable $exception) {
    fwrite(STDERR, '[FAIL] ' . $exception->getMessage() . PHP_EOL);
    $status = 1;
} finally {
    if (is_resource($process)) {
        proc_terminate($process);
        proc_close($process);
    }
    $removeTree($temporary);
}

if (isset($status) && $status !== 0) exit($status);
