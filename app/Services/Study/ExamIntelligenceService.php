<?php

declare(strict_types=1);

namespace App\Services\Study;

use App\Core\App;

final class ExamIntelligenceService
{
    private const TYPES = ['OFFICIAL_SCOPE', 'OFFICIAL_WEIGHT', 'RECENT_EXAM_REPORT', 'REVIEW_CENTER_SIGNAL', 'COMMUNITY_REPORT', 'BOARDPREP_EDITORIAL'];

    public static function all(?string $exam = null): array
    {
        $signals = array_values(array_filter(App::storage()->all('exam-intelligence'), 'is_array'));
        if ($exam === null || trim($exam) === '') {
            return $signals;
        }
        return array_values(array_filter($signals, static fn(array $signal): bool => strtolower((string) ($signal['exam'] ?? '')) === self::normalize($exam)));
    }

    public static function priority(array $material, string $exam): array
    {
        $exam = self::normalize($exam);
        $focus = is_array($material['exam_focus'][$exam] ?? null) ? $material['exam_focus'][$exam] : [];
        $level = strtoupper((string) ($focus['priority'] ?? 'LOW'));
        $reasons = [];
        if (($focus['rationale'] ?? '') !== '') {
            $reasons[] = (string) $focus['rationale'];
        }
        foreach (self::all($exam) as $signal) {
            if (($signal['material_id'] ?? '') !== ($material['id'] ?? '')) {
                continue;
            }
            $signalLevel = strtoupper((string) ($signal['strength'] ?? 'LOW'));
            if ($signalLevel === 'HIGH' && ($signal['confidence'] ?? '') === 'high' && $level === 'LOW') {
                $level = 'MEDIUM';
            }
            $reasons[] = (string) ($signal['notes'] ?? 'Documented scope relevance.');
        }
        return ['level' => in_array($level, ['HIGH', 'MEDIUM', 'LOW'], true) ? $level : 'LOW', 'reasons' => array_values(array_unique($reasons))];
    }

    public static function validate(): array
    {
        $sourceIds = array_fill_keys(array_map(static fn(array $source): string => (string) ($source['id'] ?? ''), SourceRegistryService::all()), true);
        $invalid = [];
        foreach (self::all() as $signal) {
            if (!in_array((string) ($signal['signal_type'] ?? ''), self::TYPES, true)
                || !isset($sourceIds[(string) ($signal['source_id'] ?? '')])
                || trim((string) ($signal['exam'] ?? '')) === ''
                || !in_array((string) ($signal['confidence'] ?? ''), ['high', 'medium', 'low'], true)) {
                $invalid[] = (string) ($signal['id'] ?? '<missing>');
            }
        }
        return ['valid' => $invalid === [], 'invalid' => array_values(array_unique($invalid)), 'total' => count(self::all())];
    }

    /** Resolve a factual claim using explicit source authority, never signal volume. */
    public static function resolveFact(array $signals): ?array
    {
        $signals = array_values(array_filter($signals, 'is_array'));
        usort($signals, static function (array $left, array $right): int {
            $rank = static fn(array $signal): int => match ((string) ($signal['authority'] ?? $signal['signal_type'] ?? '')) {
                'CURRENT_OFFICIAL', 'OFFICIAL_SCOPE', 'OFFICIAL_WEIGHT' => 6,
                'OLDER_OFFICIAL' => 5,
                'CORROBORATED_SECONDARY', 'REVIEW_CENTER_SIGNAL' => 4,
                'SINGLE_SECONDARY' => 3,
                'COMMUNITY_REPORT' => 2,
                default => 1,
            };
            return $rank($right) <=> $rank($left);
        });
        return $signals[0] ?? null;
    }

    public static function hasFactConflict(array $signals): bool
    {
        $claims = [];
        foreach ($signals as $signal) {
            if (!is_array($signal) || !array_key_exists('claim', $signal)) {
                continue;
            }
            $claims[(string) ($signal['authority'] ?? $signal['signal_type'] ?? 'EDITORIAL')][] = (string) $signal['claim'];
        }
        $official = array_merge($claims['CURRENT_OFFICIAL'] ?? [], $claims['OFFICIAL_SCOPE'] ?? [], $claims['OFFICIAL_WEIGHT'] ?? []);
        $secondary = array_merge($claims['CORROBORATED_SECONDARY'] ?? [], $claims['REVIEW_CENTER_SIGNAL'] ?? [], $claims['SINGLE_SECONDARY'] ?? []);
        return $official !== [] && $secondary !== [] && array_diff($official, $secondary) !== [];
    }

    private static function normalize(string $exam): string
    {
        $exam = strtolower(trim($exam));
        return ['cse' => 'civil-service', 'cle' => 'criminologist', 'nle' => 'nursing', 'ple' => 'psychometrician'][$exam] ?? $exam;
    }
}
