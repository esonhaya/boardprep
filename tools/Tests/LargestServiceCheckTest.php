<?php

declare(strict_types=1);

namespace Tools\Tests;

use Tools\Doctor\Context\DoctorContext;
use Tools\Doctor\Project\BoardPrep\Checks\LargestServiceCheck;
use Tools\Doctor\Rules\Rules;
use Tools\Doctor\Snapshot\ProjectSnapshot;

final class LargestServiceCheckTest extends TestCase
{
    public function run(): void
    {
        $snapshot = new ProjectSnapshot(
            files: [
                [
                    'path' => './app/Services/LargeService.php',
                    'lines' => Rules::serviceMaxLines() - 1,
                ],
                [
                    'path' => './app/Services/SmallService.php',
                    'lines' => 100,
                ],
            ]
        );

        DoctorContext::setSnapshot($snapshot);

        $result =
            (new LargestServiceCheck())
                ->run();

        $this->assertSame(
            'PASS',
            $result->status,
            'A service file below the configured threshold should pass.'
        );

        $this->assertSame(
            './app/Services/LargeService.php (249 lines)',
            $result->summary,
            'LargestServiceCheck should measure the largest service FILE.'
        );

        $warningSnapshot = new ProjectSnapshot(
            files: [
                [
                    'path' => './app/Services/LargeService.php',
                    'lines' => Rules::serviceMaxLines() + 1,
                ],
            ]
        );

        DoctorContext::setSnapshot($warningSnapshot);

        $warningResult =
            (new LargestServiceCheck())
                ->run();

        $this->assertSame(
            'WARNING',
            $warningResult->status,
            'A service file above the configured threshold should warn.'
        );

        $this->assertSame(
            './app/Services/LargeService.php (251 lines)',
            $warningResult->summary,
            'The warning should report service FILE size, not method size.'
        );
    }
}
