<?php

declare(strict_types=1);

final class BlueprintCoverageFinalizer
{
    public static function analyze(
        array $selected,
        array $boardBlueprint,
        array $subjectBlueprints,
        array $requests
    ): array {
        return BlueprintCoverageAnalyzer::analyze(
            $selected,
            $boardBlueprint,
            $subjectBlueprints,
            $requests
        );
    }

    public static function validate(array $coverage): array
    {
        return BlueprintCoverageValidator::validate($coverage);
    }
}
