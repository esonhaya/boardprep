<?php

declare(strict_types=1);

namespace App\Services\Learning;

/** Small continuation model derived only from the current learner's loaded history. */
final class DashboardStudyContinuityService
{
    /** @return array<string,mixed> */
    public static function build(array $orderedAttempts): array
    {
        $latest = isset($orderedAttempts[0]) && is_array($orderedAttempts[0])
            ? $orderedAttempts[0]
            : null;
        $latestId = is_array($latest) && is_scalar($latest['id'] ?? null)
            ? trim((string) $latest['id'])
            : '';
        $latestReviewAvailable = is_array($latest)
            && self::hasSavedQuestionReview($latest['review'] ?? null);

        return [
            'state' => $latest === null ? 'NO_HISTORY' : 'RETURNING_LEARNER',
            'latest_attempt' => $latest,
            'latest_result_url' => $latestId !== ''
                ? '/history/result?id=' . rawurlencode($latestId)
                : '/history',
            'latest_review_available' => $latestReviewAvailable,
            'has_mistake_or_skipped_review' => self::hasMistakeOrSkippedReview($orderedAttempts),
        ];
    }

    private static function hasSavedQuestionReview(mixed $review): bool
    {
        if (!is_array($review)) {
            return false;
        }
        foreach ($review as $item) {
            if (is_array($item) && is_array($item['question'] ?? null)
                && is_scalar($item['question']['id'] ?? null)
                && trim((string) $item['question']['id']) !== '') {
                return true;
            }
        }
        return false;
    }

    private static function hasMistakeOrSkippedReview(array $attempts): bool
    {
        foreach ($attempts as $attempt) {
            if (!is_array($attempt)
                || !is_scalar($attempt['id'] ?? null)
                || trim((string) $attempt['id']) === '') {
                continue;
            }
            $review = is_array($attempt['review'] ?? null) ? $attempt['review'] : [];
            foreach ($review as $item) {
                if (!is_array($item)
                    || !is_array($item['question'] ?? null)
                    || !is_scalar($item['question']['id'] ?? null)
                    || trim((string) $item['question']['id']) === '') {
                    continue;
                }
                $answered = $item['answered'] ?? null;
                if ($answered === false
                    || ($answered === true && ($item['correct'] ?? null) === false)) {
                    return true;
                }
            }
        }
        return false;
    }
}
