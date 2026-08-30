<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

class ExceptionHandler
{
    public function handle(
        Throwable $exception
    ): never {

        error_log(sprintf(
            '[BoardPrep] %s: %s in %s:%d',
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        ));

        http_response_code(
            $this->statusCode($exception)
        );

        header(
            "Content-Type: text/html; charset=UTF-8"
        );

        echo $this->render(
            $exception
        );

        exit;

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

        if (
            is_int($code)
            &&
            $code >= 400
            &&
            $code <= 599
        ) {

            return $code;

        }

        return 500;

    }

    protected function isDebug(): bool
    {
        return Environment::get('APP_ENV', 'production') !== 'production'
            && filter_var(
            Environment::get('APP_DEBUG', 'false'),
            FILTER_VALIDATE_BOOLEAN
        );
    }
}
