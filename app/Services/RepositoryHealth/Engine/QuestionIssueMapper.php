<?php

class QuestionIssueMapper
{
    public static function map(
        array $question,
        array $issues
    ): ValidationResult
    {
        $result = new ValidationResult();

        foreach ($issues as $issue) {

            $healthIssue = new HealthIssue();

            $healthIssue->validator = "QuestionValidator";

            $healthIssue->severity =
                $issue["severity"] ?? "info";

            $healthIssue->priority = "normal";

            $healthIssue->category = "Quality";

            $healthIssue->code =
                $issue["type"] ?? "unknown";

            $healthIssue->message =
                $issue["message"] ?? "";

            $healthIssue->recommendation = "";

            $healthIssue->repairable = false;

            $healthIssue->entityType = "question";

            $healthIssue->entityId =
                $question["id"] ?? null;

            $result->addIssue($healthIssue);

        }

        return $result;
    }
}
