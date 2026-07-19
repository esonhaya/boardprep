<?php

class LearningProfileService
{

    public static function build(
        array $analytics,
        array $weaknesses
    ): array
    {

        $average =
            $analytics["averageScore"] ?? 0;

        if($average < 60)
        {

            $level = "Beginner";

        }
        elseif($average < 80)
        {

            $level = "Intermediate";

        }
        else
        {

            $level = "Advanced";

        }

        return [

            "level" =>
                $level,

            "overallAccuracy" =>
                $average,

            "totalQuizzes" =>
                $analytics["totalQuizzes"] ?? 0,

            "bestScore" =>
                $analytics["bestScore"] ?? 0,

            "latestScore" =>
                $analytics["latestScore"] ?? 0,

            "weaknessCount" =>
                count($weaknesses)

        ];

    }

}
