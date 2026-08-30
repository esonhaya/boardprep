<?php

declare(strict_types=1);

namespace App\Services\Learning;

final class LearningAttemptNormalizer
{
    public static function all(array $attempts): array
    {
        $normalized = [];

        foreach ($attempts as $attempt) {
            if (!is_array($attempt)) {
                continue;
            }

            $attempt = self::one($attempt);
            if ($attempt !== null) {
                $normalized[] = $attempt;
            }
        }

        return $normalized;
    }

    public static function one(array $attempt): ?array
    {
        if (array_key_exists('completed', $attempt)) {
            $completed = $attempt['completed'];
            if ($completed === false || $completed === 0 || $completed === '0') {
                return null;
            }
            if (is_string($completed) && strtolower(trim($completed)) === 'false') {
                return null;
            }
        }

        $hasPercentage = array_key_exists('percentage', $attempt);
        $hasScore = array_key_exists('score', $attempt);
        $hasTotal = array_key_exists('total', $attempt);
        if (!$hasPercentage && !$hasScore && !$hasTotal) {
            return null;
        }

        $percentage = self::percentage(
            $attempt['percentage'] ?? null,
            $attempt['score'] ?? null,
            $attempt['total'] ?? null
        );
        if ($percentage === null) {
            return null;
        }
        $attempt['percentage'] = $percentage;
        $attempt['score'] = self::nonNegativeInt($attempt['score'] ?? 0);
        $attempt['total'] = self::nonNegativeInt($attempt['total'] ?? 0);

        $attempt['completed'] = true;

        return $attempt;
    }

    private static function percentage(mixed $value, mixed $score, mixed $total): ?int
    {
        if (!is_numeric($value)) {
            $scoreValue = self::nonNegativeInt($score);
            $totalValue = self::nonNegativeInt($total);
            if ($totalValue <= 0 || !is_numeric($score)) {
                return null;
            }
            $value = ($scoreValue / $totalValue) * 100;
        } else {
            $value = (float) $value;
            if ($value > 0 && $value < 1) {
                $value *= 100;
            }
        }

        return max(0, min(100, (int) round($value)));
    }

    private static function nonNegativeInt(mixed $value): int
    {
        return is_numeric($value)
            ? max(0, (int) round((float) $value))
            : 0;
    }
}
