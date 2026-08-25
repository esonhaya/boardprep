<?php

declare(strict_types=1);

final class BlueprintAllocationTotalCalculator
{
    /**
     * @param array<int,SelectionRequest> $requests
     */
    public static function calculate(array $requests): int
    {
        $allocated = 0;

        foreach ($requests as $request) {
            $allocated += $request->questionCount;
        }

        return $allocated;
    }
}
