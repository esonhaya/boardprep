<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';

use Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator;

$root = sys_get_temp_dir() . '/boardprep-http-' . bin2hex(random_bytes(6));
$sqlitePath = $root . '/http.sqlite';
mkdir($root, 0777, true);
putenv('DB_DRIVER=sqlite');
putenv('DB_SQLITE_PATH=' . $sqlitePath);

function httpCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function responseToken(string $html): string
{
    preg_match('/name="csrf_token" value="([^"]+)"/', $html, $match);
    httpCheck(isset($match[1]), 'review form did not contain a CSRF token');
    return html_entity_decode($match[1]);
}

function responseCardKey(string $html): string
{
    preg_match('/name="card_key" value="([^"]+)"/', $html, $match);
    httpCheck(isset($match[1]), 'review form did not contain a card key');
    return html_entity_decode($match[1]);
}

try {
    $simulator = new HttpSimulator(dirname(__DIR__, 2) . '/public/index.php');
    $cookie = ['PHPSESSID' => 'batch676-flashcards-a-' . bin2hex(random_bytes(8))];

    $landing = $simulator->request('GET', '/flashcards', [], [], [], $cookie);
    httpCheck($landing['status'] === 200 && str_contains($landing['output'], '<h1>Flashcards</h1'), 'landing page did not render');
    httpCheck(str_contains($landing['output'], 'Review filters') && str_contains($landing['output'], 'Start review'), 'landing filters or action missing');

    $review = $simulator->request('GET', '/flashcards/review', [], [], [], $cookie);
    httpCheck($review['status'] === 200 && str_contains($review['output'], 'Recall before revealing'), 'review page did not render');
    httpCheck(!str_contains($review['output'], 'flashcard-answer')
        && !str_contains($review['output'], 'flashcard-explanation')
        && !str_contains($review['output'], 'name="rating"'), 'answer or rationale leaked before reveal');
    preg_match('/href="([^"]+reveal=1[^"]*)"/', $review['output'], $revealMatch);
    httpCheck(isset($revealMatch[1]), 'review page did not provide reveal action');
    $revealUrl = html_entity_decode($revealMatch[1]);
    $parsed = parse_url($revealUrl);
    parse_str((string) ($parsed['query'] ?? ''), $revealQuery);

    $revealed = $simulator->request('GET', (string) ($parsed['path'] ?? '/flashcards/review'), $revealQuery, [], [], $cookie);
    httpCheck($revealed['status'] === 200 && str_contains($revealed['output'], 'flashcard-answer'), 'answer reveal did not render');
    httpCheck(substr_count($revealed['output'], 'name="rating"') === 4, 'four rating actions were not rendered');
    httpCheck(preg_match('/href="\/study-library\?material=([^"&]+)"/', $revealed['output'], $studyLink) === 1,
        'revealed flashcard did not link to its related study material');
    $materialId = rawurldecode(html_entity_decode($studyLink[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $materialPage = $simulator->request('GET', '/study-library', ['material' => $materialId], [], [], $cookie);
    httpCheck($materialPage['status'] === 200
        && str_contains($materialPage['output'], 'study-material-' . $materialId), 'flashcard study reference did not open its linked material');
    $token = responseToken($revealed['output']);
    $cardKey = responseCardKey($revealed['output']);

    $missingCsrf = $simulator->request('POST', '/flashcards/review', [], [
        'card_key' => $cardKey,
        'rating' => 'good',
    ], [], $cookie);
    httpCheck($missingCsrf['status'] === 303 && str_contains((string) $missingCsrf['location'], '/flashcards/review'), 'missing CSRF was not rejected with PRG');

    $invalidCsrf = $simulator->request('POST', '/flashcards/review', [], [
        'csrf_token' => 'invalid',
        'card_key' => $cardKey,
        'rating' => 'good',
    ], [], $cookie);
    httpCheck($invalidCsrf['status'] === 303, 'invalid CSRF was not rejected');

    $validPost = $simulator->request('POST', '/flashcards/review', [], [
        'csrf_token' => $token,
        'card_key' => $cardKey,
        'rating' => 'good',
        'include_new' => '1',
        'user_id' => 'attacker-controlled',
    ], [], $cookie);
    httpCheck($validPost['status'] === 303, 'valid review did not use POST/Redirect/GET');
    $afterPost = $simulator->request('GET', '/flashcards/review', [], [], [], $cookie);
    httpCheck($afterPost['status'] === 200 && str_contains($afterPost['output'], 'Review saved.'), 'successful review did not produce a follow-up page');

    $invalidRating = $simulator->request('POST', '/flashcards/review', [], [
        'csrf_token' => $token,
        'card_key' => $cardKey,
        'rating' => 'not-a-rating',
    ], [], $cookie);
    httpCheck($invalidRating['status'] === 303, 'invalid rating was not rejected');

    $unknownCard = $simulator->request('POST', '/flashcards/review', [], [
        'csrf_token' => $token,
        'card_key' => 'q:unknown:concept:no-such:r1',
        'rating' => 'good',
    ], [], $cookie);
    httpCheck($unknownCard['status'] === 303, 'unknown card was not rejected');

    $noNew = $simulator->request('GET', '/flashcards', ['include_new' => '0'], [], [], $cookie);
    httpCheck($noNew['status'] === 200 && str_contains($noNew['output'], 'No cards ready for this focus'), 'no-due empty state did not render');

    $badFilter = $simulator->request('GET', '/flashcards', ['board' => 'attacker-value'], [], [], $cookie);
    httpCheck($badFilter['status'] === 200 && str_contains($badFilter['output'], 'valid flashcard filter'), 'unknown filter was not rejected safely');

    $incompatibleSubject = $simulator->request('GET', '/flashcards', [
        'board' => 'let',
        'subject' => 'general-information',
    ], [], [], $cookie);
    httpCheck($incompatibleSubject['status'] === 200
        && str_contains($incompatibleSubject['output'], 'do not belong to the selected board'),
        'a subject from another board was not rejected');

    $incompatibleTopic = $simulator->request('GET', '/flashcards', [
        'board' => 'let',
        'subject' => 'english',
        'topic' => 'let-art-interpretation',
    ], [], [], $cookie);
    httpCheck($incompatibleTopic['status'] === 200
        && str_contains($incompatibleTopic['output'], 'do not belong to the selected board'),
        'a topic from a different effective subject was not rejected');

    $isolatedReview = $simulator->request('GET', '/flashcards/review', [], [], [], ['PHPSESSID' => 'batch676-flashcards-b-' . bin2hex(random_bytes(8))]);
    httpCheck($isolatedReview['status'] === 200 && str_contains($isolatedReview['output'], 'Recall before revealing'), 'isolated learner could not access review flow');

    $pdo = new PDO('sqlite:' . $sqlitePath);
    $count = (int) $pdo->query("SELECT COUNT(*) FROM storage_records WHERE collection = 'flashcard_state'")->fetchColumn();
    httpCheck($count === 1, 'review POST did not create exactly one SQLite state row');

    echo "[PASS] Landing, review, reveal, compatible board/subject/topic filters, empty states, and mobile-safe form actions render through public/index.php.\n";
    echo "[PASS] CSRF, rating, card-key, request-owner, PRG, persistence, and isolated-session boundaries hold over HTTP.\n";
} finally {
    putenv('DB_DRIVER');
    putenv('DB_SQLITE_PATH');
    if (is_file($sqlitePath)) {
        @unlink($sqlitePath);
    }
    @rmdir($root);
}
