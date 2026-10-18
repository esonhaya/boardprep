<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
\App\Core\App::boot();
$assert = static function (bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); };
$storage = \App\Core\App::storage();
$questions = $storage->all('questions');
$ids = array_map(static fn(array $q): string => (string) ($q['id'] ?? ''), $questions);
$assert(count($questions) === 9123 && count($ids) === count(array_unique($ids)), 'Question identities are not unique or the Batch 694 authoring count is unexpected.');
$idSet = array_fill_keys($ids, true);
$assert(array_diff(array_map('strval', range(1, 8962)), array_keys($idSet)) === [], 'A preexisting canonical question identity is missing.');
$boards = \App\Core\App::storage()->all('boards');
$subjectRows = [];
foreach (\App\Services\Board\BoardViewService::all() as $board) foreach ((array) ($board['practice_subjects'] ?? []) as $subject) $subjectRows[strtolower((string) $board['id']) . '|' . strtolower((string) $subject['id'])] = true;
$index = \App\Services\Question\QuestionEligibilityService::index();
$eligibleByScope = [];
foreach ($questions as $q) {
    foreach ($subjectRows as $scope => $_) {
        [$board,$subject] = explode('|',$scope,2);
        $placement = $index->forExam($q,$board);
        if (is_array($placement) && ($placement['subject_id'] ?? '') === $subject && ($q['status'] ?? 'active') === 'active') $eligibleByScope[$scope][] = $q;
    }
}
$assert(count($eligibleByScope['let|values-education'] ?? []) === 100, 'Values Education does not have exactly 100 effective eligible questions.');
$assert(count($eligibleByScope['let|early-childhood-education'] ?? []) === 42, 'Early Childhood Education source-bounded expansion did not route correctly.');
$assert(count($eligibleByScope['let|special-needs-education'] ?? []) === 42, 'Special Needs Education source-bounded expansion did not route correctly.');
$assert(count($eligibleByScope['let|technical-vocational-teacher-education'] ?? []) === 48, 'TVTEd source-bounded expansion did not route correctly.');
$assert(count($eligibleByScope['let|culture-and-arts-education'] ?? []) === 48, 'Culture and Arts Education source-bounded expansion did not route correctly.');
$assert(count($eligibleByScope['let|physical-education'] ?? []) === 48, 'Physical Education source-bounded expansion did not route correctly.');
$assert(count($eligibleByScope['psychometrician|developmental-psychology'] ?? []) === 100, 'Developmental Psychology does not have exactly 100 effective eligible questions.');
$assert(count($eligibleByScope['psychometrician|abnormal-psychology'] ?? []) === 100, 'Abnormal Psychology does not have exactly 100 effective eligible questions.');
$assert(count($eligibleByScope['psychometrician|industrial-organizational'] ?? []) === 100, 'Industrial-Organizational Psychology does not have exactly 100 effective eligible questions.');
$expectedBelow = ['let|early-childhood-education'=>58,'let|special-needs-education'=>58,'let|technical-vocational-teacher-education'=>52,'let|culture-and-arts-education'=>52,'let|physical-education'=>52,'architecture|architecture-utilities-and-construction'=>4];
foreach ($expectedBelow as $scope=>$deficit) $assert(100-count($eligibleByScope[$scope] ?? []) === $deficit, 'Unexpected verified subject deficit for ' . $scope);

$batchItems = [];
foreach ($questions as $q) {
    if (($q['batch_marker'] ?? '') === 'batch694' || (isset($q['id']) && (int) $q['id'] >= 5818 && (int) $q['id'] <= 5852)) $batchItems[] = $q;
}
$assert(count($batchItems) === 196, 'Expected 35 Values rewrites plus 161 new items.');
foreach ($batchItems as $q) {
    $choices = array_values(array_map(static fn(mixed $x): string => trim((string) $x), (array) ($q['choices'] ?? [])));
    $normalized = array_map(static fn(string $x): string => mb_strtolower(preg_replace('/\s+/u',' ',$x) ?? $x), $choices);
    $assert(count($choices) === 4 && count(array_unique($normalized)) === 4, 'Batch 694 question has missing or duplicate choices: ' . (string) $q['id']);
    $assert(in_array((string) ($q['answer'] ?? ''),$choices,true), 'Batch 694 answer key is not one of its choices: ' . (string) $q['id']);
    $assert(trim((string) ($q['explanation'] ?? '')) !== '', 'Batch 694 question has no explanation: ' . (string) $q['id']);
    $assert(!empty($q['source_ids']) && !empty($q['study_material_id']) && !empty($q['study_section_id']), 'Batch 694 item lacks provenance or lesson linkage: ' . (string) $q['id']);
}
$valuesPositions = array_fill(0,4,0);
foreach (array_merge($eligibleByScope['let|values-education'] ?? []) as $q) $valuesPositions[array_search((string) $q['answer'],array_map('strval',(array) $q['choices']),true)]++;
$assert(max($valuesPositions)-min($valuesPositions) <= 1, 'Values key positions are concentrated or unbalanced.');

$lessons = $storage->all('study-lessons');
$lessonById = [];
foreach ($lessons as $lesson) if (is_array($lesson)) $lessonById[(string) ($lesson['id'] ?? '')] = $lesson;
$links = $storage->all('study-question-links');
$linkSet = [];
foreach ($links as $link) $linkSet[(string) ($link['material_id'] ?? '') . '|' . (string) ($link['question_id'] ?? '')] = $link;
$lessonByMaterial = [];
foreach ($lessons as $lesson) if (is_array($lesson)) $lessonByMaterial[(string) ($lesson['material_id'] ?? '')] = $lesson;
$manifest = json_decode((string) file_get_contents(dirname(__DIR__,2) . '/public/study-resources/manifest.json'),true,512,JSON_THROW_ON_ERROR);
$pdfEntries = array_column((array) ($manifest['files'] ?? []),null,'lesson_id');
foreach ($batchItems as $q) {
    $materialId = (string) ($q['study_material_id'] ?? '');
    $lesson = $lessonByMaterial[$materialId] ?? null;
    $sections = [];
    foreach ((array) ($lesson['sections'] ?? []) as $section) if (is_array($section)) $sections[(string) ($section['id'] ?? '')] = true;
    $assert(is_array($lesson) && isset($sections[(string) ($q['study_section_id'] ?? '')]), 'Batch 694 instructional section does not resolve for ' . (string) $q['id']);
    $assert(isset($linkSet[$materialId . '|' . (string) $q['id']]), 'Batch 694 question lacks a question-to-source/lesson link row: ' . (string) $q['id']);
    $pdf = $pdfEntries[(string) ($lesson['id'] ?? '')] ?? null;
    $assert(is_array($pdf) && is_file(dirname(__DIR__,2) . '/' . (string) ($pdf['path'] ?? '')), 'Batch 694 lesson has no downloadable PDF: ' . $materialId);
}
foreach (['let|values-education'=>'let-values-ethical-reasoning-basics','psychometrician|developmental-psychology'=>'psychometrician-developmental-psychology-review'] as $scope=>$lessonId) {
    $lesson = $lessonById[$lessonId] ?? null;
    $assert(is_array($lesson), 'Required subject lesson is missing: ' . $lessonId);
    $sections = [];
    foreach ((array) ($lesson['sections'] ?? []) as $section) if (is_array($section)) $sections[(string) ($section['id'] ?? '')] = $section;
    $assert(count((array) ($lesson['learning_objectives'] ?? [])) >= 3 && count($sections) >= 4 && !empty($lesson['worked_examples']) && !empty($lesson['common_misconceptions']), 'Study lesson lacks substantial instructional structure: ' . $lessonId);
    $subject = explode('|',$scope,2)[1];
    foreach ($eligibleByScope[$scope] as $q) {
        $sectionId = (string) ($q['study_section_id'] ?? '');
        $assert(isset($sections[$sectionId]), 'Question lesson section link does not resolve: ' . (string) $q['id']);
        $assert(isset($linkSet[(string) $lesson['material_id'] . '|' . (string) $q['id']]), 'Question is missing its source/lesson link row: ' . (string) $q['id']);
    }
}
foreach (['let-values-ethical-reasoning-basics','psychometrician-developmental-psychology-review'] as $lessonId) {
    $entry = $pdfEntries[$lessonId] ?? null;
    $assert(is_array($entry) && is_file(dirname(__DIR__,2) . '/' . $entry['path']), 'Subject PDF is missing: ' . $lessonId);
}
foreach ([
    'architecture|architecture-planning-and-practice' => 'architecture-planning-and-practice-decisions',
    'architecture|architecture-history-and-theory' => 'architecture-history-theory-and-conservation',
] as $scope => $lessonId) {
    $lesson = $lessonById[$lessonId] ?? null;
    $assert(is_array($lesson), 'Previously source-only architecture subject lacks its study lesson: ' . $lessonId);
    $sections = [];
    foreach ((array) ($lesson['sections'] ?? []) as $section) if (is_array($section)) $sections[(string) ($section['id'] ?? '')] = true;
    $assert(count($sections) >= 4 && count((array) ($lesson['learning_objectives'] ?? [])) >= 3, 'Architecture lesson is not a substantial instructional resource: ' . $lessonId);
    foreach ($eligibleByScope[$scope] ?? [] as $q) {
        $materialId = (string) ($q['study_material_id'] ?? '');
        $assert($materialId === (string) ($lesson['material_id'] ?? ''), 'Architecture question resolves to the wrong instructional lesson: ' . (string) $q['id']);
        $assert(isset($sections[(string) ($q['study_section_id'] ?? '')]), 'Architecture question instructional section does not resolve: ' . (string) $q['id']);
        $assert(isset($linkSet[$materialId . '|' . (string) $q['id']]), 'Architecture question lacks a source/lesson link row: ' . (string) $q['id']);
    }
    $entry = $pdfEntries[$lessonId] ?? null;
    $assert(is_array($entry) && is_file(dirname(__DIR__,2) . '/' . (string) ($entry['path'] ?? '')), 'Architecture lesson has no downloadable PDF: ' . $lessonId);
}
$expectedMappings = [
    'let|english|professional-education'=>259,
    'registered-electrical-engineering|registered-electrical-engineering-foundation|registered-electrical-engineering-mathematics'=>1569,
    'architecture|architecture-foundation|architecture-history-and-theory'=>1689,
];
foreach ($expectedMappings as $key=>$questionId) {
    $q = null;
    foreach ($questions as $item) if ((string) $item['id'] === (string) $questionId) { $q=$item; break; }
    $assert(is_array($q), 'Subject-routing regression fixture missing: ' . $questionId);
    $parts=explode('|',$key);$placement=$index->forExam($q,$parts[0]);
    $assert(is_array($placement) && ($placement['subject_id']??'')===$parts[2], 'Subject-routing regression remains for question ' . $questionId);
}
$routingFixtures = ['259'=>'professional-education','304'=>'professional-education','319'=>'social-studies'];
foreach ($routingFixtures as $questionId=>$expectedSubject) {
    $q = null;
    foreach ($questions as $item) if ((string) ($item['id'] ?? '') === (string) $questionId) { $q=$item; break; }
    $placement = is_array($q) ? $index->forExam($q, 'let') : null;
    $assert(is_array($placement) && ($placement['subject_id'] ?? '') === $expectedSubject, 'Corrected LET taxonomy placement does not resolve for question ' . $questionId);
    foreach (['let|english','let|mathematics'] as $incorrectScope) {
        $delivered = array_map(static fn(array $candidate): string => (string) ($candidate['id'] ?? ''), $eligibleByScope[$incorrectScope] ?? []);
        $assert(!in_array($questionId, $delivered, true), 'Question ' . $questionId . ' leaks into the unrelated selector ' . $incorrectScope);
    }
}
$valuesQuestion = null;
foreach ($questions as $item) if ((string) ($item['id'] ?? '') === '5818') { $valuesQuestion=$item; break; }
$valuesPlacement = is_array($valuesQuestion) ? $index->forExam($valuesQuestion, 'let') : null;
$assert(is_array($valuesPlacement) && ($valuesPlacement['subject_id'] ?? '') === 'values-education', 'Values Education regression fixture lost its destination scope.');
$englishIds = array_map(static fn(array $candidate): string => (string) ($candidate['id'] ?? ''), $eligibleByScope['let|english'] ?? []);
$assert(!in_array('5818', $englishIds, true), 'Values Education question leaked into English practice.');

echo "[PASS] Batch 694 question identity, scope counts, answer structure, study links, PDFs, and representative routing corrections verified.\n";
