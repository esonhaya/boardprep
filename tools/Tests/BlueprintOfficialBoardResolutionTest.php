<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$storage = \App\Core\App::storage();
$questions = $storage->all('questions');
$eligibility = \App\Services\Question\QuestionEligibilityService::index();
$boardCodes = [];
foreach ($storage->all('boards') as $boardRecord) {
    if (is_array($boardRecord)) {
        $boardCodes[(string) ($boardRecord['id'] ?? '')] = (string) ($boardRecord['code'] ?? $boardRecord['id'] ?? '');
    }
}

$boards = [
    'civil-engineering' => ['ce-structural' => 35, 'ce-applied' => 35, 'ce-hydraulics-geotech' => 30],
    'mechanical-engineering' => [
        'mathematics' => 35,
        'power-plant-engineering' => 35,
        'machine-design-materials-shop-practice' => 30,
    ],
    'registered-electrical-engineering' => [
        'ree-mathematics' => 25,
        'engineering-sciences-and-allied-subjects' => 30,
        'electrical-engineering-professional-subjects' => 45,
    ],
    'architecture' => [
        'arch-area-a' => 30,
        'arch-area-b' => 30,
        'arch-area-c' => 40,
    ],
];

foreach ($boards as $board => $expectedGroups) {
    $boardInput = $boardCodes[$board] ?? $board;
    $specification = BaseSpecificationFactory::create([
        'board' => $boardInput,
        'subject' => '*',
        'subject_explicit' => false,
        'mode' => 'exam',
        'count' => 20,
    ]);
    $resolved = BlueprintResolverService::resolve($specification);
    $groups = $resolved['board']['allocation_groups'] ?? [];
    $actualGroups = array_column($groups, 'percentage', 'id');
    ksort($actualGroups);
    ksort($expectedGroups);
    if (array_keys($actualGroups) !== array_keys($expectedGroups)) {
        throw new RuntimeException("{$board}: official allocation groups did not resolve exactly.");
    }
    foreach ($expectedGroups as $groupId => $weight) {
        if (abs((float) ($actualGroups[$groupId] ?? -1) - (float) $weight) > 0.001) {
            throw new RuntimeException("{$board}/{$groupId}: official weight changed during resolution.");
        }
    }
    if (BlueprintIntegrityValidator::validate($resolved['board'], $resolved['subjects']) !== []) {
        throw new RuntimeException("{$board}: resolved subject/domain shares failed blueprint integrity.");
    }

    $requests = BlueprintDistributionService::distribution(
        $resolved['board'],
        $resolved['subjects'],
        80
    );
    if (array_sum(array_map(static fn(SelectionRequest $request): int => $request->questionCount, $requests)) !== 80) {
        throw new RuntimeException("{$board}: 80-question area quotas did not allocate exactly.");
    }
    foreach ($groups as $group) {
        $expectedCount = RuntimeAllocationService::allocate(80, $expectedGroups)[$group['id']] ?? 0;
        $memberKeys = [];
        foreach ($group['members'] as $member) {
            $memberKeys[(string) $member['subject'] . "\0" . (string) $member['domain']] = true;
        }
        $actualCount = 0;
        foreach ($requests as $request) {
            if (isset($memberKeys[$request->subject . "\0" . (string) $request->domain])) {
                $actualCount += $request->questionCount;
            }
        }
        if ($actualCount !== $expectedCount) {
            throw new RuntimeException("{$board}/{$group['id']}: configured official area quota was not preserved.");
        }
    }

    $practiceSpec = BaseSpecificationFactory::create([
        'board' => $boardInput,
        'subject' => '*',
        'mode' => 'exam',
        'count' => 20,
    ]);
    $result = QuizGenerationService::generate($questions, $practiceSpec, $eligibility);
    if (count($result->questions) !== 20 || $result->issues !== []) {
        throw new RuntimeException("{$board}: real eligible pool did not fulfill its configured 20-question simulation.");
    }
    foreach ($result->questions as $question) {
        if (\App\Services\Question\QuestionEligibilityService::forExam($question, $boardInput, $eligibility) === null) {
            throw new RuntimeException("{$board}: a generated simulation item failed runtime eligibility.");
        }
    }

    $focusedMember = $groups[0]['members'][0] ?? null;
    if (!is_array($focusedMember)) {
        throw new RuntimeException("{$board}: no configured subject is available for the focused-subject check.");
    }
    $focusedSpec = BaseSpecificationFactory::create([
        'board' => $boardInput,
        'subject' => (string) $focusedMember['subject_id'],
        'subject_explicit' => true,
        'mode' => 'exam',
        'count' => 5,
    ]);
    $focusedResult = QuizGenerationService::generate($questions, $focusedSpec, $eligibility);
    if (count($focusedResult->questions) !== 5 || $focusedResult->issues !== []) {
        throw new RuntimeException("{$board}: canonical subject navigation did not reach its configured exam section.");
    }
}

$cpa = BlueprintResolverService::resolve(BaseSpecificationFactory::create([
    'board' => 'cpa',
    'subject' => '*',
    'mode' => 'exam',
    'count' => 20,
]));
if (($cpa['board']['allocation_groups'] ?? []) !== [] || ($cpa['board']['subjects'] ?? []) !== []) {
    throw new RuntimeException('CPA acquired invented allocation weights despite unavailable official weighting.');
}

echo "[PASS] Official board areas, exact rounding, taxonomy mapping, eligible generation, and unsupported CPA weighting verified.\n";
