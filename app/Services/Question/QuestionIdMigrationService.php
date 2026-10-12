<?php

declare(strict_types=1);

namespace App\Services\Question;

final class QuestionIdMigrationService
{
    public const VERSION = 'batch669-nursing-id-map-v1';

    private const LEGACY_FIRST_ID = 4163;
    private const LEGACY_LAST_ID = 4482;
    private const CURRENT_FIRST_ID = 4873;

    public static function canonicalId(string $questionId, mixed $board): string
    {
        $questionId = trim($questionId);
        $boardId = is_scalar($board) ? strtolower(trim((string) $board)) : '';
        if ($boardId === 'nle') {
            $boardId = 'nursing';
        }

        if ($boardId !== 'nursing' || preg_match('/^\d+$/', $questionId) !== 1) {
            return $questionId;
        }

        $legacyId = (int) $questionId;
        if ($legacyId < self::LEGACY_FIRST_ID || $legacyId > self::LEGACY_LAST_ID) {
            return $questionId;
        }

        return (string) (self::CURRENT_FIRST_ID + ($legacyId - self::LEGACY_FIRST_ID));
    }

    public static function canonicalIdForQuestion(array $question): string
    {
        $id = is_scalar($question['id'] ?? null) ? trim((string) $question['id']) : '';
        return self::canonicalId($id, self::boardForQuestion($question));
    }

    public static function boardForQuestion(array $question): string
    {
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        $board = is_scalar($question['board'] ?? null) ? trim((string) $question['board']) : '';
        if ($board === '') {
            $board = is_scalar($taxonomy['board_id'] ?? null)
                ? trim((string) $taxonomy['board_id'])
                : '';
        }
        return is_scalar($board) ? strtolower(trim((string) $board)) : '';
    }

    public static function canonicalizeAnswerPlan(array $questions, array $plan): array
    {
        $canonicalIds = [];
        foreach ($questions as $question) {
            if (!is_array($question) || !is_scalar($question['id'] ?? null)) {
                continue;
            }

            $legacyId = trim((string) $question['id']);
            $canonicalIds[$legacyId] = self::canonicalIdForQuestion($question);
        }

        foreach ($plan as &$entry) {
            if (!is_array($entry) || !is_scalar($entry['question_id'] ?? null)) {
                continue;
            }

            $legacyId = trim((string) $entry['question_id']);
            if (isset($canonicalIds[$legacyId])) {
                $entry['question_id'] = $canonicalIds[$legacyId];
            }
        }
        unset($entry);

        return $plan;
    }

    public static function canonicalizeReview(array $review, array $attempt): array
    {
        $attemptBoard = $attempt['board'] ?? '';
        foreach ($review as &$item) {
            if (!is_array($item) || !is_array($item['question'] ?? null)) {
                continue;
            }

            $question = $item['question'];
            $id = is_scalar($question['id'] ?? null) ? trim((string) $question['id']) : '';
            if ($id === '') {
                continue;
            }

            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            $board = is_scalar($question['board'] ?? null) ? trim((string) $question['board']) : '';
            if ($board === '') {
                $board = is_scalar($taxonomy['board_id'] ?? null)
                    ? trim((string) $taxonomy['board_id'])
                    : $attemptBoard;
            }
            $canonicalId = self::canonicalId($id, $board);
            if ($canonicalId === $id) {
                continue;
            }

            $question['id'] = $canonicalId;
            $item['question'] = $question;
            if (is_scalar($item['question_id'] ?? null)
                && trim((string) $item['question_id']) === $id) {
                $item['question_id'] = $canonicalId;
            }
        }
        unset($item);

        return $review;
    }
}
