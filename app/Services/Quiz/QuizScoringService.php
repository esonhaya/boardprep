<?php

declare(strict_types=1);

class QuizScoringService
{
    public static function calculate(array $questions, array $answers): array
    {
        $accumulator = new ScoreAccumulator();
        $results = [];

        foreach ($questions as $question) {
            $evaluation = QuestionScoreEvaluator::evaluate(
                $question,
                $answers[$question["id"]] ?? null
            );

            $accumulator->record(
                $evaluation["correct"],
                $evaluation["answered"]
            );

            $results[] = ResultRecordFactory::create($question, $evaluation);
        }

        return $accumulator->summarize($results);
    }

    public static function checkAnswer(array $question, ?string $answer): bool
    {
        $userAnswer = AnswerNormalizer::normalize($question, $answer);

        return strtoupper(trim($userAnswer))
            === strtoupper(trim($question["answer"] ?? ""));
    }
}
