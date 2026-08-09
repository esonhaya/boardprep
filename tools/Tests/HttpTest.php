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

        /*
        |--------------------------------------------------------------------------
        | Public routes
        |--------------------------------------------------------------------------
        */

        $this->test(
            'GET /',
            $simulator->request('GET', '/'),
            $passed,
            $failed,
            function (array $response): void {
                $this->assertSuccessful($response);
                $this->assertNoRuntimeError($response);
                $this->assertContains(
                    $response['output'],
                    'BoardPrep'
                );
            }
        );

        $this->test(
            'GET /grammar',
            $simulator->request('GET', '/grammar'),
            $passed,
            $failed,
            function (array $response): void {
                $this->assertSuccessful($response);
                $this->assertNoRuntimeError($response);
                $this->assertContains(
                    $response['output'],
                    'Grammar'
                );
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Application routes
        |--------------------------------------------------------------------------
        */

        $this->test(
            'GET /dashboard',
            $simulator->request('GET', '/dashboard'),
            $passed,
            $failed,
            function (array $response): void {
                $this->assertSuccessful($response);
                $this->assertNoRuntimeError($response);
            }
        );

        $this->test(
            'GET /profile',
            $simulator->request('GET', '/profile'),
            $passed,
            $failed,
            function (array $response): void {
                $this->assertSuccessful($response);
                $this->assertNoRuntimeError($response);
            }
        );

        $this->test(
            'GET /progress',
            $simulator->request('GET', '/progress'),
            $passed,
            $failed,
            function (array $response): void {
                $this->assertSuccessful($response);
                $this->assertNoRuntimeError($response);
            }
        );

        $this->test(
            'GET /boards',
            $simulator->request('GET', '/boards'),
            $passed,
            $failed,
            function (array $response): void {
                $this->assertSuccessful($response);
                $this->assertNoRuntimeError($response);
            }
        );

        $this->test(
            'GET /subjects',
            $simulator->request('GET', '/subjects'),
            $passed,
            $failed,
            function (array $response): void {
                $this->assertSuccessful($response);
                $this->assertNoRuntimeError($response);
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Developer routes
        |--------------------------------------------------------------------------
        */

        $this->test(
            'GET /question-editor',
            $simulator->request('GET', '/question-editor'),
            $passed,
            $failed,
            function (array $response): void {
                $this->assertSuccessful($response);
                $this->assertNoRuntimeError($response);
            }
        );

        $this->test(
            'GET /question-import',
            $simulator->request('GET', '/question-import'),
            $passed,
            $failed,
            function (array $response): void {
                $this->assertSuccessful($response);
                $this->assertNoRuntimeError($response);
            }
        );

        $this->test(
            'GET /question-quality',
            $simulator->request('GET', '/question-quality'),
            $passed,
            $failed,
            function (array $response): void {
                $this->assertSuccessful($response);
                $this->assertNoRuntimeError($response);
            }
        );

        $this->test(
            'GET /question-inspector',
            $simulator->request('GET', '/question-inspector'),
            $passed,
            $failed,
            function (array $response): void {
                $this->assertSuccessful($response);
                $this->assertNoRuntimeError($response);
            }
        );

        $this->test(
            'GET /coverage',
            $simulator->request('GET', '/coverage'),
            $passed,
            $failed,
            function (array $response): void {
                $this->assertSuccessful($response);
                $this->assertNoRuntimeError($response);
            }
        );

        $this->test(
            'GET /taxonomy',
            $simulator->request('GET', '/taxonomy'),
            $passed,
            $failed,
            function (array $response): void {
                $this->assertSuccessful($response);
                $this->assertNoRuntimeError($response);
            }
        );

        $this->test(
            'GET /metadata-repair',
            $simulator->request('GET', '/metadata-repair'),
            $passed,
            $failed,
            function (array $response): void {
                $this->assertSuccessful($response);
                $this->assertNoRuntimeError($response);
            }
        );

        $this->test(
            'GET /blueprints',
            $simulator->request('GET', '/blueprints'),
            $passed,
            $failed,
            function (array $response): void {
                $this->assertSuccessful($response);
                $this->assertNoRuntimeError($response);
            }
        );

        $this->test(
            'GET /blueprint-health',
            $simulator->request('GET', '/blueprint-health'),
            $passed,
            $failed,
            function (array $response): void {
                $this->assertSuccessful($response);
                $this->assertNoRuntimeError($response);
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
                '/',
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
