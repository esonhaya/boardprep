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
            $context = LearningAttemptContext::fromAttempt($attempt);
            $subject = $context['subject'];
            $subject = $subject !== '' ? $subject : 'General';
            $key = implode("\0", array_map(
                static fn(string $field): string => strtolower((string) ($context[$field] ?? '')),
                ['board', 'let_level', 'let_specialization', 'civil_service_level']
            )) . "\0" . strtolower($subject);
            $rows[$key] ??= [
                'subject' => $subject,
                'board' => $context['board'],
                'let_level' => $context['let_level'],
                'let_specialization' => $context['let_specialization'],
                'civil_service_level' => $context['civil_service_level'],
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
            ?: strcasecmp((string) ($a['board'] ?? ''), (string) ($b['board'] ?? ''))
            ?: strcasecmp($a['subject'], $b['subject'])
        );
        return $rows;
    }
}
