<?php

declare(strict_types=1);

namespace App\Services\Shared;

final class QuestionImportService
{
    public static function importJson(
        string $json
    ): array {

        if (
            is_file($json)
        ) {
            $contents = file_get_contents($json);

            if ($contents === false) {
                return [
                    "success" => false,
                    "errors" => [
                        "Unable to read import file."
                    ]
                ];
            }

            $json = $contents;
        }

        $parser =
            new \QuestionImportParser();

        $processor =
            new \QuestionImportProcessor();

        $report =
            new \QuestionImportReport();

        $processor->process(
            $parser->parse($json),
            $report
        );

        return $report->summary();
    }
}
