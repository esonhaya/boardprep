<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\Operations\BrowserEvidenceService;

$root = dirname(__DIR__);
$runId = $argv[1] ?? '';
if (!preg_match('/\A[1-9][0-9]{0,19}\z/', $runId)) {
    fwrite(STDERR, "Usage: php tools/register-browser-evidence.php <github-actions-run-id>\n");
    exit(2);
}

$apiRoot = 'https://api.github.com/repos/' . BrowserEvidenceService::REPOSITORY . '/actions/runs/' . $runId;
$headers = [
    'Accept: application/vnd.github+json',
    'User-Agent: BoardPrep-browser-evidence',
    'X-GitHub-Api-Version: 2022-11-28',
];
[$runBody, $runHttpStatus, $runRequestError] = githubApiGet($apiRoot, $headers);
if (!is_string($runBody) || $runHttpStatus !== 200) {
    fwrite(STDERR, "Could not verify GitHub workflow run {$runId}; API HTTP status {$runHttpStatus}. {$runRequestError}\n");
    exit(2);
}
$run = json_decode($runBody, true);
if (!is_array($run)
    || (string) ($run['id'] ?? '') !== $runId
    || ($run['path'] ?? '') !== BrowserEvidenceService::WORKFLOW_PATH) {
    fwrite(STDERR, "Run {$runId} does not identify the BoardPrep Chromium acceptance workflow.\n");
    exit(2);
}

$headSha = strtolower((string) ($run['head_sha'] ?? ''));
$commitCheck = [];
$commitCheckStatus = 1;
if (preg_match('/\A[0-9a-f]{40}\z/', $headSha)) {
    exec('git -C ' . escapeshellarg($root) . ' cat-file -e ' . escapeshellarg($headSha . '^{commit}') . ' 2>/dev/null', $commitCheck, $commitCheckStatus);
}
if (!preg_match('/\A[0-9a-f]{40}\z/', $headSha) || $commitCheckStatus !== 0) {
    fwrite(STDERR, "Workflow commit {$headSha} is not available as a commit in this checkout.\n");
    exit(2);
}

$jobsUrl = $apiRoot . '/jobs?per_page=100';
$headers = [
    'Accept: application/vnd.github+json',
    'User-Agent: BoardPrep-browser-evidence',
    'X-GitHub-Api-Version: 2022-11-28',
];
[$jobsBody, $jobsHttpStatus, $jobsRequestError] = githubApiGet($jobsUrl, $headers);
if (!is_string($jobsBody) || $jobsHttpStatus !== 200) {
    fwrite(STDERR, "Could not verify GitHub workflow jobs for run {$runId}; API HTTP status {$jobsHttpStatus}. {$jobsRequestError}\n");
    exit(2);
}
$jobsResponse = json_decode($jobsBody, true);
$jobs = [];
foreach ((array) ($jobsResponse['jobs'] ?? []) as $job) {
    if (!is_array($job)) {
        continue;
    }
    $jobs[] = [
        'id' => (int) ($job['id'] ?? 0),
        'name' => (string) ($job['name'] ?? ''),
        'status' => (string) ($job['status'] ?? ''),
        'conclusion' => (string) ($job['conclusion'] ?? ''),
        'started_at' => (string) ($job['started_at'] ?? ''),
        'completed_at' => (string) ($job['completed_at'] ?? ''),
    ];
}

$record = [
    'run_id' => (int) $run['id'],
    'run_attempt' => max(1, (int) ($run['run_attempt'] ?? 1)),
    'repository' => BrowserEvidenceService::REPOSITORY,
    'workflow_id' => (int) ($run['workflow_id'] ?? 0),
    'workflow_path' => (string) $run['path'],
    'workflow_name' => (string) ($run['name'] ?? 'Browser acceptance'),
    'event' => (string) ($run['event'] ?? ''),
    'branch' => (string) ($run['head_branch'] ?? ''),
    'head_sha' => $headSha,
    'run_status' => (string) ($run['status'] ?? ''),
    'workflow_conclusion' => (string) ($run['conclusion'] ?? ''),
    'workflow_created_at' => (string) ($run['created_at'] ?? ''),
    'workflow_completed_at' => (string) ($run['updated_at'] ?? ''),
    'run_url' => (string) ($run['html_url'] ?? ''),
    'jobs' => $jobs,
    'verification_status' => 'VERIFIED',
    'verification_source' => 'GITHUB_ACTIONS_API',
    'verification_endpoint' => $apiRoot,
    'api_response_sha256' => hash('sha256', $runBody . "\0" . $jobsBody),
    'registered_at' => gmdate('c'),
];
$validated = BrowserEvidenceService::validateRecord($record);
if (!$validated['valid']) {
    fwrite(STDERR, "Workflow run was not registered: {$validated['reason']}\n");
    exit(2);
}

$path = $root . '/docs/release-evidence/browser-workflows.json';
$directory = dirname($path);
if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
    fwrite(STDERR, "Could not create the browser evidence directory.\n");
    exit(2);
}
$registry = ['schema_version' => 1, 'records' => []];
if (is_file($path)) {
    try {
        $existing = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($existing) || (int) ($existing['schema_version'] ?? 0) !== 1 || !is_array($existing['records'] ?? null)) {
            throw new RuntimeException('unsupported register schema');
        }
        $registry = $existing;
    } catch (Throwable $error) {
        fwrite(STDERR, "Browser workflow register is invalid; preserved existing file: {$error->getMessage()}\n");
        exit(2);
    }
}
$records = array_values(array_filter($registry['records'], 'is_array'));
$found = false;
foreach ($records as $index => $existing) {
    if ((int) ($existing['run_id'] ?? 0) === $record['run_id']
        && (int) ($existing['run_attempt'] ?? 0) === $record['run_attempt']) {
        $records[$index] = $record;
        $found = true;
        break;
    }
}
if (!$found) {
    $records[] = $record;
}
$registry = ['schema_version' => 1, 'records' => $records];
$json = json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
$temporary = tempnam($directory, '.browser-workflows-');
if (!is_string($temporary) || file_put_contents($temporary, $json, LOCK_EX) !== strlen($json) || !rename($temporary, $path)) {
    if (is_string($temporary) && is_file($temporary)) {
        unlink($temporary);
    }
    fwrite(STDERR, "Could not safely write the browser workflow register.\n");
    exit(2);
}

echo '[REGISTERED] run=' . $record['run_id']
    . ' commit=' . $record['head_sha']
    . ' conclusion=' . $record['workflow_conclusion']
    . ' jobs=' . count($jobs)
    . ' status=' . ($validated['passed'] ? 'VERIFIED_PASS' : 'VERIFIED_NOT_PASS') . PHP_EOL;
exit($validated['passed'] ? 0 : 1);

/** @param list<string> $headers @return array{0:?string,1:int,2:string} */
function githubApiGet(string $url, array $headers): array
{
    if (extension_loaded('curl') && function_exists('curl_init')) {
        $handle = curl_init($url);
        if ($handle === false) {
            return [null, 0, 'Unable to initialize the PHP cURL client.'];
        }
        $configured = curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => 'BoardPrep-browser-evidence',
        ]);
        if (!$configured) {
            return [null, 0, 'Unable to configure the PHP cURL client.'];
        }
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = is_string($body) ? '' : curl_error($handle);
        return [is_string($body) ? $body : null, $status, $error];
    }

    $context = stream_context_create(['http' => [
        'method' => 'GET',
        'timeout' => 20,
        'ignore_errors' => true,
        'header' => implode("\r\n", $headers) . "\r\n",
    ]]);
    $body = @file_get_contents($url, false, $context);
    $status = selfHttpStatus($http_response_header ?? []);
    $lastError = error_get_last();
    $error = is_string($body) || !is_array($lastError)
        ? ''
        : (string) ($lastError['message'] ?? 'HTTP stream request failed.');
    return [is_string($body) ? $body : null, $status, $error];
}

function selfHttpStatus(array $headers): int
{
    foreach ($headers as $header) {
        if (preg_match('/\AHTTP\/\S+\s+(\d{3})\b/', (string) $header, $match)) {
            return (int) $match[1];
        }
    }
    return 0;
}
