<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Services\Learning\Recommendation\LearningAttemptContext;
use App\Services\Learning\Recommendation\LearningQuestionCatalog;
use App\Services\Shared\TaxonomyStorageService;

/** Transparent practice coverage summary; this is not an exam-pass predictor. */
final class LearningReadinessService
{
    private const MIN_OBSERVED_ANSWER_EVENTS = 5;
    /** @var array<string,string>|null */
    private static ?array $boardAliasIndex = null;
    /** @var array<string,string>|null */
    private static ?array $subjectAliasIndex = null;

    public static function build(array $attempts, array $preferences = []): array
    {
        $attempts = LearningHistoryService::ordered($attempts);
        $scope = self::scope($attempts, $preferences);
        if ($scope === null) {
            $hasHistory = $attempts !== [];
            return [
                'status' => $hasHistory ? 'BOARD_NOT_SELECTED' : 'INSUFFICIENT_PRACTICE_DATA',
                'indicator_type' => 'PRACTICE_COVERAGE_AND_OBSERVED_PERFORMANCE',
                'is_pass_probability' => false,
                'board' => '',
                'board_label' => '',
                'variant_label' => '',
                'completed_attempts' => 0,
                'answered_questions' => 0,
                'unattributed_answer_events' => 0,
                'subject_coverage' => ['practiced' => 0, 'registered' => 0],
                'subjects' => [],
                'persistent_mistakes' => [],
                'explanations' => [$hasHistory
                    ? 'Choose a board to see preparation activity in that board context.'
                    : 'Complete a practice session to build an evidence-based preparation overview.'],
                'recommendations' => [
                    ['title' => 'Choose a board', 'reason' => 'Board-specific subject coverage depends on the examination you select.', 'action' => '/exams', 'label' => 'Explore examinations'],
                ],
                'blueprint_context' => null,
            ];
        }

        $scopeAttempts = array_values(array_filter(
            $attempts,
            static fn(array $attempt): bool => self::matchesScope($attempt, $scope)
        ));
        $subjectPerformance = SubjectPerformanceService::summarize($scopeAttempts);
        $performanceBySubject = [];
        foreach ($subjectPerformance as $row) {
            $performanceBySubject[self::subjectKey((string) ($row['subject'] ?? ''))] = $row;
        }

        $subjectLabels = [];
        foreach (TaxonomyStorageService::subjectsByBoard($scope['board']) as $relation) {
            $subjectId = self::text($relation['subject_id'] ?? '');
            if ($subjectId === '' || isset($subjectLabels[$subjectId])) {
                continue;
            }
            $subjectLabels[$subjectId] = TaxonomyStorageService::label('subjects', $subjectId);
        }
        $variantRequired = self::variantRequired($scope);
        $eligibleQuestionCounts = $variantRequired
            ? []
            : LearningQuestionCatalog::eligibleSubjectCounts($scope, $subjectLabels);

        $registeredSubjects = [];
        foreach ($subjectLabels as $subjectId => $subjectLabel) {
            $row = $performanceBySubject[self::subjectKey($subjectId)] ?? null;
            $attemptCount = is_array($row) ? max(0, (int) ($row['attempts'] ?? 0)) : 0;
            $answeredCount = is_array($row) ? max(0, (int) ($row['answered_questions'] ?? 0)) : 0;
            $eligibleQuestionCount = $variantRequired
                ? null
                : max(0, (int) ($eligibleQuestionCounts[$subjectId] ?? 0));
            $state = $attemptCount === 0
                ? ($variantRequired
                    ? 'VARIANT_REQUIRED'
                    : ($eligibleQuestionCount === 0 ? 'NO_ELIGIBLE_PRACTICE_POOL' : 'UNASSESSED'))
                : (($attemptCount < 2 || $answeredCount < self::MIN_OBSERVED_ANSWER_EVENTS)
                    ? 'LIMITED_EVIDENCE'
                    : 'OBSERVED_PERFORMANCE');
            $registeredSubjects[$subjectId] = [
                'subject' => $subjectId,
                'label' => $subjectLabel,
                'status' => $state,
                'completed_attempts' => $attemptCount,
                'answered_questions' => $answeredCount,
                'eligible_question_count' => $eligibleQuestionCount,
                'correct_answers' => is_array($row) ? (int) ($row['correct_answers'] ?? 0) : 0,
                'accuracy' => $state === 'OBSERVED_PERFORMANCE' ? ($row['accuracy'] ?? null) : null,
                'recent_accuracy' => $state === 'OBSERVED_PERFORMANCE' ? ($row['recent_accuracy'] ?? null) : null,
                'recent_accuracy_status' => is_array($row) ? (string) ($row['recent_accuracy_status'] ?? 'NO_ANSWERED_QUESTIONS') : 'NO_ANSWERED_QUESTIONS',
                'trend' => is_array($row) ? (string) ($row['trend'] ?? 'INSUFFICIENT_EVIDENCE') : 'INSUFFICIENT_EVIDENCE',
                'last_practiced_at' => is_array($row) ? ($row['last_practiced_at'] ?? null) : null,
            ];
        }

        $historicallyPracticed = count(array_filter(
            $registeredSubjects,
            static fn(array $row): bool => $row['completed_attempts'] > 0
        ));
        $practiced = $variantRequired
            ? 0
            : count(array_filter(
                $registeredSubjects,
                static fn(array $row): bool => $row['completed_attempts'] > 0
                    && (int) ($row['eligible_question_count'] ?? 0) > 0
            ));
        $availableSubjectCount = $variantRequired
            ? null
            : count(array_filter(
                $registeredSubjects,
                static fn(array $row): bool => (int) ($row['eligible_question_count'] ?? 0) > 0
            ));
        $unattributedAnswerEvents = self::unattributedAnswerEvents($scopeAttempts);
        $performance = LearningProgressService::build($scopeAttempts);
        $topics = TopicPerformanceService::summarize($scopeAttempts);
        $persistentMistakes = [];
        foreach ($topics as $topic) {
            if ((int) ($topic['repeated_mistakes'] ?? 0) < 1) {
                continue;
            }
            $persistentMistakes[] = [
                'topic' => (string) ($topic['topic'] ?? ''),
                'subject' => (string) ($topic['subject'] ?? ''),
                'repeated_mistakes' => (int) $topic['repeated_mistakes'],
                'attempts' => (int) ($topic['attempts'] ?? 0),
                'accuracy' => (int) ($topic['average'] ?? 0),
            ];
        }
        usort($persistentMistakes, static fn(array $a, array $b): int =>
            ($b['repeated_mistakes'] <=> $a['repeated_mistakes'])
            ?: ($a['accuracy'] <=> $b['accuracy'])
            ?: strcasecmp($a['topic'], $b['topic'])
        );
        $persistentMistakes = array_slice($persistentMistakes, 0, 3);

        $explanations = [];
        if ($scopeAttempts === []) {
            $explanations[] = 'No completed practice is recorded yet for this board and candidate variant.';
        }
        if ($variantRequired) {
            $explanations[] = self::variantRequirementExplanation($scope);
        }
        if ($unattributedAnswerEvents > 0) {
            $explanations[] = number_format($unattributedAnswerEvents)
                . ' historical answered question event' . ($unattributedAnswerEvents === 1 ? '' : 's')
                . ' could not be assigned to a subject because the saved session placement is missing. They remain in overall history and are omitted from subject comparisons.';
        }
        $unassessed = array_values(array_filter(
            $registeredSubjects,
            static fn(array $row): bool => $row['status'] === 'UNASSESSED'
        ));
        if ($unassessed !== []) {
            $labels = array_map(static fn(array $row): string => $row['label'], array_slice($unassessed, 0, 3));
            $explanations[] = 'No completed answers are recorded in ' . implode(', ', $labels)
                . (count($unassessed) > 3 ? ', and other configured subjects.' : '.')
                . ' This is an unassessed area, not a demonstrated weakness.';
        }
        $unavailable = array_values(array_filter(
            $registeredSubjects,
            static fn(array $row): bool => $row['status'] === 'NO_ELIGIBLE_PRACTICE_POOL'
        ));
        if ($unavailable !== []) {
            $labels = array_map(static fn(array $row): string => $row['label'], array_slice($unavailable, 0, 3));
            $explanations[] = 'No currently eligible questions are available in ' . implode(', ', $labels)
                . (count($unavailable) > 3 ? ', or other configured subjects.' : '. This reflects pool availability, not learner performance.');
        }
        foreach ($registeredSubjects as $subject) {
            if ($subject['status'] === 'LIMITED_EVIDENCE') {
                $explanations[] = $subject['label'] . ' has limited evidence ('
                    . $subject['completed_attempts'] . ' completed session'
                    . ($subject['completed_attempts'] === 1 ? '' : 's') . ', '
                    . $subject['answered_questions'] . ' answered question events); no proficiency label is assigned.';
                break;
            }
        }
        foreach ($persistentMistakes as $mistake) {
            $explanations[] = 'Repeated incorrect responses remain in the history for '
                . TaxonomyStorageService::label('taxonomy/topics', $mistake['topic'])
                . '; a later correct answer does not erase the earlier attempts.';
            break;
        }
        if ($explanations === []) {
            $explanations[] = 'Every configured subject has at least one completed attempt. Coverage does not establish proficiency or predict an official examination result.';
        }

        $recommendations = self::recommendations($scope, $registeredSubjects, $persistentMistakes, $variantRequired);
        $blueprint = \ExamBlueprintPreviewService::forSetup($scope['board'], '*', 20);
        $boardRecord = self::boardRecord($scope['board']);
        $blueprintContext = [
            'status' => (string) ($blueprint['status'] ?? 'CONFIGURATION_GAP'),
            'label' => (string) ($blueprint['status_label'] ?? 'Blueprint context unavailable'),
            'source_id' => (string) ($blueprint['source_id'] ?? ''),
            'note' => (string) ($blueprint['note'] ?? ''),
            'official_pass_probability' => null,
            'claim' => 'This overview summarizes BoardPrep practice activity. It is not an official examination pass predictor.',
        ];
        if (($boardRecord['id'] ?? '') === 'architecture') {
            $blueprintContext['content_depth_caveat'] = 'The registered Architecture practice pools have documented content-depth limits; subject activity here does not establish complete official topic coverage.';
        }

        return [
            'status' => $variantRequired
                ? 'VARIANT_REQUIRED'
                : ($scopeAttempts === [] ? 'INSUFFICIENT_PRACTICE_DATA' : 'PRACTICE_DATA_AVAILABLE'),
            'indicator_type' => 'PRACTICE_COVERAGE_AND_OBSERVED_PERFORMANCE',
            'is_pass_probability' => false,
            'board' => $scope['board'],
            'board_code' => (string) ($boardRecord['code'] ?? $scope['board']),
            'board_label' => TaxonomyStorageService::label('boards', $scope['board']),
            'variant_label' => self::variantLabel($scope),
            'completed_attempts' => (int) ($performance['completedAttempts'] ?? 0),
            'answered_questions' => (int) ($performance['answeredCount'] ?? 0),
            'unattributed_answer_events' => $unattributedAnswerEvents,
            'accuracy' => (int) ($performance['answeredCount'] ?? 0) > 0 ? ($performance['accuracy'] ?? null) : null,
            'recent_accuracy' => $performance['recentAccuracy'] ?? null,
            'recent_accuracy_status' => (string) ($performance['recentAccuracyStatus'] ?? 'NO_ANSWERED_QUESTIONS'),
            'recent_attempts' => (int) ($performance['recentAttemptCount'] ?? 0),
            'subject_coverage' => [
                'practiced' => $practiced,
                'historically_practiced' => $historicallyPracticed,
                'configured' => count($registeredSubjects),
                'eligible' => $availableSubjectCount,
                'registered' => $availableSubjectCount ?? count($registeredSubjects),
                'meaning' => 'Practiced subjects are currently eligible configured pools with at least one recorded completed attempt; this is coverage, not proficiency.',
            ],
            'subjects' => array_values($registeredSubjects),
            'persistent_mistakes' => $persistentMistakes,
            'explanations' => array_values(array_unique($explanations)),
            'recommendations' => $recommendations,
            'blueprint_context' => $blueprintContext,
        ];
    }

    private static function scope(array $attempts, array $preferences): ?array
    {
        $boardInput = self::text($preferences['preferred_board'] ?? '');
        $latest = null;
        if ($boardInput === '') {
            $latest = $attempts[0] ?? null;
            if (is_array($latest)) {
                $boardInput = self::text(LearningAttemptContext::fromAttempt($latest)['board'] ?? '');
            }
        }
        $board = self::canonicalBoard($boardInput);
        if ($board === '') {
            return null;
        }

        $variant = [
            'let_level' => strtoupper(self::text($preferences['let_level'] ?? '')),
            'let_specialization' => strtolower(self::text($preferences['let_specialization'] ?? '')),
            'civil_service_level' => strtoupper(self::text($preferences['civil_service_level'] ?? '')),
        ];
        if ($latest === null || ($boardInput !== '' && self::canonicalBoard($boardInput) !== self::canonicalBoard(self::text(LearningAttemptContext::fromAttempt($latest)['board'] ?? '')))) {
            $latest = null;
            foreach ($attempts as $attempt) {
                $context = LearningAttemptContext::fromAttempt($attempt);
                if (self::canonicalBoard(self::text($context['board'] ?? '')) === $board) {
                    $latest = $attempt;
                    break;
                }
            }
        }
        if ($latest !== null) {
            $latestContext = LearningAttemptContext::fromAttempt($latest);
            foreach (['let_level', 'let_specialization', 'civil_service_level'] as $field) {
                if ($variant[$field] === '') {
                    $variant[$field] = $field === 'let_specialization'
                        ? strtolower(self::text($latestContext[$field] ?? ''))
                        : strtoupper(self::text($latestContext[$field] ?? ''));
                }
            }
        }
        return ['board' => $board, ...$variant];
    }

    private static function matchesScope(array $attempt, array $scope): bool
    {
        $context = LearningAttemptContext::fromAttempt($attempt);
        if (self::canonicalBoard(self::text($context['board'] ?? '')) !== $scope['board']) {
            return false;
        }
        foreach (['let_level', 'let_specialization', 'civil_service_level'] as $field) {
            $left = $field === 'let_specialization'
                ? strtolower(self::text($context[$field] ?? ''))
                : strtoupper(self::text($context[$field] ?? ''));
            if ($left !== $scope[$field]) {
                return false;
            }
        }
        return true;
    }

    private static function recommendations(array $scope, array $subjects, array $persistentMistakes, bool $variantRequired): array
    {
        $recommendations = [];
        if ($variantRequired) {
            $recommendations[] = [
                'title' => 'Choose your examination variant',
                'reason' => self::variantRequirementExplanation($scope),
                'action' => '/quiz',
                'label' => 'Choose a level or specialization',
            ];
        }
        $hasEligiblePool = !$variantRequired && array_sum(array_map(
            static fn(array $row): int => max(0, (int) ($row['eligible_question_count'] ?? 0)),
            $subjects
        )) > 0;
        if ($hasEligiblePool && $scope['board'] === 'let' && $scope['let_level'] !== '') {
            // Specialization eligibility is placement-specific. A board-level
            // subject list is not enough evidence to recommend a particular
            // specialization pool, so route to the existing variant-aware
            // balanced selector instead.
            $action = StudyActionService::quizForTopic(
                'General', '*', 'practice', 10, 'mixed', $scope['board'],
                $scope['let_level'], $scope['let_specialization'], ''
            );
            $recommendations[] = [
                'title' => 'Start balanced LET practice',
                'reason' => 'The selected candidate variant controls eligible subject placements; the practice start service will enforce those placements.',
                'action' => StudyActionService::url($action),
                'label' => 'Start balanced practice',
            ];
        }
        foreach ($subjects as $subject) {
            if ($variantRequired || ($subject['status'] ?? '') !== 'UNASSESSED'
                || (int) ($subject['eligible_question_count'] ?? 0) < 1) {
                continue;
            }
            if ($scope['board'] === 'let' && $scope['let_level'] !== '') {
                continue;
            }
            if ($scope['board'] === 'civil-service'
                && $scope['civil_service_level'] === 'PROFESSIONAL'
                && ($subject['subject'] ?? '') === 'clerical-ability') {
                // The input normalizer rejects Clerical Ability for the
                // Professional examination, even though it remains registered
                // as a general board subject for Subprofessional candidates.
                continue;
            }
            $action = StudyActionService::quizForTopic(
                'General',
                (string) $subject['subject'],
                'practice',
                5,
                'mixed',
                $scope['board'],
                $scope['let_level'],
                $scope['let_specialization'],
                $scope['civil_service_level']
            );
            $recommendations[] = [
                'title' => 'Practice ' . $subject['label'],
                'reason' => 'This configured subject has no completed attempt in the selected board and variant yet.',
                'action' => StudyActionService::url($action),
                'label' => 'Try this subject',
                'subject' => $subject['subject'],
            ];
            if (count($recommendations) >= 2) {
                break;
            }
        }
        if ($persistentMistakes !== []) {
            array_unshift($recommendations, [
                'title' => 'Review recorded mistakes',
                'reason' => 'Historical incorrect responses remain available for targeted recovery.',
                'action' => '/mistake-notebook',
                'label' => 'Open Mistake Notebook',
            ]);
        }
        if ($recommendations === [] && $persistentMistakes !== []) {
            $recommendations[] = [
                'title' => 'Browse published study materials',
                'reason' => 'No currently eligible subject pool was found for a new practice action; the Study Library remains available.',
                'action' => '/study-library',
                'label' => 'Open Study Library',
            ];
        }
        if ($recommendations === [] && !$variantRequired && !$hasEligiblePool) {
            $recommendations[] = [
                'title' => 'Browse published study materials',
                'reason' => 'No currently eligible practice questions are available in this board and variant; published study material can still be reviewed.',
                'action' => '/study-library',
                'label' => 'Open Study Library',
            ];
        }
        if ($recommendations === []) {
            $action = StudyActionService::quizForTopic(
                'General', '*', 'practice', 10, 'mixed', $scope['board'],
                $scope['let_level'], $scope['let_specialization'], $scope['civil_service_level']
            );
            $recommendations[] = [
                'title' => 'Continue balanced practice',
                'reason' => 'Use the existing board and variant eligibility filters to continue practice.',
                'action' => StudyActionService::url($action),
                'label' => 'Start balanced practice',
            ];
        }
        return array_slice($recommendations, 0, 3);
    }

    private static function variantRequired(array $scope): bool
    {
        if ($scope['board'] === 'civil-service') {
            return !in_array($scope['civil_service_level'], ['PROFESSIONAL', 'SUBPROFESSIONAL'], true);
        }
        if ($scope['board'] === 'let') {
            $validation = \App\Services\Quiz\LET\LETVariantRegistry::validateConfiguration([
                'level' => $scope['let_level'],
                'specialization_id' => $scope['let_specialization'],
            ]);
            return ($validation['valid'] ?? false) !== true;
        }
        return false;
    }

    private static function variantRequirementExplanation(array $scope): string
    {
        if ($scope['board'] === 'civil-service') {
            return 'Choose Professional or Subprofessional before subject coverage and practice pools can be scoped accurately.';
        }
        return 'Choose a valid LET level and registered specialization before subject coverage and practice pools can be scoped accurately.';
    }

    private static function unattributedAnswerEvents(array $attempts): int
    {
        $unattributed = 0;
        foreach ($attempts as $attempt) {
            $context = LearningAttemptContext::fromAttempt($attempt);
            $review = is_array($attempt['review'] ?? null) ? $attempt['review'] : [];
            $reviewEvents = 0;
            foreach ($review as $item) {
                if (!is_array($item) || ($item['answered'] ?? false) !== true
                    || !is_bool($item['correct'] ?? null)) {
                    continue;
                }
                $reviewEvents++;
                $question = is_array($item['question'] ?? null) ? $item['question'] : [];
                $placement = LearningAttemptContext::fromQuestion($question, $context);
                $subject = self::text($placement['subject'] ?? '');
                if (($placement['question_placement_resolved'] ?? false) !== true
                    || $subject === '' || $subject === '*') {
                    $unattributed++;
                }
            }
            if ($reviewEvents === 0 && self::text($context['subject'] ?? '') === '*') {
                $unattributed += max(0, (int) ($attempt['answered'] ?? 0));
            }
        }
        return $unattributed;
    }

    private static function canonicalBoard(string $board): string
    {
        $key = strtolower(trim($board));
        if (self::$boardAliasIndex === null) {
            self::$boardAliasIndex = [];
            foreach (TaxonomyStorageService::boards() as $record) {
                if (!is_array($record)) {
                    continue;
                }
                $id = strtolower(self::text($record['id'] ?? ''));
                if ($id === '') {
                    continue;
                }
                self::$boardAliasIndex[$id] = $id;
                foreach (['code', 'name'] as $field) {
                    $alias = strtolower(self::text($record[$field] ?? ''));
                    if ($alias !== '') {
                        self::$boardAliasIndex[$alias] = $id;
                    }
                }
            }
        }
        if (isset(self::$boardAliasIndex[$key])) {
            return self::$boardAliasIndex[$key];
        }
        return match ($key) {
            'cse', 'civil service', 'civil-service' => 'civil-service',
            'cle' => 'criminologist',
            'nle' => 'nursing',
            'ple' => 'psychometrician',
            'ce' => 'civil-engineering',
            'me' => 'mechanical-engineering',
            'ree' => 'registered-electrical-engineering',
            'arch' => 'architecture',
            default => $key,
        };
    }

    private static function boardRecord(string $boardId): array
    {
        foreach (TaxonomyStorageService::boards() as $record) {
            if (is_array($record) && strtolower(self::text($record['id'] ?? '')) === strtolower($boardId)) {
                return $record;
            }
        }
        return [];
    }

    private static function subjectKey(string $subject): string
    {
        $key = strtolower(trim($subject));
        if (self::$subjectAliasIndex === null) {
            self::$subjectAliasIndex = [];
            foreach (TaxonomyStorageService::subjects() as $record) {
                if (!is_array($record)) {
                    continue;
                }
                $id = strtolower(self::text($record['id'] ?? ''));
                if ($id === '') {
                    continue;
                }
                foreach (['id', 'name', 'code'] as $field) {
                    $alias = strtolower(self::text($record[$field] ?? ''));
                    if ($alias !== '') {
                        self::$subjectAliasIndex[$alias] = $id;
                    }
                }
            }
        }
        return self::$subjectAliasIndex[$key] ?? $key;
    }

    private static function variantLabel(array $scope): string
    {
        if ($scope['civil_service_level'] !== '') {
            return ucfirst(strtolower(str_replace('_', ' ', $scope['civil_service_level'])));
        }
        if ($scope['let_level'] !== '') {
            $label = ucfirst(strtolower($scope['let_level']));
            if ($scope['let_specialization'] !== '') {
                $specialization = TaxonomyStorageService::find('let-specializations', $scope['let_specialization']);
                $label .= ' · ' . (is_array($specialization)
                    ? self::text($specialization['official_name'] ?? $scope['let_specialization'])
                    : TaxonomyStorageService::label('let-specializations', $scope['let_specialization']));
            }
            return $label;
        }
        return '';
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
