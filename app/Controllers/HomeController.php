<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;

class HomeController extends BaseController
{
    public static function index(): void
    {
        View::render(
            "home/index",
            [
                "pageTitle" => "BoardPrep"
            ]
        );
    }
}
