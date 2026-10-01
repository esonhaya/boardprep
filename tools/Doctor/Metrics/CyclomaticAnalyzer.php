<?php

declare(strict_types=1);

namespace Tools\Doctor\Metrics;

use Tools\Doctor\Snapshot\ProjectSnapshot;

/**
 * Host-side performance adapter for HayaDoctor's generic metric contract.
 * The original analyzer reread one source file for every method; this keeps
 * the same metric semantics while indexing each file once per run.
 */
final class CyclomaticAnalyzer
{
    private const PATTERN = '/\b(if|elseif|for|foreach|while|case|catch)\b|&&|\|\||\?/';

    public function analyze(ProjectSnapshot $snapshot): void
    {
        $linesByFile = [];
        foreach ($snapshot->methods as $method) {
            $file = (string) ($method['file'] ?? '');
            if (!array_key_exists($file, $linesByFile)) {
                $linesByFile[$file] = @file($file, FILE_IGNORE_NEW_LINES);
            }
            $lines = $linesByFile[$file];
            if ($lines === false || !is_array($lines)) {
                continue;
            }
            $body = implode(PHP_EOL, array_slice($lines, max(0, (int) ($method['line'] ?? 1) - 1), (int) ($method['lines'] ?? 0)));
            preg_match_all(self::PATTERN, $body, $matches);
            $snapshot->addMetric('cyclomatic', [
                'file' => $file,
                'method' => (string) ($method['name'] ?? ''),
                'score' => 1 + count($matches[0]),
            ]);
        }
    }
}
