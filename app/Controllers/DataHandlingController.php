<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;

final class DataHandlingController
{
    public static function index(): void
    {
        View::render('data-handling/index', [
            'pageTitle' => 'Data and privacy | BoardPrep',
        ]);
    }
}
