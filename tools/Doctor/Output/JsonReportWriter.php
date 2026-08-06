<?php

declare(strict_types=1);

namespace Tools\Doctor\Output;

use Tools\Doctor\DTO\DoctorResult;

final class JsonReportWriter
{
    public function write(
        DoctorResult $report
    ): void {

        $json =
            (new JsonRenderer())
                ->render($report);

        file_put_contents(
            './storage/doctor/latest-report.json',
            $json
        );

    }
}
