<?php

class BlueprintHealthController extends BaseDeveloperController
{

    public static function index(): void
    {

        $results = [];

        foreach (
            BlueprintService::all()
            as $blueprint
        ) {

            $results[] = [

                "blueprint" => $blueprint,

                "validation" =>
                    BlueprintValidator::validate(
                        $blueprint
                    )

            ];

        }

        self::renderDeveloper(

            "developer/blueprint-health",

            [

                "results" => $results

            ]

        );

    }

}
