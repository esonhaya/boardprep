<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';

use App\Services\Shared\ContentPriorityService;
use App\Services\Shared\QuestionValidationService;
use Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator;

$root = dirname(__DIR__, 2);
$assert = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
};
$questions = json_decode((string) file_get_contents($root . '/storage/questions.json'), true, 512, JSON_THROW_ON_ERROR);
$new = array_values(array_filter($questions, static fn(array $q): bool => (int) ($q['id'] ?? 0) >= 2018 && (int) ($q['id'] ?? 0) <= 2083));
$assert(count($new) === 66, 'Grammar wave must contain 66 new questions');
$positions = [0, 0, 0, 0];
$topicCounts = [];
foreach ($new as $question) {
    $id = (int) $question['id'];
    $assert(QuestionValidationService::validate($question)['valid'], 'Grammar question failed validation: ' . $id);
    $taxonomy = $question['taxonomy'] ?? [];
    $assert(($taxonomy['board_id'] ?? '') === 'let' && ($taxonomy['subject_id'] ?? '') === 'english' && ($taxonomy['domain_id'] ?? '') === 'grammar', 'Grammar ownership is invalid: ' . $id);
    $assert(($question['context_dependent'] ?? false) === true && ($question['context_necessary'] ?? false) === true, 'Grammar context metadata missing: ' . $id);
    $assert(($question['multistep'] ?? false) === true && ($question['cognitive_intent'] ?? '') === 'analyze', 'Grammar cognitive metadata missing: ' . $id);
    $position = array_search($question['answer'], $question['choices'], true);
    $assert($position !== false, 'Grammar answer is absent: ' . $id);
    $positions[$position]++;
    $topicCounts[$taxonomy['topic_id']] = ($topicCounts[$taxonomy['topic_id']] ?? 0) + 1;
}
$assert($positions === [17, 17, 16, 16], 'Grammar answer positions are not balanced');
$assert($topicCounts === ['modifiers'=>9,'sentence-structure'=>8,'subject-verb-agreement'=>9,'verb-tenses'=>10,'pronouns'=>8,'parallelism'=>8,'prepositions'=>6,'parts-of-speech'=>8], 'Grammar topic distribution changed');
$links = json_decode((string) file_get_contents($root . '/storage/study-question-links.json'), true, 512, JSON_THROW_ON_ERROR);
$linkIds = array_fill_keys(array_map(static fn(array $x): string => (string) ($x['question_id'] ?? ''), $links), true);
foreach ($new as $question) $assert(isset($linkIds[(string) $question['id']]), 'Grammar question is unlinked: ' . $question['id']);
$material = json_decode((string) file_get_contents($root . '/storage/study-materials.json'), true, 512, JSON_THROW_ON_ERROR);
$foundation = array_values(array_filter($material, static fn(array $x): bool => ($x['id'] ?? '') === 'let-english-grammar-foundation'));
$assert(count($foundation) === 1 && ($foundation[0]['technical_support_state'] ?? '') === 'ADEQUATE', 'Grammar foundation is missing or inadequate');
$report = ContentPriorityService::analyzeRepository();
$assert(($report['portfolio']['question_count'] ?? 0) === 2083, 'Portfolio count did not update');
$assert(($report['portfolio']['eligible_question_count'] ?? 0) === 2083, 'Grammar wave is not fully eligible');
$assert(($report['portfolio']['linked_question_count'] ?? 0) === 2083, 'Grammar wave is not fully linked');
$assert(($report['integrity']['portfolio']['total_invalid_links'] ?? 1) === 0, 'Grammar wave introduced invalid links');
$assert(($report['integrity']['portfolio']['total_invalid_taxonomy_refs'] ?? 1) === 0, 'Grammar wave introduced invalid taxonomy');
$assert(($report['integrity']['portfolio']['total_invalid_answer_keys'] ?? 1) === 0, 'Grammar wave introduced invalid keys');
$grammarPriorities = array_filter($report['priorities'] ?? [], static fn(array $p): bool => str_starts_with((string) ($p['target'] ?? ''), 'let|english|'));
$assert($grammarPriorities === [], 'Grammar scarcity priorities remain after depth wave');
$simulator = new HttpSimulator($root . '/public/index.php');
$response = $simulator->request('GET', '/developer/content-intelligence');
$html = (string) ($response['output'] ?? '');
$assert(($response['status'] ?? 0) === 200 && ($response['exitCode'] ?? 1) === 0, 'Developer report failed');
$assert(str_contains($html, 'LET English Reading Fit') && str_contains($html, '>2018<') && str_contains($html, 'modifiers') && str_contains($html, 'ADEQUATE'), 'Developer report omitted grammar depth/source state');
echo "[PASS] LET English grammar source, depth, eligibility, links, cards, and priority closure verified.\n";
