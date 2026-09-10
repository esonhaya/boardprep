<?php

declare(strict_types=1);

namespace App\Services\Shared;

/**
 * Normalized, actionable content QA built on the collections and integrity
 * report already loaded by ContentPriorityService.  It intentionally does not
 * own a second repository scan: callers provide the same projection used by
 * developer intelligence and the CLI.
 */
final class ContentQaService
{
    /** @param array<string, mixed> $collections @param array<string, mixed> $integrity @param array<string, mixed> $filters */
    public static function analyze(array $collections, array $integrity, array $filters = [], array $priority = []): array
    {
        $started = microtime(true);
        $questions = array_values(array_filter($collections['questions'] ?? [], 'is_array'));
        $materials = self::index($collections['study-materials'] ?? []);
        $sources = self::index($collections['sources'] ?? []);
        $linksByQuestion = [];
        $linkQuestionById = [];
        foreach (array_values(array_filter($collections['study-question-links'] ?? [], 'is_array')) as $link) {
            $id = self::string($link['question_id'] ?? null);
            $linkId = self::string($link['id'] ?? null);
            if ($id !== '') {
                $linksByQuestion[$id][] = $link;
                if ($linkId !== '') { $linkQuestionById[$linkId] = $id; }
            }
        }

        $byId = [];
        foreach ($questions as $question) {
            $id = self::string($question['id'] ?? null);
            if ($id !== '') {
                $byId[$id] = $question;
            }
        }
        $issues = [];
        $add = static function (array $issue) use (&$issues): void {
            $issue['question_ids'] = array_values(array_unique(array_filter(array_map('strval', (array) ($issue['question_ids'] ?? [])))));
            $issue += ['board_id' => '', 'subject_id' => '', 'domain_id' => '', 'topic_id' => '', 'concept_id' => '', 'confidence' => 'HIGH'];
            $issues[] = $issue;
        };

        // ContentIntegrityService remains the source of truth for hard faults.
        $failureMap = [
            'invalid_answers' => ['INVALID_ANSWER_KEY', 'ERROR', 'Answer key is missing or is not represented by a choice.', 'Correct the answer key or choices.'],
            'malformed_questions' => ['INVALID_QUESTION_ID', 'ERROR', 'Question record fails structural validation.', 'Repair the question record structure.'],
            'duplicate_question_ids' => ['DUPLICATE_QUESTION_ID', 'ERROR', 'Question ID is duplicated.', 'Assign a unique canonical ID.'],
            'broken_assets' => ['BROKEN_ASSET_REFERENCE', 'ERROR', 'Structured asset reference is broken.', 'Replace or remove the broken asset reference.'],
            'accidental_non_derivable' => ['FLASHCARD_DERIVATION_FAILURE', 'ERROR', 'Active question unexpectedly cannot derive a flashcard.', 'Repair the fields required by flashcard derivation.'],
        ];
        foreach ($failureMap as $key => [$code, $severity, $message, $action]) {
            foreach ((array) ($integrity['failures'][$key] ?? []) as $id => $value) {
                $ids = is_int($id) ? (array) $value : [$id];
                foreach ($ids as $questionId) {
                    $add(self::issue($code, $severity, 'DETERMINISTIC', (string) $message, (string) $action, [(string) $questionId], $byId));
                }
            }
        }
        foreach ((array) ($integrity['boards'] ?? []) as $board) {
            if (!is_array($board)) { continue; }
            foreach ((array) ($board['defects'] ?? []) as $key => $ids) {
                $map = [
                    'missing_eligibility' => ['INELIGIBLE_CANONICAL_CONTENT', 'ERROR', 'Canonical question has no eligibility record.', 'Add a valid eligibility reference.'],
                    'invalid_eligibility' => ['INELIGIBLE_CANONICAL_CONTENT', 'ERROR', 'Eligibility record is invalid.', 'Repair the eligibility reference.'],
                    'unlinked_questions' => ['MISSING_STUDY_LINK', 'ERROR', 'Canonical question has no study-material link.', 'Link a concept-fit study material.'],
                    'invalid_study_links' => ['INVALID_STUDY_LINK', 'ERROR', 'Study link points to invalid content.', 'Repair or remove the study link.'],
                    'duplicate_study_links' => ['DUPLICATE_LINK_RECORD', 'ERROR', 'Duplicate study link record exists.', 'Keep one canonical study link record.'],
                    'invalid_taxonomy_references' => ['INVALID_TAXONOMY_REFERENCE', 'ERROR', 'Question taxonomy reference is invalid.', 'Use an existing taxonomy node.'],
                    'missing_alt_text' => ['BROKEN_ASSET_REFERENCE', 'WARNING', 'Structured visual asset lacks alt text.', 'Add concise alternative text.'],
                ];
                if (!isset($map[$key])) { continue; }
                foreach ((array) $ids as $id) {
                    $linkId = explode(':', (string) $id, 2)[0];
                    $questionId = $linkQuestionById[$linkId] ?? (string) $id;
                    $add(self::issue($map[$key][0], $map[$key][1], 'DETERMINISTIC', $map[$key][2], $map[$key][3], [$questionId], $byId));
                }
            }
        }
        foreach ((array) ($integrity['failures']['card_key_collisions'] ?? []) as $cardKey => $ids) {
            $add(self::issue('CARD_KEY_COLLISION', 'ERROR', 'DETERMINISTIC', 'Multiple questions derive the same flashcard key.', 'Differentiate the card prompt or canonical key.', (array) $ids, $byId, ['card_key' => $cardKey]));
        }

        $rawStems = []; $normalized = []; $templates = []; $groups = [];
        foreach ($byId as $id => $question) {
            $choices = is_array($question['choices'] ?? null) ? $question['choices'] : [];
            $rationale = trim((string) ($question['explanation'] ?? $question['helpfulExplanation'] ?? ''));
            if ($rationale === '') {
                $add(self::issue('MISSING_RATIONALE', 'ERROR', 'DETERMINISTIC', 'Question has no rationale.', 'Add a rationale that explains the keyed answer.', [$id], $byId));
            } elseif (mb_strlen($rationale) < 24) {
                $add(self::issue('RATIONALE_TOO_EMPTY_TO_SUPPORT_KEY', 'WARNING', 'DETERMINISTIC', 'Rationale is too short to explain the keyed answer.', 'Expand the rationale with the decisive reasoning.', [$id], $byId));
            }
            if ($choices !== []) {
                $trimmed = array_map(static fn(mixed $choice): string => trim((string) $choice), $choices);
                if (in_array('', $trimmed, true)) {
                    $add(self::issue('EMPTY_OR_MALFORMED_CHOICE', 'ERROR', 'DETERMINISTIC', 'A choice is empty.', 'Provide a complete choice or remove it.', [$id], $byId));
                }
                if (count($trimmed) !== count(array_unique($trimmed))) {
                    $add(self::issue('DUPLICATE_CHOICES', 'WARNING', 'DETERMINISTIC', 'Two or more displayed choices are identical.', 'Differentiate or remove duplicate choices.', [$id], $byId));
                }
            }
            $hasSource = false; $invalidSource = false;
            foreach ($linksByQuestion[$id] ?? [] as $link) {
                $material = $materials[self::string($link['material_id'] ?? null)] ?? [];
                foreach ((array) ($material['source_ids'] ?? []) as $sourceId) {
                    $hasSource = true;
                    if (!isset($sources[(string) $sourceId])) { $invalidSource = true; }
                }
            }
            if ($invalidSource) {
                $add(self::issue('INVALID_SOURCE_REFERENCE', 'ERROR', 'DETERMINISTIC', 'Linked study material references an unknown source.', 'Repair the material source reference.', [$id], $byId));
            } elseif (($linksByQuestion[$id] ?? []) !== [] && !$hasSource) {
                $add(self::issue('MISSING_SOURCE', 'WARNING', 'DETERMINISTIC', 'Linked study material has no source reference.', 'Attach a concept-fit source to the linked material.', [$id], $byId));
            }
            $stem = trim((string) ($question['question'] ?? ''));
            if (mb_strlen($stem) >= 24) {
                $rawStems[mb_strtolower($stem)][] = $id;
                $fingerprint = self::fingerprint($stem, false);
                $normalized[$fingerprint][] = $id;
                $templates[self::fingerprint($stem, true)][] = $id;
            }
            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            $groupId = self::string($taxonomy['board_id'] ?? $question['board'] ?? null) . '|' . self::string($taxonomy['subject_id'] ?? $question['subject'] ?? null);
            if ($groupId !== '|') {
                $groups[$groupId]['ids'][] = $id;
                $groups[$groupId]['difficulty'][strtolower(self::string($question['difficulty'] ?? null) ?: 'unknown')] = ($groups[$groupId]['difficulty'][strtolower(self::string($question['difficulty'] ?? null) ?: 'unknown')] ?? 0) + 1;
                $intent = strtolower(self::string($question['cognitive_intent'] ?? null));
                if (in_array($intent, ['recall', 'remember', 'recognize', 'classify'], true)) $groups[$groupId]['recall'][] = $id;
                $concept = self::string($taxonomy['concept_id'] ?? $question['concept'] ?? null);
                if ($concept !== '') $groups[$groupId]['concept'][$concept][] = $id;
                $archetype = (($question['multistep'] ?? false) ? 'multi' : 'single') . '|' . (($question['data_interpretation'] ?? false) ? 'data' : 'plain') . '|' . (($question['content_blocks'] ?? []) !== [] ? 'structured' : 'text');
                $groups[$groupId]['archetype'][$archetype][] = $id;
                if ($choices !== [] && ($position = array_search((string) ($question['answer'] ?? ''), array_map('strval', $choices), true)) !== false) {
                    $key = chr(65 + $position);
                    $groups[$groupId]['answer_position'][$key][] = $id;
                }
            }
        }
        foreach ($rawStems as $stem => $ids) if (count($ids) > 1) {
            $add(self::issue('EXACT_DUPLICATE', 'ERROR', 'DETERMINISTIC', 'Question stems are identical.', 'Keep one canonical question or materially differentiate them.', $ids, $byId));
        }
        foreach ($normalized as $fingerprint => $ids) if (count($ids) > 1) {
            $add(self::issue('NORMALIZED_DUPLICATE', 'WARNING', 'DETERMINISTIC', 'Question stems normalize to the same wording.', 'Confirm these are not duplicate content.', $ids, $byId));
        }
        foreach ($templates as $fingerprint => $ids) if (count($ids) > 2) {
            $add(self::issue('NEAR_DUPLICATE_RISK', 'REVIEW', 'HEURISTIC', 'Several stems share a number-neutral wording fingerprint.', 'Review whether the items provide materially distinct reasoning.', $ids, $byId, ['template_size' => count($ids)]));
            $add(self::issue('NUMBER_SWAP_CLONE_RISK', 'REVIEW', 'HEURISTIC', 'Three or more long stems share the same number-neutral template.', 'Review for number-swapped or cosmetic variants.', $ids, $byId, ['template_size' => count($ids)]));
            if (count($ids) > 5) {
                $add(self::issue('STEM_TEMPLATE_OVERUSE', 'REVIEW', 'HEURISTIC', 'A stem template is used more than five times.', 'Diversify scenario and reasoning structure.', $ids, $byId, ['template_size' => count($ids)]));
            }
        }
        foreach (array_values(array_filter($collections['content-fit-audits'] ?? [], 'is_array')) as $audit) {
            $id = self::string($audit['question_id'] ?? null);
            $fit = strtoupper(self::string($audit['fit_classification'] ?? null));
            if ($id !== '' && isset($byId[$id]) && $fit !== '' && !in_array($fit, ['CORRECT', 'VERIFIED'], true)) {
                $add(self::issue('SOURCE_TOPIC_FIT_WARNING', 'REVIEW', 'EDITORIAL_REVIEW', 'Stored content-fit audit is ' . $fit . '.', 'Review the source, taxonomy, and question fit before changing content.', [$id], $byId, ['fit_classification' => $fit, 'audit_rationale' => self::string($audit['rationale'] ?? null)]));
            }
        }
        foreach ($groups as $groupId => $group) {
            $ids = array_values(array_unique($group['ids'] ?? [])); $size = count($ids);
            if ($size < 12) continue;
            foreach (['answer_position' => ['ANSWER_POSITION_IMBALANCE', 'WARNING', 'A single answer position exceeds 75% of this board/subject group.', 'Review answer placement while preserving correct keys.'], 'difficulty' => ['DIFFICULTY_IMBALANCE', 'WARNING', 'A single difficulty label exceeds 85% of this board/subject group.', 'Review difficulty calibration across the group.'], 'concept' => ['CONCEPT_OVERCONCENTRATION', 'REVIEW', 'A single concept exceeds 80% of this board/subject group.', 'Consider diversifying concepts when authoring future content.']] as $metric => [$code, $severity, $message, $action]) {
                $counts = array_map(static fn(mixed $value): int => is_array($value) ? count($value) : (int) $value, $group[$metric] ?? []); arsort($counts); $top = reset($counts);
                $threshold = $metric === 'answer_position' ? .75 : ($metric === 'difficulty' ? .85 : .80);
                if ($top !== false && $top / $size > $threshold) $add(self::issue($code, $severity, 'HEURISTIC', $message, $action, array_slice($ids, 0, 50), $byId, ['group' => $groupId, 'count' => $top, 'total' => $size]));
            }
            if (count($group['recall'] ?? []) / $size > .35) $add(self::issue('EXCESSIVE_PURE_RECALL', 'WARNING', 'HEURISTIC', 'Pure-recall intent exceeds 35% of this board/subject group.', 'Favor application or higher-order items in future authoring.', array_slice($group['recall'], 0, 50), $byId, ['group' => $groupId, 'count' => count($group['recall']), 'total' => $size]));
            if (count($group['archetype'] ?? []) < 2) $add(self::issue('LOW_ARCHETYPE_DIVERSITY', 'REVIEW', 'HEURISTIC', 'The group uses only one structural question archetype.', 'Diversify scenarios, representations, and reasoning chains.', array_slice($ids, 0, 50), $byId, ['group' => $groupId, 'archetypes' => count($group['archetype'] ?? [])]));
        }
        foreach ((array) ($priority['exposure']['high_exposure'] ?? []) as $id) if (isset($byId[(string) $id])) {
            $add(self::issue('LEARNER_OVEREXPOSURE', 'INFO', 'DETERMINISTIC', 'Existing exposure analysis classifies this question as high exposure.', 'Use this signal when selecting new content; it is not a defect.', [(string) $id], $byId));
        }

        $issues = array_values(array_filter($issues, static fn(array $issue): bool => self::matches($issue, $filters)));
        usort($issues, static fn(array $a, array $b): int => [self::rank($a['severity']), $a['issue_code'], implode(',', $a['question_ids'])] <=> [self::rank($b['severity']), $b['issue_code'], implode(',', $b['question_ids'])]);
        $counts = ['ERROR' => 0, 'WARNING' => 0, 'REVIEW' => 0, 'INFO' => 0];
        foreach ($issues as $issue) { $counts[$issue['severity']]++; }
        return [
            'summary' => $counts + ['total' => count($issues)],
            'issues' => $issues,
            'actionable_fix_queue' => array_values(array_filter($issues, static fn(array $issue): bool => in_array($issue['severity'], ['ERROR', 'WARNING'], true) && $issue['confidence'] === 'HIGH')),
            'filters_supported' => ['question_ids', 'id_from', 'id_to', 'board_id', 'subject_id', 'topic_id', 'severity', 'issue_code', 'detection_type'],
            'runtime' => ['qa_runtime_ms' => round((microtime(true) - $started) * 1000, 2), 'question_passes' => 1, 'duplicate_buckets' => count($normalized) + count($templates), 'n_plus_one' => false],
        ];
    }

    private static function issue(string $code, string $severity, string $type, string $message, string $action, array $ids, array $byId, array $evidence = []): array
    {
        $question = $byId[(string) ($ids[0] ?? '')] ?? [];
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        return ['issue_code' => $code, 'severity' => $severity, 'confidence' => $type === 'HEURISTIC' ? 'MEDIUM' : 'HIGH', 'detection_type' => $type, 'message' => $message, 'suggested_action' => $action, 'evidence' => $evidence, 'question_ids' => $ids, 'board_id' => self::string($taxonomy['board_id'] ?? $question['board'] ?? null), 'subject_id' => self::string($taxonomy['subject_id'] ?? $question['subject'] ?? null), 'domain_id' => self::string($taxonomy['domain_id'] ?? null), 'topic_id' => self::string($taxonomy['topic_id'] ?? $question['topic'] ?? null), 'concept_id' => self::string($taxonomy['concept_id'] ?? $question['concept'] ?? null)];
    }
    private static function matches(array $issue, array $filters): bool
    {
        foreach (['board_id', 'subject_id', 'topic_id', 'severity', 'issue_code', 'detection_type'] as $key) if (($filters[$key] ?? '') !== '' && strtolower((string) $issue[$key]) !== strtolower((string) $filters[$key])) return false;
        $wanted = array_filter(array_map('strval', (array) ($filters['question_ids'] ?? [])));
        if ($wanted !== [] && array_intersect($wanted, $issue['question_ids']) === []) return false;
        if (($filters['id_from'] ?? null) !== null || ($filters['id_to'] ?? null) !== null) {
            $inRange = false;
            foreach ($issue['question_ids'] as $id) {
                if (!is_numeric($id)) { continue; }
                $numericId = (int) $id;
                if ((($filters['id_from'] ?? null) === null || $numericId >= (int) $filters['id_from']) && (($filters['id_to'] ?? null) === null || $numericId <= (int) $filters['id_to'])) { $inRange = true; break; }
            }
            if (!$inRange) return false;
        }
        return true;
    }
    private static function fingerprint(string $stem, bool $numbers): string { $stem = mb_strtolower($stem); if ($numbers) $stem = preg_replace('/\\d+(?:[,.]\\d+)?/', '#', $stem) ?? $stem; $stem = preg_replace('/[^a-z0-9#]+/', ' ', $stem) ?? $stem; return trim(preg_replace('/\\s+/', ' ', $stem) ?? $stem); }
    private static function index(array $records): array { $out = []; foreach ($records as $record) if (is_array($record) && ($id = self::string($record['id'] ?? null)) !== '') $out[$id] = $record; return $out; }
    private static function string(mixed $value): string { return is_scalar($value) ? trim((string) $value) : ''; }
    private static function rank(string $severity): int { return ['ERROR' => 0, 'WARNING' => 1, 'REVIEW' => 2, 'INFO' => 3][$severity] ?? 9; }
}
