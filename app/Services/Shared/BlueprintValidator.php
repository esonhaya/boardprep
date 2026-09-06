<?php

declare(strict_types=1);

namespace App\Services\Shared;

class BlueprintValidator
{
    public const PERCENTAGE_PRECISION = 0;

    private const DIFFICULTIES = [
        'easy',
        'medium',
        'hard',
    ];

    /**
     * Validate a complete percentage allocation without coercing malformed
     * request values into valid-looking integers.
     *
     * @return string[]
     */
    public static function allocationErrors(
        array $allocation,
        array $requiredKeys = []
    ): array {
        $errors = [];

        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $allocation)) {
                $errors[] = "Missing allocation value for {$key}.";
            }
        }

        foreach ($allocation as $key => $value) {
            if (!self::isWholeNumber($value)) {
                $errors[] = "Allocation value for {$key} must be a whole number.";
                continue;
            }

            $value = (int) $value;
            if ($value < 0 || $value > 100) {
                $errors[] = "Allocation value for {$key} must be between 0 and 100.";
            }
        }

        if ($errors === [] && array_sum(array_map('intval', $allocation)) !== 100) {
            $errors[] = 'Percentage allocation must equal 100%.';
        }

        return array_values(array_unique($errors));
    }

    /**
     * Validate raw creation input before BlueprintCreationInput can coerce it.
     * HTTP form values are numeric strings by design; arrays, empty strings,
     * fractions, NaN, and infinity are not valid percentage values.
     *
     * @return string[]
     */
    public static function creationInputErrors(array $data): array
    {
        $errors = [];

        $questionCount = $data['questionCount'] ?? null;
        if (!self::isWholeNumber($questionCount) || (int) $questionCount <= 0) {
            $errors[] = 'Question count must be a positive whole number.';
        }

        $difficulty = $data['difficulty'] ?? null;
        if ($difficulty === null) {
            $difficulty = [];
            foreach (self::DIFFICULTIES as $key) {
                if (array_key_exists($key, $data)) {
                    $difficulty[$key] = $data[$key];
                }
            }
        }

        if (!is_array($difficulty)) {
            $errors[] = 'Difficulty distribution must be an array.';
        } else {
            $errors = array_merge(
                $errors,
                self::allocationErrors($difficulty, self::DIFFICULTIES)
            );
        }

        return array_values(array_unique($errors));
    }

    public static function validate(
        array $blueprint
    ): array {

        $errors = [];

        if (empty($blueprint["id"])) {
            $errors[] = "Missing blueprint ID.";
        }

        if (empty($blueprint["name"])) {
            $errors[] = "Missing blueprint name.";
        }

        if (empty($blueprint["board"])) {
            $errors[] = "Missing board.";
        }

        if (empty($blueprint["subject"])) {
            $errors[] = "Missing subject.";
        }

        if (!isset($blueprint["version"])) {
            $errors[] = "Missing version.";
        }

        if (
            empty($blueprint["questionCount"])
            ||
            $blueprint["questionCount"] <= 0
        ) {
            $errors[] =
                "Question count must be greater than zero.";
        }

        if (!isset($blueprint["difficulty"]) || !is_array($blueprint["difficulty"])) {

            $errors[] =
                "Missing difficulty distribution.";

        } else {
            $allocation = $blueprint["difficulty"];
            $allocationErrors = self::allocationErrors(
                $allocation,
                self::DIFFICULTIES
            );
            foreach ($allocationErrors as $allocationError) {
                $errors[] = str_replace(
                    'Percentage allocation',
                    'Difficulty distribution',
                    $allocationError
                );
            }
        }

        return [
            "valid" => empty($errors),
            "errors" => $errors,
        ];
    }

    private static function isWholeNumber(mixed $value): bool
    {
        if (is_int($value)) {
            return true;
        }

        if (is_float($value)) {
            return is_finite($value) && floor($value) === $value;
        }

        if (!is_string($value)) {
            return false;
        }

        $value = trim($value);
        return $value !== '' && preg_match('/^\+?\d+$/', $value) === 1;
    }
}
