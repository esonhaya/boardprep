<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Core\App;
use App\Repositories\QuestionRepository;
use App\Services\Shared\QuestionImportService;
use App\Storage\JsonStorage;

$directory = sys_get_temp_dir() . '/boardprep-import-' . getmypid();
mkdir($directory, 0777, true);
$repository = new QuestionRepository(new JsonStorage($directory));
App::container()->bind(QuestionRepository::class, static fn () => $repository);

$valid = [
    'id' => 'import-1',
    'status' => 'active',
    'taxonomy' => ['board_id' => 'let', 'subject_id' => 'english', 'domain_id' => 'grammar', 'topic_id' => 'parts-of-speech', 'concept_id' => 'parts-of-speech-nouns'],
    'difficulty' => 'easy',
    'type' => 'multiple_choice',
    'question' => 'Which imported option is a noun?',
    'options' => [
        ['id' => 'a', 'text' => 'Teacher', 'correct' => true],
        ['id' => 'b', 'text' => 'Quickly', 'correct' => false],
    ],
    'explanation' => 'Teacher names a person.',
];
$invalid = $valid;
$invalid['id'] = 'import-2';
$invalid['explanation'] = '';

$inconsistent = $valid;
$inconsistent['id'] = 'import-taxonomy';
$inconsistent['taxonomy']['subject_id'] = 'mathematics';
$taxonomyResult = QuestionImportService::importJson(json_encode([$inconsistent], JSON_THROW_ON_ERROR));
if (($taxonomyResult['success'] ?? true) !== false || $repository->all() !== []) {
    throw new RuntimeException('inconsistent taxonomy was accepted by import tooling');
}

$aborted = QuestionImportService::importJson(json_encode([$valid, $invalid], JSON_THROW_ON_ERROR));
if (($aborted['success'] ?? true) !== false || $repository->all() !== []) {
    throw new RuntimeException('invalid import batch caused a partial write');
}

$imported = QuestionImportService::importJson(json_encode([$valid], JSON_THROW_ON_ERROR));
if (($imported['success'] ?? false) !== true || count($repository->all()) !== 1) {
    throw new RuntimeException('valid import was not persisted');
}
$collision = QuestionImportService::importJson(json_encode([$valid], JSON_THROW_ON_ERROR));
if (($collision['success'] ?? true) !== false || count($repository->all()) !== 1) {
    throw new RuntimeException('duplicate import ID overwrote existing content');
}

unlink($directory . '/questions.json');
rmdir($directory);

echo "[PASS] imports are validated before writes and reject ID collisions safely.\n";
