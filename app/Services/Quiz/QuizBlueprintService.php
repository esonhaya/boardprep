<?php

class QuizBlueprintService
{
    public static function apply(
        array $options
    ): array
    {

        if (empty($options["blueprint"])) {
            return $options;
        }

        $blueprint =
            BlueprintRepository::find(
                $options["blueprint"]
            );

        if (!$blueprint) {
            return $options;
        }

        $topics = [];

        foreach (
            $blueprint["sections"] ?? []
            as $section
        ) {

            if (!empty($section["topic"])) {
                $topics[] = $section["topic"];
            }

        }

        if (!empty($topics)) {
            $options["topics"] = $topics;

            if (count($topics) === 1) {
                $options["topic"] = $topics[0];
            }
        }

        return $options;
    }
}
