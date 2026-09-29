<?php

declare(strict_types=1);

namespace App\Services\Quiz\LET;

use App\Core\App;

/**
 * The official specialization registry is separate from canonical question
 * identity.  It is deliberately data-driven so historical display labels do
 * not become new variant identities.
 */
final class LETVariantRegistry
{
    public const ELEMENTARY = 'ELEMENTARY';
    public const SECONDARY = 'SECONDARY';
    public const GENERAL_EDUCATION = 'GENERAL_EDUCATION';
    public const PROFESSIONAL_EDUCATION = 'PROFESSIONAL_EDUCATION';
    public const SPECIALIZATION = 'SPECIALIZATION';

    /** @return array<int,array<string,mixed>> */
    public static function specializations(): array
    {
        return array_values(array_filter(
            App::storage()->all('let-specializations'),
            static fn(mixed $record): bool => is_array($record)
        ));
    }

    public static function find(string $id): ?array
    {
        $id = self::normalizeId($id);
        foreach (self::specializations() as $record) {
            if ((string) ($record['id'] ?? '') === $id) {
                return $record;
            }
        }
        return null;
    }

    public static function resolveSpecialization(string $value): ?array
    {
        $needle = self::normalizeAlias($value);
        if ($needle === '') {
            return null;
        }
        foreach (self::specializations() as $record) {
            $values = array_merge(
                [(string) ($record['id'] ?? ''), (string) ($record['official_name'] ?? '')],
                is_array($record['aliases'] ?? null) ? $record['aliases'] : [],
                is_array($record['historical_labels'] ?? null) ? $record['historical_labels'] : []
            );
            foreach ($values as $candidate) {
                if (self::normalizeAlias((string) $candidate) === $needle) {
                    return $record;
                }
            }
        }
        return null;
    }

    /** @return array<string,mixed> */
    public static function validateConfiguration(?array $configuration): array
    {
        if ($configuration === null) {
            return ['valid' => true, 'errors' => [], 'configuration' => null];
        }

        $level = strtoupper(trim((string) ($configuration['level'] ?? '')));
        $specializationInput = trim((string) ($configuration['specialization_id'] ?? ''));
        $specialization = $specializationInput === ''
            ? null
            : self::resolveSpecialization($specializationInput);
        $errors = [];

        if (!in_array($level, [self::ELEMENTARY, self::SECONDARY], true)) {
            $errors['INVALID_LET_LEVEL'] = 'LET level must be ELEMENTARY or SECONDARY.';
        }

        if ($specializationInput !== '' && $specialization === null) {
            $errors['UNKNOWN_SPECIALIZATION'] = 'The LET specialization is not in the current official registry.';
        }

        if ($specialization !== null && $level !== '' && $level !== (string) ($specialization['level'] ?? '')) {
            $errors['INCOMPATIBLE_SPECIALIZATION_LEVEL'] = 'The specialization does not belong to the selected LET level.';
        }

        if ($level === self::SECONDARY && $specialization === null) {
            $errors['SECONDARY_SPECIALIZATION_REQUIRED'] = 'A Secondary LET candidate must choose an official specialization.';
        }

        if ($level === self::ELEMENTARY && ($configuration['component'] ?? null) === self::SPECIALIZATION && $specialization === null) {
            $errors['ELEMENTARY_SPECIALIZATION_REQUIRED'] = 'An Elementary specialization component requires an official specialization.';
        }

        $normalized = [
            'level' => $level,
            'specialization_id' => $specialization === null ? null : (string) $specialization['id'],
            'component' => isset($configuration['component']) && $configuration['component'] !== null
                ? strtoupper(trim((string) $configuration['component']))
                : null,
            'legacy' => false,
        ];
        if ($normalized['component'] !== null
            && !in_array($normalized['component'], [self::GENERAL_EDUCATION, self::PROFESSIONAL_EDUCATION, self::SPECIALIZATION], true)) {
            $errors['INVALID_LET_COMPONENT'] = 'LET component must be General Education, Professional Education, or Specialization.';
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'configuration' => $normalized,
            'specialization' => $specialization,
        ];
    }

    /**
     * Return the explicit or conservative shared placement of a LET question.
     * Existing records with no variant metadata remain shared GE/PE; subject
     * similarity never promotes them to a specialization.
     */
    public static function questionPlacement(array $question, ?array $eligibility = null): array
    {
        $placement = [];
        if (is_array($eligibility['placement'] ?? null)) {
            $placement = $eligibility['placement'];
        } elseif (is_array($question['let_placement'] ?? null)) {
            $placement = $question['let_placement'];
        } elseif (is_array($question['let_variant'] ?? null)) {
            $placement = $question['let_variant'];
        }

        $component = strtoupper(trim((string) ($placement['component'] ?? $placement['let_component'] ?? '')));
        $level = strtoupper(trim((string) ($placement['level'] ?? $placement['let_level'] ?? '')));
        $specialization = trim((string) ($placement['specialization_id'] ?? ''));
        if ($specialization !== '') {
            $resolved = self::resolveSpecialization($specialization);
            $specialization = $resolved === null ? $specialization : (string) $resolved['id'];
        }

        if ($component === '') {
            $subject = strtolower(trim((string) ($eligibility['subject_id'] ?? $question['taxonomy']['subject_id'] ?? $question['subject'] ?? '')));
            $component = $subject === 'professional-education'
                ? self::PROFESSIONAL_EDUCATION
                : self::GENERAL_EDUCATION;
        }

        return [
            'level' => $level === '' ? null : $level,
            'component' => $component,
            'specialization_id' => $specialization === '' ? null : $specialization,
            'shared' => $specialization === '',
        ];
    }

    public static function matchesQuestion(
        array $question,
        ?array $configuration,
        ?string $component = null,
        ?array $eligibility = null
    ): bool {
        if ($configuration === null) {
            return true;
        }

        $placement = self::questionPlacement($question, $eligibility);
        $requestedLevel = strtoupper(trim((string) ($configuration['level'] ?? '')));
        $requestedSpecialization = (string) ($configuration['specialization_id'] ?? '');
        $requestedComponent = $component === null || trim($component) === ''
            ? (string) ($configuration['component'] ?? '')
            : strtoupper(trim($component));

        if ($placement['level'] !== null && $placement['level'] !== $requestedLevel) {
            return false;
        }
        if ($placement['component'] !== $requestedComponent && $requestedComponent !== '') {
            return false;
        }
        if ($placement['component'] === self::SPECIALIZATION) {
            return $requestedSpecialization !== ''
                && $placement['specialization_id'] === $requestedSpecialization
                && $requestedLevel === (string) ($placement['level'] ?? $requestedLevel);
        }

        return $placement['specialization_id'] === null;
    }

    public static function normalizeId(string $value): string
    {
        return strtolower(trim($value));
    }

    public static function normalizeAlias(string $value): string
    {
        $value = strtolower(trim($value));
        return preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;
    }

    /** @return array<string,mixed> */
    public static function officialBlueprint(?array $configuration): array
    {
        $level = strtoupper(trim((string) ($configuration['level'] ?? '')));
        $specialization = (string) ($configuration['specialization_id'] ?? '');
        if ($level === self::ELEMENTARY && $specialization === '') {
            return ['level' => self::ELEMENTARY, 'components' => [self::GENERAL_EDUCATION => 40, self::PROFESSIONAL_EDUCATION => 60]];
        }
        return [
            'level' => $level,
            'specialization_id' => $specialization === '' ? null : $specialization,
            'components' => [self::GENERAL_EDUCATION => 20, self::PROFESSIONAL_EDUCATION => 40, self::SPECIALIZATION => 40],
        ];
    }
}
