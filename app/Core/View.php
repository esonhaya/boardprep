<?php

declare(strict_types=1);

namespace App\Core;

class View
{
    public static function render(
        string $view,
        array $data = []
    ): void {
        extract($data);

        ob_start();

        require dirname(__DIR__)
            . '/Views/'
            . $view
            . '.php';

        $content = ob_get_clean();

        $layout = $layout ?? 'main';

        require dirname(__DIR__)
            . '/Views/layouts/'
            . $layout
            . '.php';
    }
}
