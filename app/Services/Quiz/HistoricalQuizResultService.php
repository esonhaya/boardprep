<?php

declare(strict_types=1);

namespace App\Services\Quiz;

use App\Core\App;
use App\Repositories\AttemptRepository;
use App\Services\Learning\LearnerIdentityService;
use App\Services\Question\QuestionIdMigrationService;

final class HistoricalQuizResultService
{
    public static function findForLearner(string $attemptId): ?array
    {
        $attemptId = trim($attemptId);
        if ($attemptId === '') {
            return null;
        }

        $attempt = App::container()
            ->get(AttemptRepository::class)
            ->findForLearner($attemptId, LearnerIdentityService::current());

        if ($attempt === null) {
            return null;
        }

        $summary = self::summary($attempt);
        if ($summary === null) {
            return null;
        }

        $review = self::review($attempt['review'] ?? null);
        $review = QuestionIdMigrationService::canonicalizeReview($review, $attempt);
        $actions = QuizResultActionService::build($attempt, $summary, $review);
        $missedQuestionIds = self::missedQuestionIds($review);
        foreach ($actions as &$action) {
            if (str_starts_with((string) ($action['url'] ?? ''), '/flashcards/missed')) {
                if ($missedQuestionIds === []) {
                    $action = null;
                    continue;
                }
                $action['url'] = '/flashcards/missed?attempt_id=' . rawurlencode($attemptId);
            }
        }
        unset($action);
        $actions = array_values(array_filter($actions));

        $actions[] = [
            'label' => 'Back to History',
            'reason' => 'Return to your completed quiz attempts.',
            'url' => '/history',
        ];
        $actions[] = [
            'label' => 'Dashboard',
            'reason' => 'Return to your learner overview.',
            'url' => '/dashboard',
        ];

        return [
            'attempt' => $attempt,
            'summary' => $summary,
            'review' => $review,
            'actions' => $actions,
            'completedAt' => self::completedAt($attempt),
            'reviewUnavailable' => $review === [],
        ];
    }

    public static function missedQuestionIds(array $review): array
    {
        $ids = [];
        foreach ($review as $item) {
            if (!is_array($item)
                || ($item['correct'] ?? null) !== false
                || ($item['answered'] ?? false) !== true) {
                continue;
            }

            $question = $item['question'] ?? null;
            $id = is_array($question) && is_scalar($question['id'] ?? null)
                ? trim((string) $question['id'])
                : '';
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Return marks only for attempts that contain a valid versioned snapshot.
     * Null means the attempt predates saved review marks or its metadata is invalid.
     *
     * @return array<int,string>|null
     */
    public static function markedQuestionIds(array $attempt, array $review): ?array
    {
        $snapshot = $attempt['review_flag_snapshot'] ?? null;
        if (!is_array($snapshot) || ($snapshot['version'] ?? null) !== 1
            || !is_array($snapshot['marked_question_ids'] ?? null)
            || !array_is_list($snapshot['marked_question_ids'])) {
            return null;
        }

        $reviewIds = [];
        foreach ($review as $item) {
            $id = is_array($item) && is_array($item['question'] ?? null)
                && is_scalar($item['question']['id'] ?? null)
                ? trim((string) $item['question']['id'])
                : '';
            if ($id !== '') {
                $reviewIds[$id] = true;
            }
        }

        $marked = [];
        foreach ($snapshot['marked_question_ids'] as $rawId) {
            if (!is_scalar($rawId) || trim((string) $rawId) === '') {
                return null;
            }
            $id = trim((string) $rawId);
            if (!isset($reviewIds[$id]) || isset($marked[$id])) {
                return null;
            }
            $marked[$id] = true;
        }

        return array_keys($marked);
    }

    private static function summary(array $attempt): ?array
    {
        foreach (['score', 'total', 'percentage'] as $field) {
            if (!is_numeric($attempt[$field] ?? null)
                || !is_finite((float) $attempt[$field])) {
                return null;
            }
        }

        $score = (int) $attempt['score'];
        $total = (int) $attempt['total'];
        $percentage = (float) $attempt['percentage'];
        if ($score < 0 || $total < 0 || $score > $total
            || $percentage < 0 || $percentage > 100) {
            return null;
        }

        return [
            'score' => $score,
            'correct' => $score,
            'incorrect' => self::intValue($attempt['incorrect'] ?? 0),
            'unanswered' => self::intValue($attempt['unanswered'] ?? 0),
            'answered' => self::intValue($attempt['answered'] ?? 0),
            'total' => $total,
            'percentage' => $percentage,
        ];
    }

    private static function review(mixed $review): array
    {
        if (!is_array($review)) {
            return [];
        }

        return array_values(array_filter(
            $review,
            static fn (mixed $item): bool => is_array($item)
                && is_array($item['question'] ?? null)
        ));
    }

    private static function completedAt(array $attempt): ?string
    {
        foreach (['completed_at', 'date', 'started_at'] as $field) {
            if (is_string($attempt[$field] ?? null)
                && trim($attempt[$field]) !== '') {
                return $attempt[$field];
            }
        }

        return null;
    }

    private static function intValue(mixed $value): int
    {
        return is_numeric($value) && is_finite((float) $value)
            ? max(0, (int) $value)
            : 0;
    }
}
