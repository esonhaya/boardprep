<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\Shared\Checks;

use Tools\Doctor\Context\DoctorContext;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;

final class NamingConventionCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $snapshot = DoctorContext::snapshot();
        $details = [];
        $errors = 0;

        foreach ($snapshot->files as $fileInfo) {
            $file = $fileInfo['path'];

            if (
                str_contains($file, '/Views/')
                || str_contains($file, '/views/')
                || str_starts_with($file, './tools/Dev/')
                || str_starts_with($file, './tools/Tests/')
            ) {
                continue;
            }

            $classForFile = null;

            foreach (
                $snapshot->classMap as $class => $mappedFile
            ) {
                if ($mappedFile === $file) {
                    $classForFile = $class;
                    break;
                }
            }

            /*
             * Only files known to declare a class are class files.
             * This prevents controllers, scripts, configuration,
             * and other PHP resources from being judged as classes.
             */
            if ($classForFile === null) {
                continue;
            }

            if (
                !preg_match(
                    '/^[A-Z][A-Za-z0-9]*\.php$/',
                    basename($file)
                )
            ) {
                $errors++;

                $details[] =
                    "{$file}: class {$classForFile} "
                    . "does not use a PascalCase file name.";
            }
        }

        foreach ($snapshot->methods as $method) {
            $file = $method['file'];

            if (
                str_contains($file, '/Views/')
                || str_contains($file, '/views/')
                || str_starts_with($file, './tools/Dev/')
                || str_starts_with($file, './tools/Tests/')
            ) {
                continue;
            }

            $name = $method['name'];

            if (
                str_starts_with($name, '__')
            ) {
                continue;
            }

            if (
                !preg_match(
                    '/^[a-z][A-Za-z0-9]*$/',
                    $name
                )
            ) {
                $errors++;

                $details[] =
                    "{$file}:{$method['line']} "
                    . "method {$name}() is not camelCase.";
            }
        }

        return new CheckResult(
            title: 'Naming Conventions',
            status: $errors > 0 ? 'FAIL' : 'PASS',
            summary:
                $errors > 0
                    ? "{$errors} naming violation(s)."
                    : 'PHP naming conventions are consistent.',
            details: array_slice(
                $details,
                0,
                50
            ),
            score: max(
                0,
                100 - ($errors * 5)
            )
        );
    }

    public function category(): string
    {
        return 'Architecture';
    }

    public function priority(): int
    {
        return 40;
    }
}
