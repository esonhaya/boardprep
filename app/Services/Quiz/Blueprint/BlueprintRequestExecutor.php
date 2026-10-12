<?php

declare(strict_types=1);

final class BlueprintRequestExecutor
{
    /**
     * @param array<int,array<string,mixed>> $questions
     * @param SelectionRequest[] $requests
     * @return array<int,array<string,mixed>>
     */
    public static function execute(
        array $questions,
        array $requests,
        ?\App\Services\Question\QuestionEligibilityIndex $eligibilityIndex = null
    ): array {
        if ($eligibilityIndex === null) {
            foreach ($requests as $request) {
                if ($request instanceof SelectionRequest && $request->exam !== null) {
                    $eligibilityIndex = \App\Services\Question\QuestionEligibilityService::index();
                    break;
                }
            }
        }
        $session = new SelectionSession();
        $selected = [];

        foreach ($requests as $request) {
            $available = $session->available($questions);
            $eligibleSubjectPool = QuestionPoolFilter::filter(
                $available,
                new SelectionRequest(
                    subject: $request->subject,
                    domain: null,
                    difficultyDistribution: [],
                    questionCount: $request->questionCount,
                    letVariant: $request->letVariant,
                    letComponent: $request->letComponent,
                    exam: $request->exam
                ),
                $eligibilityIndex
            );

            $result = QuestionSelectionService::fulfillRequest(
                $eligibleSubjectPool,
                $request,
                $eligibilityIndex
            );

            $chunk = ShortageRecoveryService::recover(
                $result,
                $eligibleSubjectPool
            );

            $chunk = SelectionDeduplicator::unique($chunk);

            $session->reserve($chunk);
            $selected = array_merge($selected, $chunk);
        }

        $target = array_sum(array_map(
            static fn(SelectionRequest $request): int => max(0, $request->questionCount),
            $requests
        ));

        if (count($selected) < $target) {
            foreach ($requests as $request) {
                $available = $session->available($questions);
                $needed = $target - count($selected);
                if ($needed <= 0) {
                    break;
                }
                $recoveryRequest = new SelectionRequest(
                    subject: $request->subject,
                    domain: null,
                    difficultyDistribution: $request->difficultyDistribution,
                    questionCount: $needed,
                    letVariant: $request->letVariant,
                    letComponent: $request->letComponent,
                    exam: $request->exam
                );
                $eligible = QuestionPoolFilter::filter($available, $recoveryRequest, $eligibilityIndex);
                $result = QuestionSelectionService::fulfillRequest($eligible, $recoveryRequest, $eligibilityIndex);
                $chunk = SelectionDeduplicator::unique(
                    ShortageRecoveryService::recover($result, $eligible)
                );
                $session->reserve($chunk);
                $selected = array_merge($selected, $chunk);
            }
        }

        return $selected;
    }
}
