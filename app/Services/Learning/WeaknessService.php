<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Services\Learning\WeaknessStorageService;

class WeaknessService
{
    public static function analyze(
        array $answers
    ): array
    {
        $weakness =
            WeaknessStorageService::all();

        foreach ($answers as $answer) {
            if (!is_array($answer)) {
                continue;
            }

            $topic = self::topic($answer["topic"] ?? "General");

            if (!isset($weakness[$topic])) {

                $weakness[$topic] = [
                    "correct" => 0,
                    "wrong" => 0
                ];

            }

            if (($answer["correct"] ?? false) === true) {

                $weakness[$topic]["correct"]++;

            } else {

                $weakness[$topic]["wrong"]++;

            }

        }

        WeaknessStorageService::save(
            $weakness
        );

        return $weakness;
    }

    public static function all(): array
    {
        return WeaknessStorageService::all();
    }

    public static function clear(): void
    {
        WeaknessStorageService::clear();
    }

    private static function topic(mixed $value): string
    {
        $topic = is_scalar($value) ? trim((string) $value) : "";
        return $topic !== "" ? $topic : "General";
    }
}
