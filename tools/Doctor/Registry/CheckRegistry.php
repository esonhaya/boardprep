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

        foreach (glob('./tools/Doctor/Project/BoardPrep/Checks/*.php') as $file) {

            $class =
                'Tools\\Doctor\\Project\\BoardPrep\\Checks\\'
                . basename($file, '.php');

            if (!class_exists($class)) {
                require_once $file;
            }

            if (!class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if (
                $reflection->isAbstract()
                || !$reflection->implementsInterface(
                    CheckInterface::class
                )
            ) {
                continue;
            }

            $checks[] = new $class();

        }

        usort(
            $checks,
            fn(
                CheckInterface $a,
                CheckInterface $b
            ) =>
                $a->priority() <=> $b->priority()
        );

        return $checks;
    }
}
