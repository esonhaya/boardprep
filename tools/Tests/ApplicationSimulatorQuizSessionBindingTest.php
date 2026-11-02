<?php

declare(strict_types=1);

use App\Core\Autoloader;
use Tools\Doctor\Project\BoardPrep\Simulation\ApplicationSimulator;

$root = dirname(__DIR__, 2);
$fixtureRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
    . '/boardprep-application-simulator-quiz-binding-'
    . bin2hex(random_bytes(6));
$storagePath = $fixtureRoot . '/storage';
$sessionPath = $fixtureRoot . '/sessions';

foreach ([$storagePath, $sessionPath] as $directory) {
    if (!mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create isolated simulator regression fixture.');
    }
}

putenv('APP_STORAGE_PATH=' . $storagePath);
putenv('APP_SESSION_PATH=' . $sessionPath);
putenv('DB_DRIVER=json');
$_ENV['APP_STORAGE_PATH'] = $storagePath;
$_ENV['APP_SESSION_PATH'] = $sessionPath;
$_ENV['DB_DRIVER'] = 'json';

require_once $root . '/app/Core/Autoloader.php';
Autoloader::register();

$entryPoint = $fixtureRoot . '/index.php';
$entryPointContents = <<<'PHP'
<?php

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    echo '<form><input type="hidden" name="quiz_session_id" value="rendered-quiz-session"></form>';
    return;
}

$postedSessionId = $_POST['quiz_session_id'] ?? 'missing';
echo 'received-quiz-session=' . (is_scalar($postedSessionId) ? (string) $postedSessionId : 'invalid');
PHP;

if (file_put_contents($entryPoint, $entryPointContents, LOCK_EX) === false) {
    throw new RuntimeException('Unable to create the isolated simulator entry point.');
}

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!is_dir($path)) {
        if (is_file($path) || is_link($path)) {
            unlink($path);
        }
        return;
    }

    $entries = scandir($path);
    if ($entries === false) {
        throw new RuntimeException('Unable to inspect the simulator regression fixture for cleanup.');
    }
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $removeTree($path . DIRECTORY_SEPARATOR . $entry);
    }
    rmdir($path);
};

try {
    $simulation = new ApplicationSimulator($entryPoint);
    $rendered = $simulation->get('/quiz')->execute()->context()->get('http');
    if (($rendered['status'] ?? null) !== 200
        || !str_contains((string) ($rendered['output'] ?? ''), 'rendered-quiz-session')) {
        throw new RuntimeException('The simulated browser did not receive the quiz session form token.');
    }

    $submitted = $simulation
        ->post('/quiz?action=submit', ['action' => 'submit'])
        ->execute()
        ->context()
        ->get('http');

    if (!str_contains((string) ($submitted['output'] ?? ''), 'received-quiz-session=rendered-quiz-session')) {
        throw new RuntimeException(sprintf(
            'The simulated browser did not preserve its rendered quiz session token across requests: %s',
            trim((string) ($submitted['output'] ?? '[empty response body]'))
        ));
    }

    echo "[PASS] ApplicationSimulator preserved the rendered quiz session token across form submission.\n";
} finally {
    $removeTree($fixtureRoot);
}
