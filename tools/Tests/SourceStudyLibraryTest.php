<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Services\Study\SourceRegistryService;
use App\Services\Study\StudyLibraryService;
use App\Services\Study\ExamIntelligenceService;

$sourceCheck = SourceRegistryService::validate();
$materialCheck = StudyLibraryService::validate();
$intelligenceCheck = ExamIntelligenceService::validate();
if (!$sourceCheck['valid'] || !$materialCheck['valid'] || !$intelligenceCheck['valid'] || $sourceCheck['total'] < 2 || $materialCheck['total'] < 2) {
    throw new RuntimeException('Source/study validation failed: ' . json_encode([
        'SourceRegistryService' => [
            'valid' => $sourceCheck['valid'] ?? null,
            'invalid_record_ids' => $sourceCheck['invalid'] ?? [],
            'issues' => $sourceCheck['issues'] ?? [],
        ],
        'StudyLibraryService' => [
            'valid' => $materialCheck['valid'] ?? null,
            'status' => $materialCheck['status'] ?? null,
            'invalid_record_ids' => $materialCheck['invalid'] ?? [],
            'issues' => $materialCheck['issues'] ?? [],
        ],
        'ExamIntelligenceService' => [
            'valid' => $intelligenceCheck['valid'] ?? null,
            'invalid_record_ids' => $intelligenceCheck['invalid'] ?? [],
            'issues' => $intelligenceCheck['issues'] ?? [],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
}
$taxForm = SourceRegistryService::find('bir-efps-form-1701-q');
if ($taxForm === null || ($taxForm['source_type'] ?? '') !== 'OFFICIAL_FORM') {
    throw new RuntimeException('official form provenance is missing from the source registry');
}
$constitution = StudyLibraryService::find('constitution-review-foundation');
$ra = StudyLibraryService::find('ra-6713-review-foundation');
if ($constitution === null || $ra === null || ($constitution['sources'][0]['source_type'] ?? '') !== 'PRIMARY_LAW'
    || ($ra['sources'][0]['source_type'] ?? '') !== 'PRIMARY_LAW') {
    throw new RuntimeException('primary-law provenance is not attached to study materials');
}
$cse = StudyLibraryService::all('civil-service');
$let = StudyLibraryService::all('let');
// Batch 629 adds nine source-backed LET specialization/PE foundations.
// LET study foundations grow by milestone; keep the contract focused on the
// required baseline rather than coupling it to one batch's exact row count.
if (count($cse) < 8 || count($let) < 36 || !isset($constitution['exam_focus']['civil-service'])) {
    throw new RuntimeException('exam-specific study focus mapping is incomplete');
}
$questions = \App\Core\App::storage()->all('questions');
$ids = array_map(static fn(array $question): string => (string) ($question['id'] ?? ''), $questions);
if (count($questions) < 8962 || count(array_unique($ids)) !== count($questions)
    || array_diff(array_map('strval', range(1, 8962)), $ids) !== []) {
    throw new RuntimeException('source library work changed canonical question identities');
}
$constitutionLinks = StudyLibraryService::questionsFor('constitution-review-foundation');
$constitutionIds = array_map(static fn(array $link): string => (string) ($link['question_id'] ?? ''), $constitutionLinks);
$raLinks = StudyLibraryService::questionsFor('ra-6713-review-foundation');
$raIds = array_map(static fn(array $link): string => (string) ($link['question_id'] ?? ''), $raLinks);
$questionIds = array_fill_keys($ids, true);
if (count($constitutionIds) < 43
    || count($constitutionIds) !== count(array_unique($constitutionIds))
    || array_diff($constitutionIds, array_keys($questionIds)) !== []
    || $raIds === []
    || count($raIds) !== count(array_unique($raIds))
    || array_diff($raIds, array_keys($questionIds)) !== []) {
    throw new RuntimeException('source-backed question links are incomplete');
}
if (count(ExamIntelligenceService::all('civil-service')) !== 2
    || ExamIntelligenceService::priority($constitution, 'civil-service')['level'] !== 'HIGH') {
    throw new RuntimeException('official intelligence priority traceability is incomplete');
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('Source/study validation regression failed: ' . $message);
    }
};
$hasIssue = static function (array $result, string $recordType, string $recordId, string $reasonPart): bool {
    foreach ($result['issues'] ?? [] as $issue) {
        if (($issue['record_type'] ?? '') === $recordType
            && ($issue['record_id'] ?? '') === $recordId
            && str_contains((string) ($issue['reason'] ?? ''), $reasonPart)
            && trim((string) ($issue['recommended_action'] ?? '')) !== '') {
            return true;
        }
    }
    return false;
};

$supportedStandard = [
    'id' => 'fixture-professional-standard',
    'title' => 'Professional Standard Fixture',
    'source_type' => 'PROFESSIONAL_STANDARD',
    'authority' => 'Fixture Standards Body',
];
$assert(SourceRegistryService::validateSnapshot([$supportedStandard])['valid'], 'A supported professional standard source type must validate.');
$professionalGuidance = SourceRegistryService::find('cast-udl-guidelines-3-0-2024');
$assert(is_array($professionalGuidance)
    && ($professionalGuidance['source_type'] ?? '') === 'PROFESSIONAL_GUIDANCE'
    && SourceRegistryService::validateSnapshot([$professionalGuidance])['valid'], 'A registered professional guidance source type must validate.');
$invalidSource = $supportedStandard;
$invalidSource['id'] = 'fixture-invalid-source';
$invalidSource['source_type'] = 'UNREGISTERED_SOURCE_TYPE';
$invalidSourceResult = SourceRegistryService::validateSnapshot([$supportedStandard, $invalidSource]);
$assert(!$invalidSourceResult['valid']
    && in_array('fixture-invalid-source', $invalidSourceResult['invalid'], true)
    && $hasIssue($invalidSourceResult, 'source', 'fixture-invalid-source', 'Source type is not recognized'),
    'An invalid source type must fail with its record ID and reason.');

$validFixture = [
    'sources' => [$supportedStandard],
    'study-materials' => [[
        'id' => 'fixture-material', 'status' => 'published', 'exams' => ['civil-service'],
        'source_ids' => ['fixture-professional-standard'],
    ]],
    'questions' => [['id' => 'fixture-q1'], ['id' => 'fixture-q2']],
    'question-eligibility' => [[
        'id' => 'fixture-eligibility', 'question_ids' => ['fixture-q1', 'fixture-q2'], 'exam_ids' => ['civil-service'],
    ]],
    'study-question-links' => [[
        'id' => 'fixture-link-q1', 'question_id' => 'fixture-q1', 'material_id' => 'fixture-material',
        'source_ids' => ['fixture-professional-standard'],
    ]],
    'study-lessons' => [[
        'id' => 'fixture-lesson', 'material_id' => 'fixture-material', 'status' => 'published',
        'board_id' => 'civil-service', 'scope_note' => 'Fixture scope boundary.',
        'sections' => [['heading' => 'Concept', 'body' => 'A concise explanation.']],
        'official_scope_source_ids' => ['fixture-professional-standard'],
        'instructional_source_ids' => ['fixture-professional-standard'],
        'practice_question_ids' => ['fixture-q1'],
    ]],
];
$validFixtureResult = StudyLibraryService::validateSnapshot($validFixture);
$assert(($validFixtureResult['status'] ?? '') === 'PASS', 'Valid source, lesson, question, eligibility, and link records must pass.');

$badMaterialSource = $validFixture;
$badMaterialSource['study-materials'][0]['source_ids'] = ['missing-source'];
$badMaterialResult = StudyLibraryService::validateSnapshot($badMaterialSource);
$assert(!$badMaterialResult['valid']
    && $hasIssue($badMaterialResult, 'study_material', 'fixture-material', 'unknown source: missing-source'),
    'An invalid material source reference must identify the affected material and source ID.');

$badLinkSource = $validFixture;
$badLinkSource['study-question-links'][0]['source_ids'] = ['missing-source'];
$badLinkSourceResult = StudyLibraryService::validateSnapshot($badLinkSource);
$assert(!$badLinkSourceResult['valid']
    && $hasIssue($badLinkSourceResult, 'study_link', 'fixture-link-q1', 'unknown source: missing-source'),
    'An invalid question-to-material source reference must identify the link and source ID.');

$unknownQuestion = $validFixture;
$unknownQuestion['study-question-links'][0]['question_id'] = 'missing-question';
$unknownQuestionResult = StudyLibraryService::validateSnapshot($unknownQuestion);
$assert(!$unknownQuestionResult['valid']
    && $hasIssue($unknownQuestionResult, 'study_link', 'fixture-link-q1', 'unknown question: missing-question'),
    'A link to an unknown canonical question must fail with the offending link ID.');

$badEligibility = $validFixture;
$badEligibility['question-eligibility'][0]['exam_ids'] = ['nursing'];
$badEligibilityResult = StudyLibraryService::validateSnapshot($badEligibility);
$assert(!$badEligibilityResult['valid']
    && $hasIssue($badEligibilityResult, 'study_link', 'fixture-link-q1', 'eligibility does not include'),
    'An incompatible question/material board relationship must fail visibly.');

$duplicateLink = $validFixture;
$duplicateLink['study-question-links'][] = [
    'id' => 'fixture-link-q1', 'question_id' => 'fixture-q2', 'material_id' => 'fixture-material',
    'source_ids' => ['fixture-professional-standard'],
];
$duplicateLinkResult = StudyLibraryService::validateSnapshot($duplicateLink);
$assert(!$duplicateLinkResult['valid']
    && $hasIssue($duplicateLinkResult, 'study_link', 'fixture-link-q1', 'Study link ID is duplicated'),
    'Duplicate study-link IDs must fail with a specific actionable diagnostic.');

$pdfMaterial = StudyLibraryService::find('let-assessment-review');
$assert(is_array($pdfMaterial)
    && ($pdfMaterial['pdf_url'] ?? '') === '/study-resources/let-assessment-evidence-and-feedback.pdf'
    && is_array($pdfMaterial['lesson'] ?? null)
    && count($pdfMaterial['question_links'] ?? []) > 0,
    'An existing published lesson, PDF, and supported question relationship must remain intact.');

$official = ['authority' => 'CURRENT_OFFICIAL', 'claim' => '60%'];
$secondary = ['authority' => 'CORROBORATED_SECONDARY', 'claim' => '40%'];
if (!ExamIntelligenceService::hasFactConflict([$official, $secondary])
    || (ExamIntelligenceService::resolveFact([$secondary, $official])['claim'] ?? '') !== '60%') {
    throw new RuntimeException('official source precedence is not deterministic');
}

echo '[PASS] Source registry, provenance, exam focus, study materials, and question identity verified.' . PHP_EOL;
