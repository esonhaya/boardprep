<?php

declare(strict_types=1);

final class BlueprintResolverService
{
    public static function resolve(
        QuizSpecification $specification
    ): array {

        $resolved = [

            "board" => [],
            "subject" => [],

        ];

        if (!empty($resolved["board"])) {

            BlueprintIntegrityValidator::validate(
                $resolved["board"]
            );

        }

        if (!empty($resolved["subject"])) {

            BlueprintIntegrityValidator::validate(
                $resolved["subject"]
            );

        }

        return $resolved;

    }
}
