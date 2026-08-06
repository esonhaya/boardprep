<?php

declare(strict_types=1);

use App\Repositories\BlueprintRepository;

final class SubjectBlueprintResolver
{
    public static function resolve(
        string $board,
        string $subject
    ): array {

        $repository =
            new BlueprintRepository();

        return
            $repository->subject(
                $board,
                $subject
            )
            ?? [];

    }
}
