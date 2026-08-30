<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Core\App;
use App\Repositories\QuestionRepository;
use App\Services\Question\QuestionAuthoringService;
use App\Storage\JsonStorage;

$directory = sys_get_temp_dir() . '/boardprep-authoring-' . getmypid();
mkdir($directory, 0777, true);
$repository = new QuestionRepository(new JsonStorage($directory));
App::container()->bind(QuestionRepository::class, static fn () => $repository);

$input = [
    'board_id' => 'let',
    'subject_id' => 'english',
    'domain_id' => 'grammar',
    'topic_id' => 'parts-of-speech',
    'concept_id' => 'parts-of-speech-nouns',
    'difficulty' => 'medium',
    'question' => 'Which option is a concrete noun?',
    'option_1' => 'Quickly',
    'option_2' => 'Teacher',
    'option_3' => 'Bright',
    'option_4' => 'Think',
    'correct_option' => 'option-2',
    'explanation' => 'Teacher names a person and is a concrete noun.',
];

$created = QuestionAuthoringService::submit(0, $input);
if (($created['saved'] ?? false) !== true) {
    throw new RuntimeException('valid authored content did not persist: ' . implode(' ', $created['errors'] ?? []));
}
$id = (string) ($created['persisted']['id'] ?? '');
$reloaded = $repository->find($id);
if ($reloaded === null) {
    throw new RuntimeException('persisted authored content did not reload');
}

$request = new \SelectionRequest('english', 'grammar', ['medium' => 1], 1, 'parts-of-speech');
$eligible = \QuestionSelectionService::fulfillRequest($repository->all(), $request)->questions;
if (count($eligible) !== 1 || (string) $eligible[0]['id'] !== $id) {
    throw new RuntimeException('persisted authored content was not quiz eligible');
}

$invalid = $input;
$invalid['question'] = 'This record must not be written';
$invalid['correct_option'] = 'missing-option';
$rejected = QuestionAuthoringService::submit(0, $invalid);
if (($rejected['saved'] ?? true) !== false || count($repository->all()) !== 1) {
    throw new RuntimeException('invalid authored content changed persisted storage');
}

$stored = json_decode((string) file_get_contents($directory . '/questions.json'), true, 512, JSON_THROW_ON_ERROR);
if (!is_array($stored) || count($stored) !== 1) {
    throw new RuntimeException('question collection was not valid after rejected authoring');
}

unlink($directory . '/questions.json');
rmdir($directory);

echo "[PASS] authoring persists, reloads, reaches quiz selection, and rejects invalid writes.\n";
