<?php

declare(strict_types=1);

namespace App\Services\Accounts;

use App\Core\App;
use InvalidArgumentException;
use RuntimeException;

final class AccountService
{
    private const LOGIN_WINDOW = 900;
    private const LOGIN_IDENTIFIER_LIMIT = 8;
    private const LOGIN_IP_LIMIT = 24;
    private const REGISTER_WINDOW = 3600;
    private const REGISTER_IP_LIMIT = 5;
    private const RECOVERY_IP_LIMIT = 10;
    private const REAUTH_WINDOW = 900;
    private const REAUTH_ACCOUNT_LIMIT = 8;
    private const REAUTH_IP_LIMIT = 24;
    private const RECOVERY_CODE_COUNT = 8;

    public function __construct(private ?AccountRepository $repository = null)
    {
        $this->repository ??= new AccountRepository();
    }

    /** @return array{account:array|null,recovery_codes:list<string>} */
    public function register(string $usernameInput, string $password): array
    {
        $username = self::normalizeUsername($usernameInput);
        if ($username === null) {
            throw new InvalidArgumentException('Choose a username using 3–32 letters, numbers, periods, underscores, or hyphens.');
        }
        $ip = $this->clientAddressKey();
        $ipBucket = $this->bucket('register-ip', $ip);
        if (!$this->repository->reserveAttempts([$ipBucket => self::REGISTER_IP_LIMIT], self::REGISTER_WINDOW)) {
            throw new InvalidArgumentException('Account creation is temporarily limited. Try again later.');
        }
        AccountPasswordPolicy::validate($password);

        $codes = self::newRecoveryCodes();
        $now = gmdate(DATE_ATOM);
        $account = [
            'account_id' => 'account-' . bin2hex(random_bytes(16)),
            'learner_id' => 'account-learner-' . bin2hex(random_bytes(16)),
            'username' => $username,
            'password_hash' => AccountPasswordPolicy::hash($password),
            'created_at' => $now,
            'updated_at' => $now,
            'auth_version' => 1,
            'status' => 'active',
            'deletion_requested_at' => null,
            'preferences' => self::defaultPreferences(),
            'recovery_code_hashes' => array_map(static fn(string $code): string => hash('sha256', self::normalizeRecoveryCode($code)), $codes),
        ];

        $created = $this->repository->create($account);

        return [
            // An occupied username gets the same response shape. Its displayed
            // codes are intentionally unbound and cannot recover that account.
            'account' => $created ? self::publicRecord($account) : null,
            'recovery_codes' => $codes,
        ];
    }

    public function signIn(string $usernameInput, string $password): bool
    {
        $normalized = self::normalizeUsername($usernameInput);
        $candidate = strtolower(trim($usernameInput));
        $ip = $this->clientAddressKey();
        $buckets = [
            $this->bucket('login-identifier', $candidate) => self::LOGIN_IDENTIFIER_LIMIT,
            $this->bucket('login-ip', $ip) => self::LOGIN_IP_LIMIT,
        ];
        if (!$this->repository->reserveAttempts($buckets, self::LOGIN_WINDOW)) {
            return false;
        }
        if (strlen($password) > 1024) {
            return false;
        }

        $account = $normalized !== null ? $this->repository->findByUsername($normalized) : null;
        $hash = is_array($account) && is_string($account['password_hash'] ?? null)
            ? $account['password_hash']
            : self::dummyHash();
        $verified = AccountPasswordPolicy::verify($password, $hash);
        if (!$verified || !is_array($account) || ($account['status'] ?? '') !== 'active') {
            return false;
        }

        if (AccountPasswordPolicy::needsRehash($hash)) {
            $rehash = AccountPasswordPolicy::hash($password);
            $account = $this->repository->updateById((string) $account['account_id'], static function (array $current) use ($rehash): array {
                $current['password_hash'] = $rehash;
                $current['updated_at'] = gmdate(DATE_ATOM);
                return $current;
            }) ?? $account;
        }
        AccountSessionService::login($account);
        return true;
    }

    public function changePassword(string $currentPassword, string $newPassword): bool
    {
        $session = AccountSessionService::current();
        if (!is_array($session) || !$this->reserveReauthentication($session)) {
            return false;
        }
        AccountPasswordPolicy::validate($newPassword);
        $account = $this->repository->findById((string) $session['account_id']);
        if (!is_array($account) || !AccountPasswordPolicy::verify($currentPassword, (string) ($account['password_hash'] ?? ''))) {
            return false;
        }

        $hash = AccountPasswordPolicy::hash($newPassword);
        $changed = false;
        $updated = $this->repository->updateById((string) $session['account_id'], static function (array $current) use ($currentPassword, $hash, &$changed): array {
            if (!is_string($current['password_hash'] ?? null)
                || !AccountPasswordPolicy::verify($currentPassword, $current['password_hash'])) {
                return $current;
            }
            $current['password_hash'] = $hash;
            $current['auth_version'] = (int) ($current['auth_version'] ?? 0) + 1;
            $current['updated_at'] = gmdate(DATE_ATOM);
            $changed = true;
            return $current;
        });
        if (!is_array($updated) || !$changed) {
            return false;
        }
        AccountSessionService::refresh($updated);
        return true;
    }

    public function rotateRecoveryCodes(string $currentPassword): ?array
    {
        $session = AccountSessionService::current();
        if (!is_array($session) || !$this->reserveReauthentication($session)) {
            return null;
        }
        $account = $this->repository->findById((string) $session['account_id']);
        if (!is_array($account) || !AccountPasswordPolicy::verify($currentPassword, (string) ($account['password_hash'] ?? ''))) {
            return null;
        }
        $codes = self::newRecoveryCodes();
        $hashes = array_map(static fn(string $code): string => hash('sha256', self::normalizeRecoveryCode($code)), $codes);
        $rotated = false;
        $updated = $this->repository->updateById((string) $session['account_id'], static function (array $current) use ($currentPassword, $hashes, &$rotated): array {
            if (($current['status'] ?? '') !== 'active'
                || !is_string($current['password_hash'] ?? null)
                || !AccountPasswordPolicy::verify($currentPassword, $current['password_hash'])) {
                return $current;
            }
            $current['recovery_code_hashes'] = $hashes;
            $current['updated_at'] = gmdate(DATE_ATOM);
            $rotated = true;
            return $current;
        });
        if (!is_array($updated) || !$rotated) {
            return null;
        }
        AccountSessionService::refresh($updated);
        return $codes;
    }

    public function recover(string $usernameInput, string $recoveryCode, string $newPassword): bool
    {
        if (strlen($recoveryCode) > 256) {
            $recoveryCode = '';
        }
        $normalized = self::normalizeUsername($usernameInput);
        $candidate = self::normalizeRecoveryCode($recoveryCode);
        $buckets = [
            $this->bucket('recovery-identifier', strtolower(trim($usernameInput))) => self::RECOVERY_IP_LIMIT,
            $this->bucket('recovery-ip', $this->clientAddressKey()) => self::RECOVERY_IP_LIMIT,
        ];
        $allowed = $this->repository->reserveAttempts($buckets, self::LOGIN_WINDOW);
        $account = $normalized !== null ? $this->repository->findByUsername($normalized) : null;
        $matchHash = hash('sha256', $candidate);
        $matches = false;
        foreach ((array) ($account['recovery_code_hashes'] ?? []) as $storedHash) {
            if (is_string($storedHash) && hash_equals($storedHash, $matchHash)) {
                $matches = true;
            }
        }
        try {
            AccountPasswordPolicy::validate($newPassword);
        } catch (InvalidArgumentException) {
            return false;
        }
        if (!$allowed || !$matches || !is_array($account) || ($account['status'] ?? '') !== 'active') {
            self::dummyVerify($newPassword);
            return false;
        }

        $passwordHash = AccountPasswordPolicy::hash($newPassword);
        $consumed = false;
        $this->repository->updateByUsername($normalized, static function (array $current) use ($matchHash, $passwordHash, &$consumed): array {
            if (($current['status'] ?? '') !== 'active') {
                return $current;
            }
            $hashes = (array) ($current['recovery_code_hashes'] ?? []);
            $index = null;
            foreach ($hashes as $candidateIndex => $storedHash) {
                if (is_string($storedHash) && hash_equals($storedHash, $matchHash)) {
                    $index = $candidateIndex;
                    break;
                }
            }
            if ($index === null) {
                return $current;
            }
            array_splice($hashes, (int) $index, 1);
            $current['recovery_code_hashes'] = $hashes;
            $current['password_hash'] = $passwordHash;
            $current['auth_version'] = (int) ($current['auth_version'] ?? 0) + 1;
            $current['updated_at'] = gmdate(DATE_ATOM);
            $consumed = true;
            return $current;
        });
        return $consumed;
    }

    public function updatePreferences(array $preferences): bool
    {
        $session = AccountSessionService::current();
        if (!is_array($session)) {
            return false;
        }
        return $this->repository->updateById((string) $session['account_id'], static function (array $account) use ($preferences): array {
            $account['preferences'] = $preferences;
            $account['updated_at'] = gmdate(DATE_ATOM);
            return $account;
        }) !== null;
    }

    public function requestDeletion(string $currentPassword): bool
    {
        $session = AccountSessionService::current();
        if (!is_array($session) || !$this->reserveReauthentication($session)) {
            return false;
        }
        $account = $this->repository->findById((string) $session['account_id']);
        if (!is_array($account) || !AccountPasswordPolicy::verify($currentPassword, (string) ($account['password_hash'] ?? ''))) {
            return false;
        }
        $requested = false;
        $updated = $this->repository->updateById((string) $session['account_id'], static function (array $current) use ($currentPassword, &$requested): array {
            if (($current['status'] ?? '') !== 'active'
                || !is_string($current['password_hash'] ?? null)
                || !AccountPasswordPolicy::verify($currentPassword, $current['password_hash'])) {
                return $current;
            }
            $current['status'] = 'deletion_requested';
            $current['deletion_requested_at'] = gmdate(DATE_ATOM);
            $current['auth_version'] = (int) ($current['auth_version'] ?? 0) + 1;
            $current['updated_at'] = gmdate(DATE_ATOM);
            $requested = true;
            return $current;
        });
        return is_array($updated) && $requested;
    }

    /** Re-authenticate sensitive account actions without allowing unlimited password guesses. */
    public function verifyCurrentPassword(string $password): bool
    {
        $session = AccountSessionService::current();
        if (!is_array($session) || !$this->reserveReauthentication($session)) {
            return false;
        }
        $account = $this->repository->findById((string) $session['account_id']);
        $hash = is_array($account) && is_string($account['password_hash'] ?? null)
            ? $account['password_hash']
            : self::dummyHash();
        $verified = AccountPasswordPolicy::verify($password, $hash);
        return $verified && is_array($account) && ($account['status'] ?? '') === 'active';
    }

    public static function currentRecord(): ?array
    {
        $session = AccountSessionService::current();
        return is_array($session) ? (new AccountRepository())->findById((string) $session['account_id']) : null;
    }

    public static function publicRecord(array $account): array
    {
        return [
            'account_id' => (string) ($account['account_id'] ?? ''),
            'learner_id' => (string) ($account['learner_id'] ?? ''),
            'username' => (string) ($account['username'] ?? ''),
            'created_at' => (string) ($account['created_at'] ?? ''),
            'updated_at' => (string) ($account['updated_at'] ?? ''),
            'status' => (string) ($account['status'] ?? ''),
            'preferences' => is_array($account['preferences'] ?? null) ? $account['preferences'] : self::defaultPreferences(),
        ];
    }

    public static function normalizeUsername(string $username): ?string
    {
        $username = strtolower(trim($username));
        return preg_match('/\A[a-z0-9][a-z0-9._-]{2,31}\z/', $username) === 1 ? $username : null;
    }

    public static function normalizeRecoveryCode(string $code): string
    {
        return strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', trim($code)) ?? '');
    }

    public static function defaultPreferences(): array
    {
        return [
            'preferred_board' => '',
            'preferred_subject' => '',
            'let_level' => '',
            'let_specialization' => '',
            'civil_service_level' => '',
            'subject_interests' => [],
        ];
    }

    private static function newRecoveryCodes(): array
    {
        $codes = [];
        for ($index = 0; $index < self::RECOVERY_CODE_COUNT; $index++) {
            $raw = strtoupper(bin2hex(random_bytes(20)));
            $codes[] = implode('-', str_split($raw, 8));
        }
        return $codes;
    }

    private function clientAddressKey(): string
    {
        $address = trim((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        if (filter_var($address, FILTER_VALIDATE_IP) === false) {
            $address = 'unknown';
        }
        return $address;
    }

    private function reserveReauthentication(array $session): bool
    {
        $accountId = (string) ($session['account_id'] ?? '');
        return $accountId !== '' && $this->repository->reserveAttempts([
            $this->bucket('reauth-account', $accountId) => self::REAUTH_ACCOUNT_LIMIT,
            $this->bucket('reauth-ip', $this->clientAddressKey()) => self::REAUTH_IP_LIMIT,
        ], self::REAUTH_WINDOW);
    }

    private function bucket(string $scope, string $value): string
    {
        return $scope . ':' . hash('sha256', strtolower(trim($value)));
    }

    private static function dummyHash(): string
    {
        static $hash = null;
        if (!is_string($hash)) {
            $hash = AccountPasswordPolicy::hash('BoardPrep non-existent account timing sentinel');
        }
        return $hash;
    }

    private static function dummyVerify(string $candidate): void
    {
        AccountPasswordPolicy::verify($candidate, self::dummyHash());
    }
}
