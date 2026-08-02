<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;

class EnglishController extends BaseController
{
    public static function index(): void
    {
        View::render(
            "english/index",
            [
                "pageTitle" => "English Major"
            ]
        );
    }
}
