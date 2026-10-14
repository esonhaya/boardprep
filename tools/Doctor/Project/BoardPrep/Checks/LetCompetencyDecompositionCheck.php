<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;

/**
 * Verifies the persisted LET child taxonomy against the current canonical
 * questions, source crosswalk, and study-question pathways. Editorial scope
 * limits are reported as review evidence and are never promoted to failures.
 */
final class LetCompetencyDecompositionCheck implements CheckInterface
{
    private const PARENT = 'prof-development-readiness';
    private const REPORT = 'let-source-crosswalk-batch677.json';

    public function run(): CheckResult
    {
        $root = dirname(__DIR__, 5);
        $files = [
            'questions' => 'questions.json',
            'concepts' => 'taxonomy/concepts.json',
            'topics' => 'taxonomy/topics.json',
            'materials' => 'study-materials.json',
            'links' => 'study-question-links.json',
            'sources' => 'sources.json',
            'readiness' => 'source-readiness-let.json',
        ];
        $collections = [];
        foreach ($files as $key => $file) {
            $path = $root . '/storage/' . $file;
            if (!is_file($path)) {
                return $this->result('FAIL', ['DETERMINISTIC_DEFECT missing storage/' . $file]);
            }
            try {
                $value = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                return $this->result('FAIL', ['DETERMINISTIC_DEFECT invalid JSON in storage/' . $file . ': ' . $exception->getMessage()]);
            }
            if (!is_array($value)) {
                return $this->result('FAIL', ['DETERMINISTIC_DEFECT expected a JSON collection in storage/' . $file]);
            }
            $collections[$key] = $value;
        }
        $reportPath = $root . '/storage/' . self::REPORT;
        if (!is_file($reportPath)) {
            return $this->result('FAIL', ['DETERMINISTIC_DEFECT missing storage/' . self::REPORT]);
        }
        try {
            $report = json_decode((string) file_get_contents($reportPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            return $this->result('FAIL', ['DETERMINISTIC_DEFECT invalid ' . self::REPORT . ': ' . $exception->getMessage()]);
        }

        $audit = self::audit($collections, is_array($report) ? $report : []);
        $details = [
            sprintf(
                'LET_CHILDREN=%d; QUESTIONS=%d; SOURCE_LINKS=%d; DETERMINISTIC_DEFECTS=%d; EDITORIAL_REVIEWS=%d.',
                $audit['summary']['children'],
                $audit['summary']['questions'],
                $audit['summary']['links'],
                count($audit['errors']),
                count($audit['reviews'])
            ),
        ];
        foreach ($audit['errors'] as $finding) $details[] = 'DETERMINISTIC_DEFECT ' . $finding;
        foreach ($audit['reviews'] as $finding) $details[] = 'EDITORIAL_REVIEW ' . $finding;
        foreach ($audit['categories'] as $category) {
            $details[] = sprintf(
                'BOARD=%s QUESTION_ID=%s MATERIAL_ID=%s SOURCE_ID=%s CLASSIFICATION=%s EVIDENCE=%s RECOMMENDED_ACTION=%s',
                $category['board'], $category['question_id'], $category['material_id'], $category['source_id'],
                $category['classification'], $category['evidence'], $category['recommended_action']
            );
        }

        return new CheckResult(
            title: 'LET Competency Decomposition',
            status: $audit['errors'] === [] ? 'PASS' : 'FAIL',
            summary: $audit['errors'] === []
                ? 'LET child categories reconcile to canonical questions and linked source-backed study pathways.'
                : sprintf('%d deterministic LET competency integrity defect(s) require repair.', count($audit['errors'])),
            details: array_slice($details, 0, 70),
            recommendations: $audit['errors'] === [] ? [] : ['Repair the exact taxonomy, source, or question-resource references listed above.'],
            score: $audit['errors'] === [] ? 100 : 0,
        );
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $collections
     * @param array<string, mixed> $report
     * @return array{errors: array<int, string>, reviews: array<int, string>, categories: array<int, array<string, string>>, summary: array<string, int>}
     */
    public static function audit(array $collections, array $report): array
    {
        $errors = [];
        $reviews = [];
        $categoryFindings = [];
        $questionById = [];
        $questionsByLeaf = [];
        foreach ((array) ($collections['questions'] ?? []) as $question) {
            if (!is_array($question)) continue;
            $id = trim((string) ($question['id'] ?? ''));
            if ($id === '') continue;
            if (isset($questionById[$id])) $errors[] = 'question ' . $id . ' has a duplicate canonical ID.';
            $questionById[$id] = $question;
            $tax = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            if (strtolower((string) ($question['board'] ?? '')) === 'let'
                && (string) ($tax['parent_concept_id'] ?? '') === self::PARENT) {
                $leaf = trim((string) ($tax['concept_id'] ?? ''));
                if ($leaf === '' || $leaf === self::PARENT) {
                    $errors[] = 'question ' . $id . ' remains on the broad aggregate instead of a child category.';
                    continue;
                }
                $questionsByLeaf[$leaf][] = $id;
            }
        }

        $concepts = self::index($collections['concepts'] ?? []);
        $topics = self::index($collections['topics'] ?? []);
        $materials = self::index($collections['materials'] ?? []);
        $sources = self::index($collections['sources'] ?? []);
        $readiness = self::index($collections['readiness'] ?? [], 'leaf_id');
        $actualLinks = [];
        foreach ((array) ($collections['links'] ?? []) as $link) {
            if (!is_array($link)) continue;
            $key = trim((string) ($link['question_id'] ?? '')) . '|' . trim((string) ($link['material_id'] ?? ''));
            if ($key === '|') continue;
            if (isset($actualLinks[$key])) $errors[] = 'study link ' . $key . ' is duplicated.';
            $actualLinks[$key] = $link;
        }

        $rows = [];
        foreach ((array) ($report['crosswalks'] ?? []) as $row) {
            if (!is_array($row) || (string) ($row['parent_leaf_id'] ?? '') !== self::PARENT) continue;
            $leaf = trim((string) ($row['leaf_id'] ?? ''));
            if ($leaf === '') {
                $errors[] = 'crosswalk child row has no leaf ID.';
                continue;
            }
            if (isset($rows[$leaf])) $errors[] = 'crosswalk child ' . $leaf . ' is duplicated.';
            $rows[$leaf] = $row;
        }

        $seenQuestionIds = [];
        $linkedCount = 0;
        foreach ($rows as $leaf => $row) {
            $concept = $concepts[$leaf] ?? null;
            $ready = $readiness[$leaf] ?? null;
            if (!is_array($concept) || (string) ($concept['parent_concept_id'] ?? '') !== self::PARENT) {
                $errors[] = $leaf . ' has no child taxonomy node under ' . self::PARENT . '.';
            }
            $taxonomyMetadata = is_array($concept['boardprep_taxonomy'] ?? null) ? $concept['boardprep_taxonomy'] : [];
            if (($taxonomyMetadata['official_status'] ?? '') !== 'BOARDPREP_DERIVED_LEAF'
                || ($taxonomyMetadata['official_leaf_claim'] ?? true) !== false
                || ($taxonomyMetadata['not_official_blueprint_leaf'] ?? false) !== true) {
                $errors[] = $leaf . ' lacks explicit BoardPrep-derived / no-official-leaf metadata.';
            }
            if (($taxonomyMetadata['source_scope_fit'] ?? '') !== 'PARTIAL') {
                $errors[] = $leaf . ' must retain PARTIAL PRC scope fit.';
            }
            $topicId = (string) ($concept['topic_id'] ?? '');
            $topic = $topics[$topicId] ?? null;
            if (!is_array($topic)) $errors[] = $leaf . ' references missing topic ' . $topicId . '.';

            $questionIds = array_map('strval', (array) ($row['question_ids'] ?? []));
            $actualIds = array_map('strval', $questionsByLeaf[$leaf] ?? []);
            self::sortIds($questionIds);
            self::sortIds($actualIds);
            if ($questionIds !== $actualIds) $errors[] = $leaf . ' question IDs do not match current taxonomy.';
            if ((int) ($row['question_count'] ?? -1) !== count($actualIds)
                || !is_array($ready)
                || (int) ($ready['current_question_count'] ?? -1) !== count($actualIds)) {
                $errors[] = $leaf . ' question count differs across taxonomy, report, and readiness.';
            }
            if (!is_array($ready) || (string) ($ready['source_scope_fit'] ?? '') !== 'PARTIAL') {
                $errors[] = $leaf . ' source-readiness record is missing or overstates scope fit.';
            }
            if ((string) ($row['classification'] ?? '') !== 'CORRECTABLE'
                || (string) ($row['source_scope_fit_after'] ?? '') !== 'PARTIAL') {
                $errors[] = $leaf . ' crosswalk classification or scope fit is inconsistent.';
            }

            $rowMaterials = array_values(array_map('strval', (array) ($row['material_ids'] ?? [])));
            $readyMaterials = array_values(array_map('strval', (array) ($ready['study_material_ids'] ?? [])));
            sort($rowMaterials, SORT_STRING);
            sort($readyMaterials, SORT_STRING);
            if ($rowMaterials !== $readyMaterials) $errors[] = $leaf . ' material inventory differs from readiness.';

            $rowQuestionSet = array_fill_keys($questionIds, true);
            foreach ($questionIds as $questionId) {
                if (isset($seenQuestionIds[$questionId])) $errors[] = 'question ' . $questionId . ' is assigned to more than one child category.';
                $seenQuestionIds[$questionId] = true;
                $question = $questionById[$questionId] ?? null;
                if (!is_array($question)) {
                    $errors[] = $leaf . ' references missing canonical question ' . $questionId . '.';
                    continue;
                }
                $tax = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
                if (($tax['concept_id'] ?? '') !== $leaf
                    || ($tax['domain_id'] ?? '') !== ($topic['domain_id'] ?? null)
                    || ($tax['topic_id'] ?? '') !== $topicId
                    || ($tax['board_id'] ?? '') !== 'let') {
                    $errors[] = $leaf . ' taxonomy path differs on question ' . $questionId . '.';
                }
                $hasPath = false;
                foreach ($rowMaterials as $materialId) {
                    $key = $questionId . '|' . $materialId;
                    if (!isset($actualLinks[$key])) continue;
                    $hasPath = true;
                    $linkedCount++;
                    $material = $materials[$materialId] ?? null;
                    if (!is_array($material)) {
                        $errors[] = $leaf . ' question ' . $questionId . ' links missing material ' . $materialId . '.';
                        continue;
                    }
                    foreach ((array) ($actualLinks[$key]['source_ids'] ?? []) as $sourceId) {
                        $sourceId = (string) $sourceId;
                        if (!isset($sources[$sourceId])) $errors[] = $leaf . ' link ' . $key . ' has missing source ' . $sourceId . '.';
                        if (!in_array($sourceId, (array) ($material['source_ids'] ?? []), true)) $errors[] = $leaf . ' link ' . $key . ' cites a source outside its material: ' . $sourceId . '.';
                    }
                }
                if (!$hasPath) $errors[] = $leaf . ' question ' . $questionId . ' has no current linked study material.';
            }

            $categoryEvidence = trim((string) ($row['recommended_action'] ?? ''));
            if ($categoryEvidence === '') $errors[] = $leaf . ' has no recommended action.';
            foreach ((array) ($row['evidence'] ?? []) as $evidence) {
                if (!is_array($evidence)) {
                    $errors[] = $leaf . ' has malformed scope evidence.';
                    continue;
                }
                $sourceId = (string) ($evidence['source_id'] ?? '');
                if (!isset($sources[$sourceId])) $errors[] = $leaf . ' scope evidence references missing source ' . $sourceId . '.';
                if (trim((string) ($evidence['scope'] ?? '')) === '' || trim((string) ($evidence['claim_limit'] ?? '')) === '') {
                    $errors[] = $leaf . ' scope evidence must include both evidence and claim limit.';
                }
            }
            if (count($actualIds) > 25) $reviews[] = $leaf . ' groups ' . count($actualIds) . ' questions; inspect for semantic breadth before adding more.';
            if (($taxonomyMetadata['source_scope_fit'] ?? '') === 'PARTIAL') {
                $reviews[] = $leaf . ' is a BoardPrep-derived learning category; PRC supports only the broad Professional Education area.';
            }
            $firstQuestion = $questionIds[0] ?? '';
            $firstLink = null;
            foreach ($rowMaterials as $materialId) {
                $firstLink = $actualLinks[$firstQuestion . '|' . $materialId] ?? null;
                if (is_array($firstLink)) break;
            }
            $categoryFindings[] = [
                'board' => 'LET',
                'question_id' => $firstQuestion,
                'material_id' => (string) ($firstLink['material_id'] ?? ($rowMaterials[0] ?? '')),
                'source_id' => (string) (($firstLink['source_ids'][0] ?? 'prc-let-enhanced-tos-2025')),
                'classification' => 'EDITORIAL_REVIEW',
                'evidence' => 'PRC scope is broad Professional Education; the child is an evidenced BoardPrep instructional grouping.',
                'recommended_action' => 'Keep the PARTIAL scope label and make no official leaf or item-weight claim without a matching PRC source.',
            ];
        }

        if (count($rows) !== 8) $errors[] = 'expected eight child categories; found ' . count($rows) . '.';
        if (count($seenQuestionIds) !== 67) $errors[] = 'expected 67 unique reassigned question IDs; found ' . count($seenQuestionIds) . '.';
        if (isset($questionsByLeaf[self::PARENT]) && $questionsByLeaf[self::PARENT] !== []) $errors[] = 'broad parent still has directly assigned questions.';
        $summary = ['children' => count($rows), 'questions' => count($seenQuestionIds), 'links' => $linkedCount];
        return ['errors' => array_values(array_unique($errors)), 'reviews' => array_values(array_unique($reviews)), 'categories' => $categoryFindings, 'summary' => $summary];
    }

    public function category(): string { return 'Content'; }
    public function priority(): int { return 27; }

    /** @param array<int, array<string, mixed>> $rows @return array<string, array<string, mixed>> */
    private static function index(array $rows, string $key = 'id'): array
    {
        $index = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $id = trim((string) ($row[$key] ?? ''));
            if ($id !== '') $index[$id] = $row;
        }
        return $index;
    }

    /** @param array<int, string> $ids */
    private static function sortIds(array &$ids): void
    {
        usort($ids, static fn(string $a, string $b): int => ctype_digit($a) && ctype_digit($b) ? (int) $a <=> (int) $b : strcmp($a, $b));
    }

    /** @param array<int, string> $details */
    private function result(string $status, array $details): CheckResult
    {
        return new CheckResult(
            title: 'LET Competency Decomposition',
            status: $status,
            summary: $status === 'PASS' ? 'LET competency paths are structurally valid.' : 'LET competency inputs have deterministic defects.',
            details: $details,
            recommendations: $status === 'PASS' ? [] : ['Restore valid JSON and report inputs, then rerun the LET decomposition check.'],
            score: $status === 'PASS' ? 100 : 0,
        );
    }
}
