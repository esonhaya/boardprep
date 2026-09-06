<?php

declare(strict_types=1);

final class BlueprintIntegrityValidator
{
    public static function validate(
        array $boardBlueprint,
        array $subjectBlueprints
    ): array {

        $errors = [];

        $boardSubjects = is_array($boardBlueprint["subjects"] ?? null)
            ? $boardBlueprint["subjects"]
            : [];
        $boardAllocations = [];
        foreach ($boardSubjects as $subject) {
            if (is_array($subject) && array_key_exists('subject', $subject)) {
                $boardAllocations[(string) $subject['subject']] = $subject['percentage'] ?? null;
            }
        }

        $boardErrors = \App\Services\Shared\BlueprintValidator::allocationErrors(
            $boardAllocations,
            array_keys($boardAllocations)
        );
        if (count($boardAllocations) !== count($boardSubjects) || $boardErrors !== []) {
            $errors[] = "Board Blueprint subject percentages must total 100.";
        }

        foreach (
            $subjectBlueprints
            as $name => $blueprint
        ) {

            $domains = is_array($blueprint["domains"] ?? null)
                ? $blueprint["domains"]
                : [];
            $domainAllocations = [];
            foreach ($domains as $domain) {
                if (is_array($domain) && array_key_exists('domain', $domain)) {
                    $domainAllocations[(string) $domain['domain']] = $domain['percentage'] ?? null;
                }
            }

            if (count($domainAllocations) !== count($domains) ||
                \App\Services\Shared\BlueprintValidator::allocationErrors(
                $domainAllocations,
                array_keys($domainAllocations)
            ) !== []) {
                $errors[] = "{$name}: domain percentages must total 100.";
            }

            if (!empty($blueprint["difficulty"]) &&
                \App\Services\Shared\BlueprintValidator::allocationErrors(
                    $blueprint["difficulty"]
                ) !== []) {
                $errors[] = "{$name}: difficulty percentages must total 100.";
            }

        }

        return $errors;

    }
}
