<?php

declare(strict_types=1);

namespace App\Services\Learning;

final class LearningAttemptNormalizer
{
    public static function total(array $attempts): int
    {
        $identities = [];
        $anonymous = 0;

        foreach ($attempts as $attempt) {
            if (!is_array($attempt) || !self::hasMetrics($attempt)) {
                continue;
            }

            $identity = self::identity($attempt);
            if ($identity === null) {
                $anonymous++;
            } else {
                $identities[$identity] = true;
            }
        }

        return count($identities) + $anonymous;
    }

    public static function all(array $attempts): array
    {
        $normalized = [];
        $positions = [];

        foreach ($attempts as $attempt) {
            if (!is_array($attempt)) {
                continue;
            }

            $attempt = self::one($attempt);
            if ($attempt !== null) {
                $identity = self::identity($attempt);
                if ($identity !== null && isset($positions[$identity])) {
                    $position = $positions[$identity];
                    if (self::timestamp($attempt) >= self::timestamp($normalized[$position])) {
                        $normalized[$position] = $attempt;
                    }
                    continue;
                }

                $normalized[] = $attempt;
                if ($identity !== null) {
                    $positions[$identity] = array_key_last($normalized);
                }
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

        if (!self::hasMetrics($attempt)) {
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
        $total = self::nonNegativeInt(
            $attempt['total'] ?? ($attempt['question_count'] ?? 0)
        );
        $score = min(self::nonNegativeInt($attempt['score'] ?? 0), $total);
        if ($total > 0) {
            $percentage = (int) round(($score / $total) * 100);
        }

        $unanswered = min(
            self::nonNegativeInt($attempt['unanswered'] ?? 0),
            max(0, $total - $score)
        );
        if (array_key_exists('answered', $attempt)) {
            $answered = min(self::nonNegativeInt($attempt['answered']), $total);
            $answered = max($score, $answered);
            $unanswered = $total - $answered;
        }
        $incorrect = $total > 0 ? max(0, $total - $score - $unanswered) : 0;

        $attempt['percentage'] = $percentage;
        $attempt['score'] = $score;
        $attempt['correct'] = $score;
        $attempt['incorrect'] = $incorrect;
        $attempt['unanswered'] = $unanswered;
        $attempt['answered'] = $score + $incorrect;
        $attempt['total'] = $total;

        $attempt['completed'] = true;

        return $attempt;
    }

    private static function percentage(mixed $value, mixed $score, mixed $total): ?int
    {
        if (!self::finiteNumeric($value)) {
            $scoreValue = self::nonNegativeInt($score);
            $totalValue = self::nonNegativeInt($total);
            if ($totalValue <= 0 || !self::finiteNumeric($score)) {
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
        return self::finiteNumeric($value)
            ? max(0, (int) round((float) $value))
            : 0;
    }

    private static function finiteNumeric(mixed $value): bool
    {
        return is_numeric($value) && is_finite((float) $value);
    }

    private static function hasMetrics(array $attempt): bool
    {
        foreach (['percentage', 'score', 'total', 'question_count'] as $field) {
            if (array_key_exists($field, $attempt) && self::finiteNumeric($attempt[$field])) {
                return true;
            }
        }

        return false;
    }

    private static function identity(array $attempt): ?string
    {
        foreach (['session_id', 'id'] as $field) {
            if (is_scalar($attempt[$field] ?? null)) {
                $value = trim((string) $attempt[$field]);
                if ($value !== '') {
                    return $field . ':' . $value;
                }
            }
        }

        return null;
    }

    private static function timestamp(array $attempt): int
    {
        foreach (['completed_at', 'date', 'started_at'] as $field) {
            $value = $attempt[$field] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $timestamp = strtotime($value);
                if ($timestamp !== false) {
                    return $timestamp;
                }
            }
        }

        return 0;
    }
}
