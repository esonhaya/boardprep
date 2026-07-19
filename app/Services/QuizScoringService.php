<?php

class QuizScoringService
{
    public static function calculate(array $questions, array $answers): array
    {
        $score = 0;
        $results = [];

        foreach ($questions as $index => $question) {

            $userAnswer = $answers[$index] ?? null;

            $correct = $userAnswer === $question["answer"];

            if ($correct) {
                $score++;
            }

            $results[] = [
                "question" => $question["question"],
                "choices" => $question["choices"],
                "userAnswer" => $userAnswer,
                "correctAnswer" => $question["answer"],
                "correct" => $correct,
                "explanation" => $question["explanation"] ?? ""
            ];
        }

        return [
            "score" => $score,
            "total" => count($questions),
            "percentage" => count($questions)
                ? round(($score / count($questions)) * 100)
                : 0,
            "results" => $results
        ];
    }
}
