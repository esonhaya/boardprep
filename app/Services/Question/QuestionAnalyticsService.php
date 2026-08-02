<?php

class QuestionAnalyticsService
{

    public static function defaults(
        array $existing = []
    ): array
    {

        return [

            "source" =>
                $existing["source"] ?? "manual",

            "timesUsed" =>
                $existing["timesUsed"] ?? 0,

            "timesCorrect" =>
                $existing["timesCorrect"] ?? 0,

            "timesIncorrect" =>
                $existing["timesIncorrect"] ?? 0,

            "bookmarks" =>
                $existing["bookmarks"] ?? 0,

            "reports" =>
                $existing["reports"] ?? 0,

            "helpfulExplanation" =>
                $existing["helpfulExplanation"] ?? 0,

            "notHelpfulExplanation" =>
                $existing["notHelpfulExplanation"] ?? 0,

            "createdAt" =>
                $existing["createdAt"] ?? date("c"),

            "updatedAt" =>
                date("c")

        ];

    }

}
