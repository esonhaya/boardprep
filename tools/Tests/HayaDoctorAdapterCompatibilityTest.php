<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;
use Tools\Doctor\DTO\DoctorResult;
use Tools\Doctor\Output\JsonReportWriter;
use Tools\Doctor\Registry\CheckRegistry;

$root = dirname(__DIR__, 2);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$pinPath = $root . '/tools/HayaDoctor';
$assert(is_file($pinPath . '/tools/Doctor/Contracts/CheckInterface.php'), 'Pinned HayaDoctor CheckInterface is unavailable.');
$assert(interface_exists(CheckInterface::class)
    && class_exists(CheckResult::class)
    && class_exists(DoctorResult::class)
    && class_exists(CheckRegistry::class)
    && class_exists(JsonReportWriter::class), 'BoardPrep autoloading does not expose the pinned public Doctor API.');
$assert(str_contains((string) (new ReflectionClass(CheckInterface::class))->getFileName(), '/tools/HayaDoctor/'), 'BoardPrep resolved Doctor contracts outside the pinned submodule.');

$registry = new CheckRegistry();
$checks = $registry->fromDirectories([$root . '/tools/Doctor/Project/BoardPrep/Checks']);
$assert($checks !== [] && count(array_filter($checks, static fn(mixed $check): bool => $check instanceof CheckInterface)) === count($checks), 'BoardPrep project checks do not implement HayaDoctor CheckInterface.');
$assert(count(array_filter($checks, static fn(CheckInterface $check): bool => $check::class === 'Tools\\Doctor\\Project\\BoardPrep\\Checks\\EducationalIntegrityCheck')) === 1, 'HayaDoctor registry failed to discover the Batch 678 educational check.');

$result = new DoctorResult();
$result->add(new CheckResult('Adapter pass', 'PASS', 'fixture'));
$result->add(new CheckResult('Adapter warning', 'WARNING', 'fixture'));
$reportPath = sys_get_temp_dir() . '/boardprep-haya-adapter-' . bin2hex(random_bytes(6)) . '.json';
try {
    (new JsonReportWriter())->write($result, $reportPath);
    $report = json_decode((string) file_get_contents($reportPath), true, 512, JSON_THROW_ON_ERROR);
    $assert(($report['summary']['checks'] ?? null) === 2
        && ($report['summary']['pass'] ?? null) === 1
        && ($report['summary']['warning'] ?? null) === 1
        && count($report['checks'] ?? []) === 2, 'HayaDoctor report schema did not match BoardPrep dashboard expectations.');
    $requiredCheckFields = ['title', 'status', 'summary', 'details', 'recommendations', 'score', 'findings'];
    foreach ((array) ($report['checks'] ?? []) as $row) {
        $assert(is_array($row) && array_diff($requiredCheckFields, array_keys($row)) === [], 'Doctor report omitted a dashboard-consumed check field.');
    }
} finally {
    if (is_file($reportPath)) {
        unlink($reportPath);
    }
}

$runnerMethod = new ReflectionMethod(Tools\Doctor\Engine\DoctorRunner::class, 'run');
$assert($runnerMethod->getNumberOfRequiredParameters() === 0, 'BoardPrep no-argument DoctorRunner call contract changed.');
$assert(!is_file($pinPath . '/composer.json') && is_file($pinPath . '/tools/Doctor/Autoload.php'), 'Pinned adapter dependency boundary unexpectedly requires Composer package installation.');

$process = proc_open(
    [PHP_BINARY, $root . '/tools/doctor.php', '--batch678-invalid-option'],
    [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes,
    $root
);
$assert(is_resource($process), 'Unable to start the bounded Doctor CLI contract check.');
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exitCode = proc_close($process);
$assert($exitCode === 2 && str_contains((string) $stdout . (string) $stderr, 'Unknown Doctor option'), 'Doctor CLI invalid invocation did not return exit code 2.');

$routes = (string) file_get_contents($root . '/routes/web.php');
$dashboardController = (string) file_get_contents($root . '/app/Controllers/DoctorDashboardController.php');
$assert(str_contains($routes, 'DoctorDashboardController::class')
    && str_contains($routes, 'DoctorRunController::class')
    && str_contains($dashboardController, 'RepositoryHealthEvidenceService::currentReport()'), 'Developer Doctor dashboard is not wired to the BoardPrep adapter and current-health API.');

echo "[PASS] Pinned HayaDoctor public interfaces, registry discovery, and dashboard report schema are compatible.\n";
echo "[PASS] BoardPrep dependency loading and Doctor CLI exit code 2 are compatible with the adapter contract.\n";
