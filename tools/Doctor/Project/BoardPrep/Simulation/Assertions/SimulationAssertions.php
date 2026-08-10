<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Simulation\Assertions;

use RuntimeException;
use Tools\Doctor\Project\BoardPrep\Simulation\SimulationResponse;

final class SimulationAssertions
{
    public static function status(
        SimulationResponse $response,
        int $expected
    ): void {
        if ($response->status !== $expected) {
            throw new RuntimeException(
                sprintf(
                    'Expected HTTP status %d, received %d.',
                    $expected,
                    $response->status
                )
            );
        }
    }

    public static function successful(
        SimulationResponse $response
    ): void {
        if (!$response->isSuccessful()) {
            $body = trim($response->body);

            if ($body === '') {
                $body = '[empty response body]';
            }

            $body = preg_replace(
                '/\s+/',
                ' ',
                $body
            ) ?? $body;

            if (strlen($body) > 500) {
                $body = substr($body, 0, 500) . '...';
            }

            throw new RuntimeException(
                sprintf(
                    'Expected successful response, received HTTP %d. Body: %s',
                    $response->status,
                    $body
                )
            );
        }
    }

    public static function contains(
        SimulationResponse $response,
        string $needle
    ): void {
        if (!$response->contains($needle)) {
            throw new RuntimeException(
                sprintf(
                    'Expected response to contain "%s".',
                    $needle
                )
            );
        }
    }

    public static function notContains(
        SimulationResponse $response,
        string $needle
    ): void {
        if ($response->contains($needle)) {
            throw new RuntimeException(
                sprintf(
                    'Expected response not to contain "%s".',
                    $needle
                )
            );
        }
    }
}
