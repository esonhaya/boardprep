<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

class ExceptionHandler
{
    public function handle(Throwable $exception): never
    {
        $status = $this->statusCode($exception);

        $response = Response::make(
            $this->render($exception),
            $status
        )->header(
            'Content-Type',
            'text/html; charset=UTF-8'
        );

        $response->send();
    }

    protected function render(
        Throwable $exception
    ): string {
        if ($this->isDebug()) {
            return sprintf(
                '<h1>%s</h1><p>%s</p><pre>%s</pre>',
                htmlspecialchars($exception::class),
                htmlspecialchars($exception->getMessage()),
                htmlspecialchars($exception->getTraceAsString())
            );
        }

        return sprintf(
            '<h1>Error %d</h1><p>Something went wrong.</p>',
            $this->statusCode($exception)
        );
    }

    protected function statusCode(
        Throwable $exception
    ): int {
        $code = $exception->getCode();

        if (is_int($code) && $code >= 400 && $code <= 599) {
            return $code;
        }

        return 500;
    }

    protected function isDebug(): bool
    {
        return ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
    }
}
