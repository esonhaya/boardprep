<?php

class MasteryService
{
    public static function calculate(
        array $weakness
    ): array
    {
        $mastery = [];

        foreach ($weakness as $topic => $stats) {

            $total =
                $stats["correct"] +
                $stats["wrong"];

            if ($total === 0) {

                $percentage = 0;

            } else {

                $percentage = round(
                    ($stats["correct"] / $total) * 100
                );

            }

            $mastery[$topic] = [

                "percentage" => $percentage,

                "status" =>
                    self::status($percentage)

            ];

        }

        return $mastery;
    }

    private static function status(
        int $percentage
    ): string
    {
        if ($percentage >= 90) {

            return "Mastered";

        }

        if ($percentage >= 75) {

            return "Good";

        }

        if ($percentage >= 50) {

            return "Needs Practice";

        }

        return "Weak";
    }
}
