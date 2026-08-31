<?php

declare(strict_types=1);

namespace App\Support\Presentation;

final class UiFormatter
{
    public static function formatDateTime(?string $value): string
    {
        if ($value === null || trim($value) === '') return 'Date unavailable';
        $timestamp = strtotime($value);
        return $timestamp === false ? $value : date('M j, Y · g:i A', $timestamp);
    }

    public static function formatPercentage(int|float|string|null $value): string
    {
        return (int) max(0, min(100, (float) ($value ?? 0))) . '%';
    }

    public static function formatScore(int|float|string|null $score, int|float|string|null $total): string
    {
        return (int) ($score ?? 0) . '/' . (int) ($total ?? 0);
    }

    public static function truncatePreviewText(string $value, int $length = 120): string
    {
        return mb_strlen($value) <= $length ? $value : rtrim(mb_substr($value, 0, $length - 1)) . '…';
    }

    public static function statusLabel(?string $value): string
    {
        $value = trim((string) $value);
        return $value === '' ? 'Unknown' : ucwords(str_replace(['_', '-'], ' ', $value));
    }
}
