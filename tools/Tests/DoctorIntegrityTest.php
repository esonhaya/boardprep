<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';

use App\Core\Autoloader;
use Tools\Doctor\DTO\CheckResult;
use Tools\Doctor\DTO\DoctorResult;
use Tools\Doctor\Output\JsonReportWriter;
use Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator;
use App\Services\RepositoryHealth\Engine\RepositoryHealthEvidenceService;

Autoloader::register();

$root = dirname(__DIR__, 2);
$protectedState = static function () use ($root): array {
    $paths = [
        'storage/questions.json',
        'storage/attempts.json',
        'storage/weakness.json',
        'storage/question-statistics.json',
    ];
    foreach (glob($root . '/storage/doctor/simulation-sessions/*') ?: [] as $sessionPath) {
        if (is_file($sessionPath)) {
            $paths[] = substr($sessionPath, strlen($root) + 1);
        }
    }
    sort($paths, SORT_STRING);
    $hashes = [];
    foreach ($paths as $relativePath) {
        $path = $root . '/' . $relativePath;
        $hashes[$relativePath] = is_file($path) ? hash_file('sha256', $path) : null;
    }
    return $hashes;
};
$protectedStateBefore = $protectedState();

$result = new DoctorResult();
$result->add(new CheckResult('Project pass', 'PASS'));
$result->add(new CheckResult('Project warning', 'WARNING'));
$result->add(new CheckResult(
    'Doctor self check',
    'FAIL',
    scope: 'DOCTOR'
));

$dashboardSimulator = new HttpSimulator($root . '/public/index.php');
$reportPath = $dashboardSimulator->storagePath() . '/doctor-report.json';
(new JsonReportWriter())->write($result, $reportPath);
$reportContents = file_get_contents($reportPath);
$savedReport = $reportContents === false ? null : json_decode($reportContents, true);
if (!is_array($savedReport)) {
    throw new RuntimeException('Doctor report fixture was not valid JSON.');
}
$savedReport = RepositoryHealthEvidenceService::preparePersistedSnapshot($savedReport, $root);
file_put_contents($reportPath, json_encode($savedReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL, LOCK_EX);

$staleSnapshotPath = $dashboardSimulator->storagePath() . '/repository-health.json';
$staleHealthSnapshot = [
    'generated_at' => '2020-01-01T00:00:00+00:00',
    'overall_status' => 'PASS',
    'summary' => ['pass_count' => 1, 'warn_count' => 0, 'fail_count' => 0],
];
if (file_put_contents(
    $staleSnapshotPath,
    json_encode($staleHealthSnapshot, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL,
    LOCK_EX
) === false) {
    throw new RuntimeException('The deterministic stale health snapshot regression fixture could not be prepared.');
}

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

$dashboard = $dashboardSimulator->request('GET', '/developer/doctor');

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

$healthApi = (new HttpSimulator($root . '/public/api/doctor.php'))
    ->request('GET', '/api/doctor.php', ['health' => '1']);
$healthReport = json_decode($healthApi['output'], true);

if (
    $healthApi['status'] !== 200
    || !is_array($healthReport)
    || !isset($healthReport['summary']['overall_status'])
    || !isset($healthReport['action_queue'])
    || ($healthReport['evidence_state'] ?? '') !== RepositoryHealthEvidenceService::CURRENT
    || ($healthReport['cached_evidence']['state'] ?? '') !== RepositoryHealthEvidenceService::STALE
) {
    throw new RuntimeException('Repository Health API did not return fresh evidence with the persisted stale report classified separately.');
}
$healthIssues = (array) ($healthReport['issues'] ?? []);
$healthQueue = (array) ($healthReport['action_queue'] ?? []);
$issueCountsByStatus = [];
foreach ($healthQueue as $issue) {
    if (!is_array($issue)) continue;
    $status = strtoupper((string) ($issue['status'] ?? 'UNKNOWN'));
    $issueCountsByStatus[$status] = ($issueCountsByStatus[$status] ?? 0) + 1;
}
$healthSummary = (array) ($healthReport['summary'] ?? []);
if (
    count($healthIssues) !== (int) ($healthSummary['issue_count'] ?? -1)
    || count($healthQueue) !== count($healthIssues)
    || ($issueCountsByStatus['WARN'] ?? 0) !== (int) ($healthSummary['warn_entity_count'] ?? -1)
    || ($issueCountsByStatus['FAIL'] ?? 0) !== (int) ($healthSummary['fail_entity_count'] ?? -1)
    || ($issueCountsByStatus['REVIEW'] ?? 0) !== (int) ($healthSummary['review_entity_count'] ?? -1)
) {
    throw new RuntimeException('Current Repository Health summaries do not reconcile with the underlying findings and action queue.');
}

$filteredIssue = null;
foreach ($healthQueue as $issue) {
    if (
        is_array($issue)
        && strtoupper((string) ($issue['status'] ?? '')) === 'REVIEW'
        && strtoupper((string) ($issue['category'] ?? '')) === 'TAXONOMY'
        && trim((string) ($issue['code'] ?? '')) !== ''
    ) {
        $filteredIssue = $issue;
        break;
    }
}
if ($filteredIssue === null) {
    foreach ($healthQueue as $issue) {
        if (is_array($issue) && trim((string) ($issue['code'] ?? '')) !== '') {
            $filteredIssue = $issue;
            break;
        }
    }
}
if (!is_array($filteredIssue)) {
    throw new RuntimeException('Fresh Repository Health evidence did not contain an issue to exercise dashboard filters.');
}
$filteredDashboard = $dashboardSimulator->request('GET', '/developer/doctor', [
    'severity' => (string) ($filteredIssue['status'] ?? ''),
    'category' => (string) ($filteredIssue['category'] ?? ''),
]);

if (
    $filteredDashboard['status'] !== 200
    || !str_contains($filteredDashboard['output'], 'Repository Health Center')
    || !str_contains($filteredDashboard['output'], (string) $filteredIssue['code'])
) {
    throw new RuntimeException('Repository Health Center filters did not render a finding from current evidence.');
}

$currentDoctorSnapshot = file_get_contents($reportPath);
if ($currentDoctorSnapshot === false) {
    throw new RuntimeException('Unable to retain the current Doctor report fixture.');
}
$legacyReport = [
    'generated_at' => date(DATE_ATOM),
    'health' => 100,
    'summary' => ['checks' => 1, 'pass' => 1, 'warning' => 0, 'fail' => 0],
    'checks' => [],
];
file_put_contents($reportPath, json_encode($legacyReport, JSON_THROW_ON_ERROR) . PHP_EOL, LOCK_EX);
$staleDoctorApi = (new HttpSimulator($root . '/public/api/doctor.php'))
    ->request('GET', '/api/doctor.php');
$staleDoctorApiReport = json_decode($staleDoctorApi['output'], true);
if (
    $staleDoctorApi['status'] !== 409
    || !is_array($staleDoctorApiReport)
    || ($staleDoctorApiReport['evidence_state'] ?? '') !== RepositoryHealthEvidenceService::STALE
) {
    throw new RuntimeException('A stale Doctor report was allowed to appear as a passing API result.');
}

$staleDashboard = $dashboardSimulator->request('GET', '/developer/doctor');
if (
    $staleDashboard['status'] !== 200
    || !str_contains($staleDashboard['output'], 'Persisted static Doctor report: STALE_EVIDENCE')
    || !str_contains($staleDashboard['output'], '--%</div>')
) {
    throw new RuntimeException('The Doctor dashboard displayed a score from a stale static report.');
}

$releasePath = $dashboardSimulator->storagePath() . '/release-readiness.json';
file_put_contents($releasePath, json_encode($legacyReport, JSON_THROW_ON_ERROR) . PHP_EOL, LOCK_EX);
$staleReleaseApi = (new HttpSimulator($root . '/public/api/doctor.php'))
    ->request('GET', '/api/doctor.php', ['release' => '1']);
$staleReleaseReport = json_decode($staleReleaseApi['output'], true);
if (
    $staleReleaseApi['status'] !== 409
    || !is_array($staleReleaseReport)
    || ($staleReleaseReport['evidence_state'] ?? '') !== RepositoryHealthEvidenceService::STALE
) {
    throw new RuntimeException('A stale release report was returned as a current release decision.');
}

file_put_contents($reportPath, $currentDoctorSnapshot, LOCK_EX);
unlink($releasePath);

$invalidRefresh = $dashboardSimulator
    ->request('POST', '/developer/doctor/run', [], ['csrf_token' => 'invalid-batch640-token']);

if ($invalidRefresh['status'] !== 419) {
    throw new RuntimeException('Developer health refresh did not enforce CSRF protection.');
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

if ($protectedStateBefore !== $protectedState()) {
    throw new RuntimeException('Doctor simulation changed real learner state or historical sessions.');
}

echo "[PASS] Doctor report, routes, API, and CLI integrity verified.\n";
