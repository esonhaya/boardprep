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
    throw new RuntimeException('source or study library validation failed');
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
if (count($cse) !== 8 || count($let) !== 36 || !isset($constitution['exam_focus']['civil-service'])) {
    throw new RuntimeException('exam-specific study focus mapping is incomplete');
}
$questions = \App\Core\App::storage()->all('questions');
$ids = array_map(static fn(array $question): string => (string) ($question['id'] ?? ''), $questions);
if ($questions === [] || count(array_unique($ids)) !== count($questions)) {
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

$official = ['authority' => 'CURRENT_OFFICIAL', 'claim' => '60%'];
$secondary = ['authority' => 'CORROBORATED_SECONDARY', 'claim' => '40%'];
if (!ExamIntelligenceService::hasFactConflict([$official, $secondary])
    || (ExamIntelligenceService::resolveFact([$secondary, $official])['claim'] ?? '') !== '60%') {
    throw new RuntimeException('official source precedence is not deterministic');
}

echo '[PASS] Source registry, provenance, exam focus, study materials, and question identity verified.' . PHP_EOL;
