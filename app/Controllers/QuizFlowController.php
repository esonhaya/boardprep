<?php

class QuizFlowController
{

    public static function handle(): void
    {

        $action =
            $_GET["action"] ?? null;


        if ($action === null) {

            $action =
                isset($_GET["count"])
                ?
                "start"
                :
                "settings";

        }



        switch ($action) {


            case "settings":

                View::render(
                    "quiz/settings"
                );

                break;



            case "start":

                QuizStartService::start();

                break;



            case "submit":

                QuizSubmissionService::submit();

                break;



            case "next":

                QuizNavigationService::next();

                break;



            case "finish":

                View::render(
                    "quiz/result",
                    [

                        "result" =>
                            QuizResultService::build(),

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

}
