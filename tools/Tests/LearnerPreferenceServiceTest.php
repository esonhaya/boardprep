<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Services\Board\BoardViewService;
use App\Services\Profile\LearnerPreferenceService;

$_SESSION = [];
$boards = BoardViewService::all();
$let = null;
foreach ($boards as $board) if (strtoupper((string) ($board['code'] ?? '')) === 'LET') $let = $board;
if (!is_array($let)) throw new RuntimeException('The LET fixture board is unavailable.');
$english = null;
foreach ((array) ($let['practice_subjects'] ?? []) as $subject) if (strtolower((string) ($subject['id'] ?? '')) === 'english') $english = $subject;
if (!is_array($english)) throw new RuntimeException('The LET English fixture subject is unavailable.');

$saved = LearnerPreferenceService::save([
    'preferred_board' => 'LET',
    'preferred_subject' => 'english',
    'let_level' => 'ELEMENTARY',
    'civil_service_level' => 'PROFESSIONAL',
]);
if (($saved['preferred_board'] ?? '') !== 'LET' || ($saved['preferred_subject'] ?? '') !== 'english') {
    throw new RuntimeException('The validated anonymous learner preference was not saved.');
}
$defaults = LearnerPreferenceService::applyToQuizInput([]);
if (($defaults['exam'] ?? '') !== 'LET' || ($defaults['subject'] ?? '') !== 'english' || ($defaults['let_level'] ?? '') !== 'ELEMENTARY') {
    throw new RuntimeException('Saved preferences were not applied to an unscoped learner journey.');
}
$explicitOtherBoard = LearnerPreferenceService::applyToQuizInput(['exam' => 'NLE']);
if (isset($explicitOtherBoard['subject'])) {
    throw new RuntimeException('A stored subject preference overrode an explicit different board.');
}

foreach ([
    ['preferred_board' => 'not-a-board'],
    ['preferred_board' => 'NLE', 'preferred_subject' => '__invalid_subject__'],
    ['let_level' => 'SECONDARY'],
    ['civil_service_level' => 'ASSOCIATE'],
] as $invalid) {
    try {
        LearnerPreferenceService::save($invalid);
        throw new RuntimeException('An incompatible or invalid preference combination was accepted.');
    } catch (InvalidArgumentException) {
        // Expected: invalid preferences do not replace the previous profile.
    }
}
if ((LearnerPreferenceService::get()['preferred_subject'] ?? '') !== 'english') {
    throw new RuntimeException('A rejected preference submission replaced the existing choices.');
}
echo "[PASS] Anonymous learner preferences are validated, durable within session scope, board-aware, and overridden only by explicit context.\n";
