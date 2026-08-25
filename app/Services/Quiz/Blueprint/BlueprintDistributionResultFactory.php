<?php

declare(strict_types=1);

final class BlueprintDistributionResultFactory
{
    /**
     * @param array<int,array<string,mixed>> $allocated
     * @return array<int,array<string,mixed>>
     */
    public static function create(array $allocated): array
    {
        return array_values(array_map(
            static function (array $request): array {
                unset($request["allocationIndex"]);
                return $request;
            },
            $allocated
        ));
    }
}
