<?php

declare(strict_types=1);

final class QuizResultSessionReader
{
    /**
     * @return array{questions:array,answers:array,session:array}
     */
    public static function read(): array
    {
        return [
            "questions" => SessionService::get("questions", []),
            "answers" => SessionService::get("answers", []),
            "session" => SessionService::get("quiz_session", []),
        ];
    }
}
