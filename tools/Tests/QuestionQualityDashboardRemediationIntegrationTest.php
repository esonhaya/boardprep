<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
App\Core\Autoloader::register();

use App\Services\Question\QuestionQualityService;
use App\Services\RepositoryHealth\SourceUrlHealthSnapshot;

$root = dirname(__DIR__, 2);
$fail = static function (string $message): never {
    fwrite(STDERR, 'QUESTION_QUALITY_DASHBOARD_REMEDIATION_FAIL=' . $message . PHP_EOL);
    exit(1);
};
$protected = [
    'storage/questions.json' => hash_file('sha256', $root . '/storage/questions.json'),
    'storage/attempts.json' => hash_file('sha256', $root . '/storage/attempts.json'),
    'storage/question-statistics.json' => hash_file('sha256', $root . '/storage/question-statistics.json'),
];

$sources = json_decode((string) file_get_contents($root . '/storage/sources.json'), true, 512, JSON_THROW_ON_ERROR);
$snapshots = json_decode((string) file_get_contents($root . '/storage/source-url-health.json'), true, 512, JSON_THROW_ON_ERROR);
$snapshot = $snapshots[0] ?? [];
$snapshotStatusCounts = [];
foreach ((array) ($snapshot['records'] ?? []) as $record) {
    $status = (string) ($record['status'] ?? '');
    $snapshotStatusCounts[$status] = ($snapshotStatusCounts[$status] ?? 0) + 1;
}
$affected = SourceUrlHealthSnapshot::affectedQuestionsBySource(
    json_decode((string) file_get_contents($root . '/storage/questions.json'), true, 512, JSON_THROW_ON_ERROR),
    json_decode((string) file_get_contents($root . '/storage/study-question-links.json'), true, 512, JSON_THROW_ON_ERROR),
    json_decode((string) file_get_contents($root . '/storage/study-materials.json'), true, 512, JSON_THROW_ON_ERROR)
);
$snapshotInspection = SourceUrlHealthSnapshot::inspect($snapshots, $sources, new DateTimeImmutable('now'), $affected);

$result = QuestionQualityService::analyze();
$issues = (array) ($result['issues'] ?? []);
$byCode = [];
$findingsByCode = [];
foreach ($issues as $issue) {
    $code = (string) ($issue->code ?? '');
    $byCode[$code] = ($byCode[$code] ?? 0) + 1;
    $findingsByCode[$code][] = $issue;
}
$assertCount = static function (string $code, int $expected) use ($byCode, $fail): void {
    if (($byCode[$code] ?? 0) !== $expected) $fail($code . ' expected ' . $expected . ', found ' . ($byCode[$code] ?? 0));
};
$assertCount('INVALID_QUESTION_SOURCE_REFERENCE', 0);
$assertCount('SOURCE_URL_CONFIRMED_404', (int) ($snapshotStatusCounts['CONFIRMED_404'] ?? 0));
$assertCount('SOURCE_URL_ACCESS_RESTRICTED', (int) ($snapshotStatusCounts['ACCESS_RESTRICTED'] ?? 0));
$assertCount('SOURCE_TEXT_ACCESS_LIMITED', 6);
$inconclusiveCount = 0;
foreach ($snapshotStatusCounts as $status => $count) {
    if (!in_array($status, ['HTTP_REACHABLE', 'CONFIRMED_404', 'ACCESS_RESTRICTED'], true)) $inconclusiveCount += $count;
}
$assertCount('SOURCE_URL_CHECK_INCONCLUSIVE', $inconclusiveCount);
$assertCount('SOURCE_URL_AUDIT_PARTIAL', ($snapshot['coverage']['complete'] ?? false) === true ? 0 : 1);

$deadIds = [];
foreach ($findingsByCode['SOURCE_URL_CONFIRMED_404'] ?? [] as $issue) {
    $deadIds[] = (string) ($issue->entityId ?? '');
    if (($issue->status ?? '') !== 'REVIEW' || (int) ($issue->evidence['affected_question_count'] ?? -1) !== 0) {
        $fail('An unreferenced confirmed-404 record was not classified as zero-impact review evidence.');
    }
}

$ifrsFinding = null;
foreach ($findingsByCode['SOURCE_URL_CHECK_INCONCLUSIVE'] ?? [] as $issue) {
    if (($issue->entityId ?? '') === 'ifrs-ias-21-foreign-exchange') $ifrsFinding = $issue;
}
if (is_object($ifrsFinding)) {
    $fail('The one authorized IAS 21 retry returned current evidence but the dashboard still shows its prior DNS observation.');
}
if (($snapshotStatusCounts['HTTP_REACHABLE'] ?? 0) < 1
    || (($snapshot['coverage']['complete'] ?? false) !== true && $snapshotInspection['status'] === 'CURRENT_COMPLETE')) {
    $fail('The current complete URL snapshot did not reconcile its reachable and coverage status.');
}
sort($deadIds);
if ($deadIds !== ['aasc-psa-500-currentness-review', 'deped-matatag-general-education-framework', 'prc-lept-program-march-2026']) {
    $fail('The remaining confirmed-404 records do not match the three unreferenced source records.');
}
foreach ($findingsByCode['SOURCE_URL_ACCESS_RESTRICTED'] ?? [] as $issue) {
    if (($issue->status ?? '') !== 'WARN') $fail('HTTP access restriction must remain a nonfatal warning.');
}
foreach ($findingsByCode['SOURCE_TEXT_ACCESS_LIMITED'] ?? [] as $issue) {
    if (($issue->status ?? '') !== 'WARN'
        || ($issue->evidence['source_text_verified'] ?? true) !== false
        || ($issue->evidence['human_review_status'] ?? '') !== 'REVIEW_REQUIRED') {
        $fail('Metadata-only/full-text purchase limitation was not distinguished from URL reachability and review.');
    }
}

$report = $result['report'] ?? null;
if (!is_object($report)) $fail('The production question-quality report was not returned.');
$urlAudit = (array) ($report->summary['source_url_audit'] ?? []);
if (($urlAudit['status'] ?? '') !== ($snapshotInspection['status'] ?? '')
    || (int) ($urlAudit['checked_source_count'] ?? 0) !== count((array) ($snapshot['records'] ?? []))
    || (int) ($urlAudit['total_url_source_count'] ?? 0) !== count(array_filter($sources, static fn(array $source): bool => is_string($source['url'] ?? null) && trim($source['url']) !== ''))
    || (int) ($urlAudit['unchecked_source_count'] ?? -1) !== 0) {
    $fail('The production dashboard did not display the validated current URL snapshot accurately.');
}
$relationships = (array) ($report->summary['study_relationships'] ?? []);
if (($relationships['exact_section_link_rows'] ?? 0) !== 1049
    || ($relationships['broken_or_unresolved_section_link_rows'] ?? -1) !== 0
    || ($relationships['exact_links_pending_or_approval_evidence_missing'] ?? 0) !== 91
    || ($relationships['exact_links_unreviewed_or_status_unknown'] ?? 0) !== 958
    || ($relationships['exact_links_approved_with_explicit_evidence'] ?? 0) !== 0) {
    $fail('Exact mapping totals or unknown academic review status were conflated.');
}
$severity = (array) ($result['severitySummary'] ?? []);
$severityByStatus = ['FAIL' => 0, 'WARN' => 0, 'REVIEW' => 0, 'INFO' => 0];
foreach ($issues as $issue) {
    $status = strtoupper((string) ($issue->status ?? ''));
    if (array_key_exists($status, $severityByStatus)) $severityByStatus[$status]++;
}
if (($severity['error'] ?? -1) !== $severityByStatus['FAIL']
    || ($severity['warning'] ?? -1) !== $severityByStatus['WARN']
    || ($severity['review'] ?? -1) !== $severityByStatus['REVIEW']
    || ($severity['info'] ?? -1) !== $severityByStatus['INFO']) {
    $fail('Dashboard severity totals do not reconcile with the actual issue rows.');
}

$q6403Finding = array_values(array_filter($findingsByCode['QUESTION_REUSE_REVIEW_HOLD'] ?? [], static fn(object $issue): bool => (string) ($issue->entityId ?? '') === '6403'))[0] ?? null;
if (!is_object($q6403Finding)
    || ($q6403Finding->status ?? '') !== 'REVIEW'
    || ($q6403Finding->evidence['board_id'] ?? '') !== 'civil-service'
    || ($q6403Finding->evidence['review_status'] ?? '') !== 'REVIEW_REQUIRED'
    || !str_contains((string) ($q6403Finding->evidence['reason'] ?? ''), 'punctuation')) {
    $fail('The active Q6403 scoped integrity hold is absent or inaccurately represented.');
}

extract($result, EXTR_OVERWRITE);
ob_start();
require $root . '/app/Views/developer/question-quality.php';
$html = (string) ob_get_clean();
$visibleHtml = preg_replace('/\s+/', ' ', $html) ?? $html;
foreach ([
    'Source reachability and text access',
    'Access status:',
    'Full source text verified: <code>no</code>',
    'Independent review status: <code>REVIEW_REQUIRED</code>',
    'Affects 0 question(s): none currently linked',
    'Exact links with unknown or unreviewed status',
    'URL check result: <code>DNS_ERROR</code>',
    'Question eligibility review',
    'QUESTION_REUSE_REVIEW_HOLD',
    '#6403',
] as $needle) {
    if (!str_contains($visibleHtml, $needle)) $fail('Rendered dashboard is missing an explicit state: ' . $needle);
}

foreach ($protected as $relative => $before) {
    if (!hash_equals($before, (string) hash_file('sha256', $root . '/' . $relative))) {
        $fail('Production dashboard analysis mutated protected data: ' . $relative);
    }
}

echo 'QUESTION_QUALITY_DASHBOARD_REMEDIATION_PASS=1' . PHP_EOL;
echo 'CONFIRMED_404_REVIEW=' . count($deadIds) . PHP_EOL;
echo 'REQUIRED_BROKEN_SOURCE_ERRORS=0' . PHP_EOL;
echo 'SOURCE_TEXT_ACCESS_WARNINGS=' . ($byCode['SOURCE_TEXT_ACCESS_LIMITED'] ?? 0) . PHP_EOL;
echo 'INCONCLUSIVE_URL_CHECKS=' . $inconclusiveCount . PHP_EOL;
echo 'URL_AUDIT_COVERAGE=' . count((array) ($snapshot['records'] ?? [])) . '/' . (int) ($snapshot['coverage']['total_url_source_count'] ?? 0) . '_' . $snapshotInspection['status'] . PHP_EOL;
echo 'EXACT_LINKS=1049_PENDING_OR_UNKNOWN=1049_APPROVED=0' . PHP_EOL;
echo 'Q6403_REVIEW_HOLD=VISIBLE' . PHP_EOL;
echo 'RENDERED_DASHBOARD_AND_PROTECTED_HASHES=PASS' . PHP_EOL;
