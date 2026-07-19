<?php

class Autoloader
{
    public static function register(): void
    {
        spl_autoload_register(
            function ($class) {

                $folders = [

                    __DIR__,

                    __DIR__ . "/../Controllers",

                    __DIR__ . "/../Models",

                    __DIR__ . "/../Repositories",

                    __DIR__ . "/../Services"

                ];

                foreach ($folders as $folder) {

                    $file =
                        $folder .
                        "/" .
                        $class .
                        ".php";

                    if (file_exists($file)) {
                        require_once $file;
                        return;
                    }

                }

            }
        );
    }
}
