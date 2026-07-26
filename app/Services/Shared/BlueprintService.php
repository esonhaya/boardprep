<?php

class BlueprintService
{

    public static function all(): array
    {

        return BlueprintRepository::all();

    }



    public static function create(
        array $data
    ): array
    {

        $board =
            $data["board"] ?? "";

        $subject =
            $data["subject"] ?? "";


        $version =
            self::nextVersion(
                $board,
                $subject
            );


        $id =
            self::generateId(
                $board,
                $subject,
                $version
            );


        $blueprint = [

            "id" =>
                $id,

            "board" =>
                $board,

            "subject" =>
                $subject,

            "name" =>
                $data["name"] ?? "",

            "version" =>
                $version,

            "questionCount" =>
                (int)(
                    $data["questionCount"] ?? 0
                ),

            "difficulty" => [

                "easy" =>
                    (int)(
                        $data["easy"] ?? 0
                    ),

                "medium" =>
                    (int)(
                        $data["medium"] ?? 0
                    ),

                "hard" =>
                    (int)(
                        $data["hard"] ?? 0
                    )

            ],

            "topicWeights" => [],

            "conceptWeights" => []

        ];


        $validation =
            BlueprintValidator::validate(
                $blueprint
            );


        if (
            !$validation["valid"]
        ) {

            return [

                "success" =>
                    false,

                "errors" =>
                    $validation["errors"]

            ];

        }


        BlueprintRepository::save(
            $blueprint
        );


        return [

            "success" =>
                true,

            "blueprint" =>
                $blueprint

        ];

    }



    private static function generateId(
        string $board,
        string $subject,
        int $version
    ): string
    {

        return strtolower(
            $board
            .
            "-"
            .
            $subject
            .
            "-v"
            .
            $version
        );

    }



    private static function nextVersion(
        string $board,
        string $subject
    ): int
    {

        $highest = 0;


        foreach (
            BlueprintRepository::all()
            as $blueprint
        ) {

            if (

                ($blueprint["board"] ?? "")
                ===
                $board

                &&

                ($blueprint["subject"] ?? "")
                ===
                $subject

            ) {

                $highest =
                    max(
                        $highest,
                        (int)(
                            $blueprint["version"] ?? 0
                        )
                    );

            }

        }


        return $highest + 1;

    }

}
