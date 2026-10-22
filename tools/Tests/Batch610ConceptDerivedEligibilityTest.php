<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require_once __DIR__ . '/Support/StaticStorageFixture.php';
$root = dirname(__DIR__, 2);
putenv('APP_STORAGE_PATH=' . StaticStorageFixture::create($root, 'batch610-concept-eligibility'));
putenv('DB_DRIVER=json');
require dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Core\App;
use App\Repositories\QuestionRepository;
use App\Services\Question\QuestionEligibilityIndex;
use App\Services\Question\QuestionEligibilityService;
use App\Services\Question\QuestionReuseEligibilityResolver;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$repository = new QuestionRepository(App::storage());
$question = $repository->find('422');
$assert(is_array($question), 'bounded cohort question is missing');
$assert(QuestionReuseEligibilityResolver::productionDerivationEnabled(), 'bounded production derivation is not enabled');

$direct = QuestionEligibilityService::forExam($question, 'let');
$inherited = QuestionEligibilityService::forExam($question, 'civil-service');
$assert(($direct['eligibility_source'] ?? '') !== 'CONCEPT_INHERITED', 'source mapping was converted to derived');
$assert(($inherited['eligibility_source'] ?? '') === 'CONCEPT_INHERITED', 'bounded inherited mapping was not production-effective');
$assert(($inherited['subject_id'] ?? '') === 'verbal-ability', 'inherited target placement lost its target subject');

$index = new QuestionEligibilityIndex(
    App::storage()->all('question-eligibility'),
    App::storage()->all('blueprints'),
    QuestionReuseEligibilityResolver::productionDerivedRelationships(),
    App::storage()->all('question-reuse-overrides')
);
$indexed = QuestionEligibilityService::forExam($question, 'civil-service', $index);
$assert(($indexed['eligibility_source'] ?? '') === 'CONCEPT_INHERITED', 'selection index did not include bounded inheritance');
$selectionPool = QuestionPoolFilter::filter(
    [$question, $question],
    new SelectionRequest('verbal-ability', null, ['medium' => 1], 1, null, null, 'civil-service')
);
$assert(count($selectionPool) === 1 && (string) ($selectionPool[0]['id'] ?? '') === '422', 'concept-derived eligibility multiplied a canonical selection candidate');
$deduped = QuestionReuseEligibilityResolver::resolve(
    $question,
    false,
    [['board_id' => 'civil-service']]
);
$assert(isset($deduped['effective']['civil-service']) && !isset($deduped['derived_production']['civil-service']), 'direct and derived paths were not deduplicated');

$depthBlocked = $repository->find('421');
$assert(is_array($depthBlocked), 'depth-blocked candidate is missing');
$assert(QuestionEligibilityService::forExam($depthBlocked, 'civil-service') === null, 'unknown-depth candidate inherited into production');
$regulatory = $repository->find('221');
$assert(is_array($regulatory), 'regulatory candidate is missing');
$assert(QuestionEligibilityService::forExam($regulatory, 'criminologist') === null, 'regulatory candidate inherited into an unrelated board');

$beforeOverrides = App::storage()->all('question-reuse-overrides');
$beforeCoverage = App::storage()->all('board-concept-coverage');
try {
    $mappings = QuestionEligibilityService::editorMappings($question);
    $assert(count($mappings) === 2, 'bounded inherited mapping was not visible in admin eligibility');
    $removed = QuestionEligibilityService::removeMapping($question, 'civil-service');
    $assert(($removed['valid'] ?? false) === true, 'removing inherited eligibility did not create an exclusion');
    $excluded = QuestionEligibilityService::forExam($question, 'civil-service');
    $assert($excluded === null, 'manual EXCLUDE did not override inherited eligibility');
    $assert(QuestionEligibilityService::forExam($question, 'let') !== null, 'manual EXCLUDE removed source-board eligibility');
    $assert(App::storage()->all('board-concept-coverage') === $beforeCoverage, 'inherited removal mutated global board coverage');
    $storedOverrides = App::storage()->all('question-reuse-overrides');
    $assert(count($storedOverrides) === count($beforeOverrides) + 1, 'inherited removal did not persist one override');
} finally {
    App::storage()->replace('question-reuse-overrides', $beforeOverrides);
    App::storage()->replace('board-concept-coverage', $beforeCoverage);
}

echo '[PASS] bounded concept inheritance, depth/context gates, inherited EXCLUDE precedence, and coverage preservation verified.' . PHP_EOL;
