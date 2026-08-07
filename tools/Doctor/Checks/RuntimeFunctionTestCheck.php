<?php

declare(strict_types=1);

namespace Tools\Doctor\Checks;

use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;

final class RuntimeFunctionTestCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $testFile = dirname(__DIR__, 3) . '/tests/function-test.php';

        if (!is_file($testFile)) {
            return new CheckResult(
                title: 'Runtime Function Test',
                status: 'WARNING',
                summary: 'Function test suite is not available.',
                details: [
                    'Expected: tests/function-test.php',
                ],
                recommendations: [
                    'Create the project runtime function test suite.',
                ],
                score: 50,
            );
        }

        $command =
            escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg($testFile);

        $output = [];
        $exitCode = 0;

        exec(
            $command . ' 2>&1',
            $output,
            $exitCode
        );

        $text = implode("\n", $output);

        $pass = $this->extractCount(
            $text,
            'PASS'
        );

        $fail = $this->extractCount(
            $text,
            'FAIL'
        );

        $time = $this->extractTime($text);

        if (
            $exitCode === 0
            && $fail === 0
        ) {
            return new CheckResult(
                title: 'Runtime Function Test',
                status: 'PASS',
                summary: sprintf(
                    '%d/%d runtime simulations passed.',
                    $pass,
                    $pass + $fail
                ),
                details: [
                    "Passed: {$pass}",
                    "Failed: {$fail}",
                    "Execution time: {$time}",
                ],
                recommendations: [],
                score: 100,
            );
        }

        $details = [
            "Passed: {$pass}",
            "Failed: {$fail}",
            "Execution time: {$time}",
        ];

        $failures = $this->extractFailures($output);

        foreach ($failures as $failure) {
            $details[] = $failure;
        }

        return new CheckResult(
            title: 'Runtime Function Test',
            status: 'FAIL',
            summary: sprintf(
                '%d runtime simulation(s) failed.',
                $fail
            ),
            details: $details,
            recommendations: [
                'Fix runtime simulation failures before continuing architectural refactoring.',
                'Do not treat a passing architecture score as sufficient when runtime tests fail.',
            ],
            score: 0,
        );
    }

    public function category(): string
    {
        return 'Runtime';
    }

    public function priority(): int
    {
        return 5;
    }

    private function extractCount(
        string $text,
        string $label
    ): int {
        if (
            preg_match(
                '/^\s*' . preg_quote($label, '/') . '\s*:\s*(\d+)/mi',
                $text,
                $matches
            )
        ) {
            return (int) $matches[1];
        }

        return 0;
    }

    private function extractTime(
        string $text
    ): string {
        if (
            preg_match(
                '/^\s*TIME\s*:\s*(.+)$/mi',
                $text,
                $matches
            )
        ) {
            return trim($matches[1]);
        }

        return 'unknown';
    }

    /**
     * @param string[] $output
     * @return string[]
     */
    private function extractFailures(
        array $output
    ): array {
        $failures = [];

        foreach ($output as $line) {
            if (
                str_starts_with(
                    trim($line),
                    '[FAIL]'
                )
            ) {
                $failures[] = trim($line);
            }
        }

        return array_slice(
            $failures,
            0,
            10
        );
    }
}
