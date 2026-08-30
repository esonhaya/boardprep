<?php

declare(strict_types=1);

namespace App\Services\Question\Builder;

final class QuestionOptionBuilder
{
    private const COUNT = 4;

    public static function build(array $input, ?array $existing = null): array
    {
        $stored = is_array($existing['options'] ?? null) ? $existing['options'] : [];
        if (!self::hasOptionInput($input) && $stored !== []) {
            return $stored;
        }

        $correctId = array_key_exists('correct_option', $input)
            ? self::scalar($input['correct_option'])
            : self::existingCorrectId($stored);

        $options = [];
        for ($i = 1; $i <= self::COUNT; $i++) {
            $id = 'option-' . $i;
            $storedOption = is_array($stored[$i - 1] ?? null) ? $stored[$i - 1] : [];
            $text = array_key_exists('option_' . $i, $input)
                ? self::scalar($input['option_' . $i])
                : trim((string) ($storedOption['text'] ?? ''));

            $options[] = [
                'id' => $id,
                'text' => $text,
                'correct' => $correctId === $id,
            ];
        }

        return $options;
    }

    private static function hasOptionInput(array $input): bool
    {
        if (array_key_exists('correct_option', $input)) {
            return true;
        }

        for ($i = 1; $i <= self::COUNT; $i++) {
            if (array_key_exists('option_' . $i, $input)) {
                return true;
            }
        }

        return false;
    }

    private static function existingCorrectId(array $options): string
    {
        foreach ($options as $option) {
            if (is_array($option) && ($option['correct'] ?? false) === true) {
                return trim((string) ($option['id'] ?? ''));
            }
        }

        return '';
    }

    private static function scalar(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
