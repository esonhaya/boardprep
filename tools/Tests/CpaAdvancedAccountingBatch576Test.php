<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';

\App\Core\Autoloader::register();
require dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Core\App;
use App\Services\Flashcard\FlashcardDerivationService;

$root = dirname(__DIR__, 2);
$read = static function (string $relative) use ($root): array {
    $data = json_decode((string) file_get_contents($root . '/' . $relative), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data)) {
        throw new RuntimeException($relative . ' did not contain an array');
    }
    return $data;
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$ids = range(1809, 1828);
$idStrings = array_map('strval', $ids);
$questions = array_values(array_filter(
    $read('storage/questions.json'),
    static fn(array $question): bool => in_array((int) ($question['id'] ?? 0), $ids, true)
));
$assert(count($questions) === 20, 'CPA Advanced Accounting wave does not contain exactly 20 questions');

$expectedTopics = [
    'cpa-advanced-accounting-partnerships',
    'cpa-advanced-accounting-consolidation',
    'cpa-advanced-accounting-intercompany',
    'cpa-advanced-accounting-combinations',
    'cpa-advanced-accounting-branch',
    'cpa-advanced-accounting-foreign-currency',
    'cpa-advanced-accounting-liquidation',
    'cpa-advanced-accounting-joint-arrangements',
    'cpa-advanced-accounting-nci',
    'cpa-advanced-accounting-elimination',
    'cpa-advanced-accounting-profit-allocation',
    'cpa-advanced-accounting-fixed-assets',
    'cpa-advanced-accounting-translation',
    'cpa-advanced-accounting-measurement',
    'cpa-advanced-accounting-withdrawal',
    'cpa-advanced-accounting-dividends',
    'cpa-advanced-accounting-branch-inventory',
    'cpa-advanced-accounting-intercompany-balances',
    'cpa-advanced-accounting-profit-attribution',
    'cpa-advanced-accounting-agreement',
];
$actualTopics = [];
$positionCounts = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0];
$expectedAnswers = [
    1809 => 'P20,000', 1810 => 'P320,000', 1811 => 'P20,000', 1812 => 'P100,000',
    1813 => 'P12,500', 1814 => 'P60,000', 1815 => 'P700,000', 1816 => "Recognize the party's share of the relevant assets, liabilities, income, and expenses",
    1817 => 'P90,000', 1818 => 'P418,000', 1819 => 'P276,000', 1820 => 'P120,000',
    1821 => "The period's average rate", 1822 => 'P40,000', 1823 => 'P16,000',
    1824 => 'P0', 1825 => 'P100,000', 1826 => 'Control may exist because practical power and exposure to variable returns are present',
    1827 => 'P147,000', 1828 => 'A joint operation',
];

foreach ($questions as $question) {
    $id = (int) $question['id'];
    $taxonomy = $question['taxonomy'] ?? [];
    $assert(($question['board'] ?? '') === 'cpa' && ($taxonomy['board_id'] ?? '') === 'cpa', "question {$id} is not CPA-owned");
    $assert(($taxonomy['subject_id'] ?? '') === 'cpa-advanced-accounting', "question {$id} is outside Advanced Accounting");
    $actualTopics[] = $taxonomy['topic_id'] ?? '';
    $assert(($question['status'] ?? '') === 'active', "question {$id} is not active");
    $assert(($question['multistep'] ?? false) === true && ($question['cognitive_intent'] ?? '') !== 'recall', "question {$id} is not application-level");
    $assert(($question['source_ids'] ?? []) === ['prc-accountancy-board-page'], "question {$id} lost its authoritative source trace");
    $assert(is_array($question['choices'] ?? null) && count($question['choices']) === 4, "question {$id} choices are malformed");
    $assert(count(array_unique($question['choices'])) === 4, "question {$id} has duplicate choices");
    $assert(in_array($question['answer'] ?? '', $question['choices'], true), "question {$id} answer is not a choice");
    $assert(($question['answer'] ?? '') === $expectedAnswers[$id], "question {$id} answer or accounting computation changed");
    $position = array_search($question['answer'], $question['choices'], true);
    $positionCounts[chr(65 + $position)]++;
    if (in_array($id, [1810, 1813, 1818, 1827], true)) {
        $assert(count($question['content_blocks'] ?? []) === 1 && ($question['content_blocks'][0]['type'] ?? '') === 'table', "question {$id} table fixture is missing");
    } else {
        $assert(($question['content_blocks'] ?? []) === [], "question {$id} contains an unplanned structured block");
    }
}

$assert($actualTopics === $expectedTopics, 'the wave does not provide one item for each Advanced Accounting topic');
$assert($positionCounts === ['A' => 5, 'B' => 5, 'C' => 5, 'D' => 5], 'new answer positions are not balanced');

$eligibility = $read('storage/question-eligibility.json');
$eligibleIds = [];
foreach ($eligibility as $entry) {
    if (($entry['id'] ?? '') === 'eligibility-cpa-batch491') {
        $eligibleIds = array_map('strval', $entry['question_ids'] ?? []);
        break;
    }
}
$assert(count(array_intersect($idStrings, $eligibleIds)) === 20, 'new CPA questions are not all eligible');

$links = $read('storage/study-question-links.json');
$linksByQuestion = [];
foreach ($links as $link) {
    $questionId = (string) ($link['question_id'] ?? '');
    if (in_array($questionId, $idStrings, true)) {
        $linksByQuestion[$questionId][] = $link;
    }
}
$assert(count($linksByQuestion) === 20, 'not every new CPA question has a study link');
foreach ($idStrings as $id) {
    $assert(count($linksByQuestion[$id] ?? []) === 1, "question {$id} does not have exactly one study link");
    $assert(($linksByQuestion[$id][0]['source_ids'] ?? []) === ['prc-accountancy-board-page'], "question {$id} study link source is not authoritative");
}

$deriver = App::container()->get(FlashcardDerivationService::class);
foreach ($idStrings as $id) {
    $card = $deriver->forQuestionId($id);
    $assert(is_array($card) && ($card['question_id'] ?? '') === $id, "question {$id} did not derive a flashcard");
    $assert(count($card['study_references'] ?? []) >= 1, "question {$id} flashcard lost study support");
}

echo "[PASS] CPA Advanced Accounting Batch 576 content, accounting answers, traceability, eligibility, positions, and flashcard derivation verified.\n";
