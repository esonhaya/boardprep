<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_once __DIR__ . '/Support/CountingReadOnlyStorage.php';

use App\Core\App;
use App\Services\Learning\LearningReadinessService;
use App\Services\Learning\Recommendation\LearningQuestionCatalog;
use App\Services\Learning\Recommendation\StudyRecommendationFactory;
use App\Services\Shared\TaxonomyStorageService;
use App\Services\Study\StudyLibraryService;

$database = App::database();
$storageProperty = new ReflectionProperty($database, 'storage');
$realStorage = $storageProperty->getValue($database);
$countingStorage = new CountingReadOnlyStorage($realStorage);
$storageProperty->setValue($database, $countingStorage);

$boards = [];
foreach (TaxonomyStorageService::boards() as $record) {
    if (!is_array($record)) continue;
    $id = trim((string) ($record['id'] ?? ''));
    if ($id === '') continue;
    $subjects = TaxonomyStorageService::subjectsByBoard($id);
    $subject = trim((string) ($subjects[0]['subject_id'] ?? ''));
    if ($subject === '') continue;
    $boards[] = ['id' => $id, 'subject' => $subject];
}
$resetFactory = static function (): void {
    $class = new ReflectionClass(StudyRecommendationFactory::class);
    $class->getProperty('studyLinksByContext')->setValue(null, []);
    $class->getProperty('topicByQuestionId')->setValue(null, null);
};
$resetStudyLibrary = static function (): void {
    $class = new ReflectionClass(StudyLibraryService::class);
    foreach (['eligibilityIndex', 'questionIndex', 'examAliasIndex'] as $name) {
        $class->getProperty($name)->setValue(null, null);
    }
};
$resetCatalog = static function (): void {
    $class = new ReflectionClass(LearningQuestionCatalog::class);
    $class->getProperty('topicByQuestionId')->setValue(null, null);
    $class->getProperty('eligibleCountsByScope')->setValue(null, []);
};
$countCanonicalQuestionReads = static fn(): int => (int) ($countingStorage->allReads['questions'] ?? 0);

// Reproduce the B720 per-board question-topic-index work before the request-local shared index.
$resetFactory();
$resetStudyLibrary();
$countingStorage->allReads = [];
$legacyStarted = microtime(true);
foreach ($boards as $board) {
    $materials = StudyLibraryService::all($board['id']);
    $topicsByQuestion = [];
    foreach (App::storage()->all('questions') as $question) {
        if (!is_array($question)) continue;
        $id = (string) ($question['id'] ?? '');
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        $topic = (string) ($taxonomy['topic_id'] ?? $question['topic'] ?? '');
        if ($id !== '' && $topic !== '') $topicsByQuestion[$id] = $topic;
    }
    foreach ($materials as $material) {
        foreach ((array) ($material['question_links'] ?? []) as $link) {
            if (is_array($link)) $topic = $topicsByQuestion[(string) ($link['question_id'] ?? '')] ?? '';
        }
    }
}
$legacyMs = round((microtime(true) - $legacyStarted) * 1000, 3);
$legacyQuestionReads = $countCanonicalQuestionReads();

// Measure the implemented path after clearing only request-local static indexes.
$resetFactory();
$resetStudyLibrary();
$countingStorage->allReads = [];
$recommendationStarted = microtime(true);
foreach ($boards as $board) {
    StudyRecommendationFactory::forTopic([
        'topic' => 'General',
        'subject' => $board['subject'],
        'board' => $board['id'],
        'attempts' => 2,
        'average' => 50,
        'repeated_mistake_question_ids' => [],
    ]);
}
$implementationMs = round((microtime(true) - $recommendationStarted) * 1000, 3);
$implementationQuestionReads = $countCanonicalQuestionReads();
$beforeWarmReads = $countCanonicalQuestionReads();
foreach ($boards as $board) {
    StudyRecommendationFactory::forTopic([
        'topic' => 'General', 'subject' => $board['subject'], 'board' => $board['id'],
        'attempts' => 2, 'average' => 50, 'repeated_mistake_question_ids' => [],
    ]);
}
$warmQuestionReadDelta = $countCanonicalQuestionReads() - $beforeWarmReads;

// Exercise the new readiness eligibility counts and topic index in one request.
$resetFactory();
$resetStudyLibrary();
$resetCatalog();
$countingStorage->allReads = [];
$integratedStarted = microtime(true);
$readiness = LearningReadinessService::build([], [
    'preferred_board' => 'CSE',
    'civil_service_level' => 'PROFESSIONAL',
]);
foreach ($boards as $board) {
    StudyRecommendationFactory::forTopic([
        'topic' => 'General', 'subject' => $board['subject'], 'board' => $board['id'],
        'attempts' => 2, 'average' => 50, 'repeated_mistake_question_ids' => [],
    ]);
}
$integratedColdMs = round((microtime(true) - $integratedStarted) * 1000, 3);
$integratedQuestionReads = $countCanonicalQuestionReads();
$questionReadsBeforeWarm = $countCanonicalQuestionReads();
$warmStarted = microtime(true);
LearningReadinessService::build([], [
    'preferred_board' => 'CSE',
    'civil_service_level' => 'PROFESSIONAL',
]);
foreach ($boards as $board) {
    StudyRecommendationFactory::forTopic([
        'topic' => 'General', 'subject' => $board['subject'], 'board' => $board['id'],
        'attempts' => 2, 'average' => 50, 'repeated_mistake_question_ids' => [],
    ]);
}
$integratedWarmMs = round((microtime(true) - $warmStarted) * 1000, 3);
$integratedWarmQuestionReads = $countCanonicalQuestionReads() - $questionReadsBeforeWarm;

echo json_encode([
    'report' => 'BATCH_721_RECOMMENDATION_READ_PROFILE',
    'result' => 'MEASURED',
    'boards_tested' => count($boards),
    'board_ids' => array_column($boards, 'id'),
    'measurements' => [
        'b720_style_per_board_topic_index' => [
            'question_bank_reads' => $legacyQuestionReads,
            'elapsed_ms' => $legacyMs,
            'board_contexts' => count($boards),
        ],
        'b721_request_local_shared_index' => [
            'question_bank_reads' => $implementationQuestionReads,
            'elapsed_ms' => $implementationMs,
            'board_contexts' => count($boards),
            'warm_additional_question_bank_reads' => $warmQuestionReadDelta,
        ],
        'b721_readiness_and_study_index' => [
            'preferred_board' => $readiness['board_code'] ?? '',
            'eligible_subjects' => $readiness['subject_coverage']['eligible'] ?? null,
            'configured_subjects' => $readiness['subject_coverage']['configured'] ?? null,
            'question_bank_reads' => $integratedQuestionReads,
            'warm_additional_question_bank_reads' => $integratedWarmQuestionReads,
            'cold_elapsed_ms' => $integratedColdMs,
            'warm_elapsed_ms' => $integratedWarmMs,
        ],
        'question_bank_read_reduction' => max(0, $legacyQuestionReads - $implementationQuestionReads),
        'question_bank_read_change_percent' => $legacyQuestionReads > 0
            ? round(100 * (1 - $implementationQuestionReads / $legacyQuestionReads), 1)
            : null,
        'php_peak_memory_bytes' => memory_get_peak_usage(true),
    ],
    'safety' => [
        'storage_wrapper' => 'read-only; all mutation methods throw',
        'question_records_changed' => 0,
        'learner_records_changed' => 0,
        'academic_state_changed' => 0,
    ],
    'limits' => [
        'legacy path is a same-process reproduction of the B720 per-board topic-index call pattern, not execution from a second checkout',
        'wall time varies by device cache state; question-bank read count is instrumented exactly',
        'Study Library may perform one separate request-local bank read for its own eligibility enrichment',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
