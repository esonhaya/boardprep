<?php

declare(strict_types=1);

namespace Tools\Doctor\Registry;

use ReflectionClass;

final class AnalyzerRegistry
{
    public function all(): array
    {
        $analyzers = [];

        foreach (glob('./tools/Doctor/Analyzers/*.php') as $file) {

            $class =
                'Tools\\Doctor\\Analyzers\\'
                . basename($file, '.php');

            if (!class_exists($class)) {
                require_once $file;
            }

            if (!class_exists($class)) {
                continue;
            }

            $reflection =
                new ReflectionClass($class);

            if (
                $reflection->isAbstract()
            ) {
                continue;
            }

            if (
                !$reflection->hasMethod(
                    'analyze'
                )
            ) {
                continue;
            }

            $analyzers[] =
                new $class();

        }

        usort(
            $analyzers,
            fn($a, $b) =>
                strcmp(
                    get_class($a),
                    get_class($b)
                )
        );

        return $analyzers;
    }
}
