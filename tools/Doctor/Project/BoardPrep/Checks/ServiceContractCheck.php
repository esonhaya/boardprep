<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

final class ServiceContractCheck
{
    /**
     * @param class-string $class
     * @param array<int, string> $methods
     * @return array<string, bool>
     */
    public static function run(string $class, array $methods): array
    {
        $checks = [
            "service_class_exists" => class_exists($class),
        ];

        foreach ($methods as $method) {
            $checks["service_method_{$method}"] =
                class_exists($class) && method_exists($class, $method);
        }

        return $checks;
    }
}
