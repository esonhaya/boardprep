<?php

namespace App\Core;

class Autoloader
{
    public static function register(): void
    {
        spl_autoload_register(
            function (string $class): void {

                $prefix = 'App\\';

                if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
                    return;
                }

                $relativeClass = substr($class, strlen($prefix));

                $file = dirname(__DIR__) . '/'
                    . str_replace('\\', '/', $relativeClass)
                    . '.php';

                if (is_file($file)) {
                    require_once $file;
                }
            }
        );
    }
}
