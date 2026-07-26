<?php

class HealthScoreCalculator
{
    public static function calculate(
        array $results
    ): float
    {
        $score = 100;

        foreach ($results as $result) {

            foreach ($result->issues as $issue) {

                switch (strtolower($issue->severity)) {

                    case "error":
                        $score -= 5;
                        break;

                    case "warning":
                        $score -= 2;
                        break;

                    case "info":
                        $score -= 0.5;
                        break;

                }

            }

        }

        if ($score < 0) {
            $score = 0;
        }

        return round($score, 1);
    }
}
