<?php

declare(strict_types=1);

namespace Tools\Doctor\Simulation;

final class SimulationAssertions
{
    /**
     * @param array{
     *     status:int,
     *     output:string,
     *     stderr:string,
     *     exitCode:int,
     *     duration:float,
     *     headers:array<int,string>,
     *     location:string|null,
     *     success:bool
     * } $response
     */
    public static function status(
        array $response,
        int $expected
    ): void {

        if ($response['status'] !== $expected) {
            throw new \RuntimeException(
                sprintf(
                    'Expected HTTP %d, received HTTP %d.',
                    $expected,
                    $response['status']
                )
            );
        }
    }

    /**
     * @param array{
     *     output:string
     * } $response
     */
    public static function contains(
        array $response,
        string $expected
    ): void {

        if (
            !str_contains(
                $response['output'],
                $expected
            )
        ) {
            throw new \RuntimeException(
                "Expected response to contain: {$expected}"
            );
        }
    }

    /**
     * @param array{
     *     output:string
     * } $response
     */
    public static function notContains(
        array $response,
        string $unexpected
    ): void {

        if (
            str_contains(
                $response['output'],
                $unexpected
            )
        ) {
            throw new \RuntimeException(
                "Expected response not to contain: {$unexpected}"
            );
        }
    }

    /**
     * @param array{
     *     location:string|null
     * } $response
     */
    public static function redirectsTo(
        array $response,
        string $expected
    ): void {

        if ($response['location'] !== $expected) {
            throw new \RuntimeException(
                sprintf(
                    'Expected redirect to "%s", received "%s".',
                    $expected,
                    $response['location'] ?? 'none'
                )
            );
        }
    }

    /**
     * @param array{
     *     exitCode:int,
     *     stderr:string
     * } $response
     */
    public static function noRuntimeError(
        array $response
    ): void {

        if ($response['exitCode'] !== 0) {
            throw new \RuntimeException(
                'Simulation failed: '
                . trim($response['stderr'])
            );
        }
    }
}
