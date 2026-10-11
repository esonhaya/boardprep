<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\Learning\StudyPlanService;
use App\Services\Learning\StudyRecommendationService;
use App\Services\Learning\SubjectPerformanceService;
use App\Services\Learning\TopicPerformanceService;
use App\Services\Quiz\Start\QuizStartInputNormalizer;

$attempts = [
    ['id' => 'ce-a', 'board' => 'CE', 'subject' => 'engineering-math', 'topic' => 'statics', 'percentage' => 40, 'score' => 2, 'total' => 5],
    ['id' => 'me-a', 'board' => 'ME', 'subject' => 'engineering-math', 'topic' => 'statics', 'percentage' => 100, 'score' => 5, 'total' => 5],
    ['id' => 'cse-a', 'board' => 'CSE', 'civil_service_level' => 'SUBPROFESSIONAL', 'subject' => 'general-information', 'topic' => 'public-service', 'percentage' => 30, 'score' => 1, 'total' => 5],
    ['id' => 'let-a', 'board' => 'LET', 'let_level' => 'ELEMENTARY', 'subject' => 'professional-education', 'topic' => 'child-development', 'percentage' => 40, 'score' => 2, 'total' => 5],
    ['id' => 'let-b', 'board' => 'LET', 'let_level' => 'SECONDARY', 'let_specialization_id' => 'secondary-english', 'subject' => 'professional-education', 'topic' => 'child-development', 'percentage' => 50, 'score' => 2, 'total' => 5],
];

$topics = TopicPerformanceService::summarize($attempts);
$sameTopic = array_values(array_filter(
    $topics,
    static fn(array $row): bool => ($row['subject'] ?? '') === 'engineering-math'
        && ($row['topic'] ?? '') === 'statics'
));
if (count($sameTopic) !== 2) {
    throw new RuntimeException('Identical topics from different boards were merged.');
}
$byBoard = [];
foreach ($sameTopic as $row) {
    $byBoard[$row['board'] ?? ''] = $row;
}
if (($byBoard['CE']['average'] ?? null) !== 40 || ($byBoard['ME']['average'] ?? null) !== 100) {
    throw new RuntimeException('Board-specific topic accuracy was not retained.');
}

$subjects = SubjectPerformanceService::summarize($attempts);
$sameSubject = array_values(array_filter(
    $subjects,
    static fn(array $row): bool => ($row['subject'] ?? '') === 'engineering-math'
));
if (count($sameSubject) !== 2) {
    throw new RuntimeException('Identical subjects from different boards were merged.');
}
$sameLetTopic = array_values(array_filter(
    $topics,
    static fn(array $row): bool => ($row['board'] ?? '') === 'LET'
        && ($row['subject'] ?? '') === 'professional-education'
        && ($row['topic'] ?? '') === 'child-development'
));
if (count($sameLetTopic) !== 2) {
    throw new RuntimeException('LET candidate variants were merged in topic analytics.');
}

$weakest = TopicPerformanceService::weakest($attempts, 3);
$recommendations = StudyRecommendationService::build($attempts, $weakest, 3);
$recommendationByBoard = [];
foreach ($recommendations as $recommendation) {
    parse_str((string) parse_url((string) ($recommendation['action'] ?? ''), PHP_URL_QUERY), $query);
    $board = (string) ($recommendation['board'] ?? '');
    if ($board === '' || ($query['exam'] ?? '') !== $board) {
        throw new RuntimeException('Weak-topic recommendation did not preserve its board.');
    }
    $normalized = QuizStartInputNormalizer::normalize($query);
    if (($normalized['board'] ?? '') !== $board) {
        throw new RuntimeException('Weak-topic action does not launch its displayed board.');
    }
    if ($board === 'CSE' && ($normalized['civil_service_level'] ?? '') !== 'SUBPROFESSIONAL') {
        throw new RuntimeException('Weak-topic action lost the Civil Service level.');
    }
    if ($board === 'LET' && ($normalized['let_level'] ?? '') !== 'ELEMENTARY') {
        throw new RuntimeException('Weak-topic action lost the LET candidate level.');
    }
    $recommendationByBoard[$board] = $recommendation;
}

$plan = StudyPlanService::build([
    'weakestTopics' => [$byBoard['CE']],
    'recommendations' => [],
]);
parse_str((string) parse_url((string) ($plan[0]['action'] ?? ''), PHP_URL_QUERY), $planQuery);
if (($plan[0]['board'] ?? '') !== 'CE' || ($planQuery['exam'] ?? '') !== 'CE') {
    throw new RuntimeException('Study plan did not preserve the weak-topic board.');
}
$multiBoardPlan = StudyPlanService::build([
    'weakestTopics' => [$byBoard['CE']],
    'recommendations' => [[
        'topic' => 'statics',
        'subject' => 'engineering-math',
        'board' => 'ME',
        'average' => 100,
    ]],
]);
if (count($multiBoardPlan) !== 2) {
    throw new RuntimeException('Study plan collapsed a matching topic from another board.');
}
parse_str((string) parse_url((string) ($multiBoardPlan[1]['action'] ?? ''), PHP_URL_QUERY), $multiBoardQuery);
if (($multiBoardPlan[1]['board'] ?? '') !== 'ME' || ($multiBoardQuery['exam'] ?? '') !== 'ME') {
    throw new RuntimeException('Study plan recommendation did not launch its own board.');
}

echo "[PASS] Weak-topic and subject analytics stay board-scoped; recommendations and plans launch the matching board and variant.\n";
