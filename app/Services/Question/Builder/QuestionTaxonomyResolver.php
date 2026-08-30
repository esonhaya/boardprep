<?php

declare(strict_types=1);

namespace App\Services\Question\Builder;

use App\Services\Shared\TaxonomyStorageService;

final class QuestionTaxonomyResolver
{
    public static function resolve(array $input, ?array $existing = null): array
    {
        $stored = is_array($existing['taxonomy'] ?? null) ? $existing['taxonomy'] : [];

        $concept = self::canonicalId(TaxonomyStorageService::concepts(), self::value($input, ['concept_id', 'concept'], $stored, 'concept_id'));
        $topic = self::canonicalId(TaxonomyStorageService::topics(), self::value($input, ['topic_id', 'topic'], $stored, 'topic_id'));
        $domain = self::canonicalId(TaxonomyStorageService::domains(), self::value($input, ['domain_id', 'domain'], $stored, 'domain_id'));
        $subject = self::canonicalId(TaxonomyStorageService::subjects(), self::value($input, ['subject_id', 'subject'], $stored, 'subject_id'));
        $board = self::canonicalId(TaxonomyStorageService::boards(), self::value($input, ['board_id', 'board'], $stored, 'board_id'));

        $topic = TaxonomyHierarchyIndex::conceptTopic($concept) ?: $topic;
        $domain = TaxonomyHierarchyIndex::topicDomain($topic) ?: $domain;
        $subject = TaxonomyHierarchyIndex::domainSubject($domain) ?: $subject;
        $board = TaxonomyHierarchyIndex::subjectBoard($subject) ?: $board;

        return [
            'board_id' => $board,
            'subject_id' => $subject,
            'domain_id' => $domain,
            'topic_id' => $topic,
            'concept_id' => $concept,
        ];
    }

    private static function value(
        array $input,
        array $keys,
        array $stored,
        string $storedKey
    ): string {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $input)) {
                continue;
            }

            $value = $input[$key];
            return is_scalar($value) ? trim((string) $value) : '';
        }

        return trim((string) ($stored[$storedKey] ?? ''));
    }

    private static function canonicalId(array $records, string $value): string
    {
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }
            foreach (['id', 'code', 'name'] as $field) {
                $candidate = $record[$field] ?? null;
                if (is_scalar($candidate) && strcasecmp(trim((string) $candidate), $value) === 0) {
                    return trim((string) ($record['id'] ?? $value));
                }
            }
        }
        return $value;
    }
}
