<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Response;
use App\Repositories\QuestionRepository;
use App\Services\Question\QuestionQueryService;
use App\Services\Question\QuestionService;
use App\Services\Question\QuestionViewService;

class QuestionEditorController extends BaseDeveloperController
{
    public static function index(): void
    {
        $search =
            trim((string) Request::query(
                "search",
                ""
            ));

        $domain =
            trim((string) Request::query(
                "domain",
                ""
            ));

        $difficulty =
            trim((string) Request::query(
                "difficulty",
                ""
            ));

        $topic =
            trim((string) Request::query(
                "topic",
                ""
            ));

        $questions =
            QuestionQueryService::getQuestions(
                [
                    "search" => $search,
                    "domain" => $domain,
                    "difficulty" => $difficulty,
                    "topic" => $topic
                ]
            );

        self::renderDeveloper(
            "developer/question-editor",
            array_merge(
                [
                    "pageTitle" => "Question Editor",
                    "questions" => $questions,
                    "search" => $search,
                    "domain" => $domain,
                    "difficulty" => $difficulty,
                    "topic" => $topic
                ],
                QuestionViewService::taxonomy()
            )
        );
    }

    public static function create(): void
    {
        self::renderDeveloper(
            "developer/question-create",
            array_merge(
                [
                    "pageTitle" => "Create Question"
                ],
                QuestionViewService::taxonomy()
            )
        );
    }

    public static function edit(): void
    {
        $question =
            QuestionRepository::find(
                (int) Request::query(
                    "id",
                    0
                )
            );

        if (!$question) {
            Response::redirect(
                "/question-editor"
            );
        }

        self::renderDeveloper(
            "developer/question-edit",
            array_merge(
                [
                    "pageTitle" => "Edit Question",
                    "question" => $question
                ],
                QuestionViewService::taxonomy()
            )
        );
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

            self::renderDeveloper(
                "developer/question-create",
                array_merge(
                    [
                        "pageTitle" => "Create Question",
                        "question" => $question,
                        "errors" => $check["errors"],
                        "duplicates" => $check["duplicates"]
                    ],
                    QuestionViewService::taxonomy()
                )
            );

            return;
        }

        QuestionService::save(
            $question
        );

        Response::redirect(
            "/question-editor"
        );
    }

    public static function update(): void
    {
        $id =
            (int) Request::query(
                "id",
                0
            );

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

            self::renderDeveloper(
                "developer/question-edit",
                array_merge(
                    [
                        "pageTitle" => "Edit Question",
                        "question" => $question,
                        "errors" => $check["errors"],
                        "duplicates" => $check["duplicates"]
                    ],
                    QuestionViewService::taxonomy()
                )
            );

            return;
        }

        QuestionService::update(
            $id,
            $question
        );

        Response::redirect(
            "/question-editor"
        );
    }

    public static function archive(): void
    {
        QuestionRepository::archive(
            (int) Request::query(
                "id",
                0
            )
        );

        Response::redirect(
            "/question-editor"
        );
    }

    public static function restore(): void
    {
        QuestionRepository::restore(
            (int) Request::query(
                "id",
                0
            )
        );

        Response::redirect(
            "/question-editor"
        );
    }
}
