<?php

require_once __DIR__ . "/QuestionFilterService.php";

class QuizGenerationService
{
    public static function generate(array $questions, array $options = []): array
    {
        $questions = QuestionFilterService::filter(
            $questions,
            $options
        );

        if (!empty($options["shuffle"])) {
            shuffle($questions);
        }

        if (!empty($options["limit"])) {
            $questions = array_slice(
                $questions,
                0,
                $options["limit"]
            );
        }

        return array_values($questions);
    }
}
