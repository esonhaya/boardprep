<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Core\App;
use App\Services\Flashcard\FlashcardCardValidator;
use App\Services\Flashcard\FlashcardDerivationService;
use App\Services\Flashcard\FlashcardExclusionRegistry;
use App\Services\Question\QuestionService;
use App\Services\Question\StructuredContentService;
use App\Services\RepositoryHealth\Engine\RepositoryHealthEngine;
use App\Services\Shared\QuestionValidationService;

putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
App::boot();

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$fixture = json_decode(file_get_contents(__DIR__ . '/fixtures/batch639-study-focus-regression.json'), true, 512, JSON_THROW_ON_ERROR);
$storage = App::storage();
$question = null;
foreach ($storage->all('questions') as $candidate) {
    if (is_array($candidate) && (string) ($candidate['id'] ?? '') === '6978') {
        $question = $candidate;
        break;
    }
}
$assert(is_array($question), 'CE fixture source question missing');
$question['id'] = 'fixture-b639';
$question['content_blocks'] = $fixture['before']['content_blocks'];

$link = null;
foreach ($storage->all('study-question-links') as $candidate) {
    if (is_array($candidate) && (string) ($candidate['question_id'] ?? '') === '6978') {
        $link = $candidate;
        break;
    }
}
$assert(is_array($link), 'CE fixture study link missing');
$link['id'] = 'fixture-study-link-b639';
$link['question_id'] = 'fixture-b639';
$collections = [
    'questions' => [$question],
    'boards' => $storage->all('boards'),
    'subjects' => $storage->all('subjects'),
    'board-subjects' => $storage->all('board-subjects'),
    'blueprints' => $storage->all('blueprints'),
    'taxonomy/domains' => $storage->all('taxonomy/domains'),
    'taxonomy/topics' => $storage->all('taxonomy/topics'),
    'taxonomy/concepts' => $storage->all('taxonomy/concepts'),
    'question-eligibility' => [['id' => 'fixture-eligibility-b639', 'exam_ids' => ['civil-engineering'], 'question_ids' => ['fixture-b639']]],
    'concept-derived-eligibility' => [],
    'question-reuse-overrides' => [],
    'question-reuse-metadata' => [],
    'board-concept-coverage' => [],
    'study-materials' => array_values(array_filter($storage->all('study-materials'), static fn(mixed $record): bool => is_array($record) && (string) ($record['id'] ?? '') === (string) ($link['material_id'] ?? ''))),
    'study-question-links' => [$link],
    'sources' => $storage->all('sources'),
    'content-fit-audits' => [],
    'flashcard-exclusions' => [],
];

$structuredErrors = StructuredContentService::validateDetailed($question);
$assert(count($structuredErrors) === 1 && $structuredErrors[0]['code'] === 'UNSUPPORTED_BLOCK_TYPE', 'malformed regression fixture no longer fails the contract');
$assert(($structuredErrors[0]['path'] ?? '') === 'content_blocks.0.type', 'structured fixture failure path changed');
$assert(QuestionValidationService::validate($question)['valid'] === false, 'write-time validator allowed malformed study_focus');
$writeValidation = QuestionService::validateForCreate($question);
$assert(($writeValidation['valid'] ?? false) === false, 'QuestionService write-time guard allowed malformed study_focus');

$badReport = RepositoryHealthEngine::analyzeCollections($collections);
$badIssue = false;
foreach ($badReport->issues as $issue) {
    if ($issue->code === 'STRUCTURED_CONTENT_UNSUPPORTED_BLOCK_TYPE' && (string) $issue->entityId === 'fixture-b639') {
        $badIssue = true;
        break;
    }
}
$assert($badIssue, 'permanent Repository Health did not surface the malformed fixture ID');
$assert(($badReport->checks['STRUCTURED_CONTENT']['status'] ?? '') === 'FAIL', 'malformed fixture did not fail structured-content health');

$validQuestion = $question;
$validQuestion['content_blocks'] = $fixture['after']['content_blocks'];
$validCollections = $collections;
$validCollections['questions'] = [$validQuestion];
$validReport = RepositoryHealthEngine::analyzeCollections($validCollections);
$assert(($validReport->checks['STRUCTURED_CONTENT']['status'] ?? '') === 'PASS', 'repaired regression fixture did not pass structured-content health');
$deriver = new FlashcardDerivationService(null, $validCollections['study-materials'], $validCollections['study-question-links'], []);
$card = $deriver->derive($validQuestion);
$assert(is_array($card), 'valid regression fixture did not derive a production card');
$assert(FlashcardCardValidator::validate($card) === [], 'derived regression fixture card failed card contract');
$assert($deriver->derivationStatus($validQuestion)['derivable'] === true, 'validator/consumer contract disagreed for valid fixture');

$exclusion = [
    'entity_id' => 'fixture-exclusion-b639',
    'board_id' => 'civil-engineering',
    'reason_code' => 'NOT_FLASHCARD_SUITABLE',
    'human_readable_reason' => 'Pure recognition content is intentionally excluded from card generation.',
    'evidence' => ['reviewed_by' => 'batch639-contract-test', 'reason' => 'low-value-intent'],
    'review_status' => 'APPROVED',
];
$registry = new FlashcardExclusionRegistry([$exclusion]);
$assert(count($registry->validateAll()) === 1 && $registry->validateAll()[0]['valid'] === true, 'explicit exclusion record failed its contract');
$exclusionQuestion = $validQuestion;
$exclusionQuestion['id'] = 'fixture-exclusion-b639';
$exclusionQuestion['cognitive_intent'] = 'recall';
$exclusionQuestion['content_blocks'] = [];
$exclusionDeriver = new FlashcardDerivationService(null, $validCollections['study-materials'], [['id' => 'fixture-exclusion-link', 'material_id' => $link['material_id'], 'question_id' => 'fixture-exclusion-b639', 'source_ids' => $link['source_ids']]], [$exclusion]);
$exclusionStatus = $exclusionDeriver->derivationStatus($exclusionQuestion);
$assert($exclusionStatus['justified'] === true && is_array($exclusionStatus['exclusion']), 'explicit exclusion did not remain visible to derivation');
$malformedExclusion = $question;
$malformedExclusion['id'] = 'fixture-b639';
$malformedExclusionDeriver = new FlashcardDerivationService(null, $validCollections['study-materials'], $validCollections['study-question-links'], [array_merge($exclusion, ['entity_id' => 'fixture-b639'])]);
$malformedStatus = $malformedExclusionDeriver->derivationStatus($malformedExclusion);
$assert($malformedStatus['justified'] === false, 'malformed content masqueraded as an exclusion');

echo "[PASS] structured-content contract, write-time guard, production derivation, explicit exclusion, and permanent health fixture verified\n";
