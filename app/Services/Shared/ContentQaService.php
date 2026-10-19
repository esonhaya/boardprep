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
        $indexes = self::indexCollections($collections);
        $questions = $indexes['questions'];
        $byId = $indexes['by_id'];
        $issues = [];
        $add = self::createIssueAppender($issues);
        self::appendIntegrityIssues($integrity, $byId, $add, $indexes['link_question_by_id']);
        $signals = self::collectQuestionSignals($byId, $indexes['links_by_question'], $indexes['materials'], $indexes['sources'], $add);
        self::appendDistractorIssues($byId, $add);
        self::appendDuplicateIssues($signals, $byId, $add);
        self::appendContentFitIssues($collections, $byId, $add);
        self::appendGroupBalanceIssues($signals['groups'], $byId, $add);
        self::appendExposureIssues($priority, $byId, $add);
        [$reviewDecisions, $cognitiveReview] = self::appendCognitiveReviewIssues($collections, $questions, $byId, $add);

        return self::buildReport(
            $started,
            $collections,
            $questions,
            $indexes['materials'],
            $indexes['sources'],
            $signals,
            $issues,
            $filters,
            $reviewDecisions,
            $cognitiveReview
        );
    }

    /** @param array<string,mixed> $collections @return array<string,mixed> */
    private static function indexCollections(array $collections): array
    {
        $questions = array_values(array_filter($collections['questions'] ?? [], 'is_array'));
        $materials = self::index($collections['study-materials'] ?? []);
        $sources = self::index($collections['sources'] ?? []);
        $linksByQuestion = [];
        $linkQuestionById = [];
        foreach (array_values(array_filter($collections['study-question-links'] ?? [], 'is_array')) as $link) {
            $questionId = self::string($link['question_id'] ?? null);
            $linkId = self::string($link['id'] ?? null);
            if ($questionId === '') continue;
            $linksByQuestion[$questionId][] = $link;
            if ($linkId !== '') $linkQuestionById[$linkId] = $questionId;
        }
        $byId = [];
        foreach ($questions as $question) {
            $id = self::string($question['id'] ?? null);
            if ($id !== '') $byId[$id] = $question;
        }
        return [
            'questions' => $questions,
            'materials' => $materials,
            'sources' => $sources,
            'links_by_question' => $linksByQuestion,
            'link_question_by_id' => $linkQuestionById,
            'by_id' => $byId,
        ];
    }

    /** @param array<int,array<string,mixed>> $issues */
    private static function createIssueAppender(array &$issues): \Closure
    {
        return static function (array $issue) use (&$issues): void {
            $issue['question_ids'] = array_values(array_unique(array_filter(array_map('strval', (array) ($issue['question_ids'] ?? [])))));
            $issue += ['board_id' => '', 'subject_id' => '', 'domain_id' => '', 'topic_id' => '', 'concept_id' => '', 'confidence' => 'HIGH'];
            $issues[] = $issue;
        };
    }

    /** @param array<string,mixed> $integrity @param array<string,mixed> $byId @param array<string,string> $linkQuestionById */
    private static function appendIntegrityIssues(array $integrity, array $byId, \Closure $add, array $linkQuestionById): void
    {
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
                foreach ($ids as $questionId) $add(self::issue($code, $severity, 'DETERMINISTIC', $message, $action, [(string) $questionId], $byId));
            }
        }
        self::appendBoardDefectIssues($integrity, $byId, $add, $linkQuestionById);
        foreach ((array) ($integrity['failures']['card_key_collisions'] ?? []) as $cardKey => $ids) {
            $add(self::issue('CARD_KEY_COLLISION', 'ERROR', 'DETERMINISTIC', 'Multiple questions derive the same flashcard key.', 'Differentiate the card prompt or canonical key.', (array) $ids, $byId, ['card_key' => $cardKey]));
        }
    }

    /** @param array<string,mixed> $integrity @param array<string,mixed> $byId */
    private static function appendBoardDefectIssues(array $integrity, array $byId, \Closure $add, array $linkQuestionById): void
    {
        $map = [
            'missing_eligibility' => ['INELIGIBLE_CANONICAL_CONTENT', 'ERROR', 'Canonical question has no eligibility record.', 'Add a valid eligibility reference.'],
            'invalid_eligibility' => ['INELIGIBLE_CANONICAL_CONTENT', 'ERROR', 'Eligibility record is invalid.', 'Repair the eligibility reference.'],
            'unlinked_questions' => ['MISSING_STUDY_LINK', 'ERROR', 'Canonical question has no study-material link.', 'Link a concept-fit study material.'],
            'invalid_study_links' => ['INVALID_STUDY_LINK', 'ERROR', 'Study link points to invalid content.', 'Repair or remove the study link.'],
            'duplicate_study_links' => ['DUPLICATE_LINK_RECORD', 'ERROR', 'Duplicate study link record exists.', 'Keep one canonical study link record.'],
            'invalid_taxonomy_references' => ['INVALID_TAXONOMY_REFERENCE', 'ERROR', 'Question taxonomy reference is invalid.', 'Use an existing taxonomy node.'],
            'missing_alt_text' => ['BROKEN_ASSET_REFERENCE', 'WARNING', 'Structured visual asset lacks alt text.', 'Add concise alternative text.'],
        ];
        foreach ((array) ($integrity['boards'] ?? []) as $board) {
            if (!is_array($board)) continue;
            foreach ((array) ($board['defects'] ?? []) as $key => $ids) {
                if (!isset($map[$key])) continue;
                foreach ((array) $ids as $id) {
                    $linkId = explode(':', (string) $id, 2)[0];
                    $questionId = $linkQuestionById[$linkId] ?? (string) $id;
                    $add(self::issue($map[$key][0], $map[$key][1], 'DETERMINISTIC', $map[$key][2], $map[$key][3], [$questionId], $byId));
                }
            }
        }
    }

    /** @param array<string,mixed> $byId @param array<string,mixed> $linksByQuestion @param array<string,mixed> $materials @param array<string,mixed> $sources */
    private static function collectQuestionSignals(array $byId, array $linksByQuestion, array $materials, array $sources, \Closure $add): array
    {
        $rawStems = [];
        $normalized = [];
        $templates = [];
        $groups = [];
        foreach ($byId as $id => $question) {
            $id = (string) $id;
            self::appendQuestionContentIssues($id, $question, $linksByQuestion, $materials, $sources, $byId, $add);
            $stem = trim((string) ($question['question'] ?? ''));
            if (mb_strlen($stem) >= 24) {
                $rawStems[mb_strtolower($stem)][] = $id;
                $normalized[self::fingerprint($stem, false)][] = $id;
                $templates[self::fingerprint($stem, true)][] = $id;
            }
            self::collectTaxonomySignals($id, $question, $groups);
        }
        return ['raw_stems' => $rawStems, 'normalized' => $normalized, 'templates' => $templates, 'groups' => $groups];
    }

    /** @param array<string,mixed> $question @param array<string,mixed> $linksByQuestion @param array<string,mixed> $materials @param array<string,mixed> $sources @param array<string,mixed> $byId */
    private static function appendQuestionContentIssues(string $id, array $question, array $linksByQuestion, array $materials, array $sources, array $byId, \Closure $add): void
    {
        if (self::string($question['cognitive_intent'] ?? null) === '') {
            $add(self::issue('MISSING_COGNITIVE_INTENT', 'REVIEW', 'DETERMINISTIC', 'Question has no declared cognitive intent, so its intended reasoning demand cannot be audited.', 'Have an academic reviewer classify the reasoning actually required; do not infer the label from the topic or answer format.', [$id], $byId, ['field' => 'cognitive_intent']));
        }
        $choices = is_array($question['choices'] ?? null) ? $question['choices'] : [];
        $rationale = trim((string) ($question['explanation'] ?? $question['helpfulExplanation'] ?? ''));
        if ($rationale === '') {
            $add(self::issue('MISSING_RATIONALE', 'ERROR', 'DETERMINISTIC', 'Question has no rationale.', 'Add a rationale that explains the keyed answer.', [$id], $byId));
        } elseif (mb_strlen($rationale) < 24) {
            $add(self::issue('RATIONALE_TOO_EMPTY_TO_SUPPORT_KEY', 'WARNING', 'DETERMINISTIC', 'Rationale is too short to explain the keyed answer.', 'Expand the rationale with the decisive reasoning.', [$id], $byId));
        }
        if ($choices !== [] && in_array('', array_map(static fn(mixed $choice): string => trim((string) $choice), $choices), true)) {
            $add(self::issue('EMPTY_OR_MALFORMED_CHOICE', 'ERROR', 'DETERMINISTIC', 'A choice is empty.', 'Provide a complete choice or remove it.', [$id], $byId));
        }
        self::appendSourceLinkIssues($id, $linksByQuestion[$id] ?? [], $materials, $sources, $byId, $add);
    }

    /** @param array<int,array<string,mixed>> $links @param array<string,mixed> $materials @param array<string,mixed> $sources @param array<string,mixed> $byId */
    private static function appendSourceLinkIssues(string $id, array $links, array $materials, array $sources, array $byId, \Closure $add): void
    {
        $hasSource = false;
        $invalidSource = false;
        foreach ($links as $link) {
            $material = $materials[self::string($link['material_id'] ?? null)] ?? [];
            foreach ((array) ($material['source_ids'] ?? []) as $sourceId) {
                $hasSource = true;
                if (!isset($sources[(string) $sourceId])) $invalidSource = true;
            }
        }
        if ($invalidSource) {
            $add(self::issue('INVALID_SOURCE_REFERENCE', 'ERROR', 'DETERMINISTIC', 'Linked study material references an unknown source.', 'Repair the material source reference.', [$id], $byId));
        } elseif ($links !== [] && !$hasSource) {
            $add(self::issue('MISSING_SOURCE', 'WARNING', 'DETERMINISTIC', 'Linked study material has no source reference.', 'Attach a concept-fit source to the linked material.', [$id], $byId));
        }
    }

    /** @param array<string,mixed> $question @param array<string,mixed> $groups */
    private static function collectTaxonomySignals(string $id, array $question, array &$groups): void
    {
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        $groupId = self::string($taxonomy['board_id'] ?? $question['board'] ?? null) . '|' . self::string($taxonomy['subject_id'] ?? $question['subject'] ?? null);
        if ($groupId === '|') return;
        $groups[$groupId]['ids'][] = $id;
        $difficulty = strtolower(self::string($question['difficulty'] ?? null) ?: 'unknown');
        $groups[$groupId]['difficulty'][$difficulty] = ($groups[$groupId]['difficulty'][$difficulty] ?? 0) + 1;
        $intent = strtolower(self::string($question['cognitive_intent'] ?? null));
        if (in_array($intent, ['recall', 'remember', 'recognize', 'classify'], true)) $groups[$groupId]['recall'][] = $id;
        $concept = self::string($taxonomy['concept_id'] ?? $question['concept'] ?? null);
        if ($concept !== '') $groups[$groupId]['concept'][$concept][] = $id;
        $archetype = (($question['multistep'] ?? false) ? 'multi' : 'single') . '|' . (($question['data_interpretation'] ?? false) ? 'data' : 'plain') . '|' . (($question['content_blocks'] ?? []) !== [] ? 'structured' : 'text');
        $groups[$groupId]['archetype'][$archetype][] = $id;
        $choices = is_array($question['choices'] ?? null) ? $question['choices'] : [];
        $position = $choices === [] ? false : array_search((string) ($question['answer'] ?? ''), array_map('strval', $choices), true);
        if ($position !== false) $groups[$groupId]['answer_position'][chr(65 + $position)][] = $id;
    }

    /** @param array<string,mixed> $byId */
    private static function appendDistractorIssues(array $byId, \Closure $add): void
    {
        $audit = \App\Services\Quality\QuestionDistractorAuditService::analyze(array_values($byId));
        foreach ($audit['findings'] as $finding) {
            $questionId = (string) ($finding['question_id'] ?? '');
            $ids = $questionId !== '' ? [$questionId] : (array) ($finding['details']['question_ids'] ?? []);
            $severity = (string) ($finding['severity'] ?? 'REVIEW');
            if (!in_array($severity, ['ERROR', 'REVIEW'], true)) $severity = 'REVIEW';
            $add(self::issue(
                ($finding['code'] ?? '') === 'EXACT_OR_NORMALIZED_DUPLICATE' ? 'DUPLICATE_CHOICES' : 'DISTRACTOR_' . (string) ($finding['code'] ?? 'QUALITY_REVIEW'),
                $severity,
                $severity === 'ERROR' ? 'DETERMINISTIC' : 'HEURISTIC',
                (string) ($finding['reason'] ?? 'Answer options require review.'),
                $severity === 'ERROR' ? 'Repair the duplicate and verify the answer from its source material.' : 'Send the flagged options to an academic reviewer; do not rewrite automatically.',
                $ids,
                $byId,
                (array) ($finding['details'] ?? [])
            ));
        }
    }

    /** @param array<string,mixed> $signals @param array<string,mixed> $byId */
    private static function appendDuplicateIssues(array $signals, array $byId, \Closure $add): void
    {
        foreach ($signals['raw_stems'] as $ids) if (count($ids) > 1) {
            $add(self::issue('EXACT_DUPLICATE', 'ERROR', 'DETERMINISTIC', 'Question stems are identical.', 'Keep one canonical question or materially differentiate them.', $ids, $byId));
        }
        foreach ($signals['normalized'] as $ids) if (count($ids) > 1) {
            $add(self::issue('NORMALIZED_DUPLICATE', 'WARNING', 'DETERMINISTIC', 'Question stems normalize to the same wording.', 'Confirm these are not duplicate content.', $ids, $byId));
        }
        foreach ($signals['templates'] as $ids) {
            if (count($ids) <= 2) continue;
            $add(self::issue('NEAR_DUPLICATE_RISK', 'REVIEW', 'HEURISTIC', 'Several stems share a number-neutral wording fingerprint.', 'Review whether the items provide materially distinct reasoning.', $ids, $byId, ['template_size' => count($ids)]));
            $add(self::issue('NUMBER_SWAP_CLONE_RISK', 'REVIEW', 'HEURISTIC', 'Three or more long stems share the same number-neutral template.', 'Review for number-swapped or cosmetic variants.', $ids, $byId, ['template_size' => count($ids)]));
            if (count($ids) > 5) $add(self::issue('STEM_TEMPLATE_OVERUSE', 'REVIEW', 'HEURISTIC', 'A stem template is used more than five times.', 'Diversify scenario and reasoning structure.', $ids, $byId, ['template_size' => count($ids)]));
        }
    }

    /** @param array<string,mixed> $collections @param array<string,mixed> $byId */
    private static function appendContentFitIssues(array $collections, array $byId, \Closure $add): void
    {
        foreach (array_values(array_filter($collections['content-fit-audits'] ?? [], 'is_array')) as $audit) {
            $id = self::string($audit['question_id'] ?? null);
            $fit = strtoupper(self::string($audit['fit_classification'] ?? null));
            if ($id !== '' && isset($byId[$id]) && $fit !== '' && !in_array($fit, ['CORRECT', 'VERIFIED'], true)) {
                $add(self::issue('SOURCE_TOPIC_FIT_WARNING', 'REVIEW', 'EDITORIAL_REVIEW', 'Stored content-fit audit is ' . $fit . '.', 'Review the source, taxonomy, and question fit before changing content.', [$id], $byId, ['fit_classification' => $fit, 'audit_rationale' => self::string($audit['rationale'] ?? null)]));
            }
        }
    }

    /** @param array<string,mixed> $groups @param array<string,mixed> $byId */
    private static function appendGroupBalanceIssues(array $groups, array $byId, \Closure $add): void
    {
        foreach ($groups as $groupId => $group) {
            $ids = array_values(array_unique($group['ids'] ?? []));
            $size = count($ids);
            if ($size < 12) continue;
            $metrics = [
                'answer_position' => ['ANSWER_POSITION_IMBALANCE', 'WARNING', 'A single answer position exceeds 75% of this board/subject group.', 'Review answer placement while preserving correct keys.', .75],
                'difficulty' => ['DIFFICULTY_IMBALANCE', 'WARNING', 'A single difficulty label exceeds 85% of this board/subject group.', 'Review difficulty calibration across the group.', .85],
                'concept' => ['CONCEPT_OVERCONCENTRATION', 'REVIEW', 'A single concept exceeds 80% of this board/subject group.', 'Consider diversifying concepts when authoring future content.', .80],
            ];
            self::appendGroupMetricIssues($metrics, $group, $groupId, $size, $ids, $byId, $add);
            $recallCount = count($group['recall'] ?? []);
            if ($recallCount / $size > .35) $add(self::issue('EXCESSIVE_PURE_RECALL', 'WARNING', 'HEURISTIC', 'Pure-recall intent exceeds 35% of this board/subject group.', 'Favor application or higher-order items in future authoring.', array_slice($group['recall'], 0, 50), $byId, ['group' => $groupId, 'count' => $recallCount, 'total' => $size]));
            $archetypeCount = count($group['archetype'] ?? []);
            if ($archetypeCount < 2) $add(self::issue('LOW_ARCHETYPE_DIVERSITY', 'REVIEW', 'HEURISTIC', 'The group uses only one structural question archetype.', 'Diversify scenarios, representations, and reasoning chains.', array_slice($ids, 0, 50), $byId, ['group' => $groupId, 'archetypes' => $archetypeCount]));
        }
    }

    /** @param array<string,mixed> $metrics @param array<string,mixed> $group @param array<int,string> $ids @param array<string,mixed> $byId */
    private static function appendGroupMetricIssues(array $metrics, array $group, string $groupId, int $size, array $ids, array $byId, \Closure $add): void
    {
        foreach ($metrics as $metric => [$code, $severity, $message, $action, $threshold]) {
            $counts = array_map(static fn(mixed $value): int => is_array($value) ? count($value) : (int) $value, $group[$metric] ?? []);
            arsort($counts);
            $top = reset($counts);
            if ($top !== false && $top / $size > $threshold) {
                $add(self::issue($code, $severity, 'HEURISTIC', $message, $action, array_slice($ids, 0, 50), $byId, ['group' => $groupId, 'count' => $top, 'total' => $size]));
            }
        }
    }

    /** @param array<string,mixed> $priority @param array<string,mixed> $byId */
    private static function appendExposureIssues(array $priority, array $byId, \Closure $add): void
    {
        foreach ((array) ($priority['exposure']['high_exposure'] ?? []) as $id) {
            $id = (string) $id;
            if (isset($byId[$id])) $add(self::issue('LEARNER_OVEREXPOSURE', 'INFO', 'DETERMINISTIC', 'Existing exposure analysis classifies this question as high exposure.', 'Use this signal when selecting new content; it is not a defect.', [$id], $byId));
        }
    }

    /** @param array<string,mixed> $collections @param array<int,array<string,mixed>> $questions @param array<string,mixed> $byId @return array{0:array<int,array<string,mixed>>,1:array<string,mixed>} */
    private static function appendCognitiveReviewIssues(array $collections, array $questions, array $byId, \Closure $add): array
    {
        $reviewDecisions = array_values(array_filter($collections['academic-review-decisions'] ?? [], 'is_array'));
        $samples = array_values(array_filter($collections['cognitive-intent-review-samples'] ?? [], 'is_array'));
        $cognitiveReview = CognitiveIntentReviewService::summarize($questions, $samples, $reviewDecisions);
        foreach ($cognitiveReview['invalid_review_rows'] as $invalidRow) {
            $invalidId = self::string($invalidRow['question_id'] ?? null);
            $add(self::issue('INVALID_COGNITIVE_REVIEW_LEDGER', 'REVIEW', 'DETERMINISTIC', 'A bounded cognitive-intent review row is stale or structurally inconsistent: ' . self::string($invalidRow['reason'] ?? null) . '.', 'Refresh the snapshot or correct its review decision; do not promote a label automatically.', $invalidId !== '' ? [$invalidId] : [], $byId, ['row' => $invalidRow['row'] ?? null, 'reason' => $invalidRow['reason'] ?? 'UNKNOWN']));
        }
        return [$reviewDecisions, $cognitiveReview];
    }

    /** @param array<string,mixed> $collections @param array<int,array<string,mixed>> $questions @param array<string,mixed> $materials @param array<string,mixed> $sources @param array<string,mixed> $signals @param array<int,array<string,mixed>> $issues @param array<int,array<string,mixed>> $reviewDecisions @param array<string,mixed> $cognitiveReview */
    private static function buildReport(float $started, array $collections, array $questions, array $materials, array $sources, array $signals, array $issues, array $filters, array $reviewDecisions, array $cognitiveReview): array
    {
        $issues = self::filterAndSortIssues($issues, $filters);
        $counts = self::issueCounts($issues);
        $sourceReview = AcademicSourceReviewService::summarize(
            $questions,
            array_values($sources),
            array_values(array_filter($collections['academic-source-reviews'] ?? [], 'is_array')),
            array_values(array_filter($collections['study-question-links'] ?? [], 'is_array')),
            array_values($materials)
        );
        $reviewSummary = self::reviewDecisionSummary($reviewDecisions);
        return [
            'summary' => $counts + ['total' => count($issues)],
            'issues' => $issues,
            'actionable_fix_queue' => array_values(array_filter($issues, static fn(array $issue): bool => in_array($issue['severity'], ['ERROR', 'WARNING'], true) && $issue['confidence'] === 'HIGH')),
            'filters_supported' => ['question_ids', 'id_from', 'id_to', 'board_id', 'subject_id', 'topic_id', 'concept_id', 'severity', 'issue_code', 'detection_type'],
            'review_decision_summary' => $reviewSummary,
            'review_decisions' => $reviewDecisions,
            'semantic_source_review' => $sourceReview,
            'cognitive_intent_review' => $cognitiveReview,
            'runtime' => ['qa_runtime_ms' => round((microtime(true) - $started) * 1000, 2), 'question_passes' => 1, 'duplicate_buckets' => count($signals['normalized']) + count($signals['templates']), 'n_plus_one' => false],
        ];
    }

    /** @param array<int,array<string,mixed>> $issues @return array<int,array<string,mixed>> */
    private static function filterAndSortIssues(array $issues, array $filters): array
    {
        $issues = array_values(array_filter($issues, static fn(array $issue): bool => self::matches($issue, $filters)));
        usort($issues, static fn(array $a, array $b): int => [self::rank($a['severity']), $a['issue_code'], implode(',', $a['question_ids'])] <=> [self::rank($b['severity']), $b['issue_code'], implode(',', $b['question_ids'])]);
        return $issues;
    }

    /** @param array<int,array<string,mixed>> $issues @return array<string,int> */
    private static function issueCounts(array $issues): array
    {
        $counts = ['ERROR' => 0, 'WARNING' => 0, 'REVIEW' => 0, 'INFO' => 0];
        foreach ($issues as $issue) $counts[$issue['severity']]++;
        return $counts;
    }

    /** @param array<int,array<string,mixed>> $reviewDecisions @return array<string,int> */
    private static function reviewDecisionSummary(array $reviewDecisions): array
    {
        return [
            'total' => count($reviewDecisions),
            'review_required' => count(array_filter($reviewDecisions, static fn(array $row): bool => strtoupper(self::string($row['review_state'] ?? null)) === 'REVIEW_REQUIRED')),
            'corrections_applied_pending_human_signoff' => count(array_filter($reviewDecisions, static fn(array $row): bool => strtoupper(self::string($row['correction_state'] ?? null)) === 'APPLIED_PENDING_HUMAN_SIGNOFF')),
        ];
    }

    /** Filter a complete audit result without rerunning the corpus scan. */
    public static function filterReport(array $report, array $filters = []): array
    {
        if ($filters === []) return $report;
        $issues = array_values(array_filter(
            (array) ($report['issues'] ?? []),
            static fn(mixed $issue): bool => is_array($issue) && self::matches($issue, $filters)
        ));
        usort($issues, static fn(array $a, array $b): int => [self::rank($a['severity']), $a['issue_code'], implode(',', $a['question_ids'])] <=> [self::rank($b['severity']), $b['issue_code'], implode(',', $b['question_ids'])]);
        $counts = ['ERROR' => 0, 'WARNING' => 0, 'REVIEW' => 0, 'INFO' => 0];
        foreach ($issues as $issue) $counts[$issue['severity']]++;
        $report['summary'] = $counts + ['total' => count($issues)];
        $report['issues'] = $issues;
        $report['actionable_fix_queue'] = array_values(array_filter($issues, static fn(array $issue): bool => in_array($issue['severity'], ['ERROR', 'WARNING'], true) && $issue['confidence'] === 'HIGH'));
        $report['filters_applied'] = $filters;
        return $report;
    }

    /** Bound developer HTML responses while retaining the filtered full-scan summary. */
    public static function paginateReport(array $report, int $page = 1, int $perPage = 25): array
    {
        $issues = array_values(array_filter((array) ($report['issues'] ?? []), 'is_array'));
        $total = count($issues);
        $perPage = max(1, min(100, $perPage));
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($pages, $page));
        $offset = ($page - 1) * $perPage;
        $report['issues'] = array_slice($issues, $offset, $perPage);
        $report['pagination'] = [
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
            'total' => $total,
            'first' => $total === 0 ? 0 : $offset + 1,
            'last' => $total === 0 ? 0 : min($total, $offset + $perPage),
        ];
        return $report;
    }

    private static function issue(string $code, string $severity, string $type, string $message, string $action, array $ids, array $byId, array $evidence = []): array
    {
        $question = $byId[(string) ($ids[0] ?? '')] ?? [];
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        $issue = ['issue_code' => $code, 'severity' => $severity, 'confidence' => $type === 'HEURISTIC' ? 'MEDIUM' : 'HIGH', 'detection_type' => $type, 'message' => $message, 'suggested_action' => $action, 'evidence' => $evidence, 'question_ids' => $ids, 'board_id' => self::string($taxonomy['board_id'] ?? $question['board'] ?? null), 'subject_id' => self::string($taxonomy['subject_id'] ?? $question['subject'] ?? null), 'domain_id' => self::string($taxonomy['domain_id'] ?? null), 'topic_id' => self::string($taxonomy['topic_id'] ?? $question['topic'] ?? null), 'concept_id' => self::string($taxonomy['concept_id'] ?? $question['concept'] ?? null)];
        if (in_array($code, ['EXACT_DUPLICATE', 'NORMALIZED_DUPLICATE', 'NEAR_DUPLICATE_RISK', 'NUMBER_SWAP_CLONE_RISK', 'STEM_TEMPLATE_OVERUSE'], true)) {
            $stableIds = array_values(array_unique(array_map('strval', $ids)));
            sort($stableIds, SORT_NATURAL);
            $boardIds = [];
            foreach ($stableIds as $id) {
                $row = $byId[$id] ?? [];
                $rowTaxonomy = is_array($row['taxonomy'] ?? null) ? $row['taxonomy'] : [];
                $boardIds[] = self::string($rowTaxonomy['board_id'] ?? $row['board'] ?? null);
            }
            $boardIds = array_values(array_unique($boardIds));
            sort($boardIds, SORT_STRING);
            $identity = json_encode(['boards' => $boardIds, 'question_ids' => $stableIds], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $prefix = in_array($code, ['EXACT_DUPLICATE', 'NORMALIZED_DUPLICATE'], true) ? 'qdup-' : 'qclone-';
            $issue['cluster_id'] = $prefix . substr(hash('sha256', is_string($identity) ? $identity : ''), 0, 20);
        }
        return $issue;
    }
    private static function matches(array $issue, array $filters): bool
    {
        foreach (['board_id', 'subject_id', 'topic_id', 'concept_id', 'severity', 'issue_code', 'detection_type'] as $key) if (($filters[$key] ?? '') !== '' && strtolower((string) ($issue[$key] ?? '')) !== strtolower((string) $filters[$key])) return false;
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
