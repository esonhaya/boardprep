<?php

class DashboardController
{

    public static function index(): void
    {

        $attempts =
            AttemptHistoryService::recent();

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
