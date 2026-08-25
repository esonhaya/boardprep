<?php

declare(strict_types=1);

final class QuestionScoreEvaluator
{
    /**
     * @return array{userAnswer:string,correctAnswer:string,answered:bool,correct:bool}
     */
    public static function evaluate(array $question, ?string $answer): array
    {
        $userAnswer = AnswerNormalizer::normalize($question, $answer);
        $correctAnswer = (string) ($question["answer"] ?? "");
        $answered = trim($userAnswer) !== "";
        $correct = $answered
            && strtoupper(trim($userAnswer)) === strtoupper(trim($correctAnswer));

        return [
            "userAnswer" => $userAnswer,
            "correctAnswer" => $correctAnswer,
            "answered" => $answered,
            "correct" => $correct,
        ];
    }
}
