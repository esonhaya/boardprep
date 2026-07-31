<?php

declare(strict_types=1);

namespace App\Services\RepositoryHealth\Engine;

use App\Services\RepositoryHealth\DTO\HealthIssue;
use App\Services\RepositoryHealth\DTO\ValidationResult;

class QuestionIssueMapper
{
    public static function map(
        array $question,
        array $issues
    ): ValidationResult {
        $result = new ValidationResult();

        foreach ($issues as $issue) {

            $healthIssue = new HealthIssue();

            $code = $issue["type"] ?? "unknown";

            $healthIssue->validator =
                self::resolveValidator($code);

            $healthIssue->category =
                self::resolveCategory($code);

            $healthIssue->severity =
                $issue["severity"] ?? "info";

            $healthIssue->priority =
                self::resolvePriority(
                    $healthIssue->severity
                );

            $healthIssue->code = $code;

            $healthIssue->message =
                $issue["message"] ?? "";

            $healthIssue->recommendation =
                self::recommendation($code);

            $healthIssue->repairable =
                self::repairable($code);

            $healthIssue->entityType = "question";

            $healthIssue->entityId =
                $question["id"] ?? null;

            $result->addIssue($healthIssue);
        }

        return $result;
    }

    private static function resolveValidator(string $code): string
    {
        if (
            str_contains($code, "choice") ||
            str_contains($code, "answer")
        ) {
            return "ChoiceValidator";
        }

        if (
            str_contains($code, "board") ||
            str_contains($code, "subject") ||
            str_contains($code, "difficulty") ||
            str_contains($code, "status")
        ) {
            return "MetadataValidator";
        }

        return "QuestionValidator";
    }

    private static function resolveCategory(string $code): string
    {
        if (
            str_contains($code, "board") ||
            str_contains($code, "subject") ||
            str_contains($code, "difficulty") ||
            str_contains($code, "status")
        ) {
            return "Metadata";
        }

        if (
            str_contains($code, "choice") ||
            str_contains($code, "answer")
        ) {
            return "Choices";
        }

        return "Content";
    }

    private static function resolvePriority(string $severity): string
    {
        return match ($severity) {
            "error" => "high",
            "warning" => "normal",
            default => "low",
        };
    }

    private static function repairable(string $code): bool
    {
        return in_array($code, [
            "missing-board",
            "missing-subject",
            "invalid-status",
            "invalid-difficulty",
        ], true);
    }

    private static function recommendation(string $code): string
    {
        return match ($code) {
            "missing-board" =>
                "Assign the question to a board.",
            "missing-subject" =>
                "Assign the question to a subject.",
            "invalid-status" =>
                "Use a valid status.",
            "invalid-difficulty" =>
                "Use Easy, Medium, or Hard.",
            default => "",
        };
    }
}
