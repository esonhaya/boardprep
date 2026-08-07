<?php

declare(strict_types=1);

namespace Tools\Doctor\Simulation\Assertions;

use RuntimeException;
use Tools\Doctor\Simulation\SimulationResponse;

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
            throw new RuntimeException(
                sprintf(
                    'Expected successful response, received HTTP %d.',
                    $response->status
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
