<?php

declare(strict_types=1);

final class BlueprintExecutor
{
    public static function execute(
        array $questions,
        array $boardBlueprint,
        array $subjectBlueprints,
        QuizSpecification $specification,
        ?\App\Services\Question\QuestionEligibilityIndex $eligibilityIndex = null
    ): BlueprintExecutionResult {
        $requests = BlueprintRequestPlanBuilder::build(
            $boardBlueprint,
            $subjectBlueprints,
            $specification->questionCount,
            $specification->topics[0] ?? null,
            $specification->difficulty
        );
        $requests = array_map(
            static fn(SelectionRequest $request): SelectionRequest => new SelectionRequest(
                subject: $request->subject,
                domain: $request->domain,
                difficultyDistribution: $request->difficultyDistribution,
                questionCount: $request->questionCount,
                topic: $request->topic,
                concept: $request->concept,
                exam: $specification->board,
                letVariant: $specification->letVariant,
                letComponent: $request->letComponent
            ),
            $requests
        );

        // An explicit subject + topic is a scoped learner request, even when
        // the surrounding mode is an exam blueprint. The exam distribution
        // still applies when no topic is selected, but it must not allocate a
        // one-question/topic session to unrelated subjects and then report an
        // empty quiz.
        if (($specification->topics[0] ?? null) !== null
            && trim($specification->subject) !== ''
            && trim($specification->subject) !== '*') {
            $subjectRequests = array_values(array_filter(
                $requests,
                static fn(SelectionRequest $request): bool => strcasecmp(trim($request->subject), trim($specification->subject)) === 0
            ));
            if ($subjectRequests !== []) {
                $base = $subjectRequests[0];
                $requests = [new SelectionRequest(
                    subject: $base->subject,
                    domain: $base->domain,
                    difficultyDistribution: $base->difficultyDistribution,
                    questionCount: $specification->questionCount,
                    topic: $specification->topics[0],
                    concept: $specification->concepts[0] ?? null,
                    exam: $specification->board,
                    letVariant: $specification->letVariant,
                    letComponent: $base->letComponent,
                    civilServiceLevel: $specification->civilServiceLevel
                )];
            }
        }

        $selected = BlueprintRequestExecutor::execute(
            $questions,
            $requests,
            $eligibilityIndex
        );

        $coverage = BlueprintCoverageFinalizer::analyze(
            $selected,
            $boardBlueprint,
            $subjectBlueprints,
            $requests
        );

        $issues = BlueprintCoverageFinalizer::validate($coverage);

        return BlueprintExecutionResultFactory::create(
            $selected,
            $requests,
            $coverage,
            $issues,
            $boardBlueprint,
            $subjectBlueprints,
            $specification->subject
        );
    }
}
