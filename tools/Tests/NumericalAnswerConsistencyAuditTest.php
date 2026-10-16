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
$named = static function (string $id, string $formula, string $formulaFamily, array $inputs, float $expected, string $answer, string $unit = '', string $rounding = 'exact'): array {
    return [
        'id' => $id,
        'status' => 'active',
        'taxonomy' => ['board_id' => 'fixture-board'],
        'answer' => $answer,
        'calculation' => ['formula' => $formula, 'formula_family' => $formulaFamily, 'inputs' => $inputs, 'expected_value' => $expected, 'unit' => $unit, 'rounding' => $rounding],
    ];
};
$expandedModels = [
    $named('percent-change', 'percent_change', '', [250, 210], 16, '16%', '%'),
    $named('three-point-mean', 'average_three', 'test-average', ['a' => 7, 'b' => 4, 'c' => 9], 20 / 3, '6.67 MPa', 'MPa', '2 decimals'),
    $named('interpolation', 'interpolation', 'linear-interpolation', ['xlow' => 0, 'xhigh' => 10, 'xmid' => 4, 'low' => 12, 'high' => 26], 17.6, '17.6'),
    $named('ratio-model', 'ratio_ab', 'ratio-model', ['a' => 8, 'b' => 7], 8 / 7, '1.14', '', '2 decimals'),
    $named('effective-stress', 'total_minus_pore', 'effective-stress', ['total' => 124, 'pore' => 37], 87, '87 kPa', 'kPa'),
    $named('support-reaction', 'half_load', 'support-reaction', ['load' => 16], 8, '8 kN', 'kN'),
    $named('uniform-resultant', 'uniform_total', 'uniform-load-resultant', ['load' => 23, 'length' => 5], 115, '115 kN', 'kN'),
    $named('axial-stress', 'force_over_area', 'axial-stress', ['force' => 84, 'area' => .4], 210, '210 kPa', 'kPa'),
    $named('grade', 'rise_run_percent', 'grade', ['rise' => 3, 'run' => 81], 300 / 81, '3.7%', '%', '1 decimal'),
    $named('headway', 'hour_seconds_over_flow', 'headway', ['flow' => 28], 3600 / 28, '128.57 s/veh', 's/veh', '2 decimals'),
    $named('curve-tangent', 'radius_ratio', 'curve-tangent', ['radius' => 90, 'ratio' => .8], 72, '72 m', 'm'),
    $named('square-sequence', 'sequence_pattern_next', '', [], 36, '36'),
];
$expandedModels[array_key_last($expandedModels)]['calculation']['values'] = [1, 4, 9, 16, 25];
$expandedResults = NumericalAnswerConsistencyAudit::analyze($expandedModels);
$expandedFindings = array_column($expandedResults['findings'], 'entity_id');
foreach (array_column($expandedModels, 'id') as $id) {
    $assert(!in_array($id, $expandedFindings, true), 'A supported named numerical formula was unresolved or incorrect: ' . $id);
}
$assert(($expandedResults['metrics']['recomputed'] ?? 0) === count($expandedModels), 'Expanded numerical formula coverage did not include every supported fixture.');
$ratioShare = [
    ['id' => 'ratio-small', 'question' => 'A total is divided in the ratio 5:6. What is the smaller share?', 'status' => 'active', 'answer' => '60', 'calculation' => ['formula' => 'ratio_share_auto', 'inputs' => [132, 5, 6], 'expected_value' => 60]],
    ['id' => 'ratio-large', 'question' => 'A 5:7 ratio has a total of 144. What is the larger share?', 'status' => 'active', 'answer' => '84', 'calculation' => ['formula' => 'ratio_share_auto', 'inputs' => [144, 5, 7], 'expected_value' => 84]],
    ['id' => 'ratio-second', 'question' => 'A box has 84 forms divided between two offices in the ratio 3:4. How many go to the second office?', 'status' => 'active', 'answer' => '48', 'calculation' => ['formula' => 'ratio_share_auto', 'inputs' => [84, 3, 4], 'expected_value' => 48]],
];
$ratioResults = NumericalAnswerConsistencyAudit::analyze($ratioShare);
$assert(($ratioResults['metrics']['recomputed'] ?? 0) === 3 && $ratioResults['findings'] === [], 'Ratio-share models did not respect the question wording for smaller, larger, and second shares.');
$unsupportedSequence = $named('ambiguous-sequence', 'sequence_pattern_next', '', [], 12, '12');
$unsupportedSequence['calculation']['values'] = [2, 5, 9];
$unsupportedSequence['calculation']['expected_value'] = 15;
$unsupportedResult = NumericalAnswerConsistencyAudit::analyze([$unsupportedSequence]);
$assert(($unsupportedResult['metrics']['unsupported'] ?? 0) === 1, 'An ambiguous geometric sequence was treated as a unique pattern without sufficient evidence.');
$newSupportedModels = [
    ['id' => 'distance-time', 'status' => 'active', 'taxonomy' => ['board_id' => 'civil-service'], 'answer' => '60 km/h', 'calculation' => ['operation' => 'distance_time', 'inputs' => [210, 3.5], 'expected' => 60, 'unit' => 'km/h']],
    ['id' => 'rate-time', 'status' => 'active', 'taxonomy' => ['board_id' => 'civil-service'], 'answer' => '68', 'calculation' => ['operation' => 'rate_times_time', 'inputs' => [8, 8.5], 'expected' => 68]],
    ['id' => 'fraction-total', 'status' => 'active', 'taxonomy' => ['board_id' => 'civil-service'], 'answer' => '160', 'calculation' => ['operation' => 'fraction_of_total', 'inputs' => [2, 240, 3], 'expected' => 160]],
    ['id' => 'angular-acceleration', 'status' => 'active', 'taxonomy' => ['board_id' => 'mechanical-engineering'], 'answer' => '2 rad/s²', 'calculation' => ['operation' => 'angular_acceleration', 'formula_family' => 'uniform-angular-acceleration', 'inputs' => ['initial_angular_velocity' => 2, 'final_angular_velocity' => 10, 'time' => 4], 'expected_value' => 2, 'unit' => 'rad/s2']],
    ['id' => 'angular-frequency', 'status' => 'active', 'taxonomy' => ['board_id' => 'registered-electrical-engineering'], 'answer' => '377.0 rad/s', 'calculation' => ['operation' => 'angular_frequency_hz', 'formula_family' => 'angular-frequency', 'inputs' => ['frequency_hz' => 60], 'expected_value' => 376.99111843077515, 'unit' => 'rad/s', 'rounding' => '1 decimal']],
    ['id' => 'quadratic-derivative', 'status' => 'active', 'taxonomy' => ['board_id' => 'mechanical-engineering'], 'answer' => '7', 'calculation' => ['operation' => 'quadratic_derivative_at_x', 'formula_family' => 'quadratic-derivative', 'inputs' => ['a' => 1, 'b' => 3, 'x' => 2], 'expected_value' => 7]],
];
$newModelAudit = NumericalAnswerConsistencyAudit::analyze($newSupportedModels);
$assert(($newModelAudit['metrics']['recomputed'] ?? 0) === count($newSupportedModels)
    && ($newModelAudit['findings'] ?? []) === [], 'A newly supported rate, fraction, or engineering formula failed its independent numerical contract.');
$codes = array_column($results['findings'], 'code');
$ids = array_column($results['findings'], 'entity_id');
$assert(!in_array('valid', $ids, true), 'A correct arithmetic fixture was flagged.');
$assert(in_array('invalid', $ids, true) && in_array('NUMERICAL_FORMULA_METADATA_MISMATCH', $codes, true), 'An incorrect recorded calculation was not detected.');
$assert(!in_array('moment', $ids, true), 'The cantilever moment model did not use load × arm.');
$assert(!in_array('unsupported-moment', $ids, true), 'An unsupported moment family was treated as verified.');
$assert(!in_array('reverse-percent', $ids, true), 'A percentage reduction was not inverted correctly.');
$assert(($results['metrics']['unsupported'] ?? 0) === 1, 'Unsupported calculation families must remain explicitly counted.');
$assert(($results['metrics']['unit_unresolved'] ?? 0) === 2, 'Missing expected or answer units must remain explicitly unresolved.');

$rounded = $fixture('rounded-display', 'divide', [], 1 / 3, '0.33');
$rounded['calculation']['values'] = [1, 3];
$wrongRounded = $rounded;
$wrongRounded['id'] = 'wrong-rounded-display';
$wrongRounded['answer'] = '0.34';
$exactRounded = $rounded;
$exactRounded['id'] = 'explicit-exact-display';
$exactRounded['calculation']['rounding'] = 'exact';
$roundedResults = NumericalAnswerConsistencyAudit::analyze([$rounded, $wrongRounded, $exactRounded]);
$roundedFindingIds = array_column($roundedResults['findings'], 'entity_id');
$assert(!in_array('rounded-display', $roundedFindingIds, true), 'A key correctly rounded to its displayed decimal precision was flagged as wrong.');
$assert(in_array('wrong-rounded-display', $roundedFindingIds, true), 'A displayed answer outside the valid rounding interval was accepted.');
$assert(in_array('explicit-exact-display', $roundedFindingIds, true), 'An explicitly exact answer was weakened by inferred display precision.');
$assert(($roundedResults['metrics']['rounded_answers'] ?? 0) === 1, 'Correct rounded display results were not made visible in the audit metrics.');

$percentUnit = $fixture('percent-unit-alias', 'sum', ['a' => 10, 'b' => 10], 20, '20%');
$percentUnit['calculation']['unit'] = 'percent';
$kilogramUnit = $fixture('kilogram-unit-alias', 'sum', ['a' => 1, 'b' => 2], 3, '3 kilograms');
$kilogramUnit['calculation']['unit'] = 'kg';
$phpCurrency = $fixture('php-currency-prefix', 'sum', ['a' => 40000, 'b' => 30320], 70320, 'PHP 70,320');
$phpCurrency['calculation']['unit'] = 'PHP';
$unitResults = NumericalAnswerConsistencyAudit::analyze([$percentUnit, $kilogramUnit, $phpCurrency]);
$assert($unitResults['findings'] === [], 'Unambiguous percent, kilogram, or PHP answer units were not normalized consistently.');
$assert(($unitResults['metrics']['unit_unresolved'] ?? 0) === 0, 'Explicitly equivalent unit labels remained unresolved.');

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
foreach (['7005', '7047', '7049', '7064', '7065', '7173', '7183', '7191', '7208', '7209', '7216', '7247', '7377', '7383', '7385', '7400', '7533', '7552', '7553'] as $id) {
    $assert(!in_array($id, $findingIds, true), "Correctly rounded answer {$id} was reported as an exact-value disagreement.");
}

echo '[PASS] Explicit numerical models, reverse-percent handling, unsupported-formula boundaries, and reviewed Batch 682/683 answer fixtures verified.' . PHP_EOL;
