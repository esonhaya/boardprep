<?php

declare(strict_types=1);

namespace App\Services\Question;

use App\Services\RepositoryHealth\Engine\RepositoryHealthEngine;

class QuestionQualityService
{
    public static function analyze(): array
    {
        $report =
            RepositoryHealthEngine::analyze();

        $drafts = [];

        $missingExplanation = [];

        $invalidAnswers = [];

        $missingChoices = [];

        $duplicateChoices = [];

        $emptyQuestion = [];

        foreach ($report->issues as $issue) {

            switch ($issue->code) {

                case "draft":

                    $drafts[] = $issue;

                    break;

                case "missing-explanation":

                    $missingExplanation[] = $issue;

                    break;

                case "invalid-answer":

                    $invalidAnswers[] = $issue;

                    break;

                case "missing-choice":

                    $missingChoices[] = $issue;

                    break;

                case "duplicate-choice":

                    $duplicateChoices[] = $issue;

                    break;

                case "empty-question":

                    $emptyQuestion[] = $issue;

                    break;

            }

        }

        return [

            "report" => $report,

            "healthScore" =>
                $report->healthScore,

            "issues" =>
                $report->issues,

            "drafts" =>
                $drafts,

            "missingExplanation" =>
                $missingExplanation,

            "invalidAnswers" =>
                $invalidAnswers,

            "missingChoices" =>
                $missingChoices,

            "duplicateChoices" =>
                $duplicateChoices,

            "emptyQuestion" =>
                $emptyQuestion,

        ];

    }
}
