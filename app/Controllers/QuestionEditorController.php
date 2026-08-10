<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
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
                trim((string) Request::query("board", "")),

            "subject" =>
                trim((string) Request::query("subject", "")),

            "domain" =>
                trim((string) Request::query("domain", "")),

            "topic" =>
                trim((string) Request::query("topic", "")),

            "concept" =>
                trim((string) Request::query("concept", ""))
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
            trim((string) Request::query("search", ""));

        $domain =
            trim((string) Request::query("domain", ""));

        $difficulty =
            trim((string) Request::query("difficulty", ""));

        $topic =
            trim((string) Request::query("topic", ""));

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
                (string) Request::query("id", "")
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
        $question =
            QuestionService::build(
                time(),
                Request::input()
            );

        $check =
            QuestionService::validateForSave(
                $question
            );

        if (!empty($check["errors"])) {
            self::workspace([
                "pageTitle" => "Create Question",
                "contentMode" => "create",
                "question" => $question,
                "errors" => $check["errors"],
                "duplicates" => $check["duplicates"]
            ]);

            return;
        }

        QuestionService::save($question);

        Response::redirect("/question-editor");
    }

    public static function update(): void
    {
        $id =
            (int) Request::query("id", 0);

        $question =
            QuestionService::build(
                $id,
                Request::input()
            );

        $check =
            QuestionService::validateForSave(
                $question
            );

        if (!empty($check["errors"])) {
            self::workspace([
                "pageTitle" => "Edit Question",
                "contentMode" => "edit",
                "question" => $question,
                "errors" => $check["errors"],
                "duplicates" => $check["duplicates"]
            ]);

            return;
        }

        QuestionService::update(
            $id,
            $question
        );

        Response::redirect("/question-editor");
    }

    public static function archive(): void
    {
        $id =
            (string) Request::query("id", "");

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
            (string) Request::query("id", "");

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
