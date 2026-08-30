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
        array $requests
    ): array {
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
                    questionCount: $request->questionCount
                )
            );

            $result = QuestionSelectionService::fulfillRequest(
                $eligibleSubjectPool,
                $request
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
                    questionCount: $needed
                );
                $eligible = QuestionPoolFilter::filter($available, $recoveryRequest);
                $result = QuestionSelectionService::fulfillRequest($eligible, $recoveryRequest);
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
