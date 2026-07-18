<?php

class QuizGenerator
{
    public static function generate(array $questions, int $count = 10): array
    {
        shuffle($questions);

        return array_slice($questions, 0, min($count, count($questions)));
    }
}
