<?php

declare(strict_types=1);

namespace Tools\Doctor\Registry;

use ReflectionClass;
use Tools\Doctor\Contracts\CheckInterface;

final class CheckRegistry
{
    /**
     * @return CheckInterface[]
     */
    public function all(): array
    {
        $checks = [];

        $patterns = [
            "./tools/Doctor/Project/Shared/Checks/*.php",
            "./tools/Doctor/Project/BoardPrep/Checks/*.php",
            "./tools/Doctor/Self/Checks/*.php",
        ];

        foreach ($patterns as $pattern) {
            foreach (glob($pattern) as $file) {

                $relative = str_replace(
                    "./tools/Doctor/",
                    "",
                    dirname($file)
                );

                $namespace =
                    str_replace(
                        "/",
                        "\\",
                        $relative
                    );

                $class =
                    "Tools\\Doctor\\"
                    . $namespace
                    . "\\"
                    . basename(
                        $file,
                        ".php"
                    );

                if (!class_exists($class)) {
                    require_once $file;
                }

                if (!class_exists($class)) {
                    continue;
                }

                $reflection =
                    new ReflectionClass(
                        $class
                    );

                if (
                    $reflection->isAbstract()
                    || !$reflection->implementsInterface(
                        CheckInterface::class
                    )
                ) {
                    continue;
                }

                $checks[] =
                    new $class();
            }
        }

        usort(
            $checks,
            fn(
                CheckInterface $a,
                CheckInterface $b
            ) =>
                $a->priority()
                <=>
                $b->priority()
        );

        return $checks;
    }
}
