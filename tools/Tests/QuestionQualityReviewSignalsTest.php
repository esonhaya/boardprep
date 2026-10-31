<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
putenv('APP_ENV=production');
putenv('APP_DEBUG=false');
putenv('DB_DRIVER=json');
putenv('APP_STORAGE_PATH=' . $root . '/storage');
require_once $root . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\Question\Quality\QuestionQualityReviewSignalService;
use App\Services\Question\Quality\QuestionQualityIssueQueueService;
use App\Services\Question\AcademicReviewProposalService;

$protectedPaths = [
    'storage/questions.json',
    'storage/question-eligibility.json',
    'storage/sources.json',
    'storage/study-question-links.json',
    'storage/question-statistics.json',
    'storage/attempts.json',
    'storage/weakness.json',
    'storage/flashcard_state.json',
    'storage/academic-review-decisions.json',
    'storage/academic-source-reviews.json',
];
if (is_file($root . '/storage/learner-question-reports.json')) {
    $protectedPaths[] = 'storage/learner-question-reports.json';
}
$before = [];
foreach ($protectedPaths as $path) {
    $before[$path] = hash_file('sha256', $root . '/' . $path);
}

$signals = QuestionQualityReviewSignalService::proposalSignals();
$counts = [];
$pendingIds = [];
foreach ($signals as $signal) {
    $type = (string) ($signal['issue_type'] ?? '');
    $counts[$type] = ($counts[$type] ?? 0) + 1;
    if ($type === 'NO_DETECTED_TECHNICAL_ISSUE'
        && (($signal['evidence']['source_snapshot_validation_status'] ?? '') !== 'CURRENT'
            || (($signal['evidence']['technical_validation_status'] ?? '') !== 'STRUCTURE_AND_REFERENCES_VALID'))) {
        throw new RuntimeException('A clear technical status did not verify current source references and structure.');
    }
    if ($type === 'DUPLICATE_REVIEW_REQUIRED'
        && (!is_array($signal['evidence']['duplicate_question_ids'] ?? null)
            || $signal['evidence']['duplicate_question_ids'] === []
            || trim((string) ($signal['evidence']['duplicate_match_method'] ?? '')) === '')) {
        throw new RuntimeException('A duplicate review signal omitted its matching IDs or deterministic method.');
    }
    if ($type === 'ACADEMIC_APPROVAL_PENDING') {
        $pendingIds[(string) ($signal['proposal_id'] ?? '')] = true;
        if (strlen((string) ($signal['question_version_sha256'] ?? '')) !== 64
            || trim((string) ($signal['current_status'] ?? '')) === ''
            || (string) ($signal['evidence']['readiness_version_status'] ?? '') !== 'CURRENT'
            || (string) ($signal['evidence']['technical_status'] ?? '') !== 'NO_DETECTED_TECHNICAL_ISSUE') {
            throw new RuntimeException('A pending proposal signal omitted its current status, exact version, or freshness evidence.');
        }
    }
}

$expected = [
    'NO_DETECTED_TECHNICAL_ISSUE' => 276,
    'ACADEMIC_APPROVAL_PENDING' => 276,
    'SOURCE_REVIEW_REQUIRED' => 276,
    'FACTUAL_VERIFICATION_REQUIRED' => 276,
    'ANSWER_KEY_REVIEW_REQUIRED' => 276,
    'EXPLANATION_REVIEW_REQUIRED' => 276,
    'TAXONOMY_MAPPING_REQUIRED' => 276,
    'STUDY_SECTION_MISSING' => 258,
    'SOURCE_CURRENTNESS_WARNING' => 4,
    'SOURCE_SCOPE_WARNING' => 6,
    'READY_FOR_AUTHORIZED_REVIEW' => 266,
];
foreach ($expected as $type => $expectedCount) {
    if (($counts[$type] ?? 0) !== $expectedCount) {
        throw new RuntimeException($type . ' expected ' . $expectedCount . ' evidence-backed records; found ' . ($counts[$type] ?? 0) . '.');
    }
}
if (count($pendingIds) !== 276 || count(AcademicReviewProposalService::all()) !== 276) {
    throw new RuntimeException('The review signal projection did not preserve all 276 separate proposal identities.');
}
if (($counts['TECHNICAL_ISSUE'] ?? 0) !== 0) {
    throw new RuntimeException('The canonical B695 question structures unexpectedly failed deterministic validation.');
}
if (QuestionQualityReviewSignalService::exactCanonicalDuplicateIds(
    "  A  shared   stem ",
    ['a shared stem' => ['4512', '4512', '6403']],
    '4512'
) !== ['6403']) {
    throw new RuntimeException('Exact normalized duplicate detection did not return unique, non-self canonical matches.');
}
$currentnessQueue = QuestionQualityIssueQueueService::paginate($signals, [
    'issue_type' => 'SOURCE_CURRENTNESS_WARNING',
    'status' => 'WARN',
]);
if ($currentnessQueue['total'] !== 4
    || count($currentnessQueue['items']) !== 4
    || ($currentnessQueue['items'][0]['question_version_sha256'] ?? '') === ''
    || ($currentnessQueue['items'][0]['current_status'] ?? '') === '') {
    throw new RuntimeException('Source currentness filtering did not preserve question version and current status evidence.');
}
$readyQueue = QuestionQualityIssueQueueService::paginate($signals, [
    'issue_type' => 'READY_FOR_AUTHORIZED_REVIEW',
    'queue' => 'needs-review',
    'per_page' => '100',
]);
if ($readyQueue['total'] !== 266 || count($readyQueue['items']) !== 100) {
    throw new RuntimeException('Authorized-review readiness filtering did not remain bounded and server-paginated.');
}

foreach ($protectedPaths as $path) {
    $after = hash_file('sha256', $root . '/' . $path);
    if (!is_string($before[$path]) || !is_string($after) || !hash_equals($before[$path], $after)) {
        throw new RuntimeException('Read-only review classification changed protected data: ' . $path);
    }
}

echo 'QUESTION_QUALITY_REVIEW_SIGNALS_PASS=1 ' . json_encode($counts, JSON_UNESCAPED_SLASHES) . PHP_EOL;
