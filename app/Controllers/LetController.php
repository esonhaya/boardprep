<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;

class LetController extends BaseController
{
    public static function index(): void
    {
        View::render(
            'let/index',
            [
                'pageTitle' => 'LET',
            ]
        );
    }
}
