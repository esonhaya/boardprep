<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

use App\Core\App;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;

/**
 * Verifies that material prerequisite metadata is referentially and
 * graph-theoretically safe before it can influence reuse eligibility.
 */
final class PrerequisiteIntegrityCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $storage = App::storage();
        $questions = array_values(array_filter($storage->all('questions'), 'is_array'));
        $concepts = array_values(array_filter($storage->all('taxonomy/concepts'), 'is_array'));
        $requirements = array_values(array_filter($storage->all('question-required-concepts'), 'is_array'));

        $questionById = [];
        foreach ($questions as $question) {
            $id = trim((string) ($question['id'] ?? ''));
            if ($id !== '') {
                $questionById[$id] = $question;
            }
        }

        $conceptIds = [];
        foreach ($concepts as $concept) {
            $id = trim((string) ($concept['id'] ?? ''));
            if ($id !== '') {
                $conceptIds[$id] = true;
            }
        }

        $invalid = [];
        $self = [];
        $duplicates = [];
        $seen = [];
        $graph = [];

        foreach ($requirements as $requirement) {
            $questionId = trim((string) ($requirement['question_id'] ?? ''));
            $requiredConcept = trim((string) ($requirement['concept_id'] ?? ''));
            $type = (string) ($requirement['requirement_type'] ?? '');
            $key = $questionId . '|' . $requiredConcept;

            if (isset($seen[$key])) {
                $duplicates[] = $key;
            }
            $seen[$key] = true;

            $question = $questionById[$questionId] ?? null;
            $primaryConcept = is_array($question)
                ? trim((string) (($question['taxonomy']['concept_id'] ?? '')))
                : '';

            if (!isset($questionById[$questionId])) {
                $invalid[] = $key . ':QUESTION';
            }
            if (!isset($conceptIds[$requiredConcept])) {
                $invalid[] = $key . ':CONCEPT';
            }
            if (!in_array($type, ['REQUIRED', 'ASSUMED_FOUNDATION'], true)) {
                $invalid[] = $key . ':TYPE';
            }
            if ($primaryConcept === '' || !isset($conceptIds[$primaryConcept])) {
                $invalid[] = $key . ':PRIMARY_CONCEPT';
            }
            if ($primaryConcept !== '' && $primaryConcept === $requiredConcept) {
                $self[] = $key;
            }

            if ($type === 'REQUIRED'
                && $primaryConcept !== ''
                && isset($conceptIds[$primaryConcept])
                && isset($conceptIds[$requiredConcept])
                && $primaryConcept !== $requiredConcept
            ) {
                $graph[$primaryConcept][$requiredConcept] = true;
            }
        }

        $cycles = $this->cycles($graph);
        $invalid = array_values(array_unique($invalid));
        $self = array_values(array_unique($self));
        $duplicates = array_values(array_unique($duplicates));

        $defects = count($invalid) + count($self) + count($cycles) + count($duplicates);
        $status = $defects === 0 ? 'PASS' : 'FAIL';
        $details = [
            sprintf(
                'Requirements=%d; questions=%d; concepts=%d; material_edges=%d.',
                count($requirements),
                count($questionById),
                count($conceptIds),
                array_sum(array_map('count', $graph))
            ),
            'INVALID_REQUIRED_CONCEPT_REFERENCES=' . count($invalid),
            'SELF_PREREQUISITES=' . count($self),
            'CIRCULAR_PREREQUISITES=' . count($cycles),
            'DUPLICATE_REQUIRED_CONCEPTS=' . count($duplicates),
        ];

        if ($invalid !== []) {
            $details[] = 'INVALID=' . implode(',', array_slice($invalid, 0, 20));
        }
        if ($self !== []) {
            $details[] = 'SELF=' . implode(',', array_slice($self, 0, 20));
        }
        if ($cycles !== []) {
            $details[] = 'CYCLES=' . implode(',', array_slice($cycles, 0, 20));
        }

        return new CheckResult(
            title: 'Prerequisite Integrity',
            status: $status,
            summary: $status === 'PASS'
                ? 'Required-concept references are valid and acyclic.'
                : sprintf('%d prerequisite integrity defect(s) require repair.', $defects),
            details: $details,
            recommendations: $status === 'PASS' ? [] : [
                'Repair invalid, duplicate, self-referential, or circular prerequisite metadata before enabling reuse.'
            ],
            score: $status === 'PASS' ? 100 : 0,
        );
    }

    public function category(): string
    {
        return 'Content';
    }

    public function priority(): int
    {
        return 23;
    }

    /**
     * @param array<string,array<string,bool>> $graph
     * @return string[]
     */
    private function cycles(array $graph): array
    {
        $state = [];
        $stack = [];
        $cycles = [];

        $visit = function (string $node) use (&$visit, &$state, &$stack, &$cycles, $graph): void {
            $state[$node] = 1;
            $stack[] = $node;

            $neighbors = array_keys($graph[$node] ?? []);
            sort($neighbors, SORT_STRING);
            foreach ($neighbors as $neighbor) {
                if (($state[$neighbor] ?? 0) === 0) {
                    $visit($neighbor);
                    continue;
                }
                if (($state[$neighbor] ?? 0) !== 1) {
                    continue;
                }

                $start = array_search($neighbor, $stack, true);
                $cycle = $start === false ? [$neighbor, $node] : array_slice($stack, $start);
                $cycle[] = $neighbor;
                $cycles[implode('>', $cycle)] = true;
            }

            array_pop($stack);
            $state[$node] = 2;
        };

        $nodes = array_keys($graph);
        foreach ($graph as $neighbors) {
            foreach (array_keys($neighbors) as $neighbor) {
                $nodes[] = $neighbor;
            }
        }
        $nodes = array_values(array_unique($nodes));
        sort($nodes, SORT_STRING);
        foreach ($nodes as $node) {
            if (($state[$node] ?? 0) === 0) {
                $visit($node);
            }
        }

        $cycles = array_keys($cycles);
        sort($cycles, SORT_STRING);
        return $cycles;
    }
}
