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

$ids = range(1829, 1848);
$idStrings = array_map('strval', $ids);
$expectedTopics = [
    'cpa-auditing-assertions', 'cpa-auditing-internal-control', 'cpa-auditing-audit-risk',
    'cpa-auditing-materiality', 'cpa-auditing-evidence', 'cpa-auditing-inventory-audit',
    'cpa-auditing-control-risk', 'cpa-auditing-analytics', 'cpa-auditing-ethics',
    'cpa-auditing-sampling', 'cpa-auditing-scope', 'cpa-auditing-going-concern',
    'cpa-auditing-representations', 'cpa-auditing-completeness', 'cpa-auditing-fraud',
    'cpa-auditing-reporting', 'cpa-auditing-documentation', 'cpa-auditing-substantive-testing',
    'cpa-auditing-estimates', 'cpa-auditing-assurance',
];
$expectedAnswers = [
    1829 => 'Perform additional procedures on existence and valuation, including subsequent receipts and credit-loss evidence.',
    1830 => 'Treat the bypass as a control deficiency, assess its effect, and test any compensating review before relying on the control.',
    1831 => 'Increase substantive procedures and obtain more persuasive evidence, while avoiding unsupported reliance on weak controls.',
    1832 => 'The P135,000 aggregate must be evaluated against overall materiality, including whether its nature or distribution affects the conclusion.',
    1833 => 'It supports existence and quantities but does not by itself establish ownership or valuation.',
    1834 => 'Expand cutoff testing and determine whether the December 31 receipt and related liability/inventory were recorded in the proper period.',
    1835 => 'Reassess control risk and expand substantive procedures rather than relying on the control as planned.',
    1836 => 'Investigate the changed relationships and corroborate the explanation with disaggregated sales, aging, subsequent receipts, and other evidence.',
    1837 => 'Evaluate the resulting independence threat and apply the applicable safeguards or remove the threat if it cannot be reduced to an acceptable level.',
    1838 => 'Evaluate sampling risk and consider additional testing or a revised substantive response before relying on the control.',
    1839 => 'Seek alternatives and evaluate how the unresolved scope limitation affects the audit conclusion, including materiality and pervasiveness.',
    1840 => 'Obtain evidence about management’s plans and their feasibility, evaluate available corroboration, and assess the related disclosures.',
    1841 => 'Use the representation as supplementary evidence and perform procedures that corroborate completeness of liabilities.',
    1842 => 'Trace receiving reports and supplier invoices forward into the purchase journal and payable ledger, then investigate omissions.',
    1843 => 'Expand journal-entry and related-party testing, investigate the explanations, and maintain professional skepticism.',
    1844 => 'Evaluate materiality and pervasiveness, the applicable reporting consequence, and whether the financial statements and report disclosures are adequate.',
    1845 => 'The documentation is insufficient to show the work performed and conclusion reached; the auditor should complete or perform the necessary work before finalizing.',
    1846 => 'Perform targeted alternative procedures using subsequent receipts and shipping-to-ledger reconciliation, and evaluate whether they address the relevant assertions.',
    1847 => 'Evaluate the revised evidence and assumptions, test the calculation, and compare the estimate with subsequent claims and settlements.',
    1848 => 'Express a limited assurance conclusion based on the procedures performed, without representing that reasonable assurance was obtained.',
];
$questions = array_values(array_filter(
    $read('storage/questions.json'), static fn(array $question): bool => in_array((int) ($question['id'] ?? 0), $ids, true)
));
$assert(count($questions) === 20, 'CPA Auditing wave does not contain exactly 20 questions');

$topics = [];
$positionCounts = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0];
$tableIds = [1832, 1834, 1835, 1836, 1838, 1840, 1847];
foreach ($questions as $question) {
    $id = (int) $question['id'];
    $taxonomy = $question['taxonomy'] ?? [];
    $assert(($question['board'] ?? '') === 'cpa' && ($taxonomy['board_id'] ?? '') === 'cpa', "question {$id} is not CPA-owned");
    $assert(($taxonomy['subject_id'] ?? '') === 'cpa-auditing' && ($taxonomy['domain_id'] ?? '') === 'cpa-auditing', "question {$id} is outside CPA Auditing");
    $topics[] = $taxonomy['topic_id'] ?? '';
    $assert(($question['status'] ?? '') === 'active', "question {$id} is not active");
    $assert(($question['multistep'] ?? false) === true && ($question['cognitive_intent'] ?? '') !== 'recall', "question {$id} is not application-level");
    $expectedSources = $id === 1836
        ? ['prc-accountancy-board-page', 'iaasb-isa-520-analytical-procedures']
        : ['prc-accountancy-board-page'];
    $assert(($question['source_ids'] ?? []) === $expectedSources, "question {$id} lost source traceability");
    $assert(($question['answer'] ?? '') === $expectedAnswers[$id], "question {$id} answer changed");
    $assert(count($question['choices'] ?? []) === 4 && count(array_unique($question['choices'])) === 4, "question {$id} choices are malformed");
    $assert(in_array($question['answer'], $question['choices'], true), "question {$id} answer is not a choice");
    $positionCounts[chr(65 + (int) array_search($question['answer'], $question['choices'], true))]++;
    $hasTable = count($question['content_blocks'] ?? []) === 1 && (($question['content_blocks'][0]['type'] ?? '') === 'table');
    $assert($hasTable === in_array($id, $tableIds, true), "question {$id} structured-content plan changed");
}
$assert($topics === $expectedTopics, 'wave does not cover each active auditing singleton exactly once');
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
$assert(count($linksByQuestion) === 20, 'not every new auditing question has a study link');
foreach ($idStrings as $id) {
    $assert(count($linksByQuestion[$id] ?? []) === 1, "question {$id} does not have exactly one study link");
    $expectedSources = $id === '1836'
        ? ['prc-accountancy-board-page', 'iaasb-isa-520-analytical-procedures']
        : ['prc-accountancy-board-page'];
    $assert(($linksByQuestion[$id][0]['source_ids'] ?? []) === $expectedSources, "question {$id} study source is not authoritative");
}

$deriver = App::container()->get(FlashcardDerivationService::class);
foreach ($idStrings as $id) {
    $card = $deriver->forQuestionId($id);
    $assert(is_array($card) && (string) ($card['question_id'] ?? '') === $id, "question {$id} did not derive a flashcard");
    $assert(count($card['study_references'] ?? []) >= 1, "question {$id} flashcard lost study support");
}

echo "[PASS] CPA Auditing Batch 577B content, answer keys, structured content, traceability, eligibility, positions, and flashcard derivation verified.\n";
