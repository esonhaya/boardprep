<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

class Router
{
    /**
     * @var array<string, array<string, callable|array{0:string,1:string}>>
     */
    private array $routes = [];

    public function get(
        string $uri,
        callable|array $handler
    ): void {
        $this->add(
            'GET',
            $uri,
            $handler
        );
    }

    public function post(
        string $uri,
        callable|array $handler
    ): void {
        $this->add(
            'POST',
            $uri,
            $handler
        );
    }

    private function add(
        string $method,
        string $uri,
        callable|array $handler
    ): void {
        $this->routes[$method][
            $this->normalize($uri)
        ] = $handler;
    }

    public function dispatch(
        string $method,
        string $uri
    ): mixed {
        $method = strtoupper($method);
        $uri = $this->normalize($uri);

        $handler =
            $this->routes[$method][$uri]
            ?? null;

        if ($handler === null) {
            throw new RuntimeException(
                'Route not found.',
                404
            );
        }

        if (is_array($handler)) {
            [$controller, $action] = $handler;

            if (!class_exists($controller)) {
                throw new RuntimeException(
                    "Controller [$controller] not found."
                );
            }

            if (!method_exists($controller, $action)) {
                throw new RuntimeException(
                    "Method [$action] not found in [$controller]."
                );
            }

            return $controller::$action();
        }

        return $handler();
    }

    private function normalize(
        string $uri
    ): string {
        $path =
            parse_url(
                $uri,
                PHP_URL_PATH
            );

        if (!is_string($path)) {
            $path = '/';
        }

        $path = rtrim($path, '/');

        return $path === ''
            ? '/'
            : $path;
    }
}
