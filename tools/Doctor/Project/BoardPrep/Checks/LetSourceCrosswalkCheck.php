<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;

/**
 * Checks the curated LET scope crosswalk against canonical questions,
 * materials, sources, and persisted question-resource links.
 * Editorial classifications are reported as review states; only broken or
 * stale references are deterministic failures.
 */
final class LetSourceCrosswalkCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $root = dirname(__DIR__, 5);
        $files = [
            'let-source-crosswalk-batch673' => $root . '/storage/let-source-crosswalk-batch673.json',
            'questions' => $root . '/storage/questions.json',
            'study-question-links' => $root . '/storage/study-question-links.json',
            'study-materials' => $root . '/storage/study-materials.json',
            'sources' => $root . '/storage/sources.json',
            'source-readiness-let' => $root . '/storage/source-readiness-let.json',
        ];
        $collections = [];
        $loadErrors = [];
        foreach ($files as $key => $path) {
            if (!is_file($path)) {
                $loadErrors[] = 'Missing required collection: ' . basename($path);
                continue;
            }
            try {
                $value = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                $loadErrors[] = 'Invalid JSON in ' . basename($path) . ': ' . $exception->getMessage();
                continue;
            }
            if (!is_array($value)) {
                $loadErrors[] = 'Expected a JSON collection in ' . basename($path);
                continue;
            }
            $collections[$key] = $value;
        }
        if ($loadErrors !== []) {
            return new CheckResult(
                title: 'LET Source Crosswalk',
                status: 'FAIL',
                summary: count($loadErrors) . ' crosswalk input error(s).',
                details: $loadErrors,
                recommendations: ['Restore the required LET crosswalk and source-traceability collections.'],
                score: 0,
            );
        }

        $report = $collections['let-source-crosswalk-batch673'];
        unset($collections['let-source-crosswalk-batch673']);
        $result = self::audit($report, $collections);
        $errors = $result['errors'];
        $warnings = $result['warnings'];
        $summary = $result['summary'];
        $status = $errors !== [] ? 'FAIL' : ($warnings !== [] ? 'WARNING' : 'PASS');
        $details = [sprintf(
            'LET crosswalks=%d; verified=%d; correctable=%d; deferred=%d; detector limitations=%d; target scope UNKNOWN=%d; all LET readiness UNKNOWN=%d.',
            $summary['crosswalks'],
            $summary['verified'],
            $summary['correctable'],
            $summary['deferred'],
            $summary['detector_limitations'],
            $summary['target_unknown'],
            $summary['all_let_unknown'],
        )];
        foreach ($errors as $error) $details[] = 'ERROR ' . $error;
        foreach ($warnings as $warning) $details[] = 'REVIEW ' . $warning;

        return new CheckResult(
            title: 'LET Source Crosswalk',
            status: $status,
            summary: $status === 'FAIL'
                ? sprintf('%d broken or stale LET crosswalk reference(s) require repair.', count($errors))
                : sprintf('%d LET competency/source crosswalks reconcile with canonical question and study-source data.', $summary['crosswalks']),
            details: array_slice($details, 0, 60),
            recommendations: $warnings,
            score: $errors === [] ? 100 : 0,
        );
    }

    /**
     * @param array<string, mixed> $report
     * @param array<string, array<int, array<string, mixed>>> $collections
     * @return array{errors: array<int, string>, warnings: array<int, string>, summary: array<string, int>}
     */
    public static function audit(array $report, array $collections): array
    {
        $errors = [];
        $warnings = [];
        $allowedClassifications = ['VERIFIED', 'CORRECTABLE', 'DEFERRED', 'DETECTOR_LIMITATION'];
        $evidenceCatalog = is_array($report['evidence_sources'] ?? null) ? $report['evidence_sources'] : [];
        foreach ([
            'sources' => 'id',
            'study-materials' => 'id',
            'source-readiness-let' => 'leaf_id',
        ] as $collectionName => $idField) {
            $seen = [];
            foreach ((array) ($collections[$collectionName] ?? []) as $record) {
                if (!is_array($record)) continue;
                $recordId = trim((string) ($record[$idField] ?? ''));
                if ($recordId === '') continue;
                if (isset($seen[$recordId])) $errors[] = $collectionName . ': duplicate ' . $idField . ' ' . $recordId . '.';
                $seen[$recordId] = true;
            }
        }
        $sources = self::index($collections['sources'] ?? []);
        $materials = self::index($collections['study-materials'] ?? []);
        $readiness = self::index($collections['source-readiness-let'] ?? [], 'leaf_id');
        $questionById = [];
        $questionsByLeaf = [];
        foreach ((array) ($collections['questions'] ?? []) as $question) {
            if (!is_array($question)) continue;
            $questionId = trim((string) ($question['id'] ?? ''));
            if ($questionId === '') continue;
            if (isset($questionById[$questionId])) {
                $errors[] = 'Duplicate canonical question ID ' . $questionId . '.';
            }
            $questionById[$questionId] = $question;
            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            if (strtolower((string) ($question['board'] ?? '')) === 'let'
                && strtolower((string) ($taxonomy['board_id'] ?? 'let')) === 'let'
                && trim((string) ($taxonomy['concept_id'] ?? '')) !== '') {
                $questionsByLeaf[(string) $taxonomy['concept_id']][] = $questionId;
            }
        }
        foreach ($questionsByLeaf as &$ids) self::sortIds($ids);
        unset($ids);

        $actualLinks = [];
        foreach ((array) ($collections['study-question-links'] ?? []) as $link) {
            if (!is_array($link)) continue;
            $key = self::linkKey((string) ($link['question_id'] ?? ''), (string) ($link['material_id'] ?? ''));
            if ($key === '|') continue;
            if (isset($actualLinks[$key])) $errors[] = 'Duplicate study-question link ' . $key . '.';
            $actualLinks[$key] = $link;
        }

        $rows = is_array($report['crosswalks'] ?? null) ? $report['crosswalks'] : [];
        $seenLeaves = [];
        $classificationCounts = array_fill_keys($allowedClassifications, 0);
        $targetUnknown = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                $errors[] = 'Crosswalk row is not an object.';
                continue;
            }
            $leafId = trim((string) ($row['leaf_id'] ?? ''));
            if ($leafId === '' || isset($seenLeaves[$leafId])) {
                $errors[] = 'Missing or duplicate crosswalk leaf ID ' . ($leafId ?: '(empty)') . '.';
                continue;
            }
            $seenLeaves[$leafId] = true;
            if (strtolower((string) ($row['board'] ?? '')) !== 'let') $errors[] = $leafId . ': board must be LET.';

            $classification = (string) ($row['classification'] ?? '');
            if (!in_array($classification, $allowedClassifications, true)) {
                $errors[] = $leafId . ': unsupported classification ' . ($classification ?: '(empty)') . '.';
            } else {
                $classificationCounts[$classification]++;
            }
            $record = $readiness[$leafId] ?? null;
            if (!is_array($record)) {
                $errors[] = $leafId . ': readiness record is missing.';
                continue;
            }
            if (($record['board_id'] ?? '') !== 'let') $errors[] = $leafId . ': readiness record is not LET-scoped.';
            if (($record['source_scope_fit'] ?? '') !== ($row['source_scope_fit_after'] ?? null)) {
                $errors[] = $leafId . ': report source-scope status is stale.';
            }
            if (array_values((array) ($row['material_ids'] ?? [])) !== array_values((array) ($record['study_material_ids'] ?? []))) {
                $errors[] = $leafId . ': report study-material IDs do not match readiness.';
            }
            if (($record['source_scope_fit'] ?? '') === 'UNKNOWN') $targetUnknown++;

            $expectedQuestionIds = $questionsByLeaf[$leafId] ?? [];
            $reportQuestionIds = array_map('strval', (array) ($row['question_ids'] ?? []));
            self::sortIds($reportQuestionIds);
            if ($reportQuestionIds !== $expectedQuestionIds) {
                $errors[] = $leafId . ': canonical question IDs/count do not match taxonomy.';
            }
            if ((int) ($row['question_count'] ?? -1) !== count($expectedQuestionIds)
                || (int) ($record['current_question_count'] ?? -1) !== count($expectedQuestionIds)) {
                $errors[] = $leafId . ': question count is stale.';
            }

            $materialIds = array_map('strval', (array) ($row['material_ids'] ?? []));
            $rowQuestionIds = array_fill_keys($expectedQuestionIds, true);
            $expectedReportLinks = [];
            foreach ($expectedQuestionIds as $questionId) {
                $hasMaterialPath = false;
                foreach ($materialIds as $materialId) {
                    $key = self::linkKey($questionId, $materialId);
                    if (isset($actualLinks[$key])) {
                        $expectedReportLinks[$key] = $actualLinks[$key];
                        $hasMaterialPath = true;
                    }
                }
                if (!$hasMaterialPath) $errors[] = $leafId . ': question ' . $questionId . ' has no linked material in its declared study pathway.';
            }
            foreach ($materialIds as $materialId) {
                if (!isset($materials[$materialId])) $errors[] = $leafId . ': missing material ' . $materialId . '.';
            }

            $reportedLinks = [];
            foreach ((array) ($row['question_links'] ?? []) as $reportedLink) {
                if (!is_array($reportedLink)) {
                    $errors[] = $leafId . ': question-link evidence is malformed.';
                    continue;
                }
                $questionId = (string) ($reportedLink['question_id'] ?? '');
                $materialId = (string) ($reportedLink['material_id'] ?? '');
                $key = self::linkKey($questionId, $materialId);
                if (isset($reportedLinks[$key])) $errors[] = $leafId . ': duplicate report link ' . $key . '.';
                $reportedLinks[$key] = true;
                if (!isset($rowQuestionIds[$questionId])) $errors[] = $leafId . ': link references question outside this concept: ' . $questionId . '.';
                if (!in_array($materialId, $materialIds, true)) $errors[] = $leafId . ': link references undeclared material ' . $materialId . '.';
                $actual = $actualLinks[$key] ?? null;
                if (!is_array($actual)) {
                    $errors[] = $leafId . ': dangling study-question link evidence ' . $key . '.';
                    continue;
                }
                $q = $questionById[$questionId] ?? null;
                if (!is_array($q) || strtolower((string) ($q['board'] ?? '')) !== 'let') {
                    $errors[] = $leafId . ': question link has no LET question ' . $questionId . '.';
                }
                $actualSources = array_map('strval', (array) ($actual['source_ids'] ?? []));
                $reportedSources = array_map('strval', (array) ($reportedLink['source_ids'] ?? []));
                sort($actualSources, SORT_STRING);
                sort($reportedSources, SORT_STRING);
                if ($actualSources !== $reportedSources) $errors[] = $leafId . ': source list is stale for ' . $key . '.';
                foreach ($actualSources as $sourceId) {
                    if (!isset($sources[$sourceId])) $errors[] = $leafId . ': unresolved source ' . $sourceId . '.';
                    if (isset($materials[$materialId]) && !in_array($sourceId, (array) ($materials[$materialId]['source_ids'] ?? []), true)) {
                        $errors[] = $leafId . ': linked source is not declared by material ' . $materialId . ': ' . $sourceId . '.';
                    }
                }
            }
            if (array_keys($reportedLinks) !== array_keys($expectedReportLinks)) {
                $a = array_keys($reportedLinks); $b = array_keys($expectedReportLinks);
                sort($a, SORT_STRING); sort($b, SORT_STRING);
                if ($a !== $b) $errors[] = $leafId . ': question-to-material path inventory is incomplete or stale.';
            }

            $expectedSources = array_fill_keys(array_map('strval', (array) ($record['source_ids'] ?? [])), true);
            foreach ((array) ($row['question_links'] ?? []) as $reportedLink) {
                foreach ((array) ($reportedLink['source_ids'] ?? []) as $sourceId) $expectedSources[(string) $sourceId] = true;
            }
            $expectedSourceIds = array_keys($expectedSources); sort($expectedSourceIds, SORT_STRING);
            $reportedSourceIds = array_map('strval', (array) ($row['source_ids'] ?? [])); sort($reportedSourceIds, SORT_STRING);
            if ($expectedSourceIds !== $reportedSourceIds) $errors[] = $leafId . ': source inventory is incomplete or stale.';
            foreach ($reportedSourceIds as $sourceId) {
                if (!isset($sources[$sourceId])) {
                    $errors[] = $leafId . ': unresolved crosswalk source ' . $sourceId . '.';
                    continue;
                }
                $catalogEntry = $evidenceCatalog[$sourceId] ?? null;
                if (!is_array($catalogEntry)) {
                    $errors[] = $leafId . ': crosswalk source is missing from evidence catalog ' . $sourceId . '.';
                    continue;
                }
                $source = $sources[$sourceId];
                if (($catalogEntry['id'] ?? null) !== $sourceId
                    || ($catalogEntry['title'] ?? null) !== ($source['title'] ?? null)
                    || ($catalogEntry['url'] ?? null) !== ($source['url'] ?? null)) {
                    $errors[] = $leafId . ': evidence catalog metadata is stale for ' . $sourceId . '.';
                }
                $scheme = strtolower((string) parse_url((string) ($source['url'] ?? ''), PHP_URL_SCHEME));
                if (!in_array($scheme, ['http', 'https'], true)) $errors[] = $leafId . ': source URL is not an absolute HTTP(S) URL for ' . $sourceId . '.';
            }
            foreach ((array) ($row['evidence'] ?? []) as $evidence) {
                $sourceId = (string) (is_array($evidence) ? ($evidence['source_id'] ?? '') : '');
                if ($sourceId === '' || !isset($sources[$sourceId])) $errors[] = $leafId . ': evidence source is missing or unresolved.';
                elseif (!isset($evidenceCatalog[$sourceId])) $errors[] = $leafId . ': evidence source is missing from evidence catalog ' . $sourceId . '.';
            }
            if (trim((string) ($row['recommended_action'] ?? '')) === '') $errors[] = $leafId . ': recommended action is missing.';

            if ($classification === 'DETECTOR_LIMITATION' || $classification === 'DEFERRED' || ($record['source_scope_fit'] ?? '') === 'UNKNOWN') {
                $warnings[] = $leafId . ' (' . $classification . '): ' . (string) ($row['recommended_action'] ?? 'Review the unresolved crosswalk.');
            }
        }

        $summary = [
            'crosswalks' => count($rows),
            'verified' => $classificationCounts['VERIFIED'],
            'correctable' => $classificationCounts['CORRECTABLE'],
            'deferred' => $classificationCounts['DEFERRED'],
            'detector_limitations' => $classificationCounts['DETECTOR_LIMITATION'],
            'target_unknown' => $targetUnknown,
            'all_let_unknown' => count(array_filter($readiness, static fn(array $row): bool => ($row['board_id'] ?? '') === 'let' && ($row['source_scope_fit'] ?? '') === 'UNKNOWN')),
        ];
        $reportedSummary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
        foreach (['target_crosswalks_after' => $summary['crosswalks'], 'target_unknown_after' => $summary['target_unknown'], 'let_scope_fit_unknown_after' => $summary['all_let_unknown']] as $key => $actual) {
            if ((int) ($reportedSummary[$key] ?? -1) !== $actual) $errors[] = 'Report summary is stale for ' . $key . '.';
        }
        $reportedClasses = (array) ($reportedSummary['classifications'] ?? []);
        foreach (['VERIFIED' => 'verified', 'CORRECTABLE' => 'correctable', 'DEFERRED' => 'deferred', 'DETECTOR_LIMITATION' => 'detector_limitations'] as $classification => $field) {
            if ((int) ($reportedClasses[$classification] ?? -1) !== $summary[$field]) $errors[] = 'Report classification total is stale for ' . $classification . '.';
        }
        if ($summary['crosswalks'] !== (int) ($reportedSummary['target_crosswalks_before'] ?? -1)) {
            $errors[] = 'Report target crosswalk count is inconsistent.';
        }

        $structuralUnknown = [];
        foreach ($readiness as $row) {
            if (($row['board_id'] ?? '') === 'let'
                && ($row['source_scope_fit'] ?? '') === 'UNKNOWN'
                && str_starts_with((string) ($row['leaf_id'] ?? ''), 'cse-')) {
                $structuralUnknown[] = (string) $row['leaf_id'];
            }
        }
        sort($structuralUnknown, SORT_STRING);
        $reportedStructural = array_map('strval', (array) ($reportedSummary['separate_structural_unknown_rows'] ?? []));
        sort($reportedStructural, SORT_STRING);
        if ($structuralUnknown !== $reportedStructural) $errors[] = 'Separate structural CSE placeholders are missing or stale.';
        if ($structuralUnknown !== []) $warnings[] = 'Separate structural CSE placeholders remain UNKNOWN and are outside this 35-row LET review: ' . implode(', ', $structuralUnknown) . '.';

        return ['errors' => array_values(array_unique($errors)), 'warnings' => array_values(array_unique($warnings)), 'summary' => $summary];
    }

    public function category(): string { return 'Content'; }
    public function priority(): int { return 26; }

    /** @param array<int, array<string, mixed>> $rows @return array<string, array<string, mixed>> */
    private static function index(array $rows, string $key = 'id'): array
    {
        $index = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '') $index[$value] = $row;
        }
        return $index;
    }

    private static function linkKey(string $questionId, string $materialId): string
    {
        return trim($questionId) . '|' . trim($materialId);
    }

    /** @param array<int, string> $ids */
    private static function sortIds(array &$ids): void
    {
        usort($ids, static function (string $a, string $b): int {
            if (ctype_digit($a) && ctype_digit($b)) return (int) $a <=> (int) $b;
            return strcmp($a, $b);
        });
    }
}
