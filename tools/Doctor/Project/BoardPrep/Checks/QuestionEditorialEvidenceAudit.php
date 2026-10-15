<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

/**
 * Reports editorial evidence gaps without treating missing metadata or a
 * suspicious rationale template as proof that a question is incorrect.
 */
final class QuestionEditorialEvidenceAudit
{
    private const DIFFICULTIES = ['easy', 'medium', 'hard'];
    private const COGNITIVE_RUBRIC_TERMS = [
        'recall' => ['RECALL'],
        'recognize' => ['RECALL'],
        'understand' => ['UNDERSTANDING'],
        'understanding' => ['UNDERSTANDING'],
        'interpret' => ['UNDERSTANDING'],
        'interpretation' => ['UNDERSTANDING'],
        'apply' => ['APPLICATION'],
        'application' => ['APPLICATION'],
        'diagnose' => ['APPLICATION'],
        'predict' => ['APPLICATION'],
        'sequence' => ['APPLICATION'],
        'quantitative reasoning' => ['APPLICATION'],
        'analysis' => ['ANALYSIS'],
        'analyze' => ['ANALYSIS'],
        'compare' => ['ANALYSIS'],
        'infer' => ['ANALYSIS'],
        'distinguish' => ['ANALYSIS'],
        'synthesize' => ['ANALYSIS'],
        'classify' => ['UNDERSTANDING'],
        'evidence reasoning' => ['ANALYSIS'],
        'contextual reasoning' => ['ANALYSIS'],
        'comparative analysis' => ['ANALYSIS'],
        'critical analysis' => ['ANALYSIS'],
        'evaluate' => ['EVALUATION'],
        'evaluation' => ['EVALUATION'],
        'decide' => ['EVALUATION'],
        'professional judgment' => ['EVALUATION'],
        'instructional decision making' => ['EVALUATION'],
        'modeling' => ['APPLICATION', 'ANALYSIS'],
    ];

    public static function analyze(
        array $questions,
        array $studyLinks = [],
        array $questionEligibility = [],
        array $reviewedDuplicateGroups = []
    ): array
    {
        $byBoard = [];
        $findings = [];
        $stemsByBoard = [];
        $punctuationVariantsByBoard = [];
        $questionsById = [];
        $linksByQuestion = self::groupRecordsByQuestion($studyLinks);
        $eligibilityByQuestion = self::groupEligibilityByQuestion($questionEligibility);

        foreach ($questions as $question) {
            if (!is_array($question) || !in_array(
                strtolower(self::text($question['status'] ?? 'active')),
                ['active', 'approved'],
                true
            )) {
                continue;
            }

            $id = self::text($question['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $questionsById[$id] = $question;
            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            $board = strtolower(self::text($taxonomy['board_id'] ?? $question['board'] ?? 'unknown'));
            $board = $board !== '' ? $board : 'unknown';
            $difficulty = strtolower(self::text($question['difficulty'] ?? ''));
            $row = &$byBoard[$board];
            if (!isset($row)) {
                $row = [
                    'question_ids' => [],
                    'concept_ids' => [],
                    'difficulty' => array_fill_keys(self::DIFFICULTIES, 0),
                    'missing_cognitive_intent_ids' => [],
                ];
            }
            $row['question_ids'][] = $id;
            $concept = self::text($taxonomy['concept_id'] ?? '');
            if ($concept !== '') {
                $row['concept_ids'][$concept] = ($row['concept_ids'][$concept] ?? 0) + 1;
            }
            if (isset($row['difficulty'][$difficulty])) {
                $row['difficulty'][$difficulty]++;
            } else {
                $findings[] = self::finding(
                    'HARD_DEFECT',
                    'INVALID_DIFFICULTY_LABEL',
                    $board,
                    $id,
                    'Difficulty must be easy, medium, or hard.'
                );
            }

            $intent = self::text($question['cognitive_intent'] ?? '');
            if ($intent === '') {
                $row['missing_cognitive_intent_ids'][] = $id;
            } else {
                $rubricClasses = self::cognitiveRubricClasses($intent);
                if ($rubricClasses === []) {
                    $findings[] = self::finding(
                        'REVIEW_REQUIRED',
                        'UNMAPPED_COGNITIVE_INTENT',
                        $board,
                        $id,
                        'The recorded cognitive-intent value does not map to the documented five-level rubric; review the stem, choices, reasoning demand, and explanation before changing it.'
                    );
                } elseif ($rubricClasses === ['RECALL']
                    && (($question['multistep'] ?? false) === true || ($question['calculation_required'] ?? false) === true)) {
                    $findings[] = self::finding(
                        'REVIEW_REQUIRED',
                        'CONTRADICTORY_COGNITIVE_INTENT',
                        $board,
                        $id,
                        'The item is labeled RECALL while its own metadata says it requires multiple steps or a calculation; verify the label against the actual task.'
                    );
                }
            }

            $explanation = self::text($question['explanation'] ?? '');
            if (preg_match('/\AThe key grammatical relationship is shown by\b/i', $explanation) === 1) {
                $findings[] = self::finding(
                    'REVIEW_REQUIRED',
                    'GENERIC_EXPLANATION_TEMPLATE',
                    $board,
                    $id,
                    'The rationale uses a generic grammar template; check it against the stem, answer, and canonical taxonomy.'
                );
            }
            $stem = self::text($question['question'] ?? '');
            $normalizedStem = strtolower(preg_replace('/\s+/u', ' ', trim($stem)) ?? trim($stem));
            if ($normalizedStem !== '') {
                $stemsByBoard[$board][$normalizedStem][] = $id;
                $punctuationKey = self::punctuationNormalizedStem($normalizedStem);
                if ($punctuationKey !== '') {
                    $punctuationVariantsByBoard[$board][$punctuationKey][$normalizedStem][] = $id;
                }
            }
            if (preg_match('/\bThe competency focus is\b|\(Review item\s+[^)]*\)/i', $stem) === 1) {
                $findings[] = self::finding(
                    'HARD_DEFECT',
                    'EDITORIAL_METADATA_LEAK_IN_STEM',
                    $board,
                    $id,
                    'The learner-facing stem contains an authoring label; remove metadata that does not belong to the question.'
                );
            }
            unset($row);
        }

        foreach ($stemsByBoard as $board => $stemGroups) {
            foreach ($stemGroups as $ids) {
                if (count($ids) < 2) {
                    continue;
                }
                sort($ids, SORT_NATURAL);
                $duplicateReview = self::classifyDuplicateGroup(
                    $ids,
                    $questionsById,
                    $linksByQuestion,
                    $eligibilityByQuestion,
                    $reviewedDuplicateGroups
                );
                $findings[] = [
                    'class' => $duplicateReview['classification'] === 'CONFLICTING_ANSWER'
                        ? 'HARD_DEFECT'
                        : 'REVIEW_REQUIRED',
                    'code' => 'EXACT_DUPLICATE_STEM',
                    'board' => $board,
                    'entity_type' => 'question_set',
                    'entity_id' => implode(',', $ids),
                    'classification' => $duplicateReview['classification'],
                    'differing_fields' => $duplicateReview['differing_fields'],
                    'note' => $duplicateReview['reason'],
                ];
                if ($duplicateReview['metadata_differences'] !== []) {
                    $findings[] = [
                        'class' => 'REVIEW_REQUIRED',
                        'code' => 'DUPLICATE_TASK_METADATA_CONFLICT',
                        'board' => $board,
                        'entity_type' => 'question_set',
                        'entity_id' => implode(',', $ids),
                        'differing_fields' => $duplicateReview['metadata_differences'],
                        'note' => 'Equivalent task content carries inconsistent difficulty, cognitive-intent, or multi-step metadata; review the exact IDs without changing learner history.',
                    ];
                }
            }
        }
        foreach ($punctuationVariantsByBoard as $board => $variants) {
            foreach ($variants as $surfaceForms) {
                if (count($surfaceForms) < 2) {
                    continue;
                }
                $ids = [];
                foreach ($surfaceForms as $surfaceIds) {
                    array_push($ids, ...$surfaceIds);
                }
                $ids = array_values(array_unique($ids));
                sort($ids, SORT_NATURAL);
                $findings[] = [
                    'class' => 'REVIEW_REQUIRED',
                    'code' => 'PUNCTUATION_ONLY_STEM_VARIANT',
                    'board' => $board,
                    'entity_type' => 'question_set',
                    'entity_id' => implode(',', $ids),
                    'note' => 'These stems differ only in punctuation and spacing; review the wording, choices, key, and concept for a near-duplicate before using them as separate items.',
                ];
            }
        }

        $profiles = [];
        foreach ($byBoard as $board => $row) {
            $leafCounts = array_values($row['concept_ids']);
            sort($leafCounts, SORT_NUMERIC);
            $leafCount = count($leafCounts);
            $median = 0;
            if ($leafCount > 0) {
                $middle = intdiv($leafCount, 2);
                $median = $leafCount % 2 === 0
                    ? ($leafCounts[$middle - 1] + $leafCounts[$middle]) / 2
                    : $leafCounts[$middle];
            }
            $profiles[] = sprintf(
                'BOARD_QUESTION_DEPTH board=%s questions=%d concepts=%d concept_counts[min/median/max]=%s/%s/%s concepts_under_5=%d under_10=%d under_20=%d difficulty[easy/medium/hard]=%d/%d/%d cognitive_intent_missing=%d; counts are descriptive signals, not official blueprint weights or psychometric calibration.',
                $board,
                count($row['question_ids']),
                $leafCount,
                $leafCount > 0 ? (string) min($leafCounts) : 'n/a',
                $leafCount > 0 ? rtrim(rtrim(number_format((float) $median, 1, '.', ''), '0'), '.') : 'n/a',
                $leafCount > 0 ? (string) max($leafCounts) : 'n/a',
                count(array_filter($leafCounts, static fn(int $count): bool => $count < 5)),
                count(array_filter($leafCounts, static fn(int $count): bool => $count < 10)),
                count(array_filter($leafCounts, static fn(int $count): bool => $count < 20)),
                $row['difficulty']['easy'],
                $row['difficulty']['medium'],
                $row['difficulty']['hard'],
                count($row['missing_cognitive_intent_ids'])
            );

            if ($row['missing_cognitive_intent_ids'] !== []) {
                sort($row['missing_cognitive_intent_ids'], SORT_NATURAL);
                $findings[] = [
                    'class' => 'INSUFFICIENT_EVIDENCE',
                    'code' => 'MISSING_COGNITIVE_INTENT',
                    'board' => $board,
                    'entity_type' => 'question_set',
                    'entity_id' => implode(',', $row['missing_cognitive_intent_ids']),
                    'note' => count($row['missing_cognitive_intent_ids'])
                        . ' active questions have no recorded cognitive-intent label; the missing label does not establish an incorrect item.',
                ];
            }
        }

        sort($profiles, SORT_STRING);
        $duplicateGroups = array_values(array_filter(
            $findings,
            static fn(array $finding): bool => ($finding['code'] ?? '') === 'EXACT_DUPLICATE_STEM'
        ));
        $classificationCounts = [];
        foreach ($duplicateGroups as $group) {
            $classification = (string) ($group['classification'] ?? 'INSUFFICIENT_EVIDENCE');
            $classificationCounts[$classification] = ($classificationCounts[$classification] ?? 0) + 1;
        }
        ksort($classificationCounts, SORT_STRING);
        return [
            'findings' => $findings,
            'profiles' => $profiles,
            'duplicate_groups' => $duplicateGroups,
            'duplicate_classification_counts' => $classificationCounts,
        ];
    }

    /** @return list<string> */
    public static function cognitiveRubricClasses(string $intent): array
    {
        $intent = strtolower(trim(str_replace(['-', '_'], ' ', $intent)));
        $intent = preg_replace('/\s+/', ' ', $intent) ?? $intent;
        $parts = preg_split('/\s+and\s+/', $intent) ?: [];
        $classes = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (!isset(self::COGNITIVE_RUBRIC_TERMS[$part])) {
                return [];
            }
            array_push($classes, ...self::COGNITIVE_RUBRIC_TERMS[$part]);
        }
        $classes = array_values(array_unique($classes));
        sort($classes, SORT_STRING);
        return $classes;
    }

    /** @param list<array<string, mixed>> $links */
    public static function taskFingerprint(array $question, array $links = [], array $eligibility = []): string
    {
        $projection = self::duplicateTaskProjection($question, $links, $eligibility);
        $encoded = json_encode($projection, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return hash('sha256', is_string($encoded) ? $encoded : '');
    }

    private static function classifyDuplicateGroup(
        array $ids,
        array $questionsById,
        array $linksByQuestion,
        array $eligibilityByQuestion,
        array $reviewedDuplicateGroups
    ): array {
        $rows = [];
        $taskSignatures = [];
        $coreSignatures = [];
        $choiceSignatures = [];
        $answers = [];
        $metadata = ['difficulty' => [], 'cognitive_intent' => [], 'multistep' => []];
        $complete = true;
        foreach ($ids as $id) {
            $question = $questionsById[$id] ?? [];
            $links = $linksByQuestion[$id] ?? [];
            $eligibility = $eligibilityByQuestion[$id] ?? [];
            $rows[] = [$question, $links, $eligibility];
            $projection = self::duplicateTaskProjection($question, $links, $eligibility);
            $core = $projection;
            unset($core['source_ids'], $core['study_links'], $core['eligible_boards']);
            $taskSignatures[] = self::jsonValue($projection);
            $coreSignatures[] = self::jsonValue($core);
            $choiceSignatures[] = self::jsonValue($projection['choices'] ?? []);
            $answers[] = $projection['answer'] ?? '';
            $complete = $complete
                && self::duplicateEvidenceComplete($question, $links, $eligibility);
            foreach (array_keys($metadata) as $field) {
                $metadata[$field][] = self::jsonValue($question[$field] ?? null);
            }
        }

        $review = $complete
            ? self::matchingReviewedVariant($ids, $rows, $reviewedDuplicateGroups)
            : null;
        if ($review !== null) {
            return [
                'classification' => 'LEGITIMATE_VARIANT',
                'reason' => $review,
                'differing_fields' => ['choices', 'answer', 'explanation'],
                'metadata_differences' => self::differentMetadataFields($metadata),
            ];
        }

        if (!$complete) {
            return [
                'classification' => 'INSUFFICIENT_EVIDENCE',
                'reason' => 'The exact stems match, but one or more records lack a complete key, rationale, canonical concept, source-supported study path, or eligibility record. Preserve each ID and resolve the missing evidence first.',
                'differing_fields' => [],
                'metadata_differences' => self::differentMetadataFields($metadata),
            ];
        }

        $sameChoices = count(array_unique($choiceSignatures)) === 1;
        $sameCoreExceptAnswer = true;
        $baselineWithoutAnswer = self::duplicateTaskProjection($rows[0][0], $rows[0][1], $rows[0][2]);
        unset($baselineWithoutAnswer['answer'], $baselineWithoutAnswer['explanation']);
        foreach ($rows as $index => [$question, $links, $eligibility]) {
            $projection = self::duplicateTaskProjection($question, $links, $eligibility);
            unset($projection['answer'], $projection['explanation']);
            if (self::jsonValue($projection) !== self::jsonValue($baselineWithoutAnswer)) {
                $sameCoreExceptAnswer = false;
                break;
            }
        }

        if ($sameCoreExceptAnswer && $sameChoices && count(array_unique($answers)) > 1) {
            return [
                'classification' => 'CONFLICTING_ANSWER',
                'reason' => 'The records have the same board, concept, stem, answer choices, source support, and study path, but their correct-answer identities conflict. Verify the answer against authoritative evidence before changing any key.',
                'differing_fields' => ['answer', 'explanation'],
                'metadata_differences' => self::differentMetadataFields($metadata),
            ];
        }

        if (count(array_unique($taskSignatures)) === 1) {
            return [
                'classification' => 'TRUE_DUPLICATE',
                'reason' => 'The board, taxonomy, stem, choice set, answer, explanation, supporting sources, study material, and eligible board placements match. Keep every canonical ID until any historical references can be migrated safely; prevent equivalent tasks from appearing together.',
                'differing_fields' => [],
                'metadata_differences' => self::differentMetadataFields($metadata),
            ];
        }

        $differentFields = self::differentTaskFields($rows);
        return [
            'classification' => 'SAME_STEM_DIFFERENT_TASK',
            'reason' => 'The stems match, but task-defining fields differ (' . implode(', ', $differentFields) . '). Preserve the canonical questions as separate tasks until a reviewer establishes equivalence.',
            'differing_fields' => $differentFields,
            'metadata_differences' => self::differentMetadataFields($metadata),
        ];
    }

    private static function duplicateEvidenceComplete(array $question, array $links, array $eligibility): bool
    {
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        $choices = is_array($question['choices'] ?? null) ? $question['choices'] : [];
        $answer = self::normalizedText($question['answer'] ?? '');
        $normalizedChoices = array_map(self::normalizedText(...), $choices);
        $projection = self::duplicateTaskProjection($question, $links, $eligibility);
        return self::normalizedText($question['question'] ?? '') !== ''
            && self::normalizedText($taxonomy['board_id'] ?? $question['board'] ?? '') !== ''
            && self::normalizedText($taxonomy['concept_id'] ?? '') !== ''
            && count($normalizedChoices) >= 2
            && !in_array('', $normalizedChoices, true)
            && $answer !== ''
            && count(array_filter($normalizedChoices, static fn(string $choice): bool => $choice === $answer)) === 1
            && self::normalizedText($question['explanation'] ?? '') !== ''
            && $projection['source_ids'] !== []
            && $projection['materials'] !== []
            && $links !== []
            && $eligibility !== [];
    }

    private static function duplicateTaskProjection(array $question, array $links, array $eligibility): array
    {
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        $taxonomyProjection = [];
        foreach (['board_id', 'subject_id', 'domain_id', 'topic_id', 'concept_id'] as $field) {
            $taxonomyProjection[$field] = self::normalizedText($taxonomy[$field] ?? '');
        }
        $choices = is_array($question['choices'] ?? null) ? $question['choices'] : [];
        $choices = array_map(self::normalizedText(...), $choices);
        sort($choices, SORT_STRING);
        $studyLinks = [];
        $sourceIds = self::normalizedStringList($question['source_ids'] ?? []);
        $materials = self::normalizedStringList(array_merge(
            (array) ($question['study_material_ids'] ?? []),
            self::normalizedStringList([$question['study_material_id'] ?? ''])
        ));
        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }
            $materialId = self::normalizedText($link['material_id'] ?? '');
            $linkSources = self::normalizedStringList($link['source_ids'] ?? []);
            $materials = array_merge($materials, $materialId !== '' ? [$materialId] : []);
            $sourceIds = array_merge($sourceIds, $linkSources);
            $studyLinks[] = ['material_id' => $materialId, 'source_ids' => $linkSources];
        }
        usort($studyLinks, static fn(array $left, array $right): int => self::jsonValue($left) <=> self::jsonValue($right));
        $sourceIds = array_values(array_unique($sourceIds));
        $materials = array_values(array_unique($materials));
        $eligibleBoards = [];
        foreach ($eligibility as $record) {
            if (!is_array($record)) {
                continue;
            }
            foreach ((array) ($record['exam_ids'] ?? []) as $examId) {
                $examId = self::normalizedText($examId);
                if ($examId !== '') {
                    $eligibleBoards[] = $examId;
                }
            }
        }
        sort($sourceIds, SORT_STRING);
        sort($materials, SORT_STRING);
        sort($eligibleBoards, SORT_STRING);
        return [
            'board' => self::normalizedText($taxonomy['board_id'] ?? $question['board'] ?? ''),
            'taxonomy' => $taxonomyProjection,
            'stem' => self::normalizedText($question['question'] ?? ''),
            'choices' => $choices,
            'answer' => self::normalizedText($question['answer'] ?? ''),
            'explanation' => self::normalizedText($question['explanation'] ?? ''),
            'source_ids' => $sourceIds,
            'materials' => $materials,
            'study_links' => $studyLinks,
            'eligible_boards' => array_values(array_unique($eligibleBoards)),
        ];
    }

    private static function matchingReviewedVariant(array $ids, array $rows, array $reviews): ?string
    {
        foreach ($reviews as $review) {
            if (!is_array($review) || strtoupper((string) ($review['classification'] ?? '')) !== 'LEGITIMATE_VARIANT') {
                continue;
            }
            $reviewIds = array_map('strval', (array) ($review['question_ids'] ?? []));
            sort($reviewIds, SORT_NATURAL);
            if ($reviewIds !== $ids
                || self::normalizedText($review['board_id'] ?? '') !== self::normalizedText($rows[0][0]['taxonomy']['board_id'] ?? $rows[0][0]['board'] ?? '')
                || self::normalizedText($review['normalized_stem'] ?? '') !== self::normalizedText($rows[0][0]['question'] ?? '')
                || self::text($review['reason'] ?? '') === '') {
                continue;
            }
            $expected = is_array($review['fingerprints'] ?? null) ? $review['fingerprints'] : [];
            $expectedIds = array_map('strval', array_keys($expected));
            sort($expectedIds, SORT_NATURAL);
            if ($expectedIds !== $ids) {
                continue;
            }
            $fingerprintsMatch = true;
            foreach ($rows as [$question, $links, $eligibility]) {
                $id = self::text($question['id'] ?? '');
                if (!isset($expected[$id]) || !hash_equals((string) $expected[$id], self::taskFingerprint($question, $links, $eligibility))) {
                    $fingerprintsMatch = false;
                    break;
                }
            }
            $distinctTasks = count(array_unique(array_map(
                static fn(array $row): string => self::taskFingerprint($row[0], $row[1], $row[2]),
                $rows
            ))) === count($rows);
            if ($fingerprintsMatch && $distinctTasks) {
                return self::text($review['reason']);
            }
        }
        return null;
    }

    private static function differentTaskFields(array $rows): array
    {
        $fields = ['taxonomy', 'choices', 'answer', 'explanation', 'source_ids', 'materials', 'study_links', 'eligible_boards'];
        $different = [];
        foreach ($fields as $field) {
            $values = [];
            foreach ($rows as [$question, $links, $eligibility]) {
                $projection = self::duplicateTaskProjection($question, $links, $eligibility);
                $values[] = match ($field) {
                    'taxonomy' => self::jsonValue($projection['taxonomy']),
                    'choices' => self::jsonValue($projection['choices']),
                    'answer' => self::jsonValue($projection['answer']),
                    'explanation' => self::jsonValue($projection['explanation']),
                    'source_ids' => self::jsonValue($projection['source_ids']),
                    'materials' => self::jsonValue($projection['materials']),
                    'study_links' => self::jsonValue($projection['study_links']),
                    'eligible_boards' => self::jsonValue($projection['eligible_boards']),
                };
            }
            if (count(array_unique($values)) > 1) {
                $different[] = $field;
            }
        }
        return $different;
    }

    private static function differentMetadataFields(array $metadata): array
    {
        $different = [];
        foreach ($metadata as $field => $values) {
            if (count(array_unique($values)) > 1) {
                $different[] = $field;
            }
        }
        return $different;
    }

    private static function groupRecordsByQuestion(array $records): array
    {
        $grouped = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }
            $id = self::text($record['question_id'] ?? '');
            if ($id !== '') {
                $grouped[$id][] = $record;
            }
        }
        return $grouped;
    }

    private static function groupEligibilityByQuestion(array $records): array
    {
        $grouped = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }
            foreach ((array) ($record['question_ids'] ?? []) as $id) {
                $id = self::text($id);
                if ($id !== '') {
                    $grouped[$id][] = $record;
                }
            }
        }
        return $grouped;
    }

    private static function normalizedStringList(mixed $values): array
    {
        if (!is_array($values)) {
            $values = [$values];
        }
        $normalized = [];
        foreach ($values as $value) {
            $value = self::normalizedText($value);
            if ($value !== '') {
                $normalized[] = $value;
            }
        }
        $normalized = array_values(array_unique($normalized));
        sort($normalized, SORT_STRING);
        return $normalized;
    }

    private static function normalizedText(mixed $value): string
    {
        return strtolower(preg_replace('/\s+/u', ' ', trim(is_scalar($value) ? (string) $value : '')) ?? '');
    }

    private static function jsonValue(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
    }

    private static function finding(string $class, string $code, string $board, string $id, string $note): array
    {
        return [
            'class' => $class,
            'code' => $code,
            'board' => $board,
            'entity_type' => 'question',
            'entity_id' => $id,
            'note' => $note,
        ];
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private static function punctuationNormalizedStem(string $stem): string
    {
        $stem = str_replace(
            ['.', ',', ';', ':', '!', '?', '"', "'", '“', '”', '‘', '’', '(', ')', '[', ']', '{', '}'],
            ' ',
            $stem
        );
        return strtolower(preg_replace('/\s+/u', ' ', trim($stem)) ?? trim($stem));
    }
}
