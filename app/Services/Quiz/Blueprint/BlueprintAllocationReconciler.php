<?php

declare(strict_types=1);

final class BlueprintAllocationReconciler
{
    /**
     * Preserve the existing reconciliation boundary while delegating
     * validation, delta calculation, and adjustment phases.
     *
     * @param array<int,SelectionRequest> $requests
     * @return array<int,SelectionRequest>
     */
    public static function reconcile(
        array $requests,
        int $target
    ): array {
        BlueprintAllocationTargetGuard::validate($target);

        $difference = BlueprintAllocationDeltaCalculator::calculate(
            $requests,
            $target
        );

        if ($difference === 0 || empty($requests)) {
            return $requests;
        }

        if ($difference > 0) {
            return BlueprintAllocationIncrementer::apply(
                $requests,
                $difference
            );
        }

        return BlueprintAllocationDecrementer::apply(
            $requests,
            $difference
        );
    }
}
