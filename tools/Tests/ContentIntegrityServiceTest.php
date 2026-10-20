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
    'question-eligibility', 'study-materials', 'study-question-links', 'sources', 'flashcard-exclusions',
] as $collection) {
    $collections[$collection] = App::storage()->all($collection);
}

$copy = static fn(): array => unserialize(serialize($collections));
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$cleanDeriver = new FlashcardDerivationService(
    null,
    $collections['study-materials'],
    $collections['study-question-links'],
    $collections['flashcard-exclusions']
);
$nonDerivableIds = [];
$statusCalls = 0;
$clean = ContentIntegrityService::analyze(
    $collections,
    static function (array $question) use ($cleanDeriver, &$nonDerivableIds): ?array {
        $card = $cleanDeriver->derive($question);
        if ($card === null) {
            $nonDerivableIds[(string) ($question['id'] ?? '')] = true;
        }
        return $card;
    },
    static function (array $question) use ($cleanDeriver, &$nonDerivableIds, &$statusCalls): array {
        $id = (string) ($question['id'] ?? '');
        if (!isset($nonDerivableIds[$id])) {
            throw new RuntimeException('integrity scan requested status for a successfully derived card');
        }
        $statusCalls++;
        return $cleanDeriver->derivationStatus($question);
    }
);
$canonicalCount = (int) ($clean['portfolio']['total_canonical'] ?? 0);
$eligibleCount = (int) ($clean['portfolio']['total_eligible'] ?? 0);
$derivableCount = (int) ($clean['portfolio']['total_derivable_cards'] ?? 0);
$justifiedExclusions = (int) ($clean['portfolio']['total_justified_exclusions'] ?? 0);
$assert($canonicalCount === count($collections['questions']), 'clean canonical count changed');
$assert($eligibleCount === $canonicalCount, 'clean eligibility total changed');
$assert($derivableCount + $justifiedExclusions === $canonicalCount, 'clean flashcard derivation changed');
$assert($clean['portfolio']['total_justified_exclusions'] === 18, 'justified exclusions were not preserved');
$assert($clean['portfolio']['total_accidental_non_derivable'] === 0, 'clean portfolio has accidental exclusions');
$assert($statusCalls === count($nonDerivableIds) && $statusCalls === 18, 'status lookup was not limited to non-derivable cards');
$assert($clean['portfolio']['status'] !== 'FAIL', 'clean portfolio failed integrity');
unset($clean);
unset($cleanDeriver);
gc_collect_cycles();

$ownedId = (string) $collections['questions'][0]['id'];
$missingEligibility = $copy();
foreach ($missingEligibility['question-eligibility'] as &$entry) {
    $entry['question_ids'] = array_values(array_filter($entry['question_ids'] ?? [], static fn(mixed $id): bool => (string) $id !== $ownedId));
}
unset($entry);
$missingReport = ContentIntegrityService::analyze($missingEligibility);
$assert(in_array($ownedId, $missingReport['boards']['let']['missing_eligibility'], true), 'missing eligibility was not detected');
unset($missingEligibility, $missingReport);
gc_collect_cycles();

$invalidEligibility = $copy();
$invalidEligibility['question-eligibility'][] = ['id' => 'fixture-invalid-eligibility', 'exam_ids' => ['let'], 'question_ids' => ['missing-question']];
$invalidEligibilityReport = ContentIntegrityService::analyze($invalidEligibility);
$assert(is_array($invalidEligibilityReport), 'invalid eligibility report failed');
$assert($invalidEligibilityReport['portfolio']['total_invalid_eligibility'] > 0, 'invalid eligibility was not detected');
$assert($invalidEligibilityReport['portfolio']['status'] === 'FAIL', 'invalid eligibility did not fail readiness');
unset($invalidEligibility, $invalidEligibilityReport);
gc_collect_cycles();

$unlinked = $copy();
$unlinked['study-question-links'] = array_values(array_filter($unlinked['study-question-links'], static fn(array $link): bool => (string) ($link['question_id'] ?? '') !== $ownedId));
$unlinkedReport = ContentIntegrityService::analyze($unlinked);
$assert(in_array($ownedId, $unlinkedReport['boards']['let']['unlinked_questions'], true), 'unlinked question was not detected');
unset($unlinked, $unlinkedReport);
gc_collect_cycles();

$invalidLink = $copy();
$invalidLink['study-question-links'][] = ['id' => 'fixture-invalid-link', 'material_id' => 'missing-material', 'question_id' => $ownedId, 'source_ids' => ['missing-source']];
$invalidLinkReport = ContentIntegrityService::analyze($invalidLink);
$assert($invalidLinkReport['portfolio']['total_invalid_links'] > 0, 'invalid study link was not detected');
unset($invalidLink, $invalidLinkReport);
gc_collect_cycles();

$duplicateLink = $copy();
$duplicateLink['study-question-links'][] = $duplicateLink['study-question-links'][0];
$duplicateLinkReport = ContentIntegrityService::analyze($duplicateLink);
$assert($duplicateLinkReport['portfolio']['total_duplicate_links'] > 0, 'duplicate study link was not detected');
unset($duplicateLink, $duplicateLinkReport);
gc_collect_cycles();

$invalidTaxonomy = $copy();
$invalidTaxonomy['questions'][0]['taxonomy']['subject_id'] = 'missing-subject';
$invalidTaxonomyReport = ContentIntegrityService::analyze($invalidTaxonomy);
$assert($invalidTaxonomyReport['portfolio']['total_invalid_taxonomy_refs'] > 0, 'invalid taxonomy was not detected');
unset($invalidTaxonomy, $invalidTaxonomyReport);
gc_collect_cycles();

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
unset($missingAsset, $missingAssetReport);
gc_collect_cycles();

$invalidAnswer = $copy();
$invalidAnswer['questions'][0]['answer'] = 'not-an-option';
$invalidAnswerReport = ContentIntegrityService::analyze($invalidAnswer);
$assert($invalidAnswerReport['portfolio']['total_invalid_answer_keys'] > 0, 'invalid answer key was not detected');
unset($invalidAnswer, $invalidAnswerReport);
gc_collect_cycles();

$deriver = new FlashcardDerivationService(
    null,
    $collections['study-materials'],
    $collections['study-question-links'],
    $collections['flashcard-exclusions']
);
$pairedQuestion = null;
$pairedDerivation = null;
foreach ($collections['questions'] as $candidate) {
    $paired = $deriver->deriveWithStatus($candidate);
    if ($paired['card'] !== null) {
        $pairedQuestion = $candidate;
        $pairedDerivation = $paired;
        break;
    }
}
$assert(is_array($pairedQuestion) && is_array($pairedDerivation), 'combined flashcard status/derivation did not produce a valid card');
$assert(($pairedDerivation['status']['derivable'] ?? false) === true, 'combined derivation status disagreed with its card');
$assert($pairedDerivation['card'] === $deriver->derive($pairedQuestion), 'combined derivation changed the production card payload');
$accidental = ContentIntegrityService::analyze($collections, static function (array $question) use ($deriver, $ownedId): ?array {
    return (string) ($question['id'] ?? '') === $ownedId ? null : $deriver->derive($question);
});
$assert($accidental['portfolio']['total_accidental_non_derivable'] > 0, 'accidental non-derivable card was not detected');
$assert(in_array($ownedId, $accidental['failures']['accidental_non_derivable'], true), 'accidental non-derivable identifier was not surfaced');

$collision = ContentIntegrityService::analyze($collections, static fn(array $question): array => ['card_key' => 'fixture-collision']);
$assert($collision['portfolio']['total_card_key_collisions'] === 1, 'card-key collision was not detected');
echo "[PASS] integrity defect fixtures verified\n";

unset($collections, $collision, $accidental, $deriver);
gc_collect_cycles();

$checkInterfacePath = dirname(__DIR__) . '/HayaDoctor/tools/Doctor/Contracts/CheckInterface.php';
if (is_file($checkInterfacePath)) {
    $doctor = (new ContentIntegrityCheck())->run();
    $assert($doctor->status === 'PASS' && str_contains(implode("\n", $doctor->details), $canonicalCount . ' questions'), 'Doctor did not consume the clean integrity report');
    echo "[PASS] Doctor consumer verified\n";
} else {
    echo "[SKIP] Doctor adapter requires the uninitialized tools/HayaDoctor submodule.\n";
}

$dashboard = (new HttpSimulator(dirname(__DIR__, 2) . '/public/index.php'))->request('GET', '/developer');
$assert($dashboard['status'] === 200 && $dashboard['exitCode'] === 0, 'Developer dashboard integrity route failed');
$assert(str_contains($dashboard['output'], 'Content Integrity') && str_contains($dashboard['output'], 'Portfolio status'), 'Developer dashboard did not render integrity summary');

echo "[PASS] Shared content integrity service, defect fixtures, Doctor, and Developer UI verified.\n";
