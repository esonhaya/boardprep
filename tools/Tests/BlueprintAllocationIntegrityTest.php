<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require_once __DIR__ . '/MemoryStorage.php';

use Tools\Tests\MemoryStorage;

$storage = new MemoryStorage();
$repository = new \App\Repositories\BlueprintRepository($storage);
$valid = [
    'board' => 'LET',
    'subject' => 'English',
    'name' => 'Integrity Test Blueprint',
    'questionCount' => '20',
    'easy' => '30',
    'medium' => '50',
    'hard' => '20',
];

$created = \App\Services\Blueprint\Creation\BlueprintCreationService::create($repository, $valid);
assert(($created['success'] ?? false) === true);
$saved = $repository->find((string) ($created['blueprint']['id'] ?? ''));
assert($saved !== null);
assert(array_sum($saved['difficulty']) === 100);
assert(array_sum($repository->all()[0]['difficulty']) === 100);

foreach ([
    ['easy' => '29', 'medium' => '50', 'hard' => '20'],
    ['easy' => '30', 'medium' => '51', 'hard' => '20'],
    ['easy' => '-1', 'medium' => '51', 'hard' => '50'],
    ['easy' => '101', 'medium' => '0', 'hard' => '0'],
    ['easy' => '30', 'medium' => '20'],
    ['easy' => 'thirty', 'medium' => '50', 'hard' => '20'],
    ['easy' => '', 'medium' => '50', 'hard' => '50'],
] as $tampered) {
    $result = \App\Services\Blueprint\Creation\BlueprintCreationService::create(
        $repository,
        array_merge($valid, $tampered)
    );
    assert(($result['success'] ?? true) === false);
}
assert(count($repository->all()) === 1);

foreach ([5, 10, 20, 50, 100] as $count) {
    foreach ([
        ['easy' => 30, 'medium' => 50, 'hard' => 20],
        ['easy' => 1, 'medium' => 1, 'hard' => 98],
        ['easy' => 33, 'medium' => 33, 'hard' => 34],
        ['easy' => 0, 'medium' => 50, 'hard' => 50],
        ['easy' => 100, 'medium' => 0, 'hard' => 0],
    ] as $distribution) {
        $allocation = RuntimeAllocationService::allocate($count, $distribution);
        assert(array_sum($allocation) === $count);
    }
}

$board = [
    'subjects' => [
        ['subject' => 'English', 'percentage' => 50],
        ['subject' => 'Science', 'percentage' => 50],
    ],
];
$subjects = [
    'English' => [
        'domains' => [
            ['domain' => 'Grammar', 'percentage' => 60],
            ['domain' => 'Reading', 'percentage' => 40],
        ],
        'difficulty' => ['easy' => 33, 'medium' => 33, 'hard' => 34],
    ],
    'Science' => [
        'domains' => [['domain' => 'Biology', 'percentage' => 100]],
        'difficulty' => ['easy' => 0, 'medium' => 50, 'hard' => 50],
    ],
];
foreach ([5, 10, 20, 50, 100] as $count) {
    $requests = BlueprintDistributionService::distribution(
        $board,
        $subjects,
        $count
    );
    assert(array_sum(array_map(
        static fn(\SelectionRequest $request): int => $request->questionCount,
        $requests
    )) === $count);
}

assert(BlueprintIntegrityValidator::validate($board, $subjects) === []);
assert(BlueprintIntegrityValidator::validate(
    ['subjects' => [
        ['subject' => 'English', 'percentage' => 99],
        ['subject' => 'Science', 'percentage' => 0],
    ]],
    $subjects
) !== []);

assert(\App\Services\Shared\BlueprintValidator::allocationErrors(
    ['easy' => 33, 'medium' => 33, 'hard' => 34],
    ['easy', 'medium', 'hard']
) === []);
assert(\App\Services\Shared\BlueprintValidator::allocationErrors(
    ['easy' => 50, 'medium' => 50],
    ['easy', 'medium', 'hard']
) !== []);
assert(\App\Services\Shared\BlueprintValidator::allocationErrors(
    ['easy' => 'NaN', 'medium' => 50, 'hard' => 50],
    ['easy', 'medium', 'hard']
) !== []);

echo "[PASS] Blueprint allocation validation, persistence, and finite-count rounding verified.\n";
