<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use Tools\Doctor\Project\BoardPrep\Checks\NumericalAnswerConsistencyAudit;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$fixture = static function (string $id, string $formula, array $inputs, float $expected, string $answer): array {
    return [
        'id' => $id,
        'status' => 'active',
        'taxonomy' => ['board_id' => 'fixture-board'],
        'answer' => $answer,
        'calculation' => [
            'formula' => $formula,
            'formula_family' => $formula === 'moment_product' ? 'cantilever-moment' : 'fixture',
            'inputs' => $inputs,
            'expected_value' => $expected,
        ],
    ];
};

$valid = $fixture('valid', 'sum', ['a' => 2, 'b' => 3], 5, '5');
$invalid = $fixture('invalid', 'sum', ['a' => 2, 'b' => 3], 6, '6');
$moment = $fixture('moment', 'moment_product', ['load' => 16, 'arm' => 3.5], 56, '56 kN-m');
$unsupportedMoment = $fixture('unsupported-moment', 'moment_product', ['load' => 16, 'arm' => 3.5], 56, '56 kN-m');
$unsupportedMoment['calculation']['formula_family'] = 'unrecognized-moment-model';
$reversePercent = [
    'id' => 'reverse-percent', 'status' => 'active', 'taxonomy' => ['board_id' => 'fixture-board'], 'answer' => '500',
    'calculation' => ['operation' => 'reverse_percent', 'inputs' => [440, 12], 'expected' => 500],
];
$missingAnswerUnit = $fixture('missing-answer-unit', 'sum', ['a' => 2, 'b' => 3], 5, '5');
$missingAnswerUnit['calculation']['unit'] = 'm';
$missingExpectedUnit = $fixture('missing-expected-unit', 'sum', ['a' => 2, 'b' => 3], 5, '5 m');
$moment['calculation']['unit'] = 'kN-m';
$results = NumericalAnswerConsistencyAudit::analyze([
    $valid,
    $invalid,
    $moment,
    $unsupportedMoment,
    $reversePercent,
    $missingAnswerUnit,
    $missingExpectedUnit,
]);
$codes = array_column($results['findings'], 'code');
$ids = array_column($results['findings'], 'entity_id');
$assert(!in_array('valid', $ids, true), 'A correct arithmetic fixture was flagged.');
$assert(in_array('invalid', $ids, true) && in_array('NUMERICAL_FORMULA_METADATA_MISMATCH', $codes, true), 'An incorrect recorded calculation was not detected.');
$assert(!in_array('moment', $ids, true), 'The cantilever moment model did not use load × arm.');
$assert(!in_array('unsupported-moment', $ids, true), 'An unsupported moment family was treated as verified.');
$assert(!in_array('reverse-percent', $ids, true), 'A percentage reduction was not inverted correctly.');
$assert(($results['metrics']['unsupported'] ?? 0) === 1, 'Unsupported calculation families must remain explicitly counted.');
$assert(($results['metrics']['unit_unresolved'] ?? 0) === 2, 'Missing expected or answer units must remain explicitly unresolved.');

$root = dirname(__DIR__, 2);
$questions = json_decode((string) file_get_contents($root . '/storage/questions.json'), true, 512, JSON_THROW_ON_ERROR);
$audit = NumericalAnswerConsistencyAudit::analyze($questions);
$findingIds = array_column($audit['findings'], 'entity_id');
foreach (['651', '1301', '1569', '5589', '6983', '8864'] as $id) {
    $assert(!in_array($id, $findingIds, true), "Reviewed numerical item {$id} still has an unresolved numeric finding.");
}
foreach (['6975', '7519', '7135', '7143', '7327', '7335'] as $id) {
    $assert(!in_array($id, $findingIds, true), "Corrected Batch 682 Civil Engineering item {$id} regressed.");
}

echo '[PASS] Explicit numerical models, reverse-percent handling, unsupported-formula boundaries, and reviewed Batch 682/683 answer fixtures verified.' . PHP_EOL;
