<?php

class ProgressService
{
    public static function summary(
        array $attempts
    ): array
    {
        $count = count($attempts);

        if ($count === 0) {

            return [
                "attempts" => 0,
                "average" => 0,
                "best" => 0
            ];

        }

        $total = 0;
        $best = 0;

        foreach ($attempts as $attempt) {

            $score =
                $attempt["percentage"] ?? 0;

            $total += $score;

            if ($score > $best) {

                $best = $score;

            }

        }

        return [

            "attempts" => $count,

            "average" => round(
                $total / $count
            ),

            "best" => $best

        ];
    }
public static function latest(
    array $attempts
): ?array
{
    if (empty($attempts)) {

        return null;

    }

    return end($attempts);
}

public static function totalQuestions(
    array $attempts
): int
{
    $total = 0;

    foreach ($attempts as $attempt) {

        $total +=
            $attempt["total"] ?? 0;

    }

    return $total;
}

public static function averageScore(
    array $attempts
): int
{
    return
        self::summary(
            $attempts
        )["average"];
}
}
