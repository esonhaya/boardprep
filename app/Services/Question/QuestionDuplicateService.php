<?php

class QuestionDuplicateService
{

    public static function find(
        array $question
    ): array
    {

        $duplicates = [];


        foreach (
            QuestionRepository::all()
            as $existing
        ) {

            if (
                ($existing["id"] ?? 0)
                ===
                ($question["id"] ?? 0)
            ) {
                continue;
            }


            if (

                trim(
                    strtolower(
                        $existing["question"] ?? ""
                    )
                )

                ===

                trim(
                    strtolower(
                        $question["question"] ?? ""
                    )
                )

            ) {

                $duplicates[] =
                    $existing;

            }

        }


        return $duplicates;

    }

}
