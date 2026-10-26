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

                static function (array $question) use ($request, $boardBlueprint): bool {
                    $taxonomy = is_array($question['taxonomy'] ?? null)
                        ? $question['taxonomy'] : [];
                    $subjects = array_filter([
                        $taxonomy['subject_id'] ?? null,
                        $question['subject'] ?? null,
                    ], 'is_scalar');
                    foreach (is_array($boardBlueprint['selection_subject_aliases'] ?? null)
                        ? $boardBlueprint['selection_subject_aliases']
                        : [] as $selectionSubject => $aliases) {
                        if (strcasecmp((string) $selectionSubject, $request->subject) === 0
                            && is_array($aliases)) {
                            $subjects = array_merge($subjects, array_filter($aliases, 'is_scalar'));
                            $subjects[] = $selectionSubject;
                            break;
                        }
                    }
                    $domains = array_filter([
                        $taxonomy['domain_id'] ?? null,
                        $question['domain'] ?? null,
                    ], 'is_scalar');

                    $subjectMatches = false;
                    foreach ($subjects as $subject) {
                        $subjectMatches = $subjectMatches
                            || strcasecmp(trim((string) $subject), $request->subject) === 0;
                    }
                    if (!$subjectMatches || $request->domain === null) {
                        return $subjectMatches;
                    }

                    foreach ($domains as $domain) {
                        if (strcasecmp(trim((string) $domain), $request->domain) === 0) {
                            return true;
                        }
                    }
                    return false;

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
