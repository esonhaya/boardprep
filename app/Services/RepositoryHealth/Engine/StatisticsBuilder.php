<?php

declare(strict_types=1);

namespace App\Services\RepositoryHealth\Engine;

use App\Services\RepositoryHealth\DTO\RepositoryContext;
use App\Services\RepositoryHealth\DTO\RepositoryStatistics;
use App\Services\RepositoryHealth\DTO\ValidationResult;

class StatisticsBuilder
{
    public static function build(
        RepositoryContext $context,
        array $results
    ): RepositoryStatistics {
        $stats = new RepositoryStatistics();

        $stats->totalQuestions = count($context->questions);

        foreach ($results as $result) {

            if (!$result instanceof ValidationResult) {
                continue;
            }

            foreach ($result->issues as $issue) {

                $stats->totalIssues++;

                switch ($issue->severity) {

                    case "error":
                        $stats->errors++;
                        break;

                    case "warning":
                        $stats->warnings++;
                        break;

                    default:
                        $stats->infos++;
                        break;
                }

                $category = $issue->category ?? "Unknown";

                if (!isset($stats->issuesByCategory[$category])) {
                    $stats->issuesByCategory[$category] = 0;
                }

                $stats->issuesByCategory[$category]++;

                $validator = $issue->validator ?? "Unknown";

                if (!isset($stats->issuesByValidator[$validator])) {
                    $stats->issuesByValidator[$validator] = 0;
                }

                $stats->issuesByValidator[$validator]++;
            }
        }

        foreach ($context->questions as $question) {

            foreach (
                [
                    "difficulty" => "questionsPerDifficulty",
                    "status" => "questionsPerStatus",
                    "board" => "questionsPerBoard",
                    "subject" => "questionsPerSubject",
                    "domain" => "questionsPerDomain",
                    "topic" => "questionsPerTopic",
                    "concept" => "questionsPerConcept",
                ] as $field => $property
            ) {

                $value = (string) ($question[$field] ?? "Unknown");

                if (!isset($stats->{$property}[$value])) {
                    $stats->{$property}[$value] = 0;
                }

                $stats->{$property}[$value]++;
            }
        }

        return $stats;
    }
}
