<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';

use App\Core\App;
use App\Core\Autoloader;
use App\Services\Question\QuestionService;
use App\Services\RepositoryHealth\Engine\RepositoryContextFactory;
use App\Services\RepositoryHealth\Engine\RepositoryHealthEngine;
use App\Services\RepositoryHealth\Readiness\SourceReadinessRecord;
use App\Services\RepositoryHealth\Readiness\SourceReadinessService;
use App\Services\RepositoryHealth\Readiness\SourceReadinessValidator;

Autoloader::register();
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
App::boot();

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$collections = [];
foreach (RepositoryContextFactory::collectionNames() as $name) {
    $collections[$name] = App::storage()->all($name);
}

$records = SourceReadinessService::records($collections);
$validation = SourceReadinessService::validateCollections($collections);
$assert(count($records) === 37, 'Expected exactly 37 Criminologist readiness records.');
$assert(($validation['valid'] ?? false) === true, 'Persisted readiness records do not satisfy the permanent contract.');
$letReviewBatches = array_values(array_unique(array_map(
    static fn(array $record): int => (int) ($record['reviewed_at_batch'] ?? 0),
    (array) ($collections['source-readiness-let'] ?? [])
)));
$assert(count($letReviewBatches) > 1 && in_array(695, $letReviewBatches, true), 'Mixed per-leaf review batches were not preserved in the LET readiness collection.');
$ids = array_map(static fn(array $record): string => (string) ($record['leaf_id'] ?? ''), $records);
$assert(count($ids) === count(array_unique($ids)), 'Readiness records contain duplicate leaf IDs.');
$assert(count($ids) === count(SourceReadinessService::nativeLeafCounts($collections, 'criminologist')), 'Readiness records do not cover every active leaf.');

$schema = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/storage/schemas/source-readiness.schema.json'), true, 512, JSON_THROW_ON_ERROR);
$assert(is_array($schema['items']['required'] ?? null) && in_array('evidence_hash', $schema['items']['required'], true), 'Canonical readiness schema is incomplete.');
$assert(SourceReadinessRecord::evidenceHash($records[0]) === SourceReadinessRecord::evidenceHash($records[0]), 'Evidence hash is not deterministic.');

$malformed = $collections;
$malformed['source-readiness'][0]['source_count']++;
$malformedValidation = SourceReadinessService::validateCollections($malformed);
$assert(($malformedValidation['valid'] ?? true) === false, 'SOURCE_COUNT contradiction was accepted.');
$malformedReport = RepositoryHealthEngine::analyzeCollections($malformed);
$malformedIssue = array_filter(
    $malformedReport->issues,
    static fn(object $issue): bool => $issue->code === 'SOURCE_READINESS.INVALID_RECORD'
        && (string) ($issue->entityId ?? '') === (string) ($malformed['source-readiness'][0]['leaf_id'] ?? '')
);
$assert($malformedIssue !== [], 'Repository Health did not expose the exact malformed readiness leaf.');

$badType = $collections;
$badType['source-readiness'][0]['supported_depth_max'] = '4';
$badTypeValidation = SourceReadinessService::validateCollections($badType);
$assert(($badTypeValidation['valid'] ?? true) === false, 'Raw readiness field type mismatch was accepted.');

$missingField = $collections;
unset($missingField['source-readiness'][0]['leaf_name']);
$missingFieldValidation = SourceReadinessService::validateCollections($missingField);
$assert(($missingFieldValidation['valid'] ?? true) === false, 'Missing required readiness field was accepted.');

$contradiction = $collections;
$contradiction['source-readiness'][0]['readiness'] = 'READY';
$contradiction['source-readiness'][0]['source_authority'] = 'WEAK';
$contradictionValidation = SourceReadinessService::validateCollections($contradiction);
$contradictionCodes = array_column($contradictionValidation['errors'] ?? [], 'code');
$assert(($contradictionValidation['valid'] ?? true) === false && in_array('SOURCE_READINESS.CONTRADICTION', $contradictionCodes, true), 'READY with weak evidence was accepted.');

$missing = $collections;
array_pop($missing['source-readiness']);
$missingValidation = SourceReadinessService::validateCollections($missing);
$assert(($missingValidation['valid'] ?? true) === false, 'Missing active-leaf readiness record was accepted.');
$missingCodes = array_column($missingValidation['errors'] ?? [], 'code');
$assert(in_array('SOURCE_READINESS.MISSING_ACTIVE_LEAF', $missingCodes, true), 'Missing active-leaf code was not emitted.');

$newQuestion = null;
foreach ($collections['questions'] as $question) {
    if (is_array($question) && (string) ($question['id'] ?? '') === '8814') {
        $newQuestion = $question;
        break;
    }
}
$assert(is_array($newQuestion), 'Batch641 authoring-gate fixture is missing.');
$newQuestion['reuse'] = ['required_depth' => 3];
$gate = SourceReadinessService::validateQuestionForCreate($newQuestion, $collections);
$assert(($gate['valid'] ?? false) === true, 'Valid Batch641 question did not pass the readiness authoring gate.');
$badQuestion = $newQuestion;
$badQuestion['source_scope'] = ['future_effective_rules'];
$badGate = SourceReadinessService::validateQuestionForCreate($badQuestion, $collections);
$assert(($badGate['valid'] ?? true) === false && in_array('SOURCE_READINESS_SCOPE_OUTSIDE_BOUNDARY', $badGate['errors'] ?? [], true), 'Out-of-scope authoring was accepted.');
$writeGate = QuestionService::validateForCreate($newQuestion);
$assert(($writeGate['valid'] ?? false) === false || ($writeGate['duplicates'] ?? []) !== [], 'Existing authoring fixture unexpectedly passed duplicate-aware create validation.');

echo "[PASS] source-readiness schema, identity/count/type contracts, health issue identity, evidence hashes, and authoring gate verified.\n";
