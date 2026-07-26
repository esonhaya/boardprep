<?php

class ContentValidator
{

    public static function validate(array $question): array
    {

        $issues = [];

        $text =
            trim(
                $question["question"] ?? ""
            );

        if ($text === "") {

            $issues[] = [

                "severity" => "error",

                "type" => "empty-question",

                "message" =>
                    "Question text is empty."

            ];

        }
        elseif (mb_strlen($text) < 15) {

            $issues[] = [

                "severity" => "warning",

                "type" => "short-question",

                "message" =>
                    "Question text is unusually short."

            ];

        }

        $explanation =
            trim(
                $question["explanation"] ?? ""
            );

        if ($explanation === "") {

            $issues[] = [

                "severity" => "warning",

                "type" => "missing-explanation",

                "message" =>
                    "Explanation is missing."

            ];

        }
        elseif (mb_strlen($explanation) < 20) {

            $issues[] = [

                "severity" => "info",

                "type" => "short-explanation",

                "message" =>
                    "Explanation is very short."

            ];

        }

        return $issues;

    }

}
