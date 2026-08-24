<?php

use App\Core\App;
use App\Services\AttemptService;

class QuizResultService
{

    public static function build(): array
    {

        $questions =
            SessionService::get(
                "questions",
                []
            );

        $answers =
            SessionService::get(
                "answers",
                []
            );

        $summary =
            QuizScoringService::calculate(
                $questions,
                $answers
            );

        $session =
            SessionService::get(
                "quiz_session",
                []
            );

        if (
            !empty($session["id"])
            && !SessionService::has("attempt_persisted")
        ) {
            $attempt = [
                "id" =>
                    "attempt-" . bin2hex(random_bytes(8)),

                "session_id" =>
                    $session["id"],

                "user_id" =>
                    "session:" . $session["id"],

                "board" =>
                    $session["board"] ?? null,

                "subject" =>
                    $session["subject"] ?? null,

                "domain" =>
                    $session["domain"] ?? null,

                "mode" =>
                    $session["mode"] ?? null,

                "difficulty" =>
                    $session["difficulty"] ?? null,

                "question_count" =>
                    $session["question_count"]
                    ?? count($questions),

                "question_ids" =>
                    $session["question_ids"] ?? [],

                "score" =>
                    $summary["score"] ?? 0,

                "total" =>
                    $summary["total"]
                    ?? count($questions),

                "percentage" =>
                    $summary["percentage"] ?? 0,

                "completed" =>
                    true,

                "started_at" =>
                    $session["started_at"] ?? null,

                "completed_at" =>
                    date("c"),
            ];

            $attempt = QuizLearningContextService::enrichAttempt(
                $attempt,
                $session,
                $questions
            );

            App::container()
                ->get(AttemptService::class)
                ->save($attempt);

            SessionService::set(
                "attempt_persisted",
                true
            );
        }

        return [

            "summary" =>
                $summary,

            "review" =>
                $summary["results"]

        ];

    }

}
