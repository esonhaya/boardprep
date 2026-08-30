<?php

declare(strict_types=1);

namespace App\Services\Quiz\LearningContext;

final class QuizAttemptLearningContextFactory
{
    public static function create(array $context): array
    {
        return [
            'board' => $context['board'] ?? '',
            'subject' => $context['subject'] ?? '',
            'domain' => $context['domain'] ?? '',
            'topic' => $context['topic'] ?? 'General',
            'topics' => is_array($context['topics'] ?? null) ? $context['topics'] : [],
            'mode' => $context['mode'] ?? 'practice',
            'difficulty' => $context['difficulty'] ?? 'mixed',
        ];
    }
}
