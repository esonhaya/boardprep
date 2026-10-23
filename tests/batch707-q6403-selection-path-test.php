<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
App\Core\Autoloader::register();
require_once dirname(__DIR__) . '/app/Services/Quiz/Selection/QuestionPoolFilter.php';

$root = dirname(__DIR__);
$read = static fn(string $path): array => json_decode((string) file_get_contents($root . '/' . $path), true, 512, JSON_THROW_ON_ERROR);
$questions = $read('storage/questions.json');
$q6403 = null;
foreach ($questions as $question) if ((string) ($question['id'] ?? '') === '6403') $q6403 = $question;
if (!is_array($q6403)) throw new RuntimeException('Canonical Q6403 is missing.');
$eligibility = $read('storage/question-eligibility.json');
$derived = $read('storage/concept-derived-eligibility.json');
$overrides = $read('storage/question-reuse-overrides.json');
$baseline = new App\Services\Question\QuestionEligibilityIndex($eligibility, [], $derived, []);
$held = new App\Services\Question\QuestionEligibilityIndex($eligibility, [], $derived, $overrides);

$requests = [
    'normal practice' => new SelectionRequest('clerical-ability', null, [], 1, exam: 'civil-service', civilServiceLevel: 'SUBPROFESSIONAL'),
    'subject practice' => new SelectionRequest('clerical-ability', null, [], 1, exam: 'civil-service', civilServiceLevel: 'SUBPROFESSIONAL'),
    'random practice' => new SelectionRequest('*', null, [], 1, exam: 'civil-service', civilServiceLevel: 'SUBPROFESSIONAL'),
    'retry/new session' => new SelectionRequest('clerical-ability', null, [], 1, exam: 'civil-service', civilServiceLevel: 'SUBPROFESSIONAL'),
];

foreach ($requests as $label => $request) {
    $withoutHold = QuestionPoolFilter::filter([$q6403], $request, $baseline);
    $withHold = QuestionPoolFilter::filter([$q6403], $request, $held);
    if (count($withoutHold) !== 1 || (string) ($withoutHold[0]['id'] ?? '') !== '6403') {
        throw new RuntimeException('The fixture does not reproduce the eligible new selection path: ' . $label);
    }
    if ($withHold !== []) {
        throw new RuntimeException('The review-required exclusion failed for ' . $label);
    }
}

$timedRequest = new SelectionRequest('clerical-ability', null, ['easy' => 1], 1, exam: 'civil-service', civilServiceLevel: 'SUBPROFESSIONAL');
$timedBaseline = BlueprintRequestExecutor::execute([$q6403], [$timedRequest], $baseline);
$timedHeld = BlueprintRequestExecutor::execute([$q6403], [$timedRequest], $held);
if (count($timedBaseline) !== 1 || (string) ($timedBaseline[0]['id'] ?? '') !== '6403') {
    throw new RuntimeException('Timed/blueprint selection fixture did not expose Q6403 before the hold.');
}
if ($timedHeld !== []) {
    throw new RuntimeException('Timed/blueprint selection bypassed the review-required exclusion.');
}

echo "Q6403_NORMAL_PRACTICE_EXCLUDED=PASS\n";
echo "Q6403_SUBJECT_PRACTICE_EXCLUDED=PASS\n";
echo "Q6403_RANDOM_PRACTICE_EXCLUDED=PASS\n";
echo "Q6403_TIMED_BLUEPRINT_POOL_EXCLUDED=PASS\n";
echo "Q6403_RETRY_NEW_SESSION_EXCLUDED=PASS\n";
echo "Q6403_EXISTING_SESSION_HISTORY_UNMODIFIED=BY_DESIGN\n";
