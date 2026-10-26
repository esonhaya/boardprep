<?php

declare(strict_types=1);

use App\Services\Learning\LearningAttemptNormalizer;
use App\Services\Learning\LearningHistoryService;
use App\Services\Learning\TopicPerformanceService;
use App\Services\Shared\TaxonomyStorageService;

final class AdaptiveQuizService
{
    private const HISTORY_WINDOW = 30;
    private const EXPOSURE_WINDOW = 10;
    private const WEAK_TOPIC_LIMIT = 10;
    private static ?array $subjectKeyIndex = null;

    /**
     * Prioritize questions matching the learner's known weak topics.
     *
     * Non-adaptive specifications retain the original question order.
     *
     * @param array<int,array<string,mixed>> $questions
     * @return array<int,array<string,mixed>>
     */
    public static function prioritize(
        array $questions,
        QuizSpecification $specification
    ): array {
        if (!$specification->adaptive) {
            return $questions;
        }

        return AdaptivePriorityBuilder::build(
            $questions,
            WeaknessService::all()
        );
    }

    /**
     * Build bounded signals from this learner's completed attempt history.
     * Weak topics require question-level evidence across multiple attempts;
     * recent exposure is tracked separately for fair rotation.
     *
     * @return array{weak_topics:array<int,array<string,mixed>>,question_exposure:array<string,array{count:int,last_attempt_index:int}>,history_attempt_count:int,exposure_window_attempt_count:int}|null
     */
    public static function selectionContext(QuizSpecification $specification): ?array
    {
        if (!$specification->adaptive || $specification->mode !== 'practice') {
            return null;
        }

        return self::contextFromAttempts(
            LearningHistoryService::recent(self::HISTORY_WINDOW),
            $specification
        );
    }

    /** Pure context builder for fixtures and isolated tests. */
    public static function contextFromAttempts(array $attempts, QuizSpecification $specification): array
    {
        $attempts = LearningHistoryService::ordered(LearningAttemptNormalizer::all($attempts));
        $weakTopics = [];

        foreach (TopicPerformanceService::weakest($attempts, PHP_INT_MAX) as $row) {
            if (!is_array($row)
                || ($row['accuracy_basis'] ?? '') !== 'QUESTION_LEVEL_REVIEW'
                || (int) ($row['attempts'] ?? 0) < 2
                || (int) ($row['question_review_count'] ?? 0) < 2
                || !is_numeric($row['average'] ?? null)
                || (int) $row['average'] >= 100
                || !self::matchesSpecificationContext($row, $specification)) {
                continue;
            }

            $topic = self::text($row['topic'] ?? '');
            if ($topic === '') {
                continue;
            }

            $weakTopics[] = [
                'topic' => $topic,
                'board' => self::text($row['board'] ?? ''),
                'subject' => self::text($row['subject'] ?? ''),
                'let_level' => self::text($row['let_level'] ?? ''),
                'let_specialization' => self::text($row['let_specialization'] ?? ''),
                'civil_service_level' => self::text($row['civil_service_level'] ?? ''),
                'average' => max(0, min(100, (int) $row['average'])),
                'recent_average' => is_numeric($row['recent_average'] ?? null)
                    ? max(0, min(100, (int) $row['recent_average']))
                    : null,
                'attempts' => (int) $row['attempts'],
                'repeated_mistakes' => max(0, (int) ($row['repeated_mistakes'] ?? 0)),
                'priority' => self::weakTopicPriority($row),
                'priority_basis' => is_numeric($row['recent_average'] ?? null)
                    ? '40_PERCENT_OVERALL_60_PERCENT_RECENT'
                    : 'OVERALL_ACCURACY',
            ];
            if (count($weakTopics) >= self::WEAK_TOPIC_LIMIT) {
                break;
            }
        }

        $exposure = [];
        $recentAttempts = array_slice($attempts, 0, self::EXPOSURE_WINDOW);
        foreach ($recentAttempts as $attemptIndex => $attempt) {
            $seenInAttempt = [];
            foreach (is_array($attempt['review'] ?? null) ? $attempt['review'] : [] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $question = is_array($item['question'] ?? null) ? $item['question'] : [];
                $questionId = self::text($question['id'] ?? $item['question_id'] ?? '');
                if ($questionId === '' || isset($seenInAttempt[$questionId])) {
                    continue;
                }
                $seenInAttempt[$questionId] = true;
                $exposure[$questionId] ??= ['count' => 0, 'last_attempt_index' => $attemptIndex];
                $exposure[$questionId]['count']++;
                $exposure[$questionId]['last_attempt_index'] = min(
                    $exposure[$questionId]['last_attempt_index'],
                    $attemptIndex
                );
            }
        }

        return [
            'weak_topics' => $weakTopics,
            'question_exposure' => $exposure,
            'history_attempt_count' => count($attempts),
            'exposure_window_attempt_count' => count($recentAttempts),
            'ranking_policy' => [
                'weakness_signal' => '40% overall topic accuracy gap plus 60% recent topic accuracy gap when at least two topic observations exist',
                'repeated_mistake_bonus_max' => 20,
                'recent_exposure_penalty_max' => 48,
                'topic_concentration_penalty_max' => 36,
            ],
        ];
    }

    public static function selectionExplanation(?array $context, array $questions): ?string
    {
        if ($context === null) {
            return null;
        }

        $selectedTopics = [];
        foreach ($questions as $question) {
            if (!is_array($question)) {
                continue;
            }
            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            $topic = self::text($taxonomy['topic_id'] ?? $question['topic'] ?? '');
            $subject = self::subjectKey($taxonomy['subject_id'] ?? $question['subject'] ?? '');
            if ($topic !== '' && $subject !== '') {
                $selectedTopics[strtolower($topic)][$subject] = true;
            }
        }

        $prioritized = [];
        foreach (is_array($context['weak_topics'] ?? null) ? $context['weak_topics'] : [] as $row) {
            $topic = is_array($row) ? strtolower(self::text($row['topic'] ?? '')) : '';
            $subject = is_array($row) ? self::subjectKey($row['subject'] ?? '') : '';
            if ($topic !== '' && $subject !== '' && isset($selectedTopics[$topic][$subject])) {
                $prioritized[$topic] = true;
            }
        }

        if ($prioritized !== []) {
            return sprintf(
                'Adaptive practice prioritized %d topic%s with lower accuracy across at least two completed question-level attempts. It balances recency, repeated mistakes, and topic coverage, and favors less recently seen eligible questions at the same difficulty.',
                count($prioritized),
                count($prioritized) === 1 ? '' : 's'
            );
        }

        if ((int) ($context['exposure_window_attempt_count'] ?? 0) > 0) {
            return 'No repeatedly lower-performing topic matched the questions selected for this session. Adaptive practice favors less recently encountered eligible questions and spreads selections across topics when their ranking signals are close.';
        }

        return 'There is not enough completed question-level history to target lower-performing topics or avoid recent repeats. The session keeps the configured board, subject, eligibility, and difficulty rules.';
    }

    public static function subjectKey(mixed $value): string
    {
        $text = strtolower(trim(is_scalar($value) ? (string) $value : ''));
        if ($text === '') {
            return '';
        }
        if (self::$subjectKeyIndex === null) {
            self::$subjectKeyIndex = [];
            foreach (TaxonomyStorageService::subjects() as $subject) {
                if (!is_array($subject)) {
                    continue;
                }
                $id = strtolower(trim(self::text($subject['id'] ?? '')));
                if ($id === '') {
                    continue;
                }
                foreach (['id', 'name', 'code'] as $field) {
                    $alias = strtolower(trim(self::text($subject[$field] ?? '')));
                    if ($alias !== '') {
                        self::$subjectKeyIndex[$alias] = $id;
                    }
                }
            }
        }
        return self::$subjectKeyIndex[$text] ?? $text;
    }

    private static function matchesSpecificationContext(array $row, QuizSpecification $specification): bool
    {
        if (self::boardKey($row['board'] ?? '') !== self::boardKey($specification->board)) {
            return false;
        }
        if (trim($specification->subject) !== '' && trim($specification->subject) !== '*'
            && self::subjectKey($row['subject'] ?? '') !== self::subjectKey($specification->subject)) {
            return false;
        }

        $variant = is_array($specification->letVariant) ? $specification->letVariant : [];
        $level = strtolower(trim(self::text($variant['level'] ?? '')));
        $specialization = strtolower(trim(self::text($variant['specialization_id'] ?? '')));
        $rowLevel = strtolower(trim(self::text($row['let_level'] ?? '')));
        $rowSpecialization = strtolower(trim(self::text($row['let_specialization'] ?? '')));
        if ($level !== $rowLevel || $specialization !== $rowSpecialization) {
            return false;
        }

        $civilServiceLevel = strtoupper(trim($specification->civilServiceLevel ?? ''));
        $rowCivilServiceLevel = strtoupper(trim(self::text($row['civil_service_level'] ?? '')));
        return $civilServiceLevel === $rowCivilServiceLevel;
    }

    private static function weakTopicPriority(array $row): int
    {
        $overallGap = 100 - max(0, min(100, (int) ($row['average'] ?? 100)));
        $recent = $row['recent_average'] ?? null;
        $weaknessSignal = is_numeric($recent)
            ? (int) round($overallGap * 0.4 + (100 - max(0, min(100, (int) $recent))) * 0.6)
            : $overallGap;
        $repeatedMistakeBonus = min(20, max(0, (int) ($row['repeated_mistakes'] ?? 0)) * 5);

        return max(1, min(120, $weaknessSignal + $repeatedMistakeBonus));
    }

    public static function boardKey(mixed $value): string
    {
        $board = strtolower(trim(is_scalar($value) ? (string) $value : ''));
        return in_array($board, ['cse', 'civil service', 'civil-service'], true)
            ? 'civil-service'
            : $board;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
