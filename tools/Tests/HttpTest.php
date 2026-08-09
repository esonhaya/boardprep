<?php

declare(strict_types=1);

namespace Tools\Tests;

use Tools\Doctor\Simulation\HttpSimulator;

final class HttpTest
{
    public function run(): int
    {
        $entryPoint =
            dirname(__DIR__, 2)
            . '/public/index.php';

        $simulator =
            new HttpSimulator($entryPoint);

        $passed = 0;
        $failed = 0;

        echo "======================================\n";
        echo " BoardPrep HTTP Simulation\n";
        echo " Real public/index.php\n";
        echo "======================================\n";

        $this->test(
            'GET /',
            $simulator->request('GET', '/'),
            $passed,
            $failed,
            function (array $response): void {
                $this->assertSuccessful($response);
                $this->assertContains(
                    $response['output'],
                    'BoardPrep'
                );
            }
        );

        $this->test(
            'GET /?page=let',
            $simulator->request(
                'GET',
                '/?page=let',
                ['page' => 'let']
            ),
            $passed,
            $failed,
            function (array $response): void {
                $this->assertSuccessful($response);
                $this->assertContains(
                    $response['output'],
                    'LET'
                );
            }
        );

        $this->test(
            'GET /?page=english',
            $simulator->request(
                'GET',
                '/?page=english',
                ['page' => 'english']
            ),
            $passed,
            $failed,
            function (array $response): void {
                $this->assertSuccessful($response);
                $this->assertContains(
                    $response['output'],
                    'English'
                );
            }
        );

        $this->test(
            'GET /?page=grammar',
            $simulator->request(
                'GET',
                '/?page=grammar',
                ['page' => 'grammar']
            ),
            $passed,
            $failed,
            function (array $response): void {
                $this->assertSuccessful($response);
                $this->assertContains(
                    $response['output'],
                    'Grammar'
                );
            }
        );

        /*
        |--------------------------------------------------------------------------
        | POST simulation
        |--------------------------------------------------------------------------
        */

        $this->test(
            'POST request environment',
            $simulator->request(
                'POST',
                '/?page=home',
                [],
                [
                    'simulation' => 'true',
                    'value' => 'BoardPrep',
                ]
            ),
            $passed,
            $failed,
            function (array $response): void {
                $this->assertSuccessful($response);
                $this->assertNoRuntimeError($response);
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Custom server variables
        |--------------------------------------------------------------------------
        */

        $this->test(
            'Custom server environment',
            $simulator->request(
                'GET',
                '/',
                [],
                [],
                [
                    'HTTP_HOST' => 'boardprep.test',
                    'HTTPS' => 'on',
                    'HTTP_USER_AGENT' => 'BoardPrep-Simulator',
                ]
            ),
            $passed,
            $failed,
            function (array $response): void {
                $this->assertSuccessful($response);
                $this->assertNoRuntimeError($response);
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Cookies
        |--------------------------------------------------------------------------
        */

        $this->test(
            'Cookie environment',
            $simulator->request(
                'GET',
                '/',
                [],
                [],
                [],
                [
                    'boardprep_test' => 'simulation',
                ]
            ),
            $passed,
            $failed,
            function (array $response): void {
                $this->assertSuccessful($response);
                $this->assertNoRuntimeError($response);
            }
        );

        echo "\n======================================\n";
        echo "PASS: {$passed}\n";
        echo "FAIL: {$failed}\n";
        echo "======================================\n";

        return $failed === 0 ? 0 : 1;
    }

    private function test(
        string $name,
        array $response,
        int &$passed,
        int &$failed,
        callable $assertions
    ): void {
        try {
            $assertions($response);

            echo "[PASS] {$name} ("
                . number_format(
                    $response['duration'] * 1000,
                    2
                )
                . " ms)\n";

            $passed++;

        } catch (\Throwable $exception) {

            echo "[FAIL] {$name}\n";
            echo "       {$exception->getMessage()}\n";

            if ($response['stderr'] !== '') {
                echo "       STDERR: "
                    . trim($response['stderr'])
                    . "\n";
            }

            $failed++;
        }
    }

    private function assertSuccessful(
        array $response
    ): void {
        if (!$response['success']) {
            throw new \RuntimeException(
                "Expected successful response, received "
                . "HTTP {$response['status']}."
            );
        }
    }

    private function assertNoRuntimeError(
        array $response
    ): void {
        if ($response['exitCode'] !== 0) {
            throw new \RuntimeException(
                'Runtime error: '
                . trim($response['stderr'])
            );
        }
    }

    private function assertContains(
        string $haystack,
        string $needle
    ): void {
        if (!str_contains($haystack, $needle)) {
            throw new \RuntimeException(
                "Expected response to contain: {$needle}"
            );
        }
    }
}
