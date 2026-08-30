<?php

class MasteryService
{
    public static function calculate(
        array $weakness
    ): array
    {
        $mastery = [];

        foreach ($weakness as $topic => $stats) {

            if (!is_array($stats)) {
                continue;
            }

            $correct = self::count($stats["correct"] ?? 0);
            $wrong = self::count($stats["wrong"] ?? 0);

            $total =
                $correct +
                $wrong;

            if ($total === 0) {

                $percentage = 0;

            } else {

                $percentage = round(
                    ($correct / $total) * 100
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

    private static function count(mixed $value): int
    {
        return is_numeric($value) && is_finite((float) $value)
            ? max(0, (int) round((float) $value))
            : 0;
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
