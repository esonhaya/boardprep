<?php

declare(strict_types=1);

namespace App\Services\RepositoryHealth\Engine;

use App\Services\RepositoryHealth\DTO\HealthIssue;
use App\Services\RepositoryHealth\DTO\ValidationResult;

class RepositoryIssueMapper
{
    public static function map(
        array $issues
    ): ValidationResult {
        $result = new ValidationResult();

        foreach ($issues as $issue) {

            $healthIssue = new HealthIssue();

            $healthIssue->validator =
                "RepositoryValidator";

            $healthIssue->severity =
                $issue["severity"] ?? "info";

            $healthIssue->priority = "normal";

            $healthIssue->category = "Repository";

            $healthIssue->code =
                $issue["type"] ?? "unknown";

            $healthIssue->message =
                $issue["message"] ?? "";

            $healthIssue->recommendation = "";

            $healthIssue->repairable = false;

            if (isset($issue["question"]["id"])) {

                $healthIssue->entityType =
                    "question";

                $healthIssue->entityId =
                    $issue["question"]["id"];
            }

            $result->addIssue($healthIssue);
        }

        return $result;
    }
}
