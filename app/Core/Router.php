<?php

class Router
{
    private static array $routes = [];

    public static function get(
        string $page,
        callable $callback
    ): void
    {
        self::$routes[$page] = $callback;
    }

    public static function dispatch(): void
    {
        $page = $_GET["page"] ?? "home";

        if (isset(self::$routes[$page])) {

            call_user_func(
                self::$routes[$page]
            );

            return;
        }

        http_response_code(404);

        echo "404 - Page Not Found";
    }
}
