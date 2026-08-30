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
        $correctAnswer = is_scalar($question["answer"] ?? null)
            ? (string) $question["answer"]
            : "";
        $answered = trim($userAnswer) !== "";
        $choices = is_array($question['choices'] ?? null) ? $question['choices'] : [];
        $represented = $correctAnswer !== '' && in_array(
            strtoupper(trim($correctAnswer)),
            array_map(static fn(mixed $choice): string => is_scalar($choice) ? strtoupper(trim((string) $choice)) : '', $choices),
            true
        );
        $correct = $answered
            && $represented
            && strtoupper(trim($userAnswer)) === strtoupper(trim($correctAnswer));

        return [
            "userAnswer" => $userAnswer,
            "correctAnswer" => $correctAnswer,
            "answered" => $answered,
            "correct" => $correct,
        ];
    }
}
