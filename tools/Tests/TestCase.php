<?php

declare(strict_types=1);

namespace Tools\Tests;

use Throwable;

abstract class TestCase
{
    private int $assertions = 0;

    abstract public function run(): void;

    public function assertions(): int
    {
        return $this->assertions;
    }

    protected function assertTrue(
        bool $condition,
        string $message = 'Expected condition to be true.'
    ): void {
        $this->assertions++;

        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    protected function assertFalse(
        bool $condition,
        string $message = 'Expected condition to be false.'
    ): void {
        $this->assertions++;

        if ($condition) {
            throw new \RuntimeException($message);
        }
    }

    protected function assertSame(
        mixed $expected,
        mixed $actual,
        string $message = ''
    ): void {
        $this->assertions++;

        if ($expected !== $actual) {
            if ($message === '') {
                $message =
                    'Expected '
                    . var_export($expected, true)
                    . ', got '
                    . var_export($actual, true)
                    . '.';
            }

            throw new \RuntimeException($message);
        }
    }

    protected function assertNotNull(
        mixed $value,
        string $message = 'Expected value to be non-null.'
    ): void {
        $this->assertions++;

        if ($value === null) {
            throw new \RuntimeException($message);
        }
    }

    protected function assertThrows(
        string $exceptionClass,
        callable $callback
    ): void {
        $this->assertions++;

        try {
            $callback();
        } catch (Throwable $exception) {
            if (!$exception instanceof $exceptionClass) {
                throw new \RuntimeException(
                    'Expected '
                    . $exceptionClass
                    . ', got '
                    . get_class($exception)
                    . '.'
                );
            }

            return;
        }

        throw new \RuntimeException(
            'Expected '
            . $exceptionClass
            . ' to be thrown.'
        );
    }
}
