<?php

declare(strict_types=1);

namespace App\Controllers;
use App\Services\Shared\QuestionImportService;

class QuestionImportController extends BaseDeveloperController
{

    public static function index(): void
    {

        self::renderDeveloper(
            "developer/question-import",
            [
                "pageTitle" =>
                    "Import Questions"
            ]
        );

    }



    public static function import(): void
    {

        if (
            !isset($_FILES["file"])
        ) {

            header(
                "Location: /question-import"
            );

            exit;

        }


        $result =
            QuestionImportService::importJson(
                $_FILES["file"]["tmp_name"]
            );


        self::renderDeveloper(
            "developer/question-import",
            [
                "pageTitle" =>
                    "Import Questions",

                "result" =>
                    $result
            ]
        );

    }

}
