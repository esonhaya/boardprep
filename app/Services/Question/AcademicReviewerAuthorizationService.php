<?php

declare(strict_types=1);

namespace App\Services\Question;

use App\Contracts\StorageInterface;
use App\Core\App;
use RuntimeException;

/**
 * Resolves explicit, owner-provisioned reviewer grants.
 *
 * Grants are stored in the private application storage collection
 * `academic-reviewer-authorizations`. There is deliberately no HTTP grant
 * endpoint and no developer-role fallback. A normal account has no academic
 * permissions unless an operator provisions a qualified, scoped grant.
 */
final class AcademicReviewerAuthorizationService
{
    private const CAPABILITIES = ['REVIEW', 'SOURCE_REVIEW', 'TAXONOMY_RECONCILE', 'STUDY_LINK', 'EDIT_PROPOSAL', 'IMPORT'];
    private const NON_QUALIFICATIONS = ['DEVELOPER', 'ADMIN', 'OWNER', 'MAINTAINER', 'AI_REVIEWER', 'AUTOMATED', 'TEST_ONLY_EXPERT'];
    private StorageInterface $storage;

    public function __construct(?StorageInterface $storage = null, private ?array $grants = null)
    {
        $this->storage = $storage ?? App::storage();
    }

    /** @return list<array<string,mixed>> */
    public function grantsForAccount(string $accountId): array
    {
        if ($accountId === '') return [];
        if ($this->grants === null) $this->assertPrivateStoragePath();
        $rows = $this->grants ?? $this->storage->all('academic-reviewer-authorizations');
        return array_values(array_filter($rows, function (mixed $grant) use ($accountId): bool {
            if (!is_array($grant)) return false;
            $qualification = strtoupper(trim((string) ($grant['qualification_type'] ?? '')));
            $expiresAt = is_string($grant['expires_at'] ?? null) ? strtotime($grant['expires_at']) : false;
            return ($grant['account_id'] ?? null) === $accountId
                && ($grant['authorization_status'] ?? '') === 'ACTIVE'
                && trim((string) ($grant['qualification_type'] ?? '')) !== ''
                && !in_array($qualification, self::NON_QUALIFICATIONS, true)
                && trim((string) ($grant['qualification_reference'] ?? '')) !== ''
                && trim((string) ($grant['authorized_by'] ?? '')) !== ''
                && $this->validTimestamp($grant['authorized_at'] ?? null)
                && $this->validTimestamp($grant['expires_at'] ?? null)
                && is_int($expiresAt) && $expiresAt > time()
                && $this->validScopes($grant['scopes'] ?? null);
        }));
    }

    public function allows(string $accountId, string $boardId, string $subjectId, string $capability): bool
    {
        $capability = strtoupper(trim($capability));
        foreach ($this->grantsForAccount($accountId) as $grant) {
            foreach ($grant['scopes'] as $scope) {
                if (!is_array($scope)) continue;
                if (($scope['board_id'] ?? '') !== $boardId || ($scope['subject_id'] ?? '') !== $subjectId) continue;
                $capabilities = array_map('strtoupper', array_map('strval', (array) ($scope['capabilities'] ?? [])));
                if (in_array($capability, $capabilities, true)) return true;
            }
        }
        return false;
    }

    /** @return list<array{board_id:string,subject_id:string,capabilities:list<string>}> */
    public function scopesForAccount(string $accountId, string $capability = 'REVIEW'): array
    {
        $out = [];
        foreach ($this->grantsForAccount($accountId) as $grant) {
            foreach ($grant['scopes'] as $scope) {
                if (!is_array($scope)) continue;
                $caps = array_map('strtoupper', array_map('strval', (array) ($scope['capabilities'] ?? [])));
                if (!in_array(strtoupper($capability), $caps, true)) continue;
                $board = trim((string) ($scope['board_id'] ?? ''));
                $subject = trim((string) ($scope['subject_id'] ?? ''));
                if ($board === '' || $subject === '') continue;
                $out[$board . "\0" . $subject] = ['board_id' => $board, 'subject_id' => $subject, 'capabilities' => $caps];
            }
        }
        return array_values($out);
    }

    /** @return array<string,mixed>|null */
    public function grantFor(string $accountId, string $boardId, string $subjectId, string $capability): ?array
    {
        foreach ($this->grantsForAccount($accountId) as $grant) {
            foreach ($grant['scopes'] as $scope) {
                if (!is_array($scope)
                    || ($scope['board_id'] ?? '') !== $boardId
                    || ($scope['subject_id'] ?? '') !== $subjectId) continue;
                $caps = array_map('strtoupper', array_map('strval', (array) ($scope['capabilities'] ?? [])));
                if (in_array(strtoupper($capability), $caps, true)) return $grant;
            }
        }
        return null;
    }

    private function validTimestamp(mixed $value): bool
    {
        if (!is_string($value) || trim($value) === '') return false;
        try {
            new \DateTimeImmutable($value);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function validScopes(mixed $scopes): bool
    {
        if (!is_array($scopes) || $scopes === []) return false;
        foreach ($scopes as $scope) {
            if (!is_array($scope)
                || trim((string) ($scope['board_id'] ?? '')) === ''
                || trim((string) ($scope['subject_id'] ?? '')) === '') return false;
            $capabilities = array_map('strtoupper', array_map('strval', (array) ($scope['capabilities'] ?? [])));
            if ($capabilities === [] || array_diff($capabilities, self::CAPABILITIES) !== []) return false;
        }
        return true;
    }

    private function assertPrivateStoragePath(): void
    {
        $config = (array) App::config('database', []);
        $path = $config['path'] ?? null;
        if (!is_string($path) || trim($path) === '') throw new RuntimeException('ACADEMIC_REVIEW_STORAGE_UNAVAILABLE');
        $real = realpath($path);
        if (!is_string($real)) throw new RuntimeException('ACADEMIC_REVIEW_STORAGE_UNAVAILABLE');
        $public = realpath(dirname(__DIR__, 3) . '/public');
        if (is_string($public) && ($real === $public || str_starts_with($real, $public . DIRECTORY_SEPARATOR))) throw new RuntimeException('ACADEMIC_REVIEW_STORAGE_MUST_BE_PRIVATE');
    }
}
