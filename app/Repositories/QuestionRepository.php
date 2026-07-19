<?php

class QuestionRepository
{
    private const FILE =
        __DIR__ . "/../../storage/questions.json";

    public static function all(): array
    {
        if (!file_exists(self::FILE)) {
            return [];
        }

        $questions = json_decode(
            file_get_contents(self::FILE),
            true
        );

        return is_array($questions)
            ? $questions
            : [];
    }

    public static function bySubject(
        string $subject
    ): array
    {
        return array_values(
            array_filter(
                self::all(),
                fn($question) =>
                    ($question["subject"] ?? "")
                    === $subject
            )
        );
    }

    public static function byTopic(
        string $topic
    ): array
    {
        return array_values(
            array_filter(
                self::all(),
                fn($question) =>
                    ($question["topic"] ?? "")
                    === $topic
            )
        );
    }
}
