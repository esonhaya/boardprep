<?php

declare(strict_types=1);

namespace App\Services\Profile;

use App\Services\Board\BoardViewService;
use App\Services\Quiz\LET\LETVariantRegistry;

/** Stable choices for an anonymous session or a durable authenticated profile. */
final class LearnerPreferenceService
{
    public static function get(): array
    {
        if (\App\Services\Accounts\AccountFeatureGate::enabled()) {
            $account = \App\Services\Accounts\AccountSessionService::current();
            if (is_array($account)) {
                return array_replace(self::defaults(), is_array($account['preferences'] ?? null) ? $account['preferences'] : []);
            }
        }
        $stored = \SessionService::get('learner_preferences', []);
        return array_replace(self::defaults(), is_array($stored) ? $stored : []);
    }

    public static function save(array $input): array
    {
        $preferences = self::defaults();
        $boardInput = self::text($input['preferred_board'] ?? '');
        if ($boardInput !== '') {
            foreach (BoardViewService::all() as $board) {
                if (strcasecmp($boardInput, (string) ($board['code'] ?? '')) === 0
                    || strcasecmp($boardInput, (string) ($board['id'] ?? '')) === 0) {
                    $preferences['preferred_board'] = (string) ($board['code'] ?? $board['id'] ?? '');
                    break;
                }
            }
            if ($preferences['preferred_board'] === '') {
                throw new \InvalidArgumentException('Choose a board from the list.');
            }
        }

        $preferences['preferred_subject'] = strtolower(self::text($input['preferred_subject'] ?? ''));
        if ($preferences['preferred_subject'] !== '') {
            if ($preferences['preferred_board'] === '') {
                throw new \InvalidArgumentException('Choose a preferred board before choosing a preferred subject.');
            }
            $allowed = [];
            foreach (BoardViewService::all() as $board) {
                if (strcasecmp((string) ($board['code'] ?? ''), $preferences['preferred_board']) === 0) {
                    $allowed = array_map(static fn(array $subject): string => strtolower((string) ($subject['id'] ?? '')), (array) ($board['practice_subjects'] ?? []));
                    break;
                }
            }
            if (!in_array($preferences['preferred_subject'], $allowed, true)) {
                throw new \InvalidArgumentException('That subject is not available for the preferred board.');
            }
        }

        $preferences['let_level'] = strtoupper(self::text($input['let_level'] ?? ''));
        $preferences['let_specialization'] = self::text($input['let_specialization'] ?? '');
        if ($preferences['let_level'] !== '' || $preferences['let_specialization'] !== '') {
            $validation = LETVariantRegistry::validateConfiguration([
                'level' => $preferences['let_level'],
                'specialization_id' => $preferences['let_specialization'],
            ]);
            if (($validation['valid'] ?? false) !== true) {
                throw new \InvalidArgumentException(implode(' ', array_values($validation['errors'] ?? [])));
            }
            $preferences['let_level'] = (string) ($validation['configuration']['level'] ?? '');
            $preferences['let_specialization'] = (string) ($validation['configuration']['specialization_id'] ?? '');
        }

        $preferences['civil_service_level'] = strtoupper(self::text($input['civil_service_level'] ?? ''));
        if ($preferences['civil_service_level'] !== ''
            && !in_array($preferences['civil_service_level'], ['PROFESSIONAL', 'SUBPROFESSIONAL'], true)) {
            throw new \InvalidArgumentException('Choose Professional or Subprofessional.');
        }
        $interests = is_array($input['subject_interests'] ?? null) ? $input['subject_interests'] : [];
        $knownSubjects = array_fill_keys(array_map(static fn(array $row): string => strtolower((string) ($row['id'] ?? '')), \App\Services\Shared\TaxonomyStorageService::subjects()), true);
        foreach ($interests as $interest) {
            if (!is_scalar($interest) || !isset($knownSubjects[strtolower(trim((string) $interest))])) {
                throw new \InvalidArgumentException('Choose subject interests from the available list.');
            }
        }
        $preferences['subject_interests'] = array_values(array_unique(array_map(static fn(mixed $value): string => strtolower(trim((string) $value)), $interests)));

        if (\App\Services\Accounts\AccountFeatureGate::enabled()
            && \App\Services\Accounts\AccountSessionService::current() !== null) {
            if (!(new \App\Services\Accounts\AccountService())->updatePreferences($preferences)) {
                throw new \RuntimeException('Your account preferences could not be saved. Please try again.');
            }
        } else {
            \SessionService::set('learner_preferences', $preferences);
        }
        return $preferences;
    }

    public static function applyToQuizInput(array $input): array
    {
        $preferences = self::get();
        $explicitBoard = array_key_exists('exam', $input) ? strtolower(trim((string) ($input['exam'] ?? ''))) : '';
        $preferredBoard = strtolower(trim((string) ($preferences['preferred_board'] ?? '')));
        foreach ([
            'exam' => 'preferred_board',
            'let_level' => 'let_level',
            'let_specialization' => 'let_specialization',
            'civil_service_level' => 'civil_service_level',
        ] as $inputKey => $preferenceKey) {
            if (!array_key_exists($inputKey, $input) && ($preferences[$preferenceKey] ?? '') !== '') {
                $input[$inputKey] = $preferences[$preferenceKey];
            }
        }
        if (!array_key_exists('subject', $input)
            && ($explicitBoard === '' || ($preferredBoard !== '' && $explicitBoard === $preferredBoard))
            && ($preferences['preferred_subject'] ?? '') !== '') {
            $input['subject'] = $preferences['preferred_subject'];
        }
        return $input;
    }

    private static function defaults(): array
    {
        return ['preferred_board' => '', 'preferred_subject' => '', 'let_level' => '', 'let_specialization' => '', 'civil_service_level' => '', 'subject_interests' => []];
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
