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
