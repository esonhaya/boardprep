<?php

class PerformanceAnalyticsService
{

    public static function summary(array $attempts): array
    {

        $totalQuizzes = count($attempts);

        if ($totalQuizzes === 0) {

            return [

                "totalQuizzes" => 0,
                "averageScore" => 0,
                "bestScore" => 0,
                "latestScore" => 0

            ];

        }

        $sum = 0;
        $best = 0;

        foreach ($attempts as $attempt) {

            $percentage = (int)($attempt["percentage"] ?? 0);

            $sum += $percentage;

            if ($percentage > $best) {
                $best = $percentage;
            }

        }

        return [

            "totalQuizzes" => $totalQuizzes,

            "averageScore" =>
                round($sum / $totalQuizzes),

            "bestScore" =>
                $best,

            "latestScore" =>
                (int)$attempts[0]["percentage"]

        ];

    }

}
