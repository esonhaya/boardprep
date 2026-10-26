<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\Question\Quality\QuestionQualityIssueQueueService;

$issues = [];
foreach (range(1, 105) as $index) {
    $status = match (($index - 1) % 4) {
        0 => 'FAIL',
        1 => 'WARN',
        2 => 'REVIEW',
        default => 'INFO',
    };
    $issues[] = [
        'status' => $status,
        'category' => $index % 3 === 0 ? 'SOURCE_STUDY' : 'BALANCE',
        'entity_type' => $index % 2 === 0 ? 'question' : 'repository_record',
        'entity_id' => 'Q-' . str_pad((string) $index, 4, '0', STR_PAD_LEFT),
        'board_id' => $index % 2 === 0 ? 'let' : 'civil-service',
        'code' => 'CHECK_' . $index,
        'message' => $index === 42 ? 'unsafe <script>alert(1)</script>' : 'Finding ' . $index,
        'suggested_action' => 'Inspect evidence for ' . $index,
        'evidence' => [],
    ];
}

$page = QuestionQualityIssueQueueService::paginate($issues);
if (count($page['items']) !== 25 || $page['total'] !== 105 || $page['page'] !== 1 || $page['pages'] !== 5) {
    throw new RuntimeException('Default issue queue pagination is not bounded to 25 rows.');
}
if (($page['status_counts']['FAIL'] ?? 0) !== 27
    || ($page['status_counts']['WARN'] ?? 0) !== 26
    || ($page['status_counts']['REVIEW'] ?? 0) !== 26
    || ($page['status_counts']['INFO'] ?? 0) !== 26) {
    throw new RuntimeException('Issue status totals do not reconcile with the full input.');
}

$lastPage = QuestionQualityIssueQueueService::paginate($issues, ['page_num' => '999', 'per_page' => '25']);
if ($lastPage['page'] !== 5 || count($lastPage['items']) !== 5) {
    throw new RuntimeException('Out-of-range pages were not safely clamped.');
}

$needsReview = QuestionQualityIssueQueueService::paginate($issues, ['queue' => 'needs-review', 'per_page' => '50']);
if ($needsReview['total'] !== 79 || count($needsReview['items']) !== 50) {
    throw new RuntimeException('Needs Review should exclude informational signals and remain paginated.');
}
foreach ($needsReview['items'] as $issue) {
    if (($issue['status'] ?? '') === 'INFO') {
        throw new RuntimeException('Informational records leaked into Needs Review.');
    }
}

$filtered = QuestionQualityIssueQueueService::paginate($issues, [
    'status' => 'warn',
    'category' => 'source_study',
    'entity_type' => 'question',
    'board' => 'let',
    'search' => 'q-0042',
]);
if ($filtered['total'] !== 1
    || ($filtered['items'][0]['entity_id'] ?? '') !== 'Q-0042'
    || ($filtered['items'][0]['quality_classification'] ?? '') !== 'WARNING_REQUIRES_TRIAGE') {
    throw new RuntimeException('Combined status, category, type, board, and text filters did not identify the expected issue.');
}

$invalidFilters = QuestionQualityIssueQueueService::paginate($issues, [
    'status' => ['FAIL'],
    'search' => ['Q-0001'],
    'per_page' => '1000',
]);
if ($invalidFilters['filters']['status'] !== ''
    || $invalidFilters['filters']['search'] !== ''
    || $invalidFilters['per_page'] !== 25
    || count($invalidFilters['items']) !== 25) {
    throw new RuntimeException('Untrusted filter shapes or page sizes were not normalized safely.');
}

$typedFinding = QuestionQualityIssueQueueService::paginate([[
    'status' => 'REVIEW',
    'category' => 'ELIGIBILITY',
    'entity_type' => 'question',
    'entity_id' => '6403',
    'code' => 'QUESTION_REUSE_REVIEW_HOLD',
    'message' => 'A question has an exact-answer review hold.',
    'evidence' => ['board_id' => 'civil-service'],
]], ['issue_type' => 'ANSWER_KEY_REVIEW_REQUIRED']);
if ($typedFinding['total'] !== 1
    || ($typedFinding['items'][0]['issue_type'] ?? '') !== 'ANSWER_KEY_REVIEW_REQUIRED'
    || ($typedFinding['items'][0]['board_id'] ?? '') !== 'civil-service') {
    throw new RuntimeException('Evidence-backed answer-key review flags were not derived or filtered correctly.');
}

$duplicateFinding = QuestionQualityIssueQueueService::paginate([[
    'status' => 'REVIEW',
    'entity_type' => 'proposal',
    'entity_id' => '9124',
    'proposal_id' => 'batch695-9124',
    'issue_type' => 'DUPLICATE_REVIEW_REQUIRED',
    'code' => 'EXACT_CANONICAL_STEM_DUPLICATE',
    'message' => 'The normalized proposal stem exactly matches a canonical question.',
    'evidence' => [
        'duplicate_scope' => 'CANONICAL_QUESTION_BANK',
        'duplicate_match_method' => 'lower-case and whitespace-normalized exact stem',
        'duplicate_question_ids' => ['4512'],
    ],
]], ['search' => '4512']);
if ($duplicateFinding['total'] !== 1
    || ($duplicateFinding['items'][0]['issue_type'] ?? '') !== 'DUPLICATE_REVIEW_REQUIRED') {
    throw new RuntimeException('Search did not include duplicate IDs nested in evidence.');
}

echo "[PASS] Quality issue queue filtering, evidence classifications, and bounded pagination verified.\n";
