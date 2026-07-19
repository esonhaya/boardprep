<?php

class WeaknessService
{
    public static function analyze(
        array $answers
    ): array
    {
        $weakness =
            WeaknessRepository::all();

        foreach ($answers as $answer) {

            $topic =
                $answer["topic"] ?? "General";

            if (!isset($weakness[$topic])) {

                $weakness[$topic] = [
                    "correct" => 0,
                    "wrong" => 0
                ];

            }

            if ($answer["correct"]) {

                $weakness[$topic]["correct"]++;

            } else {

                $weakness[$topic]["wrong"]++;

            }

        }

        WeaknessRepository::save(
            $weakness
        );

        return $weakness;
    }

    public static function all(): array
    {
        return WeaknessRepository::all();
    }

    public static function clear(): void
    {
        WeaknessRepository::clear();
    }
}
