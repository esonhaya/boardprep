<?php

declare(strict_types=1);

namespace App\Services\Blueprint\Creation;

use App\Repositories\BlueprintRepository;

final class BlueprintVersionResolver
{
    public static function next(
        BlueprintRepository $repository,
        string $boardId,
        string $subjectId
    ): int {
        $highest = 0;

        foreach ($repository->versions($boardId, $subjectId) as $blueprint) {
            $highest = max($highest, (int) ($blueprint['version'] ?? 0));
        }

        return $highest + 1;
    }
}
