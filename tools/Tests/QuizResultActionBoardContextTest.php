<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\Quiz\QuizResultActionService;
use App\Services\Quiz\Start\QuizStartInputNormalizer;

$boards = ['LET', 'CSE', 'NLE', 'CLE', 'CE', 'ME', 'REE', 'ARCH', 'PLE', 'CPA'];
foreach ($boards as $board) {
    $session = [
        'board' => $board,
        'subject' => 'board-specific-subject',
        'topics' => ['board-specific-topic'],
        'mode' => 'practice',
        'difficulty' => 'mixed',
        'question_count' => 5,
    ];
    if ($board === 'CSE') {
        $session['civil_service_level'] = 'SUBPROFESSIONAL';
    }
    if ($board === 'LET') {
        $session['let_level'] = 'ELEMENTARY';
        $session['let_specialization_id'] = 'elementary-early-childhood-education';
    }

    $actions = QuizResultActionService::build($session, ['percentage' => 40]);
    parse_str((string) parse_url((string) ($actions[0]['url'] ?? ''), PHP_URL_QUERY), $query);
    $normalized = QuizStartInputNormalizer::normalize($query);
    if (($query['exam'] ?? '') !== $board || ($normalized['board'] ?? '') !== $board) {
        throw new RuntimeException('Result retry lost board context for ' . $board . '.');
    }
    if ($board === 'CSE' && ($normalized['civil_service_level'] ?? '') !== 'SUBPROFESSIONAL') {
        throw new RuntimeException('Civil Service retry lost its level context.');
    }
    if ($board === 'LET' && ($normalized['let_level'] ?? '') !== 'ELEMENTARY') {
        throw new RuntimeException('LET retry lost its candidate-level context.');
    }
    if ($board === 'LET' && ($normalized['let_specialization'] ?? '') !== 'elementary-early-childhood-education') {
        throw new RuntimeException('LET retry lost its specialization context.');
    }
}

echo "[PASS] Result retry actions preserve all ten board contexts and LET/CSE variants.\n";
