<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';

use App\Core\App;
use App\Core\Autoloader;
use App\Services\Flashcard\FlashcardCardValidator;
use App\Services\Flashcard\FlashcardDerivationService;
use App\Services\Question\CalculationMetadataContract;
use App\Services\RepositoryHealth\DTO\HealthReport;
use App\Services\RepositoryHealth\Engine\RepositoryHealthCheckCatalog;
use App\Services\RepositoryHealth\Engine\RepositoryHealthEngine;

Autoloader::register();
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
App::boot();

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$catalog = RepositoryHealthCheckCatalog::definitions();
$assert(count($catalog) >= 14, 'Repository Health catalog is incomplete.');
$assert(count($catalog) === count(array_unique(array_keys($catalog))), 'Repository Health catalog has duplicate check codes.');
$owners = array_map(static fn(array $definition): string => $definition['owner'], $catalog);
$assert(count($owners) === count(array_filter($owners, static fn(string $owner): bool => trim($owner) !== '')), 'Repository Health catalog has an unowned check.');
$assert(interface_exists('Tools\\Doctor\\Contracts\\CheckInterface'), 'HayaDoctor CheckInterface is not loadable.');
$assert(class_exists('Tools\\Doctor\\DTO\\DoctorResult'), 'HayaDoctor DoctorResult is not loadable.');
$assert(class_exists('Tools\\Doctor\\Metrics\\MetricRegistry'), 'HayaDoctor MetricRegistry is not loadable.');

$queue = [
    ['status' => 'FAIL', 'board_id' => 'cpa', 'category' => 'CALCULATION', 'entity_type' => 'question', 'entity_id' => 'q-1'],
    ['status' => 'WARN', 'board_id' => 'cpa', 'category' => 'BALANCE', 'entity_type' => 'board', 'entity_id' => 'cpa'],
    ['status' => 'REVIEW', 'board_id' => null, 'category' => 'SOURCE_STUDY', 'entity_type' => 'repository_record', 'entity_id' => 'm-1'],
];
$assert(count(HealthReport::filterActionQueue($queue, ['severity' => 'FAIL', 'board' => 'CPA'])) === 1, 'Action queue severity/board filtering failed.');
$assert(count(HealthReport::filterActionQueue($queue, ['category' => 'balance', 'entity_type' => 'board'])) === 1, 'Action queue category/entity filtering failed.');

$questions = array_values(array_filter(App::storage()->all('questions'), 'is_array'));
$calculationQuestion = null;
foreach ($questions as $candidate) {
    if (($candidate['calculation_required'] ?? false) === true && is_array($candidate['calculation'] ?? null)) {
        $calculationQuestion = $candidate;
        break;
    }
}
$assert(is_array($calculationQuestion), 'No repaired calculation fixture was found.');
$validContract = CalculationMetadataContract::inspect($calculationQuestion);
$assert($validContract['valid'] === true, 'Repaired calculation fixture does not satisfy the write contract.');

$badCalculationQuestion = $calculationQuestion;
unset($badCalculationQuestion['calculation'], $badCalculationQuestion['calculation_family']);
$badContract = CalculationMetadataContract::inspect($badCalculationQuestion);
$assert($badContract['valid'] === false && $badContract['code'] === 'MISSING_CALCULATION_PAYLOAD', 'Malformed calculation fixture did not fail its contract.');

$questionId = (string) $calculationQuestion['id'];
$links = array_values(array_filter(App::storage()->all('study-question-links'), static fn(mixed $link): bool => is_array($link) && (string) ($link['question_id'] ?? '') === $questionId));
$materials = App::storage()->all('study-materials');
$sources = App::storage()->all('sources');
$fixtureCollections = [
    'questions' => [$badCalculationQuestion],
    'boards' => App::storage()->all('boards'),
    'subjects' => App::storage()->all('subjects'),
    'board-subjects' => App::storage()->all('board-subjects'),
    'blueprints' => App::storage()->all('blueprints'),
    'taxonomy/domains' => App::storage()->all('taxonomy/domains'),
    'taxonomy/topics' => App::storage()->all('taxonomy/topics'),
    'taxonomy/concepts' => App::storage()->all('taxonomy/concepts'),
    'question-eligibility' => [['id' => 'batch640-calc-fixture', 'exam_ids' => [(string) ($badCalculationQuestion['board'] ?? '')], 'question_ids' => [$questionId]]],
    'concept-derived-eligibility' => [],
    'question-reuse-overrides' => [],
    'question-reuse-metadata' => [],
    'board-concept-coverage' => [],
    'study-materials' => $materials,
    'study-question-links' => $links,
    'sources' => $sources,
    'content-fit-audits' => [],
    'flashcard-exclusions' => [],
];
$badReport = RepositoryHealthEngine::analyzeCollections($fixtureCollections);
$badCalculationIssue = array_filter($badReport->issues, static fn(object $issue): bool => $issue->code === 'CALCULATION_METADATA_CONTRADICTION' && (string) $issue->entityId === $questionId);
$assert($badCalculationIssue !== [], 'Repository Health did not detect the calculation regression fixture.');

$validFixtureCollections = $fixtureCollections;
$validFixtureCollections['questions'] = [$calculationQuestion];
$validReport = RepositoryHealthEngine::analyzeCollections($validFixtureCollections);
$validCalculationIssue = array_filter($validReport->issues, static fn(object $issue): bool => $issue->code === 'CALCULATION_METADATA_CONTRADICTION' && (string) $issue->entityId === $questionId);
$assert($validCalculationIssue === [], 'Repaired calculation fixture still failed permanent health.');
$linkDeriver = new FlashcardDerivationService(null, $materials, $links, []);
$card = $linkDeriver->derive($calculationQuestion);
$assert(is_array($card) && FlashcardCardValidator::validate($card) === [], 'Calculation fixture did not remain production-derivable.');

echo "[PASS] Batch640 catalog, action queue filters, HayaDoctor contracts, calculation regression, and derivation contract verified.\n";
