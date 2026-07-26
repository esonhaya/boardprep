<?php

class StatisticsBuilder
{
    public static function build(
        RepositoryContext $context,
        array $results
    ): RepositoryStatistics
    {
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

            $difficulty = $question["difficulty"] ?? "Unknown";

            if (!isset($stats->questionsPerDifficulty[$difficulty])) {
                $stats->questionsPerDifficulty[$difficulty] = 0;
            }

            $stats->questionsPerDifficulty[$difficulty]++;

            $status = $question["status"] ?? "Unknown";

            if (!isset($stats->questionsPerStatus[$status])) {
                $stats->questionsPerStatus[$status] = 0;
            }

            $stats->questionsPerStatus[$status]++;

            $board = $question["board"] ?? "Unknown";

            if (!isset($stats->questionsPerBoard[$board])) {
                $stats->questionsPerBoard[$board] = 0;
            }

            $stats->questionsPerBoard[$board]++;

            $subject = $question["subject"] ?? "Unknown";

            if (!isset($stats->questionsPerSubject[$subject])) {
                $stats->questionsPerSubject[$subject] = 0;
            }

            $stats->questionsPerSubject[$subject]++;

            $domain = $question["domain"] ?? "Unknown";

            if (!isset($stats->questionsPerDomain[$domain])) {
                $stats->questionsPerDomain[$domain] = 0;
            }

            $stats->questionsPerDomain[$domain]++;

            $topic = $question["topic"] ?? "Unknown";

            if (!isset($stats->questionsPerTopic[$topic])) {
                $stats->questionsPerTopic[$topic] = 0;
            }

            $stats->questionsPerTopic[$topic]++;

            $concept = $question["concept"] ?? "Unknown";

            if (!isset($stats->questionsPerConcept[$concept])) {
                $stats->questionsPerConcept[$concept] = 0;
            }

            $stats->questionsPerConcept[$concept]++;
        }

        return $stats;
    }
}
