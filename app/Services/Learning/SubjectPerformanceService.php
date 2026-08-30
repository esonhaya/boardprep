<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Services\Learning\Recommendation\LearningAttemptContext;

final class SubjectPerformanceService
{
    public static function summarize(array $attempts): array
    {
        $rows = [];

        foreach (LearningAttemptNormalizer::all($attempts) as $attempt) {
            $subject = LearningAttemptContext::fromAttempt($attempt)['subject'];
            $subject = $subject !== '' ? $subject : 'General';
            $key = strtolower($subject);
            $rows[$key] ??= [
                'subject' => $subject,
                'attempts' => 0,
                'average' => 0,
                'best' => 0,
                '_sum' => 0,
            ];
            $percentage = (int) $attempt['percentage'];
            $rows[$key]['attempts']++;
            $rows[$key]['_sum'] += $percentage;
            $rows[$key]['best'] = max($rows[$key]['best'], $percentage);
        }

        foreach ($rows as &$row) {
            $row['average'] = (int) round($row['_sum'] / $row['attempts']);
            unset($row['_sum']);
        }
        unset($row);

        $rows = array_values($rows);
        usort($rows, static fn(array $a, array $b): int =>
            ($b['average'] <=> $a['average'])
            ?: strcasecmp($a['subject'], $b['subject'])
        );
        return $rows;
    }
}
