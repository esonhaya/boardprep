<?php

declare(strict_types=1);

namespace App\Core;

class Response
{
    public static function redirect(
        string $location,
        int $status = 302
    ): never {

        http_response_code(
            $status
        );

        header(
            "Location: {$location}"
        );

        exit;

    }

    public static function json(
        array $data,
        int $status = 200
    ): never {

        http_response_code(
            $status
        );

        header(
            "Content-Type: application/json"
        );

        echo json_encode(
            $data,
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        );

        exit;

    }

    public static function text(
        string $content,
        int $status = 200
    ): never {

        http_response_code(
            $status
        );

        echo $content;

        exit;

    }
}
