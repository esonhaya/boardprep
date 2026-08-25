<?php

declare(strict_types=1);

final class ResultRecordFactory
{
    /**
     * @param array{userAnswer:string,correctAnswer:string,answered:bool,correct:bool} $evaluation
     * @return array<string,mixed>
     */
    public static function create(array $question, array $evaluation): array
    {
        return [
            "question" => $question["question"],
            "choices" => $question["choices"] ?? [],
            "userAnswer" => $evaluation["userAnswer"],
            "correctAnswer" => $evaluation["correctAnswer"],
            "correct" => $evaluation["correct"],
            "answered" => $evaluation["answered"],
            "explanation" => $question["explanation"] ?? "",
        ];
    }
}
