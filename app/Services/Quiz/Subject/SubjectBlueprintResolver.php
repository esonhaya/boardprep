<?php

declare(strict_types=1);

final class SubjectBlueprintResolver
{
    public static function sections(
        array $subjectBlueprint
    ): array {

        return $subjectBlueprint["sections"] ?? [];

    }
}
