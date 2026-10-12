<?php
declare(strict_types=1);
final class QuestionSelectionService
{

    public static function select(
        array $questions,
        QuizSpecification $specification,
        ?\App\Services\Question\QuestionEligibilityIndex $eligibilityIndex = null
    ): array
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
            exam: $specification->board,
            letVariant: $specification->letVariant,
            civilServiceLevel: $specification->civilServiceLevel
        );

        return self::fulfillRequest($questions, $request, $eligibilityIndex)->questions;
    }


    public static function fulfillRequest(array $questions, SelectionRequest $request, ?\App\Services\Question\QuestionEligibilityIndex $eligibilityIndex = null): SelectionResult
    {
        if ($eligibilityIndex === null && $request->exam !== null) {
            $eligibilityIndex = \App\Services\Question\QuestionEligibilityService::index();
        }
        $pool = QuestionPoolFilter::filter($questions, $request, $eligibilityIndex);
        $selected = SelectionPipeline::run($pool, $request);
        return SelectionFulfillmentFactory::create($selected, $request);
    }
}
