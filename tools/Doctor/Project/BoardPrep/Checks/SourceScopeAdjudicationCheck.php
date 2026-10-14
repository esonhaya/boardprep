<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;

/**
 * Verifies the evidence-backed Batch 675 CSE parent crosswalk and ensures
 * unresolved source-scope records remain visible rather than being promoted.
 */
final class SourceScopeAdjudicationCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $root = dirname(__DIR__, 5);
        $errors = [];
        $details = [];
        $read = static function (string $name) use ($root): array {
            $path = $root . '/storage/' . $name . '.json';
            if (!is_file($path)) {
                throw new \RuntimeException('Missing required source-scope evidence: ' . $path);
            }
            $value = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            return is_array($value) ? $value : [];
        };

        try {
            $ledger = $read('source-review-adjudications-batch675');
            $links = $read('study-question-links');
            $materials = $read('study-materials');
            $sources = $read('sources');
            $coverage = $read('board-concept-coverage');
            $civilReadiness = $read('source-readiness-civil-service');
            $letReadiness = $read('source-readiness-let');
            $criminologistReadiness = $read('source-readiness');
            $psychReadiness = $read('source-readiness-psychometrician');
        } catch (\Throwable $error) {
            return new CheckResult(
                title: 'Source Scope Adjudications',
                status: 'FAIL',
                summary: 'Source-scope evidence could not be loaded: ' . $error->getMessage(),
                details: [],
                recommendations: ['Restore valid source-scope JSON and rerun this evidence check.'],
                score: 0
            );
        }

        $expected = [
            'civil-service|cse-constitutional-principles' => 'VERIFIED_PARENT_SCOPE',
            'civil-service|cse-environmental-public-interest' => 'VERIFIED_PARENT_SCOPE',
            'civil-service|cse-ethical-public-service' => 'VERIFIED_PARENT_SCOPE',
            'let|cse-constitutional-principles' => 'DEFERRED',
            'let|cse-environmental-public-interest' => 'DEFERRED',
            'let|cse-ethical-public-service' => 'DEFERRED',
            'criminologist|cse-constitutional-principles' => 'JUSTIFIED_EXCEPTION',
            'criminologist|cse-ethical-public-service' => 'JUSTIFIED_EXCEPTION',
            'criminologist|social-civics-public-good' => 'JUSTIFIED_EXCEPTION',
            'criminologist|social-history-primary-sources' => 'JUSTIFIED_EXCEPTION',
            'psychometrician|psych-abnormal-mental-health-law' => 'DEFERRED_CURRENTNESS',
        ];
        $adjudications = [];
        foreach ((array) ($ledger['adjudications'] ?? []) as $row) {
            if (!is_array($row)) continue;
            $key = (string) ($row['board_id'] ?? '') . '|' . (string) ($row['record_id'] ?? '');
            if (isset($adjudications[$key])) $errors[] = 'Duplicate adjudication row: ' . $key;
            $adjudications[$key] = $row;
            foreach (['evidence', 'action'] as $field) {
                if (trim((string) ($row[$field] ?? '')) === '') $errors[] = $key . ' is missing ' . $field . '.';
            }
        }
        if ((int) ($ledger['baseline_signal_count'] ?? -1) !== 11 || count($adjudications) !== 11) {
            $errors[] = 'The ledger must account for all 11 baseline source-review signals exactly once.';
        }
        if (array_keys($expected) !== array_keys($adjudications)) {
            $missing = array_diff(array_keys($expected), array_keys($adjudications));
            $extra = array_diff(array_keys($adjudications), array_keys($expected));
            if ($missing !== []) $errors[] = 'Missing adjudications: ' . implode(', ', $missing) . '.';
            if ($extra !== []) $errors[] = 'Unexpected adjudications: ' . implode(', ', $extra) . '.';
        }
        foreach ($expected as $key => $classification) {
            if (($adjudications[$key]['classification'] ?? null) !== $classification) {
                $errors[] = $key . ' must remain classified as ' . $classification . '.';
            }
        }

        $sourceById = self::index($sources, 'id');
        $materialById = self::index($materials, 'id');
        $readinessByLeaf = self::index($civilReadiness, 'leaf_id');
        $scopeItems = [
            'cse-constitutional-principles' => 'Philippine Constitution',
            'cse-environmental-public-interest' => 'Environment Management and Protection',
            'cse-ethical-public-service' => 'Code of Conduct and Ethical Standards for Public Officials and Employees (R.A. 6713)',
        ];
        $reportedCrosswalks = [];
        foreach ((array) ($ledger['rows'] ?? []) as $row) {
            if (!is_array($row)) continue;
            $leaf = (string) ($row['leaf_id'] ?? '');
            $reportedCrosswalks[$leaf] = $row;
        }
        if (array_keys($reportedCrosswalks) !== array_keys($scopeItems)) {
            $errors[] = 'The three evidence-backed Civil Service General Information crosswalk rows are incomplete or duplicated.';
        }

        $targetBoardsByConcept = array_fill_keys(array_keys($scopeItems), 'civil-service');
        $targetBoardsByConcept['psych-abnormal-mental-health-law'] = 'psychometrician';
        $questionIdsByConcept = [];
        $allQuestionIds = [];
        foreach (self::streamQuestions($root . '/storage/questions.json') as $question) {
            $id = (string) ($question['id'] ?? '');
            if ($id === '') continue;
            if (isset($allQuestionIds[$id])) $errors[] = 'Duplicate canonical question ID encountered: ' . $id . '.';
            $allQuestionIds[$id] = true;
            $taxonomy = (array) ($question['taxonomy'] ?? []);
            $conceptId = (string) ($taxonomy['concept_id'] ?? '');
            if (isset($targetBoardsByConcept[$conceptId])
                && ($taxonomy['board_id'] ?? '') === $targetBoardsByConcept[$conceptId]) {
                $questionIdsByConcept[$conceptId][] = $id;
            }
        }

        foreach ($scopeItems as $leaf => $officialItem) {
            $row = $reportedCrosswalks[$leaf] ?? [];
            $key = 'civil-service|' . $leaf;
            if (($row['board_id'] ?? '') !== 'civil-service'
                || ($row['official_parent'] ?? '') !== 'General Information'
                || ($row['official_scope_item'] ?? '') !== $officialItem
                || ($row['classification'] ?? '') !== 'VERIFIED_PARENT_SCOPE'
                || ($row['official_child_claim'] ?? true) !== false
                || !array_key_exists('official_weight_claim', $row)
                || $row['official_weight_claim'] !== null) {
                $errors[] = $leaf . ' overstates or misstates the official scope mapping.';
            }
            $scopeIds = (array) ($row['scope_source_ids'] ?? []);
            foreach (['csc-cse-ppt-scope-2026', 'csc-cse-ppt-calendar-2027'] as $sourceId) {
                if (!isset($sourceById[$sourceId])) $errors[] = $leaf . ' references missing source ' . $sourceId . '.';
                if (!in_array($sourceId, $scopeIds, true)) $errors[] = $leaf . ' omits scope source ' . $sourceId . '.';
            }
            $scopeSource = $sourceById['csc-cse-ppt-scope-2026'] ?? [];
            if (!str_contains((string) ($scopeSource['authority'] ?? ''), 'Civil Service Commission')
                || !str_starts_with((string) ($scopeSource['url'] ?? ''), 'https://')) {
                $errors[] = 'The CSE scope source is not a verified CSC HTTPS reference.';
            }

            $actualQuestionIds = $questionIdsByConcept[$leaf] ?? [];
            sort($actualQuestionIds, SORT_NUMERIC);
            $reportedQuestionIds = array_map('strval', (array) ($row['question_ids'] ?? []));
            sort($reportedQuestionIds, SORT_NUMERIC);
            if ($actualQuestionIds === [] || $reportedQuestionIds !== $actualQuestionIds
                || (int) ($row['question_count'] ?? -1) !== count($actualQuestionIds)) {
                $errors[] = $leaf . ' question IDs/count do not reconcile with canonical Civil Service taxonomy.';
            }

            $actualMaterialIds = [];
            $linkedQuestionIds = [];
            $questionSet = array_fill_keys($actualQuestionIds, true);
            foreach ($links as $link) {
                $questionId = (string) ($link['question_id'] ?? '');
                if (!isset($questionSet[$questionId])) continue;
                $linkedQuestionIds[$questionId] = true;
                $actualMaterialIds[] = (string) ($link['material_id'] ?? '');
            }
            sort($actualMaterialIds, SORT_STRING);
            $actualMaterialIds = array_values(array_unique($actualMaterialIds));
            $reportedMaterialIds = array_map('strval', (array) ($row['material_ids'] ?? []));
            sort($reportedMaterialIds, SORT_STRING);
            if ($actualMaterialIds !== $reportedMaterialIds) $errors[] = $leaf . ' question-to-material pathway list is stale.';
            if (count($linkedQuestionIds) !== count($actualQuestionIds)) $errors[] = $leaf . ' has one or more questions without a study-resource link.';
            foreach ($actualMaterialIds as $materialId) {
                if (!isset($materialById[$materialId])) $errors[] = $leaf . ' references missing study material ' . $materialId . '.';
            }
            $adjudication = $adjudications[$key] ?? [];
            $ledgerQuestionIds = array_map('strval', (array) ($adjudication['question_ids'] ?? []));
            sort($ledgerQuestionIds, SORT_NUMERIC);
            if ($ledgerQuestionIds !== $actualQuestionIds) $errors[] = $leaf . ' adjudication question IDs are stale.';

            $readiness = $readinessByLeaf[$leaf] ?? [];
            if (($readiness['scope_status'] ?? '') !== 'STRUCTURAL_NOT_ACTIONABLE'
                || ($readiness['scope_crosswalk_status'] ?? '') !== 'VERIFIED_PARENT_SCOPE'
                || ($readiness['scope_crosswalk_id'] ?? '') !== 'source-review-adjudications-batch675'
                || ($readiness['source_scope_fit'] ?? '') !== 'STRONG'
                || ($readiness['readiness'] ?? '') !== 'REVIEW'
                || (int) ($readiness['max_new_questions'] ?? -1) !== 0) {
                $errors[] = $leaf . ' readiness must record verified parent scope while retaining its no-authoring review gate.';
            }
            foreach ($coverage as $mapping) {
                if (($mapping['board_id'] ?? '') === 'civil-service' && ($mapping['concept_id'] ?? '') === $leaf
                    && ($mapping['subject_id'] ?? '') === 'general-information' && ($mapping['status'] ?? '') === 'VERIFIED') {
                    continue 2;
                }
            }
            $errors[] = $leaf . ' has no verified Civil Service General Information concept mapping.';
        }

        foreach (array_keys($expected) as $key) {
            $row = $adjudications[$key] ?? [];
            $qids = array_map('strval', (array) ($row['question_ids'] ?? []));
            $materials = (array) ($row['material_ids'] ?? []);
            foreach ($qids as $qid) if (!isset($allQuestionIds[$qid])) $errors[] = $key . ' references unknown question ' . $qid . '.';
            foreach ($materials as $mid) if (!isset($materialById[$mid])) $errors[] = $key . ' references unknown material ' . $mid . '.';
            if ($key === 'let|cse-constitutional-principles' || $key === 'let|cse-environmental-public-interest' || $key === 'let|cse-ethical-public-service') {
                [$board, $leaf] = explode('|', $key, 2);
                $record = self::index($letReadiness, 'leaf_id')[$leaf] ?? [];
                if (($record['board_id'] ?? '') !== $board || (int) ($record['current_question_count'] ?? -1) !== 0
                    || ($record['source_scope_fit'] ?? '') !== 'UNKNOWN' || $record['study_material_ids'] !== []) {
                    $errors[] = $key . ' must remain explicitly deferred until LET-specific evidence exists.';
                }
            }
            if (str_starts_with($key, 'criminologist|')) {
                [, $leaf] = explode('|', $key, 2);
                $record = self::index($criminologistReadiness, 'leaf_id')[$leaf] ?? [];
                if (($record['scope_status'] ?? '') !== 'STRUCTURAL_NOT_ACTIONABLE'
                    || (int) ($record['current_question_count'] ?? -1) !== 0) {
                    $errors[] = $key . ' no longer matches its documented shared-foundation exception.';
                }
            }
            if ($key === 'psychometrician|psych-abnormal-mental-health-law') {
                $record = self::index($psychReadiness, 'leaf_id')['psych-abnormal-mental-health-law'] ?? [];
                if (($record['source_currentness'] ?? '') !== 'UNKNOWN' || (int) ($record['current_question_count'] ?? -1) !== 1) {
                    $errors[] = 'Psychometrician currentness uncertainty must remain visible until current primary evidence is checked.';
                }
            }
            $details[] = $key . ' => ' . (string) ($row['classification'] ?? 'MISSING')
                . '; questions=' . count($qids) . '; materials=' . count($materials) . '.';
        }

        $details[] = 'VERIFIED_PARENT_SCOPE=3; remaining deferred or justified cases=8; no question-authoring permission is inferred.';
        return new CheckResult(
            title: 'Source Scope Adjudications',
            status: $errors === [] ? 'PASS' : 'FAIL',
            summary: $errors === []
                ? 'All 11 source-review records are classified; three CSE items are verified only to the official General Information parent and the remaining eight stay visible.'
                : count($errors) . ' source-scope evidence inconsistency(ies) detected.',
            details: array_slice(array_merge($details, $errors), 0, 40),
            recommendations: $errors === [] ? [] : ['Correct the cited source, board mapping, linked resource IDs, or evidence classification; do not relax the authoring gate.'],
            score: $errors === [] ? 100 : 0
        );
    }

    /** @return array<string, array<string, mixed>> */
    private static function index(array $rows, string $key): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row[$key])) continue;
            $indexed[(string) $row[$key]] = $row;
        }
        return $indexed;
    }

    /** @return \Generator<int, array<string, mixed>> */
    private static function streamQuestions(string $path): \Generator
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) throw new \RuntimeException('Unable to stream canonical questions.');
        try {
            $buffer = '';
            $depth = 0;
            $collecting = false;
            $insideString = false;
            $escaped = false;
            while (($line = fgets($handle)) !== false) {
                $length = strlen($line);
                for ($i = 0; $i < $length; $i++) {
                    $character = $line[$i];
                    if (!$collecting) {
                        if ($character === '{') {
                            $collecting = true;
                            $depth = 1;
                            $buffer = '{';
                            $insideString = false;
                            $escaped = false;
                        }
                        continue;
                    }
                    $buffer .= $character;
                    if ($insideString) {
                        if ($escaped) $escaped = false;
                        elseif ($character === '\\') $escaped = true;
                        elseif ($character === '"') $insideString = false;
                        continue;
                    }
                    if ($character === '"') {
                        $insideString = true;
                        continue;
                    }
                    if ($character === '{') $depth++;
                    elseif ($character === '}') {
                        $depth--;
                        if ($depth === 0) {
                            $question = json_decode($buffer, true, 512, JSON_THROW_ON_ERROR);
                            if (is_array($question)) yield $question;
                            $buffer = '';
                            $collecting = false;
                        }
                    }
                }
            }
            if ($collecting) throw new \RuntimeException('Truncated canonical question object.');
        } finally {
            fclose($handle);
        }
    }

    public function category(): string { return 'Content'; }
    public function priority(): int { return 14; }
}
