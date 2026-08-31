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
                    "Import Questions",
                "expectedFormat" => '[{"id":"...","question":"...","options":[...]}]'
            ]
        );

    }



    public static function import(): void
    {

        if (!isset($_FILES["file"]) || ($_FILES["file"]["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {

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

                "expectedFormat" => '[{"id":"...","question":"...","options":[...]}]',

                "result" =>
                    $result
            ]
        );

    }

}
