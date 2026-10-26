<?php

declare(strict_types=1);

namespace App\Services\Question\Query;

final class QuestionQueryFilters
{
    public function __construct(
        public readonly string $search,
        public readonly string $domain,
        public readonly string $difficulty,
        public readonly string $topic,
        public readonly string $status,
        public readonly string $board,
        public readonly string $subject,
        public readonly string $technicalStatus = '',
        public readonly array $technicalIssueIds = []
    ) {
    }

    public static function from(array $filters): self
    {
        $technicalStatus = strtolower(self::text($filters['technical_status'] ?? ''));
        if (!in_array($technicalStatus, ['issue', 'clear'], true)) {
            $technicalStatus = '';
        }
        $technicalIssueIds = [];
        foreach ((array) ($filters['technical_issue_ids'] ?? []) as $id) {
            if (is_scalar($id) && trim((string) $id) !== '') {
                $technicalIssueIds[(string) $id] = true;
            }
        }

        return new self(
            self::text($filters['search'] ?? ''),
            self::text($filters['domain'] ?? ''),
            self::text($filters['difficulty'] ?? ''),
            self::text($filters['topic'] ?? ''),
            self::text($filters['status'] ?? ''),
            self::text($filters['board'] ?? ''),
            self::text($filters['subject'] ?? ''),
            $technicalStatus,
            $technicalIssueIds
        );
    }

    private static function text(mixed $value): string
    {
        if (!is_scalar($value) && $value !== null) {
            return '';
        }

        return trim((string) ($value ?? ''));
    }
}
