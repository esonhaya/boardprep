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

            $result = QuestionSelectionService::fulfillRequest(
                $available,
                $request
            );

            $chunk = ShortageRecoveryService::recover(
                $result,
                $available
            );

            $chunk = SelectionDeduplicator::unique($chunk);

            $session->reserve($chunk);
            $selected = array_merge($selected, $chunk);
        }

        return $selected;
    }
}
