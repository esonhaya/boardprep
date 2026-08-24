<?php

declare(strict_types=1);

namespace App\Services\Learning;

final class TopicPerformanceService
{
    public static function summarize(array $attempts): array
    {
        $topics = [];

        foreach ($attempts as $attempt) {
            $topic = LearningHistoryService::topicOf($attempt);
            $percentage = (int) ($attempt["percentage"] ?? 0);

            if (!isset($topics[$topic])) {
                $topics[$topic] = [
                    "topic" => $topic,
                    "attempts" => 0,
                    "average" => 0,
                    "best" => 0,
                    "_sum" => 0,
                ];
            }

            $topics[$topic]["attempts"]++;
            $topics[$topic]["_sum"] += $percentage;
            $topics[$topic]["best"] =
                max($topics[$topic]["best"], $percentage);
        }

        foreach ($topics as &$topic) {
            $topic["average"] =
                (int) round(
                    $topic["_sum"] / $topic["attempts"]
                );
            unset($topic["_sum"]);
        }
        unset($topic);

        usort(
            $topics,
            static fn(array $a, array $b): int =>
                $b["average"] <=> $a["average"]
        );

        return array_values($topics);
    }

    public static function weakest(array $attempts, int $limit = 3): array
    {
        $topics = self::summarize($attempts);

        usort(
            $topics,
            static fn(array $a, array $b): int =>
                $a["average"] <=> $b["average"]
        );

        return array_slice($topics, 0, max(0, $limit));
    }
}
