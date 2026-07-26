<?php

class BlueprintController extends BaseDeveloperController{

    public static function index(): void
    {

self::renderDeveloper(    
            "developer/blueprints",

            [

                "blueprints" =>
                    BlueprintService::all()

            ]

        );

    }


public static function create(): void
{

    self::renderDeveloper(
        "developer/blueprint-create",

        [

            "boards" =>
                BoardRepository::all(),

            "subjects" =>
                SubjectRepository::all()

        ]

    );

}

    public static function save(): void
    {


        $result =
            BlueprintService::create(
                $_POST
            );


        if (
            !$result["success"]
        ) {

            self::renderDeveloper(
                "developer/blueprint-create",

                [

                    "errors" =>
                        $result["errors"]

                ]

            );

            return;

        }


        header(
            "Location: ?page=blueprints"
        );

        exit;

    }

}
