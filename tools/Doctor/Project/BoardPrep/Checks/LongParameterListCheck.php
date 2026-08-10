<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;

final class LongParameterListCheck implements CheckInterface
{
    private const MAX_PARAMETERS = 5;

    public function run(): CheckResult
    {
        $offenders = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                "app",
                RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $file) {

            if (
                !$file->isFile()
                || $file->getExtension() !== "php"
            ) {
                continue;
            }

            $contents = file_get_contents(
                $file->getPathname()
            );

            if ($contents === false) {
                continue;
            }

            preg_match_all(
                '/function\s+\w+\s*\((.*?)\)/s',
                $contents,
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $match) {

                $params = trim($match[1]);

                if ($params === "") {
                    continue;
                }

                $count = substr_count(
                    $params,
                    ","
                ) + 1;

                if ($count > self::MAX_PARAMETERS) {

                    $offenders[] =
                        str_replace(
                            getcwd() . DIRECTORY_SEPARATOR,
                            "",
                            $file->getPathname()
                        )
                        . " ({$count} parameters)";
                }
            }
        }

        return new CheckResult(

            title: "Long Parameter Lists",

            status:
                empty($offenders)
                    ? "PASS"
                    : "WARNING",

            summary:
                empty($offenders)
                    ? "No long parameter lists found."
                    : count($offenders) . " long parameter lists found.",

            details:
                array_slice(
                    $offenders,
                    0,
                    20
                ),

            recommendations:
                empty($offenders)
                    ? []
                    : [
                        "Consider DTOs, value objects or builders for large parameter lists."
                    ]

        );
    }

    public function category(): string
    {
        return "Code Quality";
    }

    public function priority(): int
    {
        return 45;
    }
}
