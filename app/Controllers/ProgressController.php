<?php

require_once __DIR__ . "/../Repositories/AttemptRepository.php";

class ProgressController
{

    public static function getStats()
    {

        $attempts = AttemptRepository::all();

        $totalAttempts = count($attempts);

        $practice = 0;
        $exam = 0;
        $best = 0;
        $latest = null;
        $sum = 0;

        foreach ($attempts as $attempt)
        {

            $sum += $attempt["percentage"];

            if ($attempt["mode"] === "practice")
            {
                $practice++;
            }
            else if ($attempt["mode"] === "exam")
            {
                $exam++;
            }

            if ($attempt["percentage"] > $best)
            {
                $best = $attempt["percentage"];
            }

            $latest = $attempt;

        }

        $average = $totalAttempts > 0
            ? round($sum / $totalAttempts)
            : 0;

        return [

            "totalAttempts" => $totalAttempts,
            "practice" => $practice,
            "exam" => $exam,
            "average" => $average,
            "best" => $best,
            "latest" => $latest,
            "attempts" => array_reverse($attempts)

        ];

    }

}
