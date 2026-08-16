<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\Shared\Checks;

use Tools\Doctor\Context\DoctorContext;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;
use Tools\Doctor\Project\Shared\Support\StaticContractScanner;

final class CallContractCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $snapshot = DoctorContext::snapshot();
        $details = [];
        $errors = 0;

        foreach ($snapshot->files as $fileInfo) {
            $file = $fileInfo['path'];

            if (str_starts_with($file, './tools/Dev/')) {
                continue;
            }

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
                        ['self', 'static'],
                        true
                    )
                    && $declaredClass === null
                ) {
                    continue;
                }

                if (
                    $call['class'] === 'parent'
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

                if ($target === null) {
                    $errors++;
                    $details[] =
                        "{$file}:{$call['line']} "
                        . "{$call['class']}::{$call['method']}() "
                        . "cannot be resolved.";
                    continue;
                }

                if (
                    !StaticContractScanner::methodExistsInProject(
                        $target,
                        $call['method'],
                        $classMap
                    )
                ) {
                    $errors++;
                    $details[] =
                        "{$file}:{$call['line']} "
                        . "{$call['class']}::{$call['method']}() "
                        . "does not exist on {$target} "
                        . "or its inherited source contract.";
                }
            }
        }

        return new CheckResult(
            title: 'Static Call Contract',
            status: $errors > 0 ? 'FAIL' : 'PASS',
            summary:
                $errors > 0
                    ? "{$errors} unresolved or missing static call(s)."
                    : 'All statically resolvable static calls are valid.',
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
        return 10;
    }
}
