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
        array $summary
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

        return $attempt;
    }
}
