<?php

declare(strict_types=1);

namespace Tools\Tests;

use Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator;

final class HttpTest
{
    public function run(): int
    {
        $simulator = new HttpSimulator(
            dirname(__DIR__, 2)
            . '/public/index.php'
        );

        $passed = 0;
        $failed = 0;

        echo "======================================\n";
        echo " BoardPrep HTTP Simulation\n";
        echo " Real public/index.php\n";
        echo "======================================\n";

        $this->runPublicRoutes(
            $simulator,
            $passed,
            $failed
        );

        $this->runApplicationRoutes(
            $simulator,
            $passed,
            $failed
        );

        $this->runQuizStartRoundTrip(
            $simulator,
            $passed,
            $failed
        );

        $this->runResilienceMatrix(
            $simulator,
            $passed,
            $failed
        );

        $this->runDeveloperRoutes(
            $simulator,
            $passed,
            $failed
        );

        $this->runEnvironmentTests(
            $simulator,
            $passed,
            $failed
        );

        echo "\n======================================\n";
        echo "PASS: {$passed}\n";
        echo "FAIL: {$failed}\n";
        echo "======================================\n";

        return $failed === 0 ? 0 : 1;
    }

    private function runPublicRoutes(
        HttpSimulator $simulator,
        int &$passed,
        int &$failed
    ): void {
        $this->testRoute(
            $simulator,
            'GET /',
            '/',
            $passed,
            $failed,
            'BoardPrep'
        );

        $this->testRoute(
            $simulator,
            'GET /grammar',
            '/grammar',
            $passed,
            $failed,
            'Grammar'
        );
    }

    private function runApplicationRoutes(
        HttpSimulator $simulator,
        int &$passed,
        int &$failed
    ): void {
        foreach (
            [
                '/dashboard',
                '/profile',
                '/progress',
                '/boards',
                '/subjects',
            ] as $path
        ) {
            $this->testRoute(
                $simulator,
                "GET {$path}",
                $path,
                $passed,
                $failed
            );
        }

        $this->test(
            'GET /board/view reaches registered board detail',
            $simulator->request('GET', '/board/view', ['id' => 'let']),
            $passed,
            $failed,
            'Board Information'
        );

        $this->testExpectedStatus(
            'malformed board id fails safely',
            $simulator->request('GET', '/board/view', ['id' => []]),
            302,
            $passed,
            $failed
        );

        $this->test(
            'malformed developer filters fail safely',
            $simulator->request('GET', '/question-editor', [
                'search' => [],
                'difficulty' => [],
                'topic' => [],
            ]),
            $passed,
            $failed
        );

        $this->test(
            'question inspector id reaches detail view',
            $simulator->request('GET', '/question-inspector', ['id' => '1']),
            $passed,
            $failed,
            'Question Information'
        );

        $this->testExpectedStatus(
            'malformed question inspector id fails safely',
            $simulator->request('GET', '/question-inspector', ['id' => []]),
            200,
            $passed,
            $failed
        );

        foreach (['/board/archive', '/subject/archive', '/question-editor/archive'] as $path) {
            $this->testExpectedStatus(
                "GET mutation rejected for {$path}",
                $simulator->request('GET', $path, ['id' => 'missing']),
                404,
                $passed,
                $failed
            );
        }
    }

    private function runDeveloperRoutes(
        HttpSimulator $simulator,
        int &$passed,
        int &$failed
    ): void {
        foreach (
            [
                '/question-editor',
                '/question-import',
                '/question-quality',
                '/question-inspector',
                '/coverage',
                '/taxonomy',
                '/metadata-repair',
                '/blueprints',
                '/blueprint-health',
            ] as $path
        ) {
            $this->testRoute(
                $simulator,
                "GET {$path}",
                $path,
                $passed,
                $failed
            );
        }
    }

    private function runQuizStartRoundTrip(
        HttpSimulator $simulator,
        int &$passed,
        int &$failed
    ): void {
        $this->test(
            'GET /quiz retry action starts quiz',
            $simulator->request(
                'GET',
                '/quiz',
                [
                    'action' => 'start',
                    'subject' => 'English',
                    'topic' => 'Grammar',
                    'mode' => 'practice',
                    'difficulty' => 'mixed',
                    'count' => 1,
                ],
                [],
                [],
                ['PHPSESSID' => 'batch424-quiz-start']
            ),
            $passed,
            $failed,
            'Question '
        );
    }

    private function runEnvironmentTests(
        HttpSimulator $simulator,
        int &$passed,
        int &$failed
    ): void {
        $this->test(
            'POST request environment',
            $simulator->request(
                'POST',
                '/quiz',
                [],
                [
                    'simulation' => 'true',
                    'value' => 'BoardPrep',
                ]
            ),
            $passed,
            $failed
        );

        $this->test(
            'Custom server environment',
            $simulator->request(
                'GET',
                '/',
                [],
                [],
                [
                    'HTTP_HOST' =>
                        'boardprep.test',
                    'HTTPS' =>
                        'on',
                    'HTTP_USER_AGENT' =>
                        'BoardPrep-Simulator',
                ]
            ),
            $passed,
            $failed
        );

        $this->test(
            'Cookie environment',
            $simulator->request(
                'GET',
                '/',
                [],
                [],
                [],
                [
                    'boardprep_test' =>
                        'simulation',
                ]
            ),
            $passed,
            $failed
        );
    }

    private function runResilienceMatrix(
        HttpSimulator $simulator,
        int &$passed,
        int &$failed
    ): void {
        $cookies = ['PHPSESSID' => 'batch439-http-resilience-' . getmypid()];

        foreach ([
            ['malformed query action', 'GET', ['action' => []], [], 302],
            ['malformed POST action', 'POST', [], ['action' => ['submit']], 302],
            ['unsupported quiz action', 'GET', ['action' => 'obsolete'], [], 302],
            ['missing quiz session submission', 'POST', ['action' => 'submit'], [], 302],
        ] as [$name, $method, $query, $post, $status]) {
            $response = $simulator->request($method, '/quiz', $query, $post, [], $cookies);
            $this->testExpectedStatus($name, $response, $status, $passed, $failed);
        }

        $extreme = $simulator->request('GET', '/quiz', [
            'action' => 'start', 'subject' => 'English', 'mode' => 'practice',
            'difficulty' => ['hard'], 'count' => '1e309',
        ], [], [], $cookies);
        $this->test('extreme and malformed quiz settings', $extreme, $passed, $failed);

        foreach (['/dashboard', '/history', '/profile', '/progress', '/study'] as $path) {
            $this->test("learner recovery route {$path}", $simulator->request('GET', $path), $passed, $failed);
        }
    }

    private function testExpectedStatus(
        string $name,
        array $response,
        int $status,
        int &$passed,
        int &$failed
    ): void {
        if ($response['exitCode'] === 0 && $response['status'] === $status
            && !str_contains($response['output'], 'Stack trace')) {
            echo "[PASS] {$name} (HTTP {$status})\n";
            $passed++;
            return;
        }

        echo "[FAIL] {$name}\n";
        echo "       Expected HTTP {$status}; received HTTP {$response['status']}.\n";
        $failed++;
    }

    private function testRoute(
        HttpSimulator $simulator,
        string $name,
        string $path,
        int &$passed,
        int &$failed,
        ?string $contains = null
    ): void {
        $this->test(
            $name,
            $simulator->request(
                'GET',
                $path
            ),
            $passed,
            $failed,
            $contains
        );
    }

    private function test(
        string $name,
        array $response,
        int &$passed,
        int &$failed,
        ?string $contains = null
    ): void {
        try {
            $this->assertSuccessful($response);
            $this->assertNoRuntimeError($response);

            if ($contains !== null) {
                $this->assertContains(
                    $response['output'],
                    $contains
                );
            }

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
