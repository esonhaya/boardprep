<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';

use App\Services\Shared\QuestionValidationService;
use App\Services\Shared\ContentPriorityService;
use Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator;

$assert = static function (bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException($message); }
};
$questions = json_decode(file_get_contents(dirname(__DIR__, 2) . '/storage/questions.json'), true, 512, JSON_THROW_ON_ERROR);
$new = array_values(array_filter($questions, static fn(array $q): bool => (int)($q['id'] ?? 0) >= 1970 && (int)($q['id'] ?? 0) <= 2017));
$assert(count($new) === 48, 'LET English wave must contain 48 questions');
$positions = [0, 0, 0, 0];
$passages = [];
foreach ($new as $question) {
    $assert(QuestionValidationService::validate($question)['valid'], 'New LET English question failed validation: ' . $question['id']);
    $assert(($question['taxonomy']['board_id'] ?? '') === 'let' && ($question['taxonomy']['subject_id'] ?? '') === 'english', 'New question has wrong ownership');
    $assert(($question['passage_dependent'] ?? false) === true && ($question['passage_necessary'] ?? false) === true, 'New question is not marked passage-dependent and necessary');
    $assert(($question['multistep'] ?? false) === true, 'New question is not multistep');
    $position = array_search($question['answer'], $question['choices'], true);
    $assert($position !== false, 'New question answer is missing from choices');
    $positions[$position]++;
    $passages[] = explode("\n\n", (string)$question['question'])[1] ?? '';
}
$assert($positions === [12, 12, 12, 12], 'New answer positions are not balanced');
$assert(count(array_unique($passages)) === 12, 'New wave did not use distinct original passages');
$report = ContentPriorityService::analyzeRepository();
$assert(($report['portfolio']['question_count'] ?? 0) === 2083, 'Portfolio count did not update after the grammar wave');
$english = array_filter($report['content_depth']['topics'] ?? [], static fn(array $row): bool => ($row['board_id'] ?? '') === 'let' && ($row['subject_id'] ?? '') === 'english');
$target = array_values(array_filter($english, static fn(array $row): bool => ($row['topic_id'] ?? '') === 'english-inference'));
$assert(count($target) === 1 && ($target[0]['question_count'] ?? 0) === 5, 'English inference depth did not update');
$assert(($target[0]['source_support_state'] ?? '') === 'ADEQUATE', 'English inference source support is not adequate');
$http = new HttpSimulator(dirname(__DIR__, 2) . '/public/index.php');
$response = $http->request('GET', '/developer/content-intelligence');
$assert(($response['status'] ?? 0) === 200 && ($response['exitCode'] ?? 1) === 0, 'Developer report did not render');
$assert(str_contains((string)$response['output'], 'LET English Reading Fit') && str_contains((string)$response['output'], '1970'), 'Developer report omitted English fit/new IDs');
echo "[PASS] LET English semantic/source/depth wave verified.\n";
