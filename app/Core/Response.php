<?php

declare(strict_types=1);

namespace App\Core;

class Response
{
    private int $status = 200;

    /**
     * @var array<string, string>
     */
    private array $headers = [];

    private string $content = '';

    public static function make(
        string $content = '',
        int $status = 200
    ): self {
        $response = new self();

        $response->status = $status;
        $response->content = $content;

        return $response;
    }

    public static function json(
        array $data,
        int $status = 200
    ): self {
        return self::make(
            json_encode(
                $data,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_PRETTY_PRINT
            ) ?: '{}',
            $status
        )->header(
            'Content-Type',
            'application/json'
        );
    }

    public static function redirect(
        string $location,
        int $status = 302
    ): self {
        return self::make('', $status)
            ->header(
                'Location',
                $location
            );
    }

    public function header(
        string $name,
        string $value
    ): self {
        $this->headers[$name] = $value;

        return $this;
    }

    public function status(
        int $status
    ): self {
        $this->status = $status;

        return $this;
    }

    public function content(
        string $content
    ): self {
        $this->content = $content;

        return $this;
    }

    public function send(): never
    {
        http_response_code(
            $this->status
        );

        foreach (
            $this->headers as $name => $value
        ) {
            header(
                sprintf(
                    '%s: %s',
                    $name,
                    $value
                )
            );
        }

        echo $this->content;

        exit;
    }
}
