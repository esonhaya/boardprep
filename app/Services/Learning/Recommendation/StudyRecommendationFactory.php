<?php

declare(strict_types=1);

namespace App\Services\Learning\Recommendation;

use App\Services\Learning\StudyActionService;
use App\Services\Shared\TaxonomyStorageService;

final class StudyRecommendationFactory
{
    public static function forTopic(array $topic): ?array
    {
        $name = self::text($topic['topic'] ?? '');
        if ($name === '') {
            return null;
        }

        $subject = self::text($topic['subject'] ?? '');
        $action = StudyActionService::quizForTopic($name, $subject);
        $displayName = TaxonomyStorageService::label('taxonomy/topics', $name);

        return [
            'type' => 'topic',
            'title' => "Review {$displayName}",
            'reason' => 'Current average: ' . (int) ($topic['average'] ?? 0) . '%',
            'topic' => $name,
            'subject' => $action['subject'],
            'action' => StudyActionService::url($action),
            'label' => "Practice {$displayName}",
        ];
    }

    public static function general(string $title, string $reason): array
    {
        $action = StudyActionService::quizForTopic('General', '');

        return [
            'type' => 'practice',
            'title' => $title,
            'reason' => $reason,
            'topic' => 'General',
            'subject' => $action['subject'],
            'action' => StudyActionService::url($action),
            'label' => 'Start a practice quiz',
        ];
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
