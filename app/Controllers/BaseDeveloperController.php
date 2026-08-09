<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\View;
use App\ViewModels\Developer\PageHeaderViewModel;

class BaseDeveloperController extends BaseController
{
    protected static function renderDeveloper(
        string $view,
        array $data = [],
        bool $showFooter = true
    ): void {

        $data["showDeveloperFooter"] =
            $showFooter;

        if (
            !isset($data["pageHeader"])
            && isset($data["pageTitle"])
        ) {
            $data["pageHeader"] = new PageHeaderViewModel(
                (string) $data["pageTitle"]
            );
        }

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
