<?php

declare(strict_types=1);

final class BlueprintAllocationDeltaCalculator
{
    /**
     * @param array<int,SelectionRequest> $requests
     */
    public static function calculate(array $requests, int $target): int
    {
        return $target - BlueprintAllocationTotalCalculator::calculate($requests);
    }
}
