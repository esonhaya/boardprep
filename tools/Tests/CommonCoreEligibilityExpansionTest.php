<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Core\App;
use App\Services\Question\QuestionEligibilityIndex;
use App\Services\Question\QuestionEligibilityService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$rules = [
    'cse-constitutional-principles' => [
        'classification' => 'A',
        'source_board' => 'civil-service',
        'target_board' => 'let',
        'target_subject' => 'social-studies',
        'evidence' => 'LET blueprint explicitly includes ethics and social/civic general education; constitutional public-service competency is context-compatible.',
    ],
    'cse-ethical-public-service' => [
        'classification' => 'A',
        'source_board' => 'civil-service',
        'target_board' => 'let',
        'target_subject' => 'social-studies',
        'evidence' => 'LET blueprint explicitly includes ethics; RA 6713 public-service norms are general civic ethics rather than profession-specific duties.',
    ],
    'filipino-pagbasa-pangunahing-diwan' => [
        'classification' => 'C',
        'source_board' => 'let',
        'target_board' => null,
        'target_subject' => null,
        'evidence' => 'The questions assess Filipino-language reading competency; current CSE verbal-ability evidence does not establish target-language compatibility.',
    ],
    'filipino-gramatika-wastong-gamit' => [
        'classification' => 'C',
        'source_board' => 'let',
        'target_board' => null,
        'target_subject' => null,
        'evidence' => 'The questions assess Filipino-language grammar and usage; current CSE verbal-ability evidence does not establish target-language compatibility.',
    ],
    'math-number-sense-operations' => [
        'classification' => 'A',
        'source_board' => 'let',
        'target_board' => 'civil-service',
        'target_subject' => 'numerical-ability',
        'evidence' => 'CSE blueprint explicitly requires Numerical Ability; the applied arithmetic, rates, percentages, and data operations are depth-compatible.',
    ],
    'math-algebra-linear-equations' => [
        'classification' => 'A',
        'source_board' => 'let',
        'target_board' => 'civil-service',
        'target_subject' => 'numerical-ability',
        'evidence' => 'CSE blueprint explicitly requires Numerical Ability; the elementary algebraic modeling and linear-equation reasoning are depth-compatible.',
    ],
    'critical-reading-evidence-quality' => [
        'classification' => 'A',
        'source_board' => 'let',
        'target_board' => 'civil-service',
        'target_subject' => 'verbal-ability',
        'evidence' => 'CSE blueprint explicitly requires Verbal Ability; evidence evaluation and reading conclusions are unchanged by board context.',
    ],
    'authors-purpose-and-tone-purpose' => [
        'classification' => 'A',
        'source_board' => 'let',
        'target_board' => 'civil-service',
        'target_subject' => 'verbal-ability',
        'evidence' => 'CSE blueprint explicitly requires Verbal Ability; purpose identification is context-independent reading reasoning.',
    ],
    'authors-purpose-and-tone-tone' => [
        'classification' => 'A',
        'source_board' => 'let',
        'target_board' => 'civil-service',
        'target_subject' => 'verbal-ability',
        'evidence' => 'CSE blueprint explicitly requires Verbal Ability; tone interpretation is context-independent reading reasoning.',
    ],
    'text-structure-sequence' => [
        'classification' => 'A',
        'source_board' => 'let',
        'target_board' => 'civil-service',
        'target_subject' => 'verbal-ability',
        'evidence' => 'CSE blueprint explicitly requires Verbal Ability; sequence and organization identification is context-independent.',
    ],
    'inference-evidence-based-conclusions' => [
        'classification' => 'A',
        'source_board' => 'let',
        'target_board' => 'civil-service',
        'target_subject' => 'verbal-ability',
        'evidence' => 'CSE blueprint explicitly requires Verbal Ability; cautious inference from textual evidence is unchanged by board context.',
    ],
    'science-biology-cells' => [
        'classification' => 'A',
        'source_board' => 'let',
        'target_board' => 'civil-service',
        'target_subject' => 'general-information',
        'evidence' => 'CSE blueprint retains General Information and existing general-science mappings establish compatible basic-science depth.',
    ],
    'science-chemistry-matter-particles' => [
        'classification' => 'A',
        'source_board' => 'let',
        'target_board' => 'civil-service',
        'target_subject' => 'general-information',
        'evidence' => 'CSE blueprint retains General Information and existing general-science mappings establish compatible basic-science depth.',
    ],
    'science-physics-force-balance' => [
        'classification' => 'A',
        'source_board' => 'let',
        'target_board' => 'civil-service',
        'target_subject' => 'general-information',
        'evidence' => 'CSE blueprint retains General Information and existing general-science mappings establish compatible basic-science depth.',
    ],
    'science-electricity-series-parallel' => [
        'classification' => 'A',
        'source_board' => 'let',
        'target_board' => 'civil-service',
        'target_subject' => 'general-information',
        'evidence' => 'CSE blueprint retains General Information and existing general-science mappings establish compatible basic-science depth.',
    ],
    'science-optics-reflection' => [
        'classification' => 'A',
        'source_board' => 'let',
        'target_board' => 'civil-service',
        'target_subject' => 'general-information',
        'evidence' => 'CSE blueprint retains General Information and existing general-science mappings establish compatible basic-science depth.',
    ],
    'science-earth-stratigraphy' => [
        'classification' => 'A',
        'source_board' => 'let',
        'target_board' => 'civil-service',
        'target_subject' => 'general-information',
        'evidence' => 'CSE blueprint retains General Information and existing general-science mappings establish compatible basic-science depth.',
    ],
    'science-ecology-population' => [
        'classification' => 'A',
        'source_board' => 'let',
        'target_board' => 'civil-service',
        'target_subject' => 'general-information',
        'evidence' => 'CSE blueprint retains General Information and existing general-science mappings establish compatible basic-science depth.',
    ],
    'science-data-evidence' => [
        'classification' => 'A',
        'source_board' => 'let',
        'target_board' => 'civil-service',
        'target_subject' => 'general-information',
        'evidence' => 'CSE blueprint retains General Information and existing general-science mappings establish compatible scientific-literacy depth.',
    ],
    'science-inquiry-evidence' => [
        'classification' => 'A',
        'source_board' => 'let',
        'target_board' => 'civil-service',
        'target_subject' => 'general-information',
        'evidence' => 'CSE blueprint retains General Information and existing general-science mappings establish compatible scientific-literacy depth.',
    ],
    'science-physics-motion-graphs' => [
        'classification' => 'A',
        'source_board' => 'let',
        'target_board' => 'civil-service',
        'target_subject' => 'general-information',
        'evidence' => 'CSE blueprint retains General Information and existing general-science mappings establish compatible basic-science depth.',
    ],
    'social-history-primary-sources' => [
        'classification' => 'A',
        'source_board' => 'let',
        'target_board' => 'civil-service',
        'target_subject' => 'general-information',
        'evidence' => 'CSE blueprint retains General Information; source analysis and Philippine-history reasoning have no profession-specific dependency.',
    ],
    'social-civics-public-good' => [
        'classification' => 'A',
        'source_board' => 'let',
        'target_board' => 'civil-service',
        'target_subject' => 'general-information',
        'evidence' => 'CSE blueprint retains General Information; civic evidence and public-good reasoning have no profession-specific dependency.',
    ],
];

$questions = App::storage()->all('questions');
$questionsById = [];
foreach ($questions as $question) {
    $questionsById[(string) ($question['id'] ?? '')] = $question;
}
$boards = array_values(array_map(
    static fn(array $board): string => (string) ($board['id'] ?? ''),
    App::storage()->all('boards')
));
$index = new QuestionEligibilityIndex(
    App::storage()->all('question-eligibility'),
    App::storage()->all('blueprints')
);

$auditRows = [];
foreach (App::storage()->all('question-eligibility') as $entry) {
    foreach (($entry['question_mappings'] ?? []) as $questionId => $mapping) {
        $audit = is_array($mapping) ? ($mapping['audit'] ?? null) : null;
        if (!is_array($audit) || ($audit['batch'] ?? null) !== 605) {
            continue;
        }
        $id = (string) $questionId;
        $assert(!isset($auditRows[$id]), 'duplicate audit row for question ' . $id);
        $auditRows[$id] = $audit;
    }
}
$assert(count($auditRows) === 449, 'audit population must contain exactly 449 questions');

$classificationCounts = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0];
foreach ($auditRows as $id => $row) {
    $question = $questionsById[$id] ?? null;
    $assert(is_array($question), 'audit row references missing question ' . $id);
    $concept = (string) (($question['taxonomy']['concept_id'] ?? ''));
    $rule = $rules[$concept] ?? null;
    $assert(is_array($rule), 'audit row has no classification rule for ' . $concept);
    $classification = (string) ($row['classification'] ?? '');
    $assert(isset($classificationCounts[$classification]), 'invalid classification for question ' . $id);
    $classificationCounts[$classification]++;
    $assert((string) ($row['classification'] ?? '') === (string) $rule['classification'], 'classification mismatch for question ' . $id);
    $assert((string) ($row['source_board'] ?? '') === (string) $rule['source_board'], 'source board mismatch for question ' . $id);
    $assert((string) ($row['source_concept'] ?? '') === $concept, 'source concept mismatch for question ' . $id);
    $assert(trim((string) ($row['evidence'] ?? '')) !== '', 'missing evidence for question ' . $id);

    $owner = strtolower((string) ($question['taxonomy']['board_id'] ?? ''));
    $assert($owner === $rule['source_board'], 'source board mismatch for question ' . $id);
    $sourceMapping = QuestionEligibilityService::forExam($question, $owner, $index);
    $assert($sourceMapping !== null, 'audited question lost source eligibility ' . $id);
    $metadata = QuestionEligibilityService::metadata($question);
    $assert(in_array($owner, array_map('strtolower', $metadata['exam_ids'] ?? []), true), 'metadata lost source eligibility ' . $id);
    $target = $rule['target_board'];
    if ($target !== null) {
        $mapping = QuestionEligibilityService::forExam($question, $target, $index);
        $assert($mapping !== null, 'shareable question missing target eligibility ' . $id);
        $assert(($mapping['subject_id'] ?? '') === $rule['target_subject'], 'target blueprint mapping mismatch for question ' . $id);
        $assert(($row['target_blueprint_node'] ?? '') === $rule['target_subject'], 'audit target blueprint mismatch for question ' . $id);
        $assert(($row['justified_target_boards'] ?? []) === [$target], 'audit target board mismatch for question ' . $id);
    } else {
        $assert($target === null, 'non-shareable question has an unexpected target ' . $id);
        $assert(($row['target_blueprint_node'] ?? null) === null, 'non-shareable question has target blueprint ' . $id);
        $assert(($row['justified_target_boards'] ?? []) === [], 'non-shareable question has justified target ' . $id);
    }
    foreach ($boards as $board) {
        if ($board === $owner || $board === $target) {
            continue;
        }
        $assert(QuestionEligibilityService::forExam($question, $board, $index) === null, 'unjustified eligibility for question ' . $id . ' on ' . $board);
    }
}
$assert($classificationCounts === ['A' => 324, 'B' => 0, 'C' => 125, 'D' => 0, 'E' => 0], 'classification counts are not reproducible');

$representatives = [
    ['id' => '2769', 'board' => 'civil-service', 'subject' => 'Verbal Ability'],
    ['id' => '2384', 'board' => 'civil-service', 'subject' => 'Numerical Ability'],
    ['id' => '2524', 'board' => 'civil-service', 'subject' => 'General Information'],
    ['id' => '246', 'board' => 'let', 'subject' => 'Social Studies'],
];
foreach ($representatives as $representative) {
    $question = $questionsById[$representative['id']] ?? null;
    $assert(is_array($question), 'representative question missing');
    $selected = QuestionSelectionService::select(
        [$question],
        new QuizSpecification(
            $representative['board'],
            $representative['subject'],
            null,
            [],
            [],
            (string) ($question['difficulty'] ?? 'medium'),
            1,
            'practice',
            false,
            false,
            null,
            null
        )
    );
    $assert(count($selected) === 1 && (string) $selected[0]['id'] === $representative['id'], 'new shared mapping failed production selection for ' . $representative['id']);
}

$filipino = $questionsById['2499'] ?? null;
$assert(is_array($filipino), 'Filipino isolation representative missing');
$assert(QuestionSelectionService::select(
    [$filipino],
    new QuizSpecification('civil-service', 'Verbal Ability', null, [], [], (string) ($filipino['difficulty'] ?? 'medium'), 1, 'practice', false, false, null, null)
) === [], 'context-specific Filipino question leaked into Civil Service');

$shared = $questionsById['2769'] ?? null;
$assert(is_array($shared), 'deduplication representative missing');
$deduplicated = QuestionSelectionService::select(
    [$shared, $shared],
    new QuizSpecification('civil-service', 'Verbal Ability', null, [], [], (string) ($shared['difficulty'] ?? 'medium'), 2, 'practice', false, false, null, null)
);
$assert(count($deduplicated) === 1 && (string) $deduplicated[0]['id'] === '2769', 'multiple eligibility paths duplicated a canonical question');

echo '[PASS] Common-core classifications, eligibility mappings, blueprint resolution, production selection, isolation, and deduplication verified.' . PHP_EOL;
