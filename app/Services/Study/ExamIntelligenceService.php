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

    public static function priority(array $material, string $exam, ?array $signals = null): array
    {
        $exam = self::normalize($exam);
        $focus = is_array($material['exam_focus'][$exam] ?? null) ? $material['exam_focus'][$exam] : [];
        $level = strtoupper((string) ($focus['priority'] ?? 'LOW'));
        $reasons = [];
        if (($focus['rationale'] ?? '') !== '') {
            $reasons[] = (string) $focus['rationale'];
        }
        foreach ($signals ?? self::all($exam) as $signal) {
            if (!is_array($signal) || strtolower((string) ($signal['exam'] ?? '')) !== $exam) {
                continue;
            }
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
        $sourceIds = [];
        foreach (SourceRegistryService::all() as $source) {
            $sourceId = trim((string) ($source['id'] ?? ''));
            if ($sourceId !== '') {
                $sourceIds[$sourceId] = true;
            }
        }
        $invalid = [];
        $issues = [];
        foreach (self::all() as $signal) {
            $id = trim((string) ($signal['id'] ?? '')) ?: '<missing>';
            $type = (string) ($signal['signal_type'] ?? '');
            $sourceId = trim((string) ($signal['source_id'] ?? ''));
            $exam = trim((string) ($signal['exam'] ?? ''));
            $confidence = (string) ($signal['confidence'] ?? '');
            if (!in_array($type, self::TYPES, true)) {
                $issues[] = self::issue($id, 'Signal type is not recognized: ' . ($type !== '' ? $type : '<missing>') . '.', 'Use a supported exam-intelligence signal type.');
            }
            if ($sourceId === '' || !isset($sourceIds[$sourceId])) {
                $issues[] = self::issue($id, 'Signal references an unknown source: ' . ($sourceId !== '' ? $sourceId : '<missing>') . '.', 'Register the supporting source or repair the source ID.');
            }
            if ($exam === '') {
                $issues[] = self::issue($id, 'Signal has no examination identifier.', 'Set the canonical examination or board ID.');
            }
            if (!in_array($confidence, ['high', 'medium', 'low'], true)) {
                $issues[] = self::issue($id, 'Signal confidence is invalid: ' . ($confidence !== '' ? $confidence : '<missing>') . '.', 'Set confidence to high, medium, or low with appropriate evidence.');
            }
        }
        foreach ($issues as $issue) {
            $invalid[] = $issue['record_id'];
        }
        return [
            'valid' => $issues === [],
            'invalid' => array_values(array_unique($invalid)),
            'issues' => $issues,
            'total' => count(self::all()),
        ];
    }

    private static function issue(string $id, string $reason, string $recommendedAction): array
    {
        return [
            'record_type' => 'exam_intelligence',
            'record_id' => $id,
            'severity' => 'ERROR',
            'reason' => $reason,
            'recommended_action' => $recommendedAction,
        ];
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
