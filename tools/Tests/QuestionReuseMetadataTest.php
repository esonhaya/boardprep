<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Core\App;
use App\Repositories\QuestionRepository;
use App\Services\Question\QuestionReuseEligibilityResolver;
use App\Services\Question\QuestionReuseMetadataService;
use App\Services\Question\QuestionBuilderService;
use App\Services\Question\QuestionEligibilityService;
use App\Storage\JsonStorage;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$metadata = QuestionReuseMetadataService::migrate([
    'id' => 'fixture',
    'board' => 'let',
    'taxonomy' => ['board_id' => 'let', 'concept_id' => 'fixture-concept'],
], false, false);
$assert(QuestionReuseMetadataService::validate($metadata) === [], 'migration metadata is invalid');
$assert(($metadata['required_depth'] ?? null) === 'UNKNOWN', 'migration guessed depth');
$invalidAuto = $metadata;
$invalidAuto['reuse_policy'] = 'AUTO';
$assert(in_array('AUTO_WITH_UNKNOWN_DEPTH', QuestionReuseMetadataService::validate($invalidAuto), true), 'AUTO with unknown depth was accepted');

$directory = sys_get_temp_dir() . '/boardprep-reuse-' . getmypid();
if (!is_dir($directory)) mkdir($directory, 0777, true);
$storage = new JsonStorage($directory);
$storage->replace('questions', [['id' => 'q1', 'question' => 'Fixture', 'taxonomy' => ['board_id' => 'let', 'subject_id' => 'english', 'domain_id' => 'grammar', 'topic_id' => 'parts-of-speech', 'concept_id' => 'parts-of-speech-nouns'], 'choices' => ['A', 'B'], 'answer' => 'A', 'explanation' => 'Fixture', 'difficulty' => 'easy']]);
$storage->replace('question-reuse-metadata', [['id' => 'reuse-q1', 'question_id' => 'q1', 'reuse' => $metadata]]);
$repository = new QuestionRepository($storage);
$hydrated = $repository->find('q1');
$assert(is_array($hydrated['reuse'] ?? null), 'repository did not hydrate reuse metadata');

$built = QuestionBuilderService::build(0, [
    'board_id' => 'let', 'subject_id' => 'english', 'domain_id' => 'grammar',
    'topic_id' => 'parts-of-speech', 'concept_id' => 'parts-of-speech-nouns',
    'difficulty' => 'easy', 'question' => 'Which is a noun?',
    'option_1' => 'Teacher', 'option_2' => 'Quickly', 'option_3' => 'Bright', 'option_4' => 'Run',
    'correct_option' => 'option-1', 'explanation' => 'Teacher names a person.',
]);
$assert(QuestionReuseMetadataService::validate($built['reuse'] ?? null) === [], 'new authoring default is invalid');
$assert(($built['reuse']['reuse_policy'] ?? null) === 'REVIEW', 'new authoring default was not conservative');

$coverage = App::storage()->all('board-concept-coverage')[0] ?? [];
$assert(QuestionReuseEligibilityResolver::validateCoverage($coverage) === [], 'migrated coverage is invalid');
$resolved = QuestionReuseEligibilityResolver::resolve((new QuestionRepository(App::storage()))->find('2664') ?? [], true);
$assert(($resolved['production_auto_inheritance_enabled'] ?? true) === false, 'dry-run resolver enabled production inheritance');
$assert(($resolved['effective'] ?? []) !== [], 'direct eligibility was not preserved');

$source = (new QuestionRepository(App::storage()))->find('1');
$assert(is_array($source), 'fixture question for override precedence is missing');
$sourceMappings = QuestionEligibilityService::editorMappings($source);
$target = array_values(array_filter($sourceMappings, static fn(array $mapping): bool => ($mapping['board_id'] ?? '') === 'civil-service'))[0] ?? [];
$overrideBase = [
    'id' => 'override-fixture',
    'question_id' => '1',
    'board_id' => 'civil-service',
    'override_type' => 'INCLUDE',
    'placement' => [
        'board_id' => 'civil-service',
        'subject_id' => $target['subject_id'] ?? 'verbal-ability',
        'domain_id' => $target['domain_id'] ?? '',
        'topic_id' => $target['topic_id'] ?? '',
        'concept_id' => $target['concept_id'] ?? '',
        'blueprint_node' => $target['blueprint_node'] ?? 'verbal-ability',
    ],
    'reason' => 'Fixture override precedence',
    'provenance' => [[
        'type' => 'MANUAL_REVIEW',
        'reference' => 'fixture',
        'note' => 'Fixture-only explicit review.',
        'verified_status' => 'VERIFIED',
    ]],
];
$assert(QuestionReuseEligibilityResolver::validateOverride($overrideBase) === [], 'valid include override was rejected');
$resolvedOverride = QuestionReuseEligibilityResolver::resolve(
    ['id' => '1', 'reuse' => ['reuse_policy' => 'REVIEW']],
    true,
    [['board_id' => 'let']],
    [$overrideBase]
);
$assert(($resolvedOverride['effective']['civil-service']['path'] ?? '') === 'MANUAL_INCLUDE', 'manual include was not effective');
$exclude = $overrideBase;
$exclude['id'] = 'override-fixture-exclude';
$exclude['override_type'] = 'EXCLUDE';
$resolvedExcluded = QuestionReuseEligibilityResolver::resolve(
    ['id' => '1', 'reuse' => ['reuse_policy' => 'REVIEW']],
    true,
    [['board_id' => 'let']],
    [$overrideBase, $exclude]
);
$assert(!isset($resolvedExcluded['effective']['civil-service']), 'explicit exclude did not win over include');
$unsafeEquivalence = [
    'source_concept_id' => 'critical-reading-evidence-quality',
    'target_concept_id' => 'pronouns-pronoun-reference',
    'relation_type' => 'PARTIAL',
    'reuse_policy' => 'AUTO',
    'status' => 'VERIFIED',
    'provenance' => $overrideBase['provenance'],
];
$assert(in_array('AUTO_UNSAFE_EQUIVALENCE', QuestionReuseEligibilityResolver::validateEquivalence($unsafeEquivalence), true), 'unsafe equivalence was accepted for AUTO');
$assert(in_array('SELF_EQUIVALENCE', QuestionReuseEligibilityResolver::validateEquivalence([
    'source_concept_id' => 'critical-reading-evidence-quality',
    'target_concept_id' => 'critical-reading-evidence-quality',
    'relation_type' => 'EXACT',
    'status' => 'VERIFIED',
    'provenance' => $overrideBase['provenance'],
]), true), 'self-equivalence was accepted');

foreach (glob($directory . '/*.json') ?: [] as $path) unlink($path);
if (is_file($directory . '/.storage.lock')) unlink($directory . '/.storage.lock');
rmdir($directory);

echo "[PASS] reuse metadata hydration, validation, authoring defaults, coverage, and resolver safety verified.
";
