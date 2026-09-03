<?php

declare(strict_types=1);

namespace App\Services\Flashcard;

use DateTimeImmutable;

final class FlashcardScheduler
{
    public const ALGORITHM_VERSION = 'sm2-lite-v1';
    public const MIN_EASE_FACTOR = 1.3;
    public const MAX_EASE_FACTOR = 3.0;
    public const INITIAL_EASE_FACTOR = 2.5;
    public const MAX_INTERVAL_DAYS = 3650;

    /**
     * @return array{state: array<string,mixed>, result: array<string,mixed>}
     */
    public function schedule(
        array $state,
        string $rating,
        DateTimeImmutable $now
    ): array {
        $rating = FlashcardRating::normalize($rating);
        $previousInterval = max(0, (int) ($state['interval_days'] ?? 0));
        $previousEase = $this->boundedEase($state['ease_factor'] ?? self::INITIAL_EASE_FACTOR);
        $previousReviewCount = max(0, (int) ($state['review_count'] ?? 0));
        $previousLapses = max(0, (int) ($state['lapses'] ?? 0));
        $isNew = $previousReviewCount === 0
            || strtolower((string) ($state['state'] ?? 'new')) === 'new';

        $interval = $this->intervalFor(
            $rating,
            $previousInterval,
            $previousEase,
            $isNew
        );
        $ease = $this->easeFor($rating, $previousEase);
        $lapses = $previousLapses + ($rating === FlashcardRating::AGAIN ? 1 : 0);
        $reviewCount = $previousReviewCount + 1;
        $nextDue = $now->modify('+' . $interval . ' days');
        $nextState = array_merge($state, [
            'review_count' => $reviewCount,
            'last_reviewed_at' => $now->format(DATE_ATOM),
            'next_due_at' => $nextDue->format(DATE_ATOM),
            'interval_days' => $interval,
            'ease_factor' => $ease,
            'lapses' => $lapses,
            'state' => $interval === 0 || $rating === FlashcardRating::AGAIN
                ? 'learning'
                : 'review',
            'suspended' => (bool) ($state['suspended'] ?? false),
            'algorithm_version' => self::ALGORITHM_VERSION,
        ]);

        return [
            'state' => $nextState,
            'result' => [
                'card_key' => (string) ($nextState['card_key'] ?? ''),
                'rating' => $rating,
                'review_count' => $reviewCount,
                'lapses' => $lapses,
                'previous_interval' => $previousInterval,
                'next_interval' => $interval,
                'previous_due_at' => $state['next_due_at'] ?? null,
                'next_due_at' => $nextDue->format(DATE_ATOM),
                'state' => $nextState['state'],
                'suspended' => $nextState['suspended'],
                'algorithm_version' => self::ALGORITHM_VERSION,
            ],
        ];
    }

    /** @return array<string,mixed> */
    public function newState(string $cardKey, string $questionId, string $cardType): array
    {
        return [
            'card_key' => $cardKey,
            'question_id' => $questionId,
            'card_type' => $cardType,
            'review_count' => 0,
            'last_reviewed_at' => null,
            'next_due_at' => null,
            'interval_days' => 0,
            'ease_factor' => self::INITIAL_EASE_FACTOR,
            'lapses' => 0,
            'state' => 'new',
            'suspended' => false,
            'algorithm_version' => self::ALGORITHM_VERSION,
        ];
    }

    private function intervalFor(
        string $rating,
        int $previousInterval,
        float $ease,
        bool $isNew
    ): int {
        if ($isNew) {
            return match ($rating) {
                FlashcardRating::AGAIN => 0,
                FlashcardRating::HARD => 1,
                FlashcardRating::GOOD => 2,
                FlashcardRating::EASY => 4,
            };
        }

        $base = max(1, $previousInterval);
        $interval = match ($rating) {
            FlashcardRating::AGAIN => (int) floor($base * 0.2),
            FlashcardRating::HARD => (int) round($base * 1.2),
            FlashcardRating::GOOD => (int) round($base * $ease),
            FlashcardRating::EASY => (int) round($base * ($ease * 1.3)),
        };

        if ($rating === FlashcardRating::AGAIN) {
            return min(self::MAX_INTERVAL_DAYS, max(0, $interval));
        }

        return min(self::MAX_INTERVAL_DAYS, max(1, $interval));
    }

    private function easeFor(string $rating, float $ease): float
    {
        $change = match ($rating) {
            FlashcardRating::AGAIN => -0.2,
            FlashcardRating::HARD => -0.15,
            FlashcardRating::GOOD => 0.0,
            FlashcardRating::EASY => 0.15,
        };

        return $this->boundedEase($ease + $change);
    }

    private function boundedEase(mixed $value): float
    {
        $ease = is_numeric($value) ? (float) $value : self::INITIAL_EASE_FACTOR;

        return min(self::MAX_EASE_FACTOR, max(self::MIN_EASE_FACTOR, $ease));
    }
}
