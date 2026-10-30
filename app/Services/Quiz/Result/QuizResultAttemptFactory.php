<?php

declare(strict_types=1);

use App\Services\Quiz\Result\Attempt\AttemptQuestionSet;
use App\Services\Quiz\Result\Attempt\AttemptRecordFactory;
use App\Services\Quiz\Result\Attempt\AttemptScoreSummary;
use App\Services\Quiz\Result\Attempt\AttemptSessionContext;

final class QuizResultAttemptFactory
{
    /**
     * @param array<string,mixed> $session
     * @param array<int,array<string,mixed>> $questions
     * @param array<string,mixed> $summary
     * @return array<string,mixed>
     */
    public static function create(
        array $session,
        array $questions,
        array $summary,
        ?array $reviewFlagSnapshot = null
    ): array {
        $questionSet = AttemptQuestionSet::fromQuestions($questions);

        $attempt = AttemptRecordFactory::create(
            AttemptSessionContext::fromSession($session),
            $questionSet,
            AttemptScoreSummary::fromSummary(
                $summary,
                $questionSet["question_count"]
            )
        );

        $attempt['review'] = is_array($summary['results'] ?? null)
            ? array_values(array_filter(
                $summary['results'],
                static fn (mixed $result): bool => is_array($result)
            ))
            : [];

        // These are bounded snapshots already validated at quiz start. Keeping them
        // on the existing attempt makes private note context and recovery origin
        // available after the session expires without creating another data store.
        if (is_array($session['notes_practice'] ?? null)) {
            $attempt['notes_practice'] = $session['notes_practice'];
        }
        if (is_array($session['mistake_recovery'] ?? null)) {
            $attempt['mistake_recovery'] = $session['mistake_recovery'];
        }

        $snapshot = self::validatedReviewFlagSnapshot($reviewFlagSnapshot, $questionSet['question_ids']);
        if ($snapshot !== null) {
            $attempt['review_flag_snapshot'] = $snapshot;
        }

        return $attempt;
    }

    /** @param array<int,string> $attemptQuestionIds */
    private static function validatedReviewFlagSnapshot(?array $snapshot, array $attemptQuestionIds): ?array
    {
        if ($snapshot === null || ($snapshot['version'] ?? null) !== 1
            || !is_array($snapshot['marked_question_ids'] ?? null)
            || !array_is_list($snapshot['marked_question_ids'])) {
            return null;
        }

        $allowed = array_fill_keys($attemptQuestionIds, true);
        $marked = [];
        foreach ($snapshot['marked_question_ids'] as $questionId) {
            if (!is_scalar($questionId) || trim((string) $questionId) === '') {
                return null;
            }
            $questionId = trim((string) $questionId);
            if (!isset($allowed[$questionId]) || isset($marked[$questionId])) {
                return null;
            }
            $marked[$questionId] = true;
        }

        return [
            'version' => 1,
            'marked_question_ids' => array_values(array_filter(
                $attemptQuestionIds,
                static fn(string $questionId): bool => isset($marked[$questionId])
            )),
        ];
    }
}
