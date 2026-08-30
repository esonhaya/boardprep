<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';

use App\Core\Autoloader;
use Tools\Doctor\DTO\CheckResult;
use Tools\Doctor\DTO\DoctorResult;
use Tools\Doctor\Output\JsonReportWriter;
use Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator;

Autoloader::register();

$root = dirname(__DIR__, 2);
$temporaryDirectory = sys_get_temp_dir()
    . '/boardprep-doctor-integrity-'
    . bin2hex(random_bytes(4));
$reportPath = $temporaryDirectory . '/nested/report.json';

$result = new DoctorResult();
$result->add(new CheckResult('Project pass', 'PASS'));
$result->add(new CheckResult('Project warning', 'WARNING'));
$result->add(new CheckResult(
    'Doctor self check',
    'FAIL',
    scope: 'DOCTOR'
));

(new JsonReportWriter())->write($result, $reportPath);

$contents = file_get_contents($reportPath);
$report = $contents === false ? null : json_decode($contents, true);

if (!is_array($report)) {
    throw new RuntimeException('Doctor writer did not publish valid JSON.');
}

if (($report['summary']['checks'] ?? null) !== 2) {
    throw new RuntimeException('Doctor project check count includes self checks.');
}

if (
    ($report['summary']['pass'] ?? null) !== 1
    || ($report['summary']['warning'] ?? null) !== 1
    || ($report['summary']['fail'] ?? null) !== 0
) {
    throw new RuntimeException('Doctor summary counts are inconsistent.');
}

if (
    !isset($report['generated_at'])
    || strtotime((string) $report['generated_at']) === false
) {
    throw new RuntimeException('Doctor report does not identify its run time.');
}

$dashboard = (new HttpSimulator($root . '/public/index.php'))
    ->request('GET', '/developer/doctor');

if ($dashboard['status'] !== 200 || $dashboard['exitCode'] !== 0) {
    throw new RuntimeException('Doctor dashboard production route failed.');
}

if (!str_contains($dashboard['output'], 'BoardPrep Doctor')) {
    throw new RuntimeException('Doctor dashboard did not render its production view.');
}

$api = (new HttpSimulator($root . '/public/api/doctor.php'))
    ->request('GET', '/api/doctor.php');
$apiReport = json_decode($api['output'], true);

if ($api['status'] !== 200 || !is_array($apiReport)) {
    throw new RuntimeException('Doctor API did not return the current valid report.');
}

if (($apiReport['summary']['checks'] ?? null) !== count($apiReport['checks'] ?? [])) {
    throw new RuntimeException('Doctor API report counts do not match its checks.');
}

$command = escapeshellarg(PHP_BINARY)
    . ' '
    . escapeshellarg($root . '/tools/doctor.php')
    . ' --invalid-option 2>&1';
$output = [];
$exitCode = 0;
exec($command, $output, $exitCode);

if ($exitCode !== 2 || !str_contains(implode("\n", $output), 'Unknown Doctor option')) {
    throw new RuntimeException('Doctor CLI accepted an invalid invocation.');
}

unlink($reportPath);
rmdir(dirname($reportPath));
rmdir($temporaryDirectory);

echo "[PASS] Doctor report, routes, API, and CLI integrity verified.\n";
