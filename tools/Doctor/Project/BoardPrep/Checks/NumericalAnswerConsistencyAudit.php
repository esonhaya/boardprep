<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

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
            $computed = self::recompute($payload);
            if ($expected === null || $computed === null) {
                $metrics['unsupported']++;
                continue;
            }
            $metrics['recomputed']++;

            $rounding = (string) ($payload['rounding'] ?? 'exact');
            $expectedUnit = self::text($payload['unit'] ?? '');
            $tolerance = self::tolerance($rounding, $expectedUnit);
            $id = self::text($question['id'] ?? '');
            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            $board = self::text($taxonomy['board_id'] ?? $question['board'] ?? 'UNKNOWN');

            if (abs($computed - $expected) > $tolerance) {
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

            if (abs($computed - $normalizedAnswer) > $tolerance) {
                $findings[] = self::finding(
                    'REVIEW_REQUIRED',
                    'NUMERICAL_ANSWER_METADATA_DISAGREEMENT',
                    $board,
                    $id,
                    sprintf('Supported calculation gives %.12g, while the keyed answer gives %.12g (%s); verify the stated inputs and answer before editing.', $computed, $normalizedAnswer, $rounding)
                );
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

    private static function recompute(array $payload): ?float
    {
        $family = strtolower(self::text($payload['formula'] ?? $payload['family'] ?? $payload['operation'] ?? ''));
        $values = self::numericList($payload['values'] ?? $payload['operands'] ?? null);

        if ($family === 'moment_product') {
            $inputs = is_array($payload['inputs'] ?? null) ? $payload['inputs'] : [];
            $formulaFamily = strtolower(self::text($payload['formula_family'] ?? ''));
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
            $inputs = $payload['inputs'] ?? null;
            $values = self::numericList($inputs);
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
        if (preg_match('/\A([₱P$]?)\s*([-+]?(?:\d{1,3}(?:,\d{3})+|\d+)(?:\.\d+)?(?:[eE][-+]?\d+)?)\s*(.*?)\z/u', $answer, $matches) !== 1) {
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
