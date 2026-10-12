<?php

declare(strict_types=1);

namespace App\Services\Learning;

final class RelativePerformanceComparisonService
{
    private const CONTEXT_FIELDS = [
        'board',
        'let_level',
        'let_specialization',
        'civil_service_level',
    ];

    public static function belowContextAverage(array $rows, int $limit = 3): array
    {
        return self::select($rows, false, $limit);
    }

    public static function aboveContextAverage(array $rows, int $limit = 3): array
    {
        return self::select($rows, true, $limit);
    }

    private static function select(array $rows, bool $aboveAverage, int $limit): array
    {
        $contexts = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $key = self::contextKey($row);
            $weight = max(1, (int) ($row['attempts'] ?? 0));
            $average = is_numeric($row['average'] ?? null)
                ? (float) $row['average']
                : 0.0;
            $contexts[$key]['weighted_total'] = ($contexts[$key]['weighted_total'] ?? 0.0) + ($average * $weight);
            $contexts[$key]['weight'] = ($contexts[$key]['weight'] ?? 0) + $weight;
        }

        $selected = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $context = $contexts[self::contextKey($row)] ?? null;
            if (!is_array($context) || (int) ($context['weight'] ?? 0) < 1) {
                continue;
            }

            $average = is_numeric($row['average'] ?? null)
                ? (float) $row['average']
                : 0.0;
            $contextAverage = (float) $context['weighted_total'] / (int) $context['weight'];
            if (($aboveAverage && $average > $contextAverage)
                || (!$aboveAverage && $average < $contextAverage)) {
                $selected[] = $row;
            }
        }

        usort($selected, static function (array $left, array $right) use ($aboveAverage): int {
            $scoreOrder = (float) ($left['average'] ?? 0) <=> (float) ($right['average'] ?? 0);
            if ($aboveAverage) {
                $scoreOrder *= -1;
            }
            if ($scoreOrder !== 0) {
                return $scoreOrder;
            }

            return strcasecmp(
                (string) ($left['subject'] ?? $left['topic'] ?? ''),
                (string) ($right['subject'] ?? $right['topic'] ?? '')
            );
        });

        return array_slice($selected, 0, max(0, $limit));
    }

    private static function contextKey(array $row): string
    {
        return implode("\0", array_map(
            static fn(string $field): string => strtolower(trim((string) ($row[$field] ?? ''))),
            self::CONTEXT_FIELDS
        ));
    }
}
