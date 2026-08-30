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
                    $taxonomy = is_array($question['taxonomy'] ?? null)
                        ? $question['taxonomy'] : [];
                    $subject = $question['subject'] ?? $taxonomy['subject_id'] ?? null;
                    $domain = $question['domain'] ?? $taxonomy['domain_id'] ?? null;

                    return is_scalar($subject)
                        && strcasecmp(trim((string) $subject), $request->subject) === 0
                        && ($request->domain === null || (
                            is_scalar($domain)
                            && strcasecmp(trim((string) $domain), $request->domain) === 0
                        ));

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
