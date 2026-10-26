<?php

declare(strict_types=1);

namespace App\Services\Quiz\Start;

use App\Services\Question\QuestionEligibilityIndex;

final class QuizStartPreparationService
{
    public static function prepare(array $input, array $questions, ?QuestionEligibilityIndex $eligibilityIndex = null): QuizStartPreparation
    {
        $specification = QuizStartSpecificationFactory::create($input);
        $adaptiveContext = \AdaptiveQuizService::selectionContext($specification);
        $result = \QuizGenerationService::generate($questions, $specification, $eligibilityIndex, $adaptiveContext);

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
        $preparedQuestions = [];
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

            // Keep the board-resolved identity beside this session snapshot.
            // A canonical question may be reusable on another board under a
            // different subject, so taxonomy alone is not enough to attribute
            // later full-board answer analytics safely.
            $variant = is_array($specification->letVariant) ? $specification->letVariant : [];
            $question['attempt_context'] = [
                'board' => (string) ($effective['exam_id'] ?? $specification->board),
                'subject' => (string) ($effective['subject_id'] ?? ''),
                'let_level' => (string) ($variant['level'] ?? ''),
                'let_specialization' => (string) ($variant['specialization_id'] ?? ''),
                'civil_service_level' => (string) ($specification->civilServiceLevel ?? ''),
            ];
            $preparedQuestions[] = $question;
        }

        return new QuizStartPreparation(
            $specification,
            $preparedQuestions,
            $result->coverage,
            $result->issues,
            \AdaptiveQuizService::selectionExplanation($adaptiveContext, $result->questions)
        );
    }
}
