<?php

class View
{
    public static function render(string $view, array $data = []): void
    {
        extract($data);

        ob_start();

        include __DIR__ . "/../Views/" . $view . ".php";

        $content = ob_get_clean();

        $layout = $layout ?? "main";

        include __DIR__ . "/../Views/layouts/" . $layout . ".php";
    }
}
