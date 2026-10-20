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
        $analysis = [];
        $phaseStartedAt = $profileHook === null ? 0 : hrtime(true);

        self::indexCollections($collections, $analysis, $profileHook, $phaseStartedAt);
        self::validateQuestionRecords($analysis, $profileHook, $phaseStartedAt);
        self::validateEligibilityRecords($analysis, $profileHook, $phaseStartedAt);
        self::validateStudyRecords($analysis, $profileHook, $phaseStartedAt);
        self::validateTaxonomyParents($analysis, $profileHook, $phaseStartedAt);
        self::analyzeFlashcardDerivations(
            $analysis,
            $collections,
            $deriveOverride,
            $statusOverride,
            $counterHook,
            $profileHook,
            $phaseStartedAt
        );
        self::aggregateBoardResults($analysis, $profileHook, $phaseStartedAt);

        return self::integrityReport($analysis);
    }

    /**
     * @param array<string, array> $collections
     * @param array<string, mixed> $analysis
     */
    private static function indexCollections(
        array $collections,
        array &$analysis,
        ?callable $profileHook,
        int &$phaseStartedAt
    ): void {
        $analysis['questions'] = self::arrays($collections['questions'] ?? []);
        $analysis['boards'] = self::arrays($collections['boards'] ?? []);
        $analysis['subjects'] = self::index($collections['subjects'] ?? []);
        $analysis['board_subjects'] = self::arrays($collections['board-subjects'] ?? []);
        $analysis['domains'] = self::index($collections['taxonomy/domains'] ?? []);
        $analysis['topics'] = self::index($collections['taxonomy/topics'] ?? []);
        $analysis['concepts'] = self::index($collections['taxonomy/concepts'] ?? []);
        $analysis['sources'] = self::index($collections['sources'] ?? []);
        $analysis['materials'] = self::index($collections['study-materials'] ?? []);
        $analysis['links'] = self::arrays($collections['study-question-links'] ?? []);
        $analysis['eligibility'] = self::arrays($collections['question-eligibility'] ?? []);

        $duplicateRecordIds = [];
        foreach (['boards', 'subjects', 'board-subjects', 'taxonomy/domains', 'taxonomy/topics', 'taxonomy/concepts', 'question-eligibility', 'study-materials', 'study-question-links', 'sources'] as $collection) {
            foreach (self::duplicateIds($collections[$collection] ?? []) as $id) {
                $duplicateRecordIds[] = $collection . ':' . $id;
            }
        }
        $analysis['duplicate_record_ids'] = $duplicateRecordIds;
        $analysis['duplicate_taxonomy_ids'] = array_values(array_filter(
            $duplicateRecordIds,
            static fn(string $value): bool => str_starts_with($value, 'taxonomy/')
        ));
        $analysis['duplicate_hard_ids'] = array_values(array_diff(
            $duplicateRecordIds,
            $analysis['duplicate_taxonomy_ids']
        ));

        self::finishProfilePhase($profileHook, 'integrity.shared_indexes', $phaseStartedAt);
    }

    /** @param array<string, mixed> $analysis */
    private static function validateQuestionRecords(
        array &$analysis,
        ?callable $profileHook,
        int &$phaseStartedAt
    ): void {
        $analysis['question_ids'] = [];
        $analysis['duplicate_question_ids'] = [];
        $analysis['questions_by_board'] = [];
        $analysis['malformed_questions'] = [];
        $analysis['invalid_answers'] = [];
        $analysis['broken_assets'] = [];
        $analysis['missing_alt'] = [];
        $analysis['invalid_taxonomy'] = [];
        $analysis['structured_content_failures'] = [];

        foreach ($analysis['questions'] as $question) {
            $id = self::id($question);
            if ($id === '') {
                $analysis['duplicate_question_ids'][] = '<missing>';
                continue;
            }
            if (isset($analysis['question_ids'][$id])) {
                $analysis['duplicate_question_ids'][] = $id;
            }
            $analysis['question_ids'][$id] = true;
            $taxonomy = self::taxonomy($question);
            $boardId = self::boardId($taxonomy['board_id'] ?? $question['board'] ?? '');
            $analysis['questions_by_board'][$boardId][] = $id;

            $validation = QuestionValidationService::validate($question);
            if (!$validation['valid']) {
                $analysis['malformed_questions'][$id] = array_values(array_unique($validation['errors']));
            }
            if (self::answerInvalid($question)) {
                $analysis['invalid_answers'][] = $id;
            }
            foreach (StructuredContentService::validateDetailed($question) as $detail) {
                $analysis['structured_content_failures'][$id][] = $detail;
                $error = (string) ($detail['message'] ?? '');
                if (str_contains(strtolower($error), 'alt')) {
                    $analysis['missing_alt'][] = $id;
                } else {
                    $analysis['broken_assets'][] = $id;
                }
            }
            foreach (self::taxonomyErrors(
                $taxonomy,
                $analysis['subjects'],
                $analysis['domains'],
                $analysis['topics'],
                $analysis['concepts'],
                $analysis['board_subjects']
            ) as $error) {
                $analysis['invalid_taxonomy'][$id][] = $error;
            }
        }

        self::finishProfilePhase(
            $profileHook,
            'integrity.question_validation_and_taxonomy',
            $phaseStartedAt,
            ['STRUCTURED_CONTENT']
        );
    }

    /** @param array<string, mixed> $analysis */
    private static function validateEligibilityRecords(
        array &$analysis,
        ?callable $profileHook,
        int &$phaseStartedAt
    ): void {
        $analysis['valid_board_ids'] = [];
        foreach ($analysis['boards'] as $board) {
            $boardId = self::boardId($board['id'] ?? '');
            if ($boardId !== '') {
                $analysis['valid_board_ids'][$boardId] = true;
            }
        }

        $analysis['eligibility_by_board'] = [];
        $analysis['invalid_eligibility'] = [];
        $analysis['invalid_eligibility_by_board'] = [];
        foreach ($analysis['eligibility'] as $entry) {
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
                    if (!isset($analysis['valid_board_ids'][$boardId])) {
                        $analysis['invalid_eligibility'][] = $entryId . ':unknown-board:' . $boardId;
                        continue;
                    }
                    if ($questionId === '' || !isset($analysis['question_ids'][$questionId])) {
                        $analysis['invalid_eligibility'][] = $entryId . ':unknown-question:' . ($questionId ?: '<missing>');
                        $analysis['invalid_eligibility_by_board'][$boardId][] = $entryId . ':unknown-question:' . ($questionId ?: '<missing>');
                        continue;
                    }
                    $analysis['eligibility_by_board'][$boardId][$questionId] = true;
                }
            }
        }

        self::finishProfilePhase(
            $profileHook,
            'integrity.eligibility_validation',
            $phaseStartedAt,
            ['ELIGIBILITY']
        );
    }

    /** @param array<string, mixed> $analysis */
    private static function validateStudyRecords(
        array &$analysis,
        ?callable $profileHook,
        int &$phaseStartedAt
    ): void {
        self::validateStudySourceReferences($analysis);
        self::indexStudyQuestionLinks($analysis);

        self::finishProfilePhase(
            $profileHook,
            'integrity.source_study_indexes',
            $phaseStartedAt,
            ['SOURCE_STUDY']
        );
    }

    /** @param array<string, mixed> $analysis */
    private static function validateStudySourceReferences(array &$analysis): void
    {
        $analysis['source_errors'] = [];
        foreach ($analysis['sources'] as $sourceId => $source) {
            if ($sourceId === '' || trim((string) ($source['title'] ?? '')) === '' || trim((string) ($source['authority'] ?? '')) === '') {
                $analysis['source_errors'][] = $sourceId ?: '<missing>';
            }
        }

        $analysis['material_source_errors'] = [];
        foreach ($analysis['materials'] as $materialId => $material) {
            foreach (is_array($material['source_ids'] ?? null) ? $material['source_ids'] : [] as $sourceId) {
                if (!isset($analysis['sources'][(string) $sourceId])) {
                    $analysis['material_source_errors'][] = $materialId . ':' . (string) $sourceId;
                }
            }
        }
    }

    /** @param array<string, mixed> $analysis */
    private static function indexStudyQuestionLinks(array &$analysis): void
    {
        $analysis['valid_links'] = [];
        $analysis['invalid_links'] = [];
        $analysis['duplicate_links'] = [];
        $analysis['links_by_question'] = [];
        $analysis['links_by_material'] = [];
        foreach ($analysis['links'] as $link) {
            $linkId = self::id($link) ?: '<missing>';
            $materialId = (string) ($link['material_id'] ?? '');
            $questionId = (string) ($link['question_id'] ?? '');
            $recordErrors = [];
            if (!isset($analysis['materials'][$materialId])) {
                $recordErrors[] = 'missing-material';
            }
            if (!isset($analysis['question_ids'][$questionId])) {
                $recordErrors[] = 'missing-question';
            }
            foreach (is_array($link['source_ids'] ?? null) ? $link['source_ids'] : [] as $sourceId) {
                if (!isset($analysis['sources'][(string) $sourceId])) {
                    $recordErrors[] = 'missing-source:' . (string) $sourceId;
                }
            }
            $key = $materialId . ':' . $questionId;
            if (isset($analysis['valid_links'][$key])) {
                $analysis['duplicate_links'][] = $linkId . ':' . $key;
            }
            $analysis['valid_links'][$key] = true;
            if ($recordErrors !== []) {
                $analysis['invalid_links'][$linkId] = $recordErrors;
            }
            if ($questionId !== '') {
                $analysis['links_by_question'][$questionId][] = $link;
            }
            if ($materialId !== '') {
                $analysis['links_by_material'][$materialId][] = $link;
            }
        }
    }

    /** @param array<string, mixed> $analysis */
    private static function validateTaxonomyParents(
        array &$analysis,
        ?callable $profileHook,
        int &$phaseStartedAt
    ): void {
        $analysis['orphan_materials'] = [];
        $analysis['justified_standalone_materials'] = [];
        foreach ($analysis['materials'] as $materialId => $material) {
            if (($analysis['links_by_material'][$materialId] ?? []) !== []) {
                continue;
            }
            if (($material['standalone_resource'] ?? false) === true && trim((string) ($material['standalone_reason'] ?? '')) !== '') {
                $analysis['justified_standalone_materials'][] = $materialId;
            } else {
                $analysis['orphan_materials'][] = $materialId;
            }
        }

        $analysis['orphan_taxonomy'] = [];
        foreach ($analysis['domains'] as $id => $record) {
            if (!isset($analysis['subjects'][(string) ($record['subject_id'] ?? '')])) {
                $analysis['orphan_taxonomy'][] = 'domain:' . $id;
            }
        }
        foreach ($analysis['topics'] as $id => $record) {
            if (!isset($analysis['domains'][(string) ($record['domain_id'] ?? '')])) {
                $analysis['orphan_taxonomy'][] = 'topic:' . $id;
            }
        }
        foreach ($analysis['concepts'] as $id => $record) {
            if (!isset($analysis['topics'][(string) ($record['topic_id'] ?? '')])) {
                $analysis['orphan_taxonomy'][] = 'concept:' . $id;
            }
        }
        foreach ($analysis['board_subjects'] as $relation) {
            $boardId = self::boardId($relation['board_id'] ?? '');
            $subjectId = (string) ($relation['subject_id'] ?? '');
            if (!isset($analysis['valid_board_ids'][$boardId]) || !isset($analysis['subjects'][$subjectId])) {
                $analysis['orphan_taxonomy'][] = 'board-subject:' . (self::id($relation) ?: $boardId . ':' . $subjectId);
            }
        }

        self::finishProfilePhase($profileHook, 'integrity.taxonomy_parent_validation', $phaseStartedAt, ['TAXONOMY']);
    }

    /**
     * @param array<string, mixed> $analysis
     * @param array<string, array> $collections
     */
    private static function analyzeFlashcardDerivations(
        array &$analysis,
        array $collections,
        ?callable $deriveOverride,
        ?callable $statusOverride,
        ?callable $counterHook,
        ?callable $profileHook,
        int &$phaseStartedAt
    ): void {
        $derive = $deriveOverride;
        $statusForQuestion = $statusOverride;
        if ($derive === null) {
            // Keep derivation within the same immutable collection snapshot as
            // the integrity scan. The shared-library deriver resolves study
            // references with a full storage read for each question.
            $deriver = new FlashcardDerivationService(
                null,
                is_array($collections['study-materials'] ?? null) ? $collections['study-materials'] : [],
                is_array($collections['study-question-links'] ?? null) ? $collections['study-question-links'] : [],
                is_array($collections['flashcard-exclusions'] ?? null) ? $collections['flashcard-exclusions'] : []
            );
            $derive = static fn(array $question): ?array => $deriver->derive($question);
            $statusForQuestion = static fn(array $question): array => $deriver->derivationStatus($question);
        }
        $statusForQuestion ??= static fn(array $question): array => self::derivationStatus($question);

        $analysis['card_by_key'] = [];
        $analysis['derivable_by_question'] = [];
        $analysis['justified_exclusions'] = [];
        $analysis['justified_exclusion_records'] = [];
        $analysis['accidental_non_derivable'] = [];
        $flashcardDerivationCalls = 0;
        $flashcardStatusCalls = 0;
        foreach ($analysis['questions'] as $question) {
            $questionId = self::id($question);
            if ($questionId === '') {
                continue;
            }
            if ($counterHook !== null) $flashcardDerivationCalls++;
            $card = $derive($question);
            if ($card === null) {
                if ($counterHook !== null) $flashcardStatusCalls++;
                $status = $statusForQuestion($question);
                self::recordNonDerivableQuestion($analysis, $questionId, $status);
                continue;
            }

            $analysis['derivable_by_question'][$questionId] = true;
            $key = (string) ($card['card_key'] ?? '');
            if ($key !== '') {
                $analysis['card_by_key'][$key][] = $questionId;
            }
        }
        if ($counterHook !== null) {
            $counterHook('flashcard_derivation_calls', $flashcardDerivationCalls);
            $counterHook('flashcard_status_checks', $flashcardStatusCalls);
            $counterHook('duplicate_check_passes', 1);
        }

        $analysis['card_collisions'] = [];
        foreach ($analysis['card_by_key'] as $key => $ids) {
            if (count(array_unique($ids)) > 1) {
                $analysis['card_collisions'][$key] = array_values(array_unique($ids));
            }
        }

        self::finishProfilePhase(
            $profileHook,
            'integrity.flashcard_derivation_and_keys',
            $phaseStartedAt,
            ['FLASHCARD_DERIVABILITY']
        );
    }

    /** @param array<string, mixed> $analysis @param array<string, mixed> $status */
    private static function recordNonDerivableQuestion(array &$analysis, string $questionId, array $status): void
    {
        if (!$status['derivable'] && $status['justified']) {
            $analysis['justified_exclusions'][] = $questionId;
            if (is_array($status['exclusion'] ?? null)) {
                $analysis['justified_exclusion_records'][] = $status['exclusion'];
                return;
            }
            // A validator/deriver failure is never an exclusion without an explicit registry record.
            array_pop($analysis['justified_exclusions']);
        }
        $analysis['accidental_non_derivable'][] = $questionId;
    }

    /**
     * @param array<string, mixed> $analysis
     * @param array<string, mixed> $board
     * @return array{board:array<string,mixed>,defects:array<string,mixed>}|null
     */
    private static function boardResult(array $board, array $analysis): ?array
    {
        $boardId = self::boardId($board['id'] ?? '');
        if ($boardId === '') {
            return null;
        }

        $owned = array_values(array_unique($analysis['questions_by_board'][$boardId] ?? []));
        $ownedSet = array_fill_keys($owned, true);
        $belongsToBoard = static fn(string $id): bool => isset($ownedSet[$id]);
        $eligible = array_keys($analysis['eligibility_by_board'][$boardId] ?? []);
        $missing = array_values(array_diff($owned, $eligible));
        $shared = array_values(array_diff($eligible, $owned));
        $study = self::boardStudyMetrics($boardId, $owned, $analysis);
        $cards = self::boardFlashcardMetrics($ownedSet, $eligible, $analysis);
        $hard = self::boardDefects($boardId, $belongsToBoard, $study, $cards, $analysis);
        $status = array_sum(array_map('count', $hard)) > 0
            ? 'FAIL'
            : (($cards['excluded'] ?? 0) > 0 ? 'PASS_WITH_JUSTIFIED_EXCLUSIONS' : 'PASS');

        return [
            'board' => self::boardSummary($board, $boardId, $owned, $eligible, $missing, $shared, $study, $cards, $hard, $status, $analysis),
            'defects' => $hard,
        ];
    }

    /** @param array<string, mixed> $analysis @return array<string, mixed> */
    private static function boardStudyMetrics(string $boardId, array $owned, array $analysis): array
    {
        $boardLinks = [];
        foreach ($owned as $questionId) {
            foreach ($analysis['links_by_question'][$questionId] ?? [] as $link) {
                $material = $analysis['materials'][(string) ($link['material_id'] ?? '')] ?? null;
                if (is_array($material) && self::materialApplies($material, $boardId)) {
                    $boardLinks[] = $link;
                }
            }
        }
        $linkedQuestionIds = array_values(array_unique(array_map(
            static fn(array $link): string => (string) ($link['question_id'] ?? ''),
            $boardLinks
        )));
        $boardMaterials = array_filter(
            $analysis['materials'],
            static fn(array $material): bool => self::materialApplies($material, $boardId)
        );
        $boardInvalidLinks = array_filter(array_keys($analysis['invalid_links']), static function (string $linkId) use ($analysis, $boardId): bool {
            foreach ($analysis['links'] as $link) {
                if (self::id($link) !== $linkId) {
                    continue;
                }
                $material = $analysis['materials'][(string) ($link['material_id'] ?? '')] ?? null;
                return is_array($material) && self::materialApplies($material, $boardId);
            }
            return false;
        });

        return [
            'links' => $boardLinks,
            'linked_question_ids' => $linkedQuestionIds,
            'unlinked' => array_values(array_diff($owned, $linkedQuestionIds)),
            'materials' => $boardMaterials,
            'invalid_links' => array_values($boardInvalidLinks),
        ];
    }

    /** @param array<string, true> $ownedSet @param list<string> $eligible @param array<string, mixed> $analysis @return array<string, mixed> */
    private static function boardFlashcardMetrics(array $ownedSet, array $eligible, array $analysis): array
    {
        $excluded = 0;
        foreach ($analysis['justified_exclusions'] as $id) {
            if (isset($ownedSet[$id])) $excluded++;
        }
        $derivable = 0;
        foreach ($analysis['derivable_by_question'] as $id => $_) {
            if (isset($ownedSet[$id])) $derivable++;
        }
        $collisions = [];
        foreach ($analysis['card_collisions'] as $key => $ids) {
            $ownedCollisionCount = 0;
            foreach ($ids as $id) {
                if (isset($ownedSet[$id])) $ownedCollisionCount++;
            }
            if ($ownedCollisionCount > 1) $collisions[] = $key;
        }
        $uniqueCardKeys = 0;
        foreach ($analysis['card_by_key'] as $ids) {
            foreach ($ids as $id) {
                if (isset($ownedSet[$id])) {
                    $uniqueCardKeys++;
                    break;
                }
            }
        }
        $ownedEligible = 0;
        foreach ($eligible as $id) {
            if (isset($ownedSet[$id])) $ownedEligible++;
        }

        return [
            'excluded' => $excluded,
            'derivable' => $derivable,
            'collisions' => $collisions,
            'unique_card_keys' => $uniqueCardKeys,
            'owned_eligible' => $ownedEligible,
        ];
    }

    /**
     * @param callable(string):bool $belongsToBoard
     * @param array<string, mixed> $study
     * @param array<string, mixed> $cards
     * @param array<string, mixed> $analysis
     * @return array<string, array>
     */
    private static function boardDefects(
        string $boardId,
        callable $belongsToBoard,
        array $study,
        array $cards,
        array $analysis
    ): array {
        return [
            'missing_eligibility' => array_values(array_diff(
                array_values(array_unique($analysis['questions_by_board'][$boardId] ?? [])),
                array_keys($analysis['eligibility_by_board'][$boardId] ?? [])
            )),
            'invalid_eligibility' => array_values(array_unique($analysis['invalid_eligibility_by_board'][$boardId] ?? [])),
            'unlinked_questions' => $study['unlinked'],
            'invalid_study_links' => $study['invalid_links'],
            'duplicate_study_links' => $analysis['duplicate_links'],
            'invalid_taxonomy_references' => array_values(array_filter(array_keys($analysis['invalid_taxonomy']), $belongsToBoard)),
            'orphan_taxonomy_nodes' => $analysis['orphan_taxonomy'],
            'broken_structured_assets' => array_values(array_filter($analysis['broken_assets'], $belongsToBoard)),
            'structured_content_failures' => array_values(array_filter(array_keys($analysis['structured_content_failures']), $belongsToBoard)),
            'missing_alt_text' => array_values(array_filter($analysis['missing_alt'], $belongsToBoard)),
            'invalid_answer_keys' => array_values(array_filter($analysis['invalid_answers'], $belongsToBoard)),
            'malformed_questions' => array_values(array_filter(array_keys($analysis['malformed_questions']), $belongsToBoard)),
            'duplicate_question_ids' => array_values(array_filter($analysis['duplicate_question_ids'], $belongsToBoard)),
            'accidental_non_derivable' => array_values(array_filter($analysis['accidental_non_derivable'], $belongsToBoard)),
            'card_key_collisions' => $cards['collisions'],
        ];
    }

    /**
     * @param array<string, mixed> $board
     * @param list<string> $owned
     * @param list<string> $eligible
     * @param list<string> $missing
     * @param list<string> $shared
     * @param array<string, mixed> $study
     * @param array<string, mixed> $cards
     * @param array<string, array> $hard
     * @param array<string, mixed> $analysis
     * @return array<string, mixed>
     */
    private static function boardSummary(
        array $board,
        string $boardId,
        array $owned,
        array $eligible,
        array $missing,
        array $shared,
        array $study,
        array $cards,
        array $hard,
        string $status,
        array $analysis
    ): array {
        $boardMaterials = $study['materials'];
        $summary = [
            'board_id' => $boardId,
            'board_name' => (string) ($board['name'] ?? $boardId),
            'canonical_owned' => count($owned),
            'eligibility_references' => count($eligible),
            'owned_eligible' => $cards['owned_eligible'],
            'missing_eligibility' => $missing,
            'shared_eligibility' => $shared,
            'invalid_eligibility' => $hard['invalid_eligibility'],
            'study_materials' => count($boardMaterials),
            'linked_questions' => count($study['linked_question_ids']),
            'unlinked_questions' => $study['unlinked'],
            'invalid_study_links' => $study['invalid_links'],
            'duplicate_study_links' => $analysis['duplicate_links'],
            'orphan_study_materials' => array_values(array_intersect(array_keys($boardMaterials), $analysis['orphan_materials'])),
            'justified_standalone_study_materials' => array_values(array_intersect(array_keys($boardMaterials), $analysis['justified_standalone_materials'])),
            'broken_source_references' => $analysis['material_source_errors'],
            'taxonomy_coverage' => count($owned) === 0 ? 100 : round((count($owned) - count($hard['invalid_taxonomy_references'])) / count($owned) * 100, 1),
            'invalid_taxonomy_references' => $hard['invalid_taxonomy_references'],
            'orphan_taxonomy_nodes' => $analysis['orphan_taxonomy'],
            'derivable_flashcards' => $cards['derivable'],
            'justified_flashcard_exclusions' => $cards['excluded'],
            'accidental_non_derivable' => $hard['accidental_non_derivable'],
            'unique_card_keys' => $cards['unique_card_keys'],
            'card_key_collisions' => $cards['collisions'],
            'broken_structured_assets' => $hard['broken_structured_assets'],
            'missing_alt_text' => $hard['missing_alt_text'],
            'invalid_answer_keys' => $hard['invalid_answer_keys'],
            'duplicate_question_ids' => $hard['duplicate_question_ids'],
            'readiness_status' => $status,
            'defects' => $hard,
            'semantic_review_warnings' => [],
        ];
        self::addBoardSemanticWarnings($summary);

        return $summary;
    }

    /** @param array<string, mixed> $summary @param array<string, mixed> $analysis */
    private static function addBoardSemanticWarnings(array &$summary): void
    {
        if ($summary['orphan_study_materials'] !== []) {
            $summary['semantic_review_warnings'][] = 'Standalone study materials are not question-linked; review editorial intent.';
        }
        if ($summary['justified_standalone_study_materials'] !== []) {
            $summary['semantic_review_warnings'][] = 'Explicitly reviewed standalone study materials are retained as justified exclusions.';
        }
    }

    /** @param array<string, mixed> $analysis */
    private static function aggregateBoardResults(
        array &$analysis,
        ?callable $profileHook,
        int &$phaseStartedAt
    ): void {
        $analysis['board_results'] = [];
        $analysis['portfolio_defects'] = [];
        foreach ($analysis['boards'] as $board) {
            $result = self::boardResult($board, $analysis);
            if ($result === null) {
                continue;
            }
            $boardId = (string) $result['board']['board_id'];
            $analysis['board_results'][$boardId] = $result['board'];
            if ($result['board']['readiness_status'] === 'FAIL') {
                $analysis['portfolio_defects'][$boardId] = $result['defects'];
            }
        }

        self::finishProfilePhase(
            $profileHook,
            'integrity.board_reachability_aggregation',
            $phaseStartedAt,
            ['QUESTION_COUNTS']
        );
    }

    /** @param array<string, mixed> $analysis @return array<string, mixed> */
    private static function integrityReport(array $analysis): array
    {
        $portfolioWarnings = self::portfolioWarnings($analysis);
        $portfolio = self::portfolioSummary($analysis);
        return [
            'generated_at' => gmdate(DATE_ATOM),
            'portfolio' => $portfolio,
            'boards' => $analysis['board_results'],
            'failures' => self::failureSummary($analysis),
            'warnings' => $portfolioWarnings,
            'semantic_review' => [
                'concept_fit' => 'Manual/editorial quality gate; structural traceability is automated.',
                'standalone_study_materials' => $analysis['orphan_materials'],
                'justified_standalone_study_materials' => $analysis['justified_standalone_materials'],
            ],
            'runtime' => [
                'integrity_runtime_ms' => null,
                'question_reads' => 1,
                'eligibility_reads' => 1,
                'study_reads' => 2,
                'source_reads' => 1,
                'taxonomy_reads' => 5,
                'flashcard_derivation_passes' => count($analysis['questions']),
                'n_plus_one' => false,
            ],
        ];
    }

    /** @param array<string, mixed> $analysis @return array<string, mixed> */
    private static function portfolioSummary(array $analysis): array
    {
        $globalHardDefects = $analysis['invalid_eligibility'] !== []
            || $analysis['invalid_links'] !== []
            || $analysis['duplicate_links'] !== []
            || $analysis['source_errors'] !== []
            || $analysis['material_source_errors'] !== []
            || $analysis['invalid_taxonomy'] !== []
            || $analysis['orphan_taxonomy'] !== []
            || $analysis['broken_assets'] !== []
            || $analysis['structured_content_failures'] !== []
            || $analysis['missing_alt'] !== []
            || $analysis['invalid_answers'] !== []
            || $analysis['malformed_questions'] !== []
            || $analysis['duplicate_question_ids'] !== []
            || $analysis['duplicate_hard_ids'] !== []
            || $analysis['accidental_non_derivable'] !== []
            || $analysis['card_collisions'] !== []
            || $analysis['portfolio_defects'] !== [];

        return [
            'total_boards' => count($analysis['board_results']),
            'total_canonical' => count($analysis['questions']),
            'total_eligible' => count(self::flattenUnique(array_map('array_keys', $analysis['eligibility_by_board']))),
            'total_missing_eligibility' => count(self::flattenUnique(array_map(
                static fn(array $board): array => $board['missing_eligibility'],
                $analysis['board_results']
            ))),
            'total_invalid_eligibility' => count($analysis['invalid_eligibility']),
            'total_study_materials' => count($analysis['materials']),
            'total_linked' => count(array_unique(array_keys($analysis['links_by_question']))),
            'total_unlinked' => count(self::flattenUnique(array_map(
                static fn(array $board): array => $board['unlinked_questions'],
                $analysis['board_results']
            ))),
            'total_invalid_links' => count($analysis['invalid_links']),
            'total_duplicate_links' => count($analysis['duplicate_links']),
            'total_orphan_materials' => count($analysis['orphan_materials']),
            'total_justified_standalone_study_materials' => count($analysis['justified_standalone_materials']),
            'total_invalid_source_refs' => count(array_unique(array_merge($analysis['source_errors'], $analysis['material_source_errors']))),
            'total_invalid_taxonomy_refs' => count($analysis['invalid_taxonomy']),
            'total_derivable_cards' => count($analysis['derivable_by_question']),
            'total_justified_exclusions' => count($analysis['justified_exclusions']),
            'justified_exclusions' => $analysis['justified_exclusion_records'],
            'total_accidental_non_derivable' => count($analysis['accidental_non_derivable']),
            'total_card_key_collisions' => count($analysis['card_collisions']),
            'total_broken_assets' => count(array_unique($analysis['broken_assets'])),
            'total_invalid_answer_keys' => count(array_unique($analysis['invalid_answers'])),
            'total_duplicate_ids' => count(array_unique(array_merge(
                $analysis['duplicate_question_ids'],
                $analysis['duplicate_record_ids']
            ))),
            'status' => $globalHardDefects
                ? 'FAIL'
                : (count($analysis['justified_exclusions']) > 0 ? 'PASS_WITH_JUSTIFIED_EXCLUSIONS' : 'PASS'),
        ];
    }

    /** @param array<string, mixed> $analysis @return array<string, mixed> */
    private static function failureSummary(array $analysis): array
    {
        return $analysis['portfolio_defects'] + [
            'invalid_links' => $analysis['invalid_links'],
            'invalid_taxonomy' => $analysis['invalid_taxonomy'],
            'invalid_answers' => $analysis['invalid_answers'],
            'malformed_questions' => $analysis['malformed_questions'],
            'duplicate_ids' => $analysis['duplicate_hard_ids'],
            'duplicate_question_ids' => $analysis['duplicate_question_ids'],
            'broken_assets' => $analysis['broken_assets'],
            'structured_content' => $analysis['structured_content_failures'],
            'structured_content_failures' => $analysis['structured_content_failures'],
            'missing_alt_text' => $analysis['missing_alt'],
            'accidental_non_derivable' => $analysis['accidental_non_derivable'],
            'card_key_collisions' => $analysis['card_collisions'],
            'justified_exclusions' => $analysis['justified_exclusion_records'],
        ];
    }

    /** @param array<string, mixed> $analysis @return array<string, array> */
    private static function portfolioWarnings(array $analysis): array
    {
        $warnings = [];
        if ($analysis['orphan_materials'] !== []) {
            $warnings['standalone_study_materials'] = $analysis['orphan_materials'];
        }
        if ($analysis['justified_standalone_materials'] !== []) {
            $warnings['justified_standalone_study_materials'] = $analysis['justified_standalone_materials'];
        }
        if ($analysis['duplicate_taxonomy_ids'] !== []) {
            $warnings['duplicate_taxonomy_ids'] = $analysis['duplicate_taxonomy_ids'];
        }
        return $warnings;
    }

    private static function finishProfilePhase(
        ?callable $profileHook,
        string $operation,
        int &$phaseStartedAt,
        array $checkCodes = []
    ): void {
        if ($profileHook === null) {
            return;
        }
        $profileHook($operation, $phaseStartedAt, $checkCodes);
        $phaseStartedAt = hrtime(true);
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
