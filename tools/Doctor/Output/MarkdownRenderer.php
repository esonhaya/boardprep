<?php

declare(strict_types=1);

namespace Tools\Doctor\Output;

use Tools\Doctor\DTO\DoctorResult;

final class MarkdownRenderer
{
    public function render(
        DoctorResult $report
    ): string {

        $markdown = "# BoardPrep Doctor\n\n";

        foreach ($report->checks as $check) {

            $markdown .= "## {$check->title}\n\n";
            $markdown .= "**Status:** {$check->status}\n\n";

            if ($check->summary !== "") {

                $markdown .= "{$check->summary}\n\n";

            }

            foreach ($check->details as $detail) {

                $markdown .= "- {$detail}\n";

            }

            $markdown .= "\n";

        }

        return $markdown;

    }
}
