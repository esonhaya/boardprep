<?php

declare(strict_types=1);

namespace Tools\Doctor\Snapshot;

final class ProjectSnapshot
{
    public function __construct(
        public array $files = [],
        public array $classes = [],
        public array $classMap = [],
        public array $interfaces = [],
        public array $traits = [],
        public array $controllers = [],
        public array $services = [],
        public array $repositories = [],
        public array $domains = [],
        public array $methods = [],
        public array $namespaces = [],
        public array $imports = [],
        public array $dependencies = [],
        public array $metrics = [],
    ) {
    }

    public function phpFileCount(): int
    {
        return count($this->files);
    }

    public function largestFile(
        string $contains = ""
    ): ?array {

        $largest = null;

        foreach ($this->files as $file) {

            if (

                $contains !== ""
                && !str_contains(
                    $file["path"],
                    $contains
                )

            ) {
                continue;
            }

            if (

                $largest === null
                || $file["lines"] > $largest["lines"]

            ) {

                $largest = $file;

            }

        }

        return $largest;

    }

    public function metric(
        string $name
    ): array {

        return $this->metrics[$name] ?? [];

    }

    public function setMetric(
        string $name,
        array $value
    ): void {

        $this->metrics[$name] = $value;

    }

    public function addMetric(
        string $name,
        array $value
    ): void {

        $this->metrics[$name] ??= [];

        $this->metrics[$name][] = $value;

    }
}
