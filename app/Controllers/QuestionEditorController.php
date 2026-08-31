<?php

declare(strict_types=1);

namespace App\Controllers;
use App\Core\Request;
use App\Core\Response;
use App\Services\Question\QuestionAuthoringService;
use App\Services\Question\QuestionEditorService;
use App\Services\Question\QuestionQueryService;
use App\Services\Question\QuestionService;
use App\Services\Question\QuestionViewService;

class QuestionEditorController extends BaseDeveloperController
{
    private static function workspace(
        array $data = []
    ): void {
        $context = [
            "board" =>
                Request::queryString("board"),

            "subject" =>
                Request::queryString("subject"),

            "domain" =>
                Request::queryString("domain"),

            "topic" =>
                Request::queryString("topic"),

            "concept" =>
                Request::queryString("concept")
        ];

        $context = array_filter(
            $context,
            static fn(string $value): bool =>
                $value !== ""
        );

        self::renderDeveloper(
            "developer/question/workspace",
            array_merge(
                $data,
                QuestionViewService::taxonomy($context),
                [
                    "context" => $context
                ]
            )
        );
    }

    public static function index(): void
    {
        $search =
            Request::queryString("search");

        $domain =
            Request::queryString("domain");

        $difficulty =
            Request::queryString("difficulty");

        $topic =
            Request::queryString("topic");

        $questions =
            QuestionQueryService::getQuestions([
                "search" => $search,
                "domain" => $domain,
                "difficulty" => $difficulty,
                "topic" => $topic
            ]);

        self::renderDeveloper(
            "developer/question/editor",
            array_merge(
                [
                    "pageTitle" =>
                        "Question Editor",

                    "questions" =>
                        $questions,

                    "search" =>
                        $search,

                    "domain" =>
                        $domain,

                    "difficulty" =>
                        $difficulty,

                    "topic" =>
                        $topic
                ],
                QuestionViewService::taxonomy()
            )
        );
    }

    public static function create(): void
    {
        self::workspace([
            "pageTitle" => "Create Question",
            "contentMode" => "create"
        ]);
    }

    public static function edit(): void
    {
        $question =
            QuestionEditorService::find(
                Request::queryString("id")
            );

        if ($question === null) {
            Response::redirect("/question-editor");
        }

        self::workspace([
            "pageTitle" => "Edit Question",
            "contentMode" => "edit",
            "question" => $question
        ]);
    }

    public static function save(): void
    {
        self::submitAuthoring(0, "Create Question", "create");
    }

    public static function update(): void
    {
        self::submitAuthoring(
            Request::queryString("id"),
            "Edit Question",
            "edit"
        );
    }

    private static function submitAuthoring(
        int|string $id,
        string $pageTitle,
        string $contentMode
    ): void {
        $result = QuestionAuthoringService::submit(
            $id,
            Request::input()
        );

        if (($result["saved"] ?? false) !== true) {
            self::workspace([
                "pageTitle" => $pageTitle,
                "contentMode" => $contentMode,
                "question" => $result["question"] ?? [],
                "errors" => $result["errors"] ?? [],
                "duplicates" => $result["duplicates"] ?? []
            ]);
            return;
        }

        Response::redirect("/question-editor");
    }

    public static function archive(): void
    {
        $id =
            Request::queryString("id");

        $question =
            QuestionEditorService::find($id);

        if ($question !== null) {
            $question["status"] = "archived";

            QuestionService::update(
                $id,
                $question
            );
        }

        Response::redirect("/question-editor");
    }

    public static function restore(): void
    {
        $id =
            Request::queryString("id");

        $question =
            QuestionEditorService::find($id);

        if ($question !== null) {
            $question["status"] = "approved";

            QuestionService::update(
                $id,
                $question
            );
        }

        Response::redirect("/question-editor");
    }
}
