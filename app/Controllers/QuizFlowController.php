<?php

class QuizFlowController
{

    public static function handle(): void
    {

        $action = $_GET["action"] ?? null;


        if ($action === null) {

            if (isset($_GET["count"])) {
                $action = "start";
            } else {
                $action = "settings";
            }

        }


        switch ($action) {


            case "settings":

                View::render(
                    "quiz/settings"
                );

                break;



            case "start":

                self::startQuiz();

                break;



            case "submit":

                self::submitAnswer();

                break;



            case "next":

                self::nextQuestion();

                break;



            case "finish":

                $result =
                    self::finishQuiz();


                View::render(
                    "quiz/result",
                    [
                        "result" => $result,
                        "mode" =>
                            SessionService::get(
                                "mode",
                                "practice"
                            )
                    ]
                );

                break;



            default:

                View::render(
                    "quiz/settings"
                );

                break;

        }

    }





    private static function startQuiz(): void
    {

        $count =
            (int)($_GET["count"] ?? 10);


        $difficulty =
            $_GET["difficulty"] ?? "mixed";


$mode =
    $_GET["mode"] ?? "practice";


$adaptive =
    isset(
        $_GET["adaptive"]
    );       



        $questions =
            QuestionRepository::all();



        $questions =
    QuizGenerationService::generate(
        $questions,
        [
            "blueprint" =>
                $_GET["exam"] ?? "LET",

            "difficulty" =>
                $difficulty,

            "shuffle" =>
                true,

            "limit" =>
                $count,

            "adaptive" =>
                $adaptive
        ]
    );


        SessionService::set(
            "questions",
            $questions
        );


        SessionService::set(
            "answers",
            []
        );


        SessionService::set(
            "mode",
            $mode
        );


        QuizNavigationService::reset();



        View::render(
            "quiz/index",
            [
                "question" =>
                    $questions[0] ?? null,

                "current" =>
                    0,

                "total" =>
                    count($questions),

                "mode" =>
                    $mode,

                "feedback" =>
                    null
            ]
        );

    }





    private static function submitAnswer(): void
    {

        $questions =
            SessionService::get(
                "questions",
                []
            );


        $current =
            QuizNavigationService::current();



        $question =
            $questions[$current] ?? null;



        if (!$question) {

            header(
                "Location: ?page=quiz&action=finish"
            );

            exit;

        }



        $answer =
            $_POST["answer"] ?? null;



        $answers =
            SessionService::get(
                "answers",
                []
            );



        $answers[
            $question["id"]
        ] = $answer;



        SessionService::set(
            "answers",
            $answers
        );



        $mode =
            SessionService::get(
                "mode",
                "practice"
            );



        if ($mode === "practice") {


            SessionService::set(
                "feedback",
                [
                    "correct" =>
                        QuizController::checkAnswer(
                            $question,
                            $answer
                        )
                ]
            );



            View::render(
                "quiz/index",
                [
                    "question" =>
                        $question,

                    "current" =>
                        $current,

                    "total" =>
                        count($questions),

                    "mode" =>
                        $mode,

                    "feedback" =>
                        SessionService::get(
                            "feedback"
                        )
                ]
            );


            return;

        }



        self::nextQuestion();

    }





    private static function nextQuestion(): void
    {

        QuizNavigationService::next();


        $questions =
            SessionService::get(
                "questions",
                []
            );


        $current =
            QuizNavigationService::current();



        if (
            $current >= count($questions)
        ) {

            header(
                "Location: ?page=quiz&action=finish"
            );

            exit;

        }



        SessionService::remove(
            "feedback"
        );



        View::render(
            "quiz/index",
            [
                "question" =>
                    $questions[$current],

                "current" =>
                    $current,

                "total" =>
                    count($questions),

                "mode" =>
                    SessionService::get(
                        "mode",
                        "practice"
                    ),

                "feedback" =>
                    null
            ]
        );

    }





    private static function finishQuiz(): array
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



        $result =
            QuizScoringService::calculate(
                $questions,
                $answers
            );



        AttemptRepository::save(
            [
                "date" =>
                    date("Y-m-d H:i:s"),

                "mode" =>
                    SessionService::get(
                        "mode",
                        "practice"
                    ),

                "subject" =>
                    "LET",

                "topic" =>
                    "grammar",

                "score" =>
                    $result["score"],

                "total" =>
                    $result["total"],

                "percentage" =>
                    $result["percentage"]
            ]
        );



        return $result;

    }

}
