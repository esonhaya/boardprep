<?php

declare(strict_types=1);

namespace Tools\Doctor\Checks;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;

final class ControllerComplexityCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $worstFile = "";
        $worstMethodCount = 0;
        $worstPublicMethods = 0;
        $worstPrivateMethods = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                "app/Controllers",
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
                '/function\s+\w+\s*\(/',
                $contents,
                $allMethods
            );

            preg_match_all(
                '/public\s+(?:static\s+)?function\s+\w+\s*\(/',
                $contents,
                $publicMethods
            );

            preg_match_all(
                '/private\s+(?:static\s+)?function\s+\w+\s*\(/',
                $contents,
                $privateMethods
            );

            $methodCount = count($allMethods[0]);

            if ($methodCount > $worstMethodCount) {

                $worstMethodCount = $methodCount;
                $worstPublicMethods = count($publicMethods[0]);
                $worstPrivateMethods = count($privateMethods[0]);

                $worstFile = str_replace(
                    getcwd() . DIRECTORY_SEPARATOR,
                    "",
                    $file->getPathname()
                );
            }
        }

        $status =
            $worstMethodCount > 12
                ? "WARNING"
                : "PASS";

        return new CheckResult(
            title: "Controller Complexity",
            status: $status,
            summary: $worstFile,
            details: [
                "Methods         : {$worstMethodCount}",
                "Public Methods  : {$worstPublicMethods}",
                "Private Methods : {$worstPrivateMethods}",
            ],
            recommendations: $status === "WARNING"
                ? [
                    "Review whether the controller should be split by responsibility."
                ]
                : []
        );
    }

    public function category(): string
    {
        return "Architecture";
    }

    public function priority(): int
    {
        return 21;
    }
}
