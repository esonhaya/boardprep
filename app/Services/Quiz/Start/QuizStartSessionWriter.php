<?php

declare(strict_types=1);

namespace App\Services\Quiz\Start;

final class QuizStartSessionWriter
{
    public static function write(object $specification, array $questions): void
    {
        \SessionService::set('quiz_session', QuizStartSessionPayloadFactory::create($specification, $questions));
        \SessionService::set('questions', $questions);
        \SessionService::set('answers', []);
        \SessionService::set('feedback', null);
        \SessionService::set('mode', $specification->mode);
    }
}
