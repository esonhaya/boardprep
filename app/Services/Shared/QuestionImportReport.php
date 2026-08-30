<?php

declare(strict_types=1);

final class QuestionImportReport
{
    public array $imported = [];

    public array $updated = [];

    public array $skipped = [];

    public array $failed = [];

    public array $errors = [];

    public function error(string $message): void
    {
        $this->errors[] = $message;
    }

    public function success(
        array $question
    ): void {

        $this->imported[] =
            $question;

    }

    public function update(
        array $question
    ): void {

        $this->updated[] =
            $question;

    }

    public function skip(
        array $question,
        string $reason
    ): void {

        $this->skipped[] = [

            "question" =>
                $question,

            "reason" =>
                $reason,

        ];

    }

    public function fail(
        array $question,
        string $reason
    ): void {

        $this->failed[] = [

            "question" =>
                $question,

            "reason" =>
                $reason,

        ];

    }

    public function summary(): array
    {

        return [

            "success" => empty($this->errors) && empty($this->failed),

            "errors" => $this->errors,

            "imported" =>
                $this->imported,

            "updated" =>
                $this->updated,

            "skipped" =>
                $this->skipped,

            "failed" =>
                $this->failed,

        ];

    }
}
