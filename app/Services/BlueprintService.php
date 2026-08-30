<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Compatibility facade for older callers. New code should use the
 * App\Services\Blueprint\BlueprintService namespace.
 */
final class BlueprintService
{
    public static function all(): array
    {
        return \App\Services\Blueprint\BlueprintService::all();
    }

    public static function create(array $data): array
    {
        return \App\Services\Blueprint\BlueprintService::create($data);
    }
}
