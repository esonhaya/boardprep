<?php

declare(strict_types=1);

namespace Tools\Doctor\Checks;

use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;
use Tools\Doctor\History\HistoryRepository;

final class HistoryTrendCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $history =
            (new HistoryRepository())
                ->load();

        if (count($history) < 2) {

            return new CheckResult(
                title: "History Trend",
                status: "PASS",
                summary: "Collecting history..."
            );

        }

        $recent =
            array_slice(
                $history,
                -5
            );

        $details = [];

        foreach ($recent as $run) {

            $details[] =
                sprintf(
                    "%s  %d%%",
                    substr(
                        $run["timestamp"],
                        5,
                        11
                    ),
                    $run["health"]
                );

        }

        $first =
            $recent[0]["health"];

        $last =
            end($recent)["health"];

        $delta =
            $last - $first;

        return new CheckResult(
            title: "History Trend",
            status: "PASS",
            summary:
                sprintf(
                    "%+d%% over last %d run(s)",
                    $delta,
                    count($recent)
                ),
            details: $details
        );
    }

    public function category(): string
    {
        return "History";
    }

    public function priority(): int
    {
        return 16;
    }
}
