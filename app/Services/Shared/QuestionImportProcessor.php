<?php

declare(strict_types=1);

use App\Services\Question\QuestionService;

final class QuestionImportProcessor
{
    public function process(
        array $questions,
        QuestionImportReport $report
    ): void {

        foreach ($questions as $question) {

            $this->processQuestion(
                $question,
                $report
            );

        }

    }

    private function processQuestion(
        array $question,
        QuestionImportReport $report
    ): void {

        $result =
            QuestionService::validateForSave(
                $question
            );

        if (
            !empty($result["errors"])
        ) {

            $report->fail(
                $question,
                "Validation failed."
            );

            return;

        }

        if (
            !empty($result["duplicates"])
        ) {

            $report->skip(
                $question,
                "Duplicate question."
            );

            return;

        }

        QuestionService::save(
            $question
        );

        $report->success(
            $question
        );

    }
}
