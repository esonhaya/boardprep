<?php

class QuizBlueprintService
{

    public static function apply(
        array $options
    ): array
    {

        if (
            empty(
                $options["blueprint"]
            )
        ) {

            return $options;

        }


        $blueprint =
            BlueprintRepository::find(
                $options["blueprint"]
            );


        if (!$blueprint) {

            return $options;

        }


        foreach (
            $blueprint["sections"] ?? []
            as $section
        ) {

            if (
                !empty(
                    $section["topic"]
                )
            ) {

                $options["topic"] =
                    $section["topic"];

            }

        }


        return $options;

    }

}
