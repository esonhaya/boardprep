<?php

declare(strict_types=1);

namespace App\Core;

class Request
{
    public function query(?string $key = null, mixed $default = null): mixed
    {
        return $this->value($_GET, $key, $default);
    }

    public function input(?string $key = null, mixed $default = null): mixed
    {
        return $this->value($_POST, $key, $default);
    }

    public function file(?string $key = null): mixed
    {
        return $this->value($_FILES, $key, null);
    }

    public function cookie(?string $key = null, mixed $default = null): mixed
    {
        return $this->value($_COOKIE, $key, $default);
    }

    public function server(?string $key = null, mixed $default = null): mixed
    {
        return $this->value($_SERVER, $key, $default);
    }

    public function session(?string $key = null, mixed $default = null): mixed
    {
        return $this->value($_SESSION, $key, $default);
    }

    public function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    public function method(): string
    {
        return strtoupper(
            $_SERVER['REQUEST_METHOD'] ?? 'GET'
        );
    }

    public function uri(): string
    {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }

    public function path(): string
    {
        return parse_url(
            $this->uri(),
            PHP_URL_PATH
        ) ?: '/';
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(
            str_replace('-', '_', $name)
        );

        return $_SERVER[$key] ?? null;
    }

    public function headers(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (!str_starts_with($key, 'HTTP_')) {
                continue;
            }

            $header = str_replace(
                '_',
                '-',
                substr($key, 5)
            );

            $headers[$header] = $value;
        }

        return $headers;
    }

    public function ip(): string
    {
        return $_SERVER['REMOTE_ADDR']
            ?? '0.0.0.0';
    }

    public function isGet(): bool
    {
        return $this->method() === 'GET';
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function isPut(): bool
    {
        return $this->method() === 'PUT';
    }

    public function isDelete(): bool
    {
        return $this->method() === 'DELETE';
    }

    private function value(
        array $source,
        ?string $key,
        mixed $default
    ): mixed {
        if ($key === null) {
            return $source;
        }

        return $source[$key] ?? $default;
    }
}
