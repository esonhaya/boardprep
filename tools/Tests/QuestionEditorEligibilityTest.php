<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Autoloader;
use App\Repositories\QuestionRepository;
use App\Services\Question\QuestionEligibilityIndex;
use App\Services\Question\QuestionEligibilityService;
use Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator;

$root = dirname(__DIR__, 2);
$sourceStorage = $root . '/storage';
$isolatedStorage = sys_get_temp_dir() . '/boardprep-editor-' . bin2hex(random_bytes(6));

$copy = static function (string $source, string $destination) use (&$copy): void {
    if (is_dir($source)) {
        mkdir($destination, 0777, true);
        foreach (scandir($source) ?: [] as $child) {
            if ($child === '.' || $child === '..') continue;
            $copy($source . DIRECTORY_SEPARATOR . $child, $destination . DIRECTORY_SEPARATOR . $child);
        }
        return;
    }
    if (!copy($source, $destination)) {
        throw new RuntimeException('Unable to prepare isolated editor storage.');
    }
};
$remove = static function (string $path) use (&$remove): void {
    if (is_dir($path)) {
        foreach (scandir($path) ?: [] as $child) {
            if ($child === '.' || $child === '..') continue;
            $remove($path . DIRECTORY_SEPARATOR . $child);
        }
        rmdir($path);
        return;
    }
    if (is_file($path)) unlink($path);
};

$copy($sourceStorage, $isolatedStorage);
putenv('APP_STORAGE_PATH=' . $isolatedStorage);
putenv('APP_ENV=testing');

require $root . '/app/Core/Autoloader.php';
Autoloader::register();
require $root . '/bootstrap/app.php';
require $root . '/tools/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';

try {
    $repository = new QuestionRepository(App::storage());
    $question = $repository->find('2664');
    if (!is_array($question)) throw new RuntimeException('Fixture question 2664 is unavailable.');
    $beforeCount = count(App::storage()->all('questions'));

    $target = [
        'target_board' => 'civil-service',
        'target_subject' => 'verbal-ability',
        'target_domain' => 'cse-reading-comprehension-domain',
        'target_topic' => 'cse-reading-comprehension-topic',
        'target_concept' => 'cse-paragraph-organization',
        'blueprint_node' => 'verbal-ability',
    ];
    $valid = QuestionEligibilityService::validateMapping($question, $target);
    if (($valid['valid'] ?? false) !== true) throw new RuntimeException('Valid target mapping was rejected.');

    $http = new HttpSimulator($root . '/public/index.php');
    $cookie = ['PHPSESSID' => 'batch607-editor-' . bin2hex(random_bytes(4))];
    $view = $http->request('GET', '/question-editor/edit', ['id' => '2664'], [], [], $cookie);
    if ($view['status'] !== 200 || !str_contains($view['output'], 'Board Eligibility')
        || !str_contains($view['output'], 'Share with another board')
        || !str_contains($view['output'], 'Duplicate as new question')) {
        throw new RuntimeException('Editor eligibility states did not render.');
    }
    if (preg_match('/name="_csrf" value="([^"]+)"/', $view['output'], $matches) !== 1) {
        throw new RuntimeException('Editor CSRF token did not render.');
    }
    $csrf = $matches[1];

    $forbidden = $http->request('POST', '/question-editor/share', ['id' => '2664'], $target + ['id' => '2664'], [], $cookie);
    if ($forbidden['status'] !== 403 || !str_contains($forbidden['output'], 'Invalid CSRF token')) {
        throw new RuntimeException('Eligibility mutations bypassed CSRF protection.');
    }
    $share = $http->request('POST', '/question-editor/share', ['id' => '2664'], $target + ['id' => '2664', '_csrf' => $csrf], [], $cookie);
    if ($share['status'] !== 302 || !str_contains((string) $share['location'], 'notice=shared')) {
        throw new RuntimeException('Admin share did not redirect successfully.');
    }
    $sharedQuestion = $repository->find('2664');
    $mappings = QuestionEligibilityService::editorMappings($sharedQuestion ?? []);
    if (count($mappings) !== 2 || $beforeCount !== count(App::storage()->all('questions'))) {
        throw new RuntimeException('Share changed canonical identity or did not create one target mapping.');
    }

    $duplicate = $http->request('POST', '/question-editor/share', ['id' => '2664'], $target + ['id' => '2664', '_csrf' => $csrf], [], $cookie);
    if ($duplicate['status'] !== 200 || !str_contains($duplicate['output'], 'DUPLICATE_BOARD_MAPPING')) {
        throw new RuntimeException('Equivalent board mapping was not rejected server-side.');
    }
    $invalid = $http->request('POST', '/question-editor/share', ['id' => '2664'], array_merge($target, ['target_concept' => 'not-a-concept', 'id' => '2664', '_csrf' => $csrf]), [], $cookie);
    if ($invalid['status'] !== 200 || !str_contains($invalid['output'], 'INVALID_CONCEPT_FOR_BOARD')) {
        throw new RuntimeException('Tampered taxonomy was not rejected server-side.');
    }

    $index = new QuestionEligibilityIndex(
        App::storage()->all('question-eligibility'),
        App::storage()->all('blueprints')
    );
    $targetEligibility = QuestionEligibilityService::forExam($sharedQuestion ?? [], 'civil-service', $index);
    if (!is_array($targetEligibility) || ($targetEligibility['subject_id'] ?? '') !== 'verbal-ability') {
        throw new RuntimeException('Shared question did not reach the target selection index.');
    }
    if (QuestionEligibilityService::forExam($sharedQuestion ?? [], 'architecture', $index) !== null) {
        throw new RuntimeException('Shared question leaked to an unrelated board.');
    }
    $selectionRequest = new SelectionRequest('verbal-ability', null, ['hard' => 2], 2, null, null, 'civil-service');
    $selectedPool = QuestionPoolFilter::filter([$sharedQuestion, $sharedQuestion], $selectionRequest);
    if (count(array_values(array_filter($selectedPool, static fn(array $candidate): bool => (string) ($candidate['id'] ?? '') === '2664'))) !== 1) {
        throw new RuntimeException('Multiple eligibility mappings multiplied the canonical question in selection.');
    }

    $placement = $http->request('POST', '/question-editor/placement', ['id' => '2664'], $target + ['id' => '2664', '_csrf' => $csrf], [], $cookie);
    if ($placement['status'] !== 302 || !str_contains((string) $placement['location'], 'placement-updated')) {
        throw new RuntimeException('Placement update did not use the eligibility path.');
    }
    $afterPlacement = QuestionEligibilityService::editorMappings($repository->find('2664') ?? []);
    $letPlacement = array_values(array_filter($afterPlacement, static fn(array $mapping): bool => ($mapping['board_id'] ?? '') === 'let'))[0] ?? [];
    if (($letPlacement['subject_id'] ?? '') !== 'filipino') {
        throw new RuntimeException('Placement update changed another board mapping.');
    }

    $unshare = $http->request('POST', '/question-editor/unshare', ['id' => '2664'], ['target_board' => 'civil-service', '_csrf' => $csrf], [], $cookie);
    if ($unshare['status'] !== 302) throw new RuntimeException('Unshare did not complete.');
    if (count(QuestionEligibilityService::editorMappings($repository->find('2664') ?? [])) !== 1) {
        throw new RuntimeException('Unshare did not preserve the original mapping only.');
    }
    $lastBoard = $http->request('POST', '/question-editor/unshare', ['id' => '2664'], ['target_board' => 'let', '_csrf' => $csrf], [], $cookie);
    if ($lastBoard['status'] !== 200 || !str_contains($lastBoard['output'], 'LAST_BOARD_UNSHARE_PROTECTED')) {
        throw new RuntimeException('Last-board protection is missing.');
    }

    $duplicateDraft = $http->request('POST', '/question-editor/duplicate', ['id' => '2664'], $target + ['id' => '2664', '_csrf' => $csrf], [], $cookie);
    if ($duplicateDraft['status'] !== 200 || !str_contains($duplicateDraft['output'], 'NEW_CANONICAL_ID=will be created')
        || !str_contains($duplicateDraft['output'], 'normal creation workflow')) {
        throw new RuntimeException('Duplicate action did not produce an explicit independent draft.');
    }

    $choices = is_array($question['choices'] ?? null) ? array_values($question['choices']) : [];
    $saveDuplicate = [
        'board' => $target['target_board'],
        'subject' => $target['target_subject'],
        'domain' => $target['target_domain'],
        'topic' => $target['target_topic'],
        'concept' => $target['target_concept'],
        'difficulty' => 'hard',
        'status' => 'draft',
        'type' => 'multiple_choice',
        'question' => $question['question'] . ' (independent classroom variant)',
        'explanation' => $question['explanation'],
        'correct_option' => 'option-1',
        '_csrf' => $csrf,
    ];
    foreach ($choices as $index => $choice) {
        $saveDuplicate['option_' . ($index + 1)] = (string) $choice;
    }
    $created = $http->request('POST', '/question-editor/save', [], $saveDuplicate, [], $cookie);
    if ($created['status'] !== 302 || !str_contains((string) $created['location'], 'saved=1')) {
        throw new RuntimeException('The duplicate draft did not save through normal creation.');
    }
    $createdQuestion = null;
    foreach (App::storage()->all('questions') as $candidate) {
        if (is_array($candidate) && ($candidate['question'] ?? '') === $saveDuplicate['question']) {
            $createdQuestion = $candidate;
            break;
        }
    }
    if (!is_array($createdQuestion) || (string) ($createdQuestion['id'] ?? '') === '2664'
        || count(App::storage()->all('questions')) !== $beforeCount + 1
        || count(QuestionEligibilityService::editorMappings($createdQuestion)) !== 1
        || (QuestionEligibilityService::editorMappings($createdQuestion)[0]['board_id'] ?? '') !== 'civil-service') {
        throw new RuntimeException('Duplicate creation did not create one independently editable target-board question.');
    }
    if (($repository->find('2664')['question'] ?? '') !== ($question['question'] ?? '')) {
        throw new RuntimeException('Saving the duplicate changed the source question.');
    }

    echo "[PASS] Admin board eligibility overview, share, placement, unshare, protection, validation, and duplicate-draft states verified.\n";
} finally {
    $remove($isolatedStorage);
}
