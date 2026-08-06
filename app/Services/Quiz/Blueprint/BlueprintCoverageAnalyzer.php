<?php

declare(strict_types=1);

final class BlueprintCoverageAnalyzer
{
    public static function analyze(
        array $questions,
        array $boardBlueprint,
        array $subjectBlueprints,
        array $requests
    ): array {

        $coverage = [];

        foreach ($requests as $request) {

            $matched = array_filter(

                $questions,

                static function (array $question) use ($request): bool {

                    return
                        ($question["subject"] ?? null)
                        ===
                        $request->subject

                        &&

                        ($question["domain"] ?? null)
                        ===
                        $request->domain;

                }

            );

            $coverage[] = [

                "subject" =>
                    $request->subject,

                "domain" =>
                    $request->domain,

                "required" =>
                    $request->questionCount,

                "generated" =>
                    count($matched),

                "difficultyDistribution" =>
                    $request->difficultyDistribution,

            ];

        }

        return $coverage;

    }
}
