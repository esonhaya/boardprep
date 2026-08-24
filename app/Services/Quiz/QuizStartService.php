<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Response;
use App\Core\Request;
use App\Core\View;
use App\Repositories\QuestionRepository;

class QuizStartService
{
    public static function start(): void
    {
        $input = Request::all();

        $questions =
            App::container()
                ->get(QuestionRepository::class)
                ->all();

        $topic = trim((string) ($input["topic"] ?? ""));
        $topics = $topic === "" ? [] : [$topic];

        $specification =
            BaseSpecificationFactory::create(
                [
                    "board" =>
                        $input["exam"] ?? "LET",

                    "subject" =>
                        $input["subject"] ?? "",

                    "domain" =>
                        $input["domain"] ?? null,

                    "topics" =>
                        $topics,

                    "difficulty" =>
                        $input["difficulty"] ?? "mixed",

                    "count" =>
                        (int) (
                            $input["count"] ?? 10
                        ),

                    "mode" =>
                        $input["mode"] ?? "practice",

                    "adaptive" =>
                        isset($input["adaptive"]),

                    "shuffle" =>
                        true,
                ]
            );

        $result =
            QuizGenerationService::generate(
                $questions,
                $specification
            );

        if (empty($result->questions)) {
            Response::redirect("/quiz", 302);
        }

        SessionService::set(
            "quiz_session",
            [
                "id" => "quiz-" . bin2hex(random_bytes(8)),
                "board" => $specification->board,
                "subject" => $specification->subject,
                "domain" => $specification->domain,
                "topics" => $specification->topics,
                "mode" => $specification->mode,
                "difficulty" => $specification->difficulty,
                "question_count" => count($result->questions),
                "question_ids" => array_values(array_filter(array_map(
                    static fn(array $question): ?string =>
                        isset($question["id"])
                            ? (string) $question["id"]
                            : null,
                    $result->questions
                ))),
                "started_at" => date("c"),
            ]
        );

        SessionService::set("questions", $result->questions);
        SessionService::set("answers", []);
        SessionService::set("feedback", null);
        SessionService::set("mode", $specification->mode);

        QuizNavigationService::reset();

        View::render(
            "quiz/index",
            [
                "question" => $result->questions[0],
                "current" => 0,
                "total" => count($result->questions),
                "mode" => $specification->mode,
                "feedback" => null,
            ]
        );
    }
}
