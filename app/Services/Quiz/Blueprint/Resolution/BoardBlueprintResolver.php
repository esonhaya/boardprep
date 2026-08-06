<?php

declare(strict_types=1);

use App\Repositories\BlueprintRepository;

final class BoardBlueprintResolver
{
    public static function resolve(
        string $board
    ): array {

        $repository =
            new BlueprintRepository();

        return
            $repository->board($board)
            ?? [];

    }
}
