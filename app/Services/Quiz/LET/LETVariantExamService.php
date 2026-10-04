<?php

declare(strict_types=1);

namespace App\Services\Quiz\LET;

use App\Core\App;
use App\Services\Question\QuestionEligibilityIndex;
use App\Services\Question\QuestionReuseEligibilityResolver;

/**
 * Component-aware LET exam assembly.  It intentionally does not recover a
 * specialization shortage from GE or PE; a missing specialization pool must
 * remain visible to the learner and to the blueprint audit.
 */
final class LETVariantExamService
{
    public static function execute(array $questions, \QuizSpecification $specification, ?QuestionEligibilityIndex $eligibilityIndex = null): \BlueprintExecutionResult
    {
        $validation = LETVariantRegistry::validateConfiguration($specification->letVariant);
        if (($validation['valid'] ?? false) !== true) {
            throw new \InvalidArgumentException(implode(' ', array_values($validation['errors'] ?? [])));
        }

        $configuration = $validation['configuration'];
        $blueprint = LETVariantRegistry::officialBlueprint($configuration);
        $requests = [];
        $counts = self::allocate(
            $specification->questionCount,
            $blueprint['components'] ?? []
        );
        $difficulty = self::difficulty($specification->difficulty);

        foreach (($blueprint['components'] ?? []) as $component => $weight) {
            $requests[] = new \SelectionRequest(
                subject: '*',
                domain: null,
                difficultyDistribution: $difficulty,
                questionCount: $counts[$component] ?? 0,
                exam: 'let',
                letVariant: $configuration,
                letComponent: (string) $component
            );
        }

        $index = $eligibilityIndex ?? new QuestionEligibilityIndex(
            App::storage()->all('question-eligibility'),
            App::storage()->all('blueprints'),
            QuestionReuseEligibilityResolver::productionDerivedRelationships(),
            App::storage()->all('question-reuse-overrides')
        );
        $selected = [];
        $reserved = [];
        $coverage = [];
        $issues = [];

        foreach ($requests as $request) {
            if ($request->questionCount < 1) {
                continue;
            }
            $result = \QuestionSelectionService::fulfillRequest($questions, $request, $index);
            $chunk = [];
            foreach ($result->questions as $question) {
                $id = (string) ($question['id'] ?? '');
                if ($id === '' || isset($reserved[$id])) {
                    continue;
                }
                $reserved[$id] = true;
                $chunk[] = $question;
            }
            $selected = array_merge($selected, $chunk);
            $shortage = max(0, $request->questionCount - count($chunk));
            $coverage[] = [
                'component' => $request->letComponent,
                'specialization_id' => $configuration['specialization_id'] ?? null,
                'required' => $request->questionCount,
                'selected' => count($chunk),
                'shortage' => $shortage,
            ];
            if ($shortage > 0) {
                $issues[] = [
                    'code' => $request->letComponent === LETVariantRegistry::SPECIALIZATION
                        ? 'LET_SPECIALIZATION_POOL_SHORTAGE'
                        : 'LET_COMPONENT_POOL_SHORTAGE',
                    'component' => $request->letComponent,
                    'specialization_id' => $configuration['specialization_id'] ?? null,
                    'shortage' => $shortage,
                ];
            }
        }

        return new \BlueprintExecutionResult(
            questions: $selected,
            requests: $requests,
            coverage: $coverage,
            issues: $issues,
            boardBlueprintVersion: 1,
            subjectBlueprintVersion: null
        );
    }

    /** @return array<string,int> */
    private static function allocate(int $total, array $weights): array
    {
        $total = max(0, $total);
        $allocations = [];
        $fractions = [];
        $assigned = 0;
        foreach ($weights as $component => $weight) {
            $raw = $total * ((float) $weight / 100.0);
            $whole = (int) floor($raw);
            $allocations[(string) $component] = $whole;
            $fractions[(string) $component] = $raw - $whole;
            $assigned += $whole;
        }
        arsort($fractions, SORT_NUMERIC);
        foreach (array_keys($fractions) as $component) {
            if ($assigned >= $total) {
                break;
            }
            $allocations[$component]++;
            $assigned++;
        }
        return $allocations;
    }

    /** @return array<string,int> */
    private static function difficulty(string $difficulty): array
    {
        $difficulty = strtolower(trim($difficulty));
        return in_array($difficulty, ['easy', 'medium', 'hard'], true)
            ? [$difficulty => 100]
            : ['easy' => 30, 'medium' => 50, 'hard' => 20];
    }
}
