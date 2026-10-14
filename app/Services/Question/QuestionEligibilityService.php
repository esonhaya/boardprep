<?php

declare(strict_types=1);

namespace App\Services\Question;

use App\Core\App;
use App\Services\Shared\TaxonomyStorageService;
use App\Services\Quiz\LET\LETVariantRegistry;

final class QuestionEligibilityService
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $metadataIndex = null;

    /**
     * Return the effective board placements used by the admin editor.
     *
     * Eligibility records may still be grouped for storage.  This method
     * presents the question-level result without exposing that storage detail
     * to the editor.
     */
    public static function editorMappings(array $question): array
    {
        $id = (string) ($question['id'] ?? '');
        $mappings = [];
        $excluded = [];
        $manualIncludes = [];
        foreach (App::storage()->all('question-reuse-overrides') as $override) {
            if (!is_array($override) || (string) ($override['question_id'] ?? '') !== $id) {
                continue;
            }
            $board = self::normalize((string) ($override['board_id'] ?? ''));
            if (($override['override_type'] ?? '') === 'EXCLUDE') {
                $excluded[$board] = true;
            } elseif (($override['override_type'] ?? '') === 'INCLUDE') {
                $manualIncludes[$board] = $override;
            }
        }

        foreach (App::storage()->all('question-eligibility') as $entry) {
            if (!is_array($entry) || !self::containsId($entry['question_ids'] ?? [], $id)) {
                continue;
            }

            $override = is_array($entry['question_mappings'][$id] ?? null)
                ? $entry['question_mappings'][$id]
                : [];
            $examIds = is_array($override['exam_ids'] ?? null)
                ? $override['exam_ids']
                : ($entry['exam_ids'] ?? []);
            $subjectIds = is_array($override['subject_ids'] ?? null)
                ? $override['subject_ids']
                : ($entry['subject_ids'] ?? []);
            $placements = is_array($override['placements'] ?? null)
                ? $override['placements']
                : [];

            foreach ($examIds as $examId) {
                if (!is_scalar($examId)) {
                    continue;
                }
                $boardId = self::normalize((string) $examId);
                if ($boardId === '' || isset($mappings[$boardId]) || isset($excluded[$boardId])) {
                    continue;
                }
                $placement = is_array($placements[$boardId] ?? null)
                    ? $placements[$boardId]
                    : [];
                $taxonomy = is_array($question['taxonomy'] ?? null)
                    ? $question['taxonomy']
                    : [];
                $isCanonicalBoard = self::normalize((string) ($taxonomy['board_id'] ?? $question['board'] ?? '')) === $boardId;
                $subjectId = self::scalarString($placement['subject_id'] ?? ($subjectIds[$examId] ?? $subjectIds[$boardId] ?? ''));
                if ($subjectId === '' && $isCanonicalBoard) {
                    $subjectId = self::scalarString($taxonomy['subject_id'] ?? $question['subject_id'] ?? '');
                }
                $mapping = self::editorMapping(
                    $boardId,
                    $subjectId,
                    $placement,
                    $isCanonicalBoard ? $taxonomy : []
                );
                if ($boardId === 'let' && is_array($override['variant_placements']['let'] ?? null)) {
                    $mapping['variant_placements'] = $override['variant_placements']['let'];
                }
                $mappings[$boardId] = $mapping;
            }
        }

        if ($mappings === []) {
            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            $boardId = self::normalize((string) ($taxonomy['board_id'] ?? $question['board'] ?? ''));
            if ($boardId !== '' && !isset($excluded[$boardId]) && !isset($mappings[$boardId])) {
                $mappings[$boardId] = self::editorMapping(
                    $boardId,
                    (string) ($taxonomy['subject_id'] ?? $question['subject_id'] ?? ''),
                    [],
                    $taxonomy
                );
            }
        }

        foreach ($manualIncludes as $boardId => $override) {
            if (isset($excluded[$boardId]) || QuestionReuseEligibilityResolver::validateOverride($override) !== []) {
                continue;
            }
            $mapping = self::editorMapping(
                $boardId,
                (string) (($override['placement']['subject_id'] ?? '')),
                is_array($override['placement'] ?? null) ? $override['placement'] : [],
                []
            );
            $mapping['eligibility_source'] = 'MANUAL_INCLUDE';
            $mappings[$boardId] = $mapping;
        }

        foreach (QuestionReuseEligibilityResolver::productionDerivedRelationships() as $relationship) {
            if ((string) ($relationship['question_id'] ?? '') !== $id) {
                continue;
            }
            $boardId = self::normalize((string) ($relationship['target_board'] ?? ''));
            if ($boardId === '' || isset($excluded[$boardId]) || isset($mappings[$boardId])) {
                continue;
            }
            $placement = is_array($relationship['target_placement'] ?? null) ? $relationship['target_placement'] : [];
            $mapping = self::editorMapping(
                $boardId,
                (string) ($placement['subject_id'] ?? ''),
                $placement,
                []
            );
            $mapping['eligibility_source'] = 'CONCEPT_INHERITED';
            $mapping['relationship_id'] = (string) ($relationship['id'] ?? '');
            $mappings[$boardId] = $mapping;
        }

        return array_values($mappings);
    }

    /**
     * Data for the board-aware editor selectors.  The taxonomy records are
     * the same records used by authoring and validation; no editor taxonomy
     * is maintained here.
     */
    public static function editorOptions(array $question = []): array
    {
        $existing = [];
        foreach (self::editorMappings($question) as $mapping) {
            $existing[(string) ($mapping['board_id'] ?? '')] = true;
        }

        $boards = [];
        foreach (TaxonomyStorageService::boards() as $board) {
            if (!is_array($board) || !is_scalar($board['id'] ?? null)) {
                continue;
            }
            $id = self::normalize((string) $board['id']);
            $boards[] = [
                'id' => $id,
                'name' => (string) ($board['name'] ?? $id),
                'eligible' => isset($existing[$id]),
            ];
        }

        $subjects = [];
        foreach (TaxonomyStorageService::boardSubjects() as $relation) {
            if (!is_array($relation) || !is_scalar($relation['board_id'] ?? null) || !is_scalar($relation['subject_id'] ?? null)) {
                continue;
            }
            $boardId = self::normalize((string) $relation['board_id']);
            $subjectId = (string) $relation['subject_id'];
            $subjects[] = [
                'board_id' => $boardId,
                'id' => $subjectId,
                'name' => TaxonomyStorageService::label('subjects', $subjectId),
            ];
        }

        $blueprintNodes = [];
        foreach ($boards as $board) {
            $blueprintNodes[$board['id']] = self::blueprintNodes($board['id']);
        }

        return [
            'boards' => $boards,
            'subjects' => $subjects,
            'blueprint_nodes' => $blueprintNodes,
            'domains' => TaxonomyStorageService::domains(),
            'topics' => TaxonomyStorageService::topics(),
            'concepts' => TaxonomyStorageService::concepts(),
            'let_specializations' => LETVariantRegistry::specializations(),
        ];
    }

    /**
     * Deterministic validation for an admin-selected board placement.
     * Semantic academic judgment remains with the administrator.
     */
    public static function validateMapping(array $question, array $input, bool $allowExistingBoard = false): array
    {
        $boardId = self::normalize(self::inputString($input, ['target_board', 'board']));
        $subjectId = self::inputString($input, ['target_subject', 'subject']);
        $domainId = self::inputString($input, ['target_domain', 'domain']);
        $topicId = self::inputString($input, ['target_topic', 'topic']);
        $conceptId = self::inputString($input, ['target_concept', 'concept']);
        $blueprintNode = self::inputString($input, ['blueprint_node', 'target_blueprint_node']);
        $letLevel = strtoupper(self::inputString($input, ['let_level', 'target_let_level']));
        $letComponent = strtoupper(self::inputString($input, ['let_component', 'target_let_component']));
        $letSpecializationInput = self::inputString($input, ['let_specialization_id', 'target_specialization_id', 'let_specialization']);
        $letSpecialization = $letSpecializationInput === '' ? null : LETVariantRegistry::resolveSpecialization($letSpecializationInput);
        $errors = [];

        if ($boardId === '' || TaxonomyStorageService::find('boards', $boardId) === null) {
            $errors['INVALID_BOARD'] = 'Select a configured target board.';
        }
        if ($subjectId === '' || TaxonomyStorageService::find('subjects', $subjectId) === null) {
            $errors['INVALID_SUBJECT_FOR_BOARD'] = 'Select a valid subject for the target board.';
        } elseif ($boardId !== '' && !TaxonomyStorageService::hasBoardSubject($boardId, $subjectId)) {
            $errors['INVALID_SUBJECT_FOR_BOARD'] = 'The selected subject is not configured for this board.';
        }

        $domain = $domainId === '' ? null : TaxonomyStorageService::find('taxonomy/domains', $domainId);
        $topic = $topicId === '' ? null : TaxonomyStorageService::find('taxonomy/topics', $topicId);
        $concept = $conceptId === '' ? null : TaxonomyStorageService::find('taxonomy/concepts', $conceptId);
        if ($domainId !== '' && $domain === null) {
            $errors['INVALID_TOPIC_FOR_BOARD'] = 'The selected domain is not a configured taxonomy node.';
        } elseif ($domain !== null && (string) ($domain['subject_id'] ?? '') !== $subjectId) {
            $errors['INVALID_TOPIC_FOR_BOARD'] = 'The selected domain is outside the target subject.';
        }
        if ($topicId !== '' && $topic === null) {
            $errors['INVALID_CONCEPT_FOR_BOARD'] = 'The selected topic is not a configured taxonomy node.';
        } elseif ($topic !== null && (string) ($topic['domain_id'] ?? '') !== $domainId) {
            $errors['INVALID_CONCEPT_FOR_BOARD'] = 'The selected topic is outside the selected domain.';
        }
        if ($conceptId !== '' && $concept === null) {
            $errors['INVALID_CONCEPT_FOR_BOARD'] = 'The selected concept is not a configured taxonomy node.';
        } elseif ($concept !== null && (string) ($concept['topic_id'] ?? '') !== $topicId) {
            $errors['INVALID_CONCEPT_FOR_BOARD'] = 'The selected concept is outside the selected topic.';
        }

        if ($blueprintNode === '') {
            $blueprintNode = $conceptId !== '' ? $conceptId : ($domainId !== '' ? $domainId : $subjectId);
        }
        if ($boardId === 'let' && ($letLevel !== '' || $letComponent !== '' || $letSpecializationInput !== '')) {
            if ($letSpecialization !== null && $letLevel === '') {
                $letLevel = strtoupper((string) ($letSpecialization['level'] ?? ''));
            }
            if ($letSpecialization !== null && $letComponent === '') {
                $letComponent = LETVariantRegistry::SPECIALIZATION;
            }
            $variantValidation = LETVariantRegistry::validateConfiguration([
                'level' => $letLevel,
                'component' => $letComponent === '' ? null : $letComponent,
                'specialization_id' => $letSpecializationInput,
            ]);
            if (($variantValidation['valid'] ?? false) !== true) {
                foreach (($variantValidation['errors'] ?? []) as $code => $message) {
                    $errors[$code] = $message;
                }
            }
            if ($letSpecialization !== null) {
                $letSpecializationInput = (string) ($letSpecialization['id'] ?? '');
            }
        }
        if ($boardId !== '' && !self::blueprintContains($boardId, $blueprintNode, $subjectId)) {
            $errors['INVALID_BLUEPRINT_PLACEMENT'] = 'The selected placement is not part of the target board blueprint.';
        }

        $existingBoards = array_map(
            static fn(array $mapping): string => (string) ($mapping['board_id'] ?? ''),
            self::editorMappings($question)
        );
        $storedBoards = self::storedBoardMappings($question);
        if (!$allowExistingBoard && in_array($boardId, $existingBoards, true) && in_array($boardId, $storedBoards, true)) {
            $errors['DUPLICATE_BOARD_MAPPING'] = 'This question is already shared with the selected board.';
        }
        if ($allowExistingBoard && !in_array($boardId, $existingBoards, true)) {
            $errors['MISSING_REQUIRED_MAPPING_METADATA'] = 'The question has no mapping for the selected board.';
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'mapping' => [
                'board_id' => $boardId,
                'subject_id' => $subjectId,
                'domain_id' => $domainId,
                'topic_id' => $topicId,
                'concept_id' => $conceptId,
                'blueprint_node' => $blueprintNode,
                'let_level' => $letLevel === '' ? null : $letLevel,
                'let_component' => $letComponent === '' ? null : $letComponent,
                'specialization_id' => $letSpecializationInput === '' ? null : $letSpecializationInput,
            ],
        ];
    }

    public static function addMapping(array $question, array $input): array
    {
        $validation = self::validateMapping($question, $input);
        if (($validation['valid'] ?? false) !== true) {
            return $validation;
        }
        self::writeQuestionMapping($question, $validation['mapping'], false);
        return $validation;
    }

    public static function updateMapping(array $question, array $input): array
    {
        $validation = self::validateMapping($question, $input, true);
        if (($validation['valid'] ?? false) !== true) {
            return $validation;
        }
        self::writeQuestionMapping($question, $validation['mapping'], true);
        return $validation;
    }

    public static function removeMapping(array $question, string $boardId): array
    {
        $boardId = self::normalize($boardId);
        $mappings = self::editorMappings($question);
        $matching = array_values(array_filter(
            $mappings,
            static fn(array $mapping): bool => ($mapping['board_id'] ?? '') === $boardId
        ));
        if ($matching === []) {
            return ['valid' => false, 'errors' => ['INVALID_BOARD' => 'The question is not eligible for that board.']];
        }
        if (($matching[0]['eligibility_source'] ?? '') === 'CONCEPT_INHERITED') {
            $override = [
                'id' => 'override-' . hash('sha256', (string) ($question['id'] ?? '') . '|' . $boardId),
                'question_id' => (string) ($question['id'] ?? ''),
                'board_id' => $boardId,
                'override_type' => 'EXCLUDE',
                'reason' => 'Admin removed bounded concept-derived eligibility for this board.',
                'provenance' => [[
                    'type' => 'MANUAL_REVIEW',
                    'reference' => 'batch-610-inherited-exclude',
                    'note' => 'This exclusion overrides the bounded inherited relationship without changing board coverage.',
                    'verified_status' => 'VERIFIED',
                ]],
            ];
            return QuestionReuseEligibilityResolver::saveOverride($override);
        }
        if (count($mappings) <= 1) {
            return ['valid' => false, 'errors' => ['LAST_BOARD_UNSHARE_PROTECTED' => 'This is the question\'s only board eligibility. Archive or delete the question instead.']];
        }

        $id = (string) ($question['id'] ?? '');
        $records = App::storage()->all('question-eligibility');
        foreach ($records as &$entry) {
            if (!is_array($entry) || !self::containsId($entry['question_ids'] ?? [], $id)) {
                continue;
            }
            $override = is_array($entry['question_mappings'][$id] ?? null)
                ? $entry['question_mappings'][$id]
                : [];
            $effectiveExams = is_array($override['exam_ids'] ?? null) ? $override['exam_ids'] : ($entry['exam_ids'] ?? []);
            $effectiveSubjects = is_array($override['subject_ids'] ?? null) ? $override['subject_ids'] : ($entry['subject_ids'] ?? []);
            $effectiveExams = array_values(array_filter($effectiveExams, static fn(mixed $value): bool => self::normalize((string) $value) !== $boardId));
            foreach (array_keys($effectiveSubjects) as $subjectBoard) {
                if (self::normalize((string) $subjectBoard) === $boardId) {
                    unset($effectiveSubjects[$subjectBoard]);
                }
            }
            $placements = is_array($override['placements'] ?? null) ? $override['placements'] : [];
            unset($placements[$boardId]);
            $entry['question_mappings'][$id] = array_merge($override, [
                'exam_ids' => $effectiveExams,
                'subject_ids' => $effectiveSubjects,
                'placements' => $placements,
            ]);
            App::storage()->replace('question-eligibility', $records);
            self::invalidateMetadataIndex();
            return ['valid' => true, 'removed' => $boardId];
        }

        return ['valid' => false, 'errors' => ['INVALID_BOARD' => 'The question eligibility record could not be located.']];
    }

    private static function writeQuestionMapping(array $question, array $mapping, bool $update): void
    {
        $id = (string) ($question['id'] ?? '');
        if ($id === '') {
            return;
        }
        $boardId = (string) ($mapping['board_id'] ?? '');
        $records = App::storage()->all('question-eligibility');

        foreach ($records as &$entry) {
            if (!is_array($entry) || !self::containsId($entry['question_ids'] ?? [], $id)) {
                continue;
            }
            $override = is_array($entry['question_mappings'][$id] ?? null)
                ? $entry['question_mappings'][$id]
                : [];
            $examIds = is_array($override['exam_ids'] ?? null) ? $override['exam_ids'] : ($entry['exam_ids'] ?? []);
            $subjectIds = is_array($override['subject_ids'] ?? null) ? $override['subject_ids'] : ($entry['subject_ids'] ?? []);
            $placements = is_array($override['placements'] ?? null) ? $override['placements'] : [];
            if (!$update) {
                $examIds[] = $boardId;
            }
            $examIds = self::uniqueNormalizedIds($examIds);
            $subjectIds[$boardId] = (string) ($mapping['subject_id'] ?? '');
            $placements[$boardId] = $mapping;
            $entry['question_mappings'][$id] = array_merge($override, [
                'exam_ids' => $examIds,
                'subject_ids' => $subjectIds,
                'placements' => $placements,
            ]);
            App::storage()->replace('question-eligibility', $records);
            self::invalidateMetadataIndex();
            return;
        }

        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        $sourceBoard = self::normalize((string) ($taxonomy['board_id'] ?? $question['board'] ?? ''));
        $sourceSubject = (string) ($taxonomy['subject_id'] ?? $question['subject_id'] ?? '');
        $examIds = self::uniqueNormalizedIds([$sourceBoard, $boardId]);
        $subjectIds = array_filter([
            $sourceBoard => $sourceSubject,
            $boardId => (string) ($mapping['subject_id'] ?? ''),
        ], static fn(string $subject): bool => $subject !== '');
        $records[] = [
            'id' => 'eligibility-question-' . preg_replace('/[^a-z0-9-]+/i', '-', $id),
            'question_ids' => [$id],
            'exam_ids' => $examIds,
            'subject_ids' => $subjectIds,
            'question_mappings' => [
                $id => [
                    'exam_ids' => $examIds,
                    'subject_ids' => $subjectIds,
                    'placements' => [$boardId => $mapping],
                ],
            ],
        ];
        App::storage()->replace('question-eligibility', $records);
        self::invalidateMetadataIndex();
    }

    private static function editorMapping(
        string $boardId,
        string $subjectId,
        array $placement,
        array $canonicalTaxonomy
    ): array {
        $domainId = self::scalarString($placement['domain_id'] ?? ($canonicalTaxonomy['domain_id'] ?? ''));
        $topicId = self::scalarString($placement['topic_id'] ?? ($canonicalTaxonomy['topic_id'] ?? ''));
        $conceptId = self::scalarString($placement['concept_id'] ?? ($canonicalTaxonomy['concept_id'] ?? ''));
        $blueprintNode = self::scalarString($placement['blueprint_node'] ?? '');
        if ($blueprintNode === '') {
            $blueprintNode = $conceptId !== '' ? $conceptId : ($domainId !== '' ? $domainId : $subjectId);
        }

        return [
            'board_id' => $boardId,
            'board_name' => TaxonomyStorageService::label('boards', $boardId),
            'subject_id' => $subjectId,
            'subject_name' => TaxonomyStorageService::label('subjects', $subjectId),
            'domain_id' => $domainId,
            'domain_name' => TaxonomyStorageService::label('taxonomy/domains', $domainId),
            'topic_id' => $topicId,
            'topic_name' => TaxonomyStorageService::label('taxonomy/topics', $topicId),
            'concept_id' => $conceptId,
            'concept_name' => TaxonomyStorageService::label('taxonomy/concepts', $conceptId),
            'blueprint_node' => $blueprintNode,
            'blueprint_name' => self::blueprintLabel($boardId, $blueprintNode),
            'let_level' => self::scalarString($placement['let_level'] ?? $placement['level'] ?? ''),
            'let_component' => self::scalarString($placement['let_component'] ?? $placement['component'] ?? ''),
            'specialization_id' => self::scalarString($placement['specialization_id'] ?? ''),
        ];
    }

    private static function blueprintContains(string $boardId, string $node, string $subjectId): bool
    {
        if ($node === '') {
            return false;
        }
        if ($boardId === 'let' && LETVariantRegistry::find($node) !== null) {
            return true;
        }
        foreach (App::storage()->all('blueprints') as $blueprint) {
            if (!is_array($blueprint)
                || self::normalize((string) ($blueprint['board_id'] ?? $blueprint['board'] ?? '')) !== $boardId
                || (($blueprint['status'] ?? 'active') !== 'active')) {
                continue;
            }
            foreach (($blueprint['sections'] ?? []) as $section) {
                if (!is_array($section)) {
                    continue;
                }
                $sectionNodes = [
                    (string) ($section['id'] ?? ''),
                    (string) ($section['domain_id'] ?? ''),
                ];
                if (in_array($node, $sectionNodes, true)
                    && (!is_array($section['subjects'] ?? null) || in_array($subjectId, $section['subjects'], true))) {
                    return true;
                }
            }
            foreach (($blueprint['official_areas'] ?? []) as $area) {
                if (!is_array($area)) {
                    continue;
                }
                if ($node === (string) ($area['id'] ?? '')
                    || ($subjectId !== '' && $node === (string) ($area['taxonomy_subject'] ?? ''))) {
                    return true;
                }
            }
        }
        return false;
    }

    private static function blueprintNodes(string $boardId): array
    {
        $nodes = [];
        foreach (App::storage()->all('blueprints') as $blueprint) {
            if (!is_array($blueprint)
                || self::normalize((string) ($blueprint['board_id'] ?? $blueprint['board'] ?? '')) !== $boardId
                || (($blueprint['status'] ?? 'active') !== 'active')) {
                continue;
            }
            foreach (array_merge((array) ($blueprint['sections'] ?? []), (array) ($blueprint['official_areas'] ?? [])) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $id = self::scalarString($item['id'] ?? ($item['taxonomy_subject'] ?? ''));
                if ($id !== '') {
                    $nodes[$id] = [
                        'id' => $id,
                        'name' => (string) ($item['name'] ?? $id),
                    ];
                }
            }
        }
        if ($boardId === 'let') {
            foreach (LETVariantRegistry::specializations() as $specialization) {
                $id = self::scalarString($specialization['id'] ?? '');
                if ($id !== '') {
                    $nodes[$id] = [
                        'id' => $id,
                        'name' => (string) ($specialization['official_name'] ?? $id),
                    ];
                }
            }
        }
        return array_values($nodes);
    }

    private static function blueprintLabel(string $boardId, string $node): string
    {
        foreach (App::storage()->all('blueprints') as $blueprint) {
            if (!is_array($blueprint) || self::normalize((string) ($blueprint['board_id'] ?? $blueprint['board'] ?? '')) !== $boardId) {
                continue;
            }
            foreach (array_merge((array) ($blueprint['sections'] ?? []), (array) ($blueprint['official_areas'] ?? [])) as $item) {
                if (is_array($item) && ((string) ($item['id'] ?? '') === $node || (string) ($item['taxonomy_subject'] ?? '') === $node)) {
                    return (string) ($item['name'] ?? $node);
                }
            }
        }
        return TaxonomyStorageService::label('subjects', $node);
    }

    private static function uniqueNormalizedIds(array $ids): array
    {
        $result = [];
        foreach ($ids as $id) {
            if (!is_scalar($id)) {
                continue;
            }
            $normalized = self::normalize((string) $id);
            if ($normalized !== '' && !in_array($normalized, $result, true)) {
                $result[] = $normalized;
            }
        }
        return $result;
    }

    private static function inputString(array $input, array $keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $input) && is_scalar($input[$key])) {
                return trim((string) $input[$key]);
            }
        }
        return '';
    }

    private static function scalarString(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    public static function metadata(array $question): array
    {
        $id = (string) ($question['id'] ?? '');
        return self::metadataIndex()[$id] ?? [];
    }

    /**
     * Return the persisted eligibility metadata for a set of questions after
     * reading and indexing the grouped collection once.
     *
     * @param array<int, array<string, mixed>> $questions
     * @return array<string, array<string, mixed>>
     */
    public static function metadataForQuestions(array $questions): array
    {
        $index = self::metadataIndex();
        $metadata = [];
        foreach ($questions as $question) {
            if (!is_array($question)) {
                continue;
            }
            $id = (string) ($question['id'] ?? '');
            if (array_key_exists($id, $index)) {
                $metadata[$id] = $index[$id];
            }
        }
        return $metadata;
    }

    /** @return array<string, array<string, mixed>> */
    private static function metadataIndex(): array
    {
        if (self::$metadataIndex !== null) {
            return self::$metadataIndex;
        }

        $metadataByQuestion = [];
        foreach (App::storage()->all('question-eligibility') as $entry) {
            if (!is_array($entry) || !is_array($entry['question_ids'] ?? null)) {
                continue;
            }
            $questionMappings = is_array($entry['question_mappings'] ?? null)
                ? $entry['question_mappings']
                : [];
            foreach (array_map('strval', $entry['question_ids']) as $id) {
                if (array_key_exists($id, $metadataByQuestion)) {
                    continue;
                }
                $override = $questionMappings[$id] ?? null;
                if (is_array($override)) {
                    $metadataByQuestion[$id] = [
                        'exam_ids' => array_values($override['exam_ids'] ?? []),
                        'subject_ids' => is_array($override['subject_ids'] ?? null) ? $override['subject_ids'] : [],
                        'placements' => is_array($override['placements'] ?? null) ? $override['placements'] : [],
                        'variant_placements' => is_array($override['variant_placements'] ?? null) ? $override['variant_placements'] : [],
                    ];
                    continue;
                }
                $metadataByQuestion[$id] = [
                    'exam_ids' => array_values($entry['exam_ids'] ?? []),
                    'subject_ids' => is_array($entry['subject_ids'] ?? null) ? $entry['subject_ids'] : [],
                    'variant_placements' => is_array($entry['variant_placements'] ?? null) ? $entry['variant_placements'] : [],
                ];
            }
        }

        self::$metadataIndex = $metadataByQuestion;
        return self::$metadataIndex;
    }

    private static function invalidateMetadataIndex(): void
    {
        self::$metadataIndex = null;
    }

    public static function persist(array $question, array $metadata): void
    {
        $id = (string) ($question['id'] ?? '');
        $examIds = array_values(array_filter(
            $metadata['exam_ids'] ?? [],
            static fn(mixed $exam): bool => is_scalar($exam) && trim((string) $exam) !== ''
        ));
        if ($id === '' || $examIds === []) {
            return;
        }
        $records = App::storage()->all('question-eligibility');
        foreach ($records as &$record) {
            if (!is_array($record) || !self::containsId($record['question_ids'] ?? [], $id)) {
                continue;
            }
            if (is_array($record['question_mappings'][$id] ?? null)) {
                $record['question_mappings'][$id]['exam_ids'] = $examIds;
                $record['question_mappings'][$id]['subject_ids'] = is_array($metadata['subject_ids'] ?? null) ? $metadata['subject_ids'] : [];
                if (is_array($metadata['placements'] ?? null)) {
                    $record['question_mappings'][$id]['placements'] = $metadata['placements'];
                }
                if (is_array($metadata['variant_placements'] ?? null)) {
                    $record['question_mappings'][$id]['variant_placements'] = $metadata['variant_placements'];
                }
                App::storage()->replace('question-eligibility', $records);
                self::invalidateMetadataIndex();
                return;
            }
            $record['exam_ids'] = $examIds;
            $record['subject_ids'] = is_array($metadata['subject_ids'] ?? null) ? $metadata['subject_ids'] : [];
            App::storage()->replace('question-eligibility', $records);
            self::invalidateMetadataIndex();
            return;
        }
        $records[] = [
            'id' => 'eligibility-question-' . preg_replace('/[^a-z0-9-]+/i', '-', $id),
            'question_ids' => [$id],
            'exam_ids' => $examIds,
            'subject_ids' => is_array($metadata['subject_ids'] ?? null) ? $metadata['subject_ids'] : [],
            'placements' => is_array($metadata['placements'] ?? null) ? $metadata['placements'] : [],
            'variant_placements' => is_array($metadata['variant_placements'] ?? null) ? $metadata['variant_placements'] : [],
        ];
        App::storage()->replace('question-eligibility', $records);
        self::invalidateMetadataIndex();
    }

    public static function forExam(
        array $question,
        string $exam,
        ?QuestionEligibilityIndex $index = null
    ): ?array
    {
        $exam = self::normalize($exam);
        $id = (string) ($question['id'] ?? '');
        if ($index === null && self::hasOverride($id, $exam, 'EXCLUDE')) {
            return null;
        }
        if ($index !== null) {
            return $index->forExam($question, $exam);
        }

        foreach (App::storage()->all('question-eligibility') as $entry) {
            if (!is_array($entry) || !self::containsId($entry['question_ids'] ?? [], $id)) {
                continue;
            }
            $questionMappings = is_array($entry['question_mappings'] ?? null) ? $entry['question_mappings'] : [];
            $override = $questionMappings[$id] ?? null;
            $examIds = is_array($override['exam_ids'] ?? null)
                ? $override['exam_ids']
                : ($entry['exam_ids'] ?? []);
            $exams = array_map([self::class, 'normalize'], is_array($examIds) ? $examIds : []);
            if (in_array($exam, $exams, true)) {
                $subjectIds = is_array($override['subject_ids'] ?? null)
                    ? $override['subject_ids']
                    : ($entry['subject_ids'] ?? []);
                $subjects = is_array($subjectIds) ? $subjectIds : [];
                $placements = is_array($override['placements'] ?? null) ? $override['placements'] : [];
                $placement = is_array($placements[$exam] ?? null) ? $placements[$exam] : [];
                return [
                    'exam_id' => $exam,
                    'subject_id' => self::resolvedSubject($question, $exam, (string) ($placement['subject_id'] ?? $subjects[$exam] ?? $entry['subject_id'] ?? '')),
                    'placement' => $placement,
                    'variant_placements' => is_array($override['variant_placements'] ?? null)
                        ? $override['variant_placements']
                        : (is_array($entry['variant_placements'] ?? null) ? $entry['variant_placements'] : []),
                ];
            }
        }

        foreach (App::storage()->all('question-reuse-overrides') as $override) {
            if (!is_array($override)
                || (string) ($override['question_id'] ?? '') !== $id
                || self::normalize((string) ($override['board_id'] ?? '')) !== $exam
                || ($override['override_type'] ?? '') !== 'INCLUDE'
                || QuestionReuseEligibilityResolver::validateOverride($override) !== []) {
                continue;
            }
            $placement = is_array($override['placement'] ?? null) ? $override['placement'] : [];
            return [
                'exam_id' => $exam,
                'subject_id' => (string) ($placement['subject_id'] ?? ''),
                'eligibility_source' => 'MANUAL_INCLUDE',
                'placement' => $placement,
            ];
        }

        foreach (QuestionReuseEligibilityResolver::productionDerivedRelationships() as $relationship) {
            if ((string) ($relationship['question_id'] ?? '') !== $id
                || self::normalize((string) ($relationship['target_board'] ?? '')) !== $exam) {
                continue;
            }
            $placement = is_array($relationship['target_placement'] ?? null) ? $relationship['target_placement'] : [];
            return [
                'exam_id' => $exam,
                'subject_id' => (string) ($placement['subject_id'] ?? ''),
                'eligibility_source' => 'CONCEPT_INHERITED',
                'relationship_id' => (string) ($relationship['id'] ?? ''),
                'placement' => $placement,
            ];
        }

        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        $board = self::normalize((string) ($taxonomy['board_id'] ?? $question['board'] ?? ''));
        return $board === $exam
            ? [
                'exam_id' => $exam,
                'subject_id' => (string) ($taxonomy['subject_id'] ?? $question['subject_id'] ?? ''),
                'placement' => [],
            ]
            : null;
    }

    /** Resolve a legacy umbrella subject to a blueprint section when taxonomy provides it. */
    private static function resolvedSubject(array $question, string $exam, string $subject): string
    {
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        $domain = (string) ($taxonomy['domain_id'] ?? '');
        if ($domain === '') return $subject;
        foreach (App::storage()->all('blueprints') as $blueprint) {
            if (($blueprint['board_id'] ?? $blueprint['board'] ?? '') !== $exam) continue;
            foreach (($blueprint['sections'] ?? []) as $section) {
                if (($section['id'] ?? '') === $domain || ($section['domain_id'] ?? '') === $domain) return (string) $section['id'];
            }
        }
        return $subject;
    }

    public static function eligible(array $questions, string $exam): array
    {
        $index = self::index();
        return array_values(array_filter(
            $questions,
            static fn(array $question): bool => self::forExam($question, $exam, $index) !== null
        ));
    }

    /**
     * Build one exact eligibility resolver for a request or batch operation.
     * Persisted INCLUDE overrides are validated before indexing so callers
     * retain the same acceptance rules as the storage-backed resolver.
     */
    public static function index(): QuestionEligibilityIndex
    {
        $overrides = array_values(array_filter(
            App::storage()->all('question-reuse-overrides'),
            static fn(mixed $override): bool => !is_array($override)
                || ($override['override_type'] ?? '') !== 'INCLUDE'
                || QuestionReuseEligibilityResolver::validateOverride($override) === []
        ));

        return new QuestionEligibilityIndex(
            App::storage()->all('question-eligibility'),
            App::storage()->all('blueprints'),
            QuestionReuseEligibilityResolver::productionDerivedRelationships(),
            $overrides
        );
    }

    private static function containsId(mixed $ids, string $id): bool
    {
        return is_array($ids) && in_array($id, array_map('strval', $ids), true);
    }

    /**
     * Return only persisted board mappings. editorMappings() intentionally
     * supplies a canonical-taxonomy fallback for new questions; that fallback
     * must not prevent the first canonical eligibility record from being
     * written by the authoring path.
     *
     * @return string[]
     */
    private static function storedBoardMappings(array $question): array
    {
        $id = (string) ($question['id'] ?? '');
        $boards = [];
        foreach (App::storage()->all('question-eligibility') as $entry) {
            if (!is_array($entry) || !self::containsId($entry['question_ids'] ?? [], $id)) {
                continue;
            }
            $mapping = is_array($entry['question_mappings'][$id] ?? null) ? $entry['question_mappings'][$id] : [];
            $examIds = is_array($mapping['exam_ids'] ?? null) ? $mapping['exam_ids'] : ($entry['exam_ids'] ?? []);
            foreach ((array) $examIds as $examId) {
                if (is_scalar($examId)) $boards[] = self::normalize((string) $examId);
            }
        }
        foreach (App::storage()->all('question-reuse-overrides') as $override) {
            if (is_array($override) && (string) ($override['question_id'] ?? '') === $id && ($override['override_type'] ?? '') === 'INCLUDE') {
                $boards[] = self::normalize((string) ($override['board_id'] ?? ''));
            }
        }
        foreach (QuestionReuseEligibilityResolver::productionDerivedRelationships() as $relationship) {
            if (is_array($relationship) && (string) ($relationship['question_id'] ?? '') === $id) {
                $boards[] = self::normalize((string) ($relationship['target_board'] ?? ''));
            }
        }
        return array_values(array_unique(array_filter($boards, static fn(string $board): bool => $board !== '')));
    }

    private static function hasOverride(string $questionId, string $board, string $type): bool
    {
        foreach (App::storage()->all('question-reuse-overrides') as $override) {
            if (is_array($override)
                && (string) ($override['question_id'] ?? '') === $questionId
                && self::normalize((string) ($override['board_id'] ?? '')) === $board
                && ($override['override_type'] ?? '') === $type) {
                return true;
            }
        }
        return false;
    }

    private static function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        return [
            'cse' => 'civil-service',
            'let' => 'let',
            'cle' => 'criminologist',
            'nle' => 'nursing',
            'ple' => 'psychometrician',
            'ce' => 'civil-engineering',
            'me' => 'mechanical-engineering',
            'ree' => 'registered-electrical-engineering',
            'arch' => 'architecture',
        ][$value] ?? $value;
    }
}
