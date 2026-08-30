<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

use App\Services\Learning\StudyActionService;
use App\Services\Learning\StudyPlanService;
use App\Services\Quiz\Start\QuizStartInputNormalizer;

$action = StudyActionService::create([
    'topic' => ['Grammar'],
    'subject' => ['English'],
    'mode' => 'bad',
    'difficulty' => 'bad',
    'count' => 'bad',
]);
$query = [];
parse_str((string) parse_url($action['url'], PHP_URL_QUERY), $query);
$normalized = QuizStartInputNormalizer::normalize($query);

if (($action['topic'] ?? '') !== '' || ($action['subject'] ?? '') !== 'English') {
    throw new RuntimeException('malformed recommendation context leaked into action');
}
if ($normalized['subject'] !== 'English' || $normalized['topics'] !== [] || $normalized['difficulty'] !== 'mixed') {
    throw new RuntimeException('fallback action did not normalize to a usable quiz');
}

$topicAction = StudyActionService::create([
    'topic' => 'Grammar',
    'subject' => 'English',
    'mode' => 'exam',
    'difficulty' => 'hard',
    'count' => 7,
]);
parse_str((string) parse_url($topicAction['url'], PHP_URL_QUERY), $topicQuery);
if ($topicQuery['topic'] !== 'Grammar' || $topicQuery['subject'] !== 'English'
    || $topicQuery['mode'] !== 'exam' || $topicQuery['difficulty'] !== 'hard'
    || (int) $topicQuery['count'] !== 7) {
    throw new RuntimeException('recommended action lost quiz context');
}

$board = ['version' => 1, 'subjects' => [['subject' => 'English', 'percentage' => 100]]];
$subjects = ['English' => [
    'version' => 1,
    'domains' => [['domain' => 'Grammar', 'percentage' => 100]],
    'difficulty' => ['easy' => 50, 'hard' => 50],
]];
$questions = [
    ['id' => 'g1', 'subject' => 'English', 'domain' => 'Grammar', 'topic' => 'Grammar', 'difficulty' => 'easy', 'status' => 'approved'],
    ['id' => 'g2', 'subject' => 'English', 'domain' => 'Grammar', 'topic' => 'Grammar', 'difficulty' => 'hard', 'status' => 'approved'],
    ['id' => 'r1', 'subject' => 'English', 'domain' => 'Grammar', 'topic' => 'Reading', 'difficulty' => 'easy', 'status' => 'approved'],
];
$spec = \App\Services\Quiz\Start\QuizStartSpecificationFactory::create($topicQuery);
$result = \BlueprintExecutor::execute($questions, $board, $subjects, $spec);
if (count($result->questions) !== 1 || ($result->questions[0]['topic'] ?? '') !== 'Grammar'
    || ($result->questions[0]['difficulty'] ?? '') !== 'hard') {
    throw new RuntimeException('recommended topic/difficulty did not reach question generation: ' . json_encode($result->questions));
}

$fallback = StudyPlanService::build([])[0] ?? [];
if (!str_starts_with((string) ($fallback['action'] ?? ''), '/quiz?action=start')) {
    throw new RuntimeException('empty-state study plan action is not launchable');
}

echo "[PASS] recommended and empty-state actions normalize to launchable quiz inputs.\n";
echo "[PASS] recommended topic, subject, and difficulty reach question generation.\n";
