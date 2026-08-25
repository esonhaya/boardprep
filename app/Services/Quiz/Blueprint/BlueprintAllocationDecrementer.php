<?php

declare(strict_types=1);

final class BlueprintAllocationDecrementer
{
    /**
     * @param array<int,SelectionRequest> $requests
     * @return array<int,SelectionRequest>
     */
    public static function apply(array $requests, int $difference): array
    {
        $count = count($requests);

        while ($difference < 0) {
            $index = 0;
            $changed = false;

            while ($index < $count && $difference < 0) {
                $request = $requests[$index];

                if ($request->questionCount > 0) {
                    $requests[$index] =
                        BlueprintAllocationRequestFactory::withQuestionCount(
                            $request,
                            $request->questionCount - 1
                        );

                    $difference++;
                    $changed = true;
                }

                $index++;
            }

            if (!$changed) {
                throw new InvalidArgumentException(
                    'Allocation target cannot be reconciled with the available requests.'
                );
            }
        }

        return $requests;
    }
}
