<?php

class Autoloader
{
    public static function register(): void
    {
        spl_autoload_register(function ($class) {

            $directories = [

                __DIR__,

                __DIR__ . "/../Controllers",

                __DIR__ . "/../Models",

                __DIR__ . "/../Repositories",

                __DIR__ . "/../Services",

                __DIR__ . "/../Services/Quiz",

                __DIR__ . "/../Services/Learning",

                __DIR__ . "/../Services/Profile",

                __DIR__ . "/../Services/Shared",

                __DIR__ . "/../Services/Quality",

                __DIR__ . "/../Services/Quality/Validators",

                __DIR__ . "/../Services/RepositoryHealth",

                __DIR__ . "/../Services/RepositoryHealth/Contracts",

                __DIR__ . "/../Services/RepositoryHealth/DTO",

                __DIR__ . "/../Services/RepositoryHealth/Engine"

            ];

            foreach ($directories as $directory) {

                $file =
                    $directory .
                    "/" .
                    $class .
                    ".php";

                if (file_exists($file)) {

                    require_once $file;

                    return;

                }

            }

        });
    }
}
