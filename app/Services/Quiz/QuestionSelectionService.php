<?php
declare(strict_types=1);
final class QuestionSelectionService
{

    public static function select(array $questions, QuizSpecification $specification): array
    {
        $request = new SelectionRequest(
            subject: $specification->subject,
            domain: $specification->domain,
            difficultyDistribution: [
                $specification->difficulty => $specification->questionCount
            ],
            questionCount: $specification->questionCount,
            topic: $specification->topics[0] ?? null,
            concept: $specification->concepts[0] ?? null,
            exam: $specification->board
        );

        return self::fulfillRequest($questions, $request)->questions;
    }


    public static function fulfillRequest(array $questions, SelectionRequest $request): SelectionResult
    {
        $pool = QuestionPoolFilter::filter($questions, $request);
        $selected = SelectionPipeline::run($pool, $request);
        return SelectionFulfillmentFactory::create($selected, $request);
    }
}
