<?php

declare(strict_types=1);

namespace App\Services\Learning;

final class LearningTimelineService
{
    public static function build(array $attempts): array
    {
        $attempts = LearningAttemptNormalizer::all($attempts);
        $timeline = [];

        foreach ($attempts as $attempt) {
            $timeline[] = [
                'date' =>
                    LearningHistoryService::dateOf($attempt),
                'timestamp' =>
                    LearningHistoryService::timestampOf($attempt),
                'percentage' =>
                    (int) ($attempt['percentage'] ?? 0),
                'score' =>
                    (int) ($attempt['score'] ?? 0),
                'total' =>
                    (int) ($attempt['total'] ?? 0),
                'mode' =>
                    (string) ($attempt['mode'] ?? 'practice'),
                'topic' =>
                    LearningHistoryService::topicOf($attempt),
                'subject' =>
                    (string) ($attempt['subject'] ?? ''),
                'completed' =>
                    (bool) ($attempt['completed'] ?? false),
            ];
        }

        usort(
            $timeline,
            static fn(array $a, array $b): int =>
                ($b['timestamp'] ?? 0)
                <=>
                ($a['timestamp'] ?? 0)
        );

        return $timeline;
    }
}
