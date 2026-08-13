<?php

declare(strict_types=1);

namespace Tools\Tests;

use Tools\Doctor\Context\DoctorContext;
use Tools\Doctor\DTO\CheckResult;
use Tools\Doctor\Project\BoardPrep\Checks\LargestMethodCheck;
use Tools\Doctor\Rules\Rules;
use Tools\Doctor\Snapshot\ProjectSnapshot;

final class LargestMethodCheckTest extends TestCase
{
    public function run(): void
    {
        $snapshot = new ProjectSnapshot();

        $snapshot->setMetric(
            'largest-method',
            [
                [
                    'file' => 'app/Services/LargeService.php',
                    'name' => 'largeService',
                    'visibility' => 'public',
                    'line' => 10,
                    'endLine' => 100,
                    'lines' => 91,
                ],
                [
                    'file' => 'app/Controllers/LargeController.php',
                    'name' => 'largeController',
                    'visibility' => 'public',
                    'line' => 10,
                    'endLine' => 100,
                    'lines' => 91,
                ],
            ]
        );

        DoctorContext::setSnapshot($snapshot);

        $result =
            (new LargestMethodCheck())
                ->run();

        $this->assertTrue(
            $result instanceof CheckResult,
            'LargestMethodCheck should return CheckResult.'
        );

        $this->assertSame(
            'WARNING',
            $result->status,
            'A large-method metric should produce a warning.'
        );

        $this->assertSame(
            'Largest method contains 91 lines.',
            $result->summary
        );

        $this->assertSame(
            1,
            $result->findingCount(),
            'A large method should produce exactly one diagnostic finding.'
        );

        $this->assertSame(
            'File: app/Services/LargeService.php',
            $result->details[1] ?? null,
            'The check should consume the first method from the metric source of truth.'
        );

        $this->assertSame(
            Rules::methodMaxLines(),
            60,
            'The method threshold should come from the central Rules source.'
        );

        $emptySnapshot =
            new ProjectSnapshot();

        $emptySnapshot->setMetric(
            'largest-method',
            []
        );

        DoctorContext::setSnapshot($emptySnapshot);

        $emptyResult =
            (new LargestMethodCheck())
                ->run();

        $this->assertSame(
            'PASS',
            $emptyResult->status,
            'An empty largest-method metric should pass.'
        );

        $this->assertSame(
            0,
            $emptyResult->findingCount(),
            'An empty largest-method metric should have no findings.'
        );
    }
}
