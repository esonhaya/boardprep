<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\Shared\CsrfTokenService;
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
                "expectedFormat" => '[{"id":"...","question":"...","options":[...]}]',
                "csrfToken" => CsrfTokenService::token(),
            ]
        );

    }



    public static function import(): void
    {

        if (!CsrfTokenService::valid(Request::input('_csrf'))) {
            Response::text('Invalid CSRF token.', 403);
        }

        if (!isset($_FILES["file"]) || ($_FILES["file"]["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Response::redirect('/question-import');
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
