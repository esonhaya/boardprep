<?php

declare(strict_types=1);

final class BlueprintDistributionAllocator
{
    /**
     * @param array<int,array<string,mixed>> $requests
     * @return array<int,array<string,mixed>>
     */
    public static function allocate(array $requests): array
    {
        $allocated = [];

        foreach ($requests as $index => $request) {
            $request["allocationIndex"] = $index;
            $request["allocatedCount"] = $request["questionCount"];
            $allocated[] = $request;
        }

        return $allocated;
    }
}
