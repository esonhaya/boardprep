<?php

declare(strict_types=1);

final class BlueprintExecutionResultFactory
{
    public static function create(
        array $selected,
        array $requests,
        array $coverage,
        array $issues,
        array $boardBlueprint,
        array $subjectBlueprints,
        string $subject
    ): BlueprintExecutionResult {
        $boardVersion = isset($boardBlueprint["version"])
            ? (int) $boardBlueprint["version"]
            : null;

        $subjectVersion = isset($subjectBlueprints[$subject]["version"])
            ? (int) $subjectBlueprints[$subject]["version"]
            : null;

        return new BlueprintExecutionResult(
            questions: $selected,
            requests: $requests,
            coverage: $coverage,
            issues: $issues,
            boardBlueprintVersion: $boardVersion,
            subjectBlueprintVersion: $subjectVersion
        );
    }
}
