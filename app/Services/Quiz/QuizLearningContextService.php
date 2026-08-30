<?php

declare(strict_types=1);

namespace App\Services\Quiz;

use App\Services\Quiz\LearningContext\QuizAttemptContextResolver;
use App\Services\Quiz\LearningContext\QuizAttemptLearningContextFactory;

final class QuizLearningContextService
{
    public static function enrichAttempt(
        array $attempt,
        array $session,
        array $questions = []
    ): array {
        $context = QuizAttemptContextResolver::resolve($attempt, $session, $questions);

        foreach (['board', 'subject', 'domain'] as $field) {
            if (self::missing($attempt[$field] ?? null) && $context[$field] !== '') {
                $attempt[$field] = $context[$field];
            }
        }

        if (self::missing($attempt['topic'] ?? null)) {
            $attempt['topic'] = $context['topic'] !== '' ? $context['topic'] : 'General';
        }

        $attempt['topics'] = $context['topics'];
        $context['topic'] = $attempt['topic'];
        $context['board'] = self::current($attempt, 'board', $context['board']);
        $context['subject'] = self::current($attempt, 'subject', $context['subject']);
        $context['domain'] = self::current($attempt, 'domain', $context['domain']);
        $attempt['learning_context'] = QuizAttemptLearningContextFactory::create($context);

        return $attempt;
    }

    public static function topics(array $session, array $questions = []): array
    {
        return QuizAttemptContextResolver::resolve([], $session, $questions)['topics'];
    }

    private static function missing(mixed $value): bool
    {
        return !is_scalar($value) || trim((string) $value) === '';
    }

    private static function current(array $attempt, string $field, string $fallback): string
    {
        $value = $attempt[$field] ?? null;
        return self::missing($value) ? $fallback : trim((string) $value);
    }
}
