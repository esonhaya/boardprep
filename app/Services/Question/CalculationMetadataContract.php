<?php

declare(strict_types=1);

namespace App\Services\Question;

/**
 * Shared calculation-metadata boundary for authoring, validation, and health.
 *
 * The repository has three established producer shapes: a calculation
 * payload (expression/result, operation/expected, or family/params), a
 * legacy family-only classification, and a fully absent calculation record.
 * The first two are supported; the last is invalid when the explicit
 * calculation_required flag is true.
 */
final class CalculationMetadataContract
{
    /**
     * @return array{valid: bool, code: string|null, family: string|null, form: string, message: string}
     */
    public static function inspect(array $question): array
    {
        $required = ($question['calculation_required'] ?? false) === true;
        $payload = $question['calculation'] ?? null;
        $payload = is_array($payload) ? $payload : null;
        $family = trim((string) ($question['calculation_family'] ?? ''));
        if ($family === '' && is_array($payload)) {
            $family = trim((string) ($payload['family'] ?? ''));
        }

        if (!$required) {
            return self::result(true, null, $family !== '' ? $family : null, $payload === null ? 'none' : 'payload', '');
        }

        if ($payload !== null && $payload !== [] && self::hasPayloadMarker($payload)) {
            return self::result(true, null, $family !== '' ? $family : null, 'payload', '');
        }

        if ($family !== '') {
            return self::result(true, null, $family, 'legacy_family', '');
        }

        return self::result(
            false,
            'MISSING_CALCULATION_PAYLOAD',
            null,
            'missing',
            'Calculation is required but no canonical calculation family or payload is present.'
        );
    }

    /** @param array<string, mixed> $payload */
    private static function hasPayloadMarker(array $payload): bool
    {
        foreach (['expression', 'operation', 'family'] as $field) {
            if (is_scalar($payload[$field] ?? null) && trim((string) $payload[$field]) !== '') {
                return true;
            }
        }

        foreach (['result', 'expected'] as $field) {
            if (array_key_exists($field, $payload) && is_scalar($payload[$field])) {
                return trim((string) $payload[$field]) !== '';
            }
        }

        return false;
    }

    /** @return array{valid: bool, code: string|null, family: string|null, form: string, message: string} */
    private static function result(bool $valid, ?string $code, ?string $family, string $form, string $message): array
    {
        return [
            'valid' => $valid,
            'code' => $code,
            'family' => $family,
            'form' => $form,
            'message' => $message,
        ];
    }
}
