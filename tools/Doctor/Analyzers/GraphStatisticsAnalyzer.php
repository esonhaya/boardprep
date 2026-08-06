<?php

declare(strict_types=1);

namespace Tools\Doctor\Analyzers;

use Tools\Doctor\Snapshot\ProjectSnapshot;

final class GraphStatisticsAnalyzer
{
    public function analyze(
        ProjectSnapshot $snapshot
    ): void {

        $graph = $snapshot->metric('knowledge-graph');

        $statistics = [];
        $hotspots = [];
        $changeImpact = [];

        foreach ($graph as $file => $node) {

            $fanIn = count($node['used_by'] ?? []);
            $fanOut = count($node['depends_on'] ?? []);
            $total = $fanIn + $fanOut;

            $statistics[$file] = [
                'fan_in' => $fanIn,
                'fan_out' => $fanOut,
                'total' => $total,
            ];

            $hotspots[] = [
                'file' => $file,
                'score' => $total,
                'fanIn' => $fanIn,
                'fanOut' => $fanOut,
            ];

            $changeImpact[] = [
                'target' => $file,
                'count' => $fanIn,
                'affected' => $node['used_by'] ?? [],
            ];
        }

        uasort($statistics, fn($a, $b) => $b['total'] <=> $a['total']);
        usort($hotspots, fn($a, $b) => $b['score'] <=> $a['score']);
        usort($changeImpact, fn($a, $b) => $b['count'] <=> $a['count']);

        $snapshot->setMetric('graph-statistics', $statistics);
        $snapshot->setMetric('hotspots', $hotspots);
        $snapshot->setMetric('change-impact', $changeImpact);
    }
}
