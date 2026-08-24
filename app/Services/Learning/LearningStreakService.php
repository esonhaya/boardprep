<?php

declare(strict_types=1);

namespace App\Services\Learning;

final class LearningStreakService
{
    public static function current(array $attempts): int
    {
        $days = [];

        foreach ($attempts as $attempt) {
            $timestamp = LearningHistoryService::timestampOf($attempt);

            if ($timestamp > 0) {
                $days[date("Y-m-d", $timestamp)] = true;
            }
        }

        if (empty($days)) {
            return 0;
        }

        $dates = array_keys($days);
        rsort($dates);

        $streak = 1;
        $cursor = strtotime($dates[0]);

        for ($index = 1, $count = count($dates); $index < $count; $index++) {
            $previous = strtotime($dates[$index]);

            if ($cursor - $previous !== 86400) {
                break;
            }

            $streak++;
            $cursor = $previous;
        }

        return $streak;
    }
}
