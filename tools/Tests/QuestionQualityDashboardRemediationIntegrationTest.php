<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
App\Core\Autoloader::register();

use App\Services\Question\QuestionQualityService;

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
$assertCount('SOURCE_URL_CONFIRMED_404', 3);
$assertCount('SOURCE_URL_ACCESS_RESTRICTED', 45);
$assertCount('SOURCE_TEXT_ACCESS_LIMITED', 6);
$assertCount('SOURCE_URL_CHECK_INCONCLUSIVE', 12);
$assertCount('SOURCE_URL_AUDIT_PARTIAL', 1);

$deadIds = [];
foreach ($findingsByCode['SOURCE_URL_CONFIRMED_404'] ?? [] as $issue) {
    $deadIds[] = (string) ($issue->entityId ?? '');
    if (($issue->status ?? '') !== 'REVIEW' || (int) ($issue->evidence['affected_question_count'] ?? -1) !== 0) {
        $fail('An unreferenced confirmed-404 record was not classified as zero-impact review evidence.');
    }
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
if (($urlAudit['status'] ?? '') !== 'CURRENT_PARTIAL'
    || (int) ($urlAudit['checked_source_count'] ?? 0) !== 75
    || (int) ($urlAudit['total_url_source_count'] ?? 0) !== 319) {
    $fail('The production dashboard did not display the current bounded 75/319 source snapshot accurately.');
}
$relationships = (array) ($report->summary['study_relationships'] ?? []);
if (($relationships['exact_section_link_rows'] ?? 0) !== 1049
    || ($relationships['exact_links_pending_or_approval_evidence_missing'] ?? 0) !== 91
    || ($relationships['exact_links_unreviewed_or_status_unknown'] ?? 0) !== 958
    || ($relationships['exact_links_approved_with_explicit_evidence'] ?? 0) !== 0) {
    $fail('Exact mapping totals or unknown academic review status were conflated.');
}
$severity = (array) ($result['severitySummary'] ?? []);
if (($severity['error'] ?? -1) !== 0 || ($severity['warning'] ?? -1) !== 798 || ($severity['review'] ?? -1) !== 17) {
    $fail('Severity totals do not distinguish unreferenced dead sources from referenced broken citations.');
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
echo 'SOURCE_TEXT_ACCESS_WARNINGS=6' . PHP_EOL;
echo 'URL_AUDIT_COVERAGE=75/319_CURRENT_PARTIAL' . PHP_EOL;
echo 'EXACT_LINKS=1049_PENDING_OR_UNKNOWN=1049_APPROVED=0' . PHP_EOL;
echo 'RENDERED_DASHBOARD_AND_PROTECTED_HASHES=PASS' . PHP_EOL;
