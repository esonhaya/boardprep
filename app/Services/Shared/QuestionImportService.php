<?php

declare(strict_types=1);

final class QuestionImportService
{
    public static function importJson(
        string $json
    ): array {

        $parser =
            new QuestionImportParser();

        $processor =
            new QuestionImportProcessor();

        $report =
            new QuestionImportReport();

        $processor->process(
            $parser->parse($json),
            $report
        );

        return $report->summary();

    }
}
