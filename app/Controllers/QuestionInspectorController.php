<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\RepositoryHealth\Engine\RepositoryHealthEngine;

class QuestionInspectorController extends BaseDeveloperController
{
    public static function index(): void
    {
        $report = RepositoryHealthEngine::analyze();

        $questions = [];

        foreach ($report->issues as $issue) {

            $id = $issue->entityId;

            if (!isset($questions[$id])) {

                $questions[$id] = [

                    "id" => $id,

                    "issues" => []

                ];

            }

            $questions[$id]["issues"][] = $issue;

        }

        self::renderDeveloper(

            "developer/question-inspector",

            [

                "pageTitle" => "Question Inspector",

                "report" => $report,

                "questions" => $questions

            ]

        );
    }
}
