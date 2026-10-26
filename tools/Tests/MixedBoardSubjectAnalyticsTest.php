<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Services\Learning\LearningReadinessService;
use App\Services\Learning\Recommendation\LearningAttemptContext;
use App\Services\Learning\SubjectPerformanceService;
use App\Services\Learning\TopicPerformanceService;
use App\Services\Quiz\Start\QuizStartInputNormalizer;
use App\Core\App;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$labelContext = LearningAttemptContext::fromQuestion([
    'taxonomy' => ['board_id' => 'let', 'subject_id' => 'english'],
    'attempt_context' => ['board' => 'LET', 'subject' => 'english'],
], ['board' => 'LET', 'subject' => 'English']);
$assert(($labelContext['subject'] ?? '') === 'English',
    'Resolved eligibility ID discarded a matching learner-facing subject label.');

$question = static function (string $id, string $subject, string $topic, bool $correct): array {
    return [
        'answered' => true,
        'correct' => $correct,
        'question' => [
            'id' => $id,
            'board' => 'LET',
            'taxonomy' => ['board_id' => 'let', 'subject_id' => 'english', 'topic_id' => $topic],
            'attempt_context' => [
                'board' => 'civil-service',
                'subject' => $subject,
                'civil_service_level' => 'PROFESSIONAL',
            ],
        ],
    ];
};
$attempts = [
    [
        'id' => 'mixed-cse-older', 'session_id' => 'cse-session-older', 'completed' => true,
        'board' => 'civil-service', 'subject' => '*', 'civil_service_level' => 'PROFESSIONAL',
        'completed_at' => '2026-10-10T10:00:00Z', 'score' => 3, 'total' => 4, 'answered' => 4,
        'review' => [
            $question('shared-q1', 'verbal-ability', 'verbal-reasoning', true),
            $question('shared-q2', 'verbal-ability', 'verbal-reasoning', false),
            $question('shared-q3', 'verbal-ability', 'verbal-reasoning', true),
            $question('cse-info-1', 'general-information', 'civic-literacy', true),
        ],
    ],
    [
        'id' => 'mixed-cse-newer', 'session_id' => 'cse-session-newer', 'completed' => true,
        'board' => 'civil-service', 'subject' => '*', 'civil_service_level' => 'PROFESSIONAL',
        'completed_at' => '2026-10-11T10:00:00Z', 'score' => 2, 'total' => 3, 'answered' => 3,
        'review' => [
            $question('shared-q1', 'verbal-ability', 'verbal-reasoning', true),
            $question('shared-q4', 'verbal-ability', 'verbal-reasoning', false),
            $question('cse-info-2', 'general-information', 'civic-literacy', true),
        ],
    ],
    [
        'id' => 'legacy-cross-board-unresolved', 'session_id' => 'legacy-cross-board-unresolved', 'completed' => true,
        'board' => 'civil-service', 'subject' => '*', 'civil_service_level' => 'PROFESSIONAL',
        'completed_at' => '2026-10-12T10:00:00Z', 'score' => 0, 'total' => 1, 'answered' => 1,
        'review' => [[
            'answered' => true,
            'correct' => false,
            'question' => [
                'id' => 'legacy-shared-without-placement',
                'taxonomy' => ['board_id' => 'let', 'subject_id' => 'english', 'topic_id' => 'legacy-attribution-risk'],
            ],
        ]],
    ],
    ['id' => 'incomplete-cse', 'completed' => false, 'board' => 'civil-service', 'subject' => '*', 'score' => 10, 'total' => 10],
];

$subjects = SubjectPerformanceService::summarize($attempts);
$bySubject = [];
foreach ($subjects as $row) {
    $bySubject[$row['subject']] = $row;
}
$assert(isset($bySubject['verbal-ability']), 'Mixed-board question placements were not attributed to the board-resolved subject.');
$assert(!isset($bySubject['english']), 'Canonical source-board subject leaked into cross-board answer analytics.');
$assert(($bySubject['verbal-ability']['attempts'] ?? 0) === 2, 'Subject quiz attempts were not deduplicated by session.');
$assert(($bySubject['verbal-ability']['answered_questions'] ?? 0) === 5
    && ($bySubject['verbal-ability']['correct_answers'] ?? 0) === 3
    && ($bySubject['verbal-ability']['incorrect_answers'] ?? 0) === 2
    && ($bySubject['verbal-ability']['accuracy'] ?? null) === 60,
    'Subject answer event counts or denominator are inconsistent.');
$assert(($bySubject['verbal-ability']['recent_accuracy'] ?? null) === 60,
    'Recent subject accuracy did not use the bounded subject attempt window.');
$assert(($bySubject['general-information']['accuracy'] ?? null) === 100,
    'Another subject in the same mixed simulation was not independently summarized.');
$assert(!isset($bySubject['english']) && !isset($bySubject[''])
    && !in_array('legacy-attribution-risk', array_column(TopicPerformanceService::summarize($attempts), 'topic'), true),
    'Legacy cross-board history without saved placement was assigned to the wrong subject or topic context.');

$topics = TopicPerformanceService::summarize($attempts);
$verbalTopic = array_values(array_filter($topics, static fn(array $row): bool =>
    ($row['subject'] ?? '') === 'verbal-ability' && ($row['topic'] ?? '') === 'verbal-reasoning'
));
$assert(count($verbalTopic) === 1 && ($verbalTopic[0]['question_review_count'] ?? 0) === 5,
    'Topic analytics did not retain the saved board-resolved subject context.');

$readiness = LearningReadinessService::build($attempts, [
    'preferred_board' => 'CSE',
    'civil_service_level' => 'PROFESSIONAL',
]);
$assert(($readiness['status'] ?? '') === 'PRACTICE_DATA_AVAILABLE', 'Board readiness omitted completed scoped attempts.');
$assert(($readiness['unattributed_answer_events'] ?? null) === 1,
    'Legacy mixed-board answer without a saved placement was not disclosed as unattributed.');
$assert(($readiness['is_pass_probability'] ?? true) === false
    && array_key_exists('official_pass_probability', $readiness['blueprint_context'] ?? [])
    && $readiness['blueprint_context']['official_pass_probability'] === null,
    'Preparation overview exposed an unsupported official passing probability.');
$assert(($readiness['subject_coverage']['practiced'] ?? 0) >= 2
    && ($readiness['subject_coverage']['registered'] ?? 0) >= ($readiness['subject_coverage']['practiced'] ?? PHP_INT_MAX),
    'Configured subject coverage is inconsistent.');
$assert(count(array_filter($readiness['subjects'], static fn(array $row): bool => ($row['status'] ?? '') === 'UNASSESSED')) > 0,
    'Unattempted registered subjects were incorrectly inferred as weak or omitted.');
$clericalReadiness = array_values(array_filter($readiness['subjects'], static fn(array $row): bool => ($row['subject'] ?? '') === 'clerical-ability'))[0] ?? [];
$assert(($clericalReadiness['status'] ?? '') === 'NO_ELIGIBLE_PRACTICE_POOL'
    && ($clericalReadiness['eligible_question_count'] ?? null) === 0,
    'CSE Professional readiness counted the excluded Clerical Ability pool as available.');
$assert(count(array_filter($readiness['recommendations'], static fn(array $row): bool => str_starts_with((string) ($row['action'] ?? ''), '/quiz?'))) > 0,
    'Unassessed subject recommendation did not point to the existing practice route.');
foreach ($readiness['recommendations'] as $recommendation) {
    if (!isset($recommendation['subject'])) {
        continue;
    }
    parse_str((string) parse_url((string) ($recommendation['action'] ?? ''), PHP_URL_QUERY), $query);
    $normalized = QuizStartInputNormalizer::normalize($query);
    $assert(($normalized['board'] ?? '') === 'civil-service'
        && ($normalized['subject'] ?? '') === $recommendation['subject']
        && ($normalized['civil_service_level'] ?? '') === 'PROFESSIONAL',
        'Preparation recommendation did not preserve a supported board, subject, and variant destination.');
    $assert(($normalized['subject'] ?? '') !== 'clerical-ability',
        'Professional Civil Service recommendation exposed an invalid Clerical Ability destination.');
}

$secondarySpecialization = null;
foreach (App::storage()->all('let-specializations') as $specialization) {
    if (is_array($specialization) && strtoupper((string) ($specialization['level'] ?? '')) === 'SECONDARY') {
        $secondarySpecialization = (string) ($specialization['id'] ?? '');
        break;
    }
}
$assert($secondarySpecialization !== null && $secondarySpecialization !== '', 'No registered Secondary LET variant is available for the recommendation fixture.');
$letReadiness = LearningReadinessService::build([], [
    'preferred_board' => 'LET',
    'let_level' => 'SECONDARY',
    'let_specialization' => $secondarySpecialization,
]);
$assert(count($letReadiness['recommendations'] ?? []) === 1
    && !array_key_exists('subject', $letReadiness['recommendations'][0]),
    'LET specialization readiness recommended a subject without exact variant-pool evidence.');
parse_str((string) parse_url((string) ($letReadiness['recommendations'][0]['action'] ?? ''), PHP_URL_QUERY), $letQuery);
$normalizedLetAction = QuizStartInputNormalizer::normalize($letQuery);
$assert(strtolower((string) ($normalizedLetAction['board'] ?? '')) === 'let'
    && ($normalizedLetAction['let_level'] ?? '') === 'SECONDARY'
    && ($normalizedLetAction['let_specialization'] ?? '') === $secondarySpecialization
    && ($normalizedLetAction['subject'] ?? '') === '*',
    'LET balanced recommendation failed to preserve the registered candidate variant.');

$coldStart = LearningReadinessService::build([], []);
$assert(($coldStart['status'] ?? '') === 'INSUFFICIENT_PRACTICE_DATA'
    && ($coldStart['is_pass_probability'] ?? true) === false,
    'New learner state was not represented as insufficient evidence.');
$cseVariantRequired = LearningReadinessService::build([], ['preferred_board' => 'CSE']);
$assert(($cseVariantRequired['status'] ?? '') === 'VARIANT_REQUIRED'
    && count($cseVariantRequired['recommendations'] ?? []) === 1
    && ($cseVariantRequired['recommendations'][0]['action'] ?? '') === '/quiz'
    && count(array_filter($cseVariantRequired['subjects'] ?? [], static fn(array $row): bool => ($row['status'] ?? '') === 'VARIANT_REQUIRED')) > 0,
    'CSE readiness offered subject starts before the required examination level was selected.');
$cpa = LearningReadinessService::build([], ['preferred_board' => 'CPA']);
$assert(($cpa['status'] ?? '') === 'INSUFFICIENT_PRACTICE_DATA'
    && ($cpa['blueprint_context']['status'] ?? '') === 'WEIGHTING_UNAVAILABLE'
    && array_key_exists('official_pass_probability', $cpa['blueprint_context'] ?? [])
    && $cpa['blueprint_context']['official_pass_probability'] === null,
    'CPA readiness invented a blueprint or official pass estimate.');

echo "[PASS] mixed-board subjects use saved eligibility placements; answer totals, readiness, cold-start, and CPA blueprint limitations are accurate.\n";
