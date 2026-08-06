<?php

declare(strict_types=1);

namespace Tools\Doctor\Graph;

use Tools\Doctor\Snapshot\ProjectSnapshot;

final class KnowledgeGraphBuilder
{
    public function build(
        ProjectSnapshot $snapshot
    ): array {

        $graph = [];

        foreach ($snapshot->dependencies as $source => $dependencies) {

            foreach (array_unique($dependencies) as $class) {

                $target =
                    $snapshot->classMap[$class]
                    ?? null;

                if ($target === null) {
                    continue;
                }

                $graph[$source]["depends_on"][$target] = true;
                $graph[$target]["used_by"][$source] = true;

            }

        }

        foreach ($graph as &$node) {

            $node["depends_on"] =
                array_keys($node["depends_on"] ?? []);

            $node["used_by"] =
                array_keys($node["used_by"] ?? []);

        }

        return $graph;

    }
}
