<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\Shared\Checks;

use Tools\Doctor\Context\DoctorContext;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;
use Tools\Doctor\Scanners\Support\StaticContractScanner;

final class CallSignatureCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $snapshot = DoctorContext::snapshot();
        $details = [];
        $errors = 0;

        $classMap = $snapshot->classMap;

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

            foreach ($classMap as $mappedClass => $mappedFile) {
                if ($mappedFile === $file) {
                    $declaredClass = $mappedClass;
                    break;
                }
            }

            foreach (
                StaticContractScanner::staticCalls(
                    $file,
                    $source
                ) as $call
            ) {
                /*
                 * parent:: calls are intentionally skipped here.
                 *
                 * The static contract scanner validates their existence
                 * through inheritance-aware source resolution. Signature
                 * validation can be added separately once parent target
                 * resolution is represented explicitly by the scanner.
                 */
                if ($call['class'] === 'parent') {
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
                    continue;
                }

                $signature =
                    StaticContractScanner::methodSignatureInProject(
                        $target,
                        $call['method'],
                        $classMap
                    );

                if ($signature === null) {
                    continue;
                }

                $supplied =
                    $call['arguments'];

                $required =
                    $signature['required'];

                $maximum =
                    $signature['maximum'];

                if (
                    $supplied < $required
                    || $supplied > $maximum
                ) {
                    $errors++;

                    $details[] =
                        "{$file}:{$call['line']} "
                        . "{$call['class']}::{$call['method']}() "
                        . "uses {$supplied} argument(s); "
                        . "expected {$required}-{$maximum}.";
                }
            }
        }

        return new CheckResult(
            title: 'Static Call Signatures',
            status: $errors > 0 ? 'FAIL' : 'PASS',
            summary:
                $errors > 0
                    ? "{$errors} static call signature violation(s)."
                    : 'Static call argument counts are valid.',
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
        return 20;
    }
}
