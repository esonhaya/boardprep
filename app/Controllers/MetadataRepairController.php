<?php

class MetadataRepairController extends BaseDeveloperController{

    public static function index(): void
    {

        $report =
            MetadataRepairService::scan();

        $repaired = null;

        if (
            ($_GET["action"] ?? "")
            ===
            "repair"
        ) {

            $repaired =
                MetadataRepairService::repair();

            $report =
                MetadataRepairService::scan();

        }

        self::renderDeveloper(
            "developer/metadata-repair",

            [

                "pageTitle" =>
                    "Metadata Repair",

                "report" =>
                    $report,

                "repaired" =>
                    $repaired

            ]

        );

    }

}
