<?php

declare(strict_types=1);

namespace App\Services\Quiz\Start;

use App\Services\Question\QuestionEligibilityIndex;

final class QuizStartPreparationService
{
    public static function prepare(array $input, array $questions, ?QuestionEligibilityIndex $eligibilityIndex = null): QuizStartPreparation
    {
        $specification = QuizStartSpecificationFactory::create($input);
        $result = \QuizGenerationService::generate($questions, $specification, $eligibilityIndex);

        // Verify the delivered set independently of the pool-building path.
        // This is a last boundary before questions enter learner session state.
        $eligibilityIndex ??= \App\Services\Question\QuestionEligibilityService::index();
        $requested = strtolower(trim($specification->subject));
        $subjectAliases = [$requested => true];
        foreach (\App\Services\Shared\TaxonomyStorageService::subjects() as $subject) {
            if (!is_array($subject)) continue;
            $id = strtolower(trim((string) ($subject['id'] ?? '')));
            $name = strtolower(trim((string) ($subject['name'] ?? '')));
            if ($requested !== '' && ($id === $requested || $name === $requested)) {
                if ($id !== '') $subjectAliases[$id] = true;
                if ($name !== '') $subjectAliases[$name] = true;
            }
        }
        foreach ($result->questions as $question) {
            $effective = \App\Services\Question\QuestionEligibilityService::forExam(
                $question,
                $specification->board,
                $eligibilityIndex
            );
            $effectiveSubject = strtolower(trim((string) ($effective['subject_id'] ?? '')));
            if ($effective === null || $effectiveSubject === ''
                || ($requested !== '*' && !isset($subjectAliases[$effectiveSubject]))) {
                throw new \InvalidArgumentException('Selected questions did not pass the requested board and subject eligibility check. Please retry with a narrower supported selection.');
            }
        }

        return new QuizStartPreparation(
            $specification,
            $result->questions,
            $result->coverage,
            $result->issues
        );
    }
}
