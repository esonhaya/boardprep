<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Core\App;
use App\Repositories\AttemptRepository;

final class LearningHistoryService
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
                self::timestampOf($b)
                <=>
                self::timestampOf($a)
        );

        return array_slice(
            $attempts,
            0,
            max(0, $limit)
        );
    }

    public static function all(): array
    {
        return self::recent(PHP_INT_MAX);
    }

    public static function timestampOf(array $attempt): int
    {
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

    public static function dateOf(array $attempt): ?string
    {
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
                return $attempt[$field];
            }
        }

        return null;
    }

    public static function topicOf(array $attempt): string
    {
        foreach ([
            "topic",
            "domain",
            "subject",
            "board",
        ] as $field) {
            if (
                isset($attempt[$field])
                && is_string($attempt[$field])
                && trim($attempt[$field]) !== ""
            ) {
                return $attempt[$field];
            }
        }

        return "General";
    }
}
