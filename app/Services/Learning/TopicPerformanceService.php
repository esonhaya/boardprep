<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Services\Learning\Recommendation\TopicPerformanceAccumulator;

final class TopicPerformanceService
{
    public static function summarize(array $attempts): array
    {
        $attempts = LearningHistoryService::ordered(LearningAttemptNormalizer::all($attempts));
        $topics = TopicPerformanceAccumulator::summarize($attempts);
        usort($topics, static function (array $a, array $b): int {
            $average = $b['average'] <=> $a['average'];
            return $average !== 0 ? $average : self::tieBreak($a, $b);
        });
        return array_values($topics);
    }

    public static function weakest(array $attempts, int $limit = 3): array
    {
        $attempts = LearningHistoryService::ordered(LearningAttemptNormalizer::all($attempts));
        $topics = TopicPerformanceAccumulator::summarize($attempts);
        usort($topics, static function (array $a, array $b): int {
            $repeatMistakes = (int) ($b['repeated_mistakes'] ?? 0) <=> (int) ($a['repeated_mistakes'] ?? 0);
            if ($repeatMistakes !== 0) {
                return $repeatMistakes;
            }
            $average = $a['average'] <=> $b['average'];
            if ($average !== 0) {
                return $average;
            }
            $decline = (($b['trend'] ?? '') === 'DECLINING') <=> (($a['trend'] ?? '') === 'DECLINING');
            if ($decline !== 0) {
                return $decline;
            }
            $evidence = (int) ($b['attempts'] ?? 0) <=> (int) ($a['attempts'] ?? 0);
            return $evidence !== 0 ? $evidence : self::tieBreak($a, $b);
        });
        return array_slice($topics, 0, max(0, $limit));
    }

    private static function tieBreak(array $a, array $b): int
    {
        $subject = strcasecmp(
            (string) ($a['subject'] ?? ''),
            (string) ($b['subject'] ?? '')
        );
        if ($subject !== 0) {
            return $subject;
        }
        $topic = strcasecmp((string) ($a['topic'] ?? ''), (string) ($b['topic'] ?? ''));
        if ($topic !== 0) {
            return $topic;
        }
        foreach (['board', 'let_level', 'let_specialization', 'civil_service_level'] as $field) {
            $context = strcasecmp((string) ($a[$field] ?? ''), (string) ($b[$field] ?? ''));
            if ($context !== 0) {
                return $context;
            }
        }
        return 0;
    }
}
