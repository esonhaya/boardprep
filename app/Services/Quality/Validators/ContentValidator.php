<?php

declare(strict_types=1);

namespace App\Services\Quality\Validators;

class ContentValidator
{
    public static function validate(array $question): array
    {
        $issues = [];

        $text = trim(
            $question["question"] ?? ""
        );

        if ($text === "") {

            $issues[] = [
                "severity" => "error",
                "type" => "empty-question",
                "message" => "Question text is empty."
            ];

        } elseif (function_exists('mb_strlen') ? mb_strlen($text) : strlen($text) < 15) {

            $issues[] = [
                "severity" => "warning",
                "type" => "short-question",
                "message" => "Question text is unusually short."
            ];

        }

        $explanation = trim(
            $question["explanation"] ?? ""
        );

        if ($explanation === "") {

            $issues[] = [
                "severity" => "warning",
                "type" => "missing-explanation",
                "message" => "Explanation is missing."
            ];

        } elseif (function_exists('mb_strlen') ? mb_strlen($explanation) : strlen($explanation) < 20) {

            $issues[] = [
                "severity" => "info",
                "type" => "short-explanation",
                "message" => "Explanation is very short."
            ];

        }

        return $issues;
    }
}
