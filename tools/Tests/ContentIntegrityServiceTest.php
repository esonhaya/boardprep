<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';

\App\Core\Autoloader::register();

use App\Core\App;
use App\Services\Flashcard\FlashcardDerivationService;
use App\Services\Shared\ContentIntegrityService;
use Tools\Doctor\Project\BoardPrep\Checks\ContentIntegrityCheck;
use Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator;

putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
App::boot();

$collections = [];
foreach ([
    'questions', 'boards', 'subjects', 'board-subjects',
    'taxonomy/domains', 'taxonomy/topics', 'taxonomy/concepts',
    'question-eligibility', 'study-materials', 'study-question-links', 'sources',
] as $collection) {
    $collections[$collection] = App::storage()->all($collection);
}

$copy = static fn(): array => unserialize(serialize($collections));
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$clean = ContentIntegrityService::analyze($collections);
$assert($clean['portfolio']['total_canonical'] === 1828, 'clean canonical count changed');
$assert($clean['portfolio']['total_eligible'] === 1828, 'clean eligibility total changed');
$assert($clean['portfolio']['total_derivable_cards'] === 1810, 'clean flashcard derivation changed');
$assert($clean['portfolio']['total_justified_exclusions'] === 18, 'justified exclusions were not preserved');
$assert($clean['portfolio']['total_accidental_non_derivable'] === 0, 'clean portfolio has accidental exclusions');
$assert($clean['portfolio']['status'] !== 'FAIL', 'clean portfolio failed integrity');

$ownedId = (string) $collections['questions'][0]['id'];
$missingEligibility = $copy();
foreach ($missingEligibility['question-eligibility'] as &$entry) {
    $entry['question_ids'] = array_values(array_filter($entry['question_ids'] ?? [], static fn(mixed $id): bool => (string) $id !== $ownedId));
}
unset($entry);
$missingReport = ContentIntegrityService::analyze($missingEligibility);
$assert(in_array($ownedId, $missingReport['boards']['let']['missing_eligibility'], true), 'missing eligibility was not detected');

$invalidEligibility = $copy();
$invalidEligibility['question-eligibility'][] = ['id' => 'fixture-invalid-eligibility', 'exam_ids' => ['let'], 'question_ids' => ['missing-question']];
$invalidEligibilityReport = ContentIntegrityService::analyze($invalidEligibility);
$assert(is_array($invalidEligibilityReport), 'invalid eligibility report failed');
$assert($invalidEligibilityReport['portfolio']['total_invalid_eligibility'] > 0, 'invalid eligibility was not detected');
$assert($invalidEligibilityReport['portfolio']['status'] === 'FAIL', 'invalid eligibility did not fail readiness');

$unlinked = $copy();
$unlinked['study-question-links'] = array_values(array_filter($unlinked['study-question-links'], static fn(array $link): bool => (string) ($link['question_id'] ?? '') !== $ownedId));
$unlinkedReport = ContentIntegrityService::analyze($unlinked);
$assert(in_array($ownedId, $unlinkedReport['boards']['let']['unlinked_questions'], true), 'unlinked question was not detected');

$invalidLink = $copy();
$invalidLink['study-question-links'][] = ['id' => 'fixture-invalid-link', 'material_id' => 'missing-material', 'question_id' => $ownedId, 'source_ids' => ['missing-source']];
$invalidLinkReport = ContentIntegrityService::analyze($invalidLink);
$assert($invalidLinkReport['portfolio']['total_invalid_links'] > 0, 'invalid study link was not detected');

$duplicateLink = $copy();
$duplicateLink['study-question-links'][] = $duplicateLink['study-question-links'][0];
$duplicateLinkReport = ContentIntegrityService::analyze($duplicateLink);
$assert($duplicateLinkReport['portfolio']['total_duplicate_links'] > 0, 'duplicate study link was not detected');

$invalidTaxonomy = $copy();
$invalidTaxonomy['questions'][0]['taxonomy']['subject_id'] = 'missing-subject';
$invalidTaxonomyReport = ContentIntegrityService::analyze($invalidTaxonomy);
$assert($invalidTaxonomyReport['portfolio']['total_invalid_taxonomy_refs'] > 0, 'invalid taxonomy was not detected');

$missingAsset = $copy();
foreach ($missingAsset['questions'] as $questionIndex => $question) {
    foreach ($question['content_blocks'] ?? [] as $blockIndex => $block) {
        if (($block['type'] ?? '') === 'figure') {
            $missingAsset['questions'][$questionIndex]['content_blocks'][$blockIndex]['asset'] = 'assets/missing-integrity-fixture.svg';
            break 2;
        }
    }
}
$missingAssetReport = ContentIntegrityService::analyze($missingAsset);
$assert($missingAssetReport['portfolio']['total_broken_assets'] > 0, 'missing asset was not detected');

$invalidAnswer = $copy();
$invalidAnswer['questions'][0]['answer'] = 'not-an-option';
$invalidAnswerReport = ContentIntegrityService::analyze($invalidAnswer);
$assert($invalidAnswerReport['portfolio']['total_invalid_answer_keys'] > 0, 'invalid answer key was not detected');

$deriver = App::container()->get(FlashcardDerivationService::class);
$accidental = ContentIntegrityService::analyze($collections, static function (array $question) use ($deriver, $ownedId): ?array {
    return (string) ($question['id'] ?? '') === $ownedId ? null : $deriver->derive($question);
});
$assert($accidental['portfolio']['total_accidental_non_derivable'] > 0, 'accidental non-derivable card was not detected');
$assert(in_array($ownedId, $accidental['failures']['accidental_non_derivable'], true), 'accidental non-derivable identifier was not surfaced');

$collision = ContentIntegrityService::analyze($collections, static fn(array $question): array => ['card_key' => 'fixture-collision']);
$assert($collision['portfolio']['total_card_key_collisions'] === 1, 'card-key collision was not detected');
echo "[PASS] integrity defect fixtures verified\n";

unset($missingEligibility, $invalidEligibility, $unlinked, $invalidLink, $duplicateLink, $invalidTaxonomy, $missingAsset, $invalidAnswer);
gc_collect_cycles();

$doctor = (new ContentIntegrityCheck())->run();
$assert($doctor->status === 'PASS' && str_contains(implode("\n", $doctor->details), '1828'), 'Doctor did not consume the clean integrity report');
echo "[PASS] Doctor consumer verified\n";

$dashboard = (new HttpSimulator(dirname(__DIR__, 2) . '/public/index.php'))->request('GET', '/developer');
$assert($dashboard['status'] === 200 && $dashboard['exitCode'] === 0, 'Developer dashboard integrity route failed');
$assert(str_contains($dashboard['output'], 'Content Integrity') && str_contains($dashboard['output'], 'Portfolio status'), 'Developer dashboard did not render integrity summary');

echo "[PASS] Shared content integrity service, defect fixtures, Doctor, and Developer UI verified.\n";
