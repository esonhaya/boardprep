<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Core\Database;
use App\Repositories\FlashcardStateRepository;
use App\Services\Flashcard\FlashcardCardKeyBuilder;
use App\Services\Flashcard\FlashcardDerivationService;
use App\Services\Learning\LearnerIdentityService;
use App\Services\Quiz\Result\Attempt\AttemptSessionContext;

$originalSession = $_SESSION ?? null;
$root = sys_get_temp_dir() . '/boardprep-flashcard-' . bin2hex(random_bytes(6));
$path = $root . '/boardprep.sqlite';
mkdir($root, 0777, true);

try {
    $_SESSION = [];
    $_GET = ['user_id' => 'attacker-controlled'];
    $firstLearner = LearnerIdentityService::current();
    if (!preg_match('/\Alearner-[a-f0-9]{32}\z/', $firstLearner)
        || LearnerIdentityService::current() !== $firstLearner) {
        throw new RuntimeException('learner identity was not stable in one session');
    }
    if (LearnerIdentityService::current() === 'attacker-controlled') {
        throw new RuntimeException('request input overrode learner identity');
    }

    $context = AttemptSessionContext::fromSession(['id' => 'quiz-fixed-for-test']);
    if (($context['user_id'] ?? '') !== $firstLearner
        || ($context['user_id'] ?? '') === ($context['session_id'] ?? '')) {
        throw new RuntimeException('quiz session identity was not separated from learner identity');
    }

    $_SESSION = [];
    $secondLearner = LearnerIdentityService::current();
    if ($secondLearner === $firstLearner) {
        throw new RuntimeException('isolated sessions shared learner identity');
    }

    $database = new Database([
        'driver' => 'sqlite',
        'sqlite_path' => $path,
        'path' => $root . '/content',
        'migration_path' => dirname(__DIR__, 2) . '/database/migrations',
    ]);
    if (!$database->usingSqlite()) {
        throw new RuntimeException('flashcard state did not use SQLite mode');
    }
    $repository = new FlashcardStateRepository($database->storage());
    $cardKey = 'q:foundation:concept:test:r1';

    $_SESSION = ['learner_identity' => $firstLearner];
    $firstState = $repository->save([
        'user_id' => 'attacker-controlled',
        'card_key' => $cardKey,
        'question_id' => 'foundation',
        'card_type' => 'concept',
    ]);
    if (($firstState['user_id'] ?? '') !== $firstLearner
        || $repository->find($cardKey)['user_id'] !== $firstLearner) {
        throw new RuntimeException('flashcard state did not enforce current learner scope');
    }

    $_SESSION = ['learner_identity' => $secondLearner];
    if ($repository->find($cardKey) !== null || $repository->all() !== []) {
        throw new RuntimeException('flashcard state leaked across learners');
    }
    $repository->save([
        'user_id' => $firstLearner,
        'card_key' => $cardKey,
        'question_id' => 'foundation',
        'card_type' => 'concept',
        'marker' => 'second-learner',
    ]);
    $_SESSION = ['learner_identity' => $firstLearner];
    if (($repository->find($cardKey)['marker'] ?? '') === 'second-learner') {
        throw new RuntimeException('cross-user write changed another learner state');
    }
    $repository->setSuspended($cardKey, true);
    if (($repository->find($cardKey)['suspended'] ?? false) !== true) {
        throw new RuntimeException('suspension state did not persist');
    }
    if (is_file($root . '/content/flashcard_state.json')) {
        throw new RuntimeException('SQLite flashcard state unexpectedly fell back to JSON');
    }

    $question = \App\Core\App::container()
        ->get(\App\Repositories\QuestionRepository::class)
        ->find('373');
    if ($question === null) {
        throw new RuntimeException('canonical key fixture was unavailable');
    }
    $stable = FlashcardCardKeyBuilder::build($question, 'reasoning', 'r1');
    $wording = $question;
    $wording['question'] .= ' Revised wording.';
    $rationale = $question;
    $rationale['explanation'] .= ' Additional teaching detail.';
    $concept = $question;
    $concept['taxonomy']['concept_id'] = 'different-concept';
    if ($stable !== FlashcardCardKeyBuilder::build($wording, 'reasoning', 'r1')
        || $stable !== FlashcardCardKeyBuilder::build($rationale, 'reasoning', 'r1')
        || $stable === FlashcardCardKeyBuilder::build($concept, 'reasoning', 'r1')
        || $stable === FlashcardCardKeyBuilder::build($question, 'reasoning', 'r2')) {
        throw new RuntimeException('card key stability or revision behavior failed');
    }

    $deriver = \App\Core\App::container()->get(FlashcardDerivationService::class);
    $card = $deriver->forQuestionId('371');
    $repeat = $deriver->forQuestionId('371');
    if ($card === null || $card !== $repeat
        || ($card['answer'] ?? '') === ''
        || ($card['explanation'] ?? '') === ''
        || count($card['study_references'] ?? []) < 1
        || array_key_exists('review_count', $card)) {
        throw new RuntimeException('canonical flashcard derivation was not deterministic or immutable');
    }

    $missingRationale = $question;
    $missingRationale['explanation'] = '';
    $lowValue = $question;
    $lowValue['cognitive_intent'] = 'recall';
    $lowValue['multistep'] = false;
    $lowValue['content_blocks'] = [];
    $unsupported = $question;
    $unsupported['content_blocks'] = [['type' => 'unsupported-media']];
    if ($deriver->derive($missingRationale) !== null
        || $deriver->derive($lowValue) !== null
        || $deriver->derive($unsupported) !== null) {
        throw new RuntimeException('ineligible flashcard source was derived');
    }

    echo "[PASS] learner identity is stable, request-safe, and separate from quiz sessions.\n";
    echo "[PASS] SQLite flashcard state is user-scoped with hostile owner fields ignored.\n";
    echo "[PASS] card keys preserve wording/rationale history and branch on concept/revision changes.\n";
    echo "[PASS] canonical flashcard derivation is deterministic, study-linked, and non-persistent.\n";
} finally {
    $_SESSION = $originalSession;
    if (is_file($path)) {
        @unlink($path);
    }
    @rmdir($root);
}
