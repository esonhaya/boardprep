<?php

class DashboardController
{

    public static function index(): void
    {

        $attempts =
            LearningHistoryService::recent();

        $weaknesses =
            WeaknessService::all();

        View::render(
            "dashboard/index",
            [

                "attempts" =>
                    $attempts,

                "weaknesses" =>
                    $weaknesses

            ]
        );

    }

}
