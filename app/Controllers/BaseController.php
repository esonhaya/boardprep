<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\View;

abstract class BaseController
{
    protected static function view(
        string $view,
        array $data = []
    ): void {

        View::render(
            $view,
            $data
        );

    }

    protected static function redirect(
        string $url
    ): never {

        Response::redirect(
            $url
        );

    }
}
