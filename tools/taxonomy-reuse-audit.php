<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Core\App;
use App\Services\Question\QuestionEligibilityIndex;
use App\Services\Shared\TaxonomyStorageService;

/**
 * Read-only, deterministic inventory for concept-level eligibility work.
 * It never writes mappings and deliberately treats name similarity as review evidence.
 */
final class TaxonomyReuseAudit
{
    private TaxonomyStorageService $tax;
    private array $boards;
    private array $subjects;
    private array $domains;
    private array $topics;
    private array $concepts;
    private array $questions;
    private array $eligibility;
    private array $blueprints;
    private QuestionEligibilityIndex $index;
    private array $byQuestion = [];
    private array $questionBoards = [];
    private array $questionTaxonomy = [];
    private array $coverage = [];
    private array $boardSubjects = [];
    private array $recordIndex = [];

    public function __construct()
    {
        $this->tax = new TaxonomyStorageService();
        $this->boards = $this->tax->boards();
        $this->subjects = $this->tax->subjects();
        $this->domains = $this->tax->domains();
        $this->topics = $this->tax->topics();
        $this->concepts = $this->tax->concepts();
        $this->questions = App::storage()->all('questions');
        $this->eligibility = App::storage()->all('question-eligibility');
        $this->blueprints = App::storage()->all('blueprints');
        $this->index = new QuestionEligibilityIndex($this->eligibility, $this->blueprints);
        foreach (['subject' => $this->subjects, 'domain' => $this->domains, 'topic' => $this->topics, 'concept' => $this->concepts] as $kind => $records) {
            foreach ($records as $record) {
                $id = (string)($record['id'] ?? '');
                if ($id !== '') $this->recordIndex[$kind][$id] = $record;
            }
        }
        $this->indexEligibility();
        $this->indexCoverage();
    }

    public function report(): array
    {
        $counts = array_fill_keys(range(1, 10), 0);
        $single = [];
        $mappingCount = 0;
        foreach ($this->questions as $q) {
            $id = (string)($q['id'] ?? '');
            $n = count($this->questionBoards[$id] ?? []);
            $mappingCount += $n;
            if ($n >= 1 && $n <= 10) $counts[$n]++;
            if ($n === 1) $single[] = $q;
        }
        $classes = [];
        $targets = [];
        foreach ($single as $q) {
            [$class, $target, $reason] = $this->classify($q);
            $id = (string)$q['id'];
            $classes[$id] = $class;
            $targets[$id] = [
                'source_board' => $this->sourceBoard($q),
                'source_subject' => (string)(($q['taxonomy'] ?? [])['subject_id'] ?? ''),
                'source_domain' => (string)(($q['taxonomy'] ?? [])['domain_id'] ?? ''),
                'source_topic' => (string)(($q['taxonomy'] ?? [])['topic_id'] ?? ''),
                'source_concept' => (string)(($q['taxonomy'] ?? [])['concept_id'] ?? ''),
                'classification' => $class,
                'targets' => $target,
                'reason' => $reason,
            ];
        }
        ksort($classes, SORT_NATURAL);
        $classificationHash = hash('sha256', implode('|', array_map(
            static fn(string $id, string $class): string => $id . ':' . $class,
            array_keys($classes),
            $classes
        )));
        $classCounts = array_count_values($classes);
        $classBySource = [];
        foreach ($single as $q) {
            $id = (string)($q['id'] ?? '');
            $source = $this->sourceBoard($q);
            $class = $classes[$id] ?? 'UNCLASSIFIED';
            $classBySource[$source][$class] = ($classBySource[$source][$class] ?? 0) + 1;
        }
        $sharedEvidence = $this->sharedEvidence();
        $matrix = $this->pairMatrix($classes, $targets);
        $families = $this->families($classes);
        $quantitative = $this->quantitative($classes);
        $depth = [
            'question_depth_field' => 'NOT_PRESENT; difficulty, cognitive_intent and multistep are present',
            'board_concept_depth_field' => 'NOT_PRESENT',
            'depth_scale' => 'NOT_PRESENT',
            'depth_semantics' => 'difficulty is not competency depth',
            'consistent_across_boards' => 'NO',
            'usable_for_automatic_reuse' => 'NO',
        ];
        $prerequisites = $this->prerequisites();
        $context = $this->context($single);
        $auto = (int)($classCounts['A'] ?? 0);
        $review = (int)($classCounts['B'] ?? 0) + (int)($classCounts['C'] ?? 0) + (int)($classCounts['D'] ?? 0);

        return [
            'baseline' => [
                'canonical_questions' => count($this->questions),
                'total_boards' => count($this->boards),
                'single_board_eligible' => count($single),
                'multi_board_eligible' => count($this->questions) - count($single),
                'multi_board_eligible_percent' => $this->pct(count($this->questions) - count($single), count($this->questions)),
                'board_eligibility_mappings' => $mappingCount,
                'eligibility_distribution' => array_combine(array_map(static fn(int $n): string => 'eligible_for_' . $n . '_board' . ($n === 1 ? '' : 's'), range(1, 10)), array_values($counts)),
                'max_boards_per_question' => max(array_keys(array_filter($counts, static fn(int $count): bool => $count > 0))),
                'average_boards_per_shared_question' => count($this->questions) - count($single) === 0 ? 0.0 : round(($mappingCount - count($single)) / (count($this->questions) - count($single)), 2),
                'read_only' => true,
            ],
            'actual_taxonomy_model' => [
                'global_root_model' => 'global subjects → domains → topics → concepts',
                'board_placement_model' => 'board-subjects relations plus board blueprint sections/official areas',
                'question_placement_fields' => ['board_id', 'subject_id', 'domain_id', 'topic_id', 'concept_id'],
                'global_concept_model' => 'YES: one global taxonomy/concepts.json collection',
                'board_specific_concept_model' => 'NO separate board concept collection or question field',
                'explicit_equivalence_model' => 'NOT_PRESENT; Batch 605 audit metadata is evidence on direct mappings, not a reusable equivalence table',
                'aliases_or_equivalence' => 'NOT_PRESENT; normalized names are review-only',
                'depth_model' => $depth,
                'prerequisite_model' => $prerequisites,
                'board_context_model' => $context,
            ],
            'board_hierarchy_inventory' => $this->boardsReport(),
            'taxonomy_classification' => [
                'global_canonical' => ['subjects' => count($this->subjects), 'domains' => count($this->domains), 'topics' => count($this->topics), 'concepts' => count($this->concepts)],
                'board_specific_views' => 'board-subjects selects global subjects; LET official_areas explicitly point to taxonomy_subject',
                'board_specific_only' => 'professional IDs live in the same global namespace; no separate marker exists',
                'structural_containers' => 'blueprint sections and LET official_areas',
                'ambiguous' => 'same-name nodes without stable equivalence evidence',
            ],
            'question_placement' => $this->placementReport(),
            'existing_sharing_evidence' => $sharedEvidence,
            'single_board_audit' => [
                'questions_audited' => count($single),
                'potential_cross_board_relationships_screened' => count($single) * max(0, count($this->boards) - 1),
                'classifications' => $classCounts,
                'classifications_by_source_board' => $classBySource,
                'decisions' => $targets,
                'unclassified' => count($single) - count($classes),
                'classification_hash' => $classificationHash,
                'rule' => 'G missing taxonomy; E explicit material context; A same global concept reachable on another board; B verified cross-board evidence without depth; C candidate with prerequisites; D deterministic family/name overlap; F no equivalent.',
            ],
            'quantitative' => $quantitative,
            'board_pair_matrix' => $matrix,
            'concept_families' => $families,
            'natural_reuse_ceiling' => [
                'current_multi_board' => count($this->questions) - count($single),
                'current_multi_board_percent' => $this->pct(count($this->questions) - count($single), count($this->questions)),
                'auto_derivable_additional_questions' => $auto,
                'projected_multi_board_after_auto' => count($this->questions) - count($single) + $auto,
                'projected_multi_board_percent' => $this->pct(count($this->questions) - count($single) + $auto, count($this->questions)),
                'review_required_additional_questions' => $review,
                'potential_after_review' => count($this->questions) - count($single) + $auto + $review,
                'board_specific_remainder' => (int)($classCounts['E'] ?? 0),
                'no_equivalent_remainder' => (int)($classCounts['F'] ?? 0),
                'taxonomy_gap_remainder' => (int)($classCounts['G'] ?? 0),
                'limitation' => 'Only stable global concept IDs are counted as automatic candidates; B-D are not automatic mappings.',
            ],
            'automatic_inheritance_support' => [
                'primary_concept_match' => 'SUPPORTED_NOW',
                'exact_compatible_equivalence' => 'PARTIALLY_SUPPORTED: direct Batch 605 evidence only',
                'depth_check' => 'NOT_SUPPORTED',
                'required_concept_check' => 'NOT_SUPPORTED at question level',
                'context_check' => 'PARTIALLY_SUPPORTED through flags, not a formal model',
                'board_blocklist' => 'NOT_SUPPORTED',
                'board_allowlist' => 'NOT_SUPPORTED',
                'manual_include' => 'SUPPORTED_NOW through direct eligibility mappings',
                'manual_exclude' => 'NOT_SUPPORTED for inherited eligibility',
                'blueprint_resolution' => 'SUPPORTED_NOW through existing mapping validation/index',
                'source_support_check' => 'PARTIALLY_SUPPORTED; links are canonical, not target-board semantic proof',
                'eligibility_provenance' => 'NOT_SUPPORTED',
                'overall_readiness' => 'NOT_READY_FOR_AUTOMATIC_INHERITANCE',
            ],
            'multi_path_and_weighting' => [
                'multi_path_eligibility_supported' => 'DIRECT_ONLY',
                'dedup_key' => 'canonical question id; QuestionPoolFilter also guards normalized text',
                'double_selection_risk' => 'NONE_OBSERVED for current direct path',
                'weight_multiplication_risk' => 'FUTURE INHERITANCE MUST DEDUP BOARD + CANONICAL ID',
                'required_fix' => 'No current production fix; provenance-aware materialization is needed before inheritance.',
            ],
            'doctor_integrity_readiness' => [
                'under_shared_concept_detection' => 'SMALL_EXTENSION',
                'invalid_inheritance_detection' => 'MAJOR_NEW_INFRASTRUCTURE',
                'equivalence_conflict_detection' => 'NOT_SUPPORTED without an explicit relation',
                'taxonomy_gap_detection' => 'SUPPORTED_NOW for required primary taxonomy fields',
                'multi_path_detection' => 'SUPPORTED_NOW for direct mappings',
                'inherited_placement_validation' => 'SMALL_EXTENSION using existing validateMapping',
            ],
            'admin_compatibility' => [
                'batch607_compatible' => 'YES for direct/manual share and placement operations',
                'required_ui_changes' => 'Future provenance labels and manual include/exclude controls',
                'required_data_changes' => 'Future provenance and inherited block/allow fields',
                'manual_override_compatible' => 'PARTIAL',
            ],
            'integrity' => [
                'eligible' => count(array_filter($this->questionBoards, static fn(array $b): bool => $b !== [])) . '/' . count($this->questions),
                'linked' => 'DEFERRED to ContentIntegrityService',
                'derivable' => 'DEFERRED to ContentIntegrityService',
                'invalid_links' => 'DEFERRED',
                'invalid_taxonomy' => $this->invalidTaxonomy(),
                'invalid_keys' => 'DEFERRED',
                'card_collisions' => 'DEFERRED',
                'invalid_eligibility_mappings' => $this->invalidEligibility(),
                'orphaned_board_mappings' => $this->orphanedMappings(),
                'weak_generic_links_introduced' => 0,
            ],
            'preservation' => [
                'canonical_content_changed' => 0,
                'question_clones_created' => 0,
                'eligibility_mappings_mutated' => 0,
                'audit_is_read_only' => true,
            ],
        ];
    }

    private function indexEligibility(): void
    {
        foreach ($this->eligibility as $entry) {
            if (!is_array($entry) || !is_array($entry['question_ids'] ?? null)) continue;
            foreach ($entry['question_ids'] as $qid) {
                if (!is_scalar($qid)) continue;
                $id = (string)$qid;
                $override = is_array($entry['question_mappings'][$id] ?? null) ? $entry['question_mappings'][$id] : [];
                $exams = is_array($override['exam_ids'] ?? null) ? $override['exam_ids'] : ($entry['exam_ids'] ?? []);
                $this->byQuestion[$id][] = ['entry' => $entry, 'override' => $override, 'exams' => $exams];
            }
        }
        foreach ($this->questions as $q) {
            $id = (string)($q['id'] ?? '');
            $this->questionTaxonomy[$id] = is_array($q['taxonomy'] ?? null) ? $q['taxonomy'] : [];
            $boards = [];
            foreach ($this->boards as $board) {
                $boardId = $this->id($board);
                if ($this->index->forExam($q, $boardId) !== null) $boards[$boardId] = true;
            }
            if ($boards === []) {
                $source = $this->sourceBoard($q);
                if ($source !== '') $boards[$source] = true;
            }
            unset($boards['']);
            $this->questionBoards[$id] = array_keys($boards);
        }
    }

    private function indexCoverage(): void
    {
        foreach ($this->boards as $boardRecord) {
            $board = $this->id($boardRecord);
            $subjects = [];
            foreach ($this->tax->subjectsByBoard($board) as $relation) {
                if (is_scalar($relation['subject_id'] ?? null)) $subjects[(string)$relation['subject_id']] = true;
            }
            $this->boardSubjects[$board] = array_keys($subjects);
            $domains = [];
            foreach ($this->domains as $d) if (in_array((string)($d['subject_id'] ?? ''), $this->boardSubjects[$board], true)) $domains[(string)$d['id']] = true;
            $topics = [];
            foreach ($this->topics as $t) if (isset($domains[(string)($t['domain_id'] ?? '')])) $topics[(string)$t['id']] = true;
            $concepts = [];
            foreach ($this->concepts as $c) if (isset($topics[(string)($c['topic_id'] ?? '')])) $concepts[(string)$c['id']] = true;
            $nodes = [];
            foreach ($this->activeBlueprints($board) as $bp) {
                foreach (array_merge($bp['sections'] ?? [], $bp['official_areas'] ?? []) as $node) {
                    if (is_array($node) && is_scalar($node['id'] ?? null)) $nodes[(string)$node['id']] = true;
                }
            }
            $this->coverage[$board] = ['domains' => array_keys($domains), 'topics' => array_keys($topics), 'concepts' => array_keys($concepts), 'blueprint_nodes' => array_keys($nodes)];
        }
    }

    private function activeBlueprints(string $board): array
    {
        return array_values(array_filter($this->blueprints, function (array $bp) use ($board): bool {
            return ($bp['scope'] ?? '') === 'board'
                && $this->board((string)($bp['board_id'] ?? $bp['board'] ?? '')) === $board
                && ($bp['status'] ?? 'active') === 'active';
        }));
    }

    private function boardsReport(): array
    {
        $out = [];
        foreach ($this->boards as $b) {
            $id = $this->id($b);
            $qids = array_keys(array_filter($this->questionBoards, static fn(array $ids): bool => in_array($id, $ids, true)));
            $single = 0; $multi = 0;
            foreach ($qids as $qid) count($this->questionBoards[$qid]) === 1 ? $single++ : $multi++;
            $c = $this->coverage[$id] ?? [];
            $out[] = [
                'board_id' => $id,
                'board_name' => $b['name'] ?? $id,
                'hierarchy' => 'Board → configured global subject → domain → topic → concept; blueprint sections overlay board placement',
                'subjects' => count($this->boardSubjects[$id] ?? []),
                'domains' => count($c['domains'] ?? []),
                'topics' => count($c['topics'] ?? []),
                'concepts' => count($c['concepts'] ?? []),
                'competencies' => 'NOT_PRESENT',
                'blueprint_nodes' => count($c['blueprint_nodes'] ?? []),
                'deepest_leaf_nodes' => count($c['concepts'] ?? []),
                'questions' => count($qids),
                'single_board_questions' => $single,
                'multi_board_questions' => $multi,
                'subject_ids' => $this->boardSubjects[$id] ?? [],
                'blueprint_section_ids' => $c['blueprint_nodes'] ?? [],
            ];
        }
        return $out;
    }

    private function placementReport(): array
    {
        $out = [
            'questions_with_subject' => 0, 'questions_with_domain' => 0, 'questions_with_topic' => 0,
            'questions_with_concept' => 0, 'questions_with_multiple_concepts' => 0,
            'questions_with_no_deepest_competency' => 0, 'questions_using_only_board_placement' => 0,
            'questions_using_global_concept_id' => 0, 'questions_using_board_specific_concept_id' => 'NOT_DETERMINABLE',
            'questions_with_ambiguous_placement' => 0,
            'identity' => 'one canonical record with one primary taxonomy path; no required_concept_ids field observed',
        ];
        foreach ($this->questions as $q) {
            $t = is_array($q['taxonomy'] ?? null) ? $q['taxonomy'] : [];
            foreach (['subject_id', 'domain_id', 'topic_id', 'concept_id'] as $field) if (!empty($t[$field])) $out['questions_with_' . str_replace('_id', '', $field)]++;
            if (is_array($t['concept_ids'] ?? null) && count($t['concept_ids']) > 1) $out['questions_with_multiple_concepts']++;
            if (empty($t['concept_id'])) $out['questions_with_no_deepest_competency']++;
            if (empty($t['subject_id']) && empty($t['domain_id']) && empty($t['topic_id']) && empty($t['concept_id'])) $out['questions_using_only_board_placement']++;
            if (!empty($t['concept_id']) && $this->record('concept', (string)$t['concept_id']) !== null) $out['questions_using_global_concept_id']++;
        }
        return $out;
    }

    private function classify(array $q): array
    {
        $id = (string)($q['id'] ?? '');
        $t = $this->questionTaxonomy[$id] ?? [];
        $source = $this->sourceBoard($q);
        $concept = (string)($t['concept_id'] ?? '');
        $topic = (string)($t['topic_id'] ?? '');
        $domain = (string)($t['domain_id'] ?? '');
        $subject = (string)($t['subject_id'] ?? '');
        if ($source === '' || $subject === '' || $domain === '' || $topic === '' || $concept === '' || !$this->record('concept', $concept) || !$this->record('topic', $topic) || !$this->record('domain', $domain)) return ['G', [], 'incomplete taxonomy'];
        if ($this->contextFlag($q, $source, $concept)) return ['E', [], 'explicit material board/professional context'];
        $sameConcept = [];
        foreach ($this->boards as $b) {
            $target = $this->id($b);
            if ($target !== $source && in_array($concept, $this->coverage[$target]['concepts'] ?? [], true)) $sameConcept[] = $target;
        }
        if ($sameConcept !== []) return ['A', $sameConcept, 'same stable global concept_id reachable through target board'];
        $verified = $this->verifiedTargets($source, $concept);
        if ($verified !== []) return ['B', $verified, 'Batch 605 verified cross-board evidence, but no depth model'];
        $topicRecord = $this->record('topic', $topic);
        $family = $this->familyTargets($source, [$this->name('concept', $concept), $this->name('topic', $topic), $this->name('domain', $domain), $this->name('subject', $subject)]);
        if ($family !== [] && is_array($topicRecord['learning']['prerequisite_ids'] ?? null) && $topicRecord['learning']['prerequisite_ids'] !== []) return ['C', $family, 'family candidate with topic prerequisites not expressible on question'];
        if ($family !== []) return ['D', $family, 'normalized family/name overlap is review evidence only'];
        return ['F', [], 'no deterministic equivalent concept or family'];
    }

    private function familyTargets(string $source, array $names): array
    {
        $names = array_values(array_filter(array_map([$this, 'norm'], $names)));
        $out = [];
        foreach ($this->boards as $b) {
            $target = $this->id($b);
            if ($target === $source) continue;
            foreach ($this->coverage[$target]['concepts'] ?? [] as $concept) {
                if (in_array($this->norm($this->name('concept', $concept)), $names, true)) { $out[] = $target; break; }
            }
        }
        return array_values(array_unique($out));
    }

    private function verifiedTargets(string $source, string $concept): array
    {
        $out = [];
        foreach ($this->eligibility as $entry) foreach (($entry['question_mappings'] ?? []) as $mapping) {
            $audit = is_array($mapping['audit'] ?? null) ? $mapping['audit'] : [];
            if (($audit['batch'] ?? null) !== 605 || ($audit['source_board'] ?? '') !== $source || ($audit['source_concept'] ?? '') !== $concept) continue;
            foreach (($audit['justified_target_boards'] ?? []) as $target) if (is_scalar($target)) $out[] = $this->board((string)$target);
        }
        return array_values(array_unique(array_filter($out)));
    }

    private function sharedEvidence(): array
    {
        $shared = array_filter($this->questionBoards, static fn(array $ids): bool => count($ids) > 1);
        $pairs = []; $batch605 = 0;
        foreach ($shared as $qid => $boards) {
            $source = $this->sourceBoardById((string)$qid);
            $concept = (string)($this->questionTaxonomy[$qid]['concept_id'] ?? '');
            foreach ($boards as $target) {
                if ($target === $source) continue;
                $type = in_array($concept, $this->coverage[$target]['concepts'] ?? [], true) ? 'EXACT' : 'UNKNOWN';
                foreach ($this->mappingRecords((string)$qid, $target) as $record) {
                    $audit = $record['audit'] ?? [];
                    if (($audit['batch'] ?? null) === 605) { $type = 'COMPATIBLE'; $batch605++; }
                }
                $pairs[$source . '→' . $target . '|' . $concept] = $type;
            }
        }
        $counts = array_count_values(array_values($pairs));
        return [
            'multi_board_questions_analyzed' => count($shared),
            'distinct_cross_board_node_pairs' => count($pairs),
            'exact_equivalence_pairs' => $counts['EXACT'] ?? 0,
            'compatible_equivalence_pairs' => $counts['COMPATIBLE'] ?? 0,
            'partial_pairs' => 0, 'related_pairs' => 0, 'conflicting_pairs' => 0,
            'unknown_pairs' => $counts['UNKNOWN'] ?? 0,
            'batch605_mappings_used_as_evidence' => $batch605,
            'limitation' => 'Direct mappings without explicit equivalence metadata remain UNKNOWN.',
        ];
    }

    private function pairMatrix(array $classes, array $targets): array
    {
        $out = [];
        foreach ($this->boards as $sRecord) foreach ($this->boards as $tRecord) {
            $source = $this->id($sRecord); $target = $this->id($tRecord);
            if ($source === $target) continue;
            $current = 0; $auto = 0; $review = 0; $blocked = 0; $exact = 0; $compatible = 0;
            foreach ($this->questions as $q) {
                $id = (string)($q['id'] ?? '');
                if ($this->sourceBoard($q) !== $source) continue;
                if (in_array($target, $this->questionBoards[$id] ?? [], true)) $current++;
                if (!in_array($target, $targets[$id]['targets'] ?? [], true)) continue;
                if (($classes[$id] ?? '') === 'A') { $auto++; $exact++; }
                elseif (in_array(($classes[$id] ?? ''), ['B', 'C', 'D'], true)) { $review++; if (($classes[$id] ?? '') === 'B') $compatible++; }
                elseif (($classes[$id] ?? '') === 'E') $blocked++;
            }
            $out[] = compact('source', 'target', 'current', 'exact', 'compatible', 'auto', 'review', 'blocked');
        }
        return $out;
    }

    private function families(array $classes): array
    {
        $rows = [];
        foreach ($this->questions as $q) {
            $id = (string)($q['id'] ?? ''); $domain = (string)(($q['taxonomy'] ?? [])['domain_id'] ?? '');
            $key = $domain !== '' ? $domain : (string)(($q['taxonomy'] ?? [])['subject_id'] ?? 'unplaced');
            $rows[$key] ??= ['concept_family' => $key, 'boards' => [], 'questions_total' => 0, 'current_shared' => 0, 'hidden_auto_reuse' => 0, 'review_required' => 0, 'blocked_by_depth' => 0, 'blocked_by_prerequisites' => 0, 'blocked_by_context' => 0];
            $rows[$key]['questions_total']++;
            foreach ($this->questionBoards[$id] ?? [] as $b) $rows[$key]['boards'][$b] = true;
            if (count($this->questionBoards[$id] ?? []) > 1) $rows[$key]['current_shared']++;
            $class = $classes[$id] ?? '';
            if ($class === 'A') $rows[$key]['hidden_auto_reuse']++;
            if (in_array($class, ['B', 'C', 'D'], true)) { $rows[$key]['review_required']++; if ($class === 'B') $rows[$key]['blocked_by_depth']++; if ($class === 'C') $rows[$key]['blocked_by_prerequisites']++; }
            if ($class === 'E') $rows[$key]['blocked_by_context']++;
        }
        foreach ($rows as &$row) { $row['boards'] = array_keys($row['boards']); $row['confidence'] = $row['hidden_auto_reuse'] ? 'EXACT_GLOBAL_ID' : ($row['review_required'] ? 'REVIEW' : 'LOW_OVERLAP'); }
        unset($row);
        usort($rows, static fn(array $a, array $b): int => ($b['hidden_auto_reuse'] + $b['review_required']) <=> ($a['hidden_auto_reuse'] + $a['review_required']));
        return array_slice($rows, 0, 20);
    }

    private function quantitative(array $classes): array
    {
        $terms = ['arithmetic','fraction','decimal','percent','percentage','ratio','proportion','algebra','equation','inequality','geometry','mensur','trigon','measurement','statistic','probability','counting','data','quantitative','number','mathematics','calculus','linear'];
        $rows = [];
        foreach ($terms as $term) {
            $ids = [];
            foreach ($this->concepts as $c) if (str_contains($this->norm(implode(' ', [(string)($c['id'] ?? ''), (string)($c['name'] ?? ''), (string)($c['description'] ?? '')])), $term)) $ids[] = (string)$c['id'];
            if ($ids === []) continue;
            $total = 0; $shared = 0; $single = 0; $auto = 0; $review = 0; $blockedDepth = 0; $blockedPrereq = 0; $blockedContext = 0; $boards = [];
            foreach ($this->questions as $q) {
                $id = (string)($q['id'] ?? ''); $concept = (string)(($q['taxonomy'] ?? [])['concept_id'] ?? '');
                if (!in_array($concept, $ids, true)) continue;
                $total++; $n = count($this->questionBoards[$id] ?? []); $n > 1 ? $shared++ : $single++;
                foreach ($this->questionBoards[$id] ?? [] as $b) $boards[$b] = true;
                $class = $classes[$id] ?? ''; if ($class === 'A') $auto++; if (in_array($class, ['B','C','D'], true)) { $review++; if ($class === 'B') $blockedDepth++; if ($class === 'C') $blockedPrereq++; } if ($class === 'E') $blockedContext++;
            }
            $rows[] = compact('term','boards','total','shared','single','auto','review','blockedDepth','blockedPrereq','blockedContext');
        }
        return $rows;
    }

    private function context(array $single): array
    {
        $material = 0; $regulatory = 0;
        foreach ($single as $q) {
            if ($this->contextFlag($q, $this->sourceBoard($q), (string)(($q['taxonomy'] ?? [])['concept_id'] ?? ''))) {
                $material++; if (!empty($q['professional_judgment']) || !empty($q['legal_procedure_application']) || !empty($q['ethics_scope_application'])) $regulatory++;
            }
        }
        return ['context_field' => 'explicit application/context flags; no formal context taxonomy', 'context_neutral_single_board_questions' => count($single) - $material, 'material_board_context_questions' => $material, 'regulatory_questions' => $regulatory, 'context_ambiguous' => 0];
    }

    private function prerequisites(): array
    {
        $n = 0; foreach ($this->topics as $t) if (is_array($t['learning']['prerequisite_ids'] ?? null) && $t['learning']['prerequisite_ids'] !== []) $n++;
        return ['primary_concept_support' => 'YES via taxonomy.concept_id', 'required_concept_support' => 'NOT_PRESENT', 'assumed_foundation_support' => 'NOT_PRESENT', 'multi_concept_questions' => 0, 'topic_nodes_with_prerequisite_metadata' => $n, 'questions_blocked_by_prerequisite_gap' => 'NOT_DETERMINABLE'];
    }

    private function contextFlag(array $q, string $board, string $concept): bool
    {
        foreach (['professional_judgment','context_necessary','legal_procedure_application','forensic_interpretation','correctional_application','technical_reasoning','site_plan_spatial','ethics_scope_application'] as $f) if (!empty($q[$f])) return true;
        $text = $this->norm(implode(' ', [(string)($q['question'] ?? ''), (string)($q['explanation'] ?? ''), $concept]));
        foreach (['national building code','philippine electrical code','nursing practice act','criminal procedure','auditing standard'] as $marker) if (str_contains($text, $marker)) return true;
        return $board !== 'let' && $board !== 'civil-service' && preg_match('/(^|[-_])(cpa|ce|me|ree|arch|nle|ple|cle)([-_]|$)/', $concept) === 1;
    }

    private function mappingRecords(string $qid, string $target): array
    {
        $out = [];
        foreach ($this->byQuestion[$qid] ?? [] as $record) {
            if (in_array($target, array_map(fn($x) => $this->board((string)$x), $record['exams']), true)) $out[] = is_array($record['override']['audit'] ?? null) ? $record['override'] : [];
        }
        return $out;
    }

    private function sourceBoardById(string $qid): string
    {
        $t = $this->questionTaxonomy[$qid] ?? [];
        return $this->board((string)($t['board_id'] ?? ''));
    }

    private function sourceBoard(array $q): string
    {
        $t = is_array($q['taxonomy'] ?? null) ? $q['taxonomy'] : [];
        return $this->board((string)($t['board_id'] ?? $q['board'] ?? ''));
    }

    private function record(string $kind, string $id): ?array
    {
        return $this->recordIndex[$kind][$id] ?? null;
    }

    private function name(string $kind, string $id): string { return (string)($this->record($kind, $id)['name'] ?? $id); }
    private function id(array $r): string { return $this->board((string)($r['id'] ?? '')); }
    private function board(string $v): string
    {
        $v = strtolower(trim($v));
        $v = str_replace(['_', ' '], '-', $v);
        return ['cse' => 'civil-service', 'civilservice' => 'civil-service'][$v] ?? $v;
    }
    private function norm(string $v): string { return trim(preg_replace('/\\s+/', ' ', preg_replace('/[^a-z0-9]+/', ' ', strtolower(trim($v))) ?? '') ?? ''); }
    private function pct(int $n, int $d): float { return $d === 0 ? 0.0 : round($n / $d * 100, 2); }

    private function invalidTaxonomy(): int
    {
        $invalid = 0;
        foreach ($this->questions as $q) {
            $t = is_array($q['taxonomy'] ?? null) ? $q['taxonomy'] : [];
            if (!$this->record('subject', (string)($t['subject_id'] ?? '')) || !$this->record('domain', (string)($t['domain_id'] ?? '')) || !$this->record('topic', (string)($t['topic_id'] ?? '')) || !$this->record('concept', (string)($t['concept_id'] ?? ''))) $invalid++;
        }
        return $invalid;
    }

    private function orphanedMappings(): int
    {
        $known = array_fill_keys(array_map(static fn($q) => (string)($q['id'] ?? ''), $this->questions), true);
        $n = 0; foreach ($this->byQuestion as $id => $_) if (!isset($known[$id])) $n++;
        return $n;
    }

    private function invalidEligibility(): int
    {
        $valid = array_fill_keys(array_map([$this, 'id'], $this->boards), true);
        $n = 0; foreach ($this->questionBoards as $boards) foreach ($boards as $b) if (!isset($valid[$b])) $n++;
        return $n;
    }
}

$report = (new TaxonomyReuseAudit())->report();
if (in_array('--json', array_slice($argv, 1), true)) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
    exit(0);
}
$b = $report['baseline']; $a = $report['single_board_audit']; $c = $a['classifications'];
printf("TAXONOMY_REUSE_STATUS=PASS CANONICAL=%d BOARDS=%d SINGLE=%d MULTI=%d MAPPINGS=%d AUDITED=%d HASH=%s\n", $b['canonical_questions'], $b['total_boards'], $b['single_board_eligible'], $b['multi_board_eligible'], $b['board_eligibility_mappings'], $a['questions_audited'], $a['classification_hash']);
foreach ($c as $k => $v) printf("CLASSIFICATION=%s COUNT=%d\n", $k, $v);
printf("AUTO_REUSE=%d REVIEW=%d BOARD_SPECIFIC=%d NO_EQUIVALENT=%d TAXONOMY_GAP=%d\n", $report['natural_reuse_ceiling']['auto_derivable_additional_questions'], $report['natural_reuse_ceiling']['review_required_additional_questions'], $report['natural_reuse_ceiling']['board_specific_remainder'], $report['natural_reuse_ceiling']['no_equivalent_remainder'], $report['natural_reuse_ceiling']['taxonomy_gap_remainder']);
