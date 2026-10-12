<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\Question\QuestionIdMigrationService;
use App\Services\Shared\ContentIntegrityService;
use App\Services\Quiz\Session\QuizSessionQuestion;
use App\Repositories\QuestionRepository;

$root = dirname(__DIR__, 2);
$readJson = static function (string $path): array {
    $value = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($value)) {
        throw new RuntimeException('Expected JSON object or array at ' . $path);
    }
    return $value;
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$report = $readJson($root . '/docs/BATCH669_NURSING_ID_RECONCILIATION.json');
$fixture = $readJson($root . '/tests/fixtures/batch669-question-id-collision.json');
$mapping = $report['mapping'] ?? [];
$assert(($report['mapping_version'] ?? '') === QuestionIdMigrationService::VERSION, 'Runtime mapping version differs from the collision report.');
$assert(count($mapping) === 320, 'The verified crosswalk must retain all 320 incoming Nursing records.');
$assert(($report['summary']['verified_same_numeric_id_collisions'] ?? 0) === 320, 'The numeric collision count changed.');
$assert(($report['summary']['incoming_records_not_reimported'] ?? 0) === 320, 'The exact duplicate Nursing cohort was not fully reconciled.');
$assert(($report['summary']['existing_collision_board_counts'] ?? []) === ['cpa' => 151, 'criminologist' => 169], 'Existing collision-board distribution changed.');
$assert(($report['summary']['shared_id_classifications'] ?? []) === [
    'identical' => 0,
    'compatible_edits' => 0,
    'conflicting_edits' => 0,
    'distinct_questions_shared_id' => 320,
    'unresolved' => 0,
], 'Shared-ID semantic classifications changed.');
$assert(($report['summary']['mutable_state_matches'] ?? 0) === 320, 'Legacy mutable counters and timestamps differ from retained Nursing rows.');
$assert(($report['summary']['mutable_state_differences'] ?? -1) === 0, 'Legacy mutable state has unresolved differences.');

$sourceIds = [];
$canonicalIds = [];
$fixtureRow = null;
foreach ($mapping as $row) {
    $sourceId = (int) ($row['source_question_id'] ?? 0);
    $canonicalId = (int) ($row['canonical_nursing_question_id'] ?? 0);
    $sourceIds[$sourceId] = true;
    $canonicalIds[$canonicalId] = true;
    $assert($canonicalId === 4873 + ($sourceId - 4163), 'Crosswalk ID allocation is not deterministic.');
    $assert(QuestionIdMigrationService::canonicalId((string) $sourceId, 'nursing') === (string) $canonicalId, 'Runtime Nursing compatibility mapping differs from the crosswalk.');
    $assert(QuestionIdMigrationService::canonicalId((string) $sourceId, 'cpa') === (string) $sourceId, 'CPA question IDs must not be remapped.');
    $assert(QuestionIdMigrationService::canonicalId((string) $sourceId, 'criminologist') === (string) $sourceId, 'Criminologist question IDs must not be remapped.');
    $collision = $row['same_numeric_id_collision'] ?? [];
    $nursing = $row['nursing_record_reconciliation'] ?? [];
    $assert(($collision['classification'] ?? '') === 'DISTINCT_QUESTIONS_SHARED_ID', 'A shared numeric ID was incorrectly treated as identical content.');
    $assert(($nursing['content_equal_except_id_and_mutable_counters'] ?? false) === true, 'Incoming Nursing record does not match its retained canonical record.');
    $assert(($nursing['references_equal'] ?? false) === true, 'Nursing eligibility, sources, study links, or reuse metadata differ.');
    if ($sourceId === (int) ($fixture['shared_question_id'] ?? 0)) {
        $fixtureRow = $row;
    }
}
$assert(count($sourceIds) === 320 && min(array_keys($sourceIds)) === 4163 && max(array_keys($sourceIds)) === 4482, 'Source ID range is incomplete or duplicated.');
$assert(count($canonicalIds) === 320 && min(array_keys($canonicalIds)) === 4873 && max(array_keys($canonicalIds)) === 5192, 'Canonical Nursing ID range is incomplete or duplicated.');
$assert(is_array($fixtureRow), 'Representative real-collision fixture is missing from the report.');
$assert(($fixtureRow['same_numeric_id_collision']['existing_board'] ?? '') === ($fixture['existing_board'] ?? null), 'Collision fixture board provenance changed.');
$assert(($fixtureRow['same_numeric_id_collision']['incoming_board'] ?? '') === ($fixture['incoming_board'] ?? null), 'Collision fixture Nursing provenance changed.');
$assert(($fixtureRow['nursing_record_reconciliation']['canonical_content_sha256'] ?? '') === ($fixtureRow['nursing_record_reconciliation']['incoming_content_sha256'] ?? null), 'Representative content fingerprints differ.');

putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
\App\Core\App::boot();
$canonicalQuestion = \App\Core\App::container()->get(QuestionRepository::class)->find('4873');
$assert(is_array($canonicalQuestion), 'Canonical Nursing record 4873 is unavailable to the production repository.');
$legacySessionQuestion = $canonicalQuestion;
$legacySessionQuestion['id'] = '4163';
$assert(QuizSessionQuestion::isCurrent($legacySessionQuestion), 'A pre-reconciliation Nursing session no longer resolves to its canonical record.');

$review = [[
    'question_id' => '4163',
    'question' => ['id' => '4163', 'board' => 'nursing', 'question' => 'unchanged review snapshot'],
]];
$migratedReview = QuestionIdMigrationService::canonicalizeReview($review, ['board' => 'nursing']);
$assert(($migratedReview[0]['question']['id'] ?? '') === '4873', 'Historical Nursing review did not resolve to the canonical Nursing ID.');
$assert(($migratedReview[0]['question_id'] ?? '') === '4873', 'Historical review question reference was not migrated in memory.');
$cpaReview = QuestionIdMigrationService::canonicalizeReview([[
    'question' => ['id' => '4163', 'board' => 'cpa'],
]], ['board' => 'cpa']);
$assert(($cpaReview[0]['question']['id'] ?? '') === '4163', 'Historical CPA review was incorrectly remapped.');

$nursingPlan = QuestionIdMigrationService::canonicalizeAnswerPlan(
    [['id' => '4163', 'board' => 'nursing']],
    [['question_id' => '4163', 'correct' => true]]
);
$assert(($nursingPlan[0]['question_id'] ?? '') === '4873' && ($nursingPlan[0]['correct'] ?? null) === true, 'Legacy Nursing answer statistics were not routed to the canonical ID.');

$question = static function (string $board, string $fingerprint): array {
    return [
        'id' => '4163',
        'board' => $board,
        'status' => 'active',
        'difficulty' => 'medium',
        'question' => 'collision fixture ' . $fingerprint,
        'choices' => ['fixture correct', 'fixture distractor'],
        'answer' => 'fixture correct',
        'explanation' => 'Structural test fixture; not canonical educational content.',
        'taxonomy' => ['board_id' => $board],
    ];
};
$collisionCollections = [
    'questions' => [
        $question('nursing', (string) ($fixture['incoming_stem_sha256'] ?? '')),
        $question('cpa', (string) ($fixture['existing_stem_sha256'] ?? '')),
    ],
    'boards' => [['id' => 'nursing', 'name' => 'Nursing'], ['id' => 'cpa', 'name' => 'CPA']],
    'subjects' => [],
    'board-subjects' => [],
    'taxonomy/domains' => [],
    'taxonomy/topics' => [],
    'taxonomy/concepts' => [],
    'question-eligibility' => [],
    'study-materials' => [],
    'study-question-links' => [],
    'sources' => [],
];
$integrity = ContentIntegrityService::analyze($collisionCollections);
$assert(in_array('4163', $integrity['failures']['duplicate_question_ids'] ?? [], true), 'Doctor integrity did not catch the real cross-board duplicate-ID pattern.');
$assert(($integrity['portfolio']['status'] ?? '') === 'FAIL', 'Duplicate canonical IDs must block integrity readiness.');

echo "[PASS] Batch 669 Nursing crosswalk, board-scoped legacy migration, and Doctor collision regression verified.\n";
