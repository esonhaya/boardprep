<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Core\App;
use App\Repositories\BoardRepository;
use App\Repositories\BoardSubjectRepository;
use App\Repositories\QuestionRepository;
use App\Services\Board\BoardViewService;
use App\Services\Shared\QuestionCoverageService;
use App\Services\Shared\QuestionValidationService;
use App\Services\Shared\TaxonomyStorageService;

$questions = (new QuestionRepository(App::storage()))->all();
$boards = TaxonomyStorageService::boards();
$subjects = array_column(TaxonomyStorageService::subjects(), null, 'id');
$domains = array_column(TaxonomyStorageService::domains(), null, 'id');
$topics = array_column(TaxonomyStorageService::topics(), null, 'id');
$concepts = array_column(TaxonomyStorageService::concepts(), null, 'id');
$relations = TaxonomyStorageService::boardSubjects();
$ids = [];
$stems = [];
$let = array_values(array_filter($questions, static fn(array $q): bool => ($q['taxonomy']['board_id'] ?? $q['board'] ?? '') === 'let'));

if (count($let) < 80) {
    throw new RuntimeException('LET foundation must contain at least 80 questions');
}
foreach ($let as $question) {
    $id = (string) ($question['id'] ?? '');
    $stem = strtolower(trim((string) ($question['question'] ?? '')));
    if ($id === '' || isset($ids[$id]) || $stem === '' || isset($stems[$stem])) {
        throw new RuntimeException('LET question IDs and stems must be unique');
    }
    $ids[$id] = true;
    $stems[$stem] = true;
    if (!QuestionValidationService::validate($question)['valid']) {
        throw new RuntimeException('LET question failed production validation: ' . $id);
    }
    $taxonomy = $question['taxonomy'] ?? [];
    foreach (['board_id', 'subject_id', 'domain_id', 'topic_id', 'concept_id'] as $key) {
        if (!isset($taxonomy[$key])) {
            throw new RuntimeException('LET taxonomy is incomplete: ' . $id);
        }
    }
    if (($domains[$taxonomy['domain_id']]['subject_id'] ?? null) !== $taxonomy['subject_id']
        || ($topics[$taxonomy['topic_id']]['domain_id'] ?? null) !== $taxonomy['domain_id']
        || ($concepts[$taxonomy['concept_id']]['topic_id'] ?? null) !== $taxonomy['topic_id']) {
        throw new RuntimeException('LET taxonomy hierarchy is inconsistent: ' . $id);
    }
}

$distribution = [];
foreach ($let as $question) {
    $subject = $question['taxonomy']['subject_id'];
    $distribution[$subject] = ($distribution[$subject] ?? 0) + 1;
}
if (count($distribution) < 6 || min($distribution) < 4) {
    throw new RuntimeException('LET content is not meaningfully distributed across subjects');
}

$letBoard = (new BoardRepository(App::storage()))->find('let');
$readiness = BoardViewService::find('let')['content_readiness'] ?? [];
if (($readiness['status'] ?? '') !== 'STUDY_READY' || ($readiness['taxonomy_completeness'] ?? 0) !== 100) {
    throw new RuntimeException('LET readiness did not reflect validated content');
}
$cse = BoardViewService::find('civil-service')['content_readiness'] ?? [];
if (($cse['status'] ?? '') !== 'EMPTY') {
    throw new RuntimeException('CSE must remain truthfully empty');
}
$coverage = QuestionCoverageService::analyzeRepository();
if (($coverage['inventory']['eligible'] ?? 0) < 80) {
    throw new RuntimeException('coverage engine did not see the LET foundation');
}

echo '[PASS] LET content, taxonomy integrity, readiness, CSE status, and coverage verified.' . PHP_EOL;
