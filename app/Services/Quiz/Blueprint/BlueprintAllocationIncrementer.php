<?php

declare(strict_types=1);

final class BlueprintAllocationIncrementer
{
    /**
     * @param array<int,SelectionRequest> $requests
     * @return array<int,SelectionRequest>
     */
    public static function apply(array $requests, int $difference): array
    {
        $count = count($requests);
        $index = 0;

        while ($difference > 0) {
            $requests[$index] = BlueprintAllocationRequestFactory::withQuestionCount(
                $requests[$index],
                $requests[$index]->questionCount + 1
            );

            $difference--;
            $index++;

            if ($index >= $count) {
                $index = 0;
            }
        }

        return $requests;
    }
}
