<?php

class RecommendationService
{

    public static function generate(
        array $analytics,
        array $weaknesses
    ): array
    {

        $recommendations = [];


        if (
            ($analytics["averageScore"] ?? 0) < 60
        ) {

            $recommendations[] =

                "Focus on fundamentals before taking another exam.";

        }


        if (
            ($analytics["averageScore"] ?? 0) >= 60 &&
            ($analytics["averageScore"] ?? 0) < 80
        ) {

            $recommendations[] =

                "Keep practicing. You're making steady progress.";

        }


        if (
            ($analytics["averageScore"] ?? 0) >= 80
        ) {

            $recommendations[] =

                "Excellent progress. Try harder quizzes.";

        }


        foreach($weaknesses as $weakness)
        {

            if(
                ($weakness["accuracy"] ?? 100) < 70
            )
            {

                $recommendations[] =

                    "Review: " .
                    ($weakness["topic"] ?? "Unknown Topic");

            }

        }


        if(empty($recommendations))
        {

            $recommendations[] =

                "Keep taking quizzes to build your learning profile.";

        }


        return $recommendations;

    }

}
