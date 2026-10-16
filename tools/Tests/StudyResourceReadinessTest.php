<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\Study\StudyLibraryService;
use App\Services\Study\StudyResourceReadinessService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$lesson = [
    'id' => 'fixture-lesson', 'title' => 'Fixture lesson', 'material_id' => 'fixture-material', 'status' => 'published',
    'board_id' => 'fixture-board', 'subject_id' => 'fixture-subject', 'pdf_file' => 'cse-percent-and-ratio-reasoning.pdf',
    'scope_note' => 'The official source defines the boundary of this practice lesson.',
    'learning_objectives' => ['Use the supported calculation.'],
    'sections' => [['heading' => 'Concept', 'body' => 'A complete explanation.']],
    'worked_examples' => [['heading' => 'Example', 'prompt' => 'What is the result?', 'steps' => ['Calculate it.'], 'answer' => '2', 'check' => 'Substitute the result.']],
    'common_misconceptions' => ['Do not reverse the ratio.'], 'question_patterns' => ['Compute the stated share.'],
    'official_scope_source_ids' => ['scope'], 'instructional_source_ids' => ['instruction'], 'practice_question_ids' => ['q-1'],
];
$collections = [
    'boards' => [['id' => 'fixture-board', 'name' => 'Fixture Board']],
    'subjects' => [['id' => 'fixture-subject']],
    'taxonomy/domains' => [['id' => 'fixture-domain']],
    'taxonomy/topics' => [['id' => 'fixture-topic']],
    'taxonomy/concepts' => [['id' => 'fixture-concept']],
    'questions' => [['id' => 'q-1', 'status' => 'active', 'difficulty' => 'medium', 'cognitive_intent' => 'apply', 'taxonomy' => ['board_id' => 'fixture-board', 'subject_id' => 'fixture-subject', 'domain_id' => 'fixture-domain', 'topic_id' => 'fixture-topic', 'concept_id' => 'fixture-concept']]],
    'sources' => [['id' => 'scope', 'title' => 'Official scope'], ['id' => 'instruction', 'title' => 'Instructional source']],
    'study-materials' => [['id' => 'fixture-material', 'status' => 'published', 'exams' => ['fixture-board'], 'source_ids' => ['scope', 'instruction']]],
    'study-lessons' => [$lesson],
    'study-question-links' => [['id' => 'link-q-1', 'question_id' => 'q-1', 'material_id' => 'fixture-material', 'source_ids' => ['scope', 'instruction']]],
    'question-eligibility' => [['id' => 'eligibility-q-1', 'question_ids' => ['q-1'], 'exam_ids' => ['fixture-board']]],
];
$integrity = [
    'portfolio' => ['status' => 'PASS', 'total_invalid_eligibility' => 0, 'total_missing_eligibility' => 0, 'total_invalid_links' => 0, 'total_invalid_taxonomy_refs' => 0, 'total_invalid_source_refs' => 0, 'total_broken_assets' => 0, 'total_invalid_answer_keys' => 0, 'total_derivable_cards' => 1, 'total_justified_exclusions' => 0, 'total_accidental_non_derivable' => 0, 'total_justified_standalone_study_materials' => 0],
    'boards' => ['fixture-board' => ['readiness_status' => 'PASS', 'defects' => []]],
];
$qa = ['summary' => ['total' => 0], 'issues' => []];
$readiness = StudyResourceReadinessService::analyze($collections, $integrity, $qa);
$assert(($readiness['status'] ?? '') === 'PASS', 'A fully evidenced fixture should pass its educational readiness checks.');
$assert(($readiness['metrics']['questions_with_registered_source_traceability'] ?? 0) === 1, 'Registered source traceability was not counted.');
$assert(($readiness['metrics']['questions_linked_to_complete_boardprep_lessons'] ?? 0) === 1, 'Complete lesson practice mapping was not counted.');
$assert(($readiness['metrics']['questions_with_downloadable_study_pdfs'] ?? 0) === 1, 'A validated generated PDF was not counted.');
$assert(($readiness['metrics']['questions_with_semantically_verified_source_support']['status'] ?? '') === 'UNKNOWN', 'Structural links were incorrectly promoted to semantic source verification.');
$assert(($readiness['board_readiness']['fixture-board']['status'] ?? '') === 'PASS', 'Board-level readiness did not reflect the completed fixture.');

$broken = $collections;
$broken['study-question-links'][0]['source_ids'] = ['scope', 'missing-source'];
$brokenValidation = StudyLibraryService::validateSnapshot($broken);
$brokenReport = StudyResourceReadinessService::analyze($broken, $integrity, $qa);
$assert(($brokenValidation['status'] ?? '') === 'FAIL' && ($brokenReport['status'] ?? '') === 'FAIL', 'A broken second source ID was missed or hidden by a passing readiness state.');
$assert(count(array_filter($brokenValidation['issues'], static fn(array $issue): bool => ($issue['record_id'] ?? '') === 'link-q-1' && str_contains($issue['reason'], 'missing-source'))) === 1, 'A broken reference did not identify its exact link and recommended repair.');

$badMaterial = $collections;
$badMaterial['study-question-links'][0]['material_id'] = 'missing-material';
$badMaterialValidation = StudyLibraryService::validateSnapshot($badMaterial);
$assert(($badMaterialValidation['status'] ?? '') === 'FAIL', 'A link to a missing study material was not detected.');

$standalone = $collections;
$standalone['study-materials'][] = ['id' => 'reviewed-standalone', 'status' => 'published', 'exams' => ['fixture-board'], 'source_ids' => ['scope'], 'standalone_resource' => true, 'standalone_reason' => 'Reviewed foundation reference retained for future authoring.'];
$standaloneValidation = StudyLibraryService::validateSnapshot($standalone);
$standaloneIntegrity = $integrity;
$standaloneIntegrity['portfolio']['total_justified_standalone_study_materials'] = 1;
$standaloneReadiness = StudyResourceReadinessService::analyze($standalone, $standaloneIntegrity, $qa);
$assert(($standaloneValidation['metrics']['orphan_published_materials'] ?? null) === [], 'A reviewed standalone material was mislabeled as an accidental orphan.');
$assert(($standaloneValidation['metrics']['unlinked_published_materials'] ?? null) === ['reviewed-standalone'], 'The reviewed standalone exclusion was hidden from the unlinked-resource inventory.');
$assert(($standaloneValidation['metrics']['justified_standalone_published_materials'] ?? null) === ['reviewed-standalone'], 'The standalone rationale was not retained as a justified exclusion.');
$assert(($standaloneReadiness['status'] ?? '') === 'PASS_WITH_JUSTIFIED_EXCLUSIONS' && ($standaloneReadiness['metrics']['justified_exclusions']['standalone_resources'] ?? 0) === 1, 'A justified standalone exclusion did not remain visible in readiness status and metrics.');
$assert(($standaloneReadiness['metrics']['justified_exclusions']['standalone_resource_records'][0]['reason'] ?? '') === 'Reviewed foundation reference retained for future authoring.', 'The report hid the reviewed standalone resource rationale.');

$unreviewed = $collections;
$unreviewed['study-materials'][] = ['id' => 'unreviewed-orphan', 'status' => 'published', 'exams' => ['fixture-board'], 'source_ids' => ['scope']];
$unreviewedReadiness = StudyResourceReadinessService::analyze($unreviewed, $integrity, $qa);
$orphanFinding = array_values(array_filter($unreviewedReadiness['findings'], static fn(array $finding): bool => ($finding['record_id'] ?? '') === 'unreviewed-orphan'));
$assert(($unreviewedReadiness['metrics']['orphan_published_materials'] ?? null) === ['unreviewed-orphan'], 'An unreviewed unlinked resource was omitted from the orphan queue.');
$assert(($unreviewedReadiness['status'] ?? '') === 'PARTIAL' && ($orphanFinding[0]['classification'] ?? '') === 'SUSPECTED_ORPHANED_PUBLISHED_RESOURCE', 'An unreviewed orphan was hidden or overstated as a confirmed structural defect.');
$assert(($unreviewedReadiness['metrics']['questions_requiring_academic_or_editorial_review'] ?? -1) === 0, 'A study-material review incorrectly increased the question academic-review count.');

echo "Study resource readiness and reference validation tests passed\n";
