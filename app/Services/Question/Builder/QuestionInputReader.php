<?php

declare(strict_types=1);

namespace App\Services\Question\Builder;

final class QuestionInputReader
{
    public static function text(
        array $input,
        string $key,
        ?array $existing = null,
        string $default = '',
        ?string $legacyKey = null
    ): string {
        $value = $input[$key]
            ?? ($legacyKey !== null ? ($input[$legacyKey] ?? null) : null)
            ?? ($existing[$key] ?? $default);

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public static function first(array $input, array $keys): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $input)) {
                continue;
            }

            $value = $input[$key];
            return is_scalar($value) ? trim((string) $value) : '';
        }

        return '';
    }

    public static function blocks(array $input, ?array $existing = null): array
    {
        $raw = $input['content_blocks_json'] ?? null;
        if ($raw === null && is_array($existing) && isset($existing['content_blocks'])) return $existing['content_blocks'];
        if (!is_string($raw) || trim($raw) === '') return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
