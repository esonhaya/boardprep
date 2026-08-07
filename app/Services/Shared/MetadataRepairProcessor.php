<?php

declare(strict_types=1);

final class MetadataRepairProcessor
{
    public function process(
        array $questions,
        MetadataRepairReport $report
    ): array {

        foreach ($questions as &$question) {

            $this->repairIdentity(
                $question,
                $report
            );

            $this->repairTaxonomy(
                $question,
                $report
            );

            $this->repairMetadata(
                $question,
                $report
            );

            $this->repairOptions(
                $question,
                $report
            );

        }

        return $questions;

    }

    private function repairIdentity(
        array &$question,
        MetadataRepairReport $report
    ): void {

        if (empty($question["id"])) {

            $question["id"] = uniqid("q");

            $report->repaired(
                $question["id"],
                "id"
            );

        }

        if (empty($question["code"])) {

            $question["code"] =
                "Q" .
                str_pad(
                    preg_replace(
                        "/\D/",
                        "",
                        (string) $question["id"]
                    ),
                    6,
                    "0",
                    STR_PAD_LEFT
                );

            $report->repaired(
                $question["id"],
                "code"
            );

        }

    }

    private function repairTaxonomy(
        array &$question,
        MetadataRepairReport $report
    ): void {

        $question["taxonomy"] ??= [];

        foreach (

            [
                "board_id",
                "subject_id",
                "domain_id",
                "topic_id",
                "concept_id",
            ]

            as $field

        ) {

            if (!isset($question["taxonomy"][$field])) {

                $question["taxonomy"][$field] = "";

                $report->repaired(
                    $question["id"],
                    "taxonomy.$field"
                );

            }

        }

    }

    private function repairMetadata(
        array &$question,
        MetadataRepairReport $report
    ): void {

        $defaults = [

            "difficulty" => "easy",
            "status" => "approved",
            "hint" => "",
            "source" => "",
            "tags" => [],
            "type" => "multiple_choice",

        ];

        foreach (

            $defaults as $field => $value

        ) {

            if (!isset($question[$field])) {

                $question[$field] = $value;

                $report->repaired(
                    $question["id"],
                    $field
                );

            }

        }

        $question["updatedAt"] = date("c");

    }

    private function repairOptions(
        array &$question,
        MetadataRepairReport $report
    ): void {

        $question["options"] ??= [];

        foreach (

            $question["options"] as $index => &$option

        ) {

            if (empty($option["id"])) {

                $option["id"] =
                    "option-" . ($index + 1);

            }

            $option["text"] ??= "";
            $option["correct"] ??= false;

        }

        if (!isset($question["explanation"])) {

            $question["explanation"] = "";

            $report->repaired(
                $question["id"],
                "explanation"
            );

        }

    }
}
