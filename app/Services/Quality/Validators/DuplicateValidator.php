<?php

declare(strict_types=1);

namespace App\Services\Quality\Validators;

class DuplicateValidator
{
    public static function analyze(array $questions): array
    {
        $issues = [];

        $ids = [];

        $questionsSeen = [];

        foreach ($questions as $question) {

            if (!is_array($question)) {
                continue;
            }

            $rawId = $question['id'] ?? null;
            $id = is_scalar($rawId) ? trim((string) $rawId) : '';

            if ($id !== "") {

                if (isset($ids[$id])) {

                    $issues[] = [
                        "severity" => "error",
                        "type" => "duplicate-id",
                        "questionId" => $id,
                        "message" => "Duplicate question ID."
                    ];

                }

                $ids[$id] = true;
            }

            $rawText = $question['question'] ?? null;
            $text = is_scalar($rawText) ? trim((string) $rawText) : '';
            $text = preg_replace('/\s+/', ' ', $text) ?? $text;
            $text = strtolower($text);

            if ($text !== "") {

                if (isset($questionsSeen[$text])) {

                    $issues[] = [
                        "severity" => "warning",
                        "type" => "duplicate-question",
                        "questionId" => $id,
                        "message" => "Duplicate question text."
                    ];

                }

                $questionsSeen[$text] = true;
            }
        }

        return $issues;
    }
}
