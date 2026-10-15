<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

use App\Core\App;
use App\Services\RepositoryHealth\Engine\RepositoryHealthEngine;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;

/**
 * Projects structural content checks into a learner-facing evidence report.
 * Editorial and external-source judgments stay explicit; the check never
 * claims semantic correctness from a valid JSON or URL shape.
 */
final class EducationalIntegrityCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $health = RepositoryHealthEngine::lastReport() ?? RepositoryHealthEngine::analyze();
        $classified = [
            'HARD_DEFECT' => [],
            'REVIEW_REQUIRED' => [],
            'JUSTIFIED_EXCEPTION' => [],
            'INSUFFICIENT_EVIDENCE' => [],
        ];

        foreach ($health->issues as $issue) {
            $row = $issue->toArray();
            $code = strtoupper((string) ($row['code'] ?? 'UNKNOWN'));
            $status = strtoupper((string) ($row['status'] ?? 'REVIEW'));
            if (($row['justified_exclusion'] ?? false) === true) {
                $class = 'JUSTIFIED_EXCEPTION';
            } elseif ($status === 'FAIL') {
                $class = 'HARD_DEFECT';
            } elseif (preg_match('/\A(?:ACTIVE_UNDER|ACTIVE_ZERO|UNKNOWN_COVERAGE)/', $code)) {
                $class = 'INSUFFICIENT_EVIDENCE';
            } else {
                $class = 'REVIEW_REQUIRED';
            }
            $classified[$class][] = self::finding($class, $code, $row);
        }
        foreach ($health->exclusions as $exclusion) {
            if (!is_array($exclusion)) {
                continue;
            }
            $classified['JUSTIFIED_EXCEPTION'][] = [
                'class' => 'JUSTIFIED_EXCEPTION',
                'code' => 'REGISTERED_FLASHCARD_EXCLUSION',
                'board' => self::text($exclusion['board_id'] ?? 'UNKNOWN'),
                'entity_type' => 'question',
                'entity_id' => self::text($exclusion['entity_id'] ?? ''),
                'note' => self::text($exclusion['human_readable_reason'] ?? 'Validated explicit flashcard exclusion.'),
            ];
        }

        $questions = App::storage()->all('questions');
        $materials = self::index(App::storage()->all('study-materials'));
        $sources = self::index(App::storage()->all('sources'));
        $links = App::storage()->all('study-question-links');
        $linkQuestionIds = [];
        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }
            $questionId = self::text($link['question_id'] ?? '');
            $materialId = self::text($link['material_id'] ?? '');
            $material = $materials[$materialId] ?? null;
            if ($questionId === '' || !is_array($material) || ($material['status'] ?? 'published') !== 'published') {
                continue;
            }
            $sourceIds = is_array($link['source_ids'] ?? null) && $link['source_ids'] !== []
                ? $link['source_ids']
                : (array) ($material['source_ids'] ?? []);
            foreach ($sourceIds as $sourceId) {
                $source = $sources[(string) $sourceId] ?? null;
                if (is_array($source) && self::externalSourceUrl($source['url'] ?? null)) {
                    $linkQuestionIds[$questionId] = true;
                    break;
                }
            }
        }

        $questionIds = [];
        foreach ($questions as $question) {
            if (!is_array($question)) {
                continue;
            }
            $questionId = self::text($question['id'] ?? '');
            if ($questionId === '') {
                continue;
            }
            $questionIds[$questionId] = true;
            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            if (self::text($taxonomy['topic_id'] ?? '') === '' || isset($linkQuestionIds[$questionId])) {
                continue;
            }
            $classified['HARD_DEFECT'][] = [
                'class' => 'HARD_DEFECT',
                'code' => 'MISSING_SOURCE_SUPPORTED_STUDY_PATH',
                'board' => self::text($taxonomy['board_id'] ?? $question['board'] ?? 'UNKNOWN'),
                'entity_type' => 'question',
                'entity_id' => $questionId,
                'note' => 'No linked published material has a valid external source URL.',
            ];
        }

        foreach ($sources as $sourceId => $source) {
            $url = $source['url'] ?? null;
            $status = strtolower(self::text($source['status'] ?? ''));
            $notes = strtolower(self::text($source['notes'] ?? ''));
            if (($url === null || trim((string) $url) === '')
                && $status === 'internal_editorial'
                && str_contains($notes, 'not an official')) {
                $classified['JUSTIFIED_EXCEPTION'][] = [
                    'class' => 'JUSTIFIED_EXCEPTION',
                    'code' => 'INTERNAL_EDITORIAL_SOURCE_WITHOUT_EXTERNAL_URL',
                    'board' => 'UNKNOWN',
                    'entity_type' => 'source',
                    'entity_id' => (string) $sourceId,
                    'note' => 'The source record explicitly identifies an internal planning aid, not official exam scope.',
                ];
                continue;
            }
            if (!self::externalSourceUrl($url)) {
                $classified['HARD_DEFECT'][] = [
                    'class' => 'HARD_DEFECT',
                    'code' => 'INVALID_SOURCE_URL',
                    'board' => 'UNKNOWN',
                    'entity_type' => 'source',
                    'entity_id' => (string) $sourceId,
                    'note' => 'Source URL is missing or is not a valid HTTP(S) URL.',
                ];
            } elseif (strtolower((string) parse_url((string) $url, PHP_URL_SCHEME)) === 'http') {
                $classified['REVIEW_REQUIRED'][] = [
                    'class' => 'REVIEW_REQUIRED',
                    'code' => 'SOURCE_URL_NOT_HTTPS',
                    'board' => 'UNKNOWN',
                    'entity_type' => 'source',
                    'entity_id' => (string) $sourceId,
                    'note' => 'URL syntax is valid, but this check does not test remote availability or redirects.',
                ];
            }
        }

        $details = [];
        foreach ($classified as $class => $findings) {
            $details[] = $class . ' count=' . count($findings);
            foreach ($findings as $finding) {
                $details[] = sprintf(
                    '%s code=%s board=%s %s_id=%s; %s',
                    $class,
                    $finding['code'],
                    $finding['board'],
                    $finding['entity_type'],
                    $finding['entity_id'],
                    $finding['note']
                );
            }
        }
        $details[] = sprintf(
            'SOURCE_STUDY_COVERAGE questions=%d source_supported=%d missing=%d; remote URL reachability is not tested.',
            count($questionIds),
            count(array_intersect_key($linkQuestionIds, $questionIds)),
            count(array_diff_key($questionIds, $linkQuestionIds))
        );
        $details[] = 'SEMANTIC_LIMITATION: Valid links and source records do not establish question correctness or editorial fit; review classifications remain open until checked.';

        $hardCount = count($classified['HARD_DEFECT']);
        $reviewCount = count($classified['REVIEW_REQUIRED']);
        $insufficientCount = count($classified['INSUFFICIENT_EVIDENCE']);
        $status = $hardCount > 0 ? 'FAIL' : (($reviewCount + $insufficientCount) > 0 ? 'WARNING' : 'PASS');
        return new CheckResult(
            title: 'Educational Evidence Integrity',
            status: $status,
            summary: sprintf(
                'Education evidence: %d hard defects, %d review-required items, %d justified exceptions, and %d insufficient-evidence signals; records include exact IDs and boards.',
                $hardCount,
                $reviewCount,
                count($classified['JUSTIFIED_EXCEPTION']),
                $insufficientCount
            ),
            details: $details,
            recommendations: $hardCount > 0
                ? ['Repair the exact source, question, link, or board records identified in the detail list.']
                : (($reviewCount + $insufficientCount) > 0
                    ? ['Keep scope unknown where evidence is insufficient; resolve review items only with primary evidence.']
                    : []),
            score: $hardCount > 0 ? 0 : ($reviewCount + $insufficientCount > 0 ? 80 : 100),
        );
    }

    public function category(): string
    {
        return 'Content';
    }

    public function priority(): int
    {
        return 26;
    }

    private static function index(array $records): array
    {
        $indexed = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }
            $id = self::text($record['id'] ?? '');
            if ($id !== '' && !isset($indexed[$id])) {
                $indexed[$id] = $record;
            }
        }
        return $indexed;
    }

    private static function externalSourceUrl(mixed $url): bool
    {
        if (!is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['https', 'http'], true)
            && self::text(parse_url($url, PHP_URL_HOST)) !== '';
    }

    private static function finding(string $class, string $code, array $row): array
    {
        $evidence = is_array($row['evidence'] ?? null) ? $row['evidence'] : [];
        return [
            'class' => $class,
            'code' => $code,
            'board' => self::text($row['board_id'] ?? $evidence['board_id'] ?? 'UNKNOWN'),
            'entity_type' => self::text($row['entity_type'] ?? 'repository_record'),
            'entity_id' => self::text($row['entity_id'] ?? ''),
            'note' => self::text($row['message'] ?? ''),
        ];
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
