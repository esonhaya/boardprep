<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\View;

class BaseDeveloperController extends BaseController
{
    protected static function renderDeveloper(
        string $view,
        array $data = [],
        bool $showFooter = true
    ): void {

        $data["showDeveloperFooter"] =
            $showFooter;

        $data["layout"] =
            "developer";

        View::render(
            $view,
            $data
        );

    }

    protected static function developerRedirect(
        string $url
    ): never {

        Response::redirect(
            $url
        );

    }

    protected static function renderDeveloperErrors(
        string $view,
        array $errors,
        array $old = []
    ): void {

        self::renderDeveloper(
            $view,
            [
                "errors" => $errors,
                "old" => $old
            ]
        );

    }
}
