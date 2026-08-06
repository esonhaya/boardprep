<?php

declare(strict_types=1);

namespace App\Core;

final class Autoloader
{
    /**
     * @var array<string,string>
     */
    private static array $prefixes = [
        'App\\'   => __DIR__ . '/../',
        'Tools\\' => __DIR__ . '/../../tools/',
    ];

    public static function register(): void
    {
        spl_autoload_register(
            function (string $class): void {

                foreach (self::$prefixes as $prefix => $baseDirectory) {

                    if (
                        strncmp(
                            $prefix,
                            $class,
                            strlen($prefix)
                        ) !== 0
                    ) {
                        continue;
                    }

                    $relativeClass = substr(
                        $class,
                        strlen($prefix)
                    );

                    $file =
                        rtrim($baseDirectory, '/')
                        . '/'
                        . str_replace('\\', '/', $relativeClass)
                        . '.php';

                    if (is_file($file)) {
                        require_once $file;
                    }

                    return;
                }
            }
        );
    }
}
