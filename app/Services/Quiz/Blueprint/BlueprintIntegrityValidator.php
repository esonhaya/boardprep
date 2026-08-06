<?php

declare(strict_types=1);

final class BlueprintIntegrityValidator
{
    public static function validate(
        array $boardBlueprint,
        array $subjectBlueprints
    ): array {

        $errors = [];

        $boardTotal = 0;

        foreach (
            $boardBlueprint["subjects"] ?? []
            as $subject
        ) {

            $boardTotal +=
                (int) ($subject["percentage"] ?? 0);

        }

        if ($boardTotal !== 100) {

            $errors[] =
                "Board Blueprint subject percentages must total 100.";

        }

        foreach (
            $subjectBlueprints
            as $name => $blueprint
        ) {

            $domainTotal = 0;

            foreach (
                $blueprint["domains"] ?? []
                as $domain
            ) {

                $domainTotal +=
                    (int) ($domain["percentage"] ?? 0);

            }

            if ($domainTotal !== 100) {

                $errors[] =
                    "{$name}: domain percentages must total 100.";

            }

            $difficultyTotal =
                array_sum(
                    $blueprint["difficulty"] ?? []
                );

            if (
                !empty($blueprint["difficulty"]) &&
                $difficultyTotal !== 100
            ) {

                $errors[] =
                    "{$name}: difficulty percentages must total 100.";

            }

        }

        return $errors;

    }
}
