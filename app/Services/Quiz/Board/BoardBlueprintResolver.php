<?php

declare(strict_types=1);

final class BoardBlueprintResolver
{
    public static function subjects(
        array $boardBlueprint
    ): array {

        return $boardBlueprint["subjects"] ?? [];

    }
}
