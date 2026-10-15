<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\Learning\StudyRecommendationService;
use App\Services\Learning\TopicPerformanceService;
use App\Services\Study\StudyLibraryService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$question = [
    'id' => '221',
    'topic' => 'cse-constitutional-civics',
    'difficulty' => 'hard',
    'taxonomy' => [
        'board_id' => 'civil-service',
        'subject_id' => 'general-information',
        'topic_id' => 'cse-constitutional-civics',
    ],
];
$attempt = static function (string $id, string $date, bool $correct, ?array $q = null): array {
    return [
        'id' => $id,
        'completed' => true,
        'completed_at' => $date,
        'board' => 'CSE',
        'civil_service_level' => 'SUBPROFESSIONAL',
        'subject' => 'general-information',
        'topic' => 'cse-constitutional-civics',
        'percentage' => $correct ? 100 : 0,
        'score' => $correct ? 1 : 0,
        'total' => 1,
        'review' => [[
            'correct' => $correct,
            'answered' => true,
            'question' => $q ?? [
                'id' => '221',
                'topic' => 'cse-constitutional-civics',
                'taxonomy' => ['topic_id' => 'cse-constitutional-civics'],
            ],
        ]],
    ];
};

$emptyRecommendations = StudyRecommendationService::build([], [], 3);
$assert(($emptyRecommendations[0]['type'] ?? '') === 'practice', 'A new learner needs an actionable baseline recommendation.');
$assert(str_contains((string) ($emptyRecommendations[0]['reason'] ?? ''), 'completed attempts only'), 'Empty-state explanation must state what evidence is used.');

$single = TopicPerformanceService::summarize([$attempt('one', '2026-10-14T10:00:00Z', false, $question)])[0] ?? [];
$assert(($single['accuracy_basis'] ?? '') === 'QUESTION_LEVEL_REVIEW', 'Question-level review should be the accuracy source when present.');
$assert(($single['average'] ?? null) === 0 && ($single['attempts'] ?? 0) === 1, 'One attempt should retain observed accuracy without implying mastery.');
$assert(($single['evidence_status'] ?? '') === 'INSUFFICIENT_EVIDENCE' && ($single['trend'] ?? '') === 'INSUFFICIENT_EVIDENCE', 'One attempt must not produce a trend.');
$singleRecommendation = StudyRecommendationService::build([
    $attempt('one', '2026-10-14T10:00:00Z', false, $question),
], [$single], 1)[0] ?? [];
$singleActionQuery = [];
parse_str((string) parse_url((string) ($singleRecommendation['action'] ?? ''), PHP_URL_QUERY), $singleActionQuery);
$assert(($singleActionQuery['difficulty'] ?? '') === 'mixed', 'One difficulty-specific miss must not overfit the next practice action.');

$repeated = [
    $attempt('newer-wrong', '2026-10-14T10:00:00Z', false, $question),
    $attempt('older-wrong', '2026-10-13T10:00:00Z', false, $question),
];
$repeatedTopic = TopicPerformanceService::summarize($repeated)[0] ?? [];
$assert(($repeatedTopic['repeated_mistakes'] ?? 0) === 1, 'The same canonical question missed in two attempts should be surfaced once.');
$recommendations = StudyRecommendationService::build($repeated, [$repeatedTopic], 3);
$recommendation = $recommendations[0] ?? [];
$assert(str_contains((string) ($recommendation['reason'] ?? ''), 'Repeated misses on 1 question'), 'Repeat-mistake explanation is missing.');
$linkedMaterialIds = array_column(StudyLibraryService::forQuestion('221'), 'material_id');
$assert(in_array(($recommendation['study_material_id'] ?? ''), $linkedMaterialIds, true), 'Study path should resolve through the exact question-to-resource link.');
$assert(str_starts_with((string) ($recommendation['study_action'] ?? ''), '/study-library?material='), 'Study resource action is not a direct published-material link.');
parse_str((string) parse_url((string) ($recommendation['action'] ?? ''), PHP_URL_QUERY), $quizQuery);
$assert(($quizQuery['exam'] ?? '') === 'CSE' && ($quizQuery['civil_service_level'] ?? '') === 'SUBPROFESSIONAL', 'Retry practice must preserve board eligibility context.');
$assert(($quizQuery['difficulty'] ?? '') === 'hard' && ($recommendation['difficulty_focus'] ?? '') === 'hard', 'Repeated answered misses at one recorded difficulty should focus the retry at that label.');
$assert(str_contains((string) ($recommendation['reason'] ?? ''), 'not psychometrically calibrated'), 'Difficulty-focused guidance must disclose that stored labels are not calibrated.');

$improving = [
    $attempt('newer-correct', '2026-10-14T10:00:00Z', true, $question),
    $attempt('older-wrong', '2026-10-13T10:00:00Z', false, $question),
];
$improvement = TopicPerformanceService::summarize($improving)[0] ?? [];
$assert(($improvement['trend'] ?? '') === 'IMPROVING' && ($improvement['recent_change_points'] ?? null) === 100, 'Recent improvement should compare the newest topic result to earlier evidence.');
$assert(($improvement['repeated_mistakes'] ?? 0) === 0, 'A corrected question should no longer count as a repeated miss.');

$skippedAttempt = static function (string $id, string $date, array $q) use ($attempt): array {
    $row = $attempt($id, $date, false, $q);
    $row['review'][0]['answered'] = false;
    return $row;
};
$skipped = TopicPerformanceService::summarize([
    $skippedAttempt('skipped-newer', '2026-10-14T10:00:00Z', $question),
    $skippedAttempt('skipped-older', '2026-10-13T10:00:00Z', $question),
])[0] ?? [];
$assert($skipped === [], 'A review containing only unanswered items must not create a zero-accuracy topic attempt.');

$partial = $attempt('partial-answer', '2026-10-14T11:00:00Z', true, $question);
$partial['score'] = 1;
$partial['total'] = 2;
$partial['answered'] = 1;
$partial['percentage'] = 50;
$unansweredHardQuestion = $question;
$unansweredHardQuestion['id'] = '222';
$partial['review'] = [
    ['correct' => true, 'answered' => true, 'question' => $question],
    ['correct' => false, 'answered' => false, 'question' => $unansweredHardQuestion],
];
$partialTopic = TopicPerformanceService::summarize([$partial])[0] ?? [];
$assert(($partialTopic['average'] ?? null) === 100 && ($partialTopic['question_review_count'] ?? 0) === 1, 'Unanswered rows must not lower the accuracy denominator for answered topic evidence.');
$assert(($partialTopic['difficulty_evidence']['hard']['answered'] ?? 0) === 1
    && ($partialTopic['difficulty_evidence']['hard']['correct'] ?? 0) === 1
    && ($partialTopic['difficulty_evidence']['hard']['incorrect'] ?? 0) === 0, 'Difficulty evidence must count answered rows only.');

$unlinkedQuestion = $question;
$unlinkedQuestion['id'] = 'test-unlinked-question';
$unlinkedQuestion['topic'] = 'unlinked-concept';
$unlinkedQuestion['taxonomy']['topic_id'] = 'unlinked-concept';
$unlinked = TopicPerformanceService::summarize([
    $attempt('unlinked', '2026-10-14T10:00:00Z', false, $unlinkedQuestion),
])[0] ?? [];
$unlinkedRecommendation = StudyRecommendationService::build([
    $attempt('unlinked', '2026-10-14T10:00:00Z', false, $unlinkedQuestion),
], [$unlinked], 3)[0] ?? [];
$assert(($unlinkedRecommendation['study_action'] ?? null) === null, 'No guessed study link should be emitted when exact topic links are missing.');

$legacy = TopicPerformanceService::summarize([
    ['id' => 'legacy', 'completed' => true, 'board' => 'CE', 'subject' => 'engineering-math', 'topic' => 'statics', 'percentage' => 40],
])[0] ?? [];
$assert(($legacy['accuracy_basis'] ?? '') === 'QUIZ_CONTEXT' && ($legacy['evidence_status'] ?? '') === 'INSUFFICIENT_EVIDENCE', 'Legacy summary-only history should remain usable and explicitly labeled.');
$fresh = TopicPerformanceService::summarize([
    ['id' => 'fresh', 'completed' => true, 'board' => 'CE', 'subject' => 'engineering-math', 'topic' => 'statics', 'percentage' => 100],
])[0] ?? [];
$assert(($fresh['average'] ?? null) === 100, 'A new calculation must not reuse stale progress from an earlier call.');

$legacyLabelQuestion = [
    'id' => '1',
    'topic' => 'Subject-Verb Agreement',
    'taxonomy' => [
        'board_id' => 'let',
        'subject_id' => 'english',
        'topic_id' => 'critical-reading',
    ],
];
$legacyLabelAttempt = [
    'id' => 'legacy-topic-label',
    'completed' => true,
    'completed_at' => '2026-10-15T09:00:00Z',
    'board' => 'LET',
    'subject' => 'English',
    'topic' => 'Subject-Verb Agreement',
    'percentage' => 0,
    'score' => 0,
    'total' => 1,
    'review' => [[
        'correct' => false,
        'answered' => true,
        'question' => $legacyLabelQuestion,
    ]],
];
$secondLegacyLabelAttempt = $legacyLabelAttempt;
$secondLegacyLabelAttempt['id'] = 'legacy-topic-label-repeat';
$secondLegacyLabelAttempt['completed_at'] = '2026-10-14T09:00:00Z';
$canonicalAttempts = [$legacyLabelAttempt, $secondLegacyLabelAttempt];
$canonicalTopic = TopicPerformanceService::summarize($canonicalAttempts)[0] ?? [];
$assert(($canonicalTopic['topic'] ?? '') === 'critical-reading', 'Canonical taxonomy must override a stale legacy question topic in learner history.');
$canonicalRecommendation = StudyRecommendationService::build($canonicalAttempts, [$canonicalTopic], 1)[0] ?? [];
$assert(($canonicalRecommendation['topic'] ?? '') === 'critical-reading', 'Adaptive practice must target the canonical topic rather than a stale display label.');
$canonicalActionQuery = [];
parse_str((string) parse_url((string) ($canonicalRecommendation['action'] ?? ''), PHP_URL_QUERY), $canonicalActionQuery);
$assert(($canonicalActionQuery['topic'] ?? '') === 'critical-reading', 'Retry action must carry the canonical topic ID.');
$assert(($canonicalRecommendation['study_material_id'] ?? '') === 'let-english-communication-review', 'Canonical topic recommendation must preserve the published question-linked study path.');

echo "[PASS] Adaptive topic analytics use question review, expose insufficient evidence, and detect repeated misses and improvement.\n";
echo "[PASS] Study and retry actions retain exact board context and refuse unlinked resources.\n";
echo "[PASS] Canonical taxonomy prevents stale legacy labels from misrouting adaptive study and practice.\n";
