<?php

declare(strict_types=1);

namespace Tools\Doctor\Snapshot;

use Tools\Doctor\Graph\KnowledgeGraphBuilder;
use Tools\Doctor\Scanners\DependencyScanner;
use Tools\Doctor\Scanners\FileScanner;
use Tools\Doctor\Scanners\PhpSourceScanner;
use Tools\Doctor\Scanners\TokenScanner;

final class SourceSnapshotBuilder
{
    /**
     * @param callable(string):bool $include
     */
    public function build(
        callable $include
    ): ProjectSnapshot {
        $snapshot = new ProjectSnapshot();

        foreach (FileScanner::php() as $file) {

            $path = str_replace(
                getcwd() . DIRECTORY_SEPARATOR,
                "",
                $file
            );

            if (!$include($path)) {
                continue;
            }

            $contents =
                PhpSourceScanner::contents($file);

            if ($contents === "") {
                continue;
            }

            $snapshot->files[] = [
                "path" => $path,
                "lines" =>
                    PhpSourceScanner::lineCount(
                        $contents
                    ),
            ];

            $this->categorize(
                $snapshot,
                $path
            );

            $this->scanSource(
                $snapshot,
                $path,
                $contents
            );
        }

        $this->discoverDomains(
            $snapshot
        );

        $snapshot->setMetric(
            "knowledge-graph",
            (new KnowledgeGraphBuilder())
                ->build($snapshot)
        );

        return $snapshot;
    }

    private function categorize(
        ProjectSnapshot $snapshot,
        string $path
    ): void {
        if (str_contains(
            $path,
            "/Controllers/"
        )) {
            $snapshot->controllers[] =
                $path;
        }

        if (str_contains(
            $path,
            "/Services/"
        )) {
            $snapshot->services[] =
                $path;
        }

        if (str_contains(
            $path,
            "/Repositories/"
        )) {
            $snapshot->repositories[] =
                $path;
        }
    }

    private function discoverDomains(
        ProjectSnapshot $snapshot
    ): void {
        $root = "app/Domains";

        if (!is_dir($root)) {
            return;
        }

        foreach (
            scandir($root) ?: []
            as $directory
        ) {
            if (
                $directory === "."
                || $directory === ".."
            ) {
                continue;
            }

            if (
                is_dir(
                    $root . "/" . $directory
                )
            ) {
                $snapshot->domains[] =
                    $directory;
            }
        }

        sort($snapshot->domains);
    }

    private function scanSource(
        ProjectSnapshot $snapshot,
        string $path,
        string $contents
    ): void {
        $classes =
            PhpSourceScanner::classes(
                $contents
            );

        $snapshot->classes =
            array_merge(
                $snapshot->classes,
                $classes
            );

        foreach ($classes as $class) {
            $snapshot->classMap[$class] =
                $path;
        }

        $snapshot->interfaces =
            array_merge(
                $snapshot->interfaces,
                PhpSourceScanner::interfaces(
                    $contents
                )
            );

        $snapshot->traits =
            array_merge(
                $snapshot->traits,
                PhpSourceScanner::traits(
                    $contents
                )
            );

        $namespace =
            PhpSourceScanner::namespace(
                $contents
            );

        if ($namespace !== null) {
            $snapshot->namespaces[$path] =
                $namespace;
        }

        $snapshot->imports[$path] =
            PhpSourceScanner::imports(
                $contents
            );

        $snapshot->dependencies[$path] =
            DependencyScanner::classes(
                $contents
            );

        foreach (
            TokenScanner::methods($contents)
            as $method
        ) {
            $snapshot->methods[] = [
                "file" => $path,
                "name" => $method["name"],
                "visibility" =>
                    $method["visibility"],
                "line" => $method["line"],
                "endLine" =>
                    $method["endLine"],
                "lines" =>
                    $method["lines"],
            ];
        }
    }
}
