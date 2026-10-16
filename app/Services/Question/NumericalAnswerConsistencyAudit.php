<?php

declare(strict_types=1);

namespace App\Services\Question;

/**
 * Recomputes a deliberately small set of explicit numeric models. Natural-
 * language questions and unsupported formula families stay out of scope.
 */
final class NumericalAnswerConsistencyAudit
{
    /**
     * @return array{findings: array<int, array<string, string>>, metrics: array<string, int>}
     */
    public static function analyze(array $questions): array
    {
        $findings = [];
        $metrics = [
            'structured_payloads' => 0,
            'recomputed' => 0,
            'unsupported' => 0,
            'unreadable_answers' => 0,
            'unit_unresolved' => 0,
            'rounded_answers' => 0,
        ];

        foreach ($questions as $question) {
            if (!is_array($question)
                || !in_array(strtolower((string) ($question['status'] ?? 'active')), ['active', 'approved'], true)) {
                continue;
            }

            $payload = self::payload($question);
            if ($payload === null) {
                continue;
            }
            $metrics['structured_payloads']++;

            $expected = self::number($payload['expected_value'] ?? $payload['expected'] ?? $payload['result'] ?? null);
            $computed = self::recompute($payload, $question);
            if ($expected === null || $computed === null) {
                $metrics['unsupported']++;
                continue;
            }
            $metrics['recomputed']++;

            $roundingDeclared = is_scalar($payload['rounding'] ?? null)
                && trim((string) $payload['rounding']) !== '';
            $rounding = $roundingDeclared ? trim((string) $payload['rounding']) : 'exact';
            $expectedUnit = self::text($payload['unit'] ?? '');
            $formulaTolerance = self::tolerance($rounding, $expectedUnit);
            $id = self::text($question['id'] ?? '');
            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            $board = self::text($taxonomy['board_id'] ?? $question['board'] ?? 'UNKNOWN');

            if (abs($computed - $expected) > $formulaTolerance) {
                $findings[] = self::finding(
                    'HARD_DEFECT',
                    'NUMERICAL_FORMULA_METADATA_MISMATCH',
                    $board,
                    $id,
                    sprintf('Supported formula recomputes to %.12g but the recorded expected value is %.12g (%s).', $computed, $expected, $rounding)
                );
                continue;
            }

            $answer = self::answerValue($question['answer'] ?? null);
            if ($answer === null) {
                $metrics['unreadable_answers']++;
                continue;
            }

            $answerUnit = $answer['unit'];
            $normalizedAnswer = self::normalizeAnswerUnit($answer['value'], $expectedUnit, $answerUnit);
            if ($normalizedAnswer === null) {
                $metrics['unit_unresolved']++;
                continue;
            }

            $displayedDecimals = $roundingDeclared ? null : self::displayedDecimals($question['answer'] ?? null);
            $answerTolerance = $roundingDeclared
                ? self::tolerance($rounding, $expectedUnit)
                : ($displayedDecimals === null ? 1.0e-8 : 0.5 * (10 ** (-$displayedDecimals)));
            $answerMatches = $displayedDecimals !== null
                ? abs(round($computed, $displayedDecimals, PHP_ROUND_HALF_UP) - $normalizedAnswer) <= 1.0e-8
                : abs($computed - $normalizedAnswer) <= $answerTolerance;

            if (!$answerMatches) {
                $findings[] = self::finding(
                    'REVIEW_REQUIRED',
                    'NUMERICAL_ANSWER_METADATA_DISAGREEMENT',
                    $board,
                    $id,
                    sprintf(
                        'Supported calculation gives %.12g, while the keyed answer gives %.12g (%s); verify the stated inputs and answer before editing.',
                        $computed,
                        $normalizedAnswer,
                        $roundingDeclared ? $rounding : ($displayedDecimals === null ? 'exact' : 'key display precision: ' . $displayedDecimals . ' decimal places')
                    )
                );
            } elseif ($displayedDecimals !== null && abs($computed - $normalizedAnswer) > 1.0e-8) {
                $metrics['rounded_answers']++;
            }
        }

        return ['findings' => $findings, 'metrics' => $metrics];
    }

    private static function payload(array $question): ?array
    {
        $payload = $question['calculation'] ?? null;
        if (is_array($payload)) {
            return $payload;
        }
        $legacy = $question['batch634']['calculation'] ?? null;
        return is_array($legacy) ? $legacy : null;
    }

    private static function recompute(array $payload, array $question): ?float
    {
        $family = strtolower(self::text($payload['formula'] ?? $payload['family'] ?? $payload['operation'] ?? ''));
        $values = self::numericList($payload['values'] ?? $payload['operands'] ?? null);
        $params = is_array($payload['params'] ?? null) ? $payload['params'] : [];
        $inputs = is_array($payload['inputs'] ?? null) ? $payload['inputs'] : [];
        $formulaFamily = strtolower(self::text($payload['formula_family'] ?? ''));

        // These named models use only the stated variables. Many historical
        // calculation records contain unrelated generated inputs; never treat
        // those as a generic operand list.
        $namedModels = [
            'quadratic_derivative_at_x' => ['quadratic-derivative', static function (array $i): ?float {
                $a = self::number($i['a'] ?? null); $b = self::number($i['b'] ?? null); $x = self::number($i['x'] ?? null);
                return $a !== null && $b !== null && $x !== null ? 2 * $a * $x + $b : null;
            }],
            'angular_acceleration' => ['uniform-angular-acceleration', static function (array $i): ?float {
                $initial = self::number($i['initial_angular_velocity'] ?? null); $final = self::number($i['final_angular_velocity'] ?? null); $time = self::number($i['time'] ?? null);
                return $initial !== null && $final !== null && $time !== null && $time != 0.0 ? ($final - $initial) / $time : null;
            }],
            'angular_frequency_hz' => ['angular-frequency', static function (array $i): ?float {
                $frequency = self::number($i['frequency_hz'] ?? null);
                return $frequency !== null ? 2 * M_PI * $frequency : null;
            }],
            'force_over_area' => ['axial-stress', static fn(array $i): ?float => self::divideNamed($i, 'force', 'area')],
            'average_three' => ['test-average', static fn(array $i): ?float => self::meanNamed($i, ['a', 'b', 'c'])],
            'interpolation' => ['linear-interpolation', static fn(array $i): ?float => self::interpolate($i)],
            'ratio_ab' => ['ratio-model', static fn(array $i): ?float => self::divideNamed($i, 'a', 'b')],
            'total_minus_pore' => ['effective-stress', static fn(array $i): ?float => self::subtractNamed($i, 'total', 'pore')],
            'half_load' => ['support-reaction', static fn(array $i): ?float => self::scaleNamed($i, 'load', 0.5)],
            'uniform_total' => ['uniform-load-resultant', static fn(array $i): ?float => self::multiplyNamed($i, 'load', 'length')],
            'rise_run_percent' => ['grade', static fn(array $i): ?float => self::percentRatioNamed($i, 'rise', 'run')],
            'hour_seconds_over_flow' => ['headway', static fn(array $i): ?float => self::inverseRateNamed($i, 'flow', 3600.0)],
            'radius_ratio' => ['curve-tangent', static fn(array $i): ?float => self::multiplyNamed($i, 'radius', 'ratio')],
        ];
        if (isset($namedModels[$family])) {
            [$requiredFamily, $calculate] = $namedModels[$family];
            return $formulaFamily === $requiredFamily ? $calculate($inputs) : null;
        }
        if ($family === 'percent_change') {
            $pair = $values ?? self::numericList($inputs);
            $old = $pair !== null && count($pair) === 2 ? $pair[0] : self::number($inputs['old'] ?? null);
            $new = $pair !== null && count($pair) === 2 ? $pair[1] : self::number($inputs['new'] ?? null);
            if ($old === null || $new === null || $old == 0.0) return null;
            return abs($new - $old) / abs($old) * 100.0;
        }
        if ($family === 'ratio_share_auto') {
            $numbers = $values ?? self::numericList($inputs);
            if ($numbers === null || count($numbers) < 3) return null;
            $stem = strtolower(self::text($question['question'] ?? ''));
            $parts = array_slice($numbers, 1);
            if (preg_match('/\b(?:smaller|smallest)\s+share\b/', $stem) === 1) $selected = min($parts);
            elseif (preg_match('/\b(?:larger|largest|greatest)\s+share\b/', $stem) === 1) $selected = max($parts);
            elseif (preg_match('/\bsecond\s+(?:office|group|share|part)\b/', $stem) === 1) $selected = $parts[1] ?? null;
            elseif (preg_match('/\bfirst\s+(?:office|group|share|part)\b/', $stem) === 1) $selected = $parts[0] ?? null;
            else return null;
            $totalParts = array_sum($parts);
            return $selected !== null && $totalParts != 0.0 ? $numbers[0] * $selected / $totalParts : null;
        }
        if ($family === 'distance_time') {
            $numbers = $values ?? self::numericList($inputs);
            return $numbers !== null && count($numbers) === 2 && $numbers[1] != 0.0 ? $numbers[0] / $numbers[1] : null;
        }
        if ($family === 'rate_times_time') {
            $numbers = $values ?? self::numericList($inputs);
            return $numbers !== null && count($numbers) === 2 ? $numbers[0] * $numbers[1] : null;
        }
        if ($family === 'fraction_of_total') {
            $numbers = $values ?? self::numericList($inputs);
            return $numbers !== null && count($numbers) === 3 && $numbers[2] != 0.0 ? $numbers[0] * $numbers[1] / $numbers[2] : null;
        }
        if ($family === 'sequence_pattern_next') {
            return self::recognizeSequenceNext(self::numericList($payload['values'] ?? null));
        }
        if ($family === 'ratio-part') {
            $part = self::number($params['part_ratio'] ?? null);
            $total = self::number($params['total_ratio'] ?? null);
            $whole = self::number($params['whole'] ?? null);
            return $part !== null && $total !== null && $whole !== null && $total != 0.0
                ? $whole * $part / $total : null;
        }
        if ($family === 'percentage' && $params !== []) {
            $part = self::number($params['part'] ?? null);
            $whole = self::number($params['whole'] ?? null);
            return $part !== null && $whole !== null && $whole != 0.0 ? $part / $whole * 100.0 : null;
        }
        if (in_array($family, ['mean', 'average'], true) && $values === null) {
            $values = self::numericList($params['values'] ?? null);
        }

        if ($family === 'moment_product') {
            if ($formulaFamily === 'cantilever-moment') {
                $force = self::number($inputs['load'] ?? null);
            } elseif (in_array($formulaFamily, ['prestress-eccentric-moment', 'prestress-resultant', 'connection-eccentricity'], true)) {
                $force = self::number($inputs['force'] ?? null);
            } else {
                return null;
            }
            $arm = self::number($inputs['arm'] ?? null);
            return $force !== null && $arm !== null ? $force * $arm : null;
        }
        if (in_array($family, ['thin_wall_hoop_stress', 'thin-wall-hoop-stress'], true) && count($values) === 3 && $values[2] != 0.0) {
            return $values[0] * $values[1] / (2 * $values[2]);
        }
        if (in_array($family, ['thin_wall_longitudinal_stress', 'thin-wall-longitudinal-stress'], true) && count($values) === 3 && $values[2] != 0.0) {
            return $values[0] * $values[1] / (4 * $values[2]);
        }
        if ($family === 'linear_system_2x2') {
            return self::solveTwoByTwo($payload);
        }

        $expression = self::calculateExpression(self::text($payload['expression'] ?? ''));
        if ($expression !== null) {
            return $expression;
        }

        if ($values === null) {
            $values = self::numericList($payload['inputs'] ?? null);
        }
        if ($values === null || $values === []) {
            return null;
        }

        return match ($family) {
            'sum', 'add', 'total' => array_sum($values),
            'subtract', 'difference' => count($values) === 2 ? $values[0] - $values[1] : null,
            'multiply', 'product' => array_product($values),
            'divide' => count($values) === 2 && $values[1] != 0.0 ? $values[0] / $values[1] : null,
            'min' => min($values),
            'max' => max($values),
            'mean', 'average' => array_sum($values) / count($values),
            'percent', 'percentage' => count($values) === 2
                ? $values[0] * ($values[1] > 1 ? $values[1] / 100 : $values[1])
                : null,
            'reverse_percent' => count($values) === 2
                ? self::reversePercent($values[0], $values[1])
                : null,
            default => null,
        };
    }

    private static function divideNamed(array $inputs, string $numerator, string $denominator): ?float
    {
        $a = self::number($inputs[$numerator] ?? null);
        $b = self::number($inputs[$denominator] ?? null);
        return $a !== null && $b !== null && $b != 0.0 ? $a / $b : null;
    }

    private static function subtractNamed(array $inputs, string $minuend, string $subtrahend): ?float
    {
        $a = self::number($inputs[$minuend] ?? null);
        $b = self::number($inputs[$subtrahend] ?? null);
        return $a !== null && $b !== null ? $a - $b : null;
    }

    private static function multiplyNamed(array $inputs, string $left, string $right): ?float
    {
        $a = self::number($inputs[$left] ?? null);
        $b = self::number($inputs[$right] ?? null);
        return $a !== null && $b !== null ? $a * $b : null;
    }

    private static function scaleNamed(array $inputs, string $key, float $factor): ?float
    {
        $value = self::number($inputs[$key] ?? null);
        return $value !== null ? $value * $factor : null;
    }

    private static function meanNamed(array $inputs, array $keys): ?float
    {
        $values = [];
        foreach ($keys as $key) {
            $value = self::number($inputs[$key] ?? null);
            if ($value === null) return null;
            $values[] = $value;
        }
        return array_sum($values) / count($values);
    }

    private static function interpolate(array $inputs): ?float
    {
        $xLow = self::number($inputs['xlow'] ?? null);
        $xHigh = self::number($inputs['xhigh'] ?? null);
        $x = self::number($inputs['xmid'] ?? null);
        $low = self::number($inputs['low'] ?? null);
        $high = self::number($inputs['high'] ?? null);
        if ($xLow === null || $xHigh === null || $x === null || $low === null || $high === null || $xHigh == $xLow) return null;
        return $low + (($x - $xLow) / ($xHigh - $xLow)) * ($high - $low);
    }

    private static function percentRatioNamed(array $inputs, string $numerator, string $denominator): ?float
    {
        $ratio = self::divideNamed($inputs, $numerator, $denominator);
        return $ratio !== null ? $ratio * 100.0 : null;
    }

    private static function inverseRateNamed(array $inputs, string $rate, float $scale): ?float
    {
        $value = self::number($inputs[$rate] ?? null);
        return $value !== null && $value != 0.0 ? $scale / $value : null;
    }

    private static function recognizeSequenceNext(?array $values): ?float
    {
        if ($values === null || count($values) < 3) return null;
        $candidates = [];
        $count = count($values);
        $integerValues = array_map(static fn(float $value): bool => abs($value - round($value)) < 1.0e-9, $values);
        if (!in_array(false, $integerValues, true)) {
            $ints = array_map('intval', $values);
            $roots = array_map(static fn(int $n): float => sqrt(max(0, $n)), $ints);
            if ($ints[0] >= 0 && self::isConsecutiveIntegers($roots)) $candidates[] = pow(round(end($roots)) + 1, 2);
            $cubes = array_map(static fn(int $n): float => $n < 0 ? -pow(abs($n), 1 / 3) : pow($n, 1 / 3), $ints);
            if (self::isConsecutiveIntegers($cubes)) $candidates[] = pow(round(end($cubes)) + 1, 3);
            $triRoots = array_map(static fn(int $n): float => (sqrt(8 * $n + 1) - 1) / 2, $ints);
            if ($ints[0] >= 0 && self::isConsecutiveIntegers($triRoots)) { $n = (int) round(end($triRoots)) + 1; $candidates[] = $n * ($n + 1) / 2; }
            if ($ints[0] === 1 && self::factorialSequence($ints)) $candidates[] = end($ints) * ($count + 1);
        }
        $differences = [];
        for ($i = 1; $i < $count; $i++) $differences[] = $values[$i] - $values[$i - 1];
        if (self::allNearEqual($differences)) $candidates[] = end($values) + end($differences);
        if ($values[0] != 0.0) {
            $ratios = [];
            for ($i = 1; $i < $count; $i++) { if ($values[$i - 1] == 0.0) { $ratios = []; break; } $ratios[] = $values[$i] / $values[$i - 1]; }
            if ($ratios !== [] && self::allNearEqual($ratios)) $candidates[] = end($values) * end($ratios);
        }
        $fibonacci = true;
        for ($i = 2; $i < $count; $i++) if (abs($values[$i] - $values[$i - 1] - $values[$i - 2]) > 1.0e-9) { $fibonacci = false; break; }
        if ($fibonacci) $candidates[] = end($values) + $values[$count - 2];
        $unique = [];
        foreach ($candidates as $candidate) $unique[sprintf('%.10g', $candidate)] = (float) $candidate;
        return count($unique) === 1 ? (float) reset($unique) : null;
    }

    private static function isConsecutiveIntegers(array $values): bool
    {
        if ($values === []) return false;
        $rounded = array_map('round', $values);
        foreach ($values as $index => $value) if (abs($value - $rounded[$index]) > 1.0e-9) return false;
        for ($index = 1; $index < count($rounded); $index++) if ((int) $rounded[$index] !== (int) $rounded[$index - 1] + 1) return false;
        return true;
    }

    private static function factorialSequence(array $values): bool
    {
        if ($values[0] !== 1) return false;
        for ($index = 1; $index < count($values); $index++) if ($values[$index] !== $values[$index - 1] * ($index + 1)) return false;
        return true;
    }

    private static function allNearEqual(array $values): bool
    {
        if (count($values) < 2) return false;
        foreach ($values as $value) if (abs($value - $values[0]) > 1.0e-9) return false;
        return true;
    }

    private static function reversePercent(float $remaining, float $reduction): ?float
    {
        $retainedFactor = $reduction > 1.0 ? 1.0 - ($reduction / 100.0) : $reduction;
        return $retainedFactor > 0.0 ? $remaining / $retainedFactor : null;
    }

    private static function solveTwoByTwo(array $payload): ?float
    {
        $equations = $payload['equations'] ?? null;
        if (!is_array($equations) || count($equations) !== 2) {
            return null;
        }
        $rows = [];
        foreach ($equations as $equation) {
            $row = self::numericList($equation);
            if ($row === null || count($row) !== 3) {
                return null;
            }
            $rows[] = $row;
        }
        [$a, $b, $c] = $rows[0];
        [$d, $e, $f] = $rows[1];
        $determinant = $a * $e - $b * $d;
        if ($determinant == 0.0) {
            return null;
        }
        return strtolower(self::text($payload['solve_for'] ?? 'x')) === 'y'
            ? ($a * $f - $c * $d) / $determinant
            : ($c * $e - $b * $f) / $determinant;
    }

    private static function calculateExpression(string $expression): ?float
    {
        $expression = str_replace(['×', '÷'], ['*', '/'], trim($expression));
        if ($expression === '' || preg_match('/\A[\d.\s()+\-*\/]+\z/', $expression) !== 1) {
            return null;
        }
        preg_match_all('/(?:\d+(?:\.\d*)?|\.\d+)|[()+\-*\/]/', $expression, $matches);
        $tokens = $matches[0] ?? [];
        if (implode('', $tokens) !== preg_replace('/\s+/', '', $expression)) {
            return null;
        }
        $position = 0;
        $value = self::parseExpressionTokens($tokens, $position);
        return $value !== null && $position === count($tokens) ? $value : null;
    }

    private static function parseExpressionTokens(array $tokens, int &$position): ?float
    {
        $value = self::parseTermTokens($tokens, $position);
        while ($value !== null && in_array($tokens[$position] ?? null, ['+', '-'], true)) {
            $operator = $tokens[$position++];
            $right = self::parseTermTokens($tokens, $position);
            if ($right === null) {
                return null;
            }
            $value = $operator === '+' ? $value + $right : $value - $right;
        }
        return $value;
    }

    private static function parseTermTokens(array $tokens, int &$position): ?float
    {
        $value = self::parseFactorTokens($tokens, $position);
        while ($value !== null && in_array($tokens[$position] ?? null, ['*', '/'], true)) {
            $operator = $tokens[$position++];
            $right = self::parseFactorTokens($tokens, $position);
            if ($right === null || ($operator === '/' && $right == 0.0)) {
                return null;
            }
            $value = $operator === '*' ? $value * $right : $value / $right;
        }
        return $value;
    }

    private static function parseFactorTokens(array $tokens, int &$position): ?float
    {
        $token = $tokens[$position] ?? null;
        if ($token === '+' || $token === '-') {
            $position++;
            $value = self::parseFactorTokens($tokens, $position);
            return $value === null || $token === '+' ? $value : -$value;
        }
        if ($token === '(') {
            $position++;
            $value = self::parseExpressionTokens($tokens, $position);
            if (($tokens[$position] ?? null) !== ')') {
                return null;
            }
            $position++;
            return $value;
        }
        if (!is_string($token) || !is_numeric($token)) {
            return null;
        }
        $position++;
        return (float) $token;
    }

    private static function numericList(mixed $values): ?array
    {
        if (!is_array($values) || $values === []) {
            return null;
        }
        $numbers = [];
        foreach ($values as $value) {
            $number = self::number($value);
            if ($number === null) {
                return null;
            }
            $numbers[] = $number;
        }
        return $numbers;
    }

    private static function answerValue(mixed $answer): ?array
    {
        if (!is_string($answer) && !is_numeric($answer)) {
            return null;
        }
        $answer = trim((string) $answer);
        if (preg_match('/\A((?:PHP|[₱P$])?)\s*([-+]?(?:\d{1,3}(?:,\d{3})+|\d+)(?:\.\d+)?(?:[eE][-+]?\d+)?)\s*(.*?)\z/iu', $answer, $matches) !== 1) {
            return null;
        }
        $suffix = trim((string) ($matches[3] ?? ''));
        $suffix = preg_replace('/\s+(?:favorable|unfavorable)\z/i', '', $suffix) ?? $suffix;
        $prefix = (string) ($matches[1] ?? '');
        return [
            'value' => (float) str_replace(',', '', $matches[2]),
            'unit' => $prefix !== '' ? $prefix : $suffix,
        ];
    }

    private static function normalizeAnswerUnit(float $answer, string $expectedUnit, string $answerUnit): ?float
    {
        $expected = self::unitKey($expectedUnit);
        $actual = self::unitKey($answerUnit);
        if ($expected === '' && $actual === '') {
            return $answer;
        }
        if ($expected === '' || $actual === '') {
            return null;
        }
        if ($expected === $actual) {
            return $answer;
        }
        if ($expected === 'fraction' && $actual === '%') {
            return $answer / 100;
        }
        if ($expected === 'mpa' && $actual === 'n/mm2') {
            return $answer;
        }
        if ($expected === 'n/mm2' && $actual === 'mpa') {
            return $answer;
        }
        return null;
    }

    private static function unitKey(string $unit): string
    {
        $unit = strtolower(trim($unit));
        $unit = str_replace(['²', '^2', '·', ' ', '−', '-'], ['2', '2', '', '', '-', ''], $unit);
        if (in_array($unit, ['₱', 'p', '$', 'peso', 'pesos'], true)) {
            return 'currency';
        }
        if ($unit === 'php') {
            return 'currency';
        }
        if (in_array($unit, ['percent', 'percentage'], true)) {
            return '%';
        }
        if (in_array($unit, ['kg', 'kilogram', 'kilograms'], true)) {
            return 'kg';
        }
        return $unit;
    }

    private static function tolerance(string $rounding, string $expectedUnit): float
    {
        if (preg_match('/(\d+)\s+decimal/i', $rounding, $matches) === 1) {
            $tolerance = 0.5 * (10 ** (-(int) $matches[1]));
            return self::unitKey($expectedUnit) === 'fraction'
                && str_contains(strtolower($rounding), 'percent')
                ? $tolerance / 100
                : $tolerance;
        }
        if (preg_match('/nearest\s+(?:whole|unit|peso|cent|[a-z\/·²^0-9-]+)/i', $rounding) === 1) {
            return strtolower($rounding) === 'nearest cent' ? 0.005 : 0.5;
        }
        return 1.0e-8;
    }

    private static function displayedDecimals(mixed $answer): ?int
    {
        if (!is_string($answer) && !is_numeric($answer)) {
            return null;
        }
        if (preg_match('/(?P<number>[-+]?(?:\\d{1,3}(?:,\\d{3})+|\\d+)(?:\\.(?P<decimals>\\d+))?(?:[eE](?P<exponent>[+-]?\\d+))?)/u', trim((string) $answer), $matches) !== 1
            || !isset($matches['decimals']) || $matches['decimals'] === '') {
            return null;
        }
        $places = strlen($matches['decimals']) - (int) ($matches['exponent'] ?? 0);
        return $places >= 0 && $places <= 12 ? $places : null;
    }

    private static function number(mixed $value): ?float
    {
        return is_numeric($value) && is_finite((float) $value) ? (float) $value : null;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private static function finding(string $class, string $code, string $board, string $id, string $note): array
    {
        return [
            'class' => $class,
            'code' => $code,
            'board' => $board,
            'entity_type' => 'question',
            'entity_id' => $id,
            'note' => $note,
        ];
    }
}
