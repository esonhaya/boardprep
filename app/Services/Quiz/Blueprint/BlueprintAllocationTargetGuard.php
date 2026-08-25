<?php

declare(strict_types=1);

final class BlueprintAllocationTargetGuard
{
    public static function validate(int $target): void
    {
        if ($target < 0) {
            throw new InvalidArgumentException(
                'Allocation target cannot be negative.'
            );
        }
    }
}
