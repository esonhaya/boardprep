<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;

final class TodoCommentCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $todos = [];

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

            $lines = @file(
                $file->getPathname(),
                FILE_IGNORE_NEW_LINES
            );

            if ($lines === false) {
                continue;
            }

            foreach ($lines as $number => $line) {

                if (
                    stripos($line, "TODO") === false
                    && stripos($line, "FIXME") === false
                ) {
                    continue;
                }

                $todos[] = sprintf(
                    "%s:%d",
                    str_replace(
                        getcwd() . DIRECTORY_SEPARATOR,
                        "",
                        $file->getPathname()
                    ),
                    $number + 1
                );

            }

        }

        return new CheckResult(

            title: "TODO Comments",

            status:
                empty($todos)
                    ? "PASS"
                    : "WARNING",

            summary:
                empty($todos)
                    ? "No TODO/FIXME comments found."
                    : count($todos) . " TODO/FIXME comments found.",

            details:
                array_slice(
                    $todos,
                    0,
                    20
                ),

            recommendations:
                empty($todos)
                    ? []
                    : [
                        "Resolve or remove outstanding TODO/FIXME comments."
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
