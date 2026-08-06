<?php

declare(strict_types=1);

final class BlueprintIntegrityValidator
{
    public static function validate(
        array $blueprint
    ): array {

        $errors = [];

        $weight = 0;

        foreach (
            $blueprint["sections"] ?? []
            as $index => $section
        ) {

            $weight +=
                (int) (
                    $section["weight"] ?? 0
                );

            if (
                isset($section["difficulty"])
            ) {

                $difficulty = 0;

                foreach (
                    $section["difficulty"]
                    as $value
                ) {

                    $difficulty +=
                        (int) $value;

                }

                if ($difficulty !== 100) {

                    $errors[] =
                        "Section {$index}: difficulty distribution must equal 100.";

                }

            }

        }

        if ($weight !== 100) {

            $errors[] =
                "Blueprint weights total {$weight}. Expected 100.";

        }

        return $errors;

    }
}
