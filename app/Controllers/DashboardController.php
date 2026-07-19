<?php

require_once __DIR__ . "/BaseController.php";

class DashboardController
extends BaseController
{
    public static function index(): void
    {
        $attempts =
            AttemptRepository::all();

        $progress =
            ProgressService::summary(
                $attempts
            );

        $latest =
            ProgressService::latest(
                $attempts
            );

        $weakness =
            WeaknessService::all();

        $mastery =
            MasteryService::calculate(
                $weakness
            );

        $recommendation =
            RecommendationService::nextTopic(
                $mastery
            );

        self::render(
            "dashboard/index",
            [

            "pageTitle" => "Learning Center",

            "progress" =>
            $progress,

            "latest" =>
            $latest,

            "mastery" =>
            $mastery,

            "recommendation" =>
            $recommendation

            ]
        );
    }
}
