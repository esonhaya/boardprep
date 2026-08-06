<?php

declare(strict_types=1);

final class BlueprintCoverageAnalyzer
{
    public static function analyze(
        array $questions,
        array $blueprint
    ): array {

        $report = [];

        foreach (
            $blueprint["sections"] ?? []
            as $section
        ) {

            $count = 0;

            foreach ($questions as $question) {

                if (
                    !empty($section["domain"]) &&
                    ($question["domain"] ?? null)
                    !==
                    $section["domain"]
                ) {
                    continue;
                }

                if (
                    !empty($section["topic"]) &&
                    ($question["topic"] ?? null)
                    !==
                    $section["topic"]
                ) {
                    continue;
                }

                if (
                    !empty($section["concept"]) &&
                    ($question["concept"] ?? null)
                    !==
                    $section["concept"]
                ) {
                    continue;
                }

                $count++;

            }

            $report[] = [

                "section" =>
                    $section,

                "available" =>
                    $count,

            ];

        }

        return $report;

    }
}
