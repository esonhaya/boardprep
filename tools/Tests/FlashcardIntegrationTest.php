<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';

use Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator;

function integrationCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function integrationHidden(string $html, string $name): string
{
    preg_match('/name="' . preg_quote($name, '/') . '"\s+value="([^"]+)"/s', $html, $match);
    integrationCheck(isset($match[1]), "missing {$name}");

    return html_entity_decode($match[1]);
}

function integrationLocation(array $response): array
{
    integrationCheck(is_string($response['location'] ?? null), 'expected redirect location');
    $parsed = parse_url($response['location']);
    parse_str((string) ($parsed['query'] ?? ''), $query);

    return [(string) ($parsed['path'] ?? '/'), $query];
}

$root = sys_get_temp_dir() . '/boardprep-integration-' . bin2hex(random_bytes(6));
$sqlitePath = $root . '/integration.sqlite';
mkdir($root, 0777, true);
putenv('DB_DRIVER=sqlite');
putenv('DB_SQLITE_PATH=' . $sqlitePath);
putenv('BOARDPREP_SIMULATION_SEED=669531');

try {
    $questions = json_decode(
        (string) file_get_contents(dirname(__DIR__, 2) . '/storage/questions.json'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    $simulator = new HttpSimulator(dirname(__DIR__, 2) . '/public/index.php');
    $learner = ['PHPSESSID' => 'batch531-integration-a'];
    $settings = $simulator->request('GET', '/quiz', [], [], [], $learner);
    $csrfToken = integrationHidden($settings['output'], 'csrf_token');

    $start = $simulator->request('POST', '/quiz', [], [
        'csrf_token' => $csrfToken,
        'action' => 'start',
        'exam' => 'LET',
        'subject' => 'social-studies',
        'topic' => 'let-art-interpretation',
        'mode' => 'exam',
        'count' => 5,
        'difficulty' => 'mixed',
    ], [], $learner);
    $startMessage = '';
    if ($start['status'] !== 200) {
        $settingsPage = $simulator->request('GET', '/quiz', [], [], [], $learner)['output'] ?? '';
        preg_match('/class="alert alert-danger"[^>]*>(.*?)<\/p>/s', $settingsPage, $match);
        $startMessage = html_entity_decode(strip_tags((string) ($match[1] ?? '')), ENT_QUOTES, 'UTF-8');
    }
    integrationCheck(
        $start['status'] === 200,
        'quiz did not start: HTTP ' . $start['status'] . ' ' . ($start['location'] ?? '') . ' ' . $startMessage
    );
    $questionId = integrationHidden($start['output'], 'question_id');
    $question = null;
    foreach ($questions as $candidate) {
        if (is_array($candidate) && (string) ($candidate['id'] ?? '') === $questionId) {
            $question = $candidate;
            break;
        }
    }
    integrationCheck(is_array($question), 'quiz question was not canonical');
    $choices = is_array($question['choices'] ?? null) ? $question['choices'] : [];
    $correctIndex = array_search($question['answer'] ?? null, $choices, true);
    $wrongIndex = $correctIndex === false ? 0 : (($correctIndex + 1) % max(1, count($choices)));
    $answer = chr(65 + $wrongIndex);

    $submitted = $simulator->request('POST', '/quiz', ['action' => 'submit'], [
        'csrf_token' => $csrfToken,
        'action' => 'submit',
        'question_id' => $questionId,
        'answer' => $answer,
    ], [], $learner);
    $nextQuestionId = $submitted['status'] === 200
        ? integrationHidden($submitted['output'], 'question_id')
        : '';
    integrationCheck(
        $submitted['status'] === 200 && $nextQuestionId !== $questionId,
        'exam-style answer did not advance to the next question'
    );
    $finished = $simulator->request('POST', '/quiz', [], ['action' => 'finish', 'csrf_token' => $csrfToken], [], $learner);
    integrationCheck($finished['status'] === 303, 'quiz finish did not use PRG');

    $result = $simulator->request('GET', '/quiz', ['action' => 'result'], [], [], $learner);
    integrationCheck(
        $result['status'] === 200 && str_contains($result['output'], 'Review missed concepts'),
        'quiz result did not expose missed-concept review: ' . $result['status'] . ' ' . substr($result['output'], 0, 120) . ' ' . substr($result['stderr'], -240)
    );

    $missed = $simulator->request('GET', '/flashcards/missed', [], [], [], $learner);
    [$missedPath, $missedQuery] = integrationLocation($missed);
    integrationCheck($missedPath === '/flashcards/review' && ($missedQuery['mode'] ?? '') === 'missed', 'missed entry was not scoped');
    $missedReview = $simulator->request('GET', $missedPath, $missedQuery, [], [], $learner);
    integrationCheck($missedReview['status'] === 200 && str_contains($missedReview['output'], 'Recall before revealing'), 'missed review did not render');
    preg_match('/card_key=([^&"]+)/', $missedReview['output'], $revealCard);
    integrationCheck(
        isset($revealCard[1]),
        'missed review did not provide a card key for canonical question ' . $questionId
            . ': ' . substr(strip_tags((string) ($missedReview['output'] ?? '')), 0, 240)
    );
    $missedCardKey = rawurldecode($revealCard[1]);

    $reveal = $simulator->request('GET', '/flashcards/review', [
        'mode' => 'missed',
        'card_key' => $missedCardKey,
        'reveal' => 1,
    ], [], [], $learner);
    integrationCheck($reveal['status'] === 200 && str_contains($reveal['output'], 'flashcard-answer'), 'missed answer did not reveal');
    $reviewPost = $simulator->request('POST', '/flashcards/review', [], [
        'csrf_token' => integrationHidden($reveal['output'], 'csrf_token'),
        'card_key' => integrationHidden($reveal['output'], 'card_key'),
        'rating' => 'good',
        'user_id' => 'foreign-user',
    ], [], $learner);
    integrationCheck($reviewPost['status'] === 303, 'missed review did not use PRG');

    $weakness = $simulator->request('GET', '/flashcards/review', ['mode' => 'weakness'], [], [], $learner);
    integrationCheck($weakness['status'] === 200 && str_contains($weakness['output'], 'Recall before revealing'), 'weakness entry did not render');

    foreach (['/dashboard', '/progress', '/study'] as $path) {
        $page = $simulator->request('GET', $path, [], [], [], $learner);
        integrationCheck($page['status'] === 200 && str_contains($page['output'], '/flashcards'), "{$path} omitted flashcard action");
    }

    $foreignMissed = $simulator->request('GET', '/flashcards/missed', ['attempt_id' => 'foreign-attempt'], [], [], ['PHPSESSID' => 'batch531-integration-b']);
    integrationCheck($foreignMissed['status'] === 303, 'foreign attempt input was not rejected');
    $pdo = new PDO('sqlite:' . $sqlitePath);
    $stateCount = (int) $pdo->query("SELECT COUNT(*) FROM storage_records WHERE collection = 'flashcard_state'")->fetchColumn();
    integrationCheck($stateCount === 1, 'integrated review did not create one state record');

    echo "[PASS] Quiz miss -> result action -> missed flashcard -> reveal -> rating journey completed through public/index.php.\n";
    echo "[PASS] Weakness, dashboard, progress, study, PRG, and foreign-attempt boundaries verified.\n";
} finally {
    putenv('DB_DRIVER');
    putenv('DB_SQLITE_PATH');
    putenv('BOARDPREP_SIMULATION_SEED');
    if (is_file($sqlitePath)) {
        @unlink($sqlitePath);
    }
    @rmdir($root);
}
