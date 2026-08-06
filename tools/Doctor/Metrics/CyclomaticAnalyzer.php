<?php

declare(strict_types=1);

namespace Tools\Doctor\Metrics;

use Tools\Doctor\Snapshot\ProjectSnapshot;

final class CyclomaticAnalyzer
{
    private const PATTERN =
        '/\b(if|elseif|for|foreach|while|case|catch)\b|&&|\|\||\?/';

    public function analyze(
        ProjectSnapshot $snapshot
    ): void {

        foreach ($snapshot->methods as $method) {

            $lines = @file(
                $method["file"],
                FILE_IGNORE_NEW_LINES
            );

            if ($lines === false) {
                continue;
            }

            $body = implode(

                PHP_EOL,

                array_slice(

                    $lines,

                    max(
                        0,
                        $method["line"] - 1
                    ),

                    $method["lines"]

                )

            );

            preg_match_all(

                self::PATTERN,

                $body,

                $matches

            );

            $snapshot->addMetric(

                "cyclomatic",

                [

                    "file" => $method["file"],

                    "method" => $method["name"],

                    "score" => 1 + count($matches[0]),

                ]

            );

        }

    }
}
