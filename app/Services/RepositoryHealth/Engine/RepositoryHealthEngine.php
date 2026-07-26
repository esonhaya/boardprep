<?php

class RepositoryHealthEngine
{
    public static function analyze(): HealthReport
    {
        $context =
            RepositoryContextFactory::create();

        $results = [];

        foreach ($context->questions as $question) {

            $issues = [];

            foreach (
                ValidatorRegistry::entityValidators()
                as $validator
            ) {

                $issues = array_merge(
                    $issues,
                    $validator::validate($question)
                );

            }

            $results[] =
                QuestionIssueMapper::map(
                    $question,
                    $issues
                );

        }

        foreach (
            ValidatorRegistry::repositoryValidators()
            as $validator
        ) {

            $results[] =
                RepositoryIssueMapper::map(
                    $validator::analyze(
                        $context->questions
                    )
                );

        }

        $statistics =
            StatisticsBuilder::build(
                $context,
                $results
            );

        $healthScore =
            HealthScoreCalculator::calculate(
                $results
            );

        return ReportBuilder::build(
            $results,
            $statistics,
            $healthScore
        );
    }
}
