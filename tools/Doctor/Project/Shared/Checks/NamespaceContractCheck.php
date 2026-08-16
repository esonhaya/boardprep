<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\Shared\Checks;

use Tools\Doctor\Context\DoctorContext;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;
use Tools\Doctor\Project\Shared\Support\StaticContractScanner;

final class NamespaceContractCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $snapshot = DoctorContext::snapshot();
        $details = [];
        $errors = 0;

        foreach ($snapshot->files as $fileInfo) {
            $file = $fileInfo['path'];
            $source = @file_get_contents($file);

            if ($source === false) {
                continue;
            }

            $namespace =
                StaticContractScanner::namespace($source);

            $imports =
                StaticContractScanner::imports($source);

            $declaredClass = null;

            foreach (
                $snapshot->classMap as $mappedClass => $mappedFile
            ) {
                if ($mappedFile === $file) {
                    $declaredClass = $mappedClass;
                    break;
                }
            }

            $classMap =
                $snapshot->classMap;

            foreach (
                StaticContractScanner::staticCalls(
                    $file,
                    $source
                ) as $call
            ) {
                if (
                    in_array(
                        $call['class'],
                        ['self', 'static', 'parent'],
                        true
                    )
                ) {
                    continue;
                }

                $target =
                    StaticContractScanner::resolveClass(
                        $call['class'],
                        $namespace,
                        $imports,
                        $declaredClass,
                        $classMap
                    );

                if (
                    in_array(
                        $call['class'],
                        ['self', 'static', 'parent'],
                        true
                    )
                ) {
                    continue;
                }

                if ($target === null) {
                    $errors++;

                    $details[] =
                        "{$file}:{$call['line']} "
                        . "Class reference {$call['class']} "
                        . "has no resolvable namespace/import.";
                }
            }
        }

        return new CheckResult(
            title: 'Namespace Contracts',
            status: $errors > 0 ? 'FAIL' : 'PASS',
            summary:
                $errors > 0
                    ? "{$errors} unresolved namespace reference(s)."
                    : 'Namespace and import references resolve.',
            details: array_slice($details, 0, 50),
            score: max(0, 100 - ($errors * 10))
        );
    }

    public function category(): string
    {
        return 'Contracts';
    }

    public function priority(): int
    {
        return 30;
    }
}
