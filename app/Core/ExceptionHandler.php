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

        $statusCode = $this->statusCode($exception);
        if (in_array($statusCode, [404, 405], true)) {
            $bufferLevel = ob_get_level();
            ob_start();

            try {
                View::render('errors/http', [
                    'pageTitle' => $statusCode === 404 ? 'Page not found' : 'Method not allowed',
                    'statusCode' => $statusCode,
                ]);

                return (string) ob_get_clean();
            } catch (Throwable) {
                while (ob_get_level() > $bufferLevel) {
                    ob_end_clean();
                }
            }

        }

        return sprintf(
            '<h1>Error %d</h1><p>Something went wrong.</p>',
            $statusCode
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
