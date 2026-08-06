<?php

declare(strict_types=1);

namespace Tools\Doctor\Graph;

final class KnowledgeGraph
{
    private array $nodes = [];
    private array $edges = [];

    public function addNode(string $id, array $meta = []): void
    {
        $this->nodes[$id] = $meta;
    }

    public function addEdge(string $from, string $to): void
    {
        $this->edges[$from] ??= [];

        if (!in_array($to, $this->edges[$from], true)) {
            $this->edges[$from][] = $to;
        }
    }

    public function neighbours(string $node): array
    {
        return $this->edges[$node] ?? [];
    }

    public function nodes(): array
    {
        return $this->nodes;
    }

    public function edges(): array
    {
        return $this->edges;
    }

    public function incoming(string $node): int
    {
        $count = 0;

        foreach ($this->edges as $targets) {
            if (in_array($node, $targets, true)) {
                $count++;
            }
        }

        return $count;
    }

    public function outgoing(string $node): int
    {
        return count($this->edges[$node] ?? []);
    }
}
