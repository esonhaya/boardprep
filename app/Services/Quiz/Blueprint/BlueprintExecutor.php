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

        // Explicit subject navigation remains a hard scope even when the
        // session mode uses an exam blueprint. Spread the requested count
        // across that subject's allocation only; never fill a short pool with
        // another subject.
        if ($specification->subjectExplicit
            && trim($specification->subject) !== ''
            && trim($specification->subject) !== '*') {
            $aliases = self::subjectAliases($specification->subject);
            $subjectRequests = array_values(array_filter(
                $requests,
                static fn(SelectionRequest $request): bool => isset($aliases[strtolower(trim($request->subject))])
            ));
            if ($subjectRequests === []) {
                // A subject missing from a board blueprint is a supported
                // shortage, not permission to run the other subject quotas.
                $requests = [];
            } else {
                $base = $subjectRequests[0];
                $requests = [new SelectionRequest(
                    subject: $base->subject,
                    domain: $base->domain,
                    difficultyDistribution: $base->difficultyDistribution,
                    questionCount: $specification->questionCount,
                    topic: $specification->topics[0] ?? null,
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

    /** @return array<string,true> */
    private static function subjectAliases(string $subject): array
    {
        $aliases = [strtolower(trim($subject)) => true];
        foreach (\App\Services\Shared\TaxonomyStorageService::subjects() as $record) {
            if (!is_array($record)) {
                continue;
            }
            $id = strtolower(trim((string) ($record['id'] ?? '')));
            $name = strtolower(trim((string) ($record['name'] ?? '')));
            if ($id === strtolower(trim($subject)) || $name === strtolower(trim($subject))) {
                if ($id !== '') $aliases[$id] = true;
                if ($name !== '') $aliases[$name] = true;
            }
        }
        return $aliases;
    }
}
