<?php

declare(strict_types=1);

namespace App\Services\Flashcard;

use App\Services\Question\QuestionEligibilityIndex;
use App\Services\Question\QuestionEligibilityService;
use App\Services\Quiz\LET\LETVariantRegistry;

/** Shared server-side scope checks for flashcard options, cards, and reviews. */
final class FlashcardScopeMatcher
{
    /** @return array<string,mixed>|null */
    public static function effectiveEligibility(array $question, array $filters, ?QuestionEligibilityIndex $index = null): ?array
    {
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        $board = strtolower(trim((string) ($filters['board'] ?? '')));
        if ($board === '') {
            return ['subject_id' => (string) ($taxonomy['subject_id'] ?? $question['subject'] ?? '')];
        }

        $effective = QuestionEligibilityService::forExam($question, $board, $index);
        if ($effective === null || trim((string) ($effective['subject_id'] ?? '')) === '') return null;

        if ($board === 'let' && is_array($filters['let_variant'] ?? null)
            && LETVariantRegistry::placementForConfiguration($question, $filters['let_variant'], null, $effective) === null) {
            return null;
        }

        if (in_array($board, ['civil-service', 'cse'], true) && ($filters['civil_service_level'] ?? '') !== '') {
            $level = strtoupper(trim((string) $filters['civil_service_level']));
            if (!in_array($level, ['PROFESSIONAL', 'SUBPROFESSIONAL'], true)) return null;
            $declared = $question['civil_service_levels'] ?? null;
            if (is_array($declared) && !in_array($level, array_map(static fn(mixed $item): string => strtoupper(trim((string) $item)), $declared), true)) {
                return null;
            }
            if ($level === 'PROFESSIONAL' && in_array(strtolower(trim((string) $effective['subject_id'])), ['clerical-ability', 'clerical ability'], true)) {
                return null;
            }
        }

        return $effective;
    }

    public static function matches(array $question, array $filters, ?QuestionEligibilityIndex $index = null): bool
    {
        $effective = self::effectiveEligibility($question, $filters, $index);
        if ($effective === null) return false;
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        if (isset($filters['subject'])
            && strtolower(trim((string) ($effective['subject_id'] ?? ''))) !== strtolower(trim((string) $filters['subject']))) return false;
        $placement = is_array($effective['placement'] ?? null) ? $effective['placement'] : [];
        $topic = (string) ($placement['topic_id'] ?? $placement['topic'] ?? $taxonomy['topic_id'] ?? $question['topic'] ?? '');
        if (isset($filters['topic']) && strtolower(trim($topic)) !== strtolower(trim((string) $filters['topic']))) return false;
        return true;
    }
}
