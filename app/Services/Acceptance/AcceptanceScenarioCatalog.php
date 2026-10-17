<?php

declare(strict_types=1);

namespace App\Services\Acceptance;

use RuntimeException;

final class AcceptanceScenarioCatalog
{
    private const EXPECTED_CASES = 12;

    /** @return list<array<string,mixed>> */
    public static function all(?string $root = null): array
    {
        $root ??= dirname(__DIR__, 3);
        $markdownPath = $root . '/docs/HUMAN_TESTER_READINESS.md';
        $guidancePath = $root . '/config/acceptance-scenario-guidance.php';
        if (!is_file($markdownPath) || !is_file($guidancePath)) {
            throw new RuntimeException('The canonical human acceptance scenarios are unavailable.');
        }

        $guidance = require $guidancePath;
        $markdown = (string) file_get_contents($markdownPath);
        $cases = [];
        foreach (preg_split('/\R/', $markdown) ?: [] as $line) {
            $line = trim($line);
            if (!str_starts_with($line, '| MVP-')) {
                continue;
            }
            $cells = array_map(
                self::plainText(...),
                explode('|', trim($line, '|'))
            );
            if (count($cells) !== 8 || preg_match('/^MVP-(\d{2})$/', $cells[0], $match) !== 1) {
                continue;
            }
            $caseId = $cells[0];
            $caseGuidance = $guidance[$caseId] ?? null;
            if (!is_array($caseGuidance)
                || trim((string) ($caseGuidance['title'] ?? '')) === ''
                || trim((string) ($caseGuidance['why'] ?? '')) === ''
                || !is_array($caseGuidance['routes'] ?? null)
                || $caseGuidance['routes'] === []) {
                throw new RuntimeException('Missing guided route details for ' . $caseId . '.');
            }
            foreach ($caseGuidance['routes'] as $route) {
                $path = (string) ($route['path'] ?? '');
                if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//') || str_contains($path, '..')) {
                    throw new RuntimeException('Invalid in-app route guidance for ' . $caseId . '.');
                }
            }

            $cases[] = [
                'case_id' => $caseId,
                'title' => (string) $caseGuidance['title'],
                'why' => (string) $caseGuidance['why'],
                'start' => $cells[1],
                'actions' => $cells[2],
                'automated_coverage' => $cells[3],
                'success' => $cells[5],
                'watch_for' => $cells[6],
                'routes' => array_values($caseGuidance['routes']),
            ];
        }

        $expected = array_map(static fn(int $number): string => sprintf('MVP-%02d', $number), range(1, self::EXPECTED_CASES));
        $actual = array_column($cases, 'case_id');
        if ($actual !== $expected || count($guidance) !== self::EXPECTED_CASES) {
            throw new RuntimeException('The Acceptance Center must load exactly the canonical MVP-01 through MVP-12 cases.');
        }

        return $cases;
    }

    public static function find(string $caseId, ?string $root = null): ?array
    {
        foreach (self::all($root) as $case) {
            if (($case['case_id'] ?? '') === $caseId) {
                return $case;
            }
        }
        return null;
    }

    private static function plainText(string $value): string
    {
        $value = preg_replace('/\*\*(.*?)\*\*/s', '$1', $value) ?? $value;
        $value = preg_replace('/`([^`]+)`/', '$1', $value) ?? $value;
        return trim(strip_tags($value));
    }
}
