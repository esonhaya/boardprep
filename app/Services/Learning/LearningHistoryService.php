<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Core\App;
use App\Repositories\AttemptRepository;

class LearningHistoryService
{

    public static function recent(int $limit = 10): array
    {

        $attempts =
            App::container()
                ->get(AttemptRepository::class)
                ->all();

        usort(
            $attempts,
            static fn(array $a, array $b): int =>
                self::timestamp($b)
                <=>
                self::timestamp($a)
        );

        return array_slice(
            $attempts,
            0,
            max(0, $limit)
        );

    }

    private static function timestamp(
        array $attempt
    ): int {
        foreach ([
            "completed_at",
            "date",
            "started_at",
        ] as $field) {
            if (
                isset($attempt[$field])
                && is_string($attempt[$field])
                && trim($attempt[$field]) !== ""
            ) {
                $timestamp = strtotime($attempt[$field]);

                if ($timestamp !== false) {
                    return $timestamp;
                }
            }
        }

        return 0;
    }

}
