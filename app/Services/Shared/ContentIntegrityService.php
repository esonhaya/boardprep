<?php

declare(strict_types=1);

namespace App\Services\Shared;

use App\Core\App;
use App\Services\Flashcard\FlashcardDerivationService;
use App\Services\Question\StructuredContentService;

/**
 * The single structural content/readiness report used by developer tooling.
 *
 * This service deliberately works from one in-memory snapshot.  It is also
 * fixture-friendly so tests can exercise the same rules without writing to
 * repository storage.
 */
final class ContentIntegrityService
{
    private const BOARD_ALIASES = [
        'cse' => 'civil-service',
        'cle' => 'criminologist',
        'nle' => 'nursing',
        'ple' => 'psychometrician',
    ];

    public static function analyzeRepository(): array
    {
        $started = microtime(true);
        $collections = [];
        foreach ([
            'questions', 'boards', 'subjects', 'board-subjects',
            'taxonomy/domains', 'taxonomy/topics', 'taxonomy/concepts',
            'question-eligibility', 'concept-derived-eligibility',
            'question-reuse-overrides', 'question-reuse-metadata',
            'board-concept-coverage', 'study-materials', 'study-question-links',
            'sources', 'content-fit-audits', 'flashcard-exclusions',
        ] as $collection) {
            $collections[$collection] = App::storage()->all($collection);
        }

        // Give the production deriver the same immutable snapshot as the
        // integrity scan. This keeps the health command deterministic and
        // avoids a second question/study parse for every card.
        $deriver = new FlashcardDerivationService(
            null,
            $collections['study-materials'],
            $collections['study-question-links'],
            $collections['flashcard-exclusions']
        );
        $report = self::analyze(
            $collections,
            static fn(array $question): ?array => $deriver->derive($question),
            static fn(array $question): array => $deriver->derivationStatus($question)
        );
        $report['runtime']['integrity_runtime_ms'] = round((microtime(true) - $started) * 1000, 2);
        return $report;
    }

    /**
     * @param array<string, array> $collections
     * @param null|callable(array):(?array) $deriveOverride
     * @param null|callable(array):array $statusOverride
     */
    public static function analyze(array $collections, ?callable $deriveOverride = null, ?callable $statusOverride = null, ?callable $profileHook = null, ?callable $counterHook = null): array
    {
        $phaseStartedAt = $profileHook === null ? 0 : hrtime(true);
        $questions = self::arrays($collections['questions'] ?? []);
        $boards = self::arrays($collections['boards'] ?? []);
        $subjects = self::index($collections['subjects'] ?? []);
        $boardSubjects = self::arrays($collections['board-subjects'] ?? []);
        $domains = self::index($collections['taxonomy/domains'] ?? []);
        $topics = self::index($collections['taxonomy/topics'] ?? []);
        $concepts = self::index($collections['taxonomy/concepts'] ?? []);
        $sources = self::index($collections['sources'] ?? []);
        $materials = self::index($collections['study-materials'] ?? []);
        $links = self::arrays($collections['study-question-links'] ?? []);
        $eligibility = self::arrays($collections['question-eligibility'] ?? []);
        $duplicateRecordIds = [];
        foreach (['boards', 'subjects', 'board-subjects', 'taxonomy/domains', 'taxonomy/topics', 'taxonomy/concepts', 'question-eligibility', 'study-materials', 'study-question-links', 'sources'] as $collection) {
            foreach (self::duplicateIds($collections[$collection] ?? []) as $id) {
                $duplicateRecordIds[] = $collection . ':' . $id;
            }
        }
        $duplicateTaxonomyIds = array_values(array_filter($duplicateRecordIds, static fn(string $value): bool => str_starts_with($value, 'taxonomy/')));
        $duplicateHardIds = array_values(array_diff($duplicateRecordIds, $duplicateTaxonomyIds));
        if ($profileHook !== null) {
            $profileHook('integrity.shared_indexes', $phaseStartedAt, []);
            $phaseStartedAt = hrtime(true);
        }

        $questionIds = [];
        $duplicateQuestionIds = [];
        $questionsByBoard = [];
        $malformedQuestions = [];
        $invalidAnswers = [];
        $brokenAssets = [];
        $missingAlt = [];
        $invalidTaxonomy = [];
        $structuredContentFailures = [];

        foreach ($questions as $question) {
            $id = self::id($question);
            if ($id === '') {
                $duplicateQuestionIds[] = '<missing>';
                continue;
            }
            if (isset($questionIds[$id])) {
                $duplicateQuestionIds[] = $id;
            }
            $questionIds[$id] = true;
            $taxonomy = self::taxonomy($question);
            $boardId = self::boardId($taxonomy['board_id'] ?? $question['board'] ?? '');
            $questionsByBoard[$boardId][] = $id;
            $validation = QuestionValidationService::validate($question);
            if (!$validation['valid']) {
                $malformedQuestions[$id] = array_values(array_unique($validation['errors']));
            }
            if (self::answerInvalid($question)) {
                $invalidAnswers[] = $id;
            }
            foreach (StructuredContentService::validateDetailed($question) as $detail) {
                $structuredContentFailures[$id][] = $detail;
                $error = (string) ($detail['message'] ?? '');
                $key = str_contains(strtolower($error), 'alt') ? 'alt' : 'asset';
                if ($key === 'alt') {
                    $missingAlt[] = $id;
                } else {
                    $brokenAssets[] = $id;
                }
            }
            foreach (self::taxonomyErrors($taxonomy, $subjects, $domains, $topics, $concepts, $boardSubjects) as $error) {
                $invalidTaxonomy[$id][] = $error;
            }
        }
        if ($profileHook !== null) {
            $profileHook('integrity.question_validation_and_taxonomy', $phaseStartedAt, ['STRUCTURED_CONTENT']);
            $phaseStartedAt = hrtime(true);
        }

        $validBoardIds = [];
        foreach ($boards as $board) {
            $boardId = self::boardId($board['id'] ?? '');
            if ($boardId !== '') {
                $validBoardIds[$boardId] = true;
            }
        }

        $eligibilityByBoard = [];
        $invalidEligibility = [];
        $invalidEligibilityByBoard = [];
        foreach ($eligibility as $entry) {
            $entryId = self::id($entry) ?: '<missing>';
            $examIds = is_array($entry['exam_ids'] ?? null) ? $entry['exam_ids'] : [];
            $questionRefs = is_array($entry['question_ids'] ?? null) ? $entry['question_ids'] : [];
            $questionMappings = is_array($entry['question_mappings'] ?? null) ? $entry['question_mappings'] : [];
            foreach ($questionRefs as $questionRef) {
                $questionId = is_scalar($questionRef) ? trim((string) $questionRef) : '';
                $mapping = is_array($questionMappings[$questionId] ?? null) ? $questionMappings[$questionId] : [];
                $effectiveExamIds = is_array($mapping['exam_ids'] ?? null) ? $mapping['exam_ids'] : $examIds;
                foreach ($effectiveExamIds as $exam) {
                    $boardId = self::boardId($exam);
                    if (!isset($validBoardIds[$boardId])) {
                        $invalidEligibility[] = $entryId . ':unknown-board:' . $boardId;
                        continue;
                    }
                    if ($questionId === '' || !isset($questionIds[$questionId])) {
                        $invalidEligibility[] = $entryId . ':unknown-question:' . ($questionId ?: '<missing>');
                        $invalidEligibilityByBoard[$boardId][] = $entryId . ':unknown-question:' . ($questionId ?: '<missing>');
                        continue;
                    }
                    $eligibilityByBoard[$boardId][$questionId] = true;
                }
            }
        }
        if ($profileHook !== null) {
            $profileHook('integrity.eligibility_validation', $phaseStartedAt, ['ELIGIBILITY']);
            $phaseStartedAt = hrtime(true);
        }

        $sourceErrors = [];
        foreach ($sources as $sourceId => $source) {
            if ($sourceId === '' || trim((string) ($source['title'] ?? '')) === '' || trim((string) ($source['authority'] ?? '')) === '') {
                $sourceErrors[] = $sourceId ?: '<missing>';
            }
        }
        $materialSourceErrors = [];
        foreach ($materials as $materialId => $material) {
            foreach (is_array($material['source_ids'] ?? null) ? $material['source_ids'] : [] as $sourceId) {
                if (!isset($sources[(string) $sourceId])) {
                    $materialSourceErrors[] = $materialId . ':' . (string) $sourceId;
                }
            }
        }

        $validLinks = [];
        $invalidLinks = [];
        $duplicateLinks = [];
        $linksByQuestion = [];
        $linksByMaterial = [];
        foreach ($links as $link) {
            $linkId = self::id($link) ?: '<missing>';
            $materialId = (string) ($link['material_id'] ?? '');
            $questionId = (string) ($link['question_id'] ?? '');
            $recordErrors = [];
            if (!isset($materials[$materialId])) {
                $recordErrors[] = 'missing-material';
            }
            if (!isset($questionIds[$questionId])) {
                $recordErrors[] = 'missing-question';
            }
            foreach (is_array($link['source_ids'] ?? null) ? $link['source_ids'] : [] as $sourceId) {
                if (!isset($sources[(string) $sourceId])) {
                    $recordErrors[] = 'missing-source:' . (string) $sourceId;
                }
            }
            $key = $materialId . ':' . $questionId;
            if (isset($validLinks[$key])) {
                $duplicateLinks[] = $linkId . ':' . $key;
            }
            $validLinks[$key] = true;
            if ($recordErrors !== []) {
                $invalidLinks[$linkId] = $recordErrors;
            }
            if ($questionId !== '') {
                $linksByQuestion[$questionId][] = $link;
            }
            if ($materialId !== '') {
                $linksByMaterial[$materialId][] = $link;
            }
        }
        if ($profileHook !== null) {
            $profileHook('integrity.source_study_indexes', $phaseStartedAt, ['SOURCE_STUDY']);
            $phaseStartedAt = hrtime(true);
        }

        $orphanMaterials = [];
        $justifiedStandaloneMaterials = [];
        foreach ($materials as $materialId => $material) {
            if (($linksByMaterial[$materialId] ?? []) === []) {
                if (($material['standalone_resource'] ?? false) === true && trim((string) ($material['standalone_reason'] ?? '')) !== '') {
                    $justifiedStandaloneMaterials[] = $materialId;
                } else {
                    $orphanMaterials[] = $materialId;
                }
            }
        }

        $orphanTaxonomy = [];
        foreach ($domains as $id => $record) {
            if (!isset($subjects[(string) ($record['subject_id'] ?? '')])) {
                $orphanTaxonomy[] = 'domain:' . $id;
            }
        }
        foreach ($topics as $id => $record) {
            if (!isset($domains[(string) ($record['domain_id'] ?? '')])) {
                $orphanTaxonomy[] = 'topic:' . $id;
            }
        }
        foreach ($concepts as $id => $record) {
            if (!isset($topics[(string) ($record['topic_id'] ?? '')])) {
                $orphanTaxonomy[] = 'concept:' . $id;
            }
        }
        foreach ($boardSubjects as $relation) {
            $boardId = self::boardId($relation['board_id'] ?? '');
            $subjectId = (string) ($relation['subject_id'] ?? '');
            if (!isset($validBoardIds[$boardId]) || !isset($subjects[$subjectId])) {
                $orphanTaxonomy[] = 'board-subject:' . (self::id($relation) ?: $boardId . ':' . $subjectId);
            }
        }
        if ($profileHook !== null) {
            $profileHook('integrity.taxonomy_parent_validation', $phaseStartedAt, ['TAXONOMY']);
            $phaseStartedAt = hrtime(true);
        }

        $derive = $deriveOverride;
        $statusForQuestion = $statusOverride;
        if ($derive === null) {
            $deriver = App::container()->get(FlashcardDerivationService::class);
            $derive = static fn(array $question): ?array => $deriver->derive($question);
            $statusForQuestion = static fn(array $question): array => $deriver->derivationStatus($question);
        }
        $statusForQuestion ??= static fn(array $question): array => self::derivationStatus($question);
        $cardByKey = [];
        $derivableByQuestion = [];
        $justifiedExclusions = [];
        $justifiedExclusionRecords = [];
        $accidentalNonDerivable = [];
        $flashcardDerivationCalls = 0;
        $flashcardStatusCalls = 0;
        foreach ($questions as $question) {
            $questionId = self::id($question);
            if ($questionId === '') {
                continue;
            }
            if ($counterHook !== null) $flashcardDerivationCalls++;
            $card = $derive($question);
            if ($counterHook !== null) $flashcardStatusCalls++;
            $status = $statusForQuestion($question);
            if ($card === null) {
                if (!$status['derivable'] && $status['justified']) {
                    $justifiedExclusions[] = $questionId;
                    if (is_array($status['exclusion'] ?? null)) {
                        $justifiedExclusionRecords[] = $status['exclusion'];
                    } else {
                        // A validator/deriver failure is never an exclusion
                        // without an explicit registry record.
                        array_pop($justifiedExclusions);
                        $accidentalNonDerivable[] = $questionId;
                    }
                } else {
                    $accidentalNonDerivable[] = $questionId;
                }
                continue;
            }
            $derivableByQuestion[$questionId] = true;
            $key = (string) ($card['card_key'] ?? '');
            if ($key !== '') {
                $cardByKey[$key][] = $questionId;
            }
        }
        if ($counterHook !== null) {
            $counterHook('flashcard_derivation_calls', $flashcardDerivationCalls);
            $counterHook('flashcard_status_checks', $flashcardStatusCalls);
            $counterHook('duplicate_check_passes', 1);
        }
        $cardCollisions = [];
        foreach ($cardByKey as $key => $ids) {
            if (count(array_unique($ids)) > 1) {
                $cardCollisions[$key] = array_values(array_unique($ids));
            }
        }
        if ($profileHook !== null) {
            $profileHook('integrity.flashcard_derivation_and_keys', $phaseStartedAt, ['FLASHCARD_DERIVABILITY']);
            $phaseStartedAt = hrtime(true);
        }

        $boardResults = [];
        $portfolioDefects = [];
        $portfolioWarnings = [];
        foreach ($boards as $board) {
            $boardId = self::boardId($board['id'] ?? '');
            if ($boardId === '') {
                continue;
            }
            $owned = array_values(array_unique($questionsByBoard[$boardId] ?? []));
            $ownedSet = array_fill_keys($owned, true);
            $belongsToBoard = static fn(string $id): bool => isset($ownedSet[$id]);
            $eligible = array_keys($eligibilityByBoard[$boardId] ?? []);
            $missing = array_values(array_diff($owned, $eligible));
            $shared = array_values(array_diff($eligible, $owned));
            $boardLinks = [];
            foreach ($owned as $questionId) {
                foreach ($linksByQuestion[$questionId] ?? [] as $link) {
                    $material = $materials[(string) ($link['material_id'] ?? '')] ?? null;
                    if (is_array($material) && self::materialApplies($material, $boardId)) {
                        $boardLinks[] = $link;
                    }
                }
            }
            $linkedQuestionIds = array_values(array_unique(array_map(static fn(array $link): string => (string) ($link['question_id'] ?? ''), $boardLinks)));
            $unlinked = array_values(array_diff($owned, $linkedQuestionIds));
            $boardMaterials = array_filter($materials, static fn(array $material): bool => self::materialApplies($material, $boardId));
            $boardInvalidLinks = array_filter(array_keys($invalidLinks), static function (string $linkId) use ($links, $materials, $boardId): bool {
                foreach ($links as $link) {
                    if (self::id($link) !== $linkId) {
                        continue;
                    }
                    $material = $materials[(string) ($link['material_id'] ?? '')] ?? null;
                    return is_array($material) && self::materialApplies($material, $boardId);
                }
                return false;
            });
            $boardExclusionCount = 0;
            foreach ($justifiedExclusions as $id) {
                if (isset($ownedSet[$id])) $boardExclusionCount++;
            }
            $boardDerivableCount = 0;
            foreach ($derivableByQuestion as $id => $_) {
                if (isset($ownedSet[$id])) $boardDerivableCount++;
            }
            $boardInvalidAnswers = array_values(array_filter($invalidAnswers, $belongsToBoard));
            $boardCardKeyCollisions = [];
            foreach ($cardCollisions as $key => $ids) {
                $ownedCollisionCount = 0;
                foreach ($ids as $id) {
                    if (isset($ownedSet[$id])) $ownedCollisionCount++;
                }
                if ($ownedCollisionCount > 1) $boardCardKeyCollisions[] = $key;
            }
            $boardUniqueCardKeyCount = 0;
            foreach ($cardByKey as $ids) {
                foreach ($ids as $id) {
                    if (isset($ownedSet[$id])) {
                        $boardUniqueCardKeyCount++;
                        break;
                    }
                }
            }
            $ownedEligibleCount = 0;
            foreach ($eligible as $id) {
                if (isset($ownedSet[$id])) $ownedEligibleCount++;
            }
            $hard = [
                'missing_eligibility' => $missing,
                'invalid_eligibility' => array_values(array_unique($invalidEligibilityByBoard[$boardId] ?? [])),
                'unlinked_questions' => $unlinked,
                'invalid_study_links' => array_values($boardInvalidLinks),
                'duplicate_study_links' => $duplicateLinks,
                'invalid_taxonomy_references' => array_values(array_filter(array_keys($invalidTaxonomy), $belongsToBoard)),
                'orphan_taxonomy_nodes' => $orphanTaxonomy,
                'broken_structured_assets' => array_values(array_filter($brokenAssets, $belongsToBoard)),
                'structured_content_failures' => array_values(array_filter(array_keys($structuredContentFailures), $belongsToBoard)),
                'missing_alt_text' => array_values(array_filter($missingAlt, $belongsToBoard)),
                'invalid_answer_keys' => $boardInvalidAnswers,
                'malformed_questions' => array_values(array_filter(array_keys($malformedQuestions), $belongsToBoard)),
                'duplicate_question_ids' => array_values(array_filter($duplicateQuestionIds, $belongsToBoard)),
                'accidental_non_derivable' => array_values(array_filter($accidentalNonDerivable, $belongsToBoard)),
                'card_key_collisions' => $boardCardKeyCollisions,
            ];
            $hardCount = array_sum(array_map('count', $hard));
            $status = $hardCount > 0 ? 'FAIL' : ($boardExclusionCount > 0 ? 'PASS_WITH_JUSTIFIED_EXCLUSIONS' : 'PASS');
            $boardResults[$boardId] = [
                'board_id' => $boardId,
                'board_name' => (string) ($board['name'] ?? $boardId),
                'canonical_owned' => count($owned),
                'eligibility_references' => count($eligible),
                'owned_eligible' => $ownedEligibleCount,
                'missing_eligibility' => $missing,
                'shared_eligibility' => $shared,
                'invalid_eligibility' => $hard['invalid_eligibility'],
                'study_materials' => count($boardMaterials),
                'linked_questions' => count($linkedQuestionIds),
                'unlinked_questions' => $unlinked,
                'invalid_study_links' => $hard['invalid_study_links'],
                'duplicate_study_links' => $duplicateLinks,
                'orphan_study_materials' => array_values(array_intersect(array_keys($boardMaterials), $orphanMaterials)),
                'justified_standalone_study_materials' => array_values(array_intersect(array_keys($boardMaterials), $justifiedStandaloneMaterials)),
                'broken_source_references' => $materialSourceErrors,
                'taxonomy_coverage' => count($owned) === 0 ? 100 : round((count($owned) - count($hard['invalid_taxonomy_references'])) / count($owned) * 100, 1),
                'invalid_taxonomy_references' => $hard['invalid_taxonomy_references'],
                'orphan_taxonomy_nodes' => $orphanTaxonomy,
                'derivable_flashcards' => $boardDerivableCount,
                'justified_flashcard_exclusions' => $boardExclusionCount,
                'accidental_non_derivable' => $hard['accidental_non_derivable'],
                'unique_card_keys' => $boardUniqueCardKeyCount,
                'card_key_collisions' => $hard['card_key_collisions'],
                'broken_structured_assets' => $hard['broken_structured_assets'],
                'missing_alt_text' => $hard['missing_alt_text'],
                'invalid_answer_keys' => $boardInvalidAnswers,
                'duplicate_question_ids' => $hard['duplicate_question_ids'],
                'readiness_status' => $status,
                'defects' => $hard,
                'semantic_review_warnings' => [],
            ];
            if ($boardResults[$boardId]['orphan_study_materials'] !== []) {
                $boardResults[$boardId]['semantic_review_warnings'][] = 'Standalone study materials are not question-linked; review editorial intent.';
            }
            if ($boardResults[$boardId]['justified_standalone_study_materials'] !== []) {
                $boardResults[$boardId]['semantic_review_warnings'][] = 'Explicitly reviewed standalone study materials are retained as justified exclusions.';
            }
            if ($status === 'FAIL') {
                $portfolioDefects[$boardId] = $hard;
            }
        }
        if ($profileHook !== null) {
            $profileHook('integrity.board_reachability_aggregation', $phaseStartedAt, ['QUESTION_COUNTS']);
        }

        if ($orphanMaterials !== []) {
            $portfolioWarnings['standalone_study_materials'] = $orphanMaterials;
        }
        if ($justifiedStandaloneMaterials !== []) {
            $portfolioWarnings['justified_standalone_study_materials'] = $justifiedStandaloneMaterials;
        }
        if ($duplicateTaxonomyIds !== []) {
            $portfolioWarnings['duplicate_taxonomy_ids'] = $duplicateTaxonomyIds;
        }
        $globalHardDefects = $invalidEligibility !== []
            || $invalidLinks !== []
            || $duplicateLinks !== []
            || $sourceErrors !== []
            || $materialSourceErrors !== []
            || $invalidTaxonomy !== []
            || $orphanTaxonomy !== []
            || $brokenAssets !== []
            || $structuredContentFailures !== []
            || $missingAlt !== []
            || $invalidAnswers !== []
            || $malformedQuestions !== []
            || $duplicateQuestionIds !== []
            || $duplicateHardIds !== []
            || $accidentalNonDerivable !== []
            || $cardCollisions !== []
            || $portfolioDefects !== [];
        $portfolio = [
            'total_boards' => count($boardResults),
            'total_canonical' => count($questions),
            'total_eligible' => count(self::flattenUnique(array_map('array_keys', $eligibilityByBoard))),
            'total_missing_eligibility' => count(self::flattenUnique(array_map(static fn(array $board): array => $board['missing_eligibility'], $boardResults))),
            'total_invalid_eligibility' => count($invalidEligibility),
            'total_study_materials' => count($materials),
            'total_linked' => count(array_unique(array_keys($linksByQuestion))),
            'total_unlinked' => count(self::flattenUnique(array_map(static fn(array $board): array => $board['unlinked_questions'], $boardResults))),
            'total_invalid_links' => count($invalidLinks),
            'total_duplicate_links' => count($duplicateLinks),
            'total_orphan_materials' => count($orphanMaterials),
            'total_justified_standalone_study_materials' => count($justifiedStandaloneMaterials),
            'total_invalid_source_refs' => count(array_unique(array_merge($sourceErrors, $materialSourceErrors))),
            'total_invalid_taxonomy_refs' => count($invalidTaxonomy),
            'total_derivable_cards' => count($derivableByQuestion),
            'total_justified_exclusions' => count($justifiedExclusions),
            'justified_exclusions' => $justifiedExclusionRecords,
            'total_accidental_non_derivable' => count($accidentalNonDerivable),
            'total_card_key_collisions' => count($cardCollisions),
            'total_broken_assets' => count(array_unique($brokenAssets)),
            'total_invalid_answer_keys' => count(array_unique($invalidAnswers)),
            'total_duplicate_ids' => count(array_unique(array_merge($duplicateQuestionIds, $duplicateRecordIds))),
            'status' => $globalHardDefects ? 'FAIL' : (count($justifiedExclusions) > 0 ? 'PASS_WITH_JUSTIFIED_EXCLUSIONS' : 'PASS'),
        ];

        return [
            'generated_at' => gmdate(DATE_ATOM),
            'portfolio' => $portfolio,
            'boards' => $boardResults,
            'failures' => $portfolioDefects + [
                'invalid_links' => $invalidLinks,
                'invalid_taxonomy' => $invalidTaxonomy,
                'invalid_answers' => $invalidAnswers,
                'malformed_questions' => $malformedQuestions,
                'duplicate_ids' => $duplicateHardIds,
                'duplicate_question_ids' => $duplicateQuestionIds,
                'broken_assets' => $brokenAssets,
                'structured_content' => $structuredContentFailures,
                'structured_content_failures' => $structuredContentFailures,
                'missing_alt_text' => $missingAlt,
                'accidental_non_derivable' => $accidentalNonDerivable,
                'card_key_collisions' => $cardCollisions,
                'justified_exclusions' => $justifiedExclusionRecords,
            ],
            'warnings' => $portfolioWarnings,
            'semantic_review' => [
                'concept_fit' => 'Manual/editorial quality gate; structural traceability is automated.',
                'standalone_study_materials' => $orphanMaterials,
                'justified_standalone_study_materials' => $justifiedStandaloneMaterials,
            ],
            'runtime' => [
                'integrity_runtime_ms' => null,
                'question_reads' => 1,
                'eligibility_reads' => 1,
                'study_reads' => 2,
                'source_reads' => 1,
                'taxonomy_reads' => 5,
                'flashcard_derivation_passes' => count($questions),
                'n_plus_one' => false,
            ],
        ];
    }

    /** @return array{derivable:bool,justified:bool,reason:string,exclusion:?array} */
    public static function derivationStatus(array $question): array
    {
        $status = strtolower((string) ($question['status'] ?? 'active'));
        if ($status === 'archived' || !in_array($status, ['active', 'approved'], true)) {
            return ['derivable' => false, 'justified' => false, 'reason' => 'inactive', 'exclusion' => null];
        }
        if (!QuestionValidationService::validate($question)['valid']) {
            return ['derivable' => false, 'justified' => false, 'reason' => 'invalid-question', 'exclusion' => null];
        }
        if (!is_scalar($question['answer'] ?? null) || trim((string) $question['answer']) === '') {
            return ['derivable' => false, 'justified' => false, 'reason' => 'missing-answer', 'exclusion' => null];
        }
        $rationale = $question['explanation'] ?? $question['helpfulExplanation'] ?? '';
        if (!is_scalar($rationale) || trim((string) $rationale) === '') {
            return ['derivable' => false, 'justified' => false, 'reason' => 'missing-rationale', 'exclusion' => null];
        }
        if (!is_array($question['taxonomy'] ?? null) || trim((string) ($question['taxonomy']['concept_id'] ?? '')) === '') {
            return ['derivable' => false, 'justified' => false, 'reason' => 'missing-concept', 'exclusion' => null];
        }
        $intent = strtolower(trim((string) ($question['cognitive_intent'] ?? '')));
        if (in_array($intent, ['recall', 'remember', 'recognize', 'classify'], true)
            && ($question['multistep'] ?? false) !== true
            && ($question['data_interpretation'] ?? false) !== true
            && ($question['passage_dependent'] ?? false) !== true
            && ($question['content_blocks'] ?? []) === []) {
            return ['derivable' => false, 'justified' => false, 'reason' => 'low-value-intent', 'exclusion' => null];
        }
        return ['derivable' => true, 'justified' => false, 'reason' => '', 'exclusion' => null];
    }

    /** @return array<string, array> */
    private static function index(array $records): array
    {
        $index = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }
            $id = self::id($record);
            if ($id !== '' && !isset($index[$id])) {
                $index[$id] = $record;
            }
        }
        return $index;
    }

    /** @return array<int, array> */
    private static function arrays(array $records): array
    {
        return array_values(array_filter($records, 'is_array'));
    }

    private static function id(array $record): string
    {
        return is_scalar($record['id'] ?? null) ? trim((string) $record['id']) : '';
    }

    private static function boardId(mixed $value): string
    {
        $id = strtolower(trim((string) $value));
        return self::BOARD_ALIASES[$id] ?? $id;
    }

    private static function taxonomy(array $question): array
    {
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        foreach (['board', 'subject', 'domain', 'topic', 'concept'] as $field) {
            $key = $field . '_id';
            if (!isset($taxonomy[$key]) && isset($question[$field])) {
                $taxonomy[$key] = $question[$field];
            }
        }
        return $taxonomy;
    }

    private static function materialApplies(array $material, string $boardId): bool
    {
        $exams = is_array($material['exams'] ?? null) ? $material['exams'] : [];
        foreach ($exams as $exam) {
            if (self::boardId($exam) === $boardId) {
                return true;
            }
        }
        return false;
    }

    private static function answerInvalid(array $question): bool
    {
        $answer = trim((string) ($question['answer'] ?? ''));
        if (is_array($question['choices'] ?? null)) {
            return $answer === '' || !in_array($answer, array_map('strval', $question['choices']), true);
        }
        if (is_array($question['options'] ?? null)) {
            $correct = array_values(array_filter($question['options'], static fn(mixed $option): bool => is_array($option) && ($option['correct'] ?? false) === true));
            return $answer === '' || $correct === [];
        }
        return true;
    }

    private static function taxonomyErrors(array $taxonomy, array $subjects, array $domains, array $topics, array $concepts, array $boardSubjects): array
    {
        $errors = [];
        $board = self::boardId($taxonomy['board_id'] ?? '');
        $subject = (string) ($taxonomy['subject_id'] ?? '');
        $domain = (string) ($taxonomy['domain_id'] ?? '');
        $topic = (string) ($taxonomy['topic_id'] ?? '');
        $concept = (string) ($taxonomy['concept_id'] ?? '');
        if ($board === '' || $subject === '' || $domain === '' || $topic === '' || $concept === '') {
            $errors[] = 'missing-taxonomy';
        }
        if ($subject !== '' && !isset($subjects[$subject])) {
            $errors[] = 'invalid-subject';
        }
        if ($domain !== '' && (!isset($domains[$domain]) || (string) ($domains[$domain]['subject_id'] ?? '') !== $subject)) {
            $errors[] = 'invalid-domain';
        }
        if ($topic !== '' && (!isset($topics[$topic]) || (string) ($topics[$topic]['domain_id'] ?? '') !== $domain)) {
            $errors[] = 'invalid-topic';
        }
        if ($concept !== '' && (!isset($concepts[$concept]) || (string) ($concepts[$concept]['topic_id'] ?? '') !== $topic)) {
            $errors[] = 'invalid-concept';
        }
        $relation = false;
        foreach ($boardSubjects as $item) {
            if (self::boardId($item['board_id'] ?? '') === $board && (string) ($item['subject_id'] ?? '') === $subject) {
                $relation = true;
                break;
            }
        }
        if ($board !== '' && $subject !== '' && !$relation) {
            $errors[] = 'invalid-board-subject';
        }
        return array_values(array_unique($errors));
    }

    /** @param array<int, array<int, string>> $groups */
    private static function flattenUnique(array $groups): array
    {
        $flat = [];
        foreach ($groups as $group) {
            foreach ($group as $value) {
                $flat[(string) $value] = true;
            }
        }
        return array_keys($flat);
    }

    /** @return string[] */
    private static function duplicateIds(array $records): array
    {
        $seen = [];
        $duplicates = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }
            $id = self::id($record);
            if ($id === '') {
                continue;
            }
            if (isset($seen[$id])) {
                $duplicates[] = $id;
            }
            $seen[$id] = true;
        }
        return array_values(array_unique($duplicates));
    }
}
