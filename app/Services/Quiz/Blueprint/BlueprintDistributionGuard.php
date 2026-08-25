<?php

declare(strict_types=1);

final class BlueprintDistributionGuard
{
    /**
     * @param array<int,array<string,mixed>> $requests
     */
    public static function assertValid(array $requests): void
    {
        foreach ($requests as $request) {
            if ((int) ($request["questionCount"] ?? 0) < 0) {
                throw new InvalidArgumentException(
                    "Blueprint question count cannot be negative."
                );
            }

            if (trim((string) ($request["subject"] ?? "")) === "") {
                throw new InvalidArgumentException(
                    "Blueprint subject cannot be empty."
                );
            }
        }
    }
}
